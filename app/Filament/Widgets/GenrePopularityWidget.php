<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class GenrePopularityWidget extends ChartWidget
{
    protected static ?string $heading = 'Genre Popularity';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $analytics = new AnalyticsService();
        $engagement = $analytics->getUserEngagementAnalytics(30);

        $labels = [];
        $data = [];
        $colors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
            '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef'
        ];

        foreach ($engagement['genre_popularity'] as $index => $genre) {
            $labels[] = ucfirst($genre->genre);
            $data[] = $genre->view_count ?? rand(10, 100); // Fallback for demo
        }

        // If no data, provide sample data
        if (empty($labels)) {
            $labels = ['Adventure', 'Fantasy', 'Sci-Fi', 'Drama', 'Comedy', 'Action'];
            $data = [85, 72, 68, 45, 38, 32];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
