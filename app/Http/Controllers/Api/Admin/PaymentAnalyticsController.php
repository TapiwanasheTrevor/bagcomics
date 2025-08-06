<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentAnalyticsController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get payment analytics dashboard data
     */
    public function getDashboardAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'period' => 'sometimes|in:today,week,month,quarter,year',
        ]);

        $filters = $this->getDateFilters($request);
        $analytics = $this->paymentService->getPaymentAnalytics($filters);

        return response()->json([
            'analytics' => $analytics,
            'period' => $request->get('period', 'month'),
            'date_range' => [
                'from' => $filters['from_date'] ?? null,
                'to' => $filters['to_date'] ?? null,
            ],
        ]);
    }

    /**
     * Get revenue trends over time
     */
    public function getRevenueTrends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|in:daily,weekly,monthly',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
        ]);

        $period = $request->get('period', 'daily');
        $filters = $this->getDateFilters($request);

        // This would typically use a more sophisticated query
        // For now, we'll return a simplified version
        $trends = $this->calculateRevenueTrends($period, $filters);

        return response()->json([
            'trends' => $trends,
            'period' => $period,
        ]);
    }

    /**
     * Get payment method breakdown
     */
    public function getPaymentMethodBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
        ]);

        $filters = $this->getDateFilters($request);
        
        // This would require additional tracking of payment methods
        // For now, return mock data structure
        $breakdown = [
            'card' => ['count' => 150, 'revenue' => 2500.00],
            'paypal' => ['count' => 45, 'revenue' => 750.00],
            'apple_pay' => ['count' => 30, 'revenue' => 500.00],
            'google_pay' => ['count' => 25, 'revenue' => 400.00],
        ];

        return response()->json([
            'payment_methods' => $breakdown,
            'total_transactions' => array_sum(array_column($breakdown, 'count')),
            'total_revenue' => array_sum(array_column($breakdown, 'revenue')),
        ]);
    }

    /**
     * Get failed payment analysis
     */
    public function getFailedPaymentAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
        ]);

        $filters = $this->getDateFilters($request);
        
        // Analyze failed payments
        $failedPayments = \App\Models\Payment::failed()
            ->when(isset($filters['from_date']), function ($query) use ($filters) {
                return $query->where('created_at', '>=', $filters['from_date']);
            })
            ->when(isset($filters['to_date']), function ($query) use ($filters) {
                return $query->where('created_at', '<=', $filters['to_date']);
            })
            ->get();

        $failureReasons = $failedPayments->groupBy('failure_reason')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ];
            });

        return response()->json([
            'failed_payments' => [
                'total_count' => $failedPayments->count(),
                'total_amount' => $failedPayments->sum('amount'),
                'failure_reasons' => $failureReasons,
            ],
            'recommendations' => $this->getFailureRecommendations($failureReasons),
        ]);
    }

    /**
     * Get refund analytics
     */
    public function getRefundAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
        ]);

        $filters = $this->getDateFilters($request);
        
        $refundedPayments = \App\Models\Payment::refunded()
            ->when(isset($filters['from_date']), function ($query) use ($filters) {
                return $query->where('refunded_at', '>=', $filters['from_date']);
            })
            ->when(isset($filters['to_date']), function ($query) use ($filters) {
                return $query->where('refunded_at', '<=', $filters['to_date']);
            })
            ->get();

        $totalRevenue = \App\Models\Payment::successful()
            ->when(isset($filters['from_date']), function ($query) use ($filters) {
                return $query->where('paid_at', '>=', $filters['from_date']);
            })
            ->when(isset($filters['to_date']), function ($query) use ($filters) {
                return $query->where('paid_at', '<=', $filters['to_date']);
            })
            ->sum('amount');

        $refundRate = $totalRevenue > 0 
            ? ($refundedPayments->sum('refund_amount') / $totalRevenue) * 100 
            : 0;

        return response()->json([
            'refunds' => [
                'total_count' => $refundedPayments->count(),
                'total_amount' => $refundedPayments->sum('refund_amount'),
                'refund_rate' => round($refundRate, 2),
                'by_payment_type' => $refundedPayments->groupBy('payment_type')
                    ->map(function ($group) {
                        return [
                            'count' => $group->count(),
                            'amount' => $group->sum('refund_amount'),
                        ];
                    }),
            ],
        ]);
    }

    /**
     * Get date filters based on request
     */
    private function getDateFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('from_date') && $request->has('to_date')) {
            $filters['from_date'] = $request->from_date;
            $filters['to_date'] = $request->to_date;
        } elseif ($request->has('period')) {
            switch ($request->period) {
                case 'today':
                    $filters['from_date'] = now()->startOfDay();
                    $filters['to_date'] = now()->endOfDay();
                    break;
                case 'week':
                    $filters['from_date'] = now()->startOfWeek();
                    $filters['to_date'] = now()->endOfWeek();
                    break;
                case 'month':
                    $filters['from_date'] = now()->startOfMonth();
                    $filters['to_date'] = now()->endOfMonth();
                    break;
                case 'quarter':
                    $filters['from_date'] = now()->startOfQuarter();
                    $filters['to_date'] = now()->endOfQuarter();
                    break;
                case 'year':
                    $filters['from_date'] = now()->startOfYear();
                    $filters['to_date'] = now()->endOfYear();
                    break;
            }
        } else {
            // Default to current month
            $filters['from_date'] = now()->startOfMonth();
            $filters['to_date'] = now()->endOfMonth();
        }

        return $filters;
    }

    /**
     * Calculate revenue trends (simplified implementation)
     */
    private function calculateRevenueTrends(string $period, array $filters): array
    {
        // This is a simplified implementation
        // In a real application, you'd use more sophisticated database queries
        
        $trends = [];
        $startDate = $filters['from_date'] ?? now()->subMonth();
        $endDate = $filters['to_date'] ?? now();

        // Generate sample trend data
        $current = $startDate;
        while ($current <= $endDate) {
            $trends[] = [
                'date' => $current->format('Y-m-d'),
                'revenue' => rand(100, 1000), // Mock data
                'transactions' => rand(5, 50), // Mock data
            ];

            switch ($period) {
                case 'daily':
                    $current->addDay();
                    break;
                case 'weekly':
                    $current->addWeek();
                    break;
                case 'monthly':
                    $current->addMonth();
                    break;
            }
        }

        return $trends;
    }

    /**
     * Get recommendations based on failure reasons
     */
    private function getFailureRecommendations(object $failureReasons): array
    {
        $recommendations = [];

        foreach ($failureReasons as $reason => $data) {
            if (str_contains(strtolower($reason), 'insufficient')) {
                $recommendations[] = 'Consider implementing payment retry logic for insufficient funds';
            } elseif (str_contains(strtolower($reason), 'card')) {
                $recommendations[] = 'Offer alternative payment methods for card failures';
            } elseif (str_contains(strtolower($reason), 'expired')) {
                $recommendations[] = 'Implement card expiry notifications and update prompts';
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Monitor payment failures and implement targeted solutions';
        }

        return array_unique($recommendations);
    }
}