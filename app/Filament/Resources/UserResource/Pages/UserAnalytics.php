<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\ChartWidget;

class UserAnalytics extends Page
{
    protected static string $resource = UserResource::class;
    protected static string $view = 'filament.resources.user-resource.pages.user-analytics';
    protected static ?string $title = 'User Analytics Dashboard';
    protected static ?string $navigationLabel = 'Analytics';

    protected function getHeaderWidgets(): array
    {
        return [
            UserOverviewWidget::class,
            UserGrowthChartWidget::class,
            RevenueChartWidget::class,
            ReadingActivityChartWidget::class,
        ];
    }
}

class UserOverviewWidget extends BaseStatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::whereHas('comicProgress', function ($query) {
            $query->where('updated_at', '>=', now()->subDays(7));
        })->count();
        
        $subscribedUsers = User::where('subscription_status', 'active')->count();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $averageReadingTime = User::avg('total_reading_time_minutes') ?? 0;

        return [
            BaseStatsOverviewWidget\Stat::make('Total Users', number_format($totalUsers))
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            BaseStatsOverviewWidget\Stat::make('Active Users (7d)', number_format($activeUsers))
                ->description(round(($activeUsers / max($totalUsers, 1)) * 100, 1) . '% of total users')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            BaseStatsOverviewWidget\Stat::make('Subscribers', number_format($subscribedUsers))
                ->description(round(($subscribedUsers / max($totalUsers, 1)) * 100, 1) . '% conversion rate')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            BaseStatsOverviewWidget\Stat::make('Total Revenue', '$' . number_format($totalRevenue, 2))
                ->description('From all successful payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            BaseStatsOverviewWidget\Stat::make('Avg Reading Time', number_format($averageReadingTime / 60, 1) . 'h')
                ->description('Average per user')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}

class UserGrowthChartWidget extends ChartWidget
{
    protected static ?string $heading = 'User Registration Growth';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $months = collect(range(0, 11))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $count = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            return [
                'month' => $date->format('M Y'),
                'users' => $count,
            ];
        })->reverse();

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $months->pluck('users')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Monthly Revenue';
    protected static ?int $sort = 3;

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
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

class ReadingActivityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Reading Activity by Day';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $days = collect(range(0, 6))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            $activity = \DB::table('user_comic_progress')
                ->whereDate('updated_at', $date->toDateString())
                ->count();
            
            return [
                'day' => $date->format('M j'),
                'activity' => $activity,
            ];
        })->reverse();

        return [
            'datasets' => [
                [
                    'label' => 'Reading Sessions',
                    'data' => $days->pluck('activity')->toArray(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgb(168, 85, 247)',
                ],
            ],
            'labels' => $days->pluck('day')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}