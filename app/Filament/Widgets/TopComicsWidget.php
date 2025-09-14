<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopComicsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Performing Comics (Last 30 Days)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $analytics = new AnalyticsService();
        $performance = $analytics->getComicPerformanceAnalytics(30);

        // Combine and rank comics by multiple metrics
        $topComics = collect();
        
        // Handle most_viewed
        if (isset($performance['most_viewed']) && $performance['most_viewed']) {
            foreach ($performance['most_viewed'] as $comic) {
                $comic->metric_type = 'Most Viewed';
                $comic->metric_value = $comic->getViewsInPeriod(30);
                $topComics->push($comic);
            }
        }

        // Handle most_purchased
        if (isset($performance['most_purchased']) && $performance['most_purchased']) {
            $mostPurchased = is_array($performance['most_purchased']) 
                ? collect($performance['most_purchased']) 
                : $performance['most_purchased'];
            
            foreach ($mostPurchased->take(5) as $comic) {
                $comic->metric_type = 'Most Purchased';
                $comic->metric_value = $comic->period_purchases ?? 0;
                $topComics->push($comic);
            }
        }

        // Handle best_rated
        if (isset($performance['best_rated']) && $performance['best_rated']) {
            $bestRated = is_array($performance['best_rated']) 
                ? collect($performance['best_rated']) 
                : $performance['best_rated'];
                
            foreach ($bestRated->take(5) as $comic) {
                $comic->metric_type = 'Best Rated';
                $comic->metric_value = number_format($comic->average_rating, 1) . '/5';
                $topComics->push($comic);
            }
        }

        return $table
            ->query(
                // Create a query from the collection
                \App\Models\Comic::whereIn('id', $topComics->pluck('id')->unique())
            )
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->square()
                    ->size(60),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->author),

                Tables\Columns\TextColumn::make('genre')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Total Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_readers')
                    ->label('Readers')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '/5' : 'No ratings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_count')
                    ->label('Purchases')
                    ->getStateUsing(fn ($record) => $record->getPurchaseCount())
                    ->numeric(),

                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue')
                    ->getStateUsing(fn ($record) => '$' . number_format($record->getTotalRevenue(), 2))
                    ->sortable(),
            ])
            ->defaultSort('view_count', 'desc')
            ->paginated(false);
    }
}
