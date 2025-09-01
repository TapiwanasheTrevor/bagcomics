<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\UserLibrary;
use App\Models\UserComicProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Get recently read comics
        $recentlyRead = $user->library()
            ->with(['comic' => function ($query) {
                $query->select('id', 'slug', 'title', 'author', 'cover_image_url', 'page_count');
            }])
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get()
            ->map(function ($libraryItem) use ($user) {
                $comic = $libraryItem->comic;
                $progress = $user->comicProgress()->where('comic_id', $comic->id)->first();
                
                return [
                    'id' => $comic->id,
                    'slug' => $comic->slug,
                    'title' => $comic->title,
                    'author' => $comic->author,
                    'cover_image_url' => $comic->getCoverImageUrl(),
                    'progress' => $progress ? [
                        'current_page' => $progress->current_page,
                        'total_pages' => $comic->page_count ?? 0,
                        'percentage' => $comic->page_count > 0 
                            ? round(($progress->current_page / $comic->page_count) * 100, 1)
                            : 0
                    ] : null
                ];
            });

        // Get library statistics
        $libraryStats = [
            'total_comics' => $user->library()->count(),
            'completed_comics' => $user->comicProgress()
                ->where('is_completed', true)
                ->count(),
            'in_progress_comics' => $user->comicProgress()
                ->where('is_completed', false)
                ->where('current_page', '>', 0)
                ->count(),
            'favorite_comics' => $user->library()
                ->where('is_favorite', true)
                ->count(),
            'average_rating' => round($user->library()
                ->whereNotNull('rating')
                ->avg('rating') ?? 0, 1),
            'total_reading_time' => $user->comicProgress()
                ->sum('reading_time_minutes') ?? 0
        ];

        // Get reading activity for the last 7 days
        $readingActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayActivity = $user->comicProgress()
                ->whereDate('updated_at', $date)
                ->select(
                    DB::raw('COUNT(DISTINCT comic_id) as comics_read'),
                    DB::raw('SUM(CASE WHEN DATE(updated_at) = ? THEN reading_time_minutes ELSE 0 END) as minutes_read')
                )
                ->setBindings([$date->toDateString()])
                ->first();

            $readingActivity[] = [
                'date' => $date->toDateString(),
                'comics_read' => $dayActivity->comics_read ?? 0,
                'minutes_read' => $dayActivity->minutes_read ?? 0
            ];
        }

        return Inertia::render('dashboard', [
            'dashboard_data' => [
                'recently_read' => $recentlyRead,
                'library_stats' => $libraryStats,
                'reading_activity' => $readingActivity
            ]
        ]);
    }
}