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
        Schema::create('cms_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Unique identifier for content piece
            $table->string('section')->index(); // Section grouping (hero, about, footer, etc.)
            $table->string('type')->default('text'); // text, image, rich_text, json
            $table->string('title')->nullable(); // Human readable title
            $table->text('content')->nullable(); // Main content
            $table->json('metadata')->nullable(); // Additional data (alt text, dimensions, etc.)
            $table->string('image_path')->nullable(); // For image content
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_contents');
    }
};
