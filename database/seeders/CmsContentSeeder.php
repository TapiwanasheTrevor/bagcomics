<?php

namespace Database\Seeders;

use App\Models\CmsContent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CmsContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $content = [
            // Hero Section
            [
                'key' => 'hero_title',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Main Title',
                'content' => 'African Stories, Boldly Told',
                'sort_order' => 1,
            ],
            [
                'key' => 'hero_subtitle',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Subtitle',
                'content' => 'Discover captivating tales from the heart of Africa. Immerse yourself in rich cultures, legendary heroes, and timeless wisdom through our curated collection of comics.',
                'sort_order' => 2,
            ],
            [
                'key' => 'hero_cta_primary',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Primary Call-to-Action Button',
                'content' => 'Start Reading',
                'sort_order' => 3,
            ],
            [
                'key' => 'hero_cta_secondary',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Secondary Call-to-Action Button',
                'content' => 'Browse Collection',
                'sort_order' => 4,
            ],

            // Section Titles
            [
                'key' => 'trending_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Trending Section Title',
                'content' => 'Trending Now',
                'sort_order' => 1,
            ],
            [
                'key' => 'trending_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Trending Section Subtitle',
                'content' => 'Most popular comics this week',
                'sort_order' => 2,
            ],
            [
                'key' => 'new_releases_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'New Releases Section Title',
                'content' => 'New Releases',
                'sort_order' => 3,
            ],
            [
                'key' => 'new_releases_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'New Releases Section Subtitle',
                'content' => 'Fresh stories just added',
                'sort_order' => 4,
            ],
            [
                'key' => 'free_comics_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Free Comics Section Title',
                'content' => 'Free to Read',
                'sort_order' => 5,
            ],
            [
                'key' => 'free_comics_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Free Comics Section Subtitle',
                'content' => 'Start your journey at no cost',
                'sort_order' => 6,
            ],

            // Navigation
            [
                'key' => 'site_name',
                'section' => 'navigation',
                'type' => 'text',
                'title' => 'Site Name/Logo Text',
                'content' => 'BAG Comics',
                'sort_order' => 1,
            ],

            // About Section
            [
                'key' => 'about_title',
                'section' => 'about',
                'type' => 'text',
                'title' => 'About Section Title',
                'content' => 'Start Your African Adventure',
                'sort_order' => 1,
            ],
            [
                'key' => 'about_description',
                'section' => 'about',
                'type' => 'text',
                'title' => 'About Section Description',
                'content' => 'Join thousands of readers exploring the rich tapestry of African storytelling. From ancient myths to futuristic tales, your next great adventure awaits.',
                'sort_order' => 2,
            ],

            // Footer
            [
                'key' => 'footer_copyright',
                'section' => 'footer',
                'type' => 'text',
                'title' => 'Footer Copyright Text',
                'content' => '© 2024 BAG Comics. All rights reserved.',
                'sort_order' => 1,
            ],
        ];

        foreach ($content as $item) {
            CmsContent::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }

        $this->command->info('CMS content seeded successfully!');
    }
}
