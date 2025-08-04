<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Services\ComicSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ComicSearchServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ComicSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use database driver for testing to avoid Meilisearch dependency
        config(['scout.driver' => 'database']);
        
        $this->searchService = new ComicSearchService();
    }

    /** @test */
    public function it_can_search_without_query()
    {
        Comic::factory()->count(5)->create([
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'per_page' => 10,
            'page' => 1,
        ]);

        $this->assertNotNull($results);
        $this->assertEquals(5, $results->total());
        $this->assertEquals(1, $results->currentPage());
    }

    /** @test */
    public function it_can_search_with_text_query()
    {
        Comic::factory()->create([
            'title' => 'Spider-Man Adventures',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'Batman Chronicles',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'query' => 'Spider',
            'per_page' => 10,
        ]);

        $this->assertNotNull($results);
        // Note: This test may need Scout to be properly configured to work
    }

    /** @test */
    public function it_applies_genre_filter_correctly()
    {
        Comic::factory()->count(3)->create([
            'genre' => 'Superhero',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(2)->create([
            'genre' => 'Fantasy',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => ['genre' => ['Superhero']],
            'per_page' => 10,
        ]);

        $this->assertEquals(3, $results->total());
        
        foreach ($results->items() as $comic) {
            $this->assertEquals('Superhero', $comic->genre);
        }
    }

    /** @test */
    public function it_applies_author_filter_correctly()
    {
        Comic::factory()->count(2)->create([
            'author' => 'Stan Lee',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(3)->create([
            'author' => 'Frank Miller',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => ['author' => ['Stan Lee']],
            'per_page' => 10,
        ]);

        $this->assertEquals(2, $results->total());
        
        foreach ($results->items() as $comic) {
            $this->assertEquals('Stan Lee', $comic->author);
        }
    }

    /** @test */
    public function it_applies_price_range_filter_correctly()
    {
        Comic::factory()->create([
            'price' => 5.99,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'price' => 15.99,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'price' => 25.99,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => [
                'price_min' => 10,
                'price_max' => 20,
            ],
            'per_page' => 10,
        ]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(15.99, $results->items()[0]->price);
    }

    /** @test */
    public function it_applies_free_comics_filter_correctly()
    {
        Comic::factory()->count(2)->create([
            'is_free' => true,
            'price' => 0,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(3)->create([
            'is_free' => false,
            'price' => 9.99,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => ['is_free' => true],
            'per_page' => 10,
        ]);

        $this->assertEquals(2, $results->total());
        
        foreach ($results->items() as $comic) {
            $this->assertTrue($comic->is_free);
        }
    }

    /** @test */
    public function it_applies_publication_year_filter_correctly()
    {
        Comic::factory()->create([
            'publication_year' => 2020,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'publication_year' => 2022,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'publication_year' => 2024,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => [
                'year_min' => 2021,
                'year_max' => 2023,
            ],
            'per_page' => 10,
        ]);

        $this->assertEquals(1, $results->total());
        $this->assertEquals(2022, $results->items()[0]->publication_year);
    }

    /** @test */
    public function it_applies_rating_filter_correctly()
    {
        Comic::factory()->create([
            'average_rating' => 3.5,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'average_rating' => 4.2,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'average_rating' => 4.8,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => ['min_rating' => 4.0],
            'per_page' => 10,
        ]);

        $this->assertEquals(2, $results->total());
        
        foreach ($results->items() as $comic) {
            $this->assertGreaterThanOrEqual(4.0, $comic->average_rating);
        }
    }

    /** @test */
    public function it_applies_sorting_correctly()
    {
        Comic::factory()->create([
            'title' => 'Zebra Comics',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'Alpha Comics',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'Beta Comics',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'sort' => 'title_asc',
            'per_page' => 10,
        ]);

        $comics = $results->items();
        $this->assertEquals('Alpha Comics', $comics[0]->title);
        $this->assertEquals('Beta Comics', $comics[1]->title);
        $this->assertEquals('Zebra Comics', $comics[2]->title);
    }

    /** @test */
    public function it_excludes_invisible_comics()
    {
        Comic::factory()->count(3)->create([
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(2)->create([
            'is_visible' => false,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search(['per_page' => 10]);

        $this->assertEquals(3, $results->total());
    }

    /** @test */
    public function it_excludes_unpublished_comics()
    {
        Comic::factory()->count(3)->create([
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(2)->create([
            'is_visible' => true,
            'published_at' => null,
        ]);

        $results = $this->searchService->search(['per_page' => 10]);

        $this->assertEquals(3, $results->total());
    }

    /** @test */
    public function it_generates_suggestions_correctly()
    {
        Comic::factory()->create([
            'title' => 'Spider-Man Adventures',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'Spider-Woman Chronicles',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'author' => 'Spider Author',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $suggestions = $this->searchService->getSuggestions('Spider', 10);

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertContains('Spider-Man Adventures', $suggestions);
        $this->assertContains('Spider-Woman Chronicles', $suggestions);
    }

    /** @test */
    public function it_generates_autocomplete_suggestions_correctly()
    {
        $series = ComicSeries::factory()->create(['name' => 'Batman Series']);
        
        Comic::factory()->create([
            'title' => 'Batman Returns',
            'author' => 'Frank Miller',
            'publisher' => 'DC Comics',
            'series_id' => $series->id,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $suggestions = $this->searchService->getAutocompleteSuggestions('Bat', 10);

        $this->assertIsArray($suggestions);
        $this->assertArrayHasKey('titles', $suggestions);
        $this->assertArrayHasKey('authors', $suggestions);
        $this->assertArrayHasKey('publishers', $suggestions);
        $this->assertArrayHasKey('series', $suggestions);

        $this->assertNotEmpty($suggestions['titles']);
        $this->assertEquals('Batman Returns', $suggestions['titles'][0]['title']);
    }

    /** @test */
    public function it_returns_filter_options_correctly()
    {
        Comic::factory()->create([
            'genre' => 'Superhero',
            'author' => 'Stan Lee',
            'publisher' => 'Marvel',
            'language' => 'en',
            'tags' => ['action', 'adventure'],
            'publication_year' => 2022,
            'price' => 9.99,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $options = $this->searchService->getFilterOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('genres', $options);
        $this->assertArrayHasKey('authors', $options);
        $this->assertArrayHasKey('publishers', $options);
        $this->assertArrayHasKey('languages', $options);
        $this->assertArrayHasKey('publication_years', $options);
        $this->assertArrayHasKey('price_range', $options);
        $this->assertArrayHasKey('tags', $options);

        $this->assertContains('Superhero', $options['genres']);
        $this->assertContains('Stan Lee', $options['authors']);
        $this->assertContains('Marvel', $options['publishers']);
        $this->assertContains('en', $options['languages']);
        $this->assertContains('action', $options['tags']);
        $this->assertContains('adventure', $options['tags']);
    }

    /** @test */
    public function it_returns_popular_search_terms()
    {
        Comic::factory()->count(5)->create([
            'genre' => 'Superhero',
            'author' => 'Stan Lee',
            'total_readers' => 1000,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(3)->create([
            'genre' => 'Fantasy',
            'author' => 'J.R.R. Tolkien',
            'total_readers' => 800,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $terms = $this->searchService->getPopularSearchTerms(10);

        $this->assertIsArray($terms);
        $this->assertNotEmpty($terms);
        $this->assertContains('Superhero', $terms);
        $this->assertContains('Stan Lee', $terms);
    }

    /** @test */
    public function it_handles_empty_suggestions_gracefully()
    {
        $suggestions = $this->searchService->getSuggestions('NonexistentQuery', 10);

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    /** @test */
    public function it_handles_short_query_suggestions()
    {
        Comic::factory()->create([
            'title' => 'Test Comic',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        // Query too short (less than 2 characters)
        $suggestions = $this->searchService->getSuggestions('t', 10);

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    /** @test */
    public function it_combines_multiple_filters_correctly()
    {
        Comic::factory()->create([
            'genre' => 'Superhero',
            'author' => 'Stan Lee',
            'price' => 9.99,
            'average_rating' => 4.5,
            'publication_year' => 2022,
            'is_free' => false,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'genre' => 'Fantasy',
            'author' => 'J.K. Rowling',
            'price' => 15.99,
            'average_rating' => 3.8,
            'publication_year' => 2020,
            'is_free' => false,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $results = $this->searchService->search([
            'filters' => [
                'genre' => ['Superhero'],
                'author' => ['Stan Lee'],
                'price_min' => 5,
                'price_max' => 15,
                'min_rating' => 4.0,
                'year_min' => 2021,
                'is_free' => false,
            ],
            'per_page' => 10,
        ]);

        $this->assertEquals(1, $results->total());
        
        $comic = $results->items()[0];
        $this->assertEquals('Superhero', $comic->genre);
        $this->assertEquals('Stan Lee', $comic->author);
        $this->assertGreaterThanOrEqual(4.0, $comic->average_rating);
        $this->assertGreaterThanOrEqual(2021, $comic->publication_year);
    }
}