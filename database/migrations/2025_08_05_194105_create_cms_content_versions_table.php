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
        Schema::create('cms_content_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_content_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_summary')->nullable();
            $table->timestamps();
            
            $table->index(['cms_content_id', 'version_number']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_content_versions');
    }
};
