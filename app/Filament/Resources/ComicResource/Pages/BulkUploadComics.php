<?php

namespace App\Filament\Resources\ComicResource\Pages;

use App\Filament\Resources\ComicResource;
use App\Models\Comic;
use App\Models\ComicSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkUploadComics extends Page
{
    protected static string $resource = ComicResource::class;
    protected static string $view = 'filament.resources.comic-resource.pages.bulk-upload-comics';
    protected static ?string $title = 'Bulk Upload Comics';
    protected static ?string $navigationLabel = 'Bulk Upload';

    public ?array $data = [];

    public static function getRoute(?string $tenant = null): string
    {
        return static::$resource::getUrl('bulk-upload', ['tenant' => $tenant]);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bulk Upload Settings')
                    ->description('Upload multiple comic files at once with shared metadata')
                    ->schema([
                        Forms\Components\Select::make('series_id')
                            ->label('Comic Series (Optional)')
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

                        Forms\Components\TextInput::make('author')
                            ->label('Default Author')
                            ->maxLength(255)
                            ->helperText('Will be applied to all uploaded comics if not specified individually'),

                        Forms\Components\TextInput::make('publisher')
                            ->label('Default Publisher')
                            ->maxLength(255),

                        Forms\Components\Select::make('genre')
                            ->label('Default Genre')
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
                            ->label('Default Language')
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

                        Forms\Components\Toggle::make('is_free')
                            ->label('Mark as Free')
                            ->default(true),

                        Forms\Components\TextInput::make('price')
                            ->label('Default Price')
                            ->numeric()
                            ->step(0.01)
                            ->visible(fn ($get) => !$get('is_free')),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Publish Immediately')
                            ->default(false)
                            ->helperText('If disabled, comics will be saved as drafts'),
                    ]),

                Forms\Components\Section::make('File Upload')
                    ->schema([
                        Forms\Components\FileUpload::make('comic_files')
                            ->label('Comic Files')
                            ->multiple()
                            ->disk('public')
                            ->directory('comics')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxFiles(20)
                            ->required()
                            ->helperText('Upload up to 20 PDF files at once. File names will be used as comic titles.'),

                        Forms\Components\FileUpload::make('cover_images')
                            ->label('Cover Images (Optional)')
                            ->multiple()
                            ->disk('public')
                            ->directory('covers')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('2:3')
                            ->imageResizeTargetWidth('400')
                            ->imageResizeTargetHeight('600')
                            ->maxFiles(20)
                            ->helperText('Upload cover images. They will be matched to comics by filename.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload Comics')
                ->color('success')
                ->action('uploadComics'),
        ];
    }

    public function uploadComics(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['comic_files'])) {
            Notification::make()
                ->title('No Files Selected')
                ->body('Please select at least one comic file to upload.')
                ->danger()
                ->send();
            return;
        }

        $uploadedCount = 0;
        $errors = [];

        foreach ($data['comic_files'] as $filePath) {
            try {
                $filename = basename($filePath);
                $title = $this->generateTitleFromFilename($filename);
                
                // Find matching cover image
                $coverImage = $this->findMatchingCoverImage($filename, $data['cover_images'] ?? []);
                
                $comic = Comic::create([
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'author' => $data['author'] ?? 'Unknown',
                    'publisher' => $data['publisher'] ?? null,
                    'genre' => $data['genre'] ?? 'action',
                    'language' => $data['language'] ?? 'en',
                    'series_id' => $data['series_id'] ?? null,
                    'pdf_file_path' => $filePath,
                    'cover_image_path' => $coverImage,
                    'is_free' => $data['is_free'] ?? true,
                    'price' => $data['is_free'] ? 0 : ($data['price'] ?? 0),
                    'is_visible' => $data['is_visible'] ?? false,
                    'published_at' => $data['is_visible'] ? now() : null,
                    'is_pdf_comic' => true,
                ]);

                $uploadedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to upload {$filename}: " . $e->getMessage();
            }
        }

        if ($uploadedCount > 0) {
            Notification::make()
                ->title('Bulk Upload Successful')
                ->body("{$uploadedCount} comics uploaded successfully.")
                ->success()
                ->send();
        }

        if (!empty($errors)) {
            Notification::make()
                ->title('Some Uploads Failed')
                ->body(implode("\n", array_slice($errors, 0, 3)))
                ->danger()
                ->send();
        }

        $this->redirect(ComicResource::getUrl('index'));
    }

    private function generateTitleFromFilename(string $filename): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', $title);
        return ucwords($title);
    }

    private function findMatchingCoverImage(string $comicFilename, array $coverImages): ?string
    {
        $comicBasename = pathinfo($comicFilename, PATHINFO_FILENAME);
        
        foreach ($coverImages as $coverPath) {
            $coverBasename = pathinfo(basename($coverPath), PATHINFO_FILENAME);
            if (strtolower($comicBasename) === strtolower($coverBasename)) {
                return $coverPath;
            }
        }
        
        return null;
    }
}