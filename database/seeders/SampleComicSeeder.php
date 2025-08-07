<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comic;

class SampleComicSeeder extends Seeder
{
    public function run()
    {
        // Clear existing comics to avoid confusion
        Comic::truncate();
        
        // Create one sample comic with correct path
        Comic::create([
            'title' => 'Ubuntu Tales: Community Stories',
            'slug' => 'ubuntu-tales-community',
            'author' => 'Community Contributors',
            'genre' => 'sci-fi',
            'description' => 'A collection of stories from the Ubuntu community showcasing the spirit of collaboration and open source development.',
            'page_count' => 20,
            'language' => 'en',
            'pdf_file_path' => 'sample-comic.pdf', // Direct path in public folder
            'pdf_file_name' => 'sample-comic.pdf',
            'is_pdf_comic' => true,
            'is_free' => true,
            'is_visible' => true,
            'published_at' => now(),
            'tags' => ['ubuntu', 'community', 'open source', 'technology'],
            'average_rating' => 4.5,
            'total_ratings' => 125,
            'total_readers' => 1543,
            'view_count' => 2876,
        ]);
    }
}