<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class RevenueByMonthWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue by Month';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $months = collect(range(0, 11))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $revenue = Payment::where('status', 'completed')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
            
            return [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        })->reverse();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $months->pluck('revenue')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
            ],
            'labels' => $months->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}