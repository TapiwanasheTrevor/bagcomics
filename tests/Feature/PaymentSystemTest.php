<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'price' => 9.99,
            'is_free' => false,
            'is_visible' => true,
        ]);
    }

    /** @test */
    public function it_can_create_single_comic_payment_intent(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentIntent = (object) [
            'id' => 'pi_test_123',
            'client_secret' => 'pi_test_123_secret_456',
        ];

        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn($mockPaymentIntent);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent");

        $response->assertStatus(200)
            ->assertJson([
                'payment_intent' => [
                    'id' => 'pi_test_123',
                ],
                'client_secret' => 'pi_test_123_secret_456',
            ]);
    }

    /** @test */
    public function it_surfaces_duplicate_purchase_errors_from_payment_intent_creation(): void
    {
        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andThrow(new \InvalidArgumentException('User already has access to this comic'));

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$this->comic->id}/intent");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'PAYMENT_INTENT_FAILED');
    }

    /** @test */
    public function it_surfaces_free_comic_payment_errors(): void
    {
        $freeComic = Comic::factory()->create(['is_free' => true, 'is_visible' => true]);

        Sanctum::actingAs($this->user);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andThrow(new \InvalidArgumentException('Cannot create payment intent for free comic'));

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson("/api/payments/comics/{$freeComic->id}/intent");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'PAYMENT_INTENT_FAILED');
    }

    /** @test */
    public function it_can_confirm_successful_payment(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
        ]);

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with(Mockery::type(User::class), 'pi_test_123')
            ->andReturn($payment);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_test_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'type',
                    'amount',
                ],
                'message',
            ]);
    }

    /** @test */
    public function it_can_get_payment_history_and_filters(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
        ]);

        Payment::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'succeeded',
        ]);

        $response = $this->getJson('/api/payments/history?status=succeeded');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination',
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('succeeded', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_request_refund_and_restrict_other_users(): void
    {
        Sanctum::actingAs($this->user);

        $ownedPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
        ]);

        $mockRefund = (object) [
            'id' => 'ref_123',
            'amount' => 999,
        ];

        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('refundPayment')
            ->once()
            ->andReturn($mockRefund);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        $refundResponse = $this->postJson("/api/payments/{$ownedPayment->id}/refund", [
            'reason' => 'Changed my mind',
        ]);

        $refundResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Refund request submitted successfully',
            ]);

        $otherUserPayment = Payment::factory()->create([
            'user_id' => User::factory()->create()->id,
            'comic_id' => $this->comic->id,
        ]);

        $deniedResponse = $this->postJson("/api/payments/{$otherUserPayment->id}/refund");
        $deniedResponse->assertStatus(403);
    }

    /** @test */
    public function it_can_get_user_payment_statistics(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'amount' => 9.99,
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'refunded',
            'amount' => 4.99,
            'refund_amount' => 4.99,
        ]);

        $response = $this->getJson('/api/payments/statistics/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_spent',
                'total_purchases',
                'total_refunds',
                'average_purchase_amount',
                'recent_purchases',
                'monthly_spending',
            ]);
    }

    /** @test */
    public function it_reports_unimplemented_enhanced_payment_endpoints_as_not_found(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $bundleResponse = $this->postJson('/api/payments/bundle/intent', [
            'comic_ids' => [$this->comic->id],
        ]);
        $subscriptionResponse = $this->postJson('/api/payments/subscription/intent', [
            'subscription_type' => 'monthly',
        ]);
        $retryResponse = $this->postJson("/api/payments/{$payment->id}/retry");

        $this->assertSame(404, $bundleResponse->status());
        $this->assertSame(404, $subscriptionResponse->status());
        $this->assertSame(404, $retryResponse->status());
    }

    /** @test */
    public function subscription_user_has_access_to_all_comics(): void
    {
        $this->user->update([
            'subscription_type' => 'monthly',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->user->hasAccessToComic($this->comic));
    }

    /** @test */
    public function expired_subscription_user_loses_access(): void
    {
        $this->user->update([
            'subscription_type' => 'monthly',
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->user->hasAccessToComic($this->comic));
    }
}
