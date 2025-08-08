<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserLibrary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;

class PaymentService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent for a single comic purchase
     */
    public function createPaymentIntent(User $user, Comic $comic, array $options = []): PaymentIntent
    {
        // Validate comic is purchasable
        if ($comic->is_free) {
            throw new \InvalidArgumentException('Cannot create payment intent for free comic');
        }

        if ($user->hasAccessToComic($comic)) {
            throw new \InvalidArgumentException('User already has access to this comic');
        }

        // Check for existing pending payment
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            try {
                return $this->stripe->paymentIntents->retrieve($existingPayment->stripe_payment_intent_id);
            } catch (ApiErrorException $e) {
                // Mark as failed and continue to create new one
                $existingPayment->markAsFailed('Payment intent not found in Stripe');
            }
        }

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => intval($comic->price * 100),
                'currency' => $options['currency'] ?? 'usd',
                'metadata' => [
                    'comic_id' => $comic->id,
                    'comic_title' => $comic->title,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'purchase_type' => 'single',
                ],
                'description' => "Purchase of '{$comic->title}' by {$comic->author}",
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            // Create payment record
            Payment::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $comic->price,
                'currency' => $options['currency'] ?? 'usd',
                'status' => 'pending',
                'payment_type' => 'single',
                'stripe_metadata' => $paymentIntent->metadata->toArray(),
            ]);

            return $paymentIntent;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create payment intent', [
                'error' => $e->getMessage(),
                'comic_id' => $comic->id,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create a payment intent for bundle purchase
     */
    public function createBundlePaymentIntent(User $user, Collection $comics, array $options = []): PaymentIntent
    {
        if ($comics->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create bundle payment for empty comic collection');
        }

        // Calculate bundle price with discount
        $totalPrice = $comics->sum('price');
        $discountPercent = $options['discount_percent'] ?? 10; // Default 10% bundle discount
        $bundlePrice = $totalPrice * (1 - $discountPercent / 100);

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => intval($bundlePrice * 100),
                'currency' => $options['currency'] ?? 'usd',
                'metadata' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'purchase_type' => 'bundle',
                    'comic_ids' => $comics->pluck('id')->implode(','),
                    'original_price' => $totalPrice,
                    'discount_percent' => $discountPercent,
                ],
                'description' => "Bundle purchase of {$comics->count()} comics",
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            // Create payment records for each comic in bundle
            foreach ($comics as $comic) {
                Payment::create([
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'amount' => $comic->price * (1 - $discountPercent / 100), // Proportional discount
                    'currency' => $options['currency'] ?? 'usd',
                    'status' => 'pending',
                    'payment_type' => 'bundle',
                    'bundle_discount_percent' => $discountPercent,
                    'stripe_metadata' => $paymentIntent->metadata->toArray(),
                ]);
            }

            return $paymentIntent;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create bundle payment intent', [
                'error' => $e->getMessage(),
                'comic_ids' => $comics->pluck('id')->toArray(),
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create a subscription payment intent
     */
    public function createSubscriptionPaymentIntent(User $user, string $subscriptionType, array $options = []): PaymentIntent
    {
        $subscriptionPrices = [
            'monthly' => 9.99,
            'yearly' => 99.99,
        ];

        if (!isset($subscriptionPrices[$subscriptionType])) {
            throw new \InvalidArgumentException('Invalid subscription type');
        }

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => intval($subscriptionPrices[$subscriptionType] * 100),
                'currency' => $options['currency'] ?? 'usd',
                'metadata' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'purchase_type' => 'subscription',
                    'subscription_type' => $subscriptionType,
                ],
                'description' => "Subscription ({$subscriptionType}) for unlimited comic access",
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            // Create payment record for subscription
            Payment::create([
                'user_id' => $user->id,
                'comic_id' => null, // Subscription doesn't relate to specific comic
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $subscriptionPrices[$subscriptionType],
                'currency' => $options['currency'] ?? 'usd',
                'status' => 'pending',
                'payment_type' => 'subscription',
                'subscription_type' => $subscriptionType,
                'stripe_metadata' => $paymentIntent->metadata->toArray(),
            ]);

            return $paymentIntent;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create subscription payment intent', [
                'error' => $e->getMessage(),
                'subscription_type' => $subscriptionType,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Process successful payment
     */
    public function processPayment(User $user, string $paymentIntentId): Payment
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                throw new \InvalidArgumentException('Payment intent has not succeeded');
            }

            $payments = Payment::where('stripe_payment_intent_id', $paymentIntentId)->get();
            
            if ($payments->isEmpty()) {
                throw new \InvalidArgumentException('No payment records found for this payment intent');
            }

            return DB::transaction(function () use ($payments, $paymentIntent, $user) {
                foreach ($payments as $payment) {
                    if ($payment->isSuccessful()) {
                        continue; // Already processed
                    }

                    // Update payment status
                    $payment->update([
                        'status' => 'succeeded',
                        'paid_at' => now(),
                        'stripe_payment_method_id' => $paymentIntent->payment_method,
                    ]);

                    // Grant access based on payment type
                    if ($payment->payment_type === 'subscription') {
                        $this->grantSubscriptionAccess($user, $payment);
                    } elseif ($payment->comic_id) {
                        $this->grantComicAccess($user, $payment);
                    }
                }

                return $payments->first();
            });

        } catch (ApiErrorException $e) {
            Log::error('Failed to process payment', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(Payment $payment, ?float $amount = null): Refund
    {
        if (!$payment->isSuccessful()) {
            throw new \InvalidArgumentException('Cannot refund unsuccessful payment');
        }

        if ($payment->isRefunded()) {
            throw new \InvalidArgumentException('Payment already refunded');
        }

        try {
            $refundAmount = $amount ? intval($amount * 100) : intval($payment->amount * 100);
            
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->stripe_payment_intent_id,
                'amount' => $refundAmount,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'comic_id' => $payment->comic_id,
                ],
            ]);

            // Update payment status
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $amount ?? $payment->amount,
                'stripe_refund_id' => $refund->id,
            ]);

            // Revoke access if fully refunded
            if (!$amount || $amount >= $payment->amount) {
                $this->revokeAccess($payment);
            }

            Log::info('Payment refunded successfully', [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount' => $refundAmount / 100,
            ]);

            return $refund;

        } catch (ApiErrorException $e) {
            Log::error('Failed to refund payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get user payment history
     */
    public function getUserPaymentHistory(User $user, array $filters = []): Collection
    {
        $query = Payment::where('user_id', $user->id)
            ->with(['comic'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->get();
    }

    /**
     * Get payment analytics
     */
    public function getPaymentAnalytics(array $filters = []): array
    {
        $query = Payment::query();

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $totalRevenue = $query->where('status', 'succeeded')->sum('amount');
        $totalTransactions = $query->where('status', 'succeeded')->count();
        $failedTransactions = $query->where('status', 'failed')->count();
        $refundedAmount = $query->where('status', 'refunded')->sum('amount');

        $revenueByType = $query->where('status', 'succeeded')
            ->selectRaw('payment_type, SUM(amount) as revenue, COUNT(*) as count')
            ->groupBy('payment_type')
            ->get()
            ->keyBy('payment_type');

        $successRate = $totalTransactions > 0 
            ? ($totalTransactions / ($totalTransactions + $failedTransactions)) * 100 
            : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'failed_transactions' => $failedTransactions,
            'refunded_amount' => $refundedAmount,
            'success_rate' => round($successRate, 2),
            'revenue_by_type' => $revenueByType,
            'average_transaction_value' => $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0,
        ];
    }

    /**
     * Grant comic access to user
     */
    private function grantComicAccess(User $user, Payment $payment): void
    {
        UserLibrary::updateOrCreate(
            [
                'user_id' => $user->id,
                'comic_id' => $payment->comic_id,
            ],
            [
                'access_type' => 'purchased',
                'purchase_price' => $payment->amount,
                'purchased_at' => now(),
            ]
        );
    }

    /**
     * Grant subscription access to user
     */
    private function grantSubscriptionAccess(User $user, Payment $payment): void
    {
        $expiresAt = $payment->subscription_type === 'yearly' 
            ? now()->addYear() 
            : now()->addMonth();

        $user->update([
            'subscription_type' => $payment->subscription_type,
            'subscription_expires_at' => $expiresAt,
            'subscription_status' => 'active',
        ]);
    }

    /**
     * Revoke access after refund
     */
    private function revokeAccess(Payment $payment): void
    {
        if ($payment->payment_type === 'subscription') {
            $payment->user->update([
                'subscription_status' => 'canceled',
                'subscription_expires_at' => now(),
            ]);
        } elseif ($payment->comic_id) {
            UserLibrary::where('user_id', $payment->user_id)
                ->where('comic_id', $payment->comic_id)
                ->delete();
        }
    }

    /**
     * Confirm a payment intent and complete the purchase
     */
    public function confirmPaymentIntent(User $user, string $paymentIntentId): Payment
    {
        try {
            // Retrieve the payment intent from Stripe
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            // Find the corresponding payment record
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if (!$payment) {
                throw new \Exception('Payment record not found for payment intent: ' . $paymentIntentId);
            }

            // Verify the payment belongs to the user
            if ($payment->user_id !== $user->id) {
                throw new \Exception('Payment does not belong to the authenticated user');
            }

            // Check if payment intent was successful
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment intent has not succeeded: ' . $paymentIntent->status);
            }

            // Update payment record
            $payment->update([
                'status' => 'completed',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'payment_method' => $paymentIntent->charges->data[0]->payment_method_details->type ?? 'card',
                'transaction_id' => $paymentIntent->charges->data[0]->id ?? null,
                'processed_at' => now(),
            ]);

            // Add comic to user's library
            if ($payment->comic) {
                UserLibrary::firstOrCreate([
                    'user_id' => $user->id,
                    'comic_id' => $payment->comic_id,
                ], [
                    'access_type' => 'purchased',
                    'purchase_price' => $payment->amount,
                    'purchased_at' => now(),
                ]);

                Log::info('Comic added to user library', [
                    'user_id' => $user->id,
                    'comic_id' => $payment->comic_id,
                    'payment_id' => $payment->id,
                ]);
            }

            return $payment;

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during payment confirmation', [
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to confirm payment with Stripe: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Payment confirmation failed', [
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}