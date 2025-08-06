<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsMediaAsset;
use App\Services\CmsMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CmsMediaController extends Controller
{
    public function __construct(
        protected CmsMediaService $mediaService
    ) {}

    /**
     * Get media library with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'uploader_id', 'search']);
        $perPage = $request->get('per_page', 20);
        
        $media = $this->mediaService->getMediaLibrary($filters, $perPage);
        
        return response()->json($media);
    }

    /**
     * Upload a new media file
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'alt_text' => 'nullable|string|max:255',
        ]);
        
        $options = [
            'alt_text' => $request->get('alt_text'),
        ];
        
        $asset = $this->mediaService->uploadFile(
            $request->file('file'),
            auth()->id(),
            $options
        );
        
        return response()->json([
            'message' => 'File uploaded successfully',
            'asset' => $asset->load('uploader'),
        ], 201);
    }

    /**
     * Get specific media asset
     */
    public function show(CmsMediaAsset $asset): JsonResponse
    {
        return response()->json($asset->load('uploader'));
    }

    /**
     * Update media asset metadata
     */
    public function update(Request $request, CmsMediaAsset $asset): JsonResponse
    {
        $validated = $request->validate([
            'alt_text' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);
        
        $this->mediaService->updateAsset($asset, $validated);
        
        return response()->json([
            'message' => 'Asset updated successfully',
            'asset' => $asset->fresh(),
        ]);
    }

    /**
     * Delete media asset
     */
    public function destroy(CmsMediaAsset $asset): JsonResponse
    {
        $this->mediaService->deleteAsset($asset);
        
        return response()->json([
            'message' => 'Asset deleted successfully',
        ]);
    }

    /**
     * Get storage statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->mediaService->getStorageStats();
        
        return response()->json($stats);
    }

    /**
     * Bulk delete assets
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'integer|exists:cms_media_assets,id',
        ]);
        
        $deleted = 0;
        $errors = [];
        
        foreach ($validated['asset_ids'] as $assetId) {
            $asset = CmsMediaAsset::find($assetId);
            
            if (!$asset) {
                $errors[] = "Asset with ID {$assetId} not found";
                continue;
            }
            
            try {
                $this->mediaService->deleteAsset($asset);
                $deleted++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete asset {$assetId}: " . $e->getMessage();
            }
        }
        
        return response()->json([
            'message' => "Deleted {$deleted} assets",
            'deleted_count' => $deleted,
            'errors' => $errors,
        ]);
    }
}