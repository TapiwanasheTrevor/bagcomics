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
        Schema::table('user_comic_progress', function (Blueprint $table) {
            // Reading session tracking
            $table->json('reading_sessions')->nullable()->after('bookmarks');
            $table->integer('total_reading_sessions')->default(0)->after('reading_sessions');
            $table->timestamp('first_read_at')->nullable()->after('total_reading_sessions');
            
            // Detailed reading metadata
            $table->json('reading_metadata')->nullable()->after('first_read_at');
            $table->decimal('average_session_duration', 8, 2)->default(0)->after('reading_metadata');
            $table->integer('pages_per_session_avg')->default(0)->after('average_session_duration');
            
            // Reading preferences and behavior
            $table->json('reading_preferences')->nullable()->after('pages_per_session_avg');
            $table->decimal('reading_speed_pages_per_minute', 5, 2)->default(0)->after('reading_preferences');
            
            // Analytics data
            $table->integer('total_time_paused_minutes')->default(0)->after('reading_speed_pages_per_minute');
            $table->integer('bookmark_count')->default(0)->after('total_time_paused_minutes');
            $table->timestamp('last_bookmark_at')->nullable()->after('bookmark_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_comic_progress', function (Blueprint $table) {
            $table->dropColumn([
                'reading_sessions',
                'total_reading_sessions',
                'first_read_at',
                'reading_metadata',
                'average_session_duration',
                'pages_per_session_avg',
                'reading_preferences',
                'reading_speed_pages_per_minute',
                'total_time_paused_minutes',
                'bookmark_count',
                'last_bookmark_at'
            ]);
        });
    }
};
