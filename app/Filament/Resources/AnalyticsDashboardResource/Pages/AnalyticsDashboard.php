<?php

namespace App\Filament\Resources\AnalyticsDashboardResource\Pages;

use App\Filament\Resources\AnalyticsDashboardResource;
use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\ComicReview;
use Filament\Resources\Pages\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\ChartWidget;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;

class AnalyticsDashboard extends Page
{
    protected static string $resource = AnalyticsDashboardResource::class;
    protected static string $view = 'filament.resources.analytics-dashboard.pages.analytics-dashboard';
    protected static ?string $title = 'Analytics Dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    // Export logic would go here
                    \Filament\Notifications\Notification::make()
                        ->title('Export Started')
                        ->body('Analytics report is being generated and will be downloaded shortly.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PlatformOverviewWidget::class,
            RevenueAnalyticsWidget::class,
            ContentPerformanceWidget::class,
            UserEngagementChartWidget::class,
            PopularComicsWidget::class,
            RevenueByMonthWidget::class,
        ];
    }
}

class PlatformOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $totalComics = Comic::count();
        $totalReviews = ComicReview::count();
        $monthlyActiveUsers = User::whereHas('comicProgress', function ($query) {
            $query->where('updated_at', '>=', now()->subDays(30));
        })->count();
        
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $monthlyRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return [
            StatsOverviewWidget\Stat::make('Total Users', number_format($totalUsers))
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            StatsOverviewWidget\Stat::make('Total Comics', number_format($totalComics))
                ->description(Comic::where('is_visible', true)->count() . ' published')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('info'),

            StatsOverviewWidget\Stat::make('Monthly Active Users', number_format($monthlyActiveUsers))
                ->description(round(($monthlyActiveUsers / max($totalUsers, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            StatsOverviewWidget\Stat::make('Total Revenue', '$' . number_format($totalRevenue, 2))
                ->description('$' . number_format($monthlyRevenue, 2) . ' this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            StatsOverviewWidget\Stat::make('Reviews & Ratings', number_format($totalReviews))
                ->description(number_format(ComicReview::avg('rating'), 1) . ' avg rating')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),
        ];
    }
}

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

class ContentPerformanceWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $topComic = Comic::orderBy('view_count', 'desc')->first();
        $totalViews = Comic::sum('view_count');
        $avgRating = ComicReview::avg('rating');
        $totalReadingTime = User::sum('total_reading_time_minutes');
        
        $publishedComics = Comic::where('is_visible', true)->count();
        $draftComics = Comic::where('is_visible', false)->count();

        return [
            StatsOverviewWidget\Stat::make('Total Views', number_format($totalViews))
                ->description('Across all comics')
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),

            StatsOverviewWidget\Stat::make('Top Comic Views', number_format($topComic->view_count ?? 0))
                ->description($topComic->title ?? 'No comics yet')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),

            StatsOverviewWidget\Stat::make('Average Rating', number_format($avgRating, 1) . '/5')
                ->description('From all reviews')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            StatsOverviewWidget\Stat::make('Total Reading Time', number_format($totalReadingTime / 60) . ' hours')
                ->description('Across all users')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            StatsOverviewWidget\Stat::make('Published Comics', number_format($publishedComics))
                ->description(number_format($draftComics) . ' drafts')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('success'),
        ];
    }
}

class UserEngagementChartWidget extends ChartWidget
{
    protected static ?string $heading = 'User Engagement Trends';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = collect(range(0, 29))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            
            $activeUsers = DB::table('user_comic_progress')
                ->whereDate('updated_at', $date->toDateString())
                ->distinct('user_id')
                ->count();
            
            $newUsers = User::whereDate('created_at', $date->toDateString())->count();
            
            return [
                'date' => $date->format('M j'),
                'active_users' => $activeUsers,
                'new_users' => $newUsers,
            ];
        })->reverse();

        return [
            'datasets' => [
                [
                    'label' => 'Active Users',
                    'data' => $days->pluck('active_users')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
                [
                    'label' => 'New Users',
                    'data' => $days->pluck('new_users')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'fill' => true,
                ],
            ],
            'labels' => $days->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

class PopularComicsWidget extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Comics by Views';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $topComics = Comic::orderBy('view_count', 'desc')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => $topComics->pluck('view_count')->toArray(),
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(199, 199, 199, 0.8)',
                        'rgba(83, 102, 255, 0.8)',
                        'rgba(255, 99, 255, 0.8)',
                        'rgba(99, 255, 132, 0.8)',
                    ],
                ],
            ],
            'labels' => $topComics->pluck('title')->map(fn($title) => 
                strlen($title) > 20 ? substr($title, 0, 20) . '...' : $title
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

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