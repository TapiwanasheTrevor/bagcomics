<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Searchable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Comic extends Model
{
    use HasFactory, HasSlug;
    
    protected $fillable = [
        'title',
        'slug',
        'author',
        'genre',
        'tags',
        'description',
        'page_count',
        'language',
        'average_rating',
        'total_ratings',
        'total_readers',
        'view_count',
        'isbn',
        'publication_year',
        'publisher',
        'series_id',
        'issue_number',
        'pdf_file_path',
        'pdf_file_name',
        'pdf_file_size',
        'pdf_mime_type',
        'is_pdf_comic',
        'pdf_metadata',
        'cover_image_path',
        'preview_pages',
        'has_mature_content',
        'content_warnings',
        'is_free',
        'price',
        'is_visible',
        'published_at',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_visible' => 'boolean',
        'has_mature_content' => 'boolean',
        'is_pdf_comic' => 'boolean',
        'published_at' => 'datetime',
        'tags' => 'array',
        'preview_pages' => 'array',
        'pdf_metadata' => 'array',
        'content_warnings' => 'array',
        'average_rating' => 'decimal:2',
        'price' => 'decimal:2',
        'page_count' => 'integer',
        'total_readers' => 'integer',
        'total_ratings' => 'integer',
        'view_count' => 'integer',
        'issue_number' => 'integer',
        'publication_year' => 'integer',
        'pdf_file_size' => 'integer',
        'reading_time_estimate' => 'integer',
    ];

    // Scopes
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserComicProgress::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ComicView::class);
    }

    public function libraryEntries(): HasMany
    {
        return $this->hasMany(UserLibrary::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_libraries')
            ->withPivot(['access_type', 'purchase_price', 'purchased_at', 'is_favorite', 'rating', 'review'])
            ->withTimestamps();
    }

    public function getProgressForUser(User $user): ?UserComicProgress
    {
        return $this->userProgress()->where('user_id', $user->id)->first();
    }

    public function getAverageRating(): float
    {
        return $this->libraryEntries()
            ->whereNotNull('rating')
            ->avg('rating') ?? 0.0;
    }

    public function getTotalReaders(): int
    {
        return $this->libraryEntries()->count();
    }

    public function getCoverImageUrl(): ?string
    {
        if ($this->cover_image_path) {
            return asset('storage/' . $this->cover_image_path);
        }
        
        // Return a default cover image if no cover image is set
        return asset('images/default-comic-cover.svg');
    }

    public function getPdfUrl(): ?string
    {
        if (!$this->pdf_file_path) {
            return null;
        }

        // In production, use the PDF proxy route for better CORS handling
        if (app()->environment('production')) {
            return route('pdf.proxy', ['path' => $this->pdf_file_path]);
        }

        // For local development, use direct storage path
        return asset('storage/' . $this->pdf_file_path);
    }

    public function updateRating(): void
    {
        $ratings = $this->libraryEntries()->whereNotNull('rating');
        $this->average_rating = $ratings->avg('rating') ?? 0.0;
        $this->total_ratings = $ratings->count();
        $this->save();
    }

    public function incrementReaderCount(): void
    {
        $this->increment('total_readers');
    }

    public function getTagsArray(): array
    {
        $tags = $this->tags;
        
        // If tags is null, return empty array
        if (is_null($tags)) {
            return [];
        }
        
        // If already an array, return as is
        if (is_array($tags)) {
            return $tags;
        }
        
        // If string, split by comma and trim whitespace
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode(',', $tags)), fn($tag) => !empty($tag));
        }
        
        // Fallback to empty array
        return [];
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTagsArray());
    }

    public function addTag(string $tag): void
    {
        $tags = $this->getTagsArray();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayments(): HasMany
    {
        return $this->payments()->where('status', 'succeeded');
    }

    public function getTotalRevenue(): float
    {
        return $this->successfulPayments()->sum('amount');
    }

    public function getPurchaseCount(): int
    {
        return $this->successfulPayments()->count();
    }

    // New relationships for enhanced functionality
    public function series(): BelongsTo
    {
        return $this->belongsTo(ComicSeries::class, 'series_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ComicReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(ComicBookmark::class);
    }

    public function socialShares(): HasMany
    {
        return $this->hasMany(SocialShare::class);
    }

    // Enhanced methods for recommendations and similarity
    public function getRecommendedComics(int $limit = 10): Collection
    {
        $recommendations = new Collection();
        
        // 1. Get comics from the same series (highest priority)
        if ($this->series_id) {
            $seriesComics = self::where('series_id', $this->series_id)
                ->where('id', '!=', $this->id)
                ->where('is_visible', true)
                ->orderBy('issue_number')
                ->limit($limit)
                ->get();
            $recommendations = $recommendations->merge($seriesComics);
        }

        // 2. Get similar comics by multiple factors (weighted scoring)
        $remaining = $limit - $recommendations->count();
        if ($remaining > 0) {
            $similarComics = $this->getSimilarComicsByScore($remaining);
            $recommendations = $recommendations->merge($similarComics);
        }

        // 3. Fill remaining slots with popular comics from same genre
        $remaining = $limit - $recommendations->count();
        if ($remaining > 0 && $this->genre) {
            $popularComics = self::where('id', '!=', $this->id)
                ->where('genre', $this->genre)
                ->where('is_visible', true)
                ->whereNotIn('id', $recommendations->pluck('id'))
                ->orderByDesc('average_rating')
                ->orderByDesc('total_readers')
                ->limit($remaining)
                ->get();
            $recommendations = $recommendations->merge($popularComics);
        }

        return $recommendations->take($limit);
    }

    public function getSimilarComics(int $limit = 5): Collection
    {
        return $this->getSimilarComicsByScore($limit);
    }

    /**
     * Get similar comics using a weighted scoring algorithm
     */
    protected function getSimilarComicsByScore(int $limit): Collection
    {
        $comics = self::where('id', '!=', $this->id)
            ->where('is_visible', true)
            ->get();

        $scoredComics = $comics->map(function ($comic) {
            $score = 0;
            
            // Genre match (highest weight)
            if ($this->genre && $comic->genre === $this->genre) {
                $score += 40;
            }
            
            // Author match
            if ($this->author && $comic->author === $this->author) {
                $score += 30;
            }
            
            // Publisher match
            if ($this->publisher && $comic->publisher === $this->publisher) {
                $score += 20;
            }
            
            // Tag similarity
            $myTags = $this->getTagsArray();
            $comicTags = $comic->getTagsArray();
            $commonTags = array_intersect($myTags, $comicTags);
            $score += count($commonTags) * 5;
            
            // Publication year proximity (within 5 years)
            if ($this->publication_year && $comic->publication_year) {
                $yearDiff = abs($this->publication_year - $comic->publication_year);
                if ($yearDiff <= 5) {
                    $score += (5 - $yearDiff) * 2;
                }
            }
            
            // Quality bonus (high-rated comics get preference)
            if ($comic->average_rating >= 4.0) {
                $score += 10;
            } elseif ($comic->average_rating >= 3.5) {
                $score += 5;
            }
            
            // Popularity bonus
            if ($comic->total_readers > 100) {
                $score += 5;
            }
            
            return [
                'comic' => $comic,
                'score' => $score
            ];
        })
        ->filter(fn($item) => $item['score'] >= 10) // Require minimum score threshold
        ->sortByDesc('score')
        ->take($limit);

        // Extract just the comics and return as Eloquent Collection
        $resultComics = $scoredComics->pluck('comic');
        return new Collection($resultComics->all());
    }

    /**
     * Get comics that users who liked this comic also liked
     */
    public function getCollaborativeRecommendations(int $limit = 10): Collection
    {
        // Get users who rated this comic highly (4+ stars)
        $similarUsers = $this->libraryEntries()
            ->where('rating', '>=', 4)
            ->pluck('user_id');

        if ($similarUsers->isEmpty()) {
            return collect();
        }

        // Get other comics these users rated highly
        return self::whereHas('libraryEntries', function ($query) use ($similarUsers) {
                $query->whereIn('user_id', $similarUsers)
                      ->where('rating', '>=', 4);
            })
            ->where('id', '!=', $this->id)
            ->where('is_visible', true)
            ->withCount(['libraryEntries as similar_user_ratings' => function ($query) use ($similarUsers) {
                $query->whereIn('user_id', $similarUsers)->where('rating', '>=', 4);
            }])
            ->orderByDesc('similar_user_ratings')
            ->orderByDesc('average_rating')
            ->limit($limit)
            ->get();
    }

    public function updateAverageRating(): void
    {
        $avgRating = $this->approvedReviews()->avg('rating') ?? 0.0;
        $totalRatings = $this->approvedReviews()->count();
        
        // Use direct database update to avoid triggering slug regeneration
        self::where('id', $this->id)->update([
            'average_rating' => $avgRating,
            'total_ratings' => $totalRatings,
        ]);
        
        // Update the model instance
        $this->average_rating = $avgRating;
        $this->total_ratings = $totalRatings;
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->getTagsArray();
        $this->tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $this->save();
    }

    public function recordView(?User $user = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sessionId = null): void
    {
        ComicView::recordView($this, $user, $ipAddress, $userAgent, $sessionId);
    }

    public function getViewsInPeriod(int $days = 30): int
    {
        return $this->views()
            ->where('viewed_at', '>', now()->subDays($days))
            ->count();
    }

    public function getUniqueViewersInPeriod(int $days = 30): int
    {
        return $this->views()
            ->where('viewed_at', '>', now()->subDays($days))
            ->selectRaw('COUNT(DISTINCT COALESCE(user_id::text, ip_address)) as unique_count')
            ->value('unique_count') ?? 0;
    }

    public function getPreviewPagesArray(): array
    {
        return $this->preview_pages ?? [];
    }

    public function setPreviewPages(array $pages): void
    {
        $this->preview_pages = array_unique(array_filter($pages, 'is_numeric'));
        $this->save();
    }

    public function isNewRelease(): bool
    {
        return $this->published_at && $this->published_at->isAfter(now()->subDays(30));
    }

    public function getReadingTimeEstimate(): int
    {
        // Estimate 2 minutes per page for average reading
        return $this->page_count ? $this->page_count * 2 : 0;
    }

    public function getPdfFileUrl(): ?string
    {
        return $this->pdf_file_path ? asset($this->pdf_file_path) : null;
    }

    public function getPdfStreamUrl(): ?string
    {
        return $this->pdf_file_path ? route('comics.stream', $this->slug) : null;
    }

    public function getFormattedFileSize(): string
    {
        if (!$this->pdf_file_size) {
            return 'Unknown size';
        }

        $bytes = $this->pdf_file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $i > 0 ? 2 : 0) . ' ' . $units[$i];
    }

    public function isPdfComic(): bool
    {
        return $this->is_pdf_comic && !empty($this->pdf_file_path);
    }

    public function getPdfMetadata(): array
    {
        return $this->pdf_metadata ?? [];
    }

    public function setPdfMetadata(array $metadata): void
    {
        $this->pdf_metadata = $metadata;
        $this->save();
    }

    /**
     * Extract and process metadata from PDF file
     */
    public function extractAndProcessMetadata(): array
    {
        if (!$this->pdf_file_path || !file_exists(storage_path('app/public/' . $this->pdf_file_path))) {
            return [];
        }

        $metadata = [];
        $filePath = storage_path('app/public/' . $this->pdf_file_path);

        try {
            // Basic file information
            $metadata['file_size'] = filesize($filePath);
            $metadata['file_modified'] = filemtime($filePath);
            $metadata['mime_type'] = mime_content_type($filePath);

            // Try to extract PDF metadata using basic PHP functions
            if (extension_loaded('imagick')) {
                $metadata = array_merge($metadata, $this->extractPdfMetadataWithImagick($filePath));
            }

            // Extract text content for search indexing (first few pages)
            $metadata['extracted_text'] = $this->extractTextFromPdf($filePath);

            // Analyze content for automatic tagging
            $metadata['suggested_tags'] = $this->generateSuggestedTags($metadata['extracted_text'] ?? '');

            // Update the model with extracted metadata
            $this->setPdfMetadata($metadata);

            return $metadata;
        } catch (\Exception $e) {
            \Log::error('Failed to extract PDF metadata', [
                'comic_id' => $this->id,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract PDF metadata using Imagick
     */
    protected function extractPdfMetadataWithImagick(string $filePath): array
    {
        $metadata = [];

        try {
            $imagick = new \Imagick();
            $imagick->readImage($filePath . '[0]'); // Read first page only

            // Get PDF properties
            $metadata['pdf_version'] = $imagick->getImageProperty('pdf:Version') ?? null;
            $metadata['pdf_producer'] = $imagick->getImageProperty('pdf:Producer') ?? null;
            $metadata['pdf_creator'] = $imagick->getImageProperty('pdf:Creator') ?? null;
            $metadata['pdf_title'] = $imagick->getImageProperty('pdf:Title') ?? null;
            $metadata['pdf_author'] = $imagick->getImageProperty('pdf:Author') ?? null;
            $metadata['pdf_subject'] = $imagick->getImageProperty('pdf:Subject') ?? null;
            $metadata['pdf_keywords'] = $imagick->getImageProperty('pdf:Keywords') ?? null;

            // Get page count
            $imagick->readImage($filePath);
            $metadata['total_pages'] = $imagick->getNumberImages();

            // Get page dimensions (from first page)
            $imagick->readImage($filePath . '[0]');
            $metadata['page_width'] = $imagick->getImageWidth();
            $metadata['page_height'] = $imagick->getImageHeight();
            $metadata['page_resolution'] = $imagick->getImageResolution();

            $imagick->clear();
            $imagick->destroy();

            // Update page count if different
            if (isset($metadata['total_pages']) && $this->page_count !== $metadata['total_pages']) {
                $this->page_count = $metadata['total_pages'];
                $this->save();
            }

        } catch (\Exception $e) {
            \Log::warning('Imagick PDF metadata extraction failed', [
                'comic_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }

        return $metadata;
    }

    /**
     * Extract text content from PDF for search indexing
     */
    protected function extractTextFromPdf(string $filePath, int $maxPages = 3): string
    {
        $extractedText = '';

        try {
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                
                // Extract text from first few pages only
                for ($page = 0; $page < $maxPages; $page++) {
                    try {
                        $imagick->readImage($filePath . '[' . $page . ']');
                        $imagick->setImageFormat('txt');
                        $pageText = $imagick->getImageBlob();
                        
                        // Clean up the text
                        $pageText = preg_replace('/^.*?txt:/', '', $pageText);
                        $pageText = trim($pageText);
                        
                        if (!empty($pageText)) {
                            $extractedText .= $pageText . ' ';
                        }
                        
                        $imagick->clear();
                    } catch (\Exception $e) {
                        // Skip this page if extraction fails
                        continue;
                    }
                }
                
                $imagick->destroy();
            }
        } catch (\Exception $e) {
            \Log::warning('PDF text extraction failed', [
                'comic_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }

        return trim($extractedText);
    }

    /**
     * Generate suggested tags based on extracted content
     */
    protected function generateSuggestedTags(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $suggestedTags = [];
        $text = strtolower($text);

        // Common comic book themes and genres
        $themeKeywords = [
            'superhero' => ['superhero', 'super hero', 'cape', 'mask', 'powers', 'villain'],
            'fantasy' => ['magic', 'wizard', 'dragon', 'sword', 'quest', 'kingdom'],
            'sci-fi' => ['space', 'alien', 'robot', 'future', 'technology', 'spaceship'],
            'horror' => ['zombie', 'vampire', 'ghost', 'monster', 'scary', 'fear'],
            'romance' => ['love', 'heart', 'kiss', 'relationship', 'romantic'],
            'action' => ['fight', 'battle', 'war', 'combat', 'explosion'],
            'mystery' => ['detective', 'crime', 'murder', 'investigation', 'clue'],
            'comedy' => ['funny', 'laugh', 'joke', 'humor', 'comic'],
        ];

        foreach ($themeKeywords as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $suggestedTags[] = $tag;
                    break;
                }
            }
        }

        // Remove duplicates and limit to 5 suggestions
        return array_unique(array_slice($suggestedTags, 0, 5));
    }

    /**
     * Auto-populate fields from extracted metadata
     */
    public function autoPopulateFromMetadata(): bool
    {
        $metadata = $this->getPdfMetadata();
        $updated = false;

        // Update title if empty and PDF has title
        if (empty($this->title) && !empty($metadata['pdf_title'])) {
            $this->title = $metadata['pdf_title'];
            $updated = true;
        }

        // Update author if empty and PDF has author
        if (empty($this->author) && !empty($metadata['pdf_author'])) {
            $this->author = $metadata['pdf_author'];
            $updated = true;
        }

        // Update description if empty and PDF has subject
        if (empty($this->description) && !empty($metadata['pdf_subject'])) {
            $this->description = $metadata['pdf_subject'];
            $updated = true;
        }

        // Add suggested tags
        if (!empty($metadata['suggested_tags'])) {
            $currentTags = $this->getTagsArray();
            $newTags = array_diff($metadata['suggested_tags'], $currentTags);
            
            if (!empty($newTags)) {
                $this->tags = array_merge($currentTags, $newTags);
                $updated = true;
            }
        }

        if ($updated) {
            $this->save();
        }

        return $updated;
    }
    
    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->allowDuplicateSlugs(false)
            ->slugsShouldBeNoLongerThan(255);
    }

    /**
     * Get the indexable data array for the model.
     */
    // Temporarily disabled search functionality for testing
    // public function toSearchableArray(): array
    // {
    //     $array = $this->toArray();
        
    //     // Add computed fields for better search
    //     $array['series_name'] = $this->series?->name;
    //     $array['tags_string'] = implode(' ', $this->getTagsArray());
    //     $array['content_warnings_string'] = implode(' ', $this->content_warnings ?? []);
    //     $array['is_new_release'] = $this->isNewRelease();
    //     $array['reading_time_estimate'] = $this->getReadingTimeEstimate();
        
    //     // Add extracted text from PDF metadata for full-text search
    //     $metadata = $this->getPdfMetadata();
    //     $array['extracted_text'] = $metadata['extracted_text'] ?? '';
        
    //     // Add review-related data
    //     $array['review_count'] = $this->reviews()->count();
    //     $array['approved_review_count'] = $this->approvedReviews()->count();
        
    //     // Add popularity metrics
    //     $array['recent_views'] = $this->getViewsInPeriod(30);
    //     $array['unique_viewers'] = $this->getUniqueViewersInPeriod(30);
        
    //     // Remove sensitive or unnecessary fields
    //     unset($array['pdf_file_path'], $array['pdf_metadata']);
        
    //     return $array;
    // }

    // /**
    //  * Determine if the model should be searchable.
    //  */
    // public function shouldBeSearchable(): bool
    // {
    //     return $this->is_visible && $this->published_at !== null;
    // }

    /**
     * Get the value used to index the model.
     */
    public function getScoutKey(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the key name used to index the model.
     */
    public function getScoutKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Customize the search index name.
     */
    public function searchableAs(): string
    {
        return 'comics_index';
    }
}
