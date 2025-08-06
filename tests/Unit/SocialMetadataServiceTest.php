<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Comic;
use App\Models\ComicSeries;
use App\Services\SocialMetadataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SocialMetadataServiceTest extends TestCase
{
    use RefreshDatabase;

    private SocialMetadataService $socialMetadataService;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->socialMetadataService = new SocialMetadataService();
        
        $series = ComicSeries::factory()->create(['name' => 'Test Series']);
        
        $this->comic = Comic::factory()->create([
            'title' => 'Test Comic',
            'author' => 'Test Author',
            'publisher' => 'Test Publisher',
            'genre' => 'Action',
            'description' => 'This is a test comic description.',
            'cover_image_path' => 'covers/test-comic.jpg',
            'isbn' => '978-0123456789',
            'page_count' => 100,
            'language' => 'en',
            'average_rating' => 4.5,
            'total_ratings' => 10,
            'series_id' => $series->id,
            'issue_number' => 1,
            'price' => 9.99,
            'is_free' => false,
            'published_at' => now(),
        ]);
    }

    public function test_generates_open_graph_metadata()
    {
        $metadata = $this->socialMetadataService->generateOpenGraphMetadata($this->comic);

        $this->assertEquals($this->comic->title, $metadata['og:title']);
        $this->assertEquals('article', $metadata['og:type']);
        $this->assertStringContainsString($this->comic->slug, $metadata['og:url']);
        $this->assertStringContainsString('storage/covers/test-comic.jpg', $metadata['og:image']);
        $this->assertEquals($this->comic->author, $metadata['article:author']);
        $this->assertEquals($this->comic->genre, $metadata['article:section']);
    }

    public function test_generates_twitter_card_metadata()
    {
        $metadata = $this->socialMetadataService->generateTwitterCardMetadata($this->comic);

        $this->assertEquals('summary_large_image', $metadata['twitter:card']);
        $this->assertEquals($this->comic->title, $metadata['twitter:title']);
        $this->assertStringContainsString($this->comic->slug, $metadata['twitter:url']);
        $this->assertStringContainsString('storage/covers/test-comic.jpg', $metadata['twitter:image']);
    }

    public function test_generates_structured_data()
    {
        $structuredData = $this->socialMetadataService->generateStructuredData($this->comic);

        $this->assertEquals('https://schema.org', $structuredData['@context']);
        $this->assertEquals('Book', $structuredData['@type']);
        $this->assertEquals($this->comic->title, $structuredData['name']);
        $this->assertEquals($this->comic->author, $structuredData['author']['name']);
        $this->assertEquals($this->comic->publisher, $structuredData['publisher']['name']);
        $this->assertEquals($this->comic->isbn, $structuredData['isbn']);
        $this->assertEquals($this->comic->page_count, $structuredData['numberOfPages']);
        $this->assertEquals($this->comic->average_rating, $structuredData['aggregateRating']['ratingValue']);
        $this->assertEquals($this->comic->total_ratings, $structuredData['aggregateRating']['ratingCount']);
    }

    public function test_generates_sharing_preview()
    {
        $preview = $this->socialMetadataService->generateSharingPreview($this->comic);

        $this->assertEquals($this->comic->title, $preview['title']);
        $this->assertStringContainsString($this->comic->description, $preview['description']);
        $this->assertStringContainsString('storage/covers/test-comic.jpg', $preview['image_url']);
        $this->assertStringContainsString($this->comic->slug, $preview['url']);
        $this->assertEquals('comic', $preview['type']);
    }

    public function test_generates_context_specific_sharing_preview()
    {
        $achievementPreview = $this->socialMetadataService->generateSharingPreview($this->comic, 'achievement');
        $recommendationPreview = $this->socialMetadataService->generateSharingPreview($this->comic, 'recommendation');
        $reviewPreview = $this->socialMetadataService->generateSharingPreview($this->comic, 'review', ['rating' => 5]);

        $this->assertStringContainsString('Achievement: Completed', $achievementPreview['title']);
        $this->assertStringContainsString('Recommended:', $recommendationPreview['title']);
        $this->assertStringContainsString('Review:', $reviewPreview['title']);
        $this->assertStringContainsString('(5/5 stars)', $reviewPreview['title']);
    }

    public function test_generates_meta_tags_html()
    {
        $html = $this->socialMetadataService->generateMetaTagsHtml($this->comic);

        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta name="twitter:card"', $html);
        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString($this->comic->title, $html);
    }

    public function test_generates_hashtags()
    {
        $generalHashtags = $this->socialMetadataService->generateHashtags($this->comic);
        $twitterHashtags = $this->socialMetadataService->generateHashtags($this->comic, 'twitter');
        $instagramHashtags = $this->socialMetadataService->generateHashtags($this->comic, 'instagram');

        $this->assertContains('#comics', $generalHashtags);
        $this->assertContains('#graphicnovels', $generalHashtags);
        $this->assertContains('#action', $generalHashtags);

        // Twitter should have limited hashtags
        $this->assertLessThanOrEqual(3, count($twitterHashtags));

        // Instagram should have more hashtags
        $this->assertContains('#reading', $instagramHashtags);
        $this->assertContains('#bookstagram', $instagramHashtags);
    }

    public function test_validates_metadata()
    {
        $metadata = [
            'og:title' => 'Test Title',
            'og:description' => '<script>alert("xss")</script>Test Description',
            'og:url' => 'https://example.com/test',
            'og:image' => 'invalid-url',
            'empty_field' => '',
            'null_field' => null,
        ];

        $validated = $this->socialMetadataService->validateMetadata($metadata);

        $this->assertEquals('Test Title', $validated['og:title']);
        $this->assertStringNotContainsString('<script>', $validated['og:description']);
        $this->assertEquals('https://example.com/test', $validated['og:url']);
        $this->assertArrayNotHasKey('og:image', $validated); // Invalid URL should be removed
        $this->assertArrayNotHasKey('empty_field', $validated);
        $this->assertArrayNotHasKey('null_field', $validated);
    }

    public function test_truncates_long_descriptions()
    {
        $longDescription = str_repeat('This is a very long description. ', 50);
        $comic = Comic::factory()->create(['description' => $longDescription]);

        $twitterMetadata = $this->socialMetadataService->generateTwitterCardMetadata($comic);
        
        $this->assertLessThanOrEqual(200, strlen($twitterMetadata['twitter:description']));
    }

    public function test_handles_comic_without_image()
    {
        $comic = Comic::factory()->create(['cover_image_path' => null]);

        $metadata = $this->socialMetadataService->generateOpenGraphMetadata($comic);
        $twitterMetadata = $this->socialMetadataService->generateTwitterCardMetadata($comic);

        $this->assertArrayNotHasKey('og:image', $metadata);
        $this->assertArrayNotHasKey('twitter:image', $twitterMetadata);
    }

    public function test_handles_comic_without_description()
    {
        $comic = Comic::factory()->create(['description' => null]);

        $preview = $this->socialMetadataService->generateSharingPreview($comic);

        $this->assertStringContainsString("Discover '{$comic->title}'", $preview['description']);
        $this->assertStringContainsString($comic->author, $preview['description']);
    }

    public function test_includes_series_information_in_structured_data()
    {
        $structuredData = $this->socialMetadataService->generateStructuredData($this->comic);

        $this->assertArrayHasKey('isPartOf', $structuredData);
        $this->assertEquals('BookSeries', $structuredData['isPartOf']['@type']);
        $this->assertEquals('Test Series', $structuredData['isPartOf']['name']);
        $this->assertEquals(1, $structuredData['isPartOf']['position']);
    }

    public function test_includes_pricing_information_for_paid_comics()
    {
        $structuredData = $this->socialMetadataService->generateStructuredData($this->comic);

        $this->assertArrayHasKey('offers', $structuredData);
        $this->assertEquals('Offer', $structuredData['offers']['@type']);
        $this->assertEquals(9.99, $structuredData['offers']['price']);
        $this->assertEquals('USD', $structuredData['offers']['priceCurrency']);
    }

    public function test_excludes_pricing_for_free_comics()
    {
        $freeComic = Comic::factory()->create(['is_free' => true, 'price' => 0]);

        $structuredData = $this->socialMetadataService->generateStructuredData($freeComic);

        $this->assertArrayNotHasKey('offers', $structuredData);
    }
}