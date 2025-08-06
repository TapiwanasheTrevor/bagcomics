<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;
use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a payment intent for purchasing a single comic
     */
    public function createPaymentIntent(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'currency' => 'sometimes|string|in:usd,eur,gbp',
            'return_url' => 'sometimes|url',
        ]);

        $user = Auth::user();

        try {
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $user, 
                $comic, 
                $request->only(['currency'])
            );

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'comic' => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'price' => $comic->price,
                    'formatted_price' => '$' . number_format($comic->price, 2),
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'error_code' => 'INVALID_REQUEST',
            ], 400);
        } catch (ApiErrorException $e) {
            Log::error('Payment intent creation failed', [
                'error' => $e->getMessage(),
                'comic_id' => $comic->id,
                'user_id' => $user->id,
            ]);
            
            $errorCode = $this->mapStripeErrorCode($e->getStripeCode());
            return response()->json([
                'error' => 'Failed to create payment intent',
                'error_code' => $errorCode,
            ], 500);
        }
    }

    /**
     * Create a payment intent for bundle purchase
     */
    public function createBundlePaymentIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comic_ids' => 'required|array|min:2',
            'comic_ids.*' => 'exists:comics,id',
            'currency' => 'sometimes|string|in:usd,eur,gbp',
            'discount_percent' => 'sometimes|numeric|min:0|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $comics = Comic::whereIn('id', $request->comic_ids)->get();

        try {
            $paymentIntent = $this->paymentService->createBundlePaymentIntent(
                $user,
                $comics,
                $request->only(['currency', 'discount_percent'])
            );

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'bundle_info' => [
                    'comic_count' => $comics->count(),
                    'original_price' => $comics->sum('price'),
                    'discounted_price' => $paymentIntent->amount / 100,
                    'savings' => $comics->sum('price') - ($paymentIntent->amount / 100),
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (ApiErrorException $e) {
            Log::error('Bundle payment intent creation failed', [
                'error' => $e->getMessage(),
                'comic_ids' => $request->comic_ids,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Failed to create bundle payment intent'], 500);
        }
    }

    /**
     * Create a subscription payment intent
     */
    public function createSubscriptionPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_type' => 'required|in:monthly,yearly',
            'currency' => 'sometimes|string|in:usd,eur,gbp',
        ]);

        $user = Auth::user();

        try {
            $paymentIntent = $this->paymentService->createSubscriptionPaymentIntent(
                $user,
                $request->subscription_type,
                $request->only(['currency'])
            );

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'subscription_info' => [
                    'type' => $request->subscription_type,
                    'price' => $paymentIntent->amount / 100,
                    'benefits' => [
                        'Unlimited comic access',
                        'Early access to new releases',
                        'Exclusive content',
                        'Ad-free reading experience',
                    ],
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (ApiErrorException $e) {
            Log::error('Subscription payment intent creation failed', [
                'error' => $e->getMessage(),
                'subscription_type' => $request->subscription_type,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Failed to create subscription payment intent'], 500);
        }
    }

    /**
     * Confirm payment and grant access
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            $payment = $this->paymentService->processPayment($user, $request->payment_intent_id);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed and access granted',
                'payment' => [
                    'id' => $payment->id,
                    'type' => $payment->payment_type_display,
                    'amount' => '$' . $payment->formatted_amount,
                    'receipt_url' => route('payments.receipt', $payment->id),
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'error_code' => 'INVALID_REQUEST',
            ], 400);
        } catch (ApiErrorException $e) {
            Log::error('Payment confirmation failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $request->payment_intent_id,
                'user_id' => $user->id,
            ]);
            
            $errorCode = $this->mapStripeErrorCode($e->getStripeCode());
            return response()->json([
                'error' => 'Failed to confirm payment',
                'error_code' => $errorCode,
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'type' => $payment->payment_type_display,
                'amount' => $payment->formatted_amount,
                'refund_amount' => $payment->formatted_refund_amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at,
                'refunded_at' => $payment->refunded_at,
                'can_be_retried' => $payment->canBeRetried(),
                'comic' => $payment->comic ? [
                    'id' => $payment->comic->id,
                    'title' => $payment->comic->title,
                    'slug' => $payment->comic->slug,
                ] : null,
            ]
        ]);
    }

    /**
     * Get user payment history
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:pending,succeeded,failed,canceled,refunded',
            'payment_type' => 'sometimes|in:single,bundle,subscription',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $filters = $request->only(['status', 'payment_type', 'from_date', 'to_date']);
        
        $payments = $this->paymentService->getUserPaymentHistory($user, $filters);
        
        // Paginate results
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $total = $payments->count();
        $paginatedPayments = $payments->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'payments' => $paginatedPayments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'type' => $payment->payment_type_display,
                    'amount' => $payment->formatted_amount,
                    'refund_amount' => $payment->formatted_refund_amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at,
                    'refunded_at' => $payment->refunded_at,
                    'comic' => $payment->comic ? [
                        'id' => $payment->comic->id,
                        'title' => $payment->comic->title,
                        'slug' => $payment->comic->slug,
                        'cover_image_path' => $payment->comic->cover_image_path,
                    ] : null,
                ];
            })->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Request refund for a payment
     */
    public function requestRefund(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:500',
        ]);

        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $refund = $this->paymentService->refundPayment(
                $payment, 
                $request->get('amount')
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => [
                    'id' => $refund->id,
                    'amount' => '$' . number_format($refund->amount / 100, 2),
                    'status' => $refund->status,
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (ApiErrorException $e) {
            Log::error('Refund processing failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Failed to process refund'], 500);
        }
    }

    /**
     * Retry failed payment
     */
    public function retryPayment(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$payment->canBeRetried()) {
            return response()->json(['error' => 'Payment cannot be retried'], 400);
        }

        try {
            if ($payment->comic_id) {
                $paymentIntent = $this->paymentService->createPaymentIntent($user, $payment->comic);
            } else {
                return response()->json(['error' => 'Cannot retry this payment type'], 400);
            }

            $payment->incrementRetryCount();

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'message' => 'New payment intent created for retry',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment retry failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Failed to retry payment'], 500);
        }
    }

    /**
     * Generate and download payment receipt
     */
    public function downloadReceipt(Payment $payment)
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$payment->isSuccessful()) {
            return response()->json(['error' => 'Receipt not available for unsuccessful payments'], 400);
        }

        try {
            $pdf = $this->generateReceiptPdf($payment);
            
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="receipt-' . $payment->id . '.pdf"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt generation failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);
            return response()->json(['error' => 'Failed to generate receipt'], 500);
        }
    }

    /**
     * Get payment invoice data
     */
    public function getInvoice(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'invoice' => [
                'id' => $payment->id,
                'number' => 'INV-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT),
                'date' => $payment->paid_at?->format('Y-m-d'),
                'due_date' => $payment->paid_at?->format('Y-m-d'),
                'status' => $payment->status,
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'items' => [
                    [
                        'description' => $payment->comic ? 
                            "Comic: {$payment->comic->title}" : 
                            $payment->payment_type_display,
                        'quantity' => 1,
                        'unit_price' => $payment->amount,
                        'total' => $payment->amount,
                    ]
                ],
                'subtotal' => $payment->amount,
                'tax' => 0,
                'total' => $payment->amount,
                'currency' => strtoupper($payment->currency),
                'payment_method' => $payment->payment_type_display,
                'refund_amount' => $payment->refund_amount,
            ]
        ]);
    }

    /**
     * Generate PDF receipt
     */
    private function generateReceiptPdf(Payment $payment): string
    {
        // Simple HTML receipt template
        $html = view('receipts.payment', compact('payment'))->render();
        
        // For now, return HTML as PDF would require additional PDF library
        // In production, you'd use something like DomPDF or wkhtmltopdf
        return $html;
    }

    /**
     * Map Stripe error codes to application error codes
     */
    private function mapStripeErrorCode(?string $stripeCode): string
    {
        return match($stripeCode) {
            'card_declined' => 'CARD_DECLINED',
            'insufficient_funds' => 'INSUFFICIENT_FUNDS',
            'expired_card' => 'EXPIRED_CARD',
            'incorrect_cvc' => 'INCORRECT_CVC',
            'processing_error' => 'PROCESSING_ERROR',
            'rate_limit' => 'RATE_LIMIT_EXCEEDED',
            default => 'PAYMENT_FAILED',
        };
    }
}
