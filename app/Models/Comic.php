<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Comic extends Model
{
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
        'isbn',
        'publication_year',
        'publisher',
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
        'average_rating' => 'decimal:2',
        'price' => 'decimal:2',
        'page_count' => 'integer',
        'total_readers' => 'integer',
        'total_ratings' => 'integer',
        'reading_time_estimate' => 'integer',
    ];

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
        return $this->cover_image_path ? asset('storage/' . $this->cover_image_path) : null;
    }

    public function getPdfUrl(): ?string
    {
        return $this->pdf_file_path ? asset('storage/' . $this->pdf_file_path) : null;
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
        return $this->tags ?? [];
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
            ->distinct()
            ->count('COALESCE(user_id, ip_address)');
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

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
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
}
