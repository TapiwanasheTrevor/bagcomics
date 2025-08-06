<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\SocialShare;
use App\Services\SocialSharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class SocialSharingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SocialSharingService $socialSharingService;
    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->socialSharingService = new SocialSharingService();
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'title' => 'Test Comic',
            'author' => 'Test Author',
            'genre' => 'Action',
            'publisher' => 'Test Publisher',
            'cover_image_path' => 'covers/test-comic.jpg',
        ]);
    }

    public function test_can_share_comic_to_facebook()
    {
        $socialShare = $this->socialSharingService->shareComic(
            $this->user,
            $this->comic,
            'facebook',
            'comic_discovery'
        );

        $this->assertInstanceOf(SocialShare::class, $socialShare);
        $this->assertEquals($this->user->id, $socialShare->user_id);
        $this->assertEquals($this->comic->id, $socialShare->comic_id);
        $this->assertEquals('facebook', $socialShare->platform);
        $this->assertEquals('comic_discovery', $socialShare->share_type);
        $this->assertNotNull($socialShare->metadata);
    }

    public function test_can_share_comic_to_twitter()
    {
        $socialShare = $this->socialSharingService->shareComic(
            $this->user,
            $this->comic,
            'twitter',
            'reading_achievement'
        );

        $this->assertEquals('twitter', $socialShare->platform);
        $this->assertEquals('reading_achievement', $socialShare->share_type);
        $this->assertStringContainsString('twitter.com', $socialShare->getShareUrl());
    }

    public function test_can_share_comic_to_instagram()
    {
        $socialShare = $this->socialSharingService->shareComic(
            $this->user,
            $this->comic,
            'instagram',
            'recommendation'
        );

        $this->assertEquals('instagram', $socialShare->platform);
        $this->assertEquals('recommendation', $socialShare->share_type);
    }

    public function test_throws_exception_for_invalid_platform()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported platform: invalid_platform');

        $this->socialSharingService->shareComic(
            $this->user,
            $this->comic,
            'invalid_platform',
            'comic_discovery'
        );
    }

    public function test_throws_exception_for_invalid_share_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported share type: invalid_type');

        $this->socialSharingService->shareComic(
            $this->user,
            $this->comic,
            'facebook',
            'invalid_type'
        );
    }

    public function test_generates_correct_share_metadata()
    {
        $metadata = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'facebook',
            'comic_discovery'
        );

        $this->assertEquals($this->comic->title, $metadata['title']);
        $this->assertStringContainsString($this->comic->title, $metadata['description']);
        $this->assertStringContainsString('storage/covers/test-comic.jpg', $metadata['image_url']);
        $this->assertStringContainsString($this->comic->slug, $metadata['comic_url']);
        $this->assertArrayHasKey('hashtags', $metadata);
        $this->assertArrayHasKey('og:title', $metadata);
    }

    public function test_generates_platform_specific_share_urls()
    {
        $facebookUrl = $this->socialSharingService->generateShareUrl($this->comic, 'facebook', 'comic_discovery');
        $twitterUrl = $this->socialSharingService->generateShareUrl($this->comic, 'twitter', 'comic_discovery');
        $instagramUrl = $this->socialSharingService->generateShareUrl($this->comic, 'instagram', 'comic_discovery');

        $this->assertStringContainsString('facebook.com/sharer', $facebookUrl);
        $this->assertStringContainsString('twitter.com/intent/tweet', $twitterUrl);
        $this->assertStringContainsString('instagram://camera', $instagramUrl);
    }

    public function test_gets_user_sharing_history()
    {
        // Create some social shares
        SocialShare::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'platform' => 'facebook',
        ]);

        SocialShare::factory()->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'platform' => 'twitter',
        ]);

        $history = $this->socialSharingService->getUserSharingHistory($this->user);
        $this->assertCount(2, $history);

        $facebookHistory = $this->socialSharingService->getUserSharingHistory($this->user, 'facebook');
        $this->assertCount(1, $facebookHistory);
    }

    public function test_gets_comic_sharing_stats()
    {
        // Create some social shares for the comic
        SocialShare::factory()->count(3)->create([
            'comic_id' => $this->comic->id,
            'platform' => 'facebook',
        ]);

        SocialShare::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'platform' => 'twitter',
        ]);

        $stats = $this->socialSharingService->getComicSharingStats($this->comic);

        $this->assertEquals(5, $stats['total_shares']);
        $this->assertEquals(3, $stats['platform_breakdown']['facebook']);
        $this->assertEquals(2, $stats['platform_breakdown']['twitter']);
    }

    public function test_tracks_reading_achievement()
    {
        $achievement = $this->socialSharingService->trackReadingAchievement(
            $this->user,
            $this->comic,
            'completed'
        );

        $this->assertEquals('completed', $achievement['achievement']);
        $this->assertArrayHasKey('sharing_suggestions', $achievement);
        $this->assertArrayHasKey('facebook', $achievement['sharing_suggestions']);
        $this->assertArrayHasKey('twitter', $achievement['sharing_suggestions']);
    }

    public function test_tracks_milestone_achievement()
    {
        $achievement = $this->socialSharingService->trackReadingAchievement(
            $this->user,
            $this->comic,
            'milestone',
            ['page' => 50, 'total_pages' => 100]
        );

        $this->assertEquals('milestone', $achievement['achievement']);
        $this->assertArrayHasKey('page', $achievement['metadata']);
        $this->assertEquals(50, $achievement['metadata']['page']);
    }

    public function test_generates_different_descriptions_for_share_types()
    {
        $discoveryMetadata = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'facebook',
            'comic_discovery'
        );

        $achievementMetadata = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'facebook',
            'reading_achievement'
        );

        $recommendationMetadata = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'facebook',
            'recommendation'
        );

        $this->assertStringContainsString('Check out this amazing comic', $discoveryMetadata['description']);
        $this->assertStringContainsString('I just', $achievementMetadata['description']);
        $this->assertStringContainsString('I highly recommend', $recommendationMetadata['description']);
    }

    public function test_generates_appropriate_hashtags()
    {
        $facebookHashtags = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'facebook',
            'comic_discovery'
        )['hashtags'];

        $twitterHashtags = $this->socialSharingService->generateShareMetadata(
            $this->comic,
            'twitter',
            'comic_discovery'
        )['hashtags'];

        $this->assertContains('#comics', $facebookHashtags);
        $this->assertContains('#graphicnovels', $facebookHashtags);
        $this->assertContains('#action', $facebookHashtags);

        // Twitter should have fewer hashtags
        $this->assertLessThanOrEqual(3, count($twitterHashtags));
    }
}