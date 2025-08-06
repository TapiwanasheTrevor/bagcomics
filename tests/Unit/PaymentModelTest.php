<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'comic_id',
            'stripe_payment_intent_id',
            'stripe_payment_method_id',
            'stripe_refund_id',
            'amount',
            'refund_amount',
            'currency',
            'status',
            'payment_type',
            'subscription_type',
            'bundle_discount_percent',
            'stripe_metadata',
            'paid_at',
            'refunded_at',
            'failure_reason',
            'retry_count',
            'last_retry_at',
        ];

        $payment = new Payment();
        $this->assertEquals($fillable, $payment->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $payment = Payment::factory()->create([
            'amount' => 9.99,
            'refund_amount' => 5.00,
            'bundle_discount_percent' => 15.5,
            'stripe_metadata' => ['key' => 'value'],
            'paid_at' => now(),
            'refunded_at' => now(),
            'retry_count' => 2,
        ]);

        $this->assertIsFloat($payment->amount);
        $this->assertIsFloat($payment->refund_amount);
        $this->assertIsFloat($payment->bundle_discount_percent);
        $this->assertIsArray($payment->stripe_metadata);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->paid_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->refunded_at);
        $this->assertIsInt($payment->retry_count);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $payment->user);
        $this->assertEquals($user->id, $payment->user->id);
    }

    /** @test */
    public function it_belongs_to_comic()
    {
        $comic = Comic::factory()->create();
        $payment = Payment::factory()->create(['comic_id' => $comic->id]);

        $this->assertInstanceOf(Comic::class, $payment->comic);
        $this->assertEquals($comic->id, $payment->comic->id);
    }

    /** @test */
    public function it_can_be_without_comic_for_subscriptions()
    {
        $payment = Payment::factory()->create([
            'comic_id' => null,
            'payment_type' => 'subscription',
        ]);

        $this->assertNull($payment->comic);
    }

    /** @test */
    public function it_checks_if_payment_is_successful()
    {
        $successfulPayment = Payment::factory()->create(['status' => 'succeeded']);
        $failedPayment = Payment::factory()->create(['status' => 'failed']);

        $this->assertTrue($successfulPayment->isSuccessful());
        $this->assertFalse($failedPayment->isSuccessful());
    }

    /** @test */
    public function it_checks_if_payment_is_pending()
    {
        $pendingPayment = Payment::factory()->create(['status' => 'pending']);
        $succeededPayment = Payment::factory()->create(['status' => 'succeeded']);

        $this->assertTrue($pendingPayment->isPending());
        $this->assertFalse($succeededPayment->isPending());
    }

    /** @test */
    public function it_checks_if_payment_is_failed()
    {
        $failedPayment = Payment::factory()->create(['status' => 'failed']);
        $canceledPayment = Payment::factory()->create(['status' => 'canceled']);
        $succeededPayment = Payment::factory()->create(['status' => 'succeeded']);

        $this->assertTrue($failedPayment->isFailed());
        $this->assertTrue($canceledPayment->isFailed());
        $this->assertFalse($succeededPayment->isFailed());
    }

    /** @test */
    public function it_checks_if_payment_is_refunded()
    {
        $refundedPayment = Payment::factory()->create(['status' => 'refunded']);
        $succeededPayment = Payment::factory()->create(['status' => 'succeeded']);

        $this->assertTrue($refundedPayment->isRefunded());
        $this->assertFalse($succeededPayment->isRefunded());
    }

    /** @test */
    public function it_checks_if_payment_is_partially_refunded()
    {
        $partiallyRefunded = Payment::factory()->create([
            'amount' => 10.00,
            'refund_amount' => 5.00,
        ]);
        
        $fullyRefunded = Payment::factory()->create([
            'amount' => 10.00,
            'refund_amount' => 10.00,
        ]);
        
        $notRefunded = Payment::factory()->create([
            'amount' => 10.00,
            'refund_amount' => null,
        ]);

        $this->assertTrue($partiallyRefunded->isPartiallyRefunded());
        $this->assertFalse($fullyRefunded->isPartiallyRefunded());
        $this->assertFalse($notRefunded->isPartiallyRefunded());
    }

    /** @test */
    public function it_checks_if_payment_can_be_retried()
    {
        $retriablePayment = Payment::factory()->create([
            'status' => 'failed',
            'retry_count' => 2,
        ]);
        
        $maxRetriedPayment = Payment::factory()->create([
            'status' => 'failed',
            'retry_count' => 3,
        ]);
        
        $succeededPayment = Payment::factory()->create(['status' => 'succeeded']);

        $this->assertTrue($retriablePayment->canBeRetried());
        $this->assertFalse($maxRetriedPayment->canBeRetried());
        $this->assertFalse($succeededPayment->canBeRetried());
    }

    /** @test */
    public function it_marks_payment_as_succeeded()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $payment->markAsSucceeded();

        $this->assertEquals('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    /** @test */
    public function it_marks_payment_as_failed()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $reason = 'Card declined';

        $payment->markAsFailed($reason);

        $this->assertEquals('failed', $payment->status);
        $this->assertEquals($reason, $payment->failure_reason);
    }

    /** @test */
    public function it_marks_payment_as_refunded()
    {
        $payment = Payment::factory()->create([
            'status' => 'succeeded',
            'amount' => 10.00,
        ]);

        $payment->markAsRefunded(5.00);

        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals(5.00, $payment->refund_amount);
        $this->assertNotNull($payment->refunded_at);
    }

    /** @test */
    public function it_increments_retry_count()
    {
        $payment = Payment::factory()->create(['retry_count' => 1]);

        $payment->incrementRetryCount();

        $this->assertEquals(2, $payment->retry_count);
        $this->assertNotNull($payment->last_retry_at);
    }

    /** @test */
    public function it_formats_amount_attribute()
    {
        $payment = Payment::factory()->create(['amount' => 9.99]);

        $this->assertEquals('$9.99', $payment->formatted_amount);
    }

    /** @test */
    public function it_formats_refund_amount_attribute()
    {
        $payment = Payment::factory()->create(['refund_amount' => 5.50]);
        $paymentWithoutRefund = Payment::factory()->create(['refund_amount' => null]);

        $this->assertEquals('$5.50', $payment->formatted_refund_amount);
        $this->assertNull($paymentWithoutRefund->formatted_refund_amount);
    }

    /** @test */
    public function it_displays_payment_type()
    {
        $singlePayment = Payment::factory()->create(['payment_type' => 'single']);
        $bundlePayment = Payment::factory()->create(['payment_type' => 'bundle']);
        $subscriptionPayment = Payment::factory()->create(['payment_type' => 'subscription']);

        $this->assertEquals('Single Purchase', $singlePayment->payment_type_display);
        $this->assertEquals('Bundle Purchase', $bundlePayment->payment_type_display);
        $this->assertEquals('Subscription', $subscriptionPayment->payment_type_display);
    }

    /** @test */
    public function it_scopes_successful_payments()
    {
        Payment::factory()->count(3)->create(['status' => 'succeeded']);
        Payment::factory()->count(2)->create(['status' => 'failed']);

        $successfulPayments = Payment::successful()->get();

        $this->assertCount(3, $successfulPayments);
        $this->assertTrue($successfulPayments->every(fn($payment) => $payment->status === 'succeeded'));
    }

    /** @test */
    public function it_scopes_pending_payments()
    {
        Payment::factory()->count(2)->create(['status' => 'pending']);
        Payment::factory()->count(3)->create(['status' => 'succeeded']);

        $pendingPayments = Payment::pending()->get();

        $this->assertCount(2, $pendingPayments);
        $this->assertTrue($pendingPayments->every(fn($payment) => $payment->status === 'pending'));
    }

    /** @test */
    public function it_scopes_failed_payments()
    {
        Payment::factory()->count(2)->create(['status' => 'failed']);
        Payment::factory()->count(1)->create(['status' => 'canceled']);
        Payment::factory()->count(3)->create(['status' => 'succeeded']);

        $failedPayments = Payment::failed()->get();

        $this->assertCount(3, $failedPayments);
        $this->assertTrue($failedPayments->every(fn($payment) => in_array($payment->status, ['failed', 'canceled'])));
    }

    /** @test */
    public function it_scopes_refunded_payments()
    {
        Payment::factory()->count(2)->create(['status' => 'refunded']);
        Payment::factory()->count(3)->create(['status' => 'succeeded']);

        $refundedPayments = Payment::refunded()->get();

        $this->assertCount(2, $refundedPayments);
        $this->assertTrue($refundedPayments->every(fn($payment) => $payment->status === 'refunded'));
    }

    /** @test */
    public function it_scopes_by_payment_type()
    {
        Payment::factory()->count(3)->create(['payment_type' => 'single']);
        Payment::factory()->count(2)->create(['payment_type' => 'bundle']);
        Payment::factory()->count(1)->create(['payment_type' => 'subscription']);

        $singlePayments = Payment::byType('single')->get();
        $bundlePayments = Payment::byType('bundle')->get();

        $this->assertCount(3, $singlePayments);
        $this->assertCount(2, $bundlePayments);
    }

    /** @test */
    public function it_scopes_by_date_range()
    {
        $startDate = now()->subDays(7);
        $endDate = now()->subDays(1);

        Payment::factory()->count(3)->create(['created_at' => now()->subDays(5)]);
        Payment::factory()->count(2)->create(['created_at' => now()->subDays(10)]);

        $paymentsInRange = Payment::inDateRange($startDate, $endDate)->get();

        $this->assertCount(3, $paymentsInRange);
    }
}