<?php

namespace App\Console\Commands;

use App\Models\Comic;
use Illuminate\Console\Command;

class IndexComicsForSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comics:index-search {--force : Force reindexing of all comics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index comics for search functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting comic search indexing...');

        if ($this->option('force')) {
            $this->info('Force reindexing enabled - clearing existing index...');
            Comic::removeAllFromSearch();
        }

        $comics = Comic::where('is_visible', true)
            ->whereNotNull('published_at')
            ->get();

        if ($comics->isEmpty()) {
            $this->warn('No comics found to index.');
            return;
        }

        $this->info("Found {$comics->count()} comics to index.");

        $bar = $this->output->createProgressBar($comics->count());
        $bar->start();

        $indexed = 0;
        $errors = 0;

        foreach ($comics as $comic) {
            try {
                $comic->searchable();
                $indexed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("Failed to index comic ID {$comic->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Indexing completed!");
        $this->info("Successfully indexed: {$indexed} comics");
        
        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors} comics");
        }

        // Display index statistics
        $this->displayIndexStats();
    }

    /**
     * Display search index statistics
     */
    protected function displayIndexStats()
    {
        $this->newLine();
        $this->info('Search Index Statistics:');
        
        $totalComics = Comic::count();
        $visibleComics = Comic::where('is_visible', true)->count();
        $publishedComics = Comic::where('is_visible', true)
            ->whereNotNull('published_at')
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Comics', $totalComics],
                ['Visible Comics', $visibleComics],
                ['Published Comics', $publishedComics],
                ['Searchable Comics', $publishedComics], // Same as published for now
            ]
        );

        // Show genre distribution
        $genreStats = Comic::where('is_visible', true)
            ->whereNotNull('published_at')
            ->selectRaw('genre, COUNT(*) as count')
            ->groupBy('genre')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($genreStats->isNotEmpty()) {
            $this->newLine();
            $this->info('Top Genres in Search Index:');
            
            $genreData = $genreStats->map(function ($stat) {
                return [$stat->genre ?: 'Unknown', $stat->count];
            })->toArray();

            $this->table(['Genre', 'Count'], $genreData);
        }
    }
}