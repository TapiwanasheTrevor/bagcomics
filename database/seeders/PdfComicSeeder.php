<?php

namespace Database\Seeders;

use App\Models\Comic;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PdfComicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample PDF comic using the uploaded sample document
        Comic::firstOrCreate(
            ['title' => 'Anansi Chronicles: The Digital Edition'],
            [
            'author' => 'Kwame Asante',
            'genre' => 'fantasy',
            'tags' => ['mythology', 'african folklore', 'digital comic', 'fantasy'],
            'description' => 'Experience the legendary spider god Anansi in this beautifully crafted digital comic. Follow his adventures through modern Ghana as he weaves ancient wisdom into contemporary challenges. This PDF edition features high-quality artwork and interactive elements.',
            'page_count' => 24,
            'language' => 'en',
            'average_rating' => 4.8,
            'total_ratings' => 156,
            'total_readers' => 1247,
            'isbn' => '978-0-123456-78-9',
            'publication_year' => 2024,
            'publisher' => 'African Comics Collective',
            'pdf_file_path' => 'sample-comic.pdf',
            'pdf_file_name' => 'anansi-chronicles-digital.pdf',
            'pdf_file_size' => file_exists(public_path('sample-comic.pdf')) ? filesize(public_path('sample-comic.pdf')) : 1024000,
            'pdf_mime_type' => 'application/pdf',
            'is_pdf_comic' => true,
            'pdf_metadata' => [
                'version' => '1.0',
                'created_with' => 'Adobe InDesign',
                'optimized_for' => 'web',
                'interactive_elements' => true,
                'bookmarks' => true,
            ],
            'cover_image_path' => null,
            'preview_pages' => [1, 2, 3],
            'has_mature_content' => false,
            'content_warnings' => null,
            'is_free' => false,
            'price' => 4.99,
            'is_visible' => true,
            'published_at' => now()->subDays(15),
        ]);

        // Create another free PDF comic
        Comic::firstOrCreate(
            ['title' => 'Ubuntu Tales: Community Stories'],
            [
            'author' => 'Amara Okafor',
            'genre' => 'slice-of-life',
            'tags' => ['community', 'ubuntu philosophy', 'african stories', 'free'],
            'description' => 'A heartwarming collection of stories celebrating the Ubuntu philosophy - "I am because we are." This free digital comic showcases the power of community and interconnectedness through beautiful African storytelling.',
            'page_count' => 16,
            'language' => 'en',
            'average_rating' => 4.5,
            'total_ratings' => 89,
            'total_readers' => 892,
            'isbn' => null,
            'publication_year' => 2024,
            'publisher' => 'Community Comics Initiative',
            'pdf_file_path' => 'sample-comic.pdf', // Using same sample file
            'pdf_file_name' => 'ubuntu-tales-community.pdf',
            'pdf_file_size' => file_exists(public_path('sample-comic.pdf')) ? filesize(public_path('sample-comic.pdf')) : 1024000,
            'pdf_mime_type' => 'application/pdf',
            'is_pdf_comic' => true,
            'pdf_metadata' => [
                'version' => '1.0',
                'created_with' => 'Canva Pro',
                'optimized_for' => 'mobile',
                'interactive_elements' => false,
                'bookmarks' => false,
            ],
            'cover_image_path' => null,
            'preview_pages' => [1, 2],
            'has_mature_content' => false,
            'content_warnings' => null,
            'is_free' => true,
            'price' => null,
            'is_visible' => true,
            'published_at' => now()->subDays(7),
        ]);
    }
}
