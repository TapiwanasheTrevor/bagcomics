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
        Schema::create('cms_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_content_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // view, edit, publish, etc.
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('referrer')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['cms_content_id', 'event_type']);
            $table->index(['occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_analytics');
    }
};
