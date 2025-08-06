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
        Schema::table('payments', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['comic_id']);
            
            // Make comic_id nullable
            $table->foreignId('comic_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('comic_id')->references('id')->on('comics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['comic_id']);
            
            // Make comic_id not nullable again
            $table->foreignId('comic_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('comic_id')->references('id')->on('comics')->onDelete('cascade');
        });
    }
};