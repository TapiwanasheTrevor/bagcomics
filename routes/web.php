<?php

use App\Models\Comic;
use App\Http\Controllers\PdfStreamController;
use App\Http\Controllers\PdfProxyController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

// New Frontend - Serve React SPA at root
Route::get('/', function () {
    return File::get(public_path('frontend/dist/index.html'));
})->name('home');

// Public comic routes
Route::get('/comics', function () {
    return Inertia::render('comics/index');
})->name('comics.index');

Route::get('/library', function () {
    return Inertia::render('library');
})->name('library')->middleware('auth');

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware('auth');

Route::get('/trending', [App\Http\Controllers\TrendingController::class, 'index'])
    ->name('trending');

Route::get('/discover', [App\Http\Controllers\DiscoverController::class, 'index'])
    ->name('discover');

Route::get('/achievements', function () {
    return Inertia::render('achievements');
})->name('achievements')->middleware('auth');

Route::get('/comics/{comic:slug}', function (Comic $comic) {
    if (!$comic->is_visible) {
        abort(404);
    }

    $comicData = $comic->load([
        'approvedReviews' => function ($query) {
            $query->select('comic_id', 'rating');
        },
        'userProgress' => function ($query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }
        }
    ])->toArray();

    // Add computed fields
    $comicData['cover_image_url'] = $comic->getCoverImageUrl();
    $comicData['reading_time_estimate'] = $comic->getReadingTimeEstimate();
    $comicData['is_new_release'] = $comic->isNewRelease();
    $comicData['average_rating'] = $comic->getCalculatedAverageRating();
    $comicData['total_ratings'] = $comic->getCalculatedTotalRatings();

    // Add PDF-related fields - use proper PDF URL method
    if ($comic->is_pdf_comic && $comic->pdf_file_path) {
        $comicData['pdf_stream_url'] = $comic->getPdfStreamUrl();
        $comicData['pdf_download_url'] = route('comics.download', $comic->slug);
    }

    // Add user-specific data if authenticated
    if (auth()->check()) {
        $comicData['user_has_access'] = auth()->user()->hasAccessToComic($comic);
        $comicData['user_progress'] = $comic->userProgress->first();
    }

    // Prepare sharing data for server-side meta tags
    $shareData = [
        'title' => $comic->title . ' - BagComics',
        'description' => $comic->description ?: "Discover \"{$comic->title}\" by " . ($comic->author ?: 'Unknown Author') . ". Read this amazing comic now on BagComics!",
        'image' => $comic->getCoverImageUrl() ? url($comic->getCoverImageUrl()) : null,
        'url' => url("/comics/{$comic->slug}"),
        'type' => 'article',
    ];

    return Inertia::render('comics/show', [
        'comic' => $comicData,
        'shareData' => $shareData
    ])->withViewData([
        'shareData' => $shareData
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
        return redirect()->guest(route('login'));
    }

    $comicData = $comic->load(['userProgress' => function ($query) {
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }
    }])->toArray();

    // Add computed fields - use proper PDF URL method
    $comicData['cover_image_url'] = $comic->getCoverImageUrl();
    $comicData['pdf_stream_url'] = $comic->getPdfStreamUrl();
    
    return Inertia::render('comics/reader', [
        'comic' => $comicData
    ]);
})->name('comics.read');

// PDF streaming routes
Route::match(['GET', 'OPTIONS'], '/comics/{comic:slug}/stream', [PdfStreamController::class, 'stream'])->name('comics.stream');
Route::get('/comics/{comic:slug}/stream-secure', [PdfStreamController::class, 'streamSecure'])->name('comics.stream.secure');
Route::get('/comics/{comic:slug}/download', [PdfStreamController::class, 'download'])->name('comics.download');

// PDF proxy route with CORS headers
Route::match(['GET', 'HEAD', 'OPTIONS'], '/pdf-proxy/{path}', [PdfProxyController::class, 'servePdf'])
    ->where('path', '.*')
    ->name('pdf.proxy');

