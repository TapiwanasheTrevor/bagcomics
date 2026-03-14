<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Get all active subscription plans.
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'price' => (float) $plan->price,
                'originalPrice' => $plan->original_price ? (float) $plan->original_price : null,
                'savingsPercent' => $plan->savings_percent,
                'description' => $plan->description,
                'features' => $plan->features ?? [],
                'isFeatured' => $plan->is_featured,
            ]);

        return response()->json(['data' => $plans]);
    }

    /**
     * Get the authenticated user's current subscription status.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'hasSubscription' => $user->hasActiveSubscription(),
                'type' => $user->subscription_type,
                'status' => $user->subscription_status,
                'displayName' => $user->getSubscriptionDisplayName(),
                'expiresAt' => $user->subscription_expires_at?->toIso8601String(),
                'daysRemaining' => $user->getSubscriptionDaysRemaining(),
            ],
        ]);
    }

    /**
     * Create a payment intent for a subscription purchase.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|string|in:monthly,yearly',
        ]);

        $user = $request->user();

        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'You already have an active subscription.',
                'code' => 'ALREADY_SUBSCRIBED',
                'subscription' => [
                    'type' => $user->subscription_type,
                    'expiresAt' => $user->subscription_expires_at?->toIso8601String(),
                ],
            ], 409);
        }

        try {
            $paymentIntent = $this->paymentService->createSubscriptionPaymentIntent(
                $user,
                $request->plan
            );

            return response()->json([
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'SUBSCRIPTION_INTENT_FAILED',
                'message' => 'Failed to create subscription. Please try again.',
            ], 400);
        }
    }

    /**
     * Cancel the user's active subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'No active subscription to cancel.',
                'code' => 'NO_SUBSCRIPTION',
            ], 404);
        }

        // Mark as canceled — access continues until expiry
        $user->update([
            'subscription_status' => 'canceled',
        ]);

        return response()->json([
            'data' => [
                'message' => 'Subscription canceled. You will retain access until ' .
                    $user->subscription_expires_at->format('F j, Y') . '.',
                'expiresAt' => $user->subscription_expires_at->toIso8601String(),
            ],
        ]);
    }
}
