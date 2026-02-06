<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentRequest;
use App\Models\Comic;
use App\Models\Payment;
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
                'payment_intent' => $paymentIntent,
                'client_secret' => $paymentIntent->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'PAYMENT_INTENT_FAILED',
                'message' => 'Failed to create payment intent',
                'details' => ['error' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Process a payment after successful payment intent confirmation.
     */
    public function processPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $payment = $this->paymentService->processPayment(
                $request->user(),
                $request->payment_intent_id
            );

            return response()->json([
                'payment' => $payment,
                'message' => 'Payment processed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'PAYMENT_PROCESSING_FAILED',
                'message' => 'Failed to process payment',
                'details' => ['error' => $e->getMessage()]
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
                    'type' => $payment->payment_method ?? 'card',
                    'amount' => '$' . number_format($payment->amount, 2),
                ],
                'message' => 'Payment confirmed successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment confirmation failed', [
                'user_id' => $request->user()->id,
                'payment_intent_id' => $request->payment_intent_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to confirm payment: ' . $e->getMessage(),
                'code' => 'PAYMENT_CONFIRMATION_FAILED',
            ], 400);
        }
    }

    /**
     * Get user's payment history.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,completed,failed,refunded',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date'
        ]);

        $query = Payment::where('user_id', $request->user()->id)
            ->with(['comic:id,title,slug,cover_image_path'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    /**
     * Get payment details by ID.
     */
    public function show(Payment $payment): JsonResponse
    {
        // Ensure user can only access their own payments
        if ($payment->user_id !== auth()->id()) {
            return response()->json([
                'code' => 'PAYMENT_ACCESS_DENIED',
                'message' => 'You can only access your own payments'
            ], 403);
        }

        $payment->load(['comic:id,title,slug,cover_image_path,price']);

        return response()->json($payment);
    }

    /**
     * Request a refund for a payment.
     */
    public function requestRefund(Payment $payment, Request $request): JsonResponse
    {
        // Ensure user can only refund their own payments
        if ($payment->user_id !== auth()->id()) {
            return response()->json([
                'code' => 'PAYMENT_ACCESS_DENIED',
                'message' => 'You can only refund your own payments'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $refund = $this->paymentService->requestRefund(
                $payment,
                $request->reason
            );

            return response()->json([
                'refund' => $refund,
                'message' => 'Refund request submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'REFUND_REQUEST_FAILED',
                'message' => 'Failed to request refund',
                'details' => ['error' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Get payment statistics for the user.
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'total_spent' => Payment::where('user_id', $userId)
                ->where('status', 'completed')
                ->sum('amount'),
            'total_purchases' => Payment::where('user_id', $userId)
                ->where('status', 'completed')
                ->count(),
            'total_refunds' => Payment::where('user_id', $userId)
                ->where('status', 'refunded')
                ->sum('refund_amount'),
            'average_purchase_amount' => Payment::where('user_id', $userId)
                ->where('status', 'completed')
                ->avg('amount'),
            'recent_purchases' => Payment::where('user_id', $userId)
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        // Monthly spending for the last 12 months
        $monthlySpending = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlySpending[] = [
                'month' => $month->format('Y-m'),
                'amount' => Payment::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('amount')
            ];
        }

        $stats['monthly_spending'] = $monthlySpending;

        return response()->json($stats);
    }
}