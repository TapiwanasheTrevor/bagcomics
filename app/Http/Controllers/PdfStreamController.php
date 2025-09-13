<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfStreamController extends Controller
{
    public function stream(Comic $comic, Request $request)
    {
        Log::info('PdfStreamController: Stream method initiated.', ['comic_slug' => $comic->slug]);

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            Log::info('PdfStreamController: Handling OPTIONS preflight request.');
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*', // Allow any origin for OPTIONS
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization, X-Requested-With, Cache-Control, Range',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        // Check if user has access to this comic
        if (!$comic->is_free && !auth()->check()) {
            Log::warning('PdfStreamController: Authentication required.', ['comic_id' => $comic->id]);
            abort(401, 'Authentication required');
        }

        // Check if comic has a PDF file
        if (!$comic->pdf_file_path) {
            Log::error('PdfStreamController: PDF file path is missing for comic.', ['comic_id' => $comic->id]);
            abort(404, 'PDF file not found');
        }

        $filePath = $comic->pdf_file_path;
        Log::info('PdfStreamController: PDF file path from model.', ['path' => $filePath]);

        $storagePath = Storage::disk('public')->path($filePath);
        $publicPath = public_path($filePath);

        // Check if file exists in storage or public directory
        if (Storage::disk('public')->exists($filePath)) {
            $fullPath = $storagePath;
            Log::info('PdfStreamController: File found in public storage.', ['full_path' => $fullPath]);
        } elseif (file_exists($publicPath)) {
            $fullPath = $publicPath;
            Log::info('PdfStreamController: File found in public directory.', ['full_path' => $fullPath]);
        } else {
            Log::error('PdfStreamController: PDF file not found in any checked location.', [
                'comic_id' => $comic->id,
                'checked_storage_path' => $storagePath,
                'checked_public_path' => $publicPath,
            ]);
            abort(404, 'PDF file not found');
        }

        // Get file info
        $fileSize = filesize($fullPath);
        $fileName = $comic->pdf_file_name ?: basename($filePath);

        // Determine the allowed origin
        $origin = $request->header('Origin') ?? config('app.url');
        $allowedOrigin = config('app.url'); // Default to APP_URL

        // In local dev, the request can come from Vite's dev server (e.g., localhost:5173)
        if (app()->environment('local')) {
            // A more robust solution would be a config array of allowed origins
            if (preg_match('~^http://(localhost|127\.0\.0\.1):[0-9]+$~', $origin)) {
                $allowedOrigin = $origin;
            }
        }

        // Enhanced headers for better PDF.js compatibility
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',

            // CORS headers
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization, X-Requested-With, Cache-Control, Range',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',

            // PDF.js specific headers
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'Cross-Origin-Embedder-Policy' => 'unsafe-none',
        ];

        // Handle range requests for better streaming
        if ($request->hasHeader('Range')) {
            return $this->handleRangeRequest($fullPath, $request, $headers);
        }

        return response()->file($fullPath, $headers);
    }

    private function handleRangeRequest($filePath, Request $request, array $headers)
    {
        $fileSize = filesize($filePath);
        $range = $request->header('Range');

        // Parse range header
        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = $matches[2] ? intval($matches[2]) : $fileSize - 1;

            if ($start > $end || $start >= $fileSize) {
                return response('', 416, [
                    'Content-Range' => "bytes */$fileSize"
                ] + $headers);
            }

            $length = $end - $start + 1;

            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            $headers['Content-Length'] = $length;

            $file = fopen($filePath, 'rb');
            fseek($file, $start);
            $data = fread($file, $length);
            fclose($file);

            return response($data, 206, $headers);
        }

        return response()->file($filePath, $headers);
    }

    public function download(Comic $comic)
    {
        // Check if user has access to this comic
        if (!$comic->is_free && !auth()->check()) {
            abort(401, 'Authentication required');
        }

        // Check if comic has a PDF file
        if (!$comic->pdf_file_path) {
            abort(404, 'PDF file not found');
        }

        $filePath = $comic->pdf_file_path;

        // Check if file exists in storage or public directory
        if (Storage::disk('public')->exists($filePath)) {
            $fullPath = Storage::disk('public')->path($filePath);
        } elseif (file_exists(public_path($filePath))) {
            $fullPath = public_path($filePath);
        } else {
            abort(404, 'PDF file not found');
        }

        $fileName = $comic->pdf_file_name ?: ($comic->title . '.pdf');

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function streamSecure(Comic $comic, Request $request)
    {
        // Check if user has access to this comic
        if (!$comic->is_free && !auth()->check()) {
            abort(401, 'Authentication required');
        }

        // Additional access control - check if user has purchased/has access
        if (auth()->check() && !auth()->user()->hasAccessToComic($comic)) {
            abort(403, 'Access denied');
        }

        // Check if comic has a PDF file
        if (!$comic->pdf_file_path) {
            abort(404, 'PDF file not found');
        }

        $filePath = $comic->pdf_file_path;

        // Check if file exists in storage or public directory
        if (Storage::disk('public')->exists($filePath)) {
            $fullPath = Storage::disk('public')->path($filePath);
        } elseif (file_exists(public_path($filePath))) {
            $fullPath = public_path($filePath);
        } else {
            abort(404, 'PDF file not found');
        }

        // Add security headers to prevent caching and downloading
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($comic->pdf_file_name ?: basename($filePath)) . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'self'; script-src 'none'; object-src 'none';",
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet, noimageindex',
        ];

        // Add watermark headers for additional protection
        $headers['X-Watermark'] = 'Protected Content - ' . (auth()->user()->email ?? 'Anonymous');
        $headers['X-Access-Time'] = now()->toISOString();

        return response()->file($fullPath, $headers);
    }
}
