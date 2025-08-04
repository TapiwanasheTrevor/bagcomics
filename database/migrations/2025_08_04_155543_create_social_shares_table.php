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
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // facebook, twitter, instagram, etc.
            $table->string('share_type'); // comic_discovery, reading_achievement, recommendation, etc.
            $table->json('metadata')->nullable(); // platform-specific data
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->index(['comic_id', 'platform']);
            $table->index(['share_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};
