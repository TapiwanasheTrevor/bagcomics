<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CreatorSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorSubmissionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'portfolio_url' => 'nullable|url|max:500',
            'comic_title' => 'required|string|max:255',
            'genre' => 'required|string|max:100',
            'synopsis' => 'required|string|max:2000',
            'sample_pages_url' => 'nullable|url|max:500',
        ]);

        CreatorSubmission::create($validated);

        return response()->json([
            'data' => ['message' => 'Your submission has been received! We will review it and get back to you.'],
        ], 201);
    }
}
