<?php

namespace App\Services;

use App\Models\CmsContent;
use App\Models\CmsContentVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CmsService
{
    /**
     * Cache duration in minutes
     */
    protected int $cacheDuration = 60;

    protected CmsVersioningService $versioningService;
    protected CmsAnalyticsService $analyticsService;
    protected CmsMediaService $mediaService;

    public function __construct(
        CmsVersioningService $versioningService,
        CmsAnalyticsService $analyticsService,
        CmsMediaService $mediaService
    ) {
        $this->versioningService = $versioningService;
        $this->analyticsService = $analyticsService;
        $this->mediaService = $mediaService;
    }

    /**
     * Get content by key with caching and analytics tracking
     */
    public function getContent(string $key, $default = null, bool $trackView = true)
    {
        $cacheKey = "cms_content_{$key}";
        
        $content = Cache::remember($cacheKey, $this->cacheDuration, function () use ($key, $default) {
            return CmsContent::getByKey($key, $default);
        });
        
        // Track view if content exists and tracking is enabled
        if ($trackView && $content && $content !== $default) {
            $cmsContent = CmsContent::byKey($key)->first();
            if ($cmsContent) {
                $this->analyticsService->trackEvent($cmsContent, 'view');
            }
        }
        
        return $content;
    }

    /**
     * Get all content for a section with caching
     */
    public function getSection(string $section): array
    {
        $cacheKey = "cms_section_{$section}";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($section) {
            $content = CmsContent::getBySection($section);
            
            // Convert to array format for easier frontend consumption
            $result = [];
            foreach ($content as $key => $item) {
                $result[$key] = match($item->type) {
                    'image' => [
                        'type' => 'image',
                        'url' => $item->image_url,
                        'alt' => $item->metadata['alt'] ?? $item->title,
                        'metadata' => $item->metadata,
                    ],
                    'json' => [
                        'type' => 'json',
                        'data' => $item->metadata,
                        'content' => $item->content,
                    ],
                    'rich_text' => [
                        'type' => 'rich_text',
                        'content' => $item->content,
                        'metadata' => $item->metadata,
                    ],
                    default => [
                        'type' => 'text',
                        'content' => $item->content,
                        'metadata' => $item->metadata,
                    ],
                };
            }
            
            return $result;
        });
    }

    /**
     * Get hero section content
     */
    public function getHeroContent(): array
    {
        return $this->getSection('hero');
    }

    /**
     * Get about section content
     */
    public function getAboutContent(): array
    {
        return $this->getSection('about');
    }

    /**
     * Get navigation content
     */
    public function getNavigationContent(): array
    {
        return $this->getSection('navigation');
    }

    /**
     * Get footer content
     */
    public function getFooterContent(): array
    {
        return $this->getSection('footer');
    }

    /**
     * Clear all CMS cache
     */
    public function clearCache(): void
    {
        $sections = ['hero', 'about', 'features', 'footer', 'navigation', 'general'];
        
        foreach ($sections as $section) {
            Cache::forget("cms_section_{$section}");
        }

        // Clear individual content cache
        $allContent = CmsContent::all();
        foreach ($allContent as $content) {
            Cache::forget("cms_content_{$content->key}");
        }
    }

    /**
     * Get all content formatted for frontend
     */
    public function getAllContent(): array
    {
        $cacheKey = 'cms_all_content';
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () {
            $sections = ['hero', 'about', 'features', 'footer', 'navigation', 'general'];
            $result = [];
            
            foreach ($sections as $section) {
                $result[$section] = $this->getSection($section);
            }
            
            return $result;
        });
    }

    /**
     * Update content with versioning support
     */
    public function updateContent(string $key, array $data, ?int $userId = null, bool $createVersion = true): CmsContent
    {
        return DB::transaction(function () use ($key, $data, $userId, $createVersion) {
            $content = CmsContent::where('key', $key)->first();
            
            if (!$content) {
                // Create new content
                $data['key'] = $key;
                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;
                $content = CmsContent::create($data);
                
                // Create initial version if versioning is enabled
                if ($createVersion) {
                    $this->versioningService->createVersion($content, $data, $userId);
                }
            } else {
                // Create version before updating if versioning is enabled
                if ($createVersion) {
                    $this->versioningService->createVersion($content, $data, $userId);
                }
                
                // Update main content
                $data['updated_by'] = $userId;
                $content->update($data);
                
                // Track edit event
                $this->analyticsService->trackEvent($content, 'edit', [
                    'user_id' => $userId,
                    'fields_changed' => array_keys($data),
                ]);
            }
            
            // Clear relevant cache
            $this->clearContentCache($key, $data['section'] ?? null);
            
            return $content;
        });
    }

    /**
     * Create content with versioning
     */
    public function createContent(array $data, ?int $userId = null): CmsContent
    {
        return $this->updateContent($data['key'], $data, $userId, true);
    }

    /**
     * Publish content
     */
    public function publishContent(CmsContent $content, ?int $userId = null): bool
    {
        $result = $content->update([
            'status' => 'published',
            'published_at' => now(),
            'updated_by' => $userId,
        ]);
        
        if ($result) {
            $this->analyticsService->trackEvent($content, 'publish', [
                'user_id' => $userId,
            ]);
            
            $this->clearContentCache($content->key, $content->section);
        }
        
        return $result;
    }

    /**
     * Schedule content for publishing
     */
    public function scheduleContent(CmsContent $content, \DateTime $scheduledAt, ?int $userId = null): bool
    {
        $result = $content->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'updated_by' => $userId,
        ]);
        
        if ($result) {
            $this->analyticsService->trackEvent($content, 'schedule', [
                'user_id' => $userId,
                'scheduled_at' => $scheduledAt->format('c'),
            ]);
        }
        
        return $result;
    }

    /**
     * Archive content
     */
    public function archiveContent(CmsContent $content, ?int $userId = null): bool
    {
        $result = $content->update([
            'status' => 'archived',
            'is_active' => false,
            'updated_by' => $userId,
        ]);
        
        if ($result) {
            $this->analyticsService->trackEvent($content, 'archive', [
                'user_id' => $userId,
            ]);
            
            $this->clearContentCache($content->key, $content->section);
        }
        
        return $result;
    }

    /**
     * Get content with full details including versions and analytics
     */
    public function getContentDetails(string $key): ?array
    {
        $content = CmsContent::with(['versions.creator', 'creator', 'updater'])
            ->where('key', $key)
            ->first();
        
        if (!$content) {
            return null;
        }
        
        return [
            'content' => $content,
            'performance' => $this->analyticsService->getContentPerformance($content),
            'versions' => $this->versioningService->getVersionHistory($content),
        ];
    }

    /**
     * Process scheduled content
     */
    public function processScheduledContent(): int
    {
        $scheduledContent = CmsContent::scheduled()
            ->where('scheduled_at', '<=', now())
            ->get();
        
        $published = 0;
        
        foreach ($scheduledContent as $content) {
            if ($this->publishContent($content)) {
                $published++;
            }
        }
        
        // Also process scheduled versions
        $published += $this->versioningService->processScheduledContent();
        
        return $published;
    }

    /**
     * Clear content-specific cache
     */
    protected function clearContentCache(string $key, ?string $section = null): void
    {
        Cache::forget("cms_content_{$key}");
        
        if ($section) {
            Cache::forget("cms_section_{$section}");
        }
        
        Cache::forget('cms_all_content');
    }

    /**
     * Get default content values
     */
    public function getDefaults(): array
    {
        return [
            'hero_title' => 'African Stories, Boldly Told',
            'hero_subtitle' => 'Discover captivating tales from the heart of Africa. Immerse yourself in rich cultures, legendary heroes, and timeless wisdom through our curated collection of comics.',
            'hero_cta_primary' => 'Start Reading',
            'hero_cta_secondary' => 'Browse Collection',
            'trending_title' => 'Trending Now',
            'trending_subtitle' => 'Most popular comics this week',
            'new_releases_title' => 'New Releases',
            'new_releases_subtitle' => 'Fresh stories just added',
            'free_comics_title' => 'Free to Read',
            'free_comics_subtitle' => 'Start your journey at no cost',
            'site_name' => 'BAG Comics',
            'footer_copyright' => '© 2024 BAG Comics. All rights reserved.',
        ];
    }
}
