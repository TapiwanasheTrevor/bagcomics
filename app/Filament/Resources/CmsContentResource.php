<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsContentResource\Pages;
use App\Filament\Resources\CmsContentResource\RelationManagers;
use App\Models\CmsContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CmsContentResource extends Resource
{
    protected static ?string $model = CmsContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'CMS Content';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Content Details')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique identifier for this content piece (e.g., hero_title, about_text)'),

                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human readable title for admin reference'),

                        Forms\Components\Select::make('section')
                            ->required()
                            ->options([
                                'hero' => 'Hero Section',
                                'about' => 'About Section',
                                'features' => 'Features Section',
                                'footer' => 'Footer',
                                'navigation' => 'Navigation',
                                'general' => 'General',
                            ])
                            ->helperText('Section where this content appears'),

                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'text' => 'Plain Text',
                                'rich_text' => 'Rich Text (HTML)',
                                'image' => 'Image',
                                'json' => 'JSON Data',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('content', null)),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order within section (lower numbers appear first)'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Whether this content is active and visible'),
                    ])->columns(2),

                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->rows(3)
                            ->visible(fn (callable $get) => in_array($get('type'), ['text', null]))
                            ->helperText('Plain text content'),

                        Forms\Components\RichEditor::make('content')
                            ->visible(fn (callable $get) => $get('type') === 'rich_text')
                            ->helperText('Rich text content with HTML formatting'),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Image')
                            ->directory('cms')
                            ->image()
                            ->imageEditor()
                            ->visible(fn (callable $get) => $get('type') === 'image')
                            ->helperText('Upload an image file'),

                        Forms\Components\Textarea::make('content')
                            ->label('JSON Data')
                            ->rows(5)
                            ->visible(fn (callable $get) => $get('type') === 'json')
                            ->helperText('JSON formatted data'),
                    ]),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->helperText('Additional metadata (alt text, dimensions, etc.)'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('section')
                    ->colors([
                        'primary' => 'hero',
                        'success' => 'about',
                        'warning' => 'features',
                        'danger' => 'footer',
                        'secondary' => 'navigation',
                        'info' => 'general',
                    ]),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'text',
                        'success' => 'rich_text',
                        'warning' => 'image',
                        'info' => 'json',
                    ]),

                Tables\Columns\TextColumn::make('content')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->visibility('private')
                    ->size(40),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('section')
                    ->options([
                        'hero' => 'Hero Section',
                        'about' => 'About Section',
                        'features' => 'Features Section',
                        'footer' => 'Footer',
                        'navigation' => 'Navigation',
                        'general' => 'General',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'text' => 'Plain Text',
                        'rich_text' => 'Rich Text',
                        'image' => 'Image',
                        'json' => 'JSON Data',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            ->defaultSort('section')
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsContents::route('/'),
            'create' => Pages\CreateCmsContent::route('/create'),
            'edit' => Pages\EditCmsContent::route('/{record}/edit'),
        ];
    }
}
