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
        // Reading Lists table
        Schema::create('reading_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('cover_image_url')->nullable();
            $table->json('tags')->nullable();
            $table->integer('followers_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('comics_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_public']);
            $table->index('is_featured');
            $table->index('followers_count');
        });

        // Reading List Comics (pivot table)
        Schema::create('reading_list_comics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->integer('position');
            $table->timestamp('added_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['reading_list_id', 'comic_id']);
            $table->index(['reading_list_id', 'position']);
        });

        // Reading List Followers
        Schema::create('reading_list_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['reading_list_id', 'user_id']);
        });

        // Reading List Likes
        Schema::create('reading_list_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['reading_list_id', 'user_id']);
        });

        // Reading List Activities
        Schema::create('reading_list_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->foreignId('comic_id')->nullable()->constrained()->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reading_list_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // User Follows (for social features)
        Schema::create('user_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('following_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('followed_at');
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index('follower_id');
            $table->index('following_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('reading_list_activities');
        Schema::dropIfExists('reading_list_likes');
        Schema::dropIfExists('reading_list_followers');
        Schema::dropIfExists('reading_list_comics');
        Schema::dropIfExists('reading_lists');
    }
};
