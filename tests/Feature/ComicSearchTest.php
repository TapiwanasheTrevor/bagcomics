<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Models\User;
use App\Services\ComicSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ComicSearchTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ComicSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use database driver for testing to avoid Meilisearch dependency
        config(['scout.driver' => 'database']);
        
        $this->searchService = app(ComicSearchService::class);
    }

    /** @test */
    public function it_can_search_comics_without_query()
    {
        // Create test comics
        Comic::factory()->count(15)->create([
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'genre',
                        'average_rating',
                        'price',
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'search_info',
            ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function it_can_search_comics_with_text_query()
    {
        // Create comics with specific titles
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

        $response = $this->getJson('/api/comics/search?query=Spider');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Spider', $response->json('search_info.query'));
    }

    /** @test */
    public function it_can_filter_comics_by_genre()
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

        $response = $this->getJson('/api/comics/search?filters[genre][]=Superhero');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertEquals('Superhero', $comic['genre']);
        }
    }

    /** @test */
    public function it_can_filter_comics_by_author()
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

        $response = $this->getJson('/api/comics/search?filters[author][]=Stan Lee');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertEquals('Stan Lee', $comic['author']);
        }
    }

    /** @test */
    public function it_can_filter_comics_by_price_range()
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

        $response = $this->getJson('/api/comics/search?filters[price_min]=10&filters[price_max]=20');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertGreaterThanOrEqual(10, $comic['price']);
            $this->assertLessThanOrEqual(20, $comic['price']);
        }
    }

    /** @test */
    public function it_can_filter_free_comics()
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

        $response = $this->getJson('/api/comics/search?filters[is_free]=1');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertTrue($comic['is_free']);
        }
    }

    /** @test */
    public function it_can_filter_by_publication_year()
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

        $response = $this->getJson('/api/comics/search?filters[year_min]=2021&filters[year_max]=2023');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertGreaterThanOrEqual(2021, $comic['publication_year']);
            $this->assertLessThanOrEqual(2023, $comic['publication_year']);
        }
    }

    /** @test */
    public function it_can_filter_by_minimum_rating()
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

        $response = $this->getJson('/api/comics/search?filters[min_rating]=4.0');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        foreach ($comics as $comic) {
            $this->assertGreaterThanOrEqual(4.0, $comic['average_rating']);
        }
    }

    /** @test */
    public function it_can_sort_comics_by_title()
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

        $response = $this->getJson('/api/comics/search?sort=title_asc');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        $titles = array_column($comics, 'title');
        
        $this->assertEquals('Alpha Comics', $titles[0]);
        $this->assertEquals('Beta Comics', $titles[1]);
        $this->assertEquals('Zebra Comics', $titles[2]);
    }

    /** @test */
    public function it_can_sort_comics_by_rating()
    {
        Comic::factory()->create([
            'title' => 'Low Rated',
            'average_rating' => 2.5,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'High Rated',
            'average_rating' => 4.8,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'title' => 'Medium Rated',
            'average_rating' => 3.7,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search?sort=rating_desc');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $comics = $response->json('data');
        $ratings = array_column($comics, 'average_rating');
        
        $this->assertEquals(4.8, $ratings[0]);
        $this->assertEquals(3.7, $ratings[1]);
        $this->assertEquals(2.5, $ratings[2]);
    }

    /** @test */
    public function it_handles_pagination_correctly()
    {
        Comic::factory()->count(25)->create([
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search?per_page=10&page=2');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $pagination = $response->json('pagination');
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
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

        $response = $this->getJson('/api/comics/search');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $this->assertEquals(3, $response->json('pagination.total'));
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

        $response = $this->getJson('/api/comics/search');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    /** @test */
    public function it_can_get_search_suggestions()
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

        $response = $this->getJson('/api/comics/search/suggestions?query=Spider');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
                'query',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Spider', $response->json('query'));
        $this->assertNotEmpty($response->json('data'));
    }

    /** @test */
    public function it_can_get_autocomplete_suggestions()
    {
        Comic::factory()->create([
            'title' => 'Batman Returns',
            'author' => 'Frank Miller',
            'publisher' => 'DC Comics',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search/autocomplete?query=Bat');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'titles',
                    'authors',
                    'publishers',
                    'series',
                ],
                'query',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Bat', $response->json('query'));
    }

    /** @test */
    public function it_can_get_filter_options()
    {
        Comic::factory()->create([
            'genre' => 'Superhero',
            'author' => 'Stan Lee',
            'publisher' => 'Marvel',
            'language' => 'en',
            'tags' => ['action', 'adventure'],
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search/filter-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'genres',
                    'authors',
                    'publishers',
                    'languages',
                    'publication_years',
                    'price_range',
                    'tags',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertContains('Superhero', $response->json('data.genres'));
        $this->assertContains('Stan Lee', $response->json('data.authors'));
        $this->assertContains('Marvel', $response->json('data.publishers'));
    }

    /** @test */
    public function it_can_get_popular_terms()
    {
        Comic::factory()->count(5)->create([
            'genre' => 'Superhero',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->count(3)->create([
            'genre' => 'Fantasy',
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search/popular-terms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data'));
    }

    /** @test */
    public function it_validates_search_parameters()
    {
        $response = $this->getJson('/api/comics/search?per_page=150'); // Exceeds max

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error',
                'errors',
            ]);

        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_validates_suggestion_parameters()
    {
        $response = $this->getJson('/api/comics/search/suggestions'); // Missing query

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_handles_empty_search_results()
    {
        $response = $this->getJson('/api/comics/search?query=NonexistentComic');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEmpty($response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    /** @test */
    public function it_can_combine_multiple_filters()
    {
        Comic::factory()->create([
            'genre' => 'Superhero',
            'author' => 'Stan Lee',
            'price' => 9.99,
            'average_rating' => 4.5,
            'publication_year' => 2022,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        Comic::factory()->create([
            'genre' => 'Fantasy',
            'author' => 'J.K. Rowling',
            'price' => 15.99,
            'average_rating' => 3.8,
            'publication_year' => 2020,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/comics/search?' . http_build_query([
            'filters' => [
                'genre' => ['Superhero'],
                'author' => ['Stan Lee'],
                'price_min' => 5,
                'price_max' => 15,
                'min_rating' => 4.0,
                'year_min' => 2021,
            ]
        ]));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('pagination.total'));
        
        $comic = $response->json('data.0');
        $this->assertEquals('Superhero', $comic['genre']);
        $this->assertEquals('Stan Lee', $comic['author']);
    }
}