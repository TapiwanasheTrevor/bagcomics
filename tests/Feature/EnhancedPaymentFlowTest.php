<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Tests\TestCase;

class EnhancedPaymentFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Comic $comic;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'price' => 9.99,
            'is_free' => false,
        ]);
        $this->paymentService = app(PaymentService::class);
    }

    /** @test */
    public function it_creates_payment_intent_with_enhanced_error_handling()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/payments/comics/{$this->comic->slug}/intent", [
            'currency' => 'usd',
            'return_url' => 'https://example.com/return'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'comic' => [
                    'id',
                    'title',
                    'price',
                    'formatted_price'
                ]
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'pending',
            'amount' => 9.99,
        ]);
    }

    /** @test */
    public function it_handles_payment_intent_creation_errors_gracefully()
    {
        $this->actingAs($this->user);

        // Mock Stripe to throw an error
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    throw new \Stripe\Exception\ApiErrorException('Card declined');
                }
            };
        });

        $response = $this->postJson("/api/payments/comics/{$this->comic->slug}/intent");

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to create payment intent',
                'error_code' => 'PAYMENT_FAILED'
            ]);
    }

    /** @test */
    public function it_confirms_payment_with_receipt_url()
    {
        $this->actingAs($this->user);

        // Create a payment record
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'status' => 'pending',
            'amount' => 9.99,
        ]);

        // Mock successful Stripe payment intent
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function retrieve($id) {
                    $intent = new \stdClass();
                    $intent->status = 'succeeded';
                    $intent->payment_method = 'pm_test_123';
                    return $intent;
                }
            };
        });

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_test_123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'payment' => [
                    'id',
                    'type',
                    'amount',
                    'receipt_url'
                ]
            ]);

        $payment->refresh();
        $this->assertEquals('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function it_handles_payment_confirmation_errors_with_retry_logic()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_nonexistent'
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error_code' => 'INVALID_REQUEST'
            ]);
    }

    /** @test */
    public function it_generates_and_downloads_payment_receipt()
    {
        $this->actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        $response = $this->get("/api/payments/{$payment->id}/receipt");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "attachment; filename=\"receipt-{$payment->id}.pdf\"");
    }

    /** @test */
    public function it_prevents_unauthorized_receipt_access()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
        ]);

        $response = $this->get("/api/payments/{$payment->id}/receipt");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_provides_detailed_payment_history_with_filtering()
    {
        $this->actingAs($this->user);

        // Create various payment records
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'payment_type' => 'single',
            'amount' => 9.99,
            'paid_at' => now()->subDays(1),
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => null,
            'status' => 'succeeded',
            'payment_type' => 'subscription',
            'amount' => 19.99,
            'paid_at' => now()->subDays(2),
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'payment_type' => 'single',
            'amount' => 9.99,
        ]);

        // Test filtering by status
        $response = $this->getJson('/api/payments/history?status=succeeded');

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
                        'comic'
                    ]
                ],
                'pagination'
            ]);

        $payments = $response->json('payments');
        $this->assertCount(2, $payments);
        $this->assertTrue(collect($payments)->every(fn($p) => $p['status'] === 'succeeded'));

        // Test filtering by payment type
        $response = $this->getJson('/api/payments/history?payment_type=subscription');

        $payments = $response->json('payments');
        $this->assertCount(1, $payments);
        $this->assertEquals('Subscription', $payments[0]['type']);
    }

    /** @test */
    public function it_handles_payment_retry_with_attempt_limits()
    {
        $this->actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'retry_count' => 2, // Near limit
        ]);

        // Mock successful payment intent creation
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    $intent = new \stdClass();
                    $intent->client_secret = 'pi_test_client_secret';
                    $intent->id = 'pi_test_retry';
                    $intent->metadata = (object) $params['metadata'];
                    return $intent;
                }
            };
        });

        $response = $this->postJson("/api/payments/{$payment->id}/retry");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'message'
            ]);

        $payment->refresh();
        $this->assertEquals(3, $payment->retry_count);
        $this->assertNotNull($payment->last_retry_at);
    }

    /** @test */
    public function it_prevents_retry_after_max_attempts()
    {
        $this->actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'retry_count' => 3, // At limit
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/retry");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Payment cannot be retried'
            ]);
    }

    /** @test */
    public function it_processes_refunds_with_access_revocation()
    {
        $this->actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        // Grant access to comic
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchase_price' => 9.99,
            'purchased_at' => now(),
        ]);

        // Mock successful Stripe refund
        $this->mock(StripeClient::class, function ($mock) {
            $mock->refunds = new class {
                public function create($params) {
                    $refund = new \stdClass();
                    $refund->id = 're_test_123';
                    $refund->amount = $params['amount'];
                    $refund->status = 'succeeded';
                    return $refund;
                }
            };
        });

        $response = $this->postJson("/api/payments/{$payment->id}/refund", [
            'reason' => 'Customer requested refund'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'refund' => [
                    'id',
                    'amount',
                    'status'
                ]
            ]);

        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals(9.99, $payment->refund_amount);
        $this->assertNotNull($payment->refunded_at);

        // Verify access was revoked
        $this->assertDatabaseMissing('user_libraries', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);
    }

    /** @test */
    public function it_provides_invoice_data_for_accounting()
    {
        $this->actingAs($this->user);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'currency' => 'usd',
            'payment_type' => 'single',
            'paid_at' => now(),
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}/invoice");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'invoice' => [
                    'id',
                    'number',
                    'date',
                    'status',
                    'customer' => [
                        'name',
                        'email'
                    ],
                    'items' => [
                        '*' => [
                            'description',
                            'quantity',
                            'unit_price',
                            'total'
                        ]
                    ],
                    'subtotal',
                    'tax',
                    'total',
                    'currency',
                    'payment_method'
                ]
            ]);

        $invoice = $response->json('invoice');
        $this->assertEquals($payment->id, $invoice['id']);
        $this->assertEquals($this->user->name, $invoice['customer']['name']);
        $this->assertEquals(9.99, $invoice['total']);
        $this->assertEquals('USD', $invoice['currency']);
    }

    /** @test */
    public function it_handles_multiple_payment_methods_gracefully()
    {
        $this->actingAs($this->user);

        // Test with different currencies
        $currencies = ['usd', 'eur', 'gbp'];

        foreach ($currencies as $currency) {
            $response = $this->postJson("/api/payments/comics/{$this->comic->slug}/intent", [
                'currency' => $currency
            ]);

            $response->assertStatus(200);
            
            $this->assertDatabaseHas('payments', [
                'user_id' => $this->user->id,
                'comic_id' => $this->comic->id,
                'currency' => $currency,
                'status' => 'pending',
            ]);
        }
    }

    /** @test */
    public function it_prevents_duplicate_purchases()
    {
        $this->actingAs($this->user);

        // User already has access to the comic
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);

        $response = $this->postJson("/api/payments/comics/{$this->comic->slug}/intent");

        $response->assertStatus(400)
            ->assertJson([
                'error_code' => 'INVALID_REQUEST'
            ]);
    }

    /** @test */
    public function it_handles_bundle_purchases_with_discounts()
    {
        $this->actingAs($this->user);

        $comics = Comic::factory()->count(3)->create([
            'price' => 10.00,
            'is_free' => false,
        ]);

        $response = $this->postJson('/api/payments/bundle/intent', [
            'comic_ids' => $comics->pluck('id')->toArray(),
            'discount_percent' => 15
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'bundle_info' => [
                    'comic_count',
                    'original_price',
                    'discounted_price',
                    'savings'
                ]
            ]);

        $bundleInfo = $response->json('bundle_info');
        $this->assertEquals(3, $bundleInfo['comic_count']);
        $this->assertEquals(30.00, $bundleInfo['original_price']);
        $this->assertEquals(25.50, $bundleInfo['discounted_price']);
        $this->assertEquals(4.50, $bundleInfo['savings']);

        // Verify payment records were created for each comic
        $this->assertDatabaseCount('payments', 3);
        foreach ($comics as $comic) {
            $this->assertDatabaseHas('payments', [
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'payment_type' => 'bundle',
                'bundle_discount_percent' => 15,
            ]);
        }
    }

    /** @test */
    public function it_handles_subscription_payments()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/payments/subscription/intent', [
            'subscription_type' => 'monthly'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'client_secret',
                'payment_intent_id',
                'subscription_info' => [
                    'type',
                    'price',
                    'benefits'
                ]
            ]);

        $subscriptionInfo = $response->json('subscription_info');
        $this->assertEquals('monthly', $subscriptionInfo['type']);
        $this->assertEquals(9.99, $subscriptionInfo['price']);
        $this->assertIsArray($subscriptionInfo['benefits']);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => null,
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
            'amount' => 9.99,
        ]);
    }
}