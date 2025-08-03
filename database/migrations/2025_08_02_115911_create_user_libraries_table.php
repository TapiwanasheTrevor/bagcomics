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
        Schema::create('user_libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('comic_id')->constrained()->onDelete('cascade');
            $table->enum('access_type', ['free', 'purchased', 'subscription'])->default('free');
            $table->decimal('purchase_price', 8, 2)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->integer('rating')->nullable(); // 1-5 star rating
            $table->text('review')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'comic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_libraries');
    }
};
