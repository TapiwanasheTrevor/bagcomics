<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Comic;
use App\Models\Payment;
use App\Models\ComicReview;
use Filament\Widgets\StatsOverviewWidget;

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