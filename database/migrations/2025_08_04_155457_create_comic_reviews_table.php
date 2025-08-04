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
        Schema::create('comic_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->integer('rating')->unsigned(); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('content');
            $table->boolean('is_spoiler')->default(false);
            $table->integer('helpful_votes')->default(0);
            $table->integer('total_votes')->default(0);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'comic_id']);
            $table->index(['comic_id', 'is_approved']);
            $table->index(['rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comic_reviews');
    }
};
