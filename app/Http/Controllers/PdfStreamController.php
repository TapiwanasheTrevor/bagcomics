<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfStreamController extends Controller
{
    public function stream(Comic $comic, Request $request)
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization, X-Requested-With, Cache-Control',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

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

        // Get file info
        $fileSize = filesize($fullPath);
        $fileName = $comic->pdf_file_name ?: basename($filePath);

        // Enhanced headers for better PDF.js compatibility
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',

            // CORS headers
            'Access-Control-Allow-Origin' => '*',
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
