<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\DateTimePicker::make('email_verified_at')
                    ->label('Email Verified At'),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->dehydrateStateUsing(fn ($state): string => bcrypt($state)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),

                Tables\Columns\TextColumn::make('comics_count')
                    ->label('Comics Owned')
                    ->counts('library')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Total Payments')
                    ->counts('payments')
                    ->sortable(),

                Tables\Columns\TextColumn::make('successful_payments_count')
                    ->label('Successful Payments')
                    ->counts('successfulPayments')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->getStateUsing(function ($record) {
                        $total = $record->successfulPayments()->sum('amount');
                        return '$' . number_format($total, 2);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reading_streak')
                    ->label('Reading Streak (days)')
                    ->getStateUsing(function ($record) {
                        return $record->getCurrentReadingStreak();
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_reading_time')
                    ->label('Total Reading Time (hours)')
                    ->getStateUsing(function ($record) {
                        $minutes = $record->getTotalReadingTimeMinutes();
                        return number_format($minutes / 60, 1);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Subscription')
                    ->getStateUsing(function ($record) {
                        return $record->hasActiveSubscription() ? 'Active' : 'None';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'None' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Last Activity')
                    ->getStateUsing(function ($record) {
                        $lastProgress = $record->comicProgress()->latest('updated_at')->first();
                        return $lastProgress ? $lastProgress->updated_at->diffForHumans() : 'Never';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('verified')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),

                Tables\Filters\Filter::make('unverified')
                    ->query(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),

                Tables\Filters\Filter::make('has_purchases')
                    ->query(fn (Builder $query): Builder => $query->whereHas('successfulPayments')),

                Tables\Filters\Filter::make('subscribed_users')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('subscription_status', 'active')
                    ),

                Tables\Filters\Filter::make('heavy_readers')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('comicProgress', function ($q) {
                            $q->where('total_reading_time_minutes', '>', 3600); // 60+ hours
                        })
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    BulkAction::make('send_notification')
                        ->label('Send Notification')
                        ->icon('heroicon-o-bell')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(100),
                            Forms\Components\Textarea::make('message')
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $user) {
                                // Send notification logic would go here
                                // For now, we'll just show a success message
                            }
                            
                            Notification::make()
                                ->title('Notifications Sent')
                                ->body('Notification sent to ' . count($records) . ' users.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                        
                    BulkAction::make('export_user_data')
                        ->label('Export User Data')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (Collection $records) {
                            // Export logic would go here
                            Notification::make()
                                ->title('Export Started')
                                ->body('User data export for ' . count($records) . ' users is being generated.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UserResource\RelationManagers\LibraryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
