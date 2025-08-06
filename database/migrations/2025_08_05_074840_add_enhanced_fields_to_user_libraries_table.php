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
        Schema::table('user_libraries', function (Blueprint $table) {
            $table->timestamp('last_accessed_at')->nullable()->after('review');
            $table->integer('total_reading_time')->default(0)->comment('Total reading time in seconds')->after('last_accessed_at');
            $table->decimal('completion_percentage', 5, 2)->default(0)->comment('Reading completion percentage')->after('total_reading_time');
            $table->string('device_sync_token')->nullable()->comment('Token for cross-device synchronization')->after('completion_percentage');
            
            // Add indexes for better query performance
            $table->index(['user_id', 'last_accessed_at']);
            $table->index(['user_id', 'completion_percentage']);
            $table->index(['user_id', 'total_reading_time']);
            $table->index('device_sync_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_libraries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_accessed_at']);
            $table->dropIndex(['user_id', 'completion_percentage']);
            $table->dropIndex(['user_id', 'total_reading_time']);
            $table->dropIndex(['device_sync_token']);
            
            $table->dropColumn([
                'last_accessed_at',
                'total_reading_time',
                'completion_percentage',
                'device_sync_token'
            ]);
        });
    }
};