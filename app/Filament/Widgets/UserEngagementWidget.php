<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class UserEngagementWidget extends ChartWidget
{
    protected static ?string $heading = 'User Engagement Trends';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $analytics = new AnalyticsService();
        $engagement = $analytics->getUserEngagementAnalytics(30);

        // Generate sample daily active users data for the chart
        $labels = [];
        $activeUsers = [];
        $readingSessions = [];

        // Generate last 7 days of data
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M j');
            
            // Simulate daily active users (in real implementation, this would come from actual data)
            $activeUsers[] = rand(5, 25);
            $readingSessions[] = rand(10, 50);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Active Users',
                    'data' => $activeUsers,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Reading Sessions',
                    'data' => $readingSessions,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
