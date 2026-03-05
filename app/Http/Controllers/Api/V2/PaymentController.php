<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentRequest;
use App\Models\Comic;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a payment intent for a comic purchase.
     */
    public function createPaymentIntent(PaymentRequest $request, Comic $comic): JsonResponse
    {
        try {
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $request->user(),
                $comic,
                $request->validated()
            );

            return response()->json([
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'PAYMENT_INTENT_FAILED',
                'message' => 'Failed to create payment intent. Please try again.'
            ], 400);
        }
    }

    /**
     * Confirm a payment after successful payment intent confirmation.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $payment = $this->paymentService->confirmPaymentIntent(
                $request->user(),
                $request->payment_intent_id
            );

            return response()->json([
                'payment' => [
                    'id' => $payment->id,
                    'amount' => '$' . number_format($payment->amount, 2),
                    'status' => $payment->status,
                ],
                'message' => 'Payment confirmed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to confirm payment. Please try again.',
                'code' => 'PAYMENT_CONFIRMATION_FAILED',
            ], 400);
        }
    }
}
