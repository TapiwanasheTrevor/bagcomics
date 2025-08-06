<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Tests\TestCase;

class EnhancedPaymentServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PaymentService $paymentService;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = new PaymentService();
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'price' => 9.99,
            'is_free' => false,
        ]);
    }

    /** @test */
    public function it_creates_payment_intent_with_proper_metadata()
    {
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    $intent = new \stdClass();
                    $intent->id = 'pi_test_123';
                    $intent->client_secret = 'pi_test_123_secret';
                    $intent->metadata = (object) $params['metadata'];
                    return $intent;
                }
            };
        });

        $paymentIntent = $this->paymentService->createPaymentIntent($this->user, $this->comic);

        $this->assertEquals('pi_test_123', $paymentIntent->id);
        $this->assertEquals('pi_test_123_secret', $paymentIntent->client_secret);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 9.99,
            'currency' => 'usd',
            'status' => 'pending',
            'payment_type' => 'single',
        ]);
    }

    /** @test */
    public function it_prevents_payment_intent_for_free_comics()
    {
        $freeComic = Comic::factory()->create([
            'price' => 0,
            'is_free' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create payment intent for free comic');

        $this->paymentService->createPaymentIntent($this->user, $freeComic);
    }

    /** @test */
    public function it_prevents_duplicate_payment_intents()
    {
        // User already has access
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User already has access to this comic');

        $this->paymentService->createPaymentIntent($this->user, $this->comic);
    }

    /** @test */
    public function it_reuses_existing_pending_payment_intent()
    {
        // Create existing pending payment
        $existingPayment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'stripe_payment_intent_id' => 'pi_existing_123',
            'status' => 'pending',
        ]);

        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function retrieve($id) {
                    $intent = new \stdClass();
                    $intent->id = $id;
                    $intent->client_secret = $id . '_secret';
                    return $intent;
                }
            };
        });

        $paymentIntent = $this->paymentService->createPaymentIntent($this->user, $this->comic);

        $this->assertEquals('pi_existing_123', $paymentIntent->id);
        
        // Should not create a new payment record
        $this->assertDatabaseCount('payments', 1);
    }

    /** @test */
    public function it_creates_bundle_payment_intent_with_discount()
    {
        $comics = Comic::factory()->count(3)->create([
            'price' => 10.00,
            'is_free' => false,
        ]);

        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    $intent = new \stdClass();
                    $intent->id = 'pi_bundle_123';
                    $intent->client_secret = 'pi_bundle_123_secret';
                    $intent->amount = $params['amount'];
                    $intent->metadata = (object) $params['metadata'];
                    return $intent;
                }
            };
        });

        $paymentIntent = $this->paymentService->createBundlePaymentIntent(
            $this->user, 
            $comics, 
            ['discount_percent' => 20]
        );

        $this->assertEquals('pi_bundle_123', $paymentIntent->id);
        $this->assertEquals(2400, $paymentIntent->amount); // $30 - 20% = $24

        // Should create payment record for each comic
        $this->assertDatabaseCount('payments', 3);
        
        foreach ($comics as $comic) {
            $this->assertDatabaseHas('payments', [
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'stripe_payment_intent_id' => 'pi_bundle_123',
                'payment_type' => 'bundle',
                'bundle_discount_percent' => 20,
                'amount' => 8.00, // $10 - 20% = $8
            ]);
        }
    }

    /** @test */
    public function it_creates_subscription_payment_intent()
    {
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    $intent = new \stdClass();
                    $intent->id = 'pi_subscription_123';
                    $intent->client_secret = 'pi_subscription_123_secret';
                    $intent->amount = $params['amount'];
                    $intent->metadata = (object) $params['metadata'];
                    return $intent;
                }
            };
        });

        $paymentIntent = $this->paymentService->createSubscriptionPaymentIntent(
            $this->user, 
            'yearly'
        );

        $this->assertEquals('pi_subscription_123', $paymentIntent->id);
        $this->assertEquals(9999, $paymentIntent->amount); // $99.99

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => null,
            'stripe_payment_intent_id' => 'pi_subscription_123',
            'payment_type' => 'subscription',
            'subscription_type' => 'yearly',
            'amount' => 99.99,
        ]);
    }

    /** @test */
    public function it_processes_successful_payment_and_grants_access()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'status' => 'pending',
            'amount' => 9.99,
        ]);

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

        $processedPayment = $this->paymentService->processPayment($this->user, 'pi_test_123');

        $this->assertEquals('succeeded', $processedPayment->status);
        $this->assertNotNull($processedPayment->paid_at);
        $this->assertEquals('pm_test_123', $processedPayment->stripe_payment_method_id);

        // Verify access was granted
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchase_price' => 9.99,
        ]);
    }

    /** @test */
    public function it_processes_subscription_payment_and_grants_subscription_access()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => null,
            'stripe_payment_intent_id' => 'pi_subscription_123',
            'status' => 'pending',
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
            'amount' => 9.99,
        ]);

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

        $processedPayment = $this->paymentService->processPayment($this->user, 'pi_subscription_123');

        $this->assertEquals('succeeded', $processedPayment->status);

        // Verify subscription was granted
        $this->user->refresh();
        $this->assertEquals('monthly', $this->user->subscription_type);
        $this->assertEquals('active', $this->user->subscription_status);
        $this->assertNotNull($this->user->subscription_expires_at);
    }

    /** @test */
    public function it_refuses_to_process_non_succeeded_payment_intent()
    {
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function retrieve($id) {
                    $intent = new \stdClass();
                    $intent->status = 'requires_payment_method';
                    return $intent;
                }
            };
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment intent has not succeeded');

        $this->paymentService->processPayment($this->user, 'pi_test_123');
    }

    /** @test */
    public function it_processes_refund_and_revokes_access()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        // Grant access
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchase_price' => 9.99,
        ]);

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

        $refund = $this->paymentService->refundPayment($payment);

        $this->assertEquals('re_test_123', $refund->id);
        $this->assertEquals(999, $refund->amount); // $9.99 in cents

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
    public function it_processes_partial_refund_without_revoking_access()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        // Grant access
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);

        $this->mock(StripeClient::class, function ($mock) {
            $mock->refunds = new class {
                public function create($params) {
                    $refund = new \stdClass();
                    $refund->id = 're_partial_123';
                    $refund->amount = $params['amount'];
                    $refund->status = 'succeeded';
                    return $refund;
                }
            };
        });

        $refund = $this->paymentService->refundPayment($payment, 5.00);

        $this->assertEquals('re_partial_123', $refund->id);
        $this->assertEquals(500, $refund->amount); // $5.00 in cents

        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals(5.00, $payment->refund_amount);

        // Verify access was NOT revoked (partial refund)
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);
    }

    /** @test */
    public function it_prevents_refund_of_unsuccessful_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'failed',
            'amount' => 9.99,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot refund unsuccessful payment');

        $this->paymentService->refundPayment($payment);
    }

    /** @test */
    public function it_prevents_refund_of_already_refunded_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'refunded',
            'amount' => 9.99,
            'refund_amount' => 9.99,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment already refunded');

        $this->paymentService->refundPayment($payment);
    }

    /** @test */
    public function it_gets_user_payment_history_with_filters()
    {
        // Create various payments
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'payment_type' => 'single',
            'created_at' => now()->subDays(1),
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
            'payment_type' => 'single',
            'created_at' => now()->subDays(2),
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
            'payment_type' => 'subscription',
            'created_at' => now()->subDays(3),
        ]);

        // Test without filters
        $allPayments = $this->paymentService->getUserPaymentHistory($this->user);
        $this->assertCount(3, $allPayments);

        // Test with status filter
        $succeededPayments = $this->paymentService->getUserPaymentHistory($this->user, [
            'status' => 'succeeded'
        ]);
        $this->assertCount(2, $succeededPayments);

        // Test with payment type filter
        $subscriptionPayments = $this->paymentService->getUserPaymentHistory($this->user, [
            'payment_type' => 'subscription'
        ]);
        $this->assertCount(1, $subscriptionPayments);

        // Test with date range filter
        $recentPayments = $this->paymentService->getUserPaymentHistory($this->user, [
            'from_date' => now()->subDays(1)->startOfDay(),
            'to_date' => now()->endOfDay(),
        ]);
        $this->assertCount(1, $recentPayments);
    }

    /** @test */
    public function it_generates_payment_analytics()
    {
        // Create test payments
        Payment::factory()->create([
            'status' => 'succeeded',
            'amount' => 10.00,
            'payment_type' => 'single',
        ]);

        Payment::factory()->create([
            'status' => 'succeeded',
            'amount' => 20.00,
            'payment_type' => 'bundle',
        ]);

        Payment::factory()->create([
            'status' => 'failed',
            'amount' => 15.00,
            'payment_type' => 'single',
        ]);

        Payment::factory()->create([
            'status' => 'refunded',
            'amount' => 5.00,
            'payment_type' => 'single',
        ]);

        $analytics = $this->paymentService->getPaymentAnalytics();

        $this->assertEquals(30.00, $analytics['total_revenue']);
        $this->assertEquals(2, $analytics['total_transactions']);
        $this->assertEquals(1, $analytics['failed_transactions']);
        $this->assertEquals(5.00, $analytics['refunded_amount']);
        $this->assertEquals(66.67, $analytics['success_rate']); // 2/(2+1) * 100
        $this->assertEquals(15.00, $analytics['average_transaction_value']); // 30/2

        $this->assertArrayHasKey('revenue_by_type', $analytics);
        $this->assertArrayHasKey('single', $analytics['revenue_by_type']);
        $this->assertArrayHasKey('bundle', $analytics['revenue_by_type']);
    }

    /** @test */
    public function it_handles_stripe_api_errors_gracefully()
    {
        $this->mock(StripeClient::class, function ($mock) {
            $mock->paymentIntents = new class {
                public function create($params) {
                    throw new ApiErrorException('Your card was declined.');
                }
            };
        });

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Your card was declined.');

        $this->paymentService->createPaymentIntent($this->user, $this->comic);
    }
}