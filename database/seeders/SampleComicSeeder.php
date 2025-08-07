<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comic;

class SampleComicSeeder extends Seeder
{
    public function run()
    {
        // Clear existing comics to avoid confusion
        Comic::query()->delete();
        
        // Create one sample comic with correct path
        $comic = new Comic();
        $comic->title = 'Ubuntu Tales: Community Stories';
        $comic->slug = 'ubuntu-tales-community';
        $comic->author = 'Community Contributors';
        $comic->genre = 'sci-fi';
        $comic->description = 'A collection of stories from the Ubuntu community showcasing the spirit of collaboration and open source development.';
        $comic->page_count = 20;
        $comic->language = 'en';
        $comic->pdf_file_path = 'sample-comic.pdf'; // Direct path in public folder
        $comic->pdf_file_name = 'sample-comic.pdf';
        $comic->is_pdf_comic = true;
        $comic->is_free = true;
        $comic->is_visible = true;
        $comic->published_at = now();
        $comic->tags = ['ubuntu', 'community', 'open source', 'technology'];
        $comic->average_rating = 4.5;
        $comic->total_ratings = 125;
        $comic->total_readers = 1543;
        $comic->view_count = 2876;
        
        $comic->save();
        
        \Log::info('Sample comic created', ['id' => $comic->id, 'slug' => $comic->slug]);
    }
}