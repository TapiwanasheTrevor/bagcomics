<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = NewsletterSubscriber::withTrashed()
            ->where('email', $request->email)
            ->first();

        if ($subscriber) {
            if ($subscriber->is_active) {
                return response()->json([
                    'data' => ['message' => 'You are already subscribed!'],
                ]);
            }

            // Re-activate
            $subscriber->update(['is_active' => true, 'unsubscribed_at' => null]);

            return response()->json([
                'data' => ['message' => 'Welcome back! You have been re-subscribed.'],
            ]);
        }

        NewsletterSubscriber::create(['email' => $request->email]);

        return response()->json([
            'data' => ['message' => 'Thank you for subscribing!'],
        ], 201);
    }
}
