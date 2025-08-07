<?php

namespace App\Filament\Widgets;

use App\Models\Comic;
use Filament\Widgets\ChartWidget;

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