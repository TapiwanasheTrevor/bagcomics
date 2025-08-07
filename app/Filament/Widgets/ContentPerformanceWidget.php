<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\UserComicProgress;
use Filament\Widgets\StatsOverviewWidget;

class ContentPerformanceWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $topComic = Comic::orderBy('view_count', 'desc')->first();
        $totalViews = Comic::sum('view_count');
        $avgRating = ComicReview::avg('rating');
        $totalReadingTime = UserComicProgress::sum('reading_time_minutes');
        
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