<?php

namespace App\Filament\Resources\AnalyticsDashboardResource\Pages;

use App\Filament\Resources\AnalyticsDashboardResource;
use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\RevenueAnalyticsWidget;
use App\Filament\Widgets\ContentPerformanceWidget;
use App\Filament\Widgets\UserEngagementChartWidget;
use App\Filament\Widgets\PopularComicsWidget;
use App\Filament\Widgets\RevenueByMonthWidget;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;

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