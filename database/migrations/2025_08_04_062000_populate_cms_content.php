<?php

use App\Models\CmsContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key constraints temporarily
        Schema::disableForeignKeyConstraints();
        
        // Define default CMS content
        $defaultContent = [
            // Hero Section
            [
                'key' => 'hero_title',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Title',
                'content' => 'African Stories, Boldly Told',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'hero_subtitle',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Subtitle',
                'content' => 'Discover captivating tales from the heart of Africa. Immerse yourself in rich cultures, legendary heroes, and timeless wisdom through our curated collection of comics.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'hero_cta_primary',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Primary CTA',
                'content' => 'Start Reading',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'hero_cta_secondary',
                'section' => 'hero',
                'type' => 'text',
                'title' => 'Hero Secondary CTA',
                'content' => 'Browse Collection',
                'is_active' => true,
                'sort_order' => 4,
            ],
            
            // General Section
            [
                'key' => 'trending_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Trending Section Title',
                'content' => 'Trending Now',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'trending_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Trending Section Subtitle',
                'content' => 'Most popular comics this week',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'new_releases_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'New Releases Title',
                'content' => 'New Releases',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'new_releases_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'New Releases Subtitle',
                'content' => 'Fresh stories just added',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'free_comics_title',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Free Comics Title',
                'content' => 'Free to Read',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'key' => 'free_comics_subtitle',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Free Comics Subtitle',
                'content' => 'Start your journey at no cost',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'key' => 'site_description',
                'section' => 'general',
                'type' => 'text',
                'title' => 'Site Description',
                'content' => 'Celebrating African storytelling through captivating comics. Discover heroes, legends, and adventures from across the continent.',
                'is_active' => true,
                'sort_order' => 7,
            ],
            
            // Navigation
            [
                'key' => 'site_name',
                'section' => 'navigation',
                'type' => 'text',
                'title' => 'Site Name',
                'content' => 'BAG Comics',
                'is_active' => true,
                'sort_order' => 1,
            ],
            
            // Footer
            [
                'key' => 'footer_copyright',
                'section' => 'footer',
                'type' => 'text',
                'title' => 'Footer Copyright',
                'content' => '© 2024 BAG Comics. Celebrating African storytelling.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];
        
        // Create or update content
        foreach ($defaultContent as $content) {
            CmsContent::updateOrCreate(
                ['key' => $content['key']],
                $content
            );
        }
        
        // Re-enable foreign key constraints
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't need to reverse this migration
        // CMS content can remain populated
    }
};