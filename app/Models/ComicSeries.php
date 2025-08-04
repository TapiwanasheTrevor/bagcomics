<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ComicSeries extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'publisher',
        'total_issues',
        'status',
    ];

    protected $casts = [
        'total_issues' => 'integer',
    ];

    public function comics(): HasMany
    {
        return $this->hasMany(Comic::class, 'series_id');
    }

    public function getLatestIssue(): ?Comic
    {
        return $this->comics()->orderBy('issue_number', 'desc')->first();
    }

    public function getTotalIssues(): int
    {
        return $this->comics()->count();
    }

    public function updateTotalIssues(): void
    {
        $this->total_issues = $this->getTotalIssues();
        $this->save();
    }

    public function getAverageRating(): float
    {
        return $this->comics()->avg('average_rating') ?? 0.0;
    }

    public function getTotalReaders(): int
    {
        return $this->comics()->sum('total_readers');
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get comics in reading order
     */
    public function getComicsInOrder(): Collection
    {
        return $this->comics()
            ->where('is_visible', true)
            ->orderBy('issue_number')
            ->orderBy('published_at')
            ->get();
    }

    /**
     * Get the first issue of the series
     */
    public function getFirstIssue(): ?Comic
    {
        return $this->comics()
            ->where('is_visible', true)
            ->orderBy('issue_number')
            ->orderBy('published_at')
            ->first();
    }

    /**
     * Get the next issue after a given comic
     */
    public function getNextIssue(Comic $comic): ?Comic
    {
        return $this->comics()
            ->where('is_visible', true)
            ->where(function ($query) use ($comic) {
                $query->where('issue_number', '>', $comic->issue_number)
                      ->orWhere(function ($q) use ($comic) {
                          $q->where('issue_number', $comic->issue_number)
                            ->where('published_at', '>', $comic->published_at);
                      });
            })
            ->orderByRaw('CAST(issue_number AS UNSIGNED) ASC')
            ->orderBy('published_at')
            ->first();
    }

    /**
     * Get the previous issue before a given comic
     */
    public function getPreviousIssue(Comic $comic): ?Comic
    {
        return $this->comics()
            ->where('is_visible', true)
            ->where(function ($query) use ($comic) {
                $query->where('issue_number', '<', $comic->issue_number)
                      ->orWhere(function ($q) use ($comic) {
                          $q->where('issue_number', $comic->issue_number)
                            ->where('published_at', '<', $comic->published_at);
                      });
            })
            ->orderByRaw('CAST(issue_number AS UNSIGNED) DESC')
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * Get series statistics
     */
    public function getStatistics(): array
    {
        $comics = $this->comics;
        
        return [
            'total_issues' => $comics->count(),
            'published_issues' => $comics->where('is_visible', true)->count(),
            'average_rating' => $comics->avg('average_rating') ?? 0.0,
            'total_readers' => $comics->sum('total_readers'),
            'total_pages' => $comics->sum('page_count'),
            'first_published' => $comics->min('published_at'),
            'last_published' => $comics->max('published_at'),
            'total_revenue' => $comics->sum(function ($comic) {
                return $comic->getTotalRevenue();
            }),
        ];
    }

    /**
     * Check if series has missing issues
     */
    public function hasMissingIssues(): bool
    {
        $issues = $this->comics()
            ->whereNotNull('issue_number')
            ->pluck('issue_number')
            ->sort()
            ->values();

        if ($issues->isEmpty()) {
            return false;
        }

        $expectedCount = $issues->max() - $issues->min() + 1;
        return $issues->count() < $expectedCount;
    }

    /**
     * Get missing issue numbers
     */
    public function getMissingIssues(): array
    {
        $issues = $this->comics()
            ->whereNotNull('issue_number')
            ->pluck('issue_number')
            ->sort()
            ->values()
            ->toArray();

        if (empty($issues)) {
            return [];
        }

        $min = min($issues);
        $max = max($issues);
        $expected = range($min, $max);
        
        return array_values(array_diff($expected, $issues));
    }

    /**
     * Get reading progress for a user across the series
     */
    public function getReadingProgressForUser(User $user): array
    {
        $comics = $this->getComicsInOrder();
        $totalComics = $comics->count();
        $readComics = 0;
        $inProgressComics = 0;

        foreach ($comics as $comic) {
            $progress = $comic->getProgressForUser($user);
            if ($progress) {
                if ($progress->is_completed) {
                    $readComics++;
                } else {
                    $inProgressComics++;
                }
            }
        }

        return [
            'total_issues' => $totalComics,
            'read_issues' => $readComics,
            'in_progress_issues' => $inProgressComics,
            'unread_issues' => $totalComics - $readComics - $inProgressComics,
            'completion_percentage' => $totalComics > 0 ? ($readComics / $totalComics) * 100 : 0,
        ];
    }

    /**
     * Get recommended series based on this series
     */
    public function getRecommendedSeries(int $limit = 5): Collection
    {
        return self::where('id', '!=', $this->id)
            ->where(function ($query) {
                if ($this->publisher) {
                    $query->where('publisher', $this->publisher);
                }
            })
            ->withAvg('comics', 'average_rating')
            ->withSum('comics', 'total_readers')
            ->orderByDesc('comics_avg_average_rating')
            ->orderByDesc('comics_sum_total_readers')
            ->limit($limit)
            ->get();
    }

    /**
     * Update series status based on comics
     */
    public function updateStatus(): void
    {
        $latestComic = $this->getLatestIssue();
        
        if (!$latestComic) {
            $this->status = 'planned';
        } elseif ($latestComic->published_at && $latestComic->published_at->isAfter(now()->subMonths(6))) {
            $this->status = 'ongoing';
        } else {
            $this->status = 'completed';
        }
        
        $this->save();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->allowDuplicateSlugs(false)
            ->slugsShouldBeNoLongerThan(255);
    }
}
