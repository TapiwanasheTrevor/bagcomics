<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Stripe\StripeClient;
use Stripe\PaymentIntent;
use Stripe\Refund;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->paymentService = new PaymentService();
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create(['price' => 9.99, 'is_free' => false]);
    }

    /** @test */
    public function it_creates_payment_intent_for_single_comic()
    {
        $this->mockStripePaymentIntentCreation();

        $paymentIntent = $this->paymentService->createPaymentIntent($this->user, $this->comic);

        $this->assertInstanceOf(PaymentIntent::class, $paymentIntent);
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'amount' => 9.99,
            'status' => 'pending',
            'payment_type' => 'single',
        ]);
    }

    /** @test */
    public function it_throws_exception_for_free_comic()
    {
        $freeComic = Comic::factory()->create(['is_free' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create payment intent for free comic');

        $this->paymentService->createPaymentIntent($this->user, $freeComic);
    }

    /** @test */
    public function it_throws_exception_for_already_owned_comic()
    {
        $this->user->library()->create([
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
            'purchased_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User already has access to this comic');

        $this->paymentService->createPaymentIntent($this->user, $this->comic);
    }

    /** @test */
    public function it_creates_bundle_payment_intent()
    {
        $comics = Comic::factory()->count(3)->create(['price' => 5.00, 'is_free' => false]);
        $this->mockStripePaymentIntentCreation();

        $paymentIntent = $this->paymentService->createBundlePaymentIntent(
            $this->user, 
            $comics, 
            ['discount_percent' => 15]
        );

        $this->assertInstanceOf(PaymentIntent::class, $paymentIntent);
        $this->assertDatabaseCount('payments', 3);
        
        foreach ($comics as $comic) {
            $this->assertDatabaseHas('payments', [
                'user_id' => $this->user->id,
                'comic_id' => $comic->id,
                'payment_type' => 'bundle',
                'bundle_discount_percent' => 15.00,
            ]);
        }
    }

    /** @test */
    public function it_creates_subscription_payment_intent()
    {
        $this->mockStripePaymentIntentCreation();

        $paymentIntent = $this->paymentService->createSubscriptionPaymentIntent(
            $this->user, 
            'monthly'
        );

        $this->assertInstanceOf(PaymentIntent::class, $paymentIntent);
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'comic_id' => null,
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
            'amount' => 9.99,
        ]);
    }

    /** @test */
    public function it_throws_exception_for_invalid_subscription_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid subscription type');

        $this->paymentService->createSubscriptionPaymentIntent($this->user, 'invalid');
    }

    /** @test */
    public function it_processes_successful_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'pending',
            'payment_type' => 'single',
        ]);

        $this->mockStripePaymentIntentRetrieval($payment->stripe_payment_intent_id, 'succeeded');

        $processedPayment = $this->paymentService->processPayment($this->user, $payment->stripe_payment_intent_id);

        $this->assertEquals('succeeded', $processedPayment->status);
        $this->assertNotNull($processedPayment->paid_at);
        
        $this->assertDatabaseHas('user_libraries', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'access_type' => 'purchased',
        ]);
    }

    /** @test */
    public function it_processes_subscription_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => null,
            'status' => 'pending',
            'payment_type' => 'subscription',
            'subscription_type' => 'monthly',
        ]);

        $this->mockStripePaymentIntentRetrieval($payment->stripe_payment_intent_id, 'succeeded');

        $this->paymentService->processPayment($this->user, $payment->stripe_payment_intent_id);

        $this->user->refresh();
        $this->assertEquals('monthly', $this->user->subscription_type);
        $this->assertEquals('active', $this->user->subscription_status);
        $this->assertNotNull($this->user->subscription_expires_at);
    }

    /** @test */
    public function it_refunds_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'status' => 'succeeded',
            'amount' => 9.99,
            'paid_at' => now(),
        ]);

        $this->mockStripeRefund();

        $refund = $this->paymentService->refundPayment($payment);

        $this->assertInstanceOf(Refund::class, $refund);
        
        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertEquals(9.99, $payment->refund_amount);
    }

    /** @test */
    public function it_throws_exception_for_refunding_unsuccessful_payment()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot refund unsuccessful payment');

        $this->paymentService->refundPayment($payment);
    }

    /** @test */
    public function it_gets_user_payment_history()
    {
        Payment::factory()->count(5)->create(['user_id' => $this->user->id]);
        Payment::factory()->count(3)->create(); // Other users

        $history = $this->paymentService->getUserPaymentHistory($this->user);

        $this->assertInstanceOf(Collection::class, $history);
        $this->assertCount(5, $history);
        $this->assertTrue($history->every(fn($payment) => $payment->user_id === $this->user->id));
    }

    /** @test */
    public function it_filters_payment_history()
    {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'succeeded',
        ]);
        
        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'failed',
        ]);

        $successfulPayments = $this->paymentService->getUserPaymentHistory($this->user, ['status' => 'succeeded']);
        $failedPayments = $this->paymentService->getUserPaymentHistory($this->user, ['status' => 'failed']);

        $this->assertCount(3, $successfulPayments);
        $this->assertCount(2, $failedPayments);
    }

    /** @test */
    public function it_gets_payment_analytics()
    {
        Payment::factory()->count(5)->create([
            'status' => 'succeeded',
            'amount' => 10.00,
            'payment_type' => 'single',
        ]);
        
        Payment::factory()->count(2)->create([
            'status' => 'succeeded',
            'amount' => 99.99,
            'payment_type' => 'subscription',
        ]);
        
        Payment::factory()->count(3)->create([
            'status' => 'failed',
            'amount' => 5.00,
        ]);

        $analytics = $this->paymentService->getPaymentAnalytics();

        $this->assertEquals(249.98, $analytics['total_revenue']); // (5 * 10) + (2 * 99.99)
        $this->assertEquals(7, $analytics['total_transactions']);
        $this->assertEquals(3, $analytics['failed_transactions']);
        $this->assertEquals(70.0, $analytics['success_rate']); // 7/(7+3) * 100
        $this->assertArrayHasKey('revenue_by_type', $analytics);
    }

    private function mockStripePaymentIntentCreation(): void
    {
        $mockPaymentIntent = new PaymentIntent('pi_test_123');
        $mockPaymentIntent->client_secret = 'pi_test_123_secret';
        $mockPaymentIntent->metadata = new \Stripe\StripeObject();
        $mockPaymentIntent->metadata->toArray = fn() => [];

        $this->mock(StripeClient::class, function ($mock) use ($mockPaymentIntent) {
            $mock->paymentIntents = new class($mockPaymentIntent) {
                private $paymentIntent;
                
                public function __construct($paymentIntent) {
                    $this->paymentIntent = $paymentIntent;
                }
                
                public function create($params) {
                    return $this->paymentIntent;
                }
            };
        });
    }

    private function mockStripePaymentIntentRetrieval(string $paymentIntentId, string $status): void
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

    private function mockStripeRefund(): void
    {
        $mockRefund = new Refund('re_test_123');
        $mockRefund->status = 'succeeded';
        $mockRefund->amount = 999;

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