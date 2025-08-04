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
            // Check if columns exist before adding them
            if (!Schema::hasColumn('comics', 'series_id')) {
                $table->foreignId('series_id')->nullable()->constrained('comic_series')->onDelete('set null')->after('id');
            }
            
            if (!Schema::hasColumn('comics', 'issue_number')) {
                $table->integer('issue_number')->nullable()->after('series_id');
            }
            
            if (!Schema::hasColumn('comics', 'view_count')) {
                $table->integer('view_count')->default(0)->after('total_readers');
            }
            
            // Add indexes for better performance (these are safe to run multiple times)
            if (!Schema::hasIndex('comics', 'comics_series_id_issue_number_index')) {
                $table->index(['series_id', 'issue_number']);
            }
            if (!Schema::hasIndex('comics', 'comics_genre_index')) {
                $table->index(['genre']);
            }
            if (!Schema::hasIndex('comics', 'comics_publisher_index')) {
                $table->index(['publisher']);
            }
            if (!Schema::hasIndex('comics', 'comics_author_index')) {
                $table->index(['author']);
            }
            if (!Schema::hasIndex('comics', 'comics_average_rating_index')) {
                $table->index(['average_rating']);
            }
            if (!Schema::hasIndex('comics', 'comics_publication_year_index')) {
                $table->index(['publication_year']);
            }
            if (!Schema::hasIndex('comics', 'comics_is_visible_published_at_index')) {
                $table->index(['is_visible', 'published_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['series_id', 'issue_number']);
            $table->dropIndex(['genre']);
            $table->dropIndex(['publisher']);
            $table->dropIndex(['author']);
            $table->dropIndex(['average_rating']);
            $table->dropIndex(['publication_year']);
            $table->dropIndex(['is_visible', 'published_at']);
            
            // Drop foreign key and columns if they exist
            if (Schema::hasColumn('comics', 'series_id')) {
                $table->dropForeign(['series_id']);
                $table->dropColumn('series_id');
            }
            
            if (Schema::hasColumn('comics', 'issue_number')) {
                $table->dropColumn('issue_number');
            }
            
            if (Schema::hasColumn('comics', 'view_count')) {
                $table->dropColumn('view_count');
            }
        });
    }
};
