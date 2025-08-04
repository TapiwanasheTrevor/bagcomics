<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->string('author')->nullable()->after('title');
            $table->string('genre')->nullable()->after('author');
            $table->json('tags')->nullable()->after('genre'); // JSON array of tags
            $table->integer('page_count')->nullable()->after('description');
            $table->string('language', 10)->default('en')->after('page_count');
            $table->decimal('average_rating', 3, 2)->default(0.00)->after('language');
            $table->integer('total_ratings')->default(0)->after('average_rating');
            $table->integer('total_readers')->default(0)->after('total_ratings');
            $table->string('isbn')->nullable()->after('total_readers');
            $table->year('publication_year')->nullable()->after('isbn');
            $table->string('publisher')->nullable()->after('publication_year');
            $table->json('preview_pages')->nullable()->after('cover_image_path'); // Array of preview page numbers
            $table->boolean('has_mature_content')->default(false)->after('preview_pages');
            $table->json('content_warnings')->nullable()->after('has_mature_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn([
                'author',
                'genre',
                'tags',
                'page_count',
                'language',
                'average_rating',
                'total_ratings',
                'total_readers',
                'isbn',
                'publication_year',
                'publisher',
                'preview_pages',
                'has_mature_content',
                'content_warnings',
            ]);
        });
    }
};
