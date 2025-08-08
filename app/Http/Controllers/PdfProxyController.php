<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PdfProxyController extends Controller
{
    /**
     * Serve PDF files with proper CORS headers
     */
    public function servePdf(Request $request, string $path)
    {
        // Sanitize the path to prevent directory traversal
        $path = trim($path, '/');
        
        // Ensure the path starts with comics/ for security
        if (!str_starts_with($path, 'comics/')) {
            abort(404);
        }
        
        $filePath = storage_path('app/public/' . $path);
        
        // Check if file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            // Log the missing file for debugging
            \Log::warning('PDF file not found', [
                'requested_path' => $path,
                'full_path' => $filePath,
                'storage_path' => storage_path('app/public'),
                'files_in_comics_dir' => is_dir(storage_path('app/public/comics')) ? 
                    scandir(storage_path('app/public/comics')) : 'Directory does not exist'
            ]);
            
            abort(404, 'PDF file not found: ' . $path);
        }
        
        // Verify it's actually a PDF
        $mimeType = mime_content_type($filePath);
        if ($mimeType !== 'application/pdf') {
            abort(404);
        }
        
        // Get file info
        $fileSize = filesize($filePath);
        $fileName = basename($filePath);
        
        // Set CORS headers
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => $fileSize,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token, Authorization, Accept, Cache-Control',
        ];
        
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200, $headers);
        }
        
        // Serve the file
        return response()->file($filePath, $headers);
    }
}