<?php

namespace Tests\Unit;

use App\Models\Comic;
use App\Models\ComicSeries;
use App\Models\User;
use App\Models\UserLibrary;
use App\Models\ComicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComicModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->series = ComicSeries::factory()->create([
            'name' => 'Test Series',
            'publisher' => 'Test Publisher'
        ]);
        
        $this->comic = Comic::factory()->create([
            'title' => 'Test Comic',
            'author' => 'Test Author',
            'genre' => 'superhero',
            'publisher' => 'Test Publisher',
            'series_id' => $this->series->id,
            'issue_number' => 1,
            'tags' => ['action', 'adventure'],
            'average_rating' => 4.5,
            'total_readers' => 100,
            'is_visible' => true
        ]);
        
        $this->user = User::factory()->create();
    }

    public function test_comic_belongs_to_series()
    {
        $this->assertInstanceOf(ComicSeries::class, $this->comic->series);
        $this->assertEquals($this->series->id, $this->comic->series->id);
    }

    public function test_comic_has_many_reviews()
    {
        ComicReview::factory()->count(3)->create(['comic_id' => $this->comic->id]);
        
        $this->assertCount(3, $this->comic->reviews);
        $this->assertInstanceOf(ComicReview::class, $this->comic->reviews->first());
    }

    public function test_get_recommended_comics_from_same_series()
    {
        // Create more comics in the same series
        $comic2 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 2,
            'is_visible' => true
        ]);
        $comic3 = Comic::factory()->create([
            'series_id' => $this->series->id,
            'issue_number' => 3,
            'is_visible' => true
        ]);

        $recommendations = $this->comic->getRecommendedComics(5);

        $this->assertCount(2, $recommendations);
        $this->assertTrue($recommendations->contains($comic2));
        $this->assertTrue($recommendations->contains($comic3));
    }

    public function test_get_recommended_comics_by_similarity()
    {
        // Create comics with similar attributes
        $similarComic = Comic::factory()->create([
            'genre' => 'superhero',
            'author' => 'Test Author',
            'publisher' => 'Test Publisher',
            'is_visible' => true,
            'average_rating' => 4.0
        ]);
        
        $differentComic = Comic::factory()->create([
            'genre' => 'horror',
            'author' => 'Different Author',
            'publisher' => 'Different Publisher',
            'is_visible' => true,
            'average_rating' => 2.0
        ]);

        $recommendations = $this->comic->getRecommendedComics(5);

        $this->assertTrue($recommendations->contains($similarComic));
        $this->assertFalse($recommendations->contains($differentComic));
    }

    public function test_get_similar_comics_by_score()
    {
        // Create comics with different similarity scores
        $highScoreComic = Comic::factory()->create([
            'genre' => 'superhero',
            'author' => 'Test Author',
            'publisher' => 'Test Publisher',
            'tags' => ['action', 'adventure', 'hero'],
            'is_visible' => true,
            'average_rating' => 4.5
        ]);
        
        $mediumScoreComic = Comic::factory()->create([
            'genre' => 'superhero',
            'author' => 'Different Author',
            'is_visible' => true,
            'average_rating' => 3.5
        ]);
        
        $lowScoreComic = Comic::factory()->create([
            'genre' => 'romance',
            'is_visible' => true,
            'average_rating' => 2.0
        ]);

        $similar = $this->comic->getSimilarComics(3);

        // High score comic should be first
        $this->assertEquals($highScoreComic->id, $similar->first()->id);
        $this->assertTrue($similar->contains($mediumScoreComic));
        $this->assertFalse($similar->contains($lowScoreComic));
    }

    public function test_get_collaborative_recommendations()
    {
        // Create users who rated this comic highly
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        UserLibrary::factory()->create([
            'user_id' => $user1->id,
            'comic_id' => $this->comic->id,
            'rating' => 5
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $user2->id,
            'comic_id' => $this->comic->id,
            'rating' => 4
        ]);
        
        // Create comics these users also rated highly
        $recommendedComic = Comic::factory()->create(['is_visible' => true]);
        
        UserLibrary::factory()->create([
            'user_id' => $user1->id,
            'comic_id' => $recommendedComic->id,
            'rating' => 5
        ]);
        
        UserLibrary::factory()->create([
            'user_id' => $user2->id,
            'comic_id' => $recommendedComic->id,
            'rating' => 4
        ]);

        $recommendations = $this->comic->getCollaborativeRecommendations(5);

        $this->assertTrue($recommendations->contains($recommendedComic));
    }

    public function test_update_average_rating()
    {
        // Create reviews with different ratings
        ComicReview::factory()->create([
            'comic_id' => $this->comic->id,
            'rating' => 5,
            'is_approved' => true
        ]);
        
        ComicReview::factory()->create([
            'comic_id' => $this->comic->id,
            'rating' => 3,
            'is_approved' => true
        ]);
        
        ComicReview::factory()->create([
            'comic_id' => $this->comic->id,
            'rating' => 2,
            'is_approved' => false // Should not be counted
        ]);

        $this->comic->updateAverageRating();

        $this->assertEquals(4.0, $this->comic->average_rating);
        $this->assertEquals(2, $this->comic->total_ratings);
    }

    public function test_tag_management()
    {
        $this->assertEquals(['action', 'adventure'], $this->comic->getTagsArray());
        $this->assertTrue($this->comic->hasTag('action'));
        $this->assertFalse($this->comic->hasTag('romance'));

        $this->comic->addTag('superhero');
        $this->assertTrue($this->comic->hasTag('superhero'));
        $this->assertCount(3, $this->comic->getTagsArray());

        $this->comic->removeTag('action');
        $this->assertFalse($this->comic->hasTag('action'));
        $this->assertCount(2, $this->comic->getTagsArray());
    }

    public function test_metadata_extraction_methods()
    {
        $metadata = [
            'pdf_title' => 'Extracted Title',
            'pdf_author' => 'Extracted Author',
            'suggested_tags' => ['sci-fi', 'action']
        ];
        
        $this->comic->setPdfMetadata($metadata);
        
        $this->assertEquals($metadata, $this->comic->getPdfMetadata());
    }

    public function test_auto_populate_from_metadata()
    {
        // Create comic with empty fields
        $emptyComic = Comic::factory()->create([
            'title' => '',
            'author' => '',
            'description' => '',
            'tags' => []
        ]);
        
        $metadata = [
            'pdf_title' => 'Metadata Title',
            'pdf_author' => 'Metadata Author',
            'pdf_subject' => 'Metadata Description',
            'suggested_tags' => ['fantasy', 'magic']
        ];
        
        $emptyComic->setPdfMetadata($metadata);
        $updated = $emptyComic->autoPopulateFromMetadata();
        
        $this->assertTrue($updated);
        $this->assertEquals('Metadata Title', $emptyComic->title);
        $this->assertEquals('Metadata Author', $emptyComic->author);
        $this->assertEquals('Metadata Description', $emptyComic->description);
        $this->assertEquals(['fantasy', 'magic'], $emptyComic->tags);
    }

    public function test_generate_suggested_tags()
    {
        $comic = new Comic();
        $reflection = new \ReflectionClass($comic);
        $method = $reflection->getMethod('generateSuggestedTags');
        $method->setAccessible(true);

        $text = 'This is a story about a superhero with magic powers fighting aliens in space';
        $tags = $method->invoke($comic, $text);

        $this->assertContains('superhero', $tags);
        $this->assertContains('fantasy', $tags);
        $this->assertContains('sci-fi', $tags);
    }

    public function test_reading_time_estimate()
    {
        $comic = Comic::factory()->create(['page_count' => 20]);
        $this->assertEquals(40, $comic->getReadingTimeEstimate()); // 2 minutes per page
    }

    public function test_is_new_release()
    {
        $newComic = Comic::factory()->create(['published_at' => now()->subDays(15)]);
        $oldComic = Comic::factory()->create(['published_at' => now()->subDays(45)]);

        $this->assertTrue($newComic->isNewRelease());
        $this->assertFalse($oldComic->isNewRelease());
    }

    public function test_formatted_file_size()
    {
        $comic = Comic::factory()->create(['pdf_file_size' => 1048576]); // 1MB
        $this->assertEquals('1 MB', $comic->getFormattedFileSize());

        $comic = Comic::factory()->create(['pdf_file_size' => 1024]); // 1KB
        $this->assertEquals('1 KB', $comic->getFormattedFileSize());
    }
}