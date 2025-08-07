<?php

use App\Models\Comic;
use App\Http\Controllers\PdfStreamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Public comic routes
Route::get('/comics', function () {
    return Inertia::render('comics/index');
})->name('comics.index');

Route::get('/library', function () {
    return Inertia::render('library');
})->name('library')->middleware('auth');

Route::get('/comics/{comic:slug}', function (Comic $comic) {
    if (!$comic->is_visible) {
        abort(404);
    }

    $comicData = $comic->load(['userProgress' => function ($query) {
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }
    }])->toArray();

    // Add computed fields
    $comicData['cover_image_url'] = $comic->getCoverImageUrl();
    $comicData['reading_time_estimate'] = $comic->getReadingTimeEstimate();
    $comicData['is_new_release'] = $comic->isNewRelease();

    // Add PDF-related fields - use proper PDF URL method
    if ($comic->is_pdf_comic && $comic->pdf_file_path) {
        $comicData['pdf_stream_url'] = $comic->getPdfUrl();
        $comicData['pdf_download_url'] = $comic->getPdfUrl();
    }

    // Add user-specific data if authenticated
    if (auth()->check()) {
        $comicData['user_has_access'] = auth()->user()->hasAccessToComic($comic);
        $comicData['user_progress'] = $comic->userProgress->first();
    }

    return Inertia::render('comics/show', [
        'comic' => $comicData
    ]);
})->name('comics.show');

// Comic reading route
Route::get('/comics/{comic:slug}/read', function (Comic $comic) {
    if (!$comic->is_visible) {
        abort(404);
    }

    // Check if user has access to this comic
    if (auth()->check() && !auth()->user()->hasAccessToComic($comic)) {
        abort(403, 'You do not have access to this comic. Please purchase it first.');
    } elseif (!auth()->check() && !$comic->is_free) {
        return redirect()->route('login');
    }

    $comicData = $comic->load(['userProgress' => function ($query) {
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }
    }])->toArray();

    // Add computed fields - use proper PDF URL method
    $comicData['cover_image_url'] = $comic->getCoverImageUrl();
    $comicData['pdf_stream_url'] = $comic->getPdfUrl();
    
    return Inertia::render('comics/reader', [
        'comic' => $comicData
    ]);
})->name('comics.read');

// PDF streaming routes
Route::match(['GET', 'OPTIONS'], '/comics/{comic:slug}/stream', [PdfStreamController::class, 'stream'])->name('comics.stream');
Route::get('/comics/{comic:slug}/stream-secure', [PdfStreamController::class, 'streamSecure'])->name('comics.stream.secure');
Route::get('/comics/{comic:slug}/download', [PdfStreamController::class, 'download'])->name('comics.download');

// Test route without model binding
Route::get('/test-simple', function() {
    return 'Simple route works - ' . now();
});

// Debug PDF URL generation
Route::get('/debug-pdf-url', function() {
    $comic = \App\Models\Comic::where('slug', 'ubuntu-tales-community')->first();
    if (!$comic) {
        return response()->json(['error' => 'Comic not found']);
    }
    
    $comicData = $comic->toArray();
    $comicData['pdf_stream_url'] = $comic->getPdfUrl();
    $comicData['pdf_download_url'] = $comic->getPdfUrl();
    
    return response()->json([
        'comic_title' => $comic->title,
        'pdf_file_path_in_db' => $comic->pdf_file_path,
        'getPdfUrl_returns' => $comic->getPdfUrl(),
        'file_exists_public' => file_exists(public_path($comic->pdf_file_path)) ? 'YES' : 'NO',
        'file_exists_storage' => file_exists(storage_path('app/public/' . $comic->pdf_file_path)) ? 'YES' : 'NO',
        'public_path_check' => public_path($comic->pdf_file_path),
        'storage_path_check' => storage_path('app/public/' . $comic->pdf_file_path),
        'asset_public' => asset($comic->pdf_file_path),
        'asset_storage' => asset('storage/' . $comic->pdf_file_path),
        'frontend_data' => [
            'pdf_stream_url' => $comicData['pdf_stream_url'],
            'pdf_download_url' => $comicData['pdf_download_url'],
        ]
    ]);
});

