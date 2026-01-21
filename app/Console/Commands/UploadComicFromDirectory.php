<?php

namespace App\Console\Commands;

use App\Models\Comic;
use App\Models\ComicPage;
use App\Services\CloudinaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UploadComicFromDirectory extends Command
{
    protected $signature = 'comic:upload
                            {directory : Path to directory containing comic images (relative to public/)}
                            {--title= : Comic title}
                            {--author= : Comic author}
                            {--description= : Comic description}
                            {--genre= : Comic genre}
                            {--free : Mark comic as free}';

    protected $description = 'Upload a comic from a local directory to Cloudinary';

    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        parent::__construct();
        $this->cloudinary = $cloudinary;
    }

    public function handle(): int
    {
        $directory = public_path($this->argument('directory'));

        if (!is_dir($directory)) {
            $this->error("Directory not found: {$directory}");
            return Command::FAILURE;
        }

        // Get all image files
        $files = glob($directory . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
        sort($files, SORT_NATURAL);

        if (empty($files)) {
            $this->error('No image files found in directory');
            return Command::FAILURE;
        }

        $this->info("Found " . count($files) . " images in directory");

        // Get comic details
        $title = $this->option('title') ?? $this->ask('Comic title?', $this->extractTitleFromPath($directory));
        $author = $this->option('author') ?? $this->ask('Author?', 'Unknown');
        $description = $this->option('description') ?? $this->ask('Description?', '');
        $genre = $this->option('genre') ?? $this->choice('Genre?', [
            'Action', 'Fantasy', 'Sci-Fi', 'Horror', 'Mystery',
            'Romance', 'Slice of Life', 'Historical', 'Crime', 'General'
        ], 'Action');
        $isFree = $this->option('free') || $this->confirm('Is this comic free?', true);

        $slug = Str::slug($title);

        // Check if comic already exists
        if (Comic::where('slug', $slug)->exists()) {
            if (!$this->confirm("Comic '{$title}' already exists. Update it?", false)) {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }
            $comic = Comic::where('slug', $slug)->first();
            // Delete existing pages
            $comic->pages()->delete();
        } else {
            $comic = null;
        }

        $this->info("Starting upload to Cloudinary...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($files) + 1);
        $progressBar->start();

        DB::beginTransaction();

        try {
            // Create or update comic
            if (!$comic) {
                $comic = Comic::create([
                    'title' => $title,
                    'slug' => $slug,
                    'author' => $author,
                    'description' => $description,
                    'genre' => $genre,
                    'is_free' => $isFree,
                    'is_visible' => true,
                    'published_at' => now(),
                    'page_count' => count($files),
                ]);
            } else {
                $comic->update([
                    'title' => $title,
                    'author' => $author,
                    'description' => $description,
                    'genre' => $genre,
                    'is_free' => $isFree,
                    'page_count' => count($files),
                ]);
            }

            // Upload cover (first page)
            $coverResult = $this->cloudinary->uploadCover($files[0], $comic->slug);
            if ($coverResult['success']) {
                $comic->cover_image_path = $coverResult['url'];
                $comic->save();
                $this->line(" Cover uploaded: {$coverResult['url']}");
            } else {
                $this->warn(" Cover upload failed: {$coverResult['error']}");
            }
            $progressBar->advance();

            // Upload all pages
            $successCount = 0;
            $errorCount = 0;

            foreach ($files as $index => $filePath) {
                $pageNumber = $index + 1;

                $result = $this->cloudinary->uploadPage($filePath, $comic->slug, $pageNumber);

                if ($result['success']) {
                    ComicPage::create([
                        'comic_id' => $comic->id,
                        'page_number' => $pageNumber,
                        'image_url' => $result['url'],
                        'image_path' => $result['public_id'],
                        'width' => $result['width'],
                        'height' => $result['height'],
                        'file_size' => $result['size'],
                    ]);
                    $successCount++;
                } else {
                    $this->warn(" Page {$pageNumber} failed: {$result['error']}");
                    $errorCount++;
                }

                $progressBar->advance();
            }

            DB::commit();

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Upload complete!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Comic ID', $comic->id],
                    ['Title', $comic->title],
                    ['Slug', $comic->slug],
                    ['Author', $comic->author],
                    ['Genre', $comic->genre],
                    ['Pages Uploaded', $successCount],
                    ['Pages Failed', $errorCount],
                    ['Cover URL', $comic->cover_image_path ?? 'N/A'],
                ]
            );

            $this->newLine();
            $this->info("View comic at: /comics/{$comic->slug}");
            $this->info("API endpoint: /api/v2/comics/{$comic->slug}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine();
            $this->error("Upload failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function extractTitleFromPath(string $path): string
    {
        $basename = basename($path);
        // Clean up common patterns
        $title = preg_replace('/[_-]+/', ' ', $basename);
        $title = preg_replace('/\s+/', ' ', $title);
        return ucwords(trim($title));
    }
}
