<?php

namespace Database\Seeders;

use App\Models\Comic;
use App\Models\ComicSeries;
use Illuminate\Database\Seeder;

class ComicSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some popular comic series
        $series = [
            [
                'name' => 'The Amazing Spider-Man',
                'description' => 'Follow the adventures of Peter Parker as he balances his life as a high school student and the web-slinging superhero Spider-Man.',
                'publisher' => 'Marvel Comics',
                'status' => 'ongoing',
            ],
            [
                'name' => 'Batman',
                'description' => 'The Dark Knight protects Gotham City from criminals and supervillains in this iconic series.',
                'publisher' => 'DC Comics',
                'status' => 'ongoing',
            ],
            [
                'name' => 'The Walking Dead',
                'description' => 'A post-apocalyptic horror series following survivors in a world overrun by zombies.',
                'publisher' => 'Image Comics',
                'status' => 'completed',
            ],
            [
                'name' => 'Saga',
                'description' => 'An epic space opera/fantasy comic about a family from opposite sides of a galactic war.',
                'publisher' => 'Image Comics',
                'status' => 'hiatus',
            ],
            [
                'name' => 'Watchmen',
                'description' => 'A groundbreaking superhero deconstruction set in an alternate 1985.',
                'publisher' => 'DC Comics',
                'status' => 'completed',
            ],
        ];

        foreach ($series as $seriesData) {
            $comicSeries = ComicSeries::firstOrCreate(
                ['name' => $seriesData['name']],
                $seriesData
            );
            
            // Create some issues for each series
            $issueCount = rand(5, 15);
            for ($i = 1; $i <= $issueCount; $i++) {
                Comic::create([
                    'series_id' => $comicSeries->id,
                    'issue_number' => $i,
                    'title' => $comicSeries->name . ' #' . $i,
                    'slug' => \Illuminate\Support\Str::slug($comicSeries->name . ' ' . $i),
                    'author' => $this->getAuthorForSeries($comicSeries->name),
                    'publisher' => $comicSeries->publisher,
                    'genre' => $this->getGenreForSeries($comicSeries->name),
                    'description' => 'Issue #' . $i . ' of ' . $comicSeries->name . ' comic series.',
                    'page_count' => rand(20, 32),
                    'average_rating' => round(rand(30, 50) / 10, 1),
                    'total_ratings' => rand(10, 100),
                    'total_readers' => rand(50, 500),
                    'is_free' => $i <= 3, // First 3 issues free
                    'price' => $i <= 3 ? 0 : rand(199, 499) / 100,
                    'language' => 'English',
                    'published_at' => now()->subDays(rand(30, 365)),
                ]);
            }
            
            // Update the total issues count
            $comicSeries->updateTotalIssues();
        }

        // Create additional random series manually
        $additionalSeries = [
            ['name' => 'Quantum Heroes', 'publisher' => 'Image Comics', 'status' => 'ongoing'],
            ['name' => 'Stellar Knights', 'publisher' => 'Dark Horse Comics', 'status' => 'completed'],
            ['name' => 'Void Runners', 'publisher' => 'IDW Publishing', 'status' => 'ongoing'],
            ['name' => 'Crystal Defenders', 'publisher' => 'Boom! Studios', 'status' => 'hiatus'],
            ['name' => 'Storm Riders', 'publisher' => 'Valiant Entertainment', 'status' => 'ongoing'],
        ];

        foreach ($additionalSeries as $seriesData) {
            $series = ComicSeries::firstOrCreate(
                ['name' => $seriesData['name']],
                array_merge($seriesData, ['description' => 'An exciting comic series featuring ' . strtolower($seriesData['name']) . '.'])
            );
            
            $issueCount = rand(3, 8);
            for ($i = 1; $i <= $issueCount; $i++) {
                Comic::create([
                    'series_id' => $series->id,
                    'issue_number' => $i,
                    'title' => $series->name . ' #' . $i,
                    'slug' => \Illuminate\Support\Str::slug($series->name . ' ' . $i),
                    'author' => $this->getAuthorForSeries($series->name),
                    'publisher' => $series->publisher,
                    'genre' => $this->getGenreForSeries($series->name),
                    'description' => 'Issue #' . $i . ' of ' . $series->name . ' comic series.',
                    'page_count' => rand(20, 32),
                    'average_rating' => round(rand(30, 50) / 10, 1),
                    'total_ratings' => rand(10, 100),
                    'total_readers' => rand(50, 500),
                    'is_free' => $i <= 2, // First 2 issues free
                    'price' => $i <= 2 ? 0 : rand(199, 399) / 100,
                    'language' => 'English',
                    'published_at' => now()->subDays(rand(30, 180)),
                ]);
            }
            $series->updateTotalIssues();
        }
    }

    private function getAuthorForSeries(string $seriesName): string
    {
        $authors = [
            'The Amazing Spider-Man' => 'Stan Lee',
            'Batman' => 'Bob Kane',
            'The Walking Dead' => 'Robert Kirkman',
            'Saga' => 'Brian K. Vaughan',
            'Watchmen' => 'Alan Moore',
        ];

        $fallbackAuthors = ['John Smith', 'Jane Doe', 'Mike Johnson', 'Sarah Wilson', 'David Brown'];
        return $authors[$seriesName] ?? $fallbackAuthors[array_rand($fallbackAuthors)];
    }

    private function getGenreForSeries(string $seriesName): string
    {
        $genres = [
            'The Amazing Spider-Man' => 'Superhero',
            'Batman' => 'Superhero',
            'The Walking Dead' => 'Horror',
            'Saga' => 'Science Fiction',
            'Watchmen' => 'Superhero',
        ];

        $fallbackGenres = ['Action', 'Adventure', 'Drama', 'Fantasy', 'Mystery'];
        return $genres[$seriesName] ?? $fallbackGenres[array_rand($fallbackGenres)];
    }
}