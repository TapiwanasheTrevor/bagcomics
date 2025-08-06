<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsContent;
use App\Services\CmsService;
use App\Services\CmsVersioningService;
use App\Services\CmsAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CmsContentController extends Controller
{
    public function __construct(
        protected CmsService $cmsService,
        protected CmsVersioningService $versioningService,
        protected CmsAnalyticsService $analyticsService
    ) {}

    /**
     * Get all CMS content with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = CmsContent::with(['creator', 'updater']);
        
        if ($request->has('section')) {
            $query->where('section', $request->section);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('key', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }
        
        $content = $query->orderBy('updated_at', 'desc')->paginate(20);
        
        return response()->json($content);
    }

    /**
     * Get specific content with details
     */
    public function show(string $key): JsonResponse
    {
        $details = $this->cmsService->getContentDetails($key);
        
        if (!$details) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        return response()->json($details);
    }

    /**
     * Create new content
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:cms_contents,key',
            'section' => 'required|string',
            'type' => 'required|string|in:text,rich_text,image,json',
            'title' => 'nullable|string',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'image_path' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'string|in:draft,published,scheduled,archived',
            'scheduled_at' => 'nullable|date|after:now',
            'change_summary' => 'nullable|string',
        ]);
        
        $content = $this->cmsService->createContent($validated, auth()->id());
        
        return response()->json([
            'message' => 'Content created successfully',
            'content' => $content->load(['creator', 'updater']),
        ], 201);
    }

    /**
     * Update content
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'section' => 'string',
            'type' => 'string|in:text,rich_text,image,json',
            'title' => 'nullable|string',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'image_path' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'string|in:draft,published,scheduled,archived',
            'scheduled_at' => 'nullable|date|after:now',
            'change_summary' => 'nullable|string',
        ]);
        
        $updatedContent = $this->cmsService->updateContent($key, $validated, auth()->id());
        
        return response()->json([
            'message' => 'Content updated successfully',
            'content' => $updatedContent->load(['creator', 'updater']),
        ]);
    }

    /**
     * Delete content
     */
    public function destroy(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $content->delete();
        
        return response()->json(['message' => 'Content deleted successfully']);
    }

    /**
     * Publish content
     */
    public function publish(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $this->cmsService->publishContent($content, auth()->id());
        
        return response()->json([
            'message' => 'Content published successfully',
            'content' => $content->fresh(),
        ]);
    }

    /**
     * Archive content
     */
    public function archive(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $this->cmsService->archiveContent($content, auth()->id());
        
        return response()->json([
            'message' => 'Content archived successfully',
            'content' => $content->fresh(),
        ]);
    }

    /**
     * Schedule content
     */
    public function schedule(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);
        
        $this->cmsService->scheduleContent(
            $content, 
            new \DateTime($validated['scheduled_at']), 
            auth()->id()
        );
        
        return response()->json([
            'message' => 'Content scheduled successfully',
            'content' => $content->fresh(),
        ]);
    }

    /**
     * Get content versions
     */
    public function versions(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $versions = $this->versioningService->getVersionHistory($content);
        
        return response()->json($versions);
    }

    /**
     * Get content analytics
     */
    public function analytics(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $days = $request->get('days', 30);
        $performance = $this->analyticsService->getContentPerformance($content, $days);
        
        return response()->json($performance);
    }

    /**
     * Revert to a previous version
     */
    public function revertVersion(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'version_number' => 'required|integer|min:1',
        ]);
        
        $success = $this->versioningService->revertToVersion(
            $content, 
            $validated['version_number'], 
            auth()->id()
        );
        
        if (!$success) {
            return response()->json(['message' => 'Version not found'], 404);
        }
        
        return response()->json([
            'message' => 'Content reverted successfully',
            'content' => $content->fresh(),
        ]);
    }

    /**
     * Compare two versions
     */
    public function compareVersions(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'version1' => 'required|integer|min:1',
            'version2' => 'required|integer|min:1',
        ]);
        
        $version1 = $content->versions()->where('version_number', $validated['version1'])->first();
        $version2 = $content->versions()->where('version_number', $validated['version2'])->first();
        
        if (!$version1 || !$version2) {
            return response()->json(['message' => 'One or both versions not found'], 404);
        }
        
        $comparison = $this->versioningService->compareVersions($version1, $version2);
        
        return response()->json($comparison);
    }

    /**
     * Get platform-wide analytics
     */
    public function platformAnalytics(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $analytics = $this->analyticsService->getPlatformAnalytics($days);
        
        return response()->json($analytics);
    }

    /**
     * Get engagement metrics
     */
    public function engagementMetrics(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $metrics = $this->analyticsService->getEngagementMetrics($days);
        
        return response()->json($metrics);
    }

    /**
     * Get user activity analytics
     */
    public function userActivity(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $activity = $this->analyticsService->getUserActivity($days);
        
        return response()->json($activity);
    }

    /**
     * Generate comprehensive analytics report
     */
    public function analyticsReport(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $report = $this->analyticsService->generateReport($days);
        
        return response()->json($report);
    }

    /**
     * Get trending content
     */
    public function trendingContent(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $limit = $request->get('limit', 10);
        $trending = $this->analyticsService->getTrendingContent($days, $limit);
        
        return response()->json($trending);
    }

    /**
     * Bulk update content status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_keys' => 'required|array',
            'content_keys.*' => 'string',
            'status' => 'required|string|in:draft,published,scheduled,archived',
            'scheduled_at' => 'nullable|date|after:now',
        ]);
        
        $updated = 0;
        $errors = [];
        
        foreach ($validated['content_keys'] as $key) {
            $content = CmsContent::where('key', $key)->first();
            
            if (!$content) {
                $errors[] = "Content with key '{$key}' not found";
                continue;
            }
            
            try {
                if ($validated['status'] === 'scheduled' && isset($validated['scheduled_at'])) {
                    $this->cmsService->scheduleContent(
                        $content, 
                        new \DateTime($validated['scheduled_at']), 
                        auth()->id()
                    );
                } elseif ($validated['status'] === 'published') {
                    $this->cmsService->publishContent($content, auth()->id());
                } elseif ($validated['status'] === 'archived') {
                    $this->cmsService->archiveContent($content, auth()->id());
                } else {
                    $content->update([
                        'status' => $validated['status'],
                        'updated_by' => auth()->id(),
                    ]);
                }
                
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to update '{$key}': " . $e->getMessage();
            }
        }
        
        return response()->json([
            'message' => "Updated {$updated} content items",
            'updated_count' => $updated,
            'errors' => $errors,
        ]);
    }
}
