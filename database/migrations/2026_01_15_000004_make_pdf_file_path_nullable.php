<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            // Make pdf_file_path nullable since we now support image-based comics
            $table->string('pdf_file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->string('pdf_file_path')->nullable(false)->change();
        });
    }
};
