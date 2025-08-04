# Comic Search Functionality

This document describes the advanced search and filtering functionality implemented for the comic platform.

## Overview

The search system provides comprehensive search capabilities including:
- Full-text search across comic titles, descriptions, and metadata
- Advanced filtering by multiple criteria
- Real-time search suggestions and autocomplete
- Performance-optimized queries
- Support for both Meilisearch and database-based search

## Architecture

### Components

1. **ComicSearchService** - Core service handling search logic
2. **ComicSearchController** - API endpoints for search functionality
3. **Comic Model** - Enhanced with Scout searchable trait
4. **Search Configuration** - Meilisearch index settings and Scout configuration

### Search Engines Supported

- **Meilisearch** (Recommended for production)
- **Database** (Fallback for development/testing)
- **Algolia** (Alternative cloud option)

## API Endpoints

### Search Comics
```
GET /api/comics/search
```

**Parameters:**
- `query` (string, optional) - Text search query
- `filters` (array, optional) - Filter criteria
- `sort` (string, optional) - Sort order
- `per_page` (integer, optional) - Results per page (1-100)
- `page` (integer, optional) - Page number

**Example:**
```bash
curl "https://api.example.com/api/comics/search?query=spider&filters[genre][]=Superhero&sort=rating_desc&per_page=20"
```

### Search Suggestions
```
GET /api/comics/search/suggestions
```

**Parameters:**
- `query` (string, required) - Partial search query
- `limit` (integer, optional) - Maximum suggestions (1-20)

### Autocomplete
```
GET /api/comics/search/autocomplete
```

**Parameters:**
- `query` (string, required) - Partial search query
- `limit` (integer, optional) - Maximum suggestions per category (1-20)

**Response includes:**
- `titles` - Matching comic titles
- `authors` - Matching authors
- `publishers` - Matching publishers
- `series` - Matching series

### Filter Options
```
GET /api/comics/search/filter-options
```

Returns available filter options:
- Available genres
- Authors
- Publishers
- Languages
- Publication year range
- Price range
- Available tags

### Popular Terms
```
GET /api/comics/search/popular-terms
```

Returns popular search terms based on usage and content popularity.

## Filtering Options

### Available Filters

| Filter | Type | Description |
|--------|------|-------------|
| `genre` | array | Filter by comic genres |
| `author` | array | Filter by authors |
| `publisher` | array | Filter by publishers |
| `price_min` | number | Minimum price |
| `price_max` | number | Maximum price |
| `is_free` | boolean | Free comics only |
| `has_mature_content` | boolean | Include/exclude mature content |
| `year_min` | integer | Minimum publication year |
| `year_max` | integer | Maximum publication year |
| `min_rating` | number | Minimum average rating (0-5) |
| `language` | array | Filter by language |
| `tags` | array | Filter by tags |
| `series_id` | integer | Filter by series |
| `is_new_release` | boolean | New releases only (last 30 days) |
| `max_reading_time` | integer | Maximum reading time in minutes |

### Example Filter Usage

```javascript
// Search for superhero comics by Stan Lee, rated 4+ stars, priced under $15
const filters = {
  genre: ['Superhero'],
  author: ['Stan Lee'],
  min_rating: 4.0,
  price_max: 15.00,
  is_free: false
};

fetch('/api/comics/search?' + new URLSearchParams({
  filters: filters,
  sort: 'rating_desc'
}));
```

## Sorting Options

| Sort Option | Description |
|-------------|-------------|
| `relevance` | Default relevance-based sorting |
| `title_asc` | Title A-Z |
| `title_desc` | Title Z-A |
| `author_asc` | Author A-Z |
| `author_desc` | Author Z-A |
| `publication_year_asc` | Oldest first |
| `publication_year_desc` | Newest first |
| `rating_desc` | Highest rated first |
| `rating_asc` | Lowest rated first |
| `popularity_desc` | Most popular first |
| `popularity_asc` | Least popular first |
| `price_asc` | Lowest price first |
| `price_desc` | Highest price first |
| `newest` | Recently published |
| `oldest` | Oldest published |
| `recent_views` | Most recently viewed |

## Search Index Configuration

### Meilisearch Settings

The search index is configured with:

**Searchable Attributes:**
- title
- author
- publisher
- description
- genre
- tags
- series name
- ISBN
- extracted text from PDFs