// Fix PDF path route
Route::get('/fix-pdf-path', function() {
    $comic = \App\Models\Comic::where('slug', 'ubuntu-tales-community')->first();
    if (!$comic) {
        return response()->json(['error' => 'Comic not found']);
    }
    
    $before = $comic->pdf_file_path;
    $comic->pdf_file_path = 'sample-comic.pdf';
    $comic->save();
    
    return response()->json([
        'before' => $before,
        'after' => $comic->pdf_file_path,
        'getPdfUrl_now_returns' => $comic->getPdfUrl(),
    ]);
});

// Test streaming directly without model binding  
Route::get('/test-stream/{slug}', function($slug) {
    $comic = \App\Models\Comic::where('slug', $slug)->first();
    if (!$comic) {
        return response()->json(['error' => 'Comic not found'], 404);
    }
    
    $filePath = public_path($comic->pdf_file_path);
    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File not found: ' . $comic->pdf_file_path], 404);
    }
    
    return response()->file($filePath, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline',
    ]);
});

// PDF.js worker route with correct MIME type
Route::get('/js/pdfjs/pdf.worker.min.js', function () {
    $path = public_path('js/pdfjs/pdf.worker.min.js');

    if (!file_exists($path)) {
        abort(404, 'PDF worker not found');
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=86400',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Accept',
    ]);
})->name('pdfjs.worker');

// Stripe webhook (outside auth middleware)
Route::post('/stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');

