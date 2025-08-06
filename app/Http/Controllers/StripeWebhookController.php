<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id
        ]);

        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentCanceled($event->data->object);
                    break;

                case 'charge.dispute.created':
                    $this->handleChargeDispute($event->data->object);
                    break;

                default:
                    Log::info('Unhandled webhook event type', ['type' => $event->type]);
            }

            return response('Webhook handled', 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($paymentIntent): void
    {
        $payments = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->get();

        if ($payments->isEmpty()) {
            Log::warning('No payments found for successful payment intent', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return;
        }

        DB::transaction(function () use ($payments, $paymentIntent) {
            foreach ($payments as $payment) {
                if ($payment->isSuccessful()) {
                    Log::info('Payment already marked as successful', [
                        'payment_id' => $payment->id
                    ]);
                    continue;
                }

                // Mark payment as successful
                $payment->update([
                    'status' => 'succeeded',
                    'paid_at' => now(),
                    'stripe_payment_method_id' => $paymentIntent->payment_method,
                ]);

                // Grant access based on payment type
                if ($payment->payment_type === 'subscription') {
                    $this->grantSubscriptionAccess($payment);
                } elseif ($payment->comic_id) {
                    $this->grantComicAccess($payment);
                }

                Log::info('Payment processed and access granted', [
                    'payment_id' => $payment->id,
                    'payment_type' => $payment->payment_type,
                    'user_id' => $payment->user_id,
                    'comic_id' => $payment->comic_id,
                ]);
            }
        });
    }

    /**
     * Grant comic access to user
     */
    private function grantComicAccess(Payment $payment): void
    {
        UserLibrary::updateOrCreate(
            [
                'user_id' => $payment->user_id,
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
    private function grantSubscriptionAccess(Payment $payment): void
    {
        $expiresAt = $payment->subscription_type === 'yearly' 
            ? now()->addYear() 
            : now()->addMonth();

        $payment->user->update([
            'subscription_type' => $payment->subscription_type,
            'subscription_expires_at' => $expiresAt,
            'subscription_status' => 'active',
        ]);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for failed payment intent', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return;
        }

        $failureReason = $paymentIntent->last_payment_error->message ?? 'Payment failed';
        $payment->markAsFailed($failureReason);

        Log::info('Payment marked as failed', [
            'payment_id' => $payment->id,
            'reason' => $failureReason,
        ]);
    }

    /**
     * Handle canceled payment
     */
    private function handlePaymentCanceled($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for canceled payment intent', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return;
        }

        $payment->update(['status' => 'canceled']);

        Log::info('Payment marked as canceled', [
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Handle charge dispute
     */
    private function handleChargeDispute($dispute): void
    {
        Log::warning('Charge dispute created', [
            'dispute_id' => $dispute->id,
            'charge_id' => $dispute->charge,
            'amount' => $dispute->amount,
            'reason' => $dispute->reason,
        ]);

        // You might want to notify administrators or take other actions
        // For now, we just log the dispute
    }
}
