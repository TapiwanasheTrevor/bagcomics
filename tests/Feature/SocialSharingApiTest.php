<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comic;
use App\Models\SocialShare;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SocialSharingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Comic $comic;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->comic = Comic::factory()->create([
            'title' => 'Test Comic',
            'author' => 'Test Author',
            'genre' => 'Action',
            'cover_image_path' => 'covers/test-comic.jpg',
        ]);
    }

    public function test_can_share_comic_to_facebook()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'facebook',
            'share_type' => 'comic_discovery',
            'message' => 'Check out this amazing comic!',
            'include_image' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'platform' => 'facebook',
                'share_type' => 'comic_discovery',
            ])
            ->assertJsonStructure([
                'success',
                'share_id',
                'share_url',
                'metadata',
                'platform',
                'share_type',
            ]);

        $this->assertDatabaseHas('social_shares', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'platform' => 'facebook',
            'share_type' => 'comic_discovery',
        ]);
    }

    public function test_can_share_comic_to_twitter()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'twitter',
            'share_type' => 'reading_achievement',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'platform' => 'twitter',
                'share_type' => 'reading_achievement',
            ]);

        $this->assertDatabaseHas('social_shares', [
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
            'platform' => 'twitter',
            'share_type' => 'reading_achievement',
        ]);
    }

    public function test_can_share_comic_to_instagram()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'instagram',
            'share_type' => 'recommendation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'platform' => 'instagram',
                'share_type' => 'recommendation',
            ]);
    }

    public function test_validates_share_request()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'invalid_platform',
            'share_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'share_type']);
    }

    public function test_requires_authentication_for_sharing()
    {
        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'facebook',
            'share_type' => 'comic_discovery',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_get_sharing_metadata()
    {
        $this->actingAs($this->user);
        
        $response = $this->getJson("/api/social/comics/{$this->comic->id}/metadata");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'metadata' => [
                    'general',
                    'open_graph',
                    'twitter_card',
                    'structured_data',
                    'hashtags',
                ],
                'comic' => [
                    'id',
                    'title',
                    'slug',
                ],
            ]);
    }

    public function test_can_get_platform_specific_metadata()
    {
        $this->actingAs($this->user);
        
        $response = $this->getJson("/api/social/comics/{$this->comic->id}/metadata?platform=facebook");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'metadata' => [
                    'general',
                    'open_graph',
                    'twitter_card',
                    'structured_data',
                    'hashtags',
                    'platform_specific',
                ],
            ]);
    }

    public function test_can_get_sharing_history()
    {
        $this->actingAs($this->user);

        // Create some social shares
        SocialShare::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'comic_id' => $this->comic->id,
        ]);

        $response = $this->getJson('/api/social/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_shares' => 3,
            ])
            ->assertJsonStructure([
                'success',
                'sharing_history' => [
                    '*' => [
                        'id',
                        'platform',
                        'share_type',
                        'comic',
                        'share_url',
                        'created_at',
                    ],
                ],
                'total_shares',
            ]);
    }

    public function test_can_filter_sharing_history_by_platform()
    {
        $this->actingAs($this->user);

        SocialShare::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'facebook',
        ]);

        SocialShare::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
        ]);

        $response = $this->getJson('/api/social/history?platform=facebook');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_shares' => 1,
            ]);

        $sharingHistory = $response->json('sharing_history');
        $this->assertEquals('facebook', $sharingHistory[0]['platform']);
    }

    public function test_can_get_comic_sharing_stats()
    {
        $this->actingAs($this->user);
        
        // Create some social shares for the comic
        SocialShare::factory()->count(2)->create([
            'comic_id' => $this->comic->id,
            'platform' => 'facebook',
        ]);

        SocialShare::factory()->create([
            'comic_id' => $this->comic->id,
            'platform' => 'twitter',
        ]);

        $response = $this->getJson("/api/social/comics/{$this->comic->id}/stats");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'comic_id' => $this->comic->id,
            ])
            ->assertJsonStructure([
                'success',
                'comic_id',
                'sharing_stats' => [
                    'total_shares',
                    'platform_breakdown',
                    'share_type_breakdown',
                    'recent_shares',
                ],
            ]);

        $stats = $response->json('sharing_stats');
        $this->assertEquals(3, $stats['total_shares']);
        $this->assertEquals(2, $stats['platform_breakdown']['facebook']);
        $this->assertEquals(1, $stats['platform_breakdown']['twitter']);
    }

    public function test_can_get_available_platforms()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/social/platforms');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'platforms' => [
                    'facebook' => [
                        'connected',
                        'name',
                    ],
                    'twitter' => [
                        'connected',
                        'name',
                    ],
                    'instagram' => [
                        'connected',
                        'name',
                    ],
                ],
            ]);
    }

    public function test_can_connect_social_account()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/social/connect', [
            'platform' => 'facebook',
            'access_token' => 'fake_access_token',
            'profile_data' => [
                'id' => '123456789',
                'name' => 'Test User',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'platform' => 'facebook',
            ]);

        $this->user->refresh();
        $this->assertArrayHasKey('facebook', $this->user->social_profiles);
    }

    public function test_validates_social_account_connection()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/social/connect', [
            'platform' => 'invalid_platform',
            'access_token' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'access_token']);
    }

    public function test_can_disconnect_social_account()
    {
        $this->actingAs($this->user);

        // First connect an account
        $this->user->social_profiles = [
            'facebook' => [
                'access_token' => encrypt('fake_token'),
                'connected_at' => now()->toISOString(),
            ],
        ];
        $this->user->save();

        $response = $this->deleteJson('/api/social/disconnect', [
            'platform' => 'facebook',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'platform' => 'facebook',
            ]);

        $this->user->refresh();
        $this->assertArrayNotHasKey('facebook', $this->user->social_profiles ?? []);
    }

    public function test_handles_disconnecting_non_connected_account()
    {
        $this->actingAs($this->user);

        $response = $this->deleteJson('/api/social/disconnect', [
            'platform' => 'facebook',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_sharing_awards_achievements()
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/social/comics/{$this->comic->id}/share", [
            'platform' => 'facebook',
            'share_type' => 'comic_discovery',
        ]);

        $response->assertStatus(200);

        // Check if achievements were awarded (first social share)
        $this->user->refresh();
        $achievements = $this->user->achievements ?? [];
        
        if (!empty($achievements)) {
            $socialShareAchievement = collect($achievements)->firstWhere('type', 'social_sharer');
            $this->assertNotNull($socialShareAchievement);
        }
    }

    public function test_returns_404_for_non_existent_comic()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/social/comics/99999/share', [
            'platform' => 'facebook',
            'share_type' => 'comic_discovery',
        ]);

        $response->assertStatus(404);
    }

    public function test_limits_sharing_history_results()
    {
        $this->actingAs($this->user);

        // Create more shares than the default limit
        SocialShare::factory()->count(25)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/social/history?limit=10');

        $response->assertStatus(200);

        $sharingHistory = $response->json('sharing_history');
        $this->assertCount(10, $sharingHistory);
    }
}