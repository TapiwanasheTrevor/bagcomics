<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive API Test Suite
 * 
 * This class provides utilities and configurations for testing all API endpoints
 * in the comprehensive comic platform.
 */
class ApiTestSuite extends TestCase
{
    use RefreshDatabase;

    /**
     * Common API test assertions
     */
    protected function assertApiResponse($response, int $expectedStatus = 200): void
    {
        $response->assertStatus($expectedStatus);
        
        if ($expectedStatus >= 200 && $expectedStatus < 300) {
            $response->assertJsonStructure([
                'success',
                'data',
                'timestamp'
            ]);
            $this->assertTrue($response->json('success'));
        } else {
            $response->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'timestamp'
                ]
            ]);
            $this->assertFalse($response->json('success'));
        }
    }

    /**
     * Assert pagination structure
     */
    protected function assertPaginationStructure($response): void
    {
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total'
            ],
            'timestamp'
        ]);
    }

    /**
     * Assert rate limiting headers are present
     */
    protected function assertRateLimitHeaders($response): void
    {
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    /**
     * Assert authentication required response
     */
    protected function assertAuthenticationRequired($response): void
    {
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED'
                ]
            ]);
    }

    /**
     * Assert validation error response
     */
    protected function assertValidationError($response, array $expectedFields = []): void
    {
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR'
                ]
            ]);

        if (!empty($expectedFields)) {
            $response->assertJsonValidationErrors($expectedFields);
        }
    }

    /**
     * Assert access denied response
     */
    protected function assertAccessDenied($response): void
    {
        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message'
                ]
            ]);
    }

    /**
     * Assert not found response
     */
    protected function assertNotFound($response): void
    {
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND'
                ]
            ]);
    }

    /**
     * Get all API endpoints for testing
     */
    public static function getAllApiEndpoints(): array
    {
        return [
            // Public endpoints
            'GET /api/comics' => ['method' => 'GET', 'uri' => '/api/comics', 'auth' => false],
            'GET /api/comics/{id}' => ['method' => 'GET', 'uri' => '/api/comics/1', 'auth' => false],
            'GET /api/comics/featured' => ['method' => 'GET', 'uri' => '/api/comics/featured', 'auth' => false],
            'GET /api/comics/new-releases' => ['method' => 'GET', 'uri' => '/api/comics/new-releases', 'auth' => false],
            'GET /api/comics/genres' => ['method' => 'GET', 'uri' => '/api/comics/genres', 'auth' => false],
            'GET /api/comics/tags' => ['method' => 'GET', 'uri' => '/api/comics/tags', 'auth' => false],
            'POST /api/comics/{id}/track-view' => ['method' => 'POST', 'uri' => '/api/comics/1/track-view', 'auth' => false],
            
            // Search endpoints
            'GET /api/search/comics' => ['method' => 'GET', 'uri' => '/api/search/comics', 'auth' => false],
            'GET /api/search/suggestions' => ['method' => 'GET', 'uri' => '/api/search/suggestions', 'auth' => false],
            'GET /api/search/autocomplete' => ['method' => 'GET', 'uri' => '/api/search/autocomplete', 'auth' => false],
            'GET /api/search/filter-options' => ['method' => 'GET', 'uri' => '/api/search/filter-options', 'auth' => false],
            'GET /api/search/popular-terms' => ['method' => 'GET', 'uri' => '/api/search/popular-terms', 'auth' => false],
            
            // Authenticated user endpoints
            'GET /api/comics/{id}/progress' => ['method' => 'GET', 'uri' => '/api/comics/1/progress', 'auth' => true],
            'POST /api/comics/{id}/progress/update' => ['method' => 'POST', 'uri' => '/api/comics/1/progress/update', 'auth' => true],
            'POST /api/comics/{id}/bookmarks' => ['method' => 'POST', 'uri' => '/api/comics/1/bookmarks', 'auth' => true],
            'GET /api/comics/{id}/bookmarks' => ['method' => 'GET', 'uri' => '/api/comics/1/bookmarks', 'auth' => true],
            
            // Reviews endpoints
            'GET /api/reviews/comics/{id}' => ['method' => 'GET', 'uri' => '/api/reviews/comics/1', 'auth' => false],
            'POST /api/reviews/comics/{id}' => ['method' => 'POST', 'uri' => '/api/reviews/comics/1', 'auth' => true],
            'PUT /api/reviews/{id}' => ['method' => 'PUT', 'uri' => '/api/reviews/1', 'auth' => true],
            'DELETE /api/reviews/{id}' => ['method' => 'DELETE', 'uri' => '/api/reviews/1', 'auth' => true],
            'POST /api/reviews/{id}/vote' => ['method' => 'POST', 'uri' => '/api/reviews/1/vote', 'auth' => true],
            
            // Payment endpoints
            'POST /api/payments/comics/{id}/intent' => ['method' => 'POST', 'uri' => '/api/payments/comics/1/intent', 'auth' => true],
            'POST /api/payments/process' => ['method' => 'POST', 'uri' => '/api/payments/process', 'auth' => true],
            'GET /api/payments/history' => ['method' => 'GET', 'uri' => '/api/payments/history', 'auth' => true],
            'GET /api/payments/{id}' => ['method' => 'GET', 'uri' => '/api/payments/1', 'auth' => true],
            'POST /api/payments/{id}/refund' => ['method' => 'POST', 'uri' => '/api/payments/1/refund', 'auth' => true],
            'GET /api/payments/statistics/user' => ['method' => 'GET', 'uri' => '/api/payments/statistics/user', 'auth' => true],
            
            // Social endpoints
            'POST /api/social/comics/{id}/share' => ['method' => 'POST', 'uri' => '/api/social/comics/1/share', 'auth' => true],
            'GET /api/social/comics/{id}/metadata' => ['method' => 'GET', 'uri' => '/api/social/comics/1/metadata', 'auth' => true],
            'GET /api/social/history' => ['method' => 'GET', 'uri' => '/api/social/history', 'auth' => true],
            
            // Library endpoints
            'GET /api/library' => ['method' => 'GET', 'uri' => '/api/library', 'auth' => true],
            'GET /api/library/statistics' => ['method' => 'GET', 'uri' => '/api/library/statistics', 'auth' => true],
            'POST /api/library/comics/{id}/add' => ['method' => 'POST', 'uri' => '/api/library/comics/1/add', 'auth' => true],
            'DELETE /api/library/comics/{id}/remove' => ['method' => 'DELETE', 'uri' => '/api/library/comics/1/remove', 'auth' => true],
            'POST /api/library/comics/{id}/favorite' => ['method' => 'POST', 'uri' => '/api/library/comics/1/favorite', 'auth' => true],
            
            // Analytics endpoints (user)
            'GET /api/analytics/reading-behavior' => ['method' => 'GET', 'uri' => '/api/analytics/reading-behavior', 'auth' => true],
            
            // Admin endpoints
            'GET /api/admin/analytics/overview' => ['method' => 'GET', 'uri' => '/api/admin/analytics/overview', 'auth' => true, 'admin' => true],
            'GET /api/admin/analytics/revenue' => ['method' => 'GET', 'uri' => '/api/admin/analytics/revenue', 'auth' => true, 'admin' => true],
            'GET /api/admin/analytics/content-performance' => ['method' => 'GET', 'uri' => '/api/admin/analytics/content-performance', 'auth' => true, 'admin' => true],
            'POST /api/admin/analytics/export' => ['method' => 'POST', 'uri' => '/api/admin/analytics/export', 'auth' => true, 'admin' => true],
            
            // CMS Admin endpoints
            'GET /api/admin/cms/content' => ['method' => 'GET', 'uri' => '/api/admin/cms/content', 'auth' => true, 'admin' => true],
            'POST /api/admin/cms/content' => ['method' => 'POST', 'uri' => '/api/admin/cms/content', 'auth' => true, 'admin' => true],
            'GET /api/admin/cms/media' => ['method' => 'GET', 'uri' => '/api/admin/cms/media', 'auth' => true, 'admin' => true],
            'POST /api/admin/cms/media' => ['method' => 'POST', 'uri' => '/api/admin/cms/media', 'auth' => true, 'admin' => true],
            
            // Review moderation endpoints
            'GET /api/admin/reviews/pending' => ['method' => 'GET', 'uri' => '/api/admin/reviews/pending', 'auth' => true, 'admin' => true],
            'POST /api/admin/reviews/{id}/approve' => ['method' => 'POST', 'uri' => '/api/admin/reviews/1/approve', 'auth' => true, 'admin' => true],
            'POST /api/admin/reviews/{id}/reject' => ['method' => 'POST', 'uri' => '/api/admin/reviews/1/reject', 'auth' => true, 'admin' => true],
        ];
    }

    /**
     * Test data providers for common scenarios
     */
    public static function validationErrorProvider(): array
    {
        return [
            'empty_request' => [[]],
            'invalid_types' => [['rating' => 'invalid', 'page' => 'not_number']],
            'out_of_range' => [['rating' => 6, 'page' => -1]],
            'missing_required' => [['optional_field' => 'value']],
        ];
    }

    public static function paginationProvider(): array
    {
        return [
            'default_pagination' => [[]],
            'custom_per_page' => [['per_page' => 5]],
            'specific_page' => [['page' => 2]],
            'max_per_page' => [['per_page' => 100]],
            'invalid_per_page' => [['per_page' => 101]], // Should be capped
        ];
    }

    public static function sortingProvider(): array
    {
        return [
            'sort_by_title_asc' => [['sort_by' => 'title', 'sort_order' => 'asc']],
            'sort_by_title_desc' => [['sort_by' => 'title', 'sort_order' => 'desc']],
            'sort_by_rating_desc' => [['sort_by' => 'average_rating', 'sort_order' => 'desc']],
            'sort_by_date_desc' => [['sort_by' => 'published_at', 'sort_order' => 'desc']],
            'invalid_sort_field' => [['sort_by' => 'invalid_field']], // Should use default
        ];
    }

    public static function filterProvider(): array
    {
        return [
            'genre_filter' => [['genre' => 'Superhero']],
            'author_filter' => [['author' => 'Stan Lee']],
            'free_comics' => [['is_free' => true]],
            'mature_content' => [['has_mature_content' => false]],
            'rating_range' => [['min_rating' => 4.0, 'max_rating' => 5.0]],
            'price_range' => [['min_price' => 0, 'max_price' => 10.00]],
            'publication_year' => [['publication_year_from' => 2020, 'publication_year_to' => 2025]],
            'multiple_filters' => [['genre' => 'Superhero', 'is_free' => false, 'min_rating' => 4.0]],
        ];
    }
}