// Debug routes for troubleshooting
Route::get('/debug-logs', function() {
    if (!auth()->check()) {
        return response()->json([
            'error' => 'Not authenticated',
            'login_url' => route('login')
        ]);
    }
    
    $user = auth()->user();
    
    return response()->json([
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'created_at' => $user->created_at,
        ],
        'admin_config' => [
            'admin_emails_env' => env('ADMIN_EMAILS', 'Not set'),
            'app_environment' => app()->environment(),
            'auth_model' => env('AUTH_MODEL', 'Not set'),
        ],
        'panel_access' => [
            'can_access_admin_panel' => $user->canAccessPanel(app(\Filament\Panel::class)),
            'has_admin_access_helper' => method_exists($user, 'hasAdminAccess') ? $user->hasAdminAccess() : 'Method not found',
            'implements_filament_user' => $user instanceof \Filament\Models\Contracts\FilamentUser,
        ],
        'session' => [
            'session_driver' => config('session.driver'),
            'session_domain' => config('session.domain'),
        ],
        'app_config' => [
            'app_url' => config('app.url'),
            'app_debug' => config('app.debug'),
        ]
    ], 200, [], JSON_PRETTY_PRINT);
});

Route::get('/debug-admin-check', function() {
    if (!auth()->check()) {
        return 'Please log in first: <a href="' . route('login') . '">Login</a>';
    }
    
    $user = auth()->user();
    $adminEmails = explode(',', env('ADMIN_EMAILS', ''));
    
    $output = [
        'User Email: ' . $user->email,
        'Is Admin Field: ' . ($user->is_admin ? 'Yes' : 'No'),
        'Admin Emails Env: ' . env('ADMIN_EMAILS', 'Not set'),
        'Admin Emails Array: ' . json_encode($adminEmails),
        'Email in Admin List: ' . (in_array($user->email, $adminEmails) ? 'Yes' : 'No'),
        'App Environment: ' . app()->environment(),
        'Can Access Panel: ' . ($user->canAccessPanel(app(\Filament\Panel::class)) ? 'Yes' : 'No'),
        'Has Admin Access (Helper): ' . ($user->hasAdminAccess() ? 'Yes' : 'No'),
        '',
        'Admin Panel URL: <a href="/admin">/admin</a>',
    ];
    
    return '<pre>' . implode("\n", $output) . '</pre>';
});

Route::get('/debug-recent-logs', function() {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return 'Unauthorized - Admin access required';
    }
    
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) {
        return 'Log file not found';
    }
    
    // Get last 50 lines of the log file
    $lines = array_slice(file($logPath), -100);
    $content = implode('', $lines);
    
    return '<pre style="font-size: 12px; background: #f5f5f5; padding: 20px;">' . 
           htmlspecialchars($content) . 
           '</pre>';
});

Route::get('/debug-storage', function() {
    if (!auth()->check()) {
        return 'Authentication required';
    }
    
    $storageInfo = [
        'storage_app_public_path' => storage_path('app/public'),
        'storage_app_public_exists' => is_dir(storage_path('app/public')),
        'public_storage_path' => public_path('storage'),
        'public_storage_exists' => is_link(public_path('storage')) || is_dir(public_path('storage')),
        'storage_link_target' => is_link(public_path('storage')) ? readlink(public_path('storage')) : 'Not a symlink',
        'comics_dir_exists' => is_dir(storage_path('app/public/comics')),
        'comics_files' => is_dir(storage_path('app/public/comics')) ? scandir(storage_path('app/public/comics')) : 'Directory does not exist',
    ];
    
    // Check database comics
    $comics = \App\Models\Comic::whereNotNull('pdf_file_path')->get(['id', 'title', 'slug', 'pdf_file_path']);
    
    $comicsInfo = $comics->map(function($comic) {
        $filePath = storage_path('app/public/' . $comic->pdf_file_path);
        return [
            'id' => $comic->id,
            'title' => $comic->title,
            'slug' => $comic->slug,
            'pdf_file_path' => $comic->pdf_file_path,
            'full_file_path' => $filePath,
            'file_exists' => file_exists($filePath),
            'file_size' => file_exists($filePath) ? filesize($filePath) : 'File not found',
            'pdf_url' => $comic->getPdfUrl(),
        ];
    });
    
    return response()->json([
        'storage_info' => $storageInfo,
        'comics_with_pdfs' => $comicsInfo,
        'timestamp' => now()
    ], 200, [], JSON_PRETTY_PRINT);
});