**Filterable Attributes:**
- genre, author, publisher
- publication_year, price, rating
- is_free, has_mature_content
- language, tags, series_id

**Sortable Attributes:**
- title, author, publication_year
- average_rating, total_readers
- view_count, price, published_at

## Performance Considerations

### Optimization Features

1. **Database Query Optimization**
   - Proper indexing on filterable columns
   - Efficient pagination
   - Query result caching

2. **Search Engine Optimization**
   - Optimized index settings
   - Relevant ranking rules
   - Stop words and synonyms

3. **Response Caching**
   - Filter options caching
   - Popular terms caching
   - Suggestion result caching

### Performance Targets

- Basic search: < 500ms
- Complex filtering: < 800ms
- Suggestions: < 200ms
- Autocomplete: < 250ms
- Filter options: < 400ms

## Setup and Configuration

### 1. Install Dependencies

```bash
composer require laravel/scout meilisearch/meilisearch-php
```

### 2. Configure Environment

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your_master_key
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

### 4. Index Existing Comics

```bash
php artisan comics:index-search --force
```

### 5. Start Meilisearch (Development)

```bash
# Using Docker
docker run -it --rm \
  -p 7700:7700 \
  -v $(pwd)/meili_data:/meili_data \
  getmeili/meilisearch:latest

# Or install locally
curl -L https://install.meilisearch.com | sh
./meilisearch
```

## Testing

### Running Tests

```bash
# Unit tests
php artisan test tests/Unit/ComicSearchServiceTest.php

# Feature tests
php artisan test tests/Feature/ComicSearchTest.php

# Performance tests
php artisan test tests/Feature/ComicSearchPerformanceTest.php
```

### Test Configuration

Tests use the database driver by default to avoid requiring Meilisearch:

```php
// In test setup
config(['scout.driver' => 'database']);
```

## Monitoring and Analytics

### Search Analytics

The system tracks:
- Search query frequency
- Filter usage patterns
- Performance metrics
- Popular search terms

### Admin Analytics Endpoint

```
GET /api/comics/search/analytics
```

Requires authentication and returns:
- Total searches
- Popular queries
- Filter usage statistics
- Search trends over time

## Troubleshooting

### Common Issues

1. **Meilisearch Connection Failed**
   - Ensure Meilisearch is running
   - Check host and port configuration
   - Verify network connectivity

2. **Search Results Empty**
   - Check if comics are indexed
   - Verify comic visibility settings
   - Run reindexing command

3. **Slow Search Performance**
   - Check database indexes
   - Monitor Meilisearch performance
   - Review query complexity

### Debug Commands

```bash
# Check Scout configuration
php artisan scout:status

# Reindex all comics
php artisan comics:index-search --force

# Clear search cache
php artisan cache:clear
```

## Future Enhancements

### Planned Features

1. **Advanced Analytics**
   - Search result click tracking
   - User behavior analysis
   - A/B testing for search relevance

2. **Machine Learning**
   - Personalized search results
   - Improved recommendation algorithms
   - Auto-tagging based on content

3. **Enhanced Filtering**
   - Faceted search interface
   - Dynamic filter suggestions
   - Saved search preferences

4. **Performance Improvements**
   - Search result caching
   - Predictive prefetching
   - Edge caching for global users

## API Response Examples

### Search Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Spider-Man: Amazing Adventures",
      "author": "Stan Lee",
      "genre": "Superhero",
      "average_rating": 4.5,
      "price": 9.99,
      "cover_image_path": "covers/spiderman.jpg"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 95
  },
  "search_info": {
    "query": "spider",
    "filters_applied": true,
    "sort": "relevance"
  }
}
```

### Suggestions Response
```json
{
  "success": true,
  "data": [
    "Spider-Man",
    "Spider-Woman",
    "Spider-Verse"
  ],
  "query": "spider"
}
```

### Autocomplete Response
```json
{
  "success": true,
  "data": {
    "titles": [
      {
        "id": 1,
        "title": "Batman Returns",
        "slug": "batman-returns",
        "cover_image_path": "covers/batman.jpg"
      }
    ],
    "authors": ["Frank Miller"],
    "publishers": ["DC Comics"],
    "series": [
      {
        "id": 1,
        "name": "Batman Series",
        "slug": "batman-series"
      }
    ]
  },
  "query": "bat"
}
```