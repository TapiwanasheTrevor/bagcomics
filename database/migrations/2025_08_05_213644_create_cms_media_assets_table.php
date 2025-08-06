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
        Schema::create('cms_media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->bigInteger('size'); // in bytes
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable(); // EXIF data, optimization info, etc.
            $table->boolean('is_optimized')->default(false);
            $table->json('variants')->nullable(); // thumbnails, different sizes
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['mime_type']);
            $table->index(['uploaded_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_media_assets');
    }
};
