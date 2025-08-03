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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Reading preferences
            $table->enum('reading_view_mode', ['single', 'continuous'])->default('single');
            $table->enum('reading_direction', ['ltr', 'rtl'])->default('ltr'); // left-to-right or right-to-left
            $table->decimal('reading_zoom_level', 3, 2)->default(1.20); // Default zoom level
            $table->boolean('auto_hide_controls')->default(true);
            $table->integer('control_hide_delay')->default(3000); // milliseconds

            // Display preferences
            $table->enum('theme', ['light', 'dark', 'auto'])->default('dark');
            $table->boolean('reduce_motion')->default(false);
            $table->boolean('high_contrast')->default(false);

            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('new_releases_notifications')->default(true);
            $table->boolean('reading_reminders')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
