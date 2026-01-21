<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comic_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('page_number');
            $table->string('image_url', 500);
            $table->string('image_path', 500)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();

            $table->unique(['comic_id', 'page_number']);
            $table->index('comic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comic_pages');
    }
};
