<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comic;
use App\Models\UserComicProgress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{

    public function updateProgress(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'current_page' => 'required|integer|min:1',
            'total_pages' => 'nullable|integer|min:1',
            'reading_time_minutes' => 'nullable|integer|min:0',
        ]);

        if (!$request->user()->hasAccessToComic($comic)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $progress = UserComicProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'comic_id' => $comic->id,
            ],
            [
                'reading_time_minutes' => $request->get('reading_time_minutes', 0),
            ]
        );

        $progress->updateProgress(
            $request->current_page,
            $request->total_pages
        );

        return response()->json([
            'message' => 'Progress updated successfully',
            'progress' => $progress
        ]);
    }

    public function getProgress(Request $request, Comic $comic): JsonResponse
    {
        $progress = $request->user()->getProgressForComic($comic);

        if (!$progress) {
            return response()->json([
                'current_page' => 1,
                'total_pages' => null,
                'progress_percentage' => 0,
                'is_completed' => false,
                'is_bookmarked' => false,
                'reading_time_minutes' => 0,
                'last_read_at' => null,
                'completed_at' => null,
                'bookmarks' => []
            ]);
        }

        return response()->json($progress);
    }

    public function addBookmark(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'page' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        if (!$request->user()->hasAccessToComic($comic)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $progress = UserComicProgress::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'comic_id' => $comic->id,
            ]
        );

        $progress->addBookmark($request->page, $request->note);

        return response()->json([
            'message' => 'Bookmark added successfully',
            'bookmarks' => $progress->bookmarks
        ]);
    }

    public function removeBookmark(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'bookmark_index' => 'required|integer|min:0',
        ]);

        $progress = $request->user()->getProgressForComic($comic);

        if (!$progress) {
            return response()->json(['message' => 'No progress found'], 404);
        }

        $bookmarks = $progress->bookmarks ?? [];
        $index = $request->bookmark_index;

        if (!isset($bookmarks[$index])) {
            return response()->json(['message' => 'Bookmark not found'], 404);
        }

        unset($bookmarks[$index]);
        $progress->bookmarks = array_values($bookmarks);
        $progress->is_bookmarked = count($progress->bookmarks) > 0;
        $progress->save();

        return response()->json([
            'message' => 'Bookmark removed successfully',
            'bookmarks' => $progress->bookmarks
        ]);
    }

    public function getReadingStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'total_comics_read' => $user->comicProgress()->where('is_completed', true)->count(),
            'total_comics_in_progress' => $user->comicProgress()->where('is_completed', false)->where('current_page', '>', 1)->count(),
            'total_reading_time_minutes' => $user->comicProgress()->sum('reading_time_minutes'),
            'total_bookmarks' => $user->comicProgress()->where('is_bookmarked', true)->count(),
            'favorite_genres' => $this->getFavoriteGenres($user),
            'reading_streak_days' => $this->getReadingStreak($user),
        ];

        return response()->json($stats);
    }

    public function getRecentlyRead(Request $request): JsonResponse
    {
        $recentlyRead = $request->user()->comicProgress()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->whereNotNull('last_read_at')
            ->orderBy('last_read_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json($recentlyRead);
    }

    public function getContinueReading(Request $request): JsonResponse
    {
        $continueReading = $request->user()->comicProgress()
            ->with(['comic' => function ($query) {
                $query->where('is_visible', true);
            }])
            ->where('is_completed', false)
            ->where('current_page', '>', 1)
            ->orderBy('last_read_at', 'desc')
            ->limit(6)
            ->get();

        return response()->json($continueReading);
    }

    private function getFavoriteGenres($user): array
    {
        return $user->library()
            ->join('comics', 'user_libraries.comic_id', '=', 'comics.id')
            ->whereNotNull('comics.genre')
            ->groupBy('comics.genre')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(5)
            ->pluck('comics.genre')
            ->toArray();
    }

    private function getReadingStreak($user): int
    {
        $progressEntries = $user->comicProgress()
            ->whereNotNull('last_read_at')
            ->orderBy('last_read_at', 'desc')
            ->pluck('last_read_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values();

        if ($progressEntries->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $currentDate = now()->format('Y-m-d');

        foreach ($progressEntries as $readDate) {
            if ($readDate === $currentDate) {
                $streak++;
                $currentDate = now()->subDays($streak)->format('Y-m-d');
            } else {
                break;
            }
        }

        return $streak;
    }
}
