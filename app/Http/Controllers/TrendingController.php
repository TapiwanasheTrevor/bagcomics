<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrendingController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('trending');
    }
}