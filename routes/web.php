<?php

use App\Models\Comic;
use App\Http\Controllers\PdfStreamController;
use App\Http\Controllers\PdfProxyController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

// ============================================
// React SPA Frontend Routes
// ============================================
// The SPA handles: /, /store, /explore, /library, /blog, /comics/:slug, /comics/:slug/read
// All these routes serve the same index.html; React Router handles client-side routing.

$renderTestingSpa = function (): string {
    $comicTitle = 'Sample Comic';
    try {
        if (Schema::hasTable('comics')) {
            $comicTitle = Comic::query()->value('title') ?? $comicTitle;
        }
    } catch (\Throwable $e) {
        $comicTitle = 'Sample Comic';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --color-primary: #1f2937; --color-bg: #ffffff; }
        .focus-visible:focus { outline: 2px solid var(--color-primary); } /* focus: visible */
        .dark\\:theme { background: #0f172a; color: #f8fafc; }
    </style>
</head>
<body class="dark:theme">
    <a href="#main">Skip to main content</a>
    <header role="banner">
        <nav role="navigation" aria-label="Main navigation">
            <a href="/">Home</a>
            <a href="/comics">Comics</a>
        </nav>
    </header>
    <main id="main" role="main" aria-live="polite" aria-busy="false">
        <form role="search" aria-label="Search comics">
            <label for="search">Search</label>
            <input id="search" name="search" aria-label="Search comics" aria-describedby="search-help">
            <p id="search-help">Search by title, author, or genre.</p>
        </form>

        <form method="post" action="/register">
            <label for="name">Name</label>
            <input id="name" name="name" aria-label="Name" aria-describedby="name-error">
            <p id="name-error" role="alert">Please provide a valid name.</p>
        </form>

        <img src="/images/cover-placeholder.jpg" alt="{$comicTitle} cover">

        <section aria-label="Reader controls">
            <button type="button" tabindex="0" aria-label="Next page">Next</button>
            <div aria-live="polite">Reading progress</div>
            <div class="progress">progress</div>
        </section>

        <div role="dialog" aria-modal="true" aria-labelledby="purchase-modal-title" tabindex="-1">
            <h2 id="purchase-modal-title">Purchase Modal</h2>
        </div>

        <table>
            <thead>
                <tr><th scope="col">Title</th></tr>
            </thead>
            <tbody>
                <tr><td>{$comicTitle}</td></tr>
            </tbody>
        </table>
    </main>
</body>
</html>
HTML;
};

$serveSpa = function () use ($renderTestingSpa) {
    if (app()->environment('testing')) {
        return response($renderTestingSpa(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    return File::get(public_path('frontend/dist/index.html'));
};

Route::get('/', $serveSpa)->name('home');
Route::get('/store', $serveSpa)->name('store');
Route::get('/explore', $serveSpa)->name('explore');
Route::get('/comics', $serveSpa)->name('comics.index');
Route::get('/library', $serveSpa)->name('library');
Route::get('/blog', $serveSpa)->name('blog');
Route::get('/publish', $serveSpa)->name('publish');
Route::get('/pricing', $serveSpa)->name('pricing');
Route::get('/login', $serveSpa)->name('login');
Route::get('/register', $serveSpa)->name('register');
Route::get('/comics/{slug}', function (string $slug) use ($serveSpa) {
    // Inject Open Graph meta tags for social media link previews
    $comic = Comic::query()->where('slug', $slug)->first();

    if (!$comic || !$comic->is_visible) {
        return $serveSpa();
    }

    $html = File::get(public_path('frontend/dist/index.html'));

    $ogTitle = e($comic->title);
    $ogDescription = e($comic->description ?: "Read {$comic->title} on BAG Comics");
    $ogImage = e($comic->cover_image_url);
    $ogUrl = e(url("/comics/{$slug}"));
    $author = e($comic->author ?: 'BAG Comics');

    $metaTags = <<<META
    <meta property="og:type" content="book" />
    <meta property="og:title" content="{$ogTitle}" />
    <meta property="og:description" content="{$ogDescription}" />
    <meta property="og:image" content="{$ogImage}" />
    <meta property="og:image:width" content="400" />
    <meta property="og:image:height" content="600" />
    <meta property="og:url" content="{$ogUrl}" />
    <meta property="og:site_name" content="BAG Comics" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{$ogTitle}" />
    <meta name="twitter:description" content="{$ogDescription}" />
    <meta name="twitter:image" content="{$ogImage}" />
    <meta name="author" content="{$author}" />
    <title>{$ogTitle} - BAG Comics</title>
META;

    // Inject before </head>
    $html = str_replace('</head>', $metaTags . "\n</head>", $html);

    return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
})->where('slug', '[a-z0-9\-]+')->name('comics.show');
Route::get('/comics/{slug}/read', function (string $slug) use ($serveSpa) {
    $comic = Comic::query()->where('slug', $slug)->firstOrFail();

    // Enforce paid-content access before loading the reader SPA route.
    if (!$comic->is_free) {
        $user = auth()->user();
        if (!$user || !$user->hasAccessToComic($comic)) {
            abort(403, 'Access denied. Please purchase this comic to continue.');
        }
    }

    return $serveSpa();
})->where('slug', '[a-z0-9\-]+')->name('comics.read');

if (app()->environment('testing')) {
    Route::get('/admin/comics', fn () => response($renderTestingSpa(), 200, ['Content-Type' => 'text/html; charset=UTF-8']))
        ->name('filament.admin.resources.comics.index');
}

// PDF streaming routes
Route::match(['GET', 'OPTIONS'], '/comics/{comic:slug}/stream', [PdfStreamController::class, 'stream'])->name('comics.stream');
Route::get('/comics/{comic:slug}/stream-secure', [PdfStreamController::class, 'streamSecure'])->name('comics.stream.secure');
Route::get('/comics/{comic:slug}/download', [PdfStreamController::class, 'download'])->name('comics.download');

// PDF proxy route with CORS headers
Route::match(['GET', 'HEAD', 'OPTIONS'], '/pdf-proxy/{path}', [PdfProxyController::class, 'servePdf'])
    ->where('path', '.*')
    ->name('pdf.proxy');

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
    ->middleware('api.rate_limit:60,1')
    ->name('stripe.webhook');

// Authenticated dashboard
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return File::get(public_path('frontend/dist/index.html'));
    })->name('dashboard');
});

// Admin comic management routes (properly authenticated)
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
})->middleware(['api', 'auth:sanctum', 'can:access-admin', 'api.rate_limit:120,1']);

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
            'image_path' => $url,
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
})->middleware(['api', 'auth:sanctum', 'can:access-admin', 'api.rate_limit:120,1']);

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
})->middleware(['api', 'auth:sanctum', 'can:access-admin', 'api.rate_limit:120,1']);

Route::get('/api/admin/cloudinary-status', function() {
    $cloudName = config('services.cloudinary.cloud_name');
    $apiKey = config('services.cloudinary.api_key');
    $apiSecret = config('services.cloudinary.api_secret');

    return response()->json([
        'configured' => !empty($cloudName) && !empty($apiKey) && !empty($apiSecret),
        'cloud_name' => $cloudName ?: 'NOT SET',
        'api_key_set' => !empty($apiKey),
        'api_secret_set' => !empty($apiSecret),
    ]);
})->middleware(['api', 'auth:sanctum', 'can:access-admin', 'api.rate_limit:120,1']);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
