<?php

namespace Tests\Feature\Api;

use App\Models\Comic;
use App\Models\ComicView;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserComicProgress;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create();
        
        // Mock the admin permission check
        $this->adminUser->shouldReceive('can')
            ->with('access-admin')
            ->andReturn(true);
    }

    public function test_reading_behavior_requires_authentication(): void
    {
        $response = $this->getJson('/api/analytics/reading-behavior');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED'
                ]
            ]);
    }

    public function test_user_can_access_own_reading_behavior(): void
    {
        Sanctum::actingAs($this->user);

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'total_reading_time' => 3600,
            'comics_read' => 5,
            'average_session_length' => 720,
            'reading_streak' => 7
        ];

        $mockAnalyticsService->shouldReceive('getReadingBehavior')
            ->once()
            ->with('30d', $this->user->id)
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/analytics/reading-behavior?user_id=' . $this->user->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_user_cannot_access_other_users_reading_behavior(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();

        $response = $this->getJson('/api/analytics/reading-behavior?user_id=' . $otherUser->id);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED'
                ]
            ]);
    }

    public function test_admin_analytics_require_admin_permission(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/analytics/overview');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_platform_overview(): void
    {
        // Mock admin user with proper permissions
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        // Mock the Gate check
        $this->app['auth']->shouldUse('sanctum');
        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'total_users' => 1000,
            'active_users' => 250,
            'total_comics' => 500,
            'total_revenue' => 15000.00
        ];

        $mockAnalyticsService->shouldReceive('getPlatformOverview')
            ->once()
            ->with('30d', 'UTC')
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_user_engagement_metrics(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'daily_active_users' => [
                ['date' => '2025-08-01', 'count' => 150],
                ['date' => '2025-08-02', 'count' => 175],
            ],
            'average_session_length' => 1200,
            'bounce_rate' => 0.25
        ];

        $mockAnalyticsService->shouldReceive('getUserEngagement')
            ->once()
            ->with('7d', 'active_users')
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/user-engagement?period=7d&metric=active_users');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_content_performance(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'top_comics' => [
                [
                    'id' => 1,
                    'title' => 'Amazing Spider-Man #1',
                    'views' => 5000,
                    'readers' => 1200,
                    'rating' => 4.5
                ]
            ],
            'trending_genres' => ['superhero', 'sci-fi', 'fantasy']
        ];

        $mockAnalyticsService->shouldReceive('getContentPerformance')
            ->once()
            ->with('30d', 'views', 20)
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/content-performance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_revenue_analytics(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'total_revenue' => 25000.00,
            'revenue_by_day' => [
                ['date' => '2025-08-01', 'revenue' => 500.00],
                ['date' => '2025-08-02', 'revenue' => 750.00],
            ],
            'average_order_value' => 8.99,
            'conversion_rate' => 0.15
        ];

        $mockAnalyticsService->shouldReceive('getRevenueAnalytics')
            ->once()
            ->with('30d', 'daily')
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/revenue');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_genre_popularity(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'genre_stats' => [
                ['genre' => 'Superhero', 'views' => 15000, 'percentage' => 35.5],
                ['genre' => 'Sci-Fi', 'views' => 8000, 'percentage' => 18.9],
                ['genre' => 'Fantasy', 'views' => 6000, 'percentage' => 14.2]
            ]
        ];

        $mockAnalyticsService->shouldReceive('getGenrePopularity')
            ->once()
            ->with('30d', 'views')
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/genre-popularity');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_search_analytics(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'top_search_terms' => [
                ['term' => 'spider-man', 'count' => 500],
                ['term' => 'batman', 'count' => 350],
                ['term' => 'x-men', 'count' => 200]
            ],
            'search_conversion_rate' => 0.25,
            'zero_result_searches' => 45
        ];

        $mockAnalyticsService->shouldReceive('getSearchAnalytics')
            ->once()
            ->with('30d', 50)
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/search-analytics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_user_retention(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'cohort_data' => [
                [
                    'cohort' => '2025-W01',
                    'users' => 100,
                    'retention' => [
                        'week_1' => 0.85,
                        'week_2' => 0.65,
                        'week_4' => 0.45
                    ]
                ]
            ]
        ];

        $mockAnalyticsService->shouldReceive('getUserRetention')
            ->once()
            ->with('weekly', 12)
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/user-retention');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_access_conversion_funnel(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockData = [
            'funnel_steps' => [
                ['step' => 'View Comic', 'users' => 1000, 'conversion_rate' => 1.0],
                ['step' => 'Add to Cart', 'users' => 300, 'conversion_rate' => 0.3],
                ['step' => 'Complete Purchase', 'users' => 150, 'conversion_rate' => 0.15]
            ]
        ];

        $mockAnalyticsService->shouldReceive('getConversionFunnel')
            ->once()
            ->with('30d', 'purchase')
            ->andReturn($mockData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->getJson('/api/admin/analytics/conversion-funnel');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockData
            ]);
    }

    public function test_admin_can_export_analytics_data(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        $mockAnalyticsService = Mockery::mock(AnalyticsService::class);
        $mockExportData = [
            'url' => 'https://example.com/exports/analytics-2025-08-06.csv',
            'filename' => 'analytics-2025-08-06.csv',
            'expires_at' => now()->addHours(24)->toISOString()
        ];

        $mockAnalyticsService->shouldReceive('exportAnalytics')
            ->once()
            ->with('overview', '30d', 'csv')
            ->andReturn($mockExportData);

        $this->app->instance(AnalyticsService::class, $mockAnalyticsService);

        $response = $this->postJson('/api/admin/analytics/export', [
            'type' => 'overview',
            'period' => '30d',
            'format' => 'csv'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $mockExportData
            ]);
    }

    public function test_analytics_endpoints_validate_parameters(): void
    {
        $adminUser = User::factory()->create();
        Sanctum::actingAs($adminUser);

        $this->app['gate']->define('access-admin', function ($user) use ($adminUser) {
            return $user->id === $adminUser->id;
        });

        // Test invalid period
        $response = $this->getJson('/api/admin/analytics/overview?period=invalid');
        $response->assertStatus(422);

        // Test invalid metric
        $response = $this->getJson('/api/admin/analytics/user-engagement?metric=invalid');
        $response->assertStatus(422);

        // Test invalid export type
        $response = $this->postJson('/api/admin/analytics/export', [
            'type' => 'invalid'
        ]);
        $response->assertStatus(422);
    }

    public function test_analytics_api_rate_limiting_is_applied(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/analytics/reading-behavior');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}