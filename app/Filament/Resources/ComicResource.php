<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComicResource\Pages;
use App\Filament\Resources\ComicResource\RelationManagers;
use App\Models\Comic;
use App\Models\ComicSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class ComicResource extends Resource
{
    protected static ?string $model = Comic::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Comics';
    protected static ?string $pluralModelLabel = 'Comic Books';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->maxLength(255)
                        ->unique(Comic::class, 'slug', ignoreRecord: true)
                        ->disabled()
                        ->helperText('Automatically generated from the title'),

                    Forms\Components\TextInput::make('author')
                        ->maxLength(255),

                    Forms\Components\Select::make('genre')
                        ->options([
                            'action' => 'Action',
                            'adventure' => 'Adventure',
                            'comedy' => 'Comedy',
                            'drama' => 'Drama',
                            'fantasy' => 'Fantasy',
                            'horror' => 'Horror',
                            'mystery' => 'Mystery',
                            'romance' => 'Romance',
                            'sci-fi' => 'Science Fiction',
                            'slice-of-life' => 'Slice of Life',
                            'superhero' => 'Superhero',
                            'thriller' => 'Thriller',
                        ])
                        ->searchable(),

                    Forms\Components\Select::make('language')
                        ->options([
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'ja' => 'Japanese',
                            'ko' => 'Korean',
                            'zh' => 'Chinese',
                        ])
                        ->default('en'),

                    Forms\Components\TagsInput::make('tags')
                        ->separator(','),

                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Cover Image')
                ->schema([
                    Forms\Components\Placeholder::make('current_cover')
                        ->label('Current Cover')
                        ->content(fn ($record) => $record?->cover_image_url
                            ? new \Illuminate\Support\HtmlString('<img src="' . e($record->cover_image_url) . '" style="max-height:200px;border-radius:8px;" />')
                            : 'No cover image')
                        ->visible(fn ($record) => $record !== null && $record->cover_image_path),

                    Forms\Components\FileUpload::make('cover_image_path')
                        ->label(fn ($record) => $record?->cover_image_path ? 'Replace Cover Image' : 'Cover Image')
                        ->disk('public')
                        ->directory('covers')
                        ->image()
                        ->imageEditor()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('2:3')
                        ->imageResizeTargetWidth('400')
                        ->imageResizeTargetHeight('600')
                        ->helperText('Upload a cover image (2:3 aspect ratio). Comic pages are managed in the Pages tab after saving.'),
                ]),

            Forms\Components\Section::make('Publishing')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('series_id')
                        ->label('Comic Series')
                        ->relationship('series', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description')
                                ->rows(3),
                            Forms\Components\TextInput::make('publisher')
                                ->maxLength(255),
                            Forms\Components\Select::make('status')
                                ->options([
                                    'ongoing' => 'Ongoing',
                                    'completed' => 'Completed',
                                    'hiatus' => 'On Hiatus',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('ongoing'),
                        ]),

                    Forms\Components\TextInput::make('issue_number')
                        ->label('Issue Number')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn ($get) => $get('series_id')),

                    Forms\Components\TextInput::make('publisher')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('publication_year')
                        ->label('Publication Year')
                        ->numeric()
                        ->minValue(1900)
                        ->maxValue(date('Y')),

                    Forms\Components\TextInput::make('isbn')
                        ->label('ISBN')
                        ->maxLength(20),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish Date')
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Pricing & Visibility')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_free')
                        ->label('Free Comic')
                        ->default(true)
                        ->live(),

                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->step(0.01)
                        ->label('Price (USD)')
                        ->visible(fn ($get) => !$get('is_free')),

                    Forms\Components\Toggle::make('is_visible')
                        ->label('Visible on Frontend')
                        ->default(true),

                    Forms\Components\Toggle::make('has_mature_content')
                        ->label('Contains Mature Content')
                        ->live(),

                    Forms\Components\Textarea::make('content_warnings')
                        ->label('Content Warnings')
                        ->rows(2)
                        ->visible(fn ($get) => $get('has_mature_content'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('cover_image_url')
                ->label('Cover')
                ->square(),

            Tables\Columns\TextColumn::make('title')
                ->sortable()
                ->searchable()
                ->description(fn ($record) => $record->author),

            Tables\Columns\TextColumn::make('series.name')
                ->label('Series')
                ->searchable()
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('issue_number')
                ->label('Issue #')
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('genre')
                ->badge()
                ->color('info'),

            Tables\Columns\TextColumn::make('pages_count')
                ->label('Pages')
                ->counts('pages')
                ->sortable(),

            Tables\Columns\TextColumn::make('average_rating')
                ->label('Rating')
                ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '/5' : 'No ratings')
                ->sortable(),

            Tables\Columns\IconColumn::make('is_free')
                ->label('Free?')
                ->boolean(),

            Tables\Columns\TextColumn::make('price')
                ->money('USD')
                ->label('Price'),

            Tables\Columns\IconColumn::make('has_mature_content')
                ->label('Mature')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\IconColumn::make('is_visible')
                ->label('Visible')
                ->boolean(),

            Tables\Columns\TextColumn::make('view_count')
                ->label('Views')
                ->numeric()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('total_readers')
                ->label('Readers')
                ->numeric()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('published_at')
                ->label('Published')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\TernaryFilter::make('is_free'),
            Tables\Filters\TernaryFilter::make('is_visible'),
            Tables\Filters\TernaryFilter::make('has_mature_content'),
            Tables\Filters\SelectFilter::make('genre')
                ->options([
                    'action' => 'Action',
                    'adventure' => 'Adventure',
                    'comedy' => 'Comedy',
                    'drama' => 'Drama',
                    'fantasy' => 'Fantasy',
                    'horror' => 'Horror',
                    'mystery' => 'Mystery',
                    'romance' => 'Romance',
                    'sci-fi' => 'Science Fiction',
                    'slice-of-life' => 'Slice of Life',
                    'superhero' => 'Superhero',
                    'thriller' => 'Thriller',
                ]),
            Tables\Filters\SelectFilter::make('language')
                ->options([
                    'en' => 'English',
                    'es' => 'Spanish',
                    'fr' => 'French',
                    'de' => 'German',
                    'ja' => 'Japanese',
                    'ko' => 'Korean',
                    'zh' => 'Chinese',
                ]),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),

                BulkAction::make('bulk_publish')
                    ->label('Publish Selected')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->action(function (Collection $records) {
                        $records->each(function ($record) {
                            $record->update([
                                'is_visible' => true,
                                'published_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Comics Published')
                            ->body(count($records) . ' comics have been published successfully.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('bulk_unpublish')
                    ->label('Unpublish Selected')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->action(function (Collection $records) {
                        $records->each(function ($record) {
                            $record->update(['is_visible' => false]);
                        });

                        Notification::make()
                            ->title('Comics Unpublished')
                            ->body(count($records) . ' comics have been unpublished.')
                            ->warning()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('bulk_update_genre')
                    ->label('Update Genre')
                    ->icon('heroicon-o-tag')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('genre')
                            ->options([
                                'action' => 'Action',
                                'adventure' => 'Adventure',
                                'comedy' => 'Comedy',
                                'drama' => 'Drama',
                                'fantasy' => 'Fantasy',
                                'horror' => 'Horror',
                                'mystery' => 'Mystery',
                                'romance' => 'Romance',
                                'sci-fi' => 'Science Fiction',
                                'slice-of-life' => 'Slice of Life',
                                'superhero' => 'Superhero',
                                'thriller' => 'Thriller',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(function ($record) use ($data) {
                            $record->update(['genre' => $data['genre']]);
                        });

                        Notification::make()
                            ->title('Genre Updated')
                            ->body(count($records) . ' comics have been updated to ' . $data['genre'] . '.')
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
            RelationManagers\PagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComics::route('/'),
            'create' => Pages\CreateComic::route('/create'),
            'edit' => Pages\EditComic::route('/{record}/edit'),
        ];
    }
}
