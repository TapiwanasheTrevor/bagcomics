<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Support\Str;

class SocialMetadataService
{
    /**
     * Generate Open Graph metadata for a comic
     */
    public function generateOpenGraphMetadata(Comic $comic, array $options = []): array
    {
        $metadata = [
            'og:title' => $comic->title,
            'og:description' => $this->generateDescription($comic, $options),
            'og:type' => 'article',
            'og:url' => route('comics.show', $comic->slug),
            'og:site_name' => config('app.name'),
            'og:locale' => 'en_US',
        ];

        // Add image metadata
        if ($comic->cover_image_path) {
            $imageUrl = asset('storage/' . $comic->cover_image_path);
            $metadata['og:image'] = $imageUrl;
            $metadata['og:image:alt'] = "Cover of {$comic->title}";
            $metadata['og:image:type'] = 'image/jpeg';
            $metadata['og:image:width'] = '1200';
            $metadata['og:image:height'] = '630';
        }

        // Add article-specific metadata
        $metadata['article:author'] = $comic->author;
        $metadata['article:published_time'] = $comic->published_at?->toISOString();
        $metadata['article:section'] = $comic->genre;
        
        // Add tags
        if ($comic->tags) {
            foreach ($comic->tags as $tag) {
                $metadata['article:tag'][] = $tag;
            }
        }

        // Add Facebook-specific metadata
        if (config('services.facebook.app_id')) {
            $metadata['fb:app_id'] = config('services.facebook.app_id');
        }

        return $metadata;
    }

    /**
     * Generate Twitter Card metadata for a comic
     */
    public function generateTwitterCardMetadata(Comic $comic, array $options = []): array
    {
        $metadata = [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $comic->title,
            'twitter:description' => $this->generateDescription($comic, $options, 200), // Twitter description limit
            'twitter:url' => route('comics.show', $comic->slug),
        ];

        // Add image metadata
        if ($comic->cover_image_path) {
            $metadata['twitter:image'] = asset('storage/' . $comic->cover_image_path);
            $metadata['twitter:image:alt'] = "Cover of {$comic->title}";
        }

        // Add site handle if configured
        if (config('services.twitter.site_handle')) {
            $metadata['twitter:site'] = config('services.twitter.site_handle');
        }

        // Add creator handle if available
        if (isset($options['creator_handle'])) {
            $metadata['twitter:creator'] = $options['creator_handle'];
        }

        return $metadata;
    }

