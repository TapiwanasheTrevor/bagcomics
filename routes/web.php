<?php

use App\Models\Comic;
use App\Http\Controllers\PdfStreamController;
use App\Http\Controllers\PdfProxyController;
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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
