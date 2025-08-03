<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\User;
use App\Models\ComicView;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Platform Activity';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->with(['user', 'comic'])
                    ->where('created_at', '>', now()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),

                Tables\Columns\TextColumn::make('comic.title')
                    ->label('Comic')
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->label('Amount'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'succeeded',
                        'warning' => 'pending',
                        'danger' => ['failed', 'canceled'],
                        'secondary' => 'refunded',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->since(),
            ])
            ->paginated(false)
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
}
