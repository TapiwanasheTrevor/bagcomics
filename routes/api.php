<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ComicSearchController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Comic Search API Routes
Route::prefix('comics')->group(function () {
    Route::get('/search', [ComicSearchController::class, 'search']);
    Route::get('/search/suggestions', [ComicSearchController::class, 'suggestions']);
    Route::get('/search/autocomplete', [ComicSearchController::class, 'autocomplete']);
    Route::get('/search/filter-options', [ComicSearchController::class, 'filterOptions']);
    Route::get('/search/popular-terms', [ComicSearchController::class, 'popularTerms']);
    Route::get('/search/analytics', [ComicSearchController::class, 'analytics'])->middleware('auth:sanctum');
});


