<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;

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