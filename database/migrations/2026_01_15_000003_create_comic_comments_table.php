<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comic_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_spoiler')->default(false);
            $table->timestamps();

            $table->index(['comic_id', 'is_approved']);
            $table->index('user_id');
        });

        // Add comments_count column to comics table for denormalized count
        Schema::table('comics', function (Blueprint $table) {
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comic_comments');

        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn('comments_count');
        });
    }
};
