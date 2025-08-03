<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Payment;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent for purchasing a comic
     */
    public function createPaymentIntent(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'return_url' => 'required|url',
        ]);

        $user = Auth::user();

        // Check if comic is free
        if ($comic->is_free) {
            return response()->json([
                'error' => 'This comic is free and does not require payment'
            ], 400);
        }

        // Check if user already has access
        if ($user->hasAccessToComic($comic)) {
            return response()->json([
                'error' => 'You already have access to this comic'
            ], 400);
        }

        // Check if there's already a pending payment
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('comic_id', $comic->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            try {
                // Retrieve the existing payment intent from Stripe
                $paymentIntent = $this->stripe->paymentIntents->retrieve(
                    $existingPayment->stripe_payment_intent_id
                );

                return response()->json([
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_id' => $existingPayment->id,
                ]);
            } catch (ApiErrorException $e) {
                // If payment intent doesn't exist in Stripe, mark as failed and create new one
                $existingPayment->markAsFailed('Payment intent not found in Stripe');
            }
        }

        try {
            // Create payment intent in Stripe
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => intval($comic->price * 100), // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'comic_id' => $comic->id,
                    'comic_title' => $comic->title,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ],
                'description' => "Purchase of '{$comic->title}' by {$comic->author}",
            ]);

            // Create payment record in database
            $payment = Payment::create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $comic->price,
                'currency' => 'usd',
                'status' => 'pending',
                'stripe_metadata' => $paymentIntent->metadata->toArray(),
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_id' => $payment->id,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent creation failed', [
                'error' => $e->getMessage(),
                'comic_id' => $comic->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to create payment intent'
            ], 500);
        }
    }

    /**
     * Confirm payment and grant access to comic
     */
    public function confirmPayment(Request $request, Payment $payment): JsonResponse
    {
        $user = Auth::user();

        // Verify payment belongs to authenticated user
        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if payment is already processed
        if ($payment->isSuccessful()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already confirmed'
            ]);
        }

        try {
            // Retrieve payment intent from Stripe
            $paymentIntent = $this->stripe->paymentIntents->retrieve(
                $payment->stripe_payment_intent_id
            );

            if ($paymentIntent->status === 'succeeded') {
                DB::transaction(function () use ($payment, $paymentIntent) {
                    // Mark payment as successful
                    $payment->update([
                        'status' => 'succeeded',
                        'paid_at' => now(),
                        'stripe_payment_method_id' => $paymentIntent->payment_method,
                    ]);

                    // Grant access to comic
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
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed and access granted'
                ]);
            } else {
                $payment->markAsFailed("Payment status: {$paymentIntent->status}");

                return response()->json([
                    'error' => 'Payment not completed',
                    'status' => $paymentIntent->status
                ], 400);
            }

        } catch (ApiErrorException $e) {
            Log::error('Payment confirmation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);

            return response()->json([
                'error' => 'Failed to confirm payment'
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        // Verify payment belongs to authenticated user
        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'comic' => [
                    'id' => $payment->comic->id,
                    'title' => $payment->comic->title,
                    'slug' => $payment->comic->slug,
                ],
            ]
        ]);
    }
}