// Web-based API routes for frontend (using session authentication)
Route::prefix('api')->group(function () {
    // Public comic routes
    Route::prefix('comics')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ComicController::class, 'index']);
        Route::get('/featured', [App\Http\Controllers\Api\ComicController::class, 'featured']);
        Route::get('/new-releases', [App\Http\Controllers\Api\ComicController::class, 'newReleases']);
        Route::get('/genres', [App\Http\Controllers\Api\ComicController::class, 'genres']);
        Route::get('/tags', [App\Http\Controllers\Api\ComicController::class, 'tags']);
        Route::get('/{comic:slug}', [App\Http\Controllers\Api\ComicController::class, 'show']);
        Route::post('/{comic:slug}/view', [App\Http\Controllers\Api\ComicController::class, 'trackView']);
    });

    // Public review routes
    Route::prefix('reviews')->group(function () {
        Route::get('/most-helpful', [App\Http\Controllers\Api\ReviewController::class, 'getMostHelpful']);
        Route::get('/recent', [App\Http\Controllers\Api\ReviewController::class, 'getRecent']);
        Route::get('/comics/{comic:slug}', [App\Http\Controllers\Api\ReviewController::class, 'index']);
        
        // Specific routes must come before parameterized routes
        Route::middleware('auth')->group(function () {
            Route::get('/user', [App\Http\Controllers\Api\ReviewController::class, 'getUserReviews']);
        });
        
        Route::get('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'show']);
    });

    // Protected routes requiring authentication
    Route::middleware('auth')->group(function () {
        // User library routes
        Route::prefix('library')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\UserLibraryController::class, 'index']);
            Route::get('/favorites', [App\Http\Controllers\Api\UserLibraryController::class, 'favorites']);
            Route::get('/recently-added', [App\Http\Controllers\Api\UserLibraryController::class, 'recentlyAdded']);
            Route::post('/comics/{comic:slug}', [App\Http\Controllers\Api\UserLibraryController::class, 'addToLibrary']);
            Route::delete('/comics/{comic:slug}', [App\Http\Controllers\Api\UserLibraryController::class, 'removeFromLibrary']);
            Route::patch('/comics/{comic:slug}/favorite', [App\Http\Controllers\Api\UserLibraryController::class, 'toggleFavorite']);
            Route::patch('/comics/{comic:slug}/rating', [App\Http\Controllers\Api\UserLibraryController::class, 'setRating']);
        });

        // Progress tracking routes
        Route::prefix('progress')->group(function () {
            Route::get('/stats', [App\Http\Controllers\Api\ProgressController::class, 'getReadingStats']);
            Route::get('/recently-read', [App\Http\Controllers\Api\ProgressController::class, 'getRecentlyRead']);
            Route::get('/continue-reading', [App\Http\Controllers\Api\ProgressController::class, 'getContinueReading']);
            Route::get('/comics/{comic:slug}', [App\Http\Controllers\Api\ProgressController::class, 'getProgress']);
            Route::patch('/comics/{comic:slug}', [App\Http\Controllers\Api\ProgressController::class, 'updateProgress']);
            Route::post('/comics/{comic:slug}/bookmarks', [App\Http\Controllers\Api\ProgressController::class, 'addBookmark']);
            Route::delete('/comics/{comic:slug}/bookmarks', [App\Http\Controllers\Api\ProgressController::class, 'removeBookmark']);
        });

        // Payment routes
        Route::prefix('payments')->group(function () {
            // Single comic purchase
            Route::post('/comics/{comic:slug}/intent', [App\Http\Controllers\PaymentController::class, 'createPaymentIntent']);
            
            // Bundle purchase
            Route::post('/bundle/intent', [App\Http\Controllers\PaymentController::class, 'createBundlePaymentIntent']);
            
            // Subscription purchase
            Route::post('/subscription/intent', [App\Http\Controllers\PaymentController::class, 'createSubscriptionPaymentIntent']);
            
            // Payment confirmation and management
            Route::post('/confirm', [App\Http\Controllers\PaymentController::class, 'confirmPayment']);
            Route::get('/{payment}/status', [App\Http\Controllers\PaymentController::class, 'getPaymentStatus']);
            Route::get('/history', [App\Http\Controllers\PaymentController::class, 'getPaymentHistory']);
            
            // Refunds and retries
            Route::post('/{payment}/refund', [App\Http\Controllers\PaymentController::class, 'requestRefund']);
            Route::post('/{payment}/retry', [App\Http\Controllers\PaymentController::class, 'retryPayment']);
            
            // Receipts and invoices
            Route::get('/{payment}/receipt', [App\Http\Controllers\PaymentController::class, 'downloadReceipt'])->name('payments.receipt');
            Route::get('/{payment}/invoice', [App\Http\Controllers\PaymentController::class, 'getInvoice']);
        });

        // Protected review routes
        Route::prefix('reviews')->group(function () {
            // Comic-specific reviews
            Route::post('/comics/{comic:slug}', [App\Http\Controllers\Api\ReviewController::class, 'store']);
            Route::get('/comics/{comic:slug}/user', [App\Http\Controllers\Api\ReviewController::class, 'getUserReview']);
            
            // Review voting (must come before {review} routes to avoid conflicts)
            Route::post('/{review}/vote', [App\Http\Controllers\Api\ReviewController::class, 'vote']);
            Route::delete('/{review}/vote', [App\Http\Controllers\Api\ReviewController::class, 'removeVote']);
            
            // Individual review management (must come last due to {review} parameter)
            Route::put('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'update']);
            Route::delete('/{review}', [App\Http\Controllers\Api\ReviewController::class, 'destroy']);
        });

        // Admin review moderation routes
        Route::prefix('admin/reviews')->middleware('can:access-admin')->group(function () {
            Route::get('/moderation', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'index']);
            Route::get('/statistics', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'statistics']);
            Route::post('/{review}/approve', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'approve']);
            Route::post('/{review}/reject', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'reject']);
            Route::post('/bulk-approve', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'bulkApprove']);
            Route::post('/bulk-reject', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'bulkReject']);
            Route::delete('/{review}', [App\Http\Controllers\Api\Admin\ReviewModerationController::class, 'destroy']);
        });

        // Admin payment analytics routes
        Route::prefix('admin/payments')->middleware('can:access-admin')->group(function () {
            Route::get('/analytics', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'getDashboardAnalytics']);
            Route::get('/revenue-trends', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'getRevenueTrends']);
            Route::get('/payment-methods', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'getPaymentMethodBreakdown']);
            Route::get('/failed-analysis', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'getFailedPaymentAnalysis']);
            Route::get('/refund-analytics', [App\Http\Controllers\Api\Admin\PaymentAnalyticsController::class, 'getRefundAnalytics']);
        });

        // Analytics routes (admin only)
        Route::prefix('analytics')->middleware('can:access-admin')->group(function () {
            Route::get('/platform-metrics', [App\Http\Controllers\Api\AnalyticsController::class, 'platformMetrics']);
            Route::get('/revenue', [App\Http\Controllers\Api\AnalyticsController::class, 'revenueAnalytics']);
            Route::get('/user-engagement', [App\Http\Controllers\Api\AnalyticsController::class, 'userEngagement']);
            Route::get('/comic-performance', [App\Http\Controllers\Api\AnalyticsController::class, 'comicPerformance']);
            Route::get('/conversion', [App\Http\Controllers\Api\AnalyticsController::class, 'conversionAnalytics']);
            Route::get('/popular-comics', [App\Http\Controllers\Api\AnalyticsController::class, 'popularComics']);
            Route::get('/trending-comics', [App\Http\Controllers\Api\AnalyticsController::class, 'trendingComics']);
        });
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Debug route to expose logs
Route::get('/debug-log', function () {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        return response()->file($logFile);
    }
    return response('Log file not found', 404);
});

