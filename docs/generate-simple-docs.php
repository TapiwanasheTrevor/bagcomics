<?php

/**
 * Simple API Documentation Generator
 * 
 * This script generates API documentation without requiring YAML extensions
 */

echo "Generating API documentation...\n";

// Create API overview documentation
$overviewContent = "# API Documentation Overview

## Comprehensive Comic Platform API

This API provides complete functionality for a comic book cataloguing, management, sale, and consumption platform.

## Base URLs

- **Development**: `http://localhost:8000/api`
- **Production**: `https://api.comicplatform.com/api`

## Authentication

The API uses Laravel Sanctum for authentication. Include the Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_API_TOKEN
```

## Rate Limiting

- Public routes: 120 requests per minute
- Authenticated routes: 300 requests per minute  
- Admin routes: 200 requests per minute

## Response Format

All API responses follow a consistent JSON format:

```json
{
  \"success\": true,
  \"data\": {...},
  \"timestamp\": \"2025-08-06T12:00:00Z\"
}
```

Error responses:

```json
{
  \"success\": false,
  \"error\": {
    \"code\": \"ERROR_CODE\",
    \"message\": \"Error description\",
    \"timestamp\": \"2025-08-06T12:00:00Z\"
  }
}
```

## API Endpoints

### Comics API

#### GET /api/comics
Retrieve a paginated list of comics with optional filtering and sorting.

**Parameters:**
- `genre` (string, optional): Filter by genre
- `author` (string, optional): Filter by author
- `publisher` (string, optional): Filter by publisher
- `language` (string, optional): Filter by language
- `tags` (string, optional): Comma-separated list of tags
- `is_free` (boolean, optional): Filter by free comics
- `has_mature_content` (boolean, optional): Filter by mature content
- `search` (string, optional): Search in title, author, and description
- `sort_by` (string, optional): Sort field (title, published_at, average_rating, total_readers, page_count)
- `sort_order` (string, optional): Sort order (asc, desc)
- `per_page` (integer, optional): Items per page (1-100, default: 12)
- `page` (integer, optional): Page number (default: 1)

#### GET /api/comics/{id}
Retrieve detailed information about a specific comic.

#### GET /api/comics/featured
Retrieve a list of featured comics based on ratings and popularity.

#### GET /api/comics/new-releases
Retrieve recently published comics.

#### GET /api/comics/genres
Get list of available genres.

#### GET /api/comics/tags
Get list of available tags.

#### POST /api/comics/{comic}/track-view
Track a view for analytics (requires authentication).

### Search API

#### GET /api/search/comics
Advanced search functionality with filters and sorting.

**Parameters:**
- `query` (string, optional): Search query
- `genre` (string, optional): Filter by genre
- `min_rating` (number, optional): Minimum rating filter
- `max_rating` (number, optional): Maximum rating filter
- `min_price` (number, optional): Minimum price filter
- `max_price` (number, optional): Maximum price filter
- `publication_year_from` (integer, optional): Publication year from
- `publication_year_to` (integer, optional): Publication year to

#### GET /api/search/suggestions
Get search suggestions.

#### GET /api/search/autocomplete
Get autocomplete suggestions.

### Reading Progress API (Authenticated)

#### GET /api/comics/{comic}/progress
Get user's reading progress for a specific comic.

#### POST /api/comics/{comic}/progress/update
Update user's reading progress.

**Body:**
```json
{
  \"current_page\": 15,
  \"reading_time_seconds\": 900,
  \"device_type\": \"mobile\"
}
```

#### POST /api/comics/{comic}/progress/bookmarks
Add a bookmark.

#### GET /api/comics/{comic}/progress/bookmarks
Get bookmarks for a comic.

#### DELETE /api/comics/{comic}/progress/bookmarks
Remove a bookmark.

### Reviews API

#### GET /api/reviews/comics/{comic}
Get reviews for a specific comic.

#### POST /api/reviews/comics/{comic}
Submit a review (requires authentication).

**Body:**
```json
{
  \"rating\": 5,
  \"title\": \"Amazing comic!\",
  \"content\": \"This is a fantastic comic book.\",
  \"is_spoiler\": false
}
```

#### PUT /api/reviews/{review}
Update a review (requires authentication).

#### DELETE /api/reviews/{review}
Delete a review (requires authentication).

#### POST /api/reviews/{review}/vote
Vote on review helpfulness (requires authentication).

### Payments API (Authenticated)

#### POST /api/payments/comics/{comic}/intent
Create a payment intent for purchasing a comic.

#### POST /api/payments/process
Process a payment after successful payment intent confirmation.

#### GET /api/payments/history
Get user's payment history.

#### GET /api/payments/{payment}
Get specific payment details.

### Social Sharing API (Authenticated)

#### POST /api/social/comics/{comic}/share
Share a comic on social media.

#### GET /api/social/comics/{comic}/metadata
Get sharing metadata for a comic.

#### GET /api/social/history
Get user's sharing history.

### User Library API (Authenticated)

#### GET /api/library
Get user's comic library.

#### POST /api/library/comics/{comic}/add
Add a comic to user's library.

#### DELETE /api/library/comics/{comic}/remove
Remove a comic from user's library.

#### POST /api/library/comics/{comic}/favorite
Toggle favorite status.

#### POST /api/library/comics/{comic}/rating
Rate a comic in library.

### Analytics API

#### GET /api/analytics/reading-behavior (Authenticated)
Get user's reading behavior analytics.

#### GET /api/admin/analytics/overview (Admin)
Get platform analytics overview.

#### GET /api/admin/analytics/user-engagement (Admin)
Get user engagement metrics.

#### GET /api/admin/analytics/revenue (Admin)
Get revenue analytics.

### Admin API (Admin Authentication Required)

#### GET /api/admin/reviews/pending
Get pending reviews for moderation.

#### POST /api/admin/reviews/{review}/approve
Approve a review.

#### POST /api/admin/reviews/{review}/reject
Reject a review.

#### GET /api/admin/cms/content
Manage CMS content.

#### POST /api/admin/cms/content
Create CMS content.

#### PUT /api/admin/cms/content/{key}
Update CMS content.

## Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | BAD_REQUEST | Invalid request format or parameters |
| 401 | UNAUTHORIZED | Authentication required |
| 403 | FORBIDDEN | Insufficient permissions |
| 404 | NOT_FOUND | Resource not found |
| 422 | VALIDATION_ERROR | Request validation failed |
| 429 | TOO_MANY_REQUESTS | Rate limit exceeded |
| 500 | INTERNAL_SERVER_ERROR | Server error |

## Examples

### Get Comics List
```bash
curl -X GET \"http://localhost:8000/api/comics?genre=Superhero&sort_by=rating&sort_order=desc\"
```

### Search Comics
```bash
curl -X GET \"http://localhost:8000/api/search/comics?query=spider-man&min_rating=4.0\"
```

### Update Reading Progress
```bash
curl -X POST \"http://localhost:8000/api/comics/1/progress/update\" \\
  -H \"Authorization: Bearer YOUR_TOKEN\" \\
  -H \"Content-Type: application/json\" \\
  -d '{
    \"current_page\": 15,
    \"reading_time_seconds\": 900,
    \"device_type\": \"mobile\"
  }'
