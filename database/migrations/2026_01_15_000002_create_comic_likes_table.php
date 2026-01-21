<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comic_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'comic_id']);
            $table->index('comic_id');
        });

        // Add likes_count column to comics table for denormalized count
        Schema::table('comics', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('total_ratings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comic_likes');

        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn('likes_count');
        });
    }
};
