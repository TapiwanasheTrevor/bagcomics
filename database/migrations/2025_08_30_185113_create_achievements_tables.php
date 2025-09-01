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
        // Achievements table
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category');
            $table->string('type');
            $table->string('icon');
            $table->string('color');
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary']);
            $table->integer('points')->default(0);
            $table->json('requirements');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->integer('unlock_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['rarity', 'is_active']);
            $table->index('unlock_order');
        });

        // User Achievements (pivot table)
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->timestamp('unlocked_at');
            $table->json('progress_data')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
            $table->index(['user_id', 'is_seen']);
            $table->index(['user_id', 'unlocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
