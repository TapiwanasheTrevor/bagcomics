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
        Schema::table('cms_contents', function (Blueprint $table) {
            $table->string('status')->default('published')->after('is_active'); // draft, published, scheduled, archived
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('scheduled_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->after('created_by');
            $table->integer('current_version')->default(1)->after('updated_by');
            $table->text('change_summary')->nullable()->after('current_version');
            
            $table->index(['status', 'scheduled_at']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_contents', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['created_by']);
            $table->dropColumn([
                'status',
                'published_at',
                'scheduled_at',
                'created_by',
                'updated_by',
                'current_version',
                'change_summary'
            ]);
        });
    }
};
