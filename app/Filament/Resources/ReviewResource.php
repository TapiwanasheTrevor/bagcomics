<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\ComicReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewResource extends Resource
{
    protected static ?string $model = ComicReview::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Reviews & Moderation';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('comic_id')
                    ->relationship('comic', 'title')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(5),

                Forms\Components\TextInput::make('title')
                    ->label('Review Title')
                    ->maxLength(255),

                Forms\Components\Textarea::make('content')
                    ->label('Review Text')
                    ->rows(4)
                    ->maxLength(5000)
                    ->required(),

                Forms\Components\Toggle::make('is_spoiler')
                    ->label('Contains Spoilers')
                    ->default(false),

                Forms\Components\Toggle::make('is_approved')
                    ->label('Approved')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('comic.title')
                    ->label('Comic')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rating')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        5 => 'success',
                        4 => 'info',
                        3 => 'warning',
                        2, 1 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => $state . '/5')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Review')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_spoiler')
                    ->label('Spoiler')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('helpful_votes')
                    ->label('Helpful Votes')
                    ->numeric()
                    ->sortable()
                    ->default(0),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approval Status'),

                Tables\Filters\TernaryFilter::make('is_spoiler')
                    ->label('Contains Spoilers'),

                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        1 => '1 Star',
                        2 => '2 Stars',
                        3 => '3 Stars',
                        4 => '4 Stars',
                        5 => '5 Stars',
                    ]),

                Tables\Filters\Filter::make('needs_moderation')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('is_approved', false)
                    )
                    ->label('Needs Approval'),

                Tables\Filters\Filter::make('recent_reviews')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '>=', now()->subDays(7))
                    )
                    ->label('Last 7 Days'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => !$record->is_approved)
                    ->action(function ($record) {
                        $record->update(['is_approved' => true]);
                        
                        Notification::make()
                            ->title('Review Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('disapprove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_approved)
                    ->action(function ($record) {
                        $record->update(['is_approved' => false]);
                        
                        Notification::make()
                            ->title('Review Disapproved')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['is_approved' => true]);
                            });
                            
                            Notification::make()
                                ->title('Reviews Approved')
                                ->body(count($records) . ' reviews have been approved.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulk_disapprove')
                        ->label('Disapprove Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['is_approved' => false]);
                            });
                            
                            Notification::make()
                                ->title('Reviews Disapproved')
                                ->body(count($records) . ' reviews have been disapproved.')
                                ->warning()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Return null for now since is_flagged column doesn't exist
        // Can be updated when review moderation is implemented
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}