// Test route without model binding
Route::get('/test-simple', function() {
    return 'Simple route works - ' . now();
});

// Debug PDF URL generation  
Route::get('/debug-pdf-url', function() {
    $comic = \App\Models\Comic::where('slug', 'ubuntu-tales-community')->first();
    if (!$comic) {
        return response()->json(['error' => 'Comic not found - run /reseed-sample-comic first']);
    }
    
    return response()->json([
        'comic_title' => $comic->title,
        'pdf_file_path' => $comic->pdf_file_path,
        'getPdfUrl' => $comic->getPdfUrl(),
        'file_exists_public' => file_exists(public_path($comic->pdf_file_path)) ? 'YES' : 'NO',
        'public_path' => public_path($comic->pdf_file_path),
        'direct_url_test' => 'https://bagcomics.onrender.com/' . $comic->pdf_file_path,
    ]);
});

// Reseed with clean sample comic
Route::get('/reseed-sample-comic', function() {
    try {
        // Run the sample comic seeder
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SampleComicSeeder']);
        
        $comic = \App\Models\Comic::where('slug', 'ubuntu-tales-community')->first();
        
        if (!$comic) {
            return response()->json([
                'success' => false,
                'error' => 'Comic was not created by seeder',
                'total_comics_after_seed' => \App\Models\Comic::count(),
                'all_comics' => \App\Models\Comic::select('id', 'title', 'slug')->get()
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Sample comic reseeded successfully',
            'comic' => [
                'title' => $comic->title,
                'slug' => $comic->slug,
                'pdf_file_path' => $comic->pdf_file_path,
                'getPdfUrl' => $comic->getPdfUrl(),
                'file_exists' => file_exists(public_path($comic->pdf_file_path)) ? 'YES' : 'NO'
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Simple direct comic creation
Route::get('/create-sample-comic', function() {
    try {
        // Clear existing comics
        \App\Models\Comic::query()->delete();
        
        // Create sample comic directly
        $comic = new \App\Models\Comic();
        $comic->title = 'Ubuntu Tales: Community Stories';
        $comic->slug = 'ubuntu-tales-community';
        $comic->author = 'Community Contributors';
        $comic->genre = 'sci-fi';
        $comic->description = 'A sample comic for testing.';
        $comic->page_count = 20;
        $comic->language = 'en';
        $comic->pdf_file_path = 'sample-comic.pdf';
        $comic->pdf_file_name = 'sample-comic.pdf';
        $comic->is_pdf_comic = true;
        $comic->is_free = true;
        $comic->is_visible = true;
        $comic->published_at = now();
        $comic->tags = ['ubuntu', 'community'];
        $comic->average_rating = 4.5;
        $comic->save();
        
        return response()->json([
            'success' => true,
            'comic' => [
                'id' => $comic->id,
                'title' => $comic->title,
                'slug' => $comic->slug,
                'pdf_file_path' => $comic->pdf_file_path,
                'getPdfUrl' => $comic->getPdfUrl(),
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Add is_admin column and make user admin
Route::get('/make-admin/{email?}', function($email = null) {
    try {
        // First, add the is_admin column if it doesn't exist
        if (!\Schema::hasColumn('users', 'is_admin')) {
            \Schema::table('users', function ($table) {
                $table->boolean('is_admin')->default(false);
            });
        }
        
        $email = $email ?: 'admin@bagcomics.com'; // Default admin email
        
        $user = \App\Models\User::where('email', $email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found with email: ' . $email,
                'suggestion' => 'Try: /make-admin/your-actual-email@domain.com',
                'existing_users' => \App\Models\User::select('email')->take(5)->get()
            ]);
        }
        
        // Set user as admin
        $user->is_admin = true;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'User ' . $user->email . ' is now an admin',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Check admin users
Route::get('/check-admins', function() {
    $users = \App\Models\User::select('id', 'name', 'email', 'is_admin', 'created_at')->get();
    
    return response()->json([
        'total_users' => $users->count(),
        'admin_users' => $users->where('is_admin', true)->values(),
        'all_users' => $users->map(function($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'is_admin' => $user->is_admin ?? false,
                'can_access_panel' => method_exists($user, 'canAccessPanel') ? 'yes' : 'no'
            ];
        })
    ]);
});

// Fix admin panel setup
Route::get('/fix-admin', function() {
    try {
        // Run migrations to ensure all tables exist
        Artisan::call('migrate', ['--force' => true]);
        
        // Clear all caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        
        // Add is_admin column if it doesn't exist
        if (!Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function ($table) {
                $table->boolean('is_admin')->default(false);
            });
        }
        
        // Create admin user
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@bagcomics.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );
        $user->is_admin = true;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Admin panel setup complete',
            'admin_user' => $user->email,
            'login_url' => url('/admin'),
            'credentials' => 'Email: admin@bagcomics.com, Password: password'
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
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

// Test authentication route
Route::get('/test-auth', function() {
    try {
        // Test auth provider
        $guard = auth()->guard('web');
        $provider = $guard->getProvider();
        
        // Test finding a user
        $user = App\Models\User::where('is_admin', true)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'No admin users found'
            ]);
        }
        
        // Test auth provider retrieveByCredentials method
        $credentials = ['email' => $user->email];
        $retrievedUser = $provider->retrieveByCredentials($credentials);
        
        return response()->json([
            'success' => true,
            'message' => 'Authentication test successful',
            'provider' => get_class($provider),
            'model' => $provider->getModel(),
            'test_user' => $user->email,
            'retrieved_user' => $retrievedUser ? $retrievedUser->email : 'null'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Force mail configuration check and temporary override
Route::get('/mail-config-debug', function() {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json(['error' => 'Admin access required']);
    }
    
    $currentConfig = [
        'driver' => config('mail.default'),
        'mailers' => config('mail.mailers'),
        'from' => config('mail.from'),
    ];
    
    // Check if we're in production and using log driver
    $needsOverride = app()->environment('production') && config('mail.default') === 'log';
    
    if ($needsOverride) {
        // Force SMTP configuration temporarily
        config(['mail.default' => 'smtp']);
        config(['mail.mailers.smtp.host' => 'smtp.sendgrid.net']);
        config(['mail.mailers.smtp.port' => 587]);
        config(['mail.mailers.smtp.encryption' => 'tls']);
        config(['mail.mailers.smtp.username' => 'apikey']);
        // Note: We can't set the password here for security reasons
    }
    
    return response()->json([
        'environment' => app()->environment(),
        'needs_override' => $needsOverride,
        'current_config' => $currentConfig,
        'env_check' => [
            'MAIL_MAILER' => env('MAIL_MAILER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD_SET' => !empty(env('MAIL_PASSWORD')),
        ],
        'instructions' => $needsOverride ? [
            'message' => 'Your mail is configured to use LOG driver in production!',
            'fix' => 'Add environment variables in Render Dashboard',
            'required_vars' => [
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'smtp.sendgrid.net',
                'MAIL_PORT' => '587',
                'MAIL_USERNAME' => 'apikey',
                'MAIL_PASSWORD' => 'your-sendgrid-api-key',
                'MAIL_ENCRYPTION' => 'tls',
                'MAIL_FROM_ADDRESS' => 'noreply@bagcomics.com',
                'MAIL_FROM_NAME' => 'BAG Comics'
            ]
        ] : 'Configuration looks correct'
    ], 200, [], JSON_PRETTY_PRINT);
});

// Check admin status
Route::get('/admin-status', function() {
    if (!auth()->check()) {
        return response()->json([
            'authenticated' => false,
            'message' => 'You need to log in first',
            'login_url' => route('login')
        ]);
    }
    
    $user = auth()->user();
    return response()->json([
        'authenticated' => true,
        'user_id' => $user->id,
        'user_name' => $user->name,
        'user_email' => $user->email,
        'is_admin' => $user->is_admin ?? false,
        'admin_access' => $user->is_admin ?? false,
        'make_admin_url' => $user->is_admin ? null : url('/make-me-admin'),
        'timestamp' => now()
    ], 200, [], JSON_PRETTY_PRINT);
});

// Make current user admin (emergency access)
Route::get('/make-me-admin', function() {
    if (!auth()->check()) {
        return response()->json(['error' => 'You must be logged in']);
    }
    
    $user = auth()->user();
    
    // Add is_admin column if it doesn't exist
    try {
        if (!\Schema::hasColumn('users', 'is_admin')) {
            \Schema::table('users', function ($table) {
                $table->boolean('is_admin')->default(false);
            });
        }
        
        $user->is_admin = true;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'You are now an admin user',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin
            ],
            'test_urls' => [
                'sendgrid_config' => url('/test-sendgrid'),
                'send_email' => url('/test-send-email'),
                'quick_email' => url('/send-test-email-quick?email=' . $user->email)
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Test SendGrid email configuration
Route::get('/test-sendgrid', function() {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json([
            'error' => 'Admin access required',
            'authenticated' => auth()->check(),
            'is_admin' => auth()->check() ? (auth()->user()->is_admin ?? false) : null,
            'check_status_url' => url('/admin-status'),
            'make_admin_url' => auth()->check() ? url('/make-me-admin') : null
        ]);
    }
    
    try {
        $mailConfig = [
            'default' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'password_set' => !empty(config('mail.mailers.smtp.password')),
            'encryption' => config('mail.mailers.smtp.encryption'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
        
        // Test basic configuration
        $issues = [];
        if (config('mail.mailers.smtp.host') !== 'smtp.sendgrid.net') {
            $issues[] = 'SMTP host should be smtp.sendgrid.net';
        }
        if (config('mail.mailers.smtp.port') != 587) {
            $issues[] = 'SMTP port should be 587';
        }
        if (empty(config('mail.mailers.smtp.password'))) {
            $issues[] = 'SendGrid API key is not set';
        }
        
        return response()->json([
            'success' => count($issues) === 0,
            'configuration' => $mailConfig,
            'issues' => $issues,
            'environment' => app()->environment(),
            'timestamp' => now()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Basic email test (no admin required)
Route::get('/test-email-basic', function() {
    if (!auth()->check()) {
        return response()->json(['error' => 'You must be logged in']);
    }
    
    $user = auth()->user();
    
    try {
        \Illuminate\Support\Facades\Mail::raw(
            "Hello {$user->name}!\n\nThis is a basic email test from BAG Comics.\n\nIf you receive this email, your email system is working correctly.\n\nSent at: " . now(),
            function($message) use ($user) {
                $message->to($user->email)
                       ->subject('BAG Comics - Basic Email Test');
            }
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Basic test email sent successfully to your email address',
            'email' => $user->email,
            'timestamp' => now(),
            'note' => 'Check your email inbox (and spam folder) for the test email'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'email' => $user->email,
            'suggestion' => 'This error suggests there may be an issue with your SendGrid configuration'
        ], 500);
    }
});

// Send test email interface (GET)
Route::get('/test-send-email', function() {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json([
            'error' => 'Admin access required',
            'alternatives' => [
                'basic_test' => url('/test-email-basic'),
                'check_admin' => url('/admin-status'),
                'make_admin' => auth()->check() ? url('/make-me-admin') : 'Login first'
            ]
        ]);
    }
    
    return response()->json([
        'message' => 'SendGrid Email Test Interface',
        'instructions' => 'Send POST request to this endpoint with email and type parameters',
        'parameters' => [
            'email' => 'required|email - Email address to send test to',
            'type' => 'optional|string - "basic" or "notification" (default: basic)'
        ],
        'examples' => [
            'curl -X POST ' . url('/test-send-email') . ' -H "Content-Type: application/json" -d \'{"email":"test@example.com","type":"basic"}\'',
            'curl -X POST ' . url('/test-send-email') . ' -H "Content-Type: application/json" -d \'{"email":"test@example.com","type":"notification"}\''
        ],
        'quick_test_links' => [
            'basic_email' => url('/send-test-email-quick?email=admin@bagcomics.com&type=basic'),
            'notification_email' => url('/send-test-email-quick?email=admin@bagcomics.com&type=notification')
        ]
    ], 200, [], JSON_PRETTY_PRINT);
});

// Send test email via SendGrid (POST)
Route::post('/test-send-email', function(\Illuminate\Http\Request $request) {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json(['error' => 'Admin access required']);
    }
    
    $request->validate([
        'email' => 'required|email',
        'type' => 'string|in:basic,notification'
    ]);
    
    $email = $request->email;
    $type = $request->get('type', 'basic');
    
    try {
        if ($type === 'basic') {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from BAG Comics to verify SendGrid configuration. Sent at: ' . now(),
                function($message) use ($email) {
                    $message->to($email)
                           ->subject('BAG Comics - SendGrid Test Email');
                }
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Basic test email sent successfully',
                'email' => $email,
                'type' => 'basic'
            ]);
        } else {
            // Test notification email
            $user = \App\Models\User::where('email', $email)->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name' => 'Test User',
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now()
                ]);
            }
            
            $comic = \App\Models\Comic::first();
            if (!$comic) {
                return response()->json([
                    'success' => false,
                    'error' => 'No comic available for testing'
                ]);
            }
            
            $user->notify(new \App\Notifications\NewComicReleased($comic));
            
            return response()->json([
                'success' => true,
                'message' => 'Notification email sent successfully',
                'email' => $email,
                'type' => 'notification',
                'comic' => $comic->title
            ]);
        }
        
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('SendGrid test email failed', [
            'email' => $email,
            'type' => $type,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'email' => $email,
            'type' => $type
        ], 500);
    }
});

// Quick test email route (GET with query parameters)
Route::get('/send-test-email-quick', function(\Illuminate\Http\Request $request) {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json(['error' => 'Admin access required']);
    }
    
    $email = $request->query('email', 'admin@bagcomics.com');
    $type = $request->query('type', 'basic');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return response()->json([
            'success' => false,
            'error' => 'Invalid email address'
        ]);
    }
    
    try {
        if ($type === 'basic') {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a quick test email from BAG Comics to verify SendGrid configuration. Sent at: ' . now(),
                function($message) use ($email) {
                    $message->to($email)
                           ->subject('BAG Comics - Quick SendGrid Test');
                }
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Quick test email sent successfully',
                'email' => $email,
                'type' => 'basic',
                'timestamp' => now()
            ]);
        } else {
            // Test notification email
            $user = \App\Models\User::where('email', $email)->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name' => 'Test User',
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now()
                ]);
            }
            
            $comic = \App\Models\Comic::first();
            if (!$comic) {
                return response()->json([
                    'success' => false,
                    'error' => 'No comic available for testing'
                ]);
            }
            
            $user->notify(new \App\Notifications\NewComicReleased($comic));
            
            return response()->json([
                'success' => true,
                'message' => 'Quick notification test email sent successfully',
                'email' => $email,
                'type' => 'notification',
                'comic' => $comic->title,
                'timestamp' => now()
            ]);
        }
        
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('SendGrid quick test email failed', [
            'email' => $email,
            'type' => $type,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'email' => $email,
            'type' => $type
        ], 500);
    }
});

// Test password reset email
Route::get('/test-password-reset', function() {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json(['error' => 'Admin access required']);
    }
    
    $user = auth()->user();
    
    try {
        // Generate a test token (for testing purposes only)
        $token = \Illuminate\Support\Str::random(60);
        
        // Send password reset notification
        $user->sendPasswordResetNotification($token);
        
        return response()->json([
            'success' => true,
            'message' => 'Password reset email sent successfully',
            'email' => $user->email,
            'note' => 'Check your email inbox and SendGrid Activity dashboard',
            'reset_url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
            'timestamp' => now()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'email' => $user->email
        ], 500);
    }
});

// Debug reader page data
Route::get('/debug-reader/{slug}', function($slug) {
    $comic = \App\Models\Comic::where('slug', $slug)->first();
    if (!$comic) {
        return response()->json(['error' => 'Comic not found']);
    }
    
    $comicData = $comic->load(['userProgress' => function ($query) {
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }
    }])->toArray();
    
    // Add computed fields
    $comicData['cover_image_url'] = $comic->getCoverImageUrl();
    $comicData['pdf_stream_url'] = $comic->getPdfStreamUrl();
    
    return response()->json([
        'comic_data' => $comicData,
        'pdf_stream_url' => $comicData['pdf_stream_url'],
        'title' => $comicData['title'],
        'slug' => $comicData['slug']
    ], 200, [], JSON_PRETTY_PRINT);
});

// Debug PDF file access
Route::get('/test-pdf-access/{slug}', function($slug) {
    if (!auth()->check() || !auth()->user()->is_admin) {
        return response()->json(['error' => 'Admin access required']);
    }
    
    try {
        $comic = \App\Models\Comic::where('slug', $slug)->first();
        if (!$comic) {
            return response()->json([
                'success' => false,
                'error' => 'Comic not found'
            ]);
        }
        
        $result = [
            'comic' => [
                'id' => $comic->id,
                'title' => $comic->title,
                'slug' => $comic->slug,
                'is_pdf_comic' => $comic->is_pdf_comic,
                'pdf_file_path' => $comic->pdf_file_path,
                'pdf_file_name' => $comic->pdf_file_name,
                'is_visible' => $comic->is_visible,
            ],
            'file_checks' => []
        ];
        
        if ($comic->pdf_file_path) {
            $filePath = $comic->pdf_file_path;
            
            // Check storage/app/public location
            $storagePath = \Illuminate\Support\Facades\Storage::disk('public')->path($filePath);
            $storageExists = \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath);
            
            // Check public directory location
            $publicPath = public_path($filePath);
            $publicExists = file_exists($publicPath);
            
            $result['file_checks'] = [
                'storage_disk_path' => $storagePath,
                'storage_disk_exists' => $storageExists,
                'storage_disk_readable' => $storageExists && is_readable($storagePath),
                'storage_disk_size' => $storageExists ? filesize($storagePath) : null,
                
                'public_path' => $publicPath,
                'public_exists' => $publicExists,
                'public_readable' => $publicExists && is_readable($publicPath),
                'public_size' => $publicExists ? filesize($publicPath) : null,
                
                'pdf_url' => $comic->getPdfUrl(),
                'stream_url' => route('comics.stream', $comic->slug),
            ];
            
            // Test MIME type if file exists
            if ($storageExists) {
                $result['file_checks']['storage_mime_type'] = mime_content_type($storagePath);
            }
            if ($publicExists) {
                $result['file_checks']['public_mime_type'] = mime_content_type($publicPath);
            }
        }
        
        return response()->json($result, 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// ============================================
// Setup Sample Comic with Pages from /public/sample/
// ============================================

Route::get('/setup-sample-comic', function() {
    $baseUrl = config('app.url');

    // Find or create The Anointer comic
    $comic = \App\Models\Comic::where('slug', 'the-anointer-issue-1')
        ->orWhere('title', 'like', '%Anointer%Issue #1%')
        ->first();

    if (!$comic) {
        $comic = \App\Models\Comic::create([
            'title' => 'The Anointer Issue #1',
            'slug' => 'the-anointer-sample',
            'author' => 'Oscar Lwalwe',
            'description' => 'A sample comic to demonstrate the reader functionality.',
            'genre' => 'action',
            'is_free' => true,
            'is_visible' => true,
            'published_at' => now(),
        ]);
    }

    // Set cover image from first page
    $comic->cover_image_path = $baseUrl . '/sample/The%20Anointor%20Book%20one%20Final(Complete)%202_pages-to-jpg-0001.jpg';
    $comic->save();

    // Delete existing pages for this comic
    $comic->pages()->delete();

    // Add all 15 pages
    $pages = [];
    for ($i = 1; $i <= 15; $i++) {
        $paddedNum = str_pad($i, 4, '0', STR_PAD_LEFT);
        $filename = "The Anointor Book one Final(Complete) 2_pages-to-jpg-{$paddedNum}.jpg";
        $url = $baseUrl . '/sample/' . rawurlencode($filename);

        $page = \App\Models\ComicPage::create([
            'comic_id' => $comic->id,
            'page_number' => $i,
            'image_url' => $url,
            'image_path' => $url,
        ]);
        $pages[] = ['page' => $i, 'url' => $url];
    }

    $comic->page_count = 15;
    $comic->save();

    return response()->json([
        'success' => true,
        'message' => 'Sample comic setup complete',
        'comic' => [
            'id' => $comic->id,
            'slug' => $comic->slug,
            'title' => $comic->title,
            'cover_url' => $comic->cover_image_url,
            'page_count' => $comic->page_count,
        ],
        'pages' => $pages,
        'test_urls' => [
            'api' => $baseUrl . '/api/v2/comics/' . $comic->slug,
            'frontend' => $baseUrl . '/',
        ]
    ]);
});

// ============================================
// Comic Management Routes (for updating to Cloudinary)
// ============================================

// Update a comic's cover image URL
Route::post('/api/admin/comics/{comic}/update-cover-url', function(\Illuminate\Http\Request $request, \App\Models\Comic $comic) {
    $request->validate(['cover_url' => 'required|url']);

    $comic->cover_image_path = $request->cover_url;
    $comic->save();

    return response()->json([
        'success' => true,
        'message' => 'Cover URL updated',
        'comic' => [
            'id' => $comic->id,
            'slug' => $comic->slug,
            'cover_image_url' => $comic->cover_image_url,
        ]
    ]);
})->middleware('auth');

// Add pages to a comic via URLs
Route::post('/api/admin/comics/{comic}/add-pages-urls', function(\Illuminate\Http\Request $request, \App\Models\Comic $comic) {
    $request->validate([
        'pages' => 'required|array|min:1',
        'pages.*' => 'required|url',
    ]);

    $startPage = $comic->pages()->max('page_number') ?? 0;
    $added = [];

    foreach ($request->pages as $index => $url) {
        $pageNumber = $startPage + $index + 1;
        $page = \App\Models\ComicPage::create([
            'comic_id' => $comic->id,
            'page_number' => $pageNumber,
            'image_url' => $url,
            'image_path' => $url, // Use URL as path for external images
        ]);
        $added[] = ['page_number' => $pageNumber, 'url' => $url];
    }

    $comic->page_count = $comic->pages()->count();
    $comic->save();

    return response()->json([
        'success' => true,
        'message' => count($added) . ' pages added',
        'pages' => $added,
        'total_pages' => $comic->page_count,
    ]);
})->middleware('auth');

// List all comics with their cover status
Route::get('/api/admin/comics-status', function() {
    $comics = \App\Models\Comic::select('id', 'slug', 'title', 'cover_image_path')
        ->withCount('pages')
        ->get()
        ->map(fn($c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'cover_image_path' => $c->cover_image_path,
            'cover_image_url' => $c->cover_image_url,
            'cover_is_cloudinary' => str_starts_with($c->cover_image_path ?? '', 'http'),
            'pages_count' => $c->pages_count,
        ]);

    return response()->json([
        'total' => $comics->count(),
        'comics' => $comics,
    ]);
});

// Cloudinary config check
Route::get('/api/admin/cloudinary-status', function() {
    $cloudName = config('services.cloudinary.cloud_name');
    $apiKey = config('services.cloudinary.api_key');
    $apiSecret = config('services.cloudinary.api_secret');

    return response()->json([
        'configured' => !empty($cloudName) && !empty($apiKey) && !empty($apiSecret),
        'cloud_name' => $cloudName ?: 'NOT SET',
        'api_key_set' => !empty($apiKey),
        'api_secret_set' => !empty($apiSecret),
        'instructions' => 'Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET in Render environment variables',
    ]);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