Route::post('/test-upload', function (\Illuminate\Http\Request $request) {
    if ($request->hasFile('file')) {
        return $request->file('file')->store('test-uploads');
    }
    return 'No file uploaded';
});

// Debug routes for testing CSRF and upload functionality (only in non-production)
if (!app()->environment('production')) {
    // Route to bypass ServiceWorker for development
    Route::get('/dev-assets/{path}', function ($path) {
        $assetPath = public_path('build/assets/' . $path);
        if (file_exists($assetPath)) {
            $mimeType = match(pathinfo($path, PATHINFO_EXTENSION)) {
                'js' => 'application/javascript',
                'css' => 'text/css',
                'json' => 'application/json',
                default => 'text/plain'
            };
            
            return response()->file($assetPath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            ]);
        }
        abort(404);
    })->where('path', '.*');
    Route::post('/debug-upload-test', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'csrf_token' => csrf_token(),
            'session_id' => session()->getId(),
            'headers' => [
                'x-csrf-token' => $request->header('X-CSRF-TOKEN'),
                'x-xsrf-token' => $request->header('X-XSRF-TOKEN'),
                'cookie_xsrf' => $request->cookie('XSRF-TOKEN'),
                'cookie_session' => $request->cookie(config('session.cookie')),
            ],
            'is_secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'url' => $request->url(),
            'session_driver' => config('session.driver'),
            'session_cookie' => config('session.cookie'),
            'session_secure' => config('session.secure'),
            'session_same_site' => config('session.same_site'),
        ]);
    })->middleware(['web']);

    Route::post('/debug-livewire-upload', function (\Illuminate\Http\Request $request) {
        \Log::info('Livewire Upload Debug', [
            'headers' => $request->headers->all(),
            'cookies' => $request->cookies->all(),
            'csrf_token' => csrf_token(),
            'session_token' => session()->token(),
            'request_token' => $request->input('_token'),
            'header_csrf' => $request->header('X-CSRF-TOKEN'),
        ]);
        
        return response()->json(['status' => 'debug logged']);
    })->name('debug.livewire.upload');
}

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
