<?php

namespace App\Services;

use App\Models\CmsContent;
use App\Models\CmsContentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CmsVersioningService
{
    /**
     * Create a new version of content
     */
    public function createVersion(CmsContent $content, array $data, ?int $userId = null): CmsContentVersion
    {
        return DB::transaction(function () use ($content, $data, $userId) {
            $version = $content->createVersion($data, $userId);
            
            // Track the versioning event
            $content->trackEvent('version_created', [
                'version_number' => $version->version_number,
                'user_id' => $userId,
                'change_summary' => $data['change_summary'] ?? null,
            ]);
            
            return $version;
        });
    }

    /**
     * Publish a version
     */
    public function publishVersion(CmsContentVersion $version, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($version, $userId) {
            // Deactivate current published version
            $version->cmsContent->versions()
                ->where('status', 'published')
                ->update(['status' => 'archived', 'is_active' => false]);
            
            // Activate the new version
            $version->update([
                'status' => 'published',
                'is_active' => true,
                'published_at' => now(),
            ]);
            
            // Update the main content record
            $version->cmsContent->update([
                'title' => $version->title,
                'content' => $version->content,
                'metadata' => $version->metadata,
                'image_path' => $version->image_path,
                'status' => 'published',
                'published_at' => now(),
                'current_version' => $version->version_number,
                'updated_by' => $userId,
            ]);
            
            // Track the publishing event
            $version->cmsContent->trackEvent('version_published', [
                'version_number' => $version->version_number,
                'user_id' => $userId,
            ]);
            
            // Clear cache
            app(CmsService::class)->clearCache();
            
            return true;
        });
    }

    /**
     * Schedule a version for publishing
     */
    public function scheduleVersion(CmsContentVersion $version, \DateTime $scheduledAt, ?int $userId = null): bool
    {
        $version->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);
        
        // Track the scheduling event
        $version->cmsContent->trackEvent('version_scheduled', [
            'version_number' => $version->version_number,
            'scheduled_at' => $scheduledAt->format('c'),
            'user_id' => $userId,
        ]);
        
        return true;
    }

    /**
     * Revert to a previous version
     */
    public function revertToVersion(CmsContent $content, int $versionNumber, ?int $userId = null): bool
    {
        $version = $content->versions()->where('version_number', $versionNumber)->first();
        
        if (!$version) {
            return false;
        }
        
        return DB::transaction(function () use ($content, $version, $userId) {
            // Create a new version based on the old one
            $newVersion = $content->createVersion([
                'title' => $version->title,
                'content' => $version->content,
                'metadata' => $version->metadata,
                'image_path' => $version->image_path,
                'status' => 'published',
                'change_summary' => "Reverted to version {$version->version_number}",
            ], $userId);
            
            // Publish the new version
            return $this->publishVersion($newVersion, $userId);
        });
    }

    /**
     * Get version history for content
     */
    public function getVersionHistory(CmsContent $content): Collection
    {
        return $content->versions()
            ->with('creator')
            ->orderBy('version_number', 'desc')
            ->get();
    }

    /**
     * Compare two versions
     */
    public function compareVersions(CmsContentVersion $version1, CmsContentVersion $version2): array
    {
        return [
            'title_changed' => $version1->title !== $version2->title,
            'content_changed' => $version1->content !== $version2->content,
            'metadata_changed' => $version1->metadata !== $version2->metadata,
            'image_changed' => $version1->image_path !== $version2->image_path,
            'version1' => [
                'version_number' => $version1->version_number,
                'title' => $version1->title,
                'content' => $version1->content,
                'created_at' => $version1->created_at,
                'creator' => $version1->creator?->name,
            ],
            'version2' => [
                'version_number' => $version2->version_number,
                'title' => $version2->title,
                'content' => $version2->content,
                'created_at' => $version2->created_at,
                'creator' => $version2->creator?->name,
            ],
        ];
    }

    /**
     * Process scheduled content for publishing
     */
    public function processScheduledContent(): int
    {
        $scheduledVersions = CmsContentVersion::scheduled()
            ->where('scheduled_at', '<=', now())
            ->get();
        
        $published = 0;
        
        foreach ($scheduledVersions as $version) {
            if ($this->publishVersion($version)) {
                $published++;
            }
        }
        
        return $published;
    }
}