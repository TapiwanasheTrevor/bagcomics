<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Stripe\StripeClient;
use Stripe\PaymentIntent;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PaymentService $paymentService;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->paymentService = app(PaymentService::class);
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['price' => 9.99, 'is_free' => false]);
    }

    /** @test */
    public function it_can_create_single_comic_payment_intent()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/payments/comics/{$this->comic->slug}/intent");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'amount' => 9.99,
            'status' => 'pending',
            'payment_type' => 'single',
        ]);
    }

    /** @test */
    public function it_can_create_bundle_payment_intent()
    {
        $comics = Comic::factory()->count(3)->create(['price' => 5.00, 'is_free' => false]);
        $this->actingAs($this->user);

        $response = $this->postJson('/payments/bundle/intent', [
            'comic_ids' => $comics->pluck('id')->toArray(),
            'discount_percent' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'bundle_info' => [
                    'comic_count',
                    'original_price',
                    'discounted_price',
                    'savings',
                ],
            ]);

        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'payment_type' => 'bundle',
            'bundle_discount_percent' => 15.00,
        ]);
    }

    /** @test */
    public function it_can_create_subscription_payment_intent()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/payments/subscription/intent', [
            'subscription_type' => 'monthly',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'subscription_info' => [
                    'type',
                    'price',
                    'benefits',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => null,
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
            'amount' => 9.99,
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_comic_purchases()
    {
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchased_at' => now(),
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/payments/comics/{$this->comic->slug}/intent");

        $response->assertStatus(400)
            ->assertJson(['error' => 'User already has access to this comic']);
    }

    /** @test */
    public function it_prevents_payment_for_free_comics()
    {
        $freeComic = Comic::factory()->create(['is_free' => true]);
        $this->actingAs($this->user);

        $response = $this->postJson("/payments/comics/{$freeComic->slug}/intent");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Cannot create payment intent for free comic']);
    }

    /** @test */
    public function it_can_confirm_successful_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'pending',
            'payment_type' => 'single',
        ]);

        $this->actingAs($this->user);

        // Mock successful Stripe payment intent
        $this->mockStripePaymentIntent($payment->stripe_payment_intent_id, 'succeeded');

        $response = $this->postJson('/payments/confirm', [
            'payment_intent_id' => $payment->stripe_payment_intent_id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment confirmed and access granted',
            ]);

        $payment->refresh();
        $this->assertEquals('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);

        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);
    }

    /** @test */
    public function it_can_get_payment_history()
    {
        Payment::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
        ]);

        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/payments/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payments' => [
                    '*' => [
                        'id',
                        'status',
                        'type',
                        'amount',
                        'currency',
                        'paid_at',
                        'comic',
                    ],
                ],
                'pagination',
            ]);

        $this->assertCount(7, $response->json('payments'));
    }

    /** @test */
    public function it_can_filter_payment_history()
    {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'payment_type' => 'single',
        ]);

        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'payment_type' => 'subscription',
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/payments/history?payment_type=single');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('payments'));
    }

    /** @test */
    public function it_can_request_refund()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->user);

        // Mock Stripe refund
        $this->mockStripeRefund($payment->stripe_payment_intent_id);

        $response = $this->postJson("/payments/{$payment->id}/refund");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);

        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
    }

    /** @test */
    public function it_can_retry_failed_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'retry_count' => 1,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/payments/{$payment->id}/retry");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'message',
            ]);

        $payment->refresh();
        $this->assertEquals(2, $payment->retry_count);
        $this->assertNotNull($payment->last_retry_at);
    }

    /** @test */
    public function it_prevents_retry_after_max_attempts()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'retry_count' => 3,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/payments/{$payment->id}/retry");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Payment cannot be retried']);
    }

    /** @test */
    public function it_grants_subscription_access_on_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => null,
            'status' => 'pending',
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
        ]);

        $this->actingAs($this->user);

        // Mock successful Stripe payment intent
        $this->mockStripePaymentIntent($payment->stripe_payment_intent_id, 'succeeded');

        $response = $this->postJson('/payments/confirm', [
            'payment_intent_id' => $payment->stripe_payment_intent_id,
        ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals('monthly', $this->user->subscription_type);
        $this->assertEquals('active', $this->user->subscription_status);
        $this->assertNotNull($this->user->subscription_expires_at);
    }

    /** @test */
    public function subscription_user_has_access_to_all_comics()
    {
        $this->user->update([
            'subscription_type' => 'monthly',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->user->hasAccessToComic($this->comic));
    }

    /** @test */
    public function expired_subscription_user_loses_access()
    {
        $this->user->update([
            'subscription_type' => 'monthly',
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->user->hasAccessToComic($this->comic));
    }

    /** @test */
    public function it_validates_bundle_purchase_requirements()
    {
        $this->actingAs($this->user);

        // Test with single comic (should fail)
        $response = $this->postJson('/payments/bundle/intent', [
            'comic_ids' => [$this->comic->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comic_ids']);

        // Test with invalid comic IDs
        $response = $this->postJson('/payments/bundle/intent', [
            'comic_ids' => [999, 1000],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comic_ids.0', 'comic_ids.1']);
    }

    /** @test */
    public function it_validates_subscription_type()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/payments/subscription/intent', [
            'subscription_type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subscription_type']);
    }

    private function mockStripePaymentIntent(string $paymentIntentId, string $status): void
    {
        $mockPaymentIntent = new PaymentIntent($paymentIntentId);
        $mockPaymentIntent->status = $status;
        $mockPaymentIntent->payment_method = 'pm_test_123';

        $this->mock(StripeClient::class, function ($mock) use ($mockPaymentIntent) {
            $mock->paymentIntents = new class($mockPaymentIntent) {
                private $paymentIntent;
                
                public function __construct($paymentIntent) {
                    $this->paymentIntent = $paymentIntent;
                }
                
                public function retrieve($id) {
                    return $this->paymentIntent;
                }
            };
        });
    }

    private function mockStripeRefund(string $paymentIntentId): void
    {
        $mockRefund = new \Stripe\Refund('re_test_123');
        $mockRefund->status = 'succeeded';
        $mockRefund->amount = 999; // $9.99 in cents

        $this->mock(StripeClient::class, function ($mock) use ($mockRefund) {
            $mock->refunds = new class($mockRefund) {
                private $refund;
                
                public function __construct($refund) {
                    $this->refund = $refund;
                }
                
                public function create($params) {
                    return $this->refund;
                }
            };
        });
    }
}