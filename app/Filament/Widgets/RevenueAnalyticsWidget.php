<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;

class RevenueAnalyticsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $monthlyRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
        $weeklyRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('amount');
        
        $avgRevenuePerUser = $totalRevenue / max(User::count(), 1);
        $totalTransactions = Payment::where('status', 'completed')->count();
        $avgTransactionValue = $totalRevenue / max($totalTransactions, 1);

        return [
            StatsOverviewWidget\Stat::make('Monthly Revenue', '$' . number_format($monthlyRevenue, 2))
                ->description('Current month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            StatsOverviewWidget\Stat::make('Weekly Revenue', '$' . number_format($weeklyRevenue, 2))
                ->description('This week')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            StatsOverviewWidget\Stat::make('Avg Revenue/User', '$' . number_format($avgRevenuePerUser, 2))
                ->description('Lifetime value')
                ->descriptionIcon('heroicon-m-user')
                ->color('warning'),

            StatsOverviewWidget\Stat::make('Avg Transaction', '$' . number_format($avgTransactionValue, 2))
                ->description(number_format($totalTransactions) . ' total transactions')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),
        ];
    }
}