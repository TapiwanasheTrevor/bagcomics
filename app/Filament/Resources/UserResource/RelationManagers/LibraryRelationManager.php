<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LibraryRelationManager extends RelationManager
{
    protected static string $relationship = 'library';

    protected static ?string $title = 'User Library';

    protected static ?string $icon = 'heroicon-o-book-open';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('comic_id')
                    ->relationship('comic', 'title')
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('access_type')
                    ->options([
                        'free' => 'Free',
                        'purchased' => 'Purchased',
                        'subscription' => 'Subscription',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('purchase_price')
                    ->numeric()
                    ->prefix('$'),

                Forms\Components\DateTimePicker::make('purchased_at'),

                Forms\Components\DateTimePicker::make('access_expires_at'),

                Forms\Components\Toggle::make('is_favorite')
                    ->label('Favorite'),

                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5),

                Forms\Components\Textarea::make('review')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comic.title')
            ->columns([
                Tables\Columns\TextColumn::make('comic.title')
                    ->label('Comic')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('access_type')
                    ->colors([
                        'success' => 'free',
                        'warning' => 'purchased',
                        'info' => 'subscription',
                    ]),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_favorite')
                    ->boolean(),

                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => $state ? $state . '/5 ⭐' : 'No rating'),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('access_type')
                    ->options([
                        'free' => 'Free',
                        'purchased' => 'Purchased',
                        'subscription' => 'Subscription',
                    ]),

                Tables\Filters\Filter::make('favorites')
                    ->query(fn (Builder $query): Builder => $query->where('is_favorite', true)),

                Tables\Filters\Filter::make('rated')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('rating')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