    /**
     * Generate structured data (JSON-LD) for a comic
     */
    public function generateStructuredData(Comic $comic): array
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => $comic->title,
            'author' => [
                '@type' => 'Person',
                'name' => $comic->author,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $comic->publisher,
            ],
            'description' => $this->generateDescription($comic),
            'genre' => $comic->genre,
            'url' => route('comics.show', $comic->slug),
            'datePublished' => $comic->published_at?->toDateString(),
            'inLanguage' => $comic->language ?? 'en',
        ];

        // Add image
        if ($comic->cover_image_path) {
            $structuredData['image'] = asset('storage/' . $comic->cover_image_path);
        }

        // Add ISBN if available
        if ($comic->isbn) {
            $structuredData['isbn'] = $comic->isbn;
        }

        // Add page count
        if ($comic->page_count) {
            $structuredData['numberOfPages'] = $comic->page_count;
        }

        // Add rating information
        if ($comic->average_rating && $comic->total_ratings > 0) {
            $structuredData['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $comic->average_rating,
                'ratingCount' => $comic->total_ratings,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // Add series information
        if ($comic->series) {
            $structuredData['isPartOf'] = [
                '@type' => 'BookSeries',
                'name' => $comic->series->name,
                'position' => $comic->issue_number,
            ];
        }

        // Add offers (pricing information)
        if (!$comic->is_free && $comic->price) {
            $structuredData['offers'] = [
                '@type' => 'Offer',
                'price' => $comic->price,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => route('comics.show', $comic->slug),
            ];
        }

        return $structuredData;
    }

    /**
     * Generate sharing preview metadata for different contexts
     */
    public function generateSharingPreview(Comic $comic, string $context = 'general', array $options = []): array
    {
        $preview = [
            'title' => $comic->title,
            'description' => $this->generateDescription($comic, $options),
            'image_url' => $comic->cover_image_path ? asset('storage/' . $comic->cover_image_path) : null,
            'url' => route('comics.show', $comic->slug),
            'type' => 'comic',
        ];

        // Context-specific modifications
        switch ($context) {
            case 'achievement':
                $preview['title'] = "Achievement: Completed {$comic->title}";
                $preview['description'] = "I just finished reading this amazing comic!";
                break;
            
            case 'recommendation':
                $preview['title'] = "Recommended: {$comic->title}";
                $preview['description'] = "You should definitely check out this comic!";
                break;
            
            case 'review':
                $rating = $options['rating'] ?? null;
                $preview['title'] = "Review: {$comic->title}" . ($rating ? " ({$rating}/5 stars)" : '');
                $preview['description'] = "Here's my review of this comic...";
                break;
        }

        return $preview;
    }

    /**
     * Generate meta tags HTML for a comic page
     */
    public function generateMetaTagsHtml(Comic $comic, array $options = []): string
    {
        $ogMetadata = $this->generateOpenGraphMetadata($comic, $options);
        $twitterMetadata = $this->generateTwitterCardMetadata($comic, $options);
        $structuredData = $this->generateStructuredData($comic);

        $html = '';

        // Open Graph tags
        foreach ($ogMetadata as $property => $content) {
            if (is_array($content)) {
                foreach ($content as $item) {
                    $html .= "<meta property=\"{$property}\" content=\"" . htmlspecialchars($item) . "\">\n";
                }
            } else {
                $html .= "<meta property=\"{$property}\" content=\"" . htmlspecialchars($content) . "\">\n";
            }
        }

        // Twitter Card tags
        foreach ($twitterMetadata as $name => $content) {
            $html .= "<meta name=\"{$name}\" content=\"" . htmlspecialchars($content) . "\">\n";
        }

        // Structured data
        $html .= "<script type=\"application/ld+json\">\n";
        $html .= json_encode($structuredData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $html .= "\n</script>\n";

        return $html;
    }

    /**
     * Generate description for social sharing
     */
    private function generateDescription(Comic $comic, array $options = [], int $maxLength = 300): string
    {
        $description = $comic->description;
        
        // If no description, generate one
        if (!$description) {
            $description = "Discover '{$comic->title}' by {$comic->author}";
            
            if ($comic->genre) {
                $description .= " - a {$comic->genre} comic";
            }
            
            if ($comic->publisher) {
                $description .= " published by {$comic->publisher}";
            }
            
            $description .= ".";
        }

        // Add context-specific information
        if (isset($options['context'])) {
            switch ($options['context']) {
                case 'achievement':
                    $description = "Just completed this amazing comic! " . $description;
                    break;
                case 'recommendation':
                    $description = "Highly recommended: " . $description;
                    break;
            }
        }

        // Truncate if necessary
        if (strlen($description) > $maxLength) {
            $description = Str::limit($description, $maxLength - 3);
        }

        return $description;
    }

    /**
     * Generate hashtags for social sharing
     */
    public function generateHashtags(Comic $comic, string $platform = 'general'): array
    {
        $hashtags = ['#comics', '#graphicnovels'];
        
        // Add genre-based hashtags
        if ($comic->genre) {
            $hashtags[] = '#' . Str::camel(strtolower($comic->genre));
        }
        
        // Add publisher hashtag
        if ($comic->publisher) {
            $hashtags[] = '#' . Str::camel(strtolower(str_replace(' ', '', $comic->publisher)));
        }
        
        // Add custom tags
        if ($comic->tags) {
            foreach (array_slice($comic->tags, 0, 2) as $tag) {
                $hashtags[] = '#' . Str::camel(strtolower(str_replace(' ', '', $tag)));
            }
        }
        
        // Platform-specific limits and modifications
        switch ($platform) {
            case 'twitter':
                $hashtags = array_slice($hashtags, 0, 3); // Twitter best practice
                break;
            case 'instagram':
                // Instagram allows more hashtags
                $hashtags[] = '#reading';
                $hashtags[] = '#bookstagram';
                break;
        }
        
        return array_unique($hashtags);
    }

    /**
     * Validate and sanitize metadata
     */
    public function validateMetadata(array $metadata): array
    {
        $validated = [];
        
        foreach ($metadata as $key => $value) {
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // Sanitize strings
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            
            // Validate URLs
            if (in_array($key, ['og:url', 'og:image', 'twitter:image']) && !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            $validated[$key] = $value;
        }
        
        return $validated;
    }
}