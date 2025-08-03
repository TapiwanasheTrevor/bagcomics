<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformMetricsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $analytics = new AnalyticsService();
        $metrics = $analytics->getPlatformMetrics(30);

        return [
            Stat::make('Total Users', number_format($metrics['total_users']))
                ->description($metrics['new_users'] . ' new users this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Revenue', '$' . number_format($metrics['total_revenue'], 2))
                ->description('$' . number_format($metrics['revenue_period'], 2) . ' this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Comics', number_format($metrics['total_comics']))
                ->description('Published and visible')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('primary'),

            Stat::make('Total Purchases', number_format($metrics['total_purchases']))
                ->description($metrics['purchases_period'] . ' this month')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),

            Stat::make('Total Views', number_format($metrics['total_views']))
                ->description(number_format($metrics['views_period']) . ' this month')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Active Readers', number_format($metrics['active_readers']))
                ->description('Read comics this month')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
        ];
    }
}
