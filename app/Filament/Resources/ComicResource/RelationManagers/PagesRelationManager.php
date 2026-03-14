<?php

namespace App\Filament\Resources\ComicResource\RelationManagers;

use App\Models\ComicPage;
use App\Services\CloudinaryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;

class PagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pages';

    protected static ?string $title = 'Comic Pages';

    protected static ?string $recordTitleAttribute = 'page_number';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('page_number')
                ->required()
                ->numeric()
                ->minValue(1)
                ->label('Page Number'),

            Forms\Components\Placeholder::make('preview')
                ->label('Current Image')
                ->content(fn ($record) => $record?->image_url
                    ? new \Illuminate\Support\HtmlString(
                        '<img src="' . e($record->image_url) . '" style="max-height:200px;border-radius:8px;" />'
                    )
                    : 'No image')
                ->visible(fn ($record) => $record !== null),

            Forms\Components\Placeholder::make('dimensions')
                ->label('Dimensions')
                ->content(fn ($record) => $record
                    ? ($record->width ?? '?') . ' x ' . ($record->height ?? '?') . ' px'
                    : '-')
                ->visible(fn ($record) => $record !== null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('page_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Preview')
                    ->height(100)
                    ->width(70),

                Tables\Columns\TextColumn::make('width')
                    ->label('Width')
                    ->suffix('px')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('height')
                    ->label('Height')
                    ->suffix('px')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state
                        ? ($state > 1048576
                            ? number_format($state / 1048576, 1) . ' MB'
                            : number_format($state / 1024, 1) . ' KB')
                        : '-')
                    ->toggleable(),
            ])
            ->defaultSort('page_number')
            ->reorderable('page_number')
            ->headerActions([
                Action::make('uploadPages')
                    ->label('Upload Pages')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Upload Comic Pages')
                    ->modalDescription('Drag and drop image files or click to browse. Pages will be numbered automatically starting after the last existing page.')
                    ->modalWidth('xl')
                    ->form([
                        Forms\Components\FileUpload::make('pages')
                            ->label('Drop images here')
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->maxFiles(50)
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('150')
                            ->helperText('Drag & drop page images in reading order. JPG, PNG, WebP accepted. Max 10MB each, up to 50 files.')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->uploadPagesToCloudinary($data['pages']);
                    }),

                Action::make('uploadZip')
                    ->label('Upload ZIP')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('success')
                    ->modalHeading('Upload Pages from ZIP')
                    ->modalDescription('Upload a ZIP file containing page images. Images will be sorted by filename and numbered automatically.')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\FileUpload::make('zip_file')
                            ->label('ZIP File')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->maxSize(102400) // 100MB
                            ->helperText('ZIP containing JPG, PNG, or WebP images. Max 100MB. Images are sorted by filename.')
                            ->required(),

                        Forms\Components\Toggle::make('replace_existing')
                            ->label('Replace all existing pages')
                            ->helperText('If enabled, all current pages will be deleted before uploading.')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        $this->handleZipUpload($data['zip_file'], $data['replace_existing'] ?? false);
                    }),

                Action::make('replaceAllPages')
                    ->label('Replace All Pages')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->modalHeading('Replace All Comic Pages')
                    ->modalDescription('This will DELETE all existing pages and upload new ones. This cannot be undone.')
                    ->modalWidth('xl')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\FileUpload::make('pages')
                            ->label('Drop images here')
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->maxFiles(50)
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('150')
                            ->helperText('Drag & drop page images in reading order. JPG, PNG, WebP accepted.')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $comic = $this->getOwnerRecord();
                        $cloudinary = app(CloudinaryService::class);

                        // Delete all existing pages from Cloudinary
                        foreach ($comic->pages as $page) {
                            if ($page->image_path && $cloudinary->isConfigured()) {
                                try {
                                    $cloudinary->deleteImage($page->image_path);
                                } catch (\Exception $e) {
                                    Log::warning('Failed to delete page from Cloudinary during replace', [
                                        'path' => $page->image_path,
                                    ]);
                                }
                            }
                        }

                        // Delete all page records
                        $comic->pages()->delete();

                        // Upload new pages starting from 1
                        $this->uploadPagesToCloudinary($data['pages'], startPage: 1);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),

                Action::make('replaceImage')
                    ->label('Replace')
                    ->icon('heroicon-o-photo')
                    ->iconButton()
                    ->color('warning')
                    ->modalHeading(fn (ComicPage $record) => 'Replace Page ' . $record->page_number)
                    ->form([
                        Forms\Components\FileUpload::make('image')
                            ->label('New Image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->imagePreviewHeight('200')
                            ->required(),
                    ])
                    ->action(function (ComicPage $record, array $data) {
                        $comic = $this->getOwnerRecord();
                        $cloudinary = app(CloudinaryService::class);

                        if (!$cloudinary->isConfigured()) {
                            Notification::make()
                                ->title('Cloudinary not configured')
                                ->danger()
                                ->send();
                            return;
                        }

                        $filePath = $this->resolveUploadedFilePath($data['image']);
                        if (!$filePath) {
                            Notification::make()->title('File not found')->danger()->send();
                            return;
                        }

                        // Delete old image from Cloudinary
                        if ($record->image_path) {
                            try {
                                $cloudinary->deleteImage($record->image_path);
                            } catch (\Exception $e) {
                                Log::warning('Failed to delete old page image', ['path' => $record->image_path]);
                            }
                        }

                        // Upload new image
                        $result = $cloudinary->uploadPage($filePath, $comic->slug, $record->page_number);

                        if ($result['success']) {
                            $record->update([
                                'image_url' => $result['url'],
                                'image_path' => $result['public_id'],
                                'width' => $result['width'] ?? null,
                                'height' => $result['height'] ?? null,
                                'file_size' => $result['size'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Page ' . $record->page_number . ' replaced')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Upload failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }

                        @unlink($filePath);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->before(function (ComicPage $record) {
                        if ($record->image_path) {
                            try {
                                $cloudinary = app(CloudinaryService::class);
                                if ($cloudinary->isConfigured()) {
                                    $cloudinary->deleteImage($record->image_path);
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to delete page from Cloudinary', [
                                    'path' => $record->image_path,
                                ]);
                            }
                        }
                    })
                    ->after(function () {
                        $this->renumberAndUpdateCount();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $cloudinary = app(CloudinaryService::class);
                            if (!$cloudinary->isConfigured()) {
                                return;
                            }
                            foreach ($records as $record) {
                                if ($record->image_path) {
                                    try {
                                        $cloudinary->deleteImage($record->image_path);
                                    } catch (\Exception $e) {
                                        Log::warning('Bulk delete: failed to remove from Cloudinary', [
                                            'path' => $record->image_path,
                                        ]);
                                    }
                                }
                            }
                        })
                        ->after(function () {
                            $this->renumberAndUpdateCount();
                        }),
                ]),
            ]);
    }

    /**
     * Extract images from a ZIP file and upload them to Cloudinary.
     */
    protected function handleZipUpload(string $zipFilePath, bool $replaceExisting): void
    {
        $fullPath = $this->resolveUploadedFilePath($zipFilePath);
        if (!$fullPath) {
            Notification::make()->title('ZIP file not found')->danger()->send();
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($fullPath) !== true) {
            Notification::make()->title('Failed to open ZIP file')->danger()->send();
            @unlink($fullPath);
            return;
        }

        // Extract image files, skip directories and non-image files
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $imageFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Skip directories, macOS resource forks, and hidden files
            if (str_ends_with($name, '/') || str_contains($name, '__MACOSX') || str_contains($name, '/.')) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExtensions)) {
                $imageFiles[] = $name;
            }
        }

        if (empty($imageFiles)) {
            $zip->close();
            @unlink($fullPath);
            Notification::make()
                ->title('No images found in ZIP')
                ->body('The ZIP file must contain JPG, PNG, or WebP images.')
                ->danger()
                ->send();
            return;
        }

        // Sort by filename for correct page order
        sort($imageFiles, SORT_NATURAL | SORT_FLAG_CASE);

        // Extract to a temp directory
        $extractDir = storage_path('app/zip-extract-' . uniqid());
        @mkdir($extractDir, 0755, true);

        $extractedPaths = [];
        foreach ($imageFiles as $name) {
            $content = $zip->getFromName($name);
            if ($content === false) {
                continue;
            }

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
            $destPath = $extractDir . '/' . $safeName;
            file_put_contents($destPath, $content);
            $extractedPaths[] = $destPath;
        }

        $zip->close();
        @unlink($fullPath);

        if (empty($extractedPaths)) {
            $this->cleanupDirectory($extractDir);
            Notification::make()->title('Failed to extract images')->danger()->send();
            return;
        }

        // If replacing, delete existing pages first
        if ($replaceExisting) {
            $comic = $this->getOwnerRecord();
            $cloudinary = app(CloudinaryService::class);

            foreach ($comic->pages as $page) {
                if ($page->image_path && $cloudinary->isConfigured()) {
                    try {
                        $cloudinary->deleteImage($page->image_path);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete page during ZIP replace', ['path' => $page->image_path]);
                    }
                }
            }
            $comic->pages()->delete();
        }

        // Upload extracted images (paths are already absolute)
        $startPage = $replaceExisting ? 1 : null;
        $this->uploadPagesToCloudinary($extractedPaths, startPage: $startPage, absolutePaths: true);

        // Clean up extract directory
        $this->cleanupDirectory($extractDir);
    }

    /**
     * Remove a temporary directory and its contents.
     */
    protected function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /**
     * Upload page images to Cloudinary and create ComicPage records.
     */
    protected function uploadPagesToCloudinary(array $filePaths, ?int $startPage = null, bool $absolutePaths = false): void
    {
        $comic = $this->getOwnerRecord();
        $cloudinary = app(CloudinaryService::class);

        if (!$cloudinary->isConfigured()) {
            Notification::make()
                ->title('Cloudinary not configured')
                ->body('Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in your .env file.')
                ->danger()
                ->send();
            return;
        }

        $startPage ??= ($comic->pages()->max('page_number') ?? 0) + 1;
        $uploaded = 0;
        $errors = 0;

        foreach ($filePaths as $index => $filePathOrTmp) {
            $pageNumber = $startPage + $index;

            try {
                $fullPath = $absolutePaths ? $filePathOrTmp : $this->resolveUploadedFilePath($filePathOrTmp);
                if (!$fullPath || !file_exists($fullPath)) {
                    $errors++;
                    continue;
                }

                $result = $cloudinary->uploadPage($fullPath, $comic->slug, $pageNumber);

                if ($result['success']) {
                    ComicPage::create([
                        'comic_id' => $comic->id,
                        'page_number' => $pageNumber,
                        'image_url' => $result['url'],
                        'image_path' => $result['public_id'],
                        'width' => $result['width'] ?? null,
                        'height' => $result['height'] ?? null,
                        'file_size' => $result['size'] ?? null,
                    ]);
                    $uploaded++;
                } else {
                    $errors++;
                    Log::warning('Page upload failed', [
                        'comic' => $comic->slug,
                        'page' => $pageNumber,
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                }

                // Clean up temp file
                @unlink($fullPath);
            } catch (\Exception $e) {
                $errors++;
                Log::error('Page upload exception', [
                    'comic' => $comic->slug,
                    'page' => $pageNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update comic page count
        $comic->update(['page_count' => $comic->pages()->count()]);

        if ($uploaded > 0) {
            Notification::make()
                ->title($uploaded . ' page(s) uploaded')
                ->body($errors > 0 ? "{$errors} failed." : 'All pages uploaded successfully.')
                ->success()
                ->send();
        } elseif ($errors > 0) {
            Notification::make()
                ->title('Upload failed')
                ->body("All {$errors} page(s) failed to upload. Check logs for details.")
                ->danger()
                ->send();
        }
    }

    /**
     * Resolve a Filament-uploaded file to its full filesystem path.
     */
    protected function resolveUploadedFilePath(string $filePath): ?string
    {
        // Try livewire-tmp directory first (Filament's default temp location)
        $livewirePath = storage_path('app/livewire-tmp/' . $filePath);
        if (file_exists($livewirePath)) {
            return $livewirePath;
        }

        // Try public disk
        $publicPath = storage_path('app/public/' . $filePath);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        // Try app storage root
        $appPath = storage_path('app/' . $filePath);
        if (file_exists($appPath)) {
            return $appPath;
        }

        // Try as absolute path
        if (file_exists($filePath)) {
            return $filePath;
        }

        Log::warning('Could not resolve uploaded file path', ['path' => $filePath]);
        return null;
    }

    /**
     * Renumber pages sequentially and update the comic's page count.
     */
    protected function renumberAndUpdateCount(): void
    {
        $comic = $this->getOwnerRecord();
        $comic->pages()->orderBy('page_number')->get()->each(function ($page, $index) {
            $page->update(['page_number' => $index + 1]);
        });
        $comic->update(['page_count' => $comic->pages()->count()]);
    }
}
