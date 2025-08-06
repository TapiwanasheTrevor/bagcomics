<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnalyticsDashboardResource\Pages;
use Filament\Resources\Resource;

class AnalyticsDashboardResource extends Resource
{
    protected static ?string $model = null;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics Dashboard';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return [
            'index' => Pages\AnalyticsDashboard::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}