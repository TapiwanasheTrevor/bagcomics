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
        if (!Schema::hasColumn('user_comic_progress', 'device_type')) {
            Schema::table('user_comic_progress', function (Blueprint $table) {
                $table->string('device_type')->nullable()->after('reading_time_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('user_comic_progress', 'device_type')) {
            Schema::table('user_comic_progress', function (Blueprint $table) {
                $table->dropColumn('device_type');
            });
        }
    }
};
