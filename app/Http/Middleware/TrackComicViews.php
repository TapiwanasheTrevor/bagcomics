<?php

namespace App\Http\Middleware;

use App\Models\Comic;
use App\Models\ComicView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackComicViews
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track views for successful GET requests to comic pages
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $this->trackComicView($request);
        }

        return $response;
    }

    private function trackComicView(Request $request): void
    {
        // Check if this is a comic-related route
        $route = $request->route();
        if (!$route) {
            return;
        }

        $comic = null;

        // Try to get comic from route parameters
        if ($route->hasParameter('comic')) {
            $comic = $route->parameter('comic');
            if (!$comic instanceof Comic) {
                // If it's a slug, try to find the comic
                $comic = Comic::where('slug', $comic)->first();
            }
        }

        // If we found a comic, track the view
        if ($comic instanceof Comic) {
            ComicView::recordView(
                comic: $comic,
                user: $request->user(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionId: $request->session()->getId()
            );
        }
    }
}
