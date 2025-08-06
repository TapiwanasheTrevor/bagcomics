<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Services\ReadingProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReadingProgressController extends Controller
{
    public function __construct(
        private ReadingProgressService $readingProgressService
    ) {}

    /**
     * Update reading progress
     */
    public function updateProgress(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_page' => 'required|integer|min:1',
                'metadata' => 'sometimes|array',
            ]);

            $progress = $this->readingProgressService->updateProgress(
                $request->user(),
                $comic,
                $validated['current_page'],
                $validated['metadata'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'statistics' => $progress->getReadingStatistics(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update progress',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start reading session
     */
    public function startSession(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'metadata' => 'sometimes|array',
            ]);

            $progress = $this->readingProgressService->startReadingSession(
                $request->user(),
                $comic,
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'current_session' => $progress->getCurrentSession(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to start session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End reading session
     */
    public function endSession(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'end_page' => 'required|integer|min:1',
                'metadata' => 'sometimes|array',
            ]);

            $progress = $this->readingProgressService->endReadingSession(
                $request->user(),
                $comic,
                $validated['end_page'],
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'statistics' => $progress->getReadingStatistics(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to end session',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add pause time to current session
     */
    public function addPauseTime(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pause_minutes' => 'required|integer|min:1',
            ]);

            $this->readingProgressService->addPauseTime(
                $request->user(),
                $comic,
                $validated['pause_minutes']
            );

            return response()->json([
                'success' => true,
                'message' => 'Pause time added successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to add pause time',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reading progress
     */
    public function getProgress(Request $request, Comic $comic): JsonResponse
    {
        try {
            $progress = $this->readingProgressService->getProgress($request->user(), $comic);

            if (!$progress) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'progress' => null,
                        'statistics' => $this->readingProgressService->getReadingStatistics($request->user(), $comic),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'statistics' => $progress->getReadingStatistics(),
                    'current_session' => $progress->getCurrentSession(),
                    'has_active_session' => $progress->hasActiveSession(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get progress',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add bookmark
     */
    public function addBookmark(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'required|integer|min:1',
                'note' => 'sometimes|string|max:1000',
            ]);

            $bookmark = $this->readingProgressService->addBookmark(
                $request->user(),
                $comic,
                $validated['page'],
                $validated['note'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'bookmark' => $bookmark,
                    'message' => 'Bookmark added successfully',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to add bookmark',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove bookmark
     */
    public function removeBookmark(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'required|integer|min:1',
            ]);

            $removed = $this->readingProgressService->removeBookmark(
                $request->user(),
                $comic,
                $validated['page']
            );

            if (!$removed) {
                return response()->json([
                    'success' => false,
                    'error' => 'Bookmark not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bookmark removed successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to remove bookmark',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get bookmarks
     */
    public function getBookmarks(Request $request, Comic $comic): JsonResponse
    {
        try {
            $bookmarks = $this->readingProgressService->getBookmarks($request->user(), $comic);

            return response()->json([
                'success' => true,
                'data' => [
                    'bookmarks' => $bookmarks,
                    'count' => $bookmarks->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get bookmarks',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update reading preferences
     */
    public function updatePreferences(Request $request, Comic $comic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'preferences' => 'required|array',
                'preferences.zoom_level' => 'sometimes|numeric|min:0.1|max:5.0',
                'preferences.reading_mode' => 'sometimes|string|in:single_page,double_page,continuous',
                'preferences.background_color' => 'sometimes|string',
                'preferences.auto_advance' => 'sometimes|boolean',
                'preferences.page_transition' => 'sometimes|string|in:slide,fade,none',
            ]);

            $progress = $this->readingProgressService->updateReadingPreferences(
                $request->user(),
                $comic,
                $validated['preferences']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'preferences' => $progress->reading_preferences,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update preferences',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's overall reading statistics
     */
    public function getUserStatistics(Request $request): JsonResponse
    {
        try {
            $statistics = $this->readingProgressService->getUserReadingStatistics($request->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $statistics,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get user statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronize bookmarks
     */
    public function synchronizeBookmarks(Request $request, Comic $comic): JsonResponse
    {
        try {
            $this->readingProgressService->synchronizeBookmarks($request->user(), $comic);

            return response()->json([
                'success' => true,
                'message' => 'Bookmarks synchronized successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to synchronize bookmarks',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}