<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\SocialShare;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialSharingService
{
    const SUPPORTED_PLATFORMS = ['facebook', 'twitter', 'instagram'];
    const SHARE_TYPES = ['comic_discovery', 'reading_achievement', 'recommendation', 'review'];

    /**
     * Share a comic to a social media platform
     */
    public function shareComic(User $user, Comic $comic, string $platform, string $shareType, array $options = []): SocialShare
    {
        $this->validatePlatform($platform);
        $this->validateShareType($shareType);

        $metadata = $this->generateShareMetadata($comic, $platform, $shareType, $options);
        
        $socialShare = SocialShare::createShare($user, $comic, $platform, $shareType, $metadata);

        // Log the sharing activity
        Log::info('Comic shared to social media', [
            'user_id' => $user->id,
            'comic_id' => $comic->id,
            'platform' => $platform,
            'share_type' => $shareType
        ]);

        return $socialShare;
    }

    /**
     * Generate sharing metadata for different platforms
     */
    public function generateShareMetadata(Comic $comic, string $platform, string $shareType, array $options = []): array
    {
        $baseMetadata = [
            'title' => $comic->title,
            'description' => $this->generateShareDescription($comic, $shareType, $options),
            'image_url' => $comic->cover_image_path ? asset('storage/' . $comic->cover_image_path) : null,
            'comic_url' => route('comics.show', $comic->slug),
            'share_url' => $this->generateShareUrl($comic, $platform, $shareType),
            'hashtags' => $this->generateHashtags($comic, $platform),
            'share_type' => $shareType,
            'timestamp' => now()->toISOString(),
        ];

        return array_merge($baseMetadata, $this->getPlatformSpecificMetadata($platform, $comic, $shareType, $options));
    }

    /**
     * Generate platform-specific share URLs
     */
    public function generateShareUrl(Comic $comic, string $platform, string $shareType): string
    {
        $comicUrl = route('comics.show', $comic->slug);
        $encodedUrl = urlencode($comicUrl);
        $encodedTitle = urlencode($comic->title);
        
        switch ($platform) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";
            
            case 'twitter':
                $text = urlencode($this->generateTwitterText($comic, $shareType));
                return "https://twitter.com/intent/tweet?text={$text}&url={$encodedUrl}";
            
            case 'instagram':
                // Instagram doesn't support direct URL sharing, return app URL for deep linking
                return "instagram://camera";
            
            default:
                return $comicUrl;
        }
    }

    /**
     * Generate share description based on share type
     */
    private function generateShareDescription(Comic $comic, string $shareType, array $options = []): string
    {
        switch ($shareType) {
            case 'comic_discovery':
                return "Check out this amazing comic: {$comic->title} by {$comic->author}!";
            
            case 'reading_achievement':
                $achievement = $options['achievement'] ?? 'finished reading';
                return "I just {$achievement} '{$comic->title}' - what an incredible story!";
            
            case 'recommendation':
                return "I highly recommend '{$comic->title}' by {$comic->author}. You should definitely check it out!";
            
            case 'review':
                $rating = $options['rating'] ?? null;
                $ratingText = $rating ? " ({$rating}/5 stars)" : '';
                return "Just reviewed '{$comic->title}'{$ratingText}. Here's what I thought...";
            
            default:
                return "Discover '{$comic->title}' by {$comic->author} on our comic platform!";
        }
    }

    /**
     * Generate hashtags for the comic
     */
    private function generateHashtags(Comic $comic, string $platform): array
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
        
        // Platform-specific hashtag limits
        $maxHashtags = $platform === 'twitter' ? 3 : 5;
        
        return array_slice($hashtags, 0, $maxHashtags);
    }

    /**
     * Generate Twitter-specific text
     */
    private function generateTwitterText(Comic $comic, string $shareType): string
    {
        $baseText = $this->generateShareDescription($comic, $shareType, []);
        $hashtags = implode(' ', $this->generateHashtags($comic, 'twitter'));
        
        // Twitter character limit consideration
        $maxLength = 280 - strlen($hashtags) - 30; // Reserve space for URL and hashtags
        
        if (strlen($baseText) > $maxLength) {
            $baseText = substr($baseText, 0, $maxLength - 3) . '...';
        }
        
        return $baseText . ' ' . $hashtags;
    }

    /**
     * Get platform-specific metadata
     */
    private function getPlatformSpecificMetadata(string $platform, Comic $comic, string $shareType, array $options = []): array
    {
        switch ($platform) {
            case 'facebook':
                return [
                    'og:title' => $comic->title,
                    'og:description' => $this->generateShareDescription($comic, $shareType, $options),
                    'og:image' => $comic->cover_image_path ? asset('storage/' . $comic->cover_image_path) : null,
                    'og:type' => 'article',
                    'fb:app_id' => config('services.facebook.app_id'),
                ];
            
            case 'twitter':
                return [
                    'twitter:card' => 'summary_large_image',
                    'twitter:title' => $comic->title,
                    'twitter:description' => $this->generateShareDescription($comic, $shareType, $options),
                    'twitter:image' => $comic->cover_image_path ? asset('storage/' . $comic->cover_image_path) : null,
                    'twitter:site' => config('services.twitter.site_handle'),
                ];
            
            case 'instagram':
                return [
                    'caption' => $this->generateShareDescription($comic, $shareType, $options),
                    'image_url' => $comic->cover_image_path ? asset('storage/' . $comic->cover_image_path) : null,
                ];
            
            default:
                return [];
        }
    }

    /**
     * Get user's sharing history
     */
    public function getUserSharingHistory(User $user, ?string $platform = null): Collection
    {
        $query = $user->socialShares()->with('comic')->latest();
        
        if ($platform) {
            $query->byPlatform($platform);
        }
        
        return $query->get();
    }

    /**
     * Get sharing statistics for a comic
     */
    public function getComicSharingStats(Comic $comic): array
    {
        $shares = $comic->socialShares;
        
        return [
            'total_shares' => $shares->count(),
            'platform_breakdown' => $shares->groupBy('platform')->map->count(),
            'share_type_breakdown' => $shares->groupBy('share_type')->map->count(),
            'recent_shares' => $shares->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Track reading achievement and suggest sharing
     */
    public function trackReadingAchievement(User $user, Comic $comic, string $achievement, array $metadata = []): array
    {
        $suggestions = [];
        
        // Generate sharing suggestions based on achievement
        switch ($achievement) {
            case 'completed':
                $suggestions = [
                    'facebook' => 'Share your reading completion with friends!',
                    'twitter' => 'Tweet about finishing this amazing comic!',
                ];
                break;
            
            case 'milestone':
                $page = $metadata['page'] ?? 0;
                $totalPages = $comic->page_count ?? 1;
                $percentage = round(($page / $totalPages) * 100);
                
                if ($percentage >= 50) {
                    $suggestions = [
                        'twitter' => "Share your reading progress ({$percentage}% complete)!",
                    ];
                }
                break;
        }
        
        return [
            'achievement' => $achievement,
            'metadata' => $metadata,
            'sharing_suggestions' => $suggestions,
        ];
    }

    /**
     * Validate platform is supported
     */
    private function validatePlatform(string $platform): void
    {
        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            throw new \InvalidArgumentException("Unsupported platform: {$platform}");
        }
    }

    /**
     * Validate share type
     */
    private function validateShareType(string $shareType): void
    {
        if (!in_array($shareType, self::SHARE_TYPES)) {
            throw new \InvalidArgumentException("Unsupported share type: {$shareType}");
        }
    }
}