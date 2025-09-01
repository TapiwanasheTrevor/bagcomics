<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->enum('recommendation_type', [
                'collaborative_filtering',
                'content_based',
                'hybrid',
                'trending',
                'new_release',
                'similar_readers',
                'genre_based',
                'author_based',
                'continue_series'
            ]);
            $table->decimal('score', 5, 4)->default(0); // 0.0000 to 1.0000
            $table->json('reasons')->nullable(); // Array of reason codes
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('recommended_at')->default(now());
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'recommendation_type']);
            $table->index(['user_id', 'score']);
            $table->index(['user_id', 'is_dismissed', 'expires_at']);
            $table->unique(['user_id', 'comic_id'], 'user_comic_recommendation');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_recommendations');
    }
};