```

### Submit Review
```bash
curl -X POST \"http://localhost:8000/api/reviews/comics/1\" \\
  -H \"Authorization: Bearer YOUR_TOKEN\" \\
  -H \"Content-Type: application/json\" \\
  -d '{
    \"rating\": 5,
    \"title\": \"Amazing story!\",
    \"content\": \"This comic has an incredible storyline.\",
    \"is_spoiler\": false
  }'
```

## SDK and Tools

### Postman Collection
A Postman collection is available for testing all API endpoints. Import the collection from `docs/postman-collection.json`.

### Rate Limiting Headers
All responses include rate limiting headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Time when rate limit resets

## Support

For API support, contact: support@comicplatform.com
";

file_put_contents(__DIR__ . '/API_DOCUMENTATION.md', $overviewContent);

// Create a simple Postman collection
$postmanCollection = [
    'info' => [
        'name' => 'Comprehensive Comic Platform API',
        'description' => 'Complete API collection for the comic platform',
        'version' => '1.0.0'
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [
            [
                'key' => 'token',
                'value' => '{{api_token}}',
                'type' => 'string'
            ]
        ]
    ],
    'variable' => [
        [
            'key' => 'base_url',
            'value' => 'http://localhost:8000/api',
            'type' => 'string'
        ],
        [
            'key' => 'api_token',
            'value' => 'your_api_token_here',
            'type' => 'string'
        ]
    ],
    'item' => [
        [
            'name' => 'Comics',
            'item' => [
                [
                    'name' => 'Get Comics List',
                    'request' => [
                        'method' => 'GET',
                        'header' => [],
                        'url' => [
                            'raw' => '{{base_url}}/comics',
                            'host' => ['{{base_url}}'],
                            'path' => ['comics']
                        ]
                    ]
                ],
                [
                    'name' => 'Get Comic Details',
                    'request' => [
                        'method' => 'GET',
                        'header' => [],
                        'url' => [
                            'raw' => '{{base_url}}/comics/1',
                            'host' => ['{{base_url}}'],
                            'path' => ['comics', '1']
                        ]
                    ]
                ],
                [
                    'name' => 'Get Featured Comics',
                    'request' => [
                        'method' => 'GET',
                        'header' => [],
                        'url' => [
                            'raw' => '{{base_url}}/comics/featured',
                            'host' => ['{{base_url}}'],
                            'path' => ['comics', 'featured']
                        ]
                    ]
                ]
            ]
        ],
        [
            'name' => 'Search',
            'item' => [
                [
                    'name' => 'Search Comics',
                    'request' => [
                        'method' => 'GET',
                        'header' => [],
                        'url' => [
                            'raw' => '{{base_url}}/search/comics?query=spider-man',
                            'host' => ['{{base_url}}'],
                            'path' => ['search', 'comics'],
                            'query' => [
                                [
                                    'key' => 'query',
                                    'value' => 'spider-man'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        [
            'name' => 'Reading Progress',
            'item' => [
                [
                    'name' => 'Update Reading Progress',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'current_page' => 15,
                                'reading_time_seconds' => 900,
                                'device_type' => 'mobile'
                            ], JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => '{{base_url}}/comics/1/progress/update',
                            'host' => ['{{base_url}}'],
                            'path' => ['comics', '1', 'progress', 'update']
                        ]
                    ]
                ]
            ]
        ],
        [
            'name' => 'Reviews',
            'item' => [
                [
                    'name' => 'Submit Review',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'rating' => 5,
                                'title' => 'Amazing comic!',
                                'content' => 'This is a fantastic comic book.',
                                'is_spoiler' => false
                            ], JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => '{{base_url}}/reviews/comics/1',
                            'host' => ['{{base_url}}'],
                            'path' => ['reviews', 'comics', '1']
                        ]
                    ]
                ]
            ]
        ]
    ]
];

file_put_contents(__DIR__ . '/postman-collection.json', json_encode($postmanCollection, JSON_PRETTY_PRINT));

echo "API documentation generated successfully!\n";
echo "Files created:\n";
echo "- API_DOCUMENTATION.md\n";
echo "- postman-collection.json\n";