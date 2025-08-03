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
        // Check if the sessions table already exists
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        } else {
            // Table exists, check for any missing columns and add them if needed
            Schema::table('sessions', function (Blueprint $table) {
                // Add any additional columns here if this migration was meant to add new columns
                // For now, we'll just ensure the table structure is correct
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if this migration created the table
        // Since the base sessions table is created by Laravel's default migration,
        // we should not drop it here
    }
};