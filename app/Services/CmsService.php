<?php

namespace App\Services;

use App\Models\CmsContent;
use Illuminate\Support\Facades\Cache;

class CmsService
{
    /**
     * Cache duration in minutes
     */
    protected int $cacheDuration = 60;

    /**
     * Get content by key with caching
     */
    public function getContent(string $key, $default = null)
    {
        $cacheKey = "cms_content_{$key}";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($key, $default) {
            return CmsContent::getByKey($key, $default);
        });
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
     * Update content and clear cache
     */
    public function updateContent(string $key, array $data): bool
    {
        $content = CmsContent::where('key', $key)->first();
        
        if ($content) {
            $content->update($data);
        } else {
            $data['key'] = $key;
            $content = CmsContent::create($data);
        }
        
        // Clear relevant cache
        Cache::forget("cms_content_{$key}");
        if (isset($data['section'])) {
            Cache::forget("cms_section_{$data['section']}");
        }
        Cache::forget('cms_all_content');
        
        return true;
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
