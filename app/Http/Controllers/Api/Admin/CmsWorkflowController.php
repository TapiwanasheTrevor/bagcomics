<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsContent;
use App\Models\CmsContentVersion;
use App\Services\CmsService;
use App\Services\CmsVersioningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CmsWorkflowController extends Controller
{
    public function __construct(
        protected CmsService $cmsService,
        protected CmsVersioningService $versioningService
    ) {}

    /**
     * Get workflow dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $draftCount = CmsContent::draft()->count();
        $scheduledCount = CmsContent::scheduled()->count();
        $publishedCount = CmsContent::published()->count();
        $archivedCount = CmsContent::where('status', 'archived')->count();
        
        $recentActivity = CmsContent::with(['creator', 'updater'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
        
        $scheduledContent = CmsContent::scheduled()
            ->with(['creator'])
            ->orderBy('scheduled_at', 'asc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'counts' => [
                'draft' => $draftCount,
                'scheduled' => $scheduledCount,
                'published' => $publishedCount,
                'archived' => $archivedCount,
                'total' => $draftCount + $scheduledCount + $publishedCount + $archivedCount,
            ],
            'recent_activity' => $recentActivity,
            'scheduled_content' => $scheduledContent,
        ]);
    }

    /**
     * Get content by workflow status
     */
    public function contentByStatus(Request $request, string $status): JsonResponse
    {
        $validStatuses = ['draft', 'published', 'scheduled', 'archived'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json(['message' => 'Invalid status'], 400);
        }
        
        $query = CmsContent::with(['creator', 'updater'])
            ->where('status', $status);
        
        if ($status === 'scheduled') {
            $query->orderBy('scheduled_at', 'asc');
        } else {
            $query->orderBy('updated_at', 'desc');
        }
        
        $content = $query->paginate(20);
        
        return response()->json($content);
    }

    /**
     * Create a new version of content
     */
    public function createVersion(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'title' => 'nullable|string',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'image_path' => 'nullable|string',
            'status' => 'string|in:draft,published,scheduled',
            'scheduled_at' => 'nullable|date|after:now',
            'change_summary' => 'nullable|string',
        ]);
        
        $version = $this->versioningService->createVersion($content, $validated, auth()->id());
        
        return response()->json([
            'message' => 'Version created successfully',
            'version' => $version->load('creator'),
        ], 201);
    }

    /**
     * Publish a specific version
     */
    public function publishVersion(CmsContentVersion $version): JsonResponse
    {
        $success = $this->versioningService->publishVersion($version, auth()->id());
        
        if (!$success) {
            return response()->json(['message' => 'Failed to publish version'], 500);
        }
        
        return response()->json([
            'message' => 'Version published successfully',
            'version' => $version->fresh(),
            'content' => $version->cmsContent->fresh(),
        ]);
    }

    /**
     * Schedule a specific version
     */
    public function scheduleVersion(Request $request, CmsContentVersion $version): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);
        
        $success = $this->versioningService->scheduleVersion(
            $version,
            new \DateTime($validated['scheduled_at']),
            auth()->id()
        );
        
        if (!$success) {
            return response()->json(['message' => 'Failed to schedule version'], 500);
        }
        
        return response()->json([
            'message' => 'Version scheduled successfully',
            'version' => $version->fresh(),
        ]);
    }

    /**
     * Get content ready for publishing
     */
    public function readyToPublish(): JsonResponse
    {
        $readyContent = CmsContent::scheduled()
            ->where('scheduled_at', '<=', now())
            ->with(['creator'])
            ->orderBy('scheduled_at', 'asc')
            ->get();
        
        $readyVersions = CmsContentVersion::scheduled()
            ->where('scheduled_at', '<=', now())
            ->with(['cmsContent', 'creator'])
            ->orderBy('scheduled_at', 'asc')
            ->get();
        
        return response()->json([
            'ready_content' => $readyContent,
            'ready_versions' => $readyVersions,
            'total_ready' => $readyContent->count() + $readyVersions->count(),
        ]);
    }

    /**
     * Process all scheduled content
     */
    public function processScheduled(): JsonResponse
    {
        $published = $this->cmsService->processScheduledContent();
        
        return response()->json([
            'message' => "Processed {$published} scheduled items",
            'published_count' => $published,
        ]);
    }

    /**
     * Get workflow history for content
     */
    public function workflowHistory(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        // Get analytics events related to workflow
        $workflowEvents = $content->analytics()
            ->whereIn('event_type', ['edit', 'publish', 'schedule', 'archive', 'version_created', 'version_published'])
            ->orderBy('occurred_at', 'desc')
            ->get();
        
        // Get versions
        $versions = $this->versioningService->getVersionHistory($content);
        
        return response()->json([
            'content' => $content,
            'workflow_events' => $workflowEvents,
            'versions' => $versions,
        ]);
    }

    /**
     * Approve content for publishing
     */
    public function approveContent(string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        if ($content->status !== 'draft') {
            return response()->json(['message' => 'Only draft content can be approved'], 400);
        }
        
        $this->cmsService->publishContent($content, auth()->id());
        
        return response()->json([
            'message' => 'Content approved and published',
            'content' => $content->fresh(),
        ]);
    }

    /**
     * Reject content and send back to draft
     */
    public function rejectContent(Request $request, string $key): JsonResponse
    {
        $content = CmsContent::where('key', $key)->first();
        
        if (!$content) {
            return response()->json(['message' => 'Content not found'], 404);
        }
        
        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);
        
        $content->update([
            'status' => 'draft',
            'updated_by' => auth()->id(),
        ]);
        
        // Track rejection event
        $content->trackEvent('reject', [
            'user_id' => auth()->id(),
            'reason' => $validated['reason'] ?? null,
        ]);
        
        return response()->json([
            'message' => 'Content rejected and moved to draft',
            'content' => $content->fresh(),
        ]);
    }
}