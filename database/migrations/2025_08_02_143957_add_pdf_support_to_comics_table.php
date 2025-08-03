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
        Schema::table('comics', function (Blueprint $table) {
            // pdf_file_path already exists, so we'll add the additional columns
            $table->string('pdf_file_name')->nullable()->after('pdf_file_path');
            $table->bigInteger('pdf_file_size')->nullable()->after('pdf_file_name');
            $table->string('pdf_mime_type')->nullable()->after('pdf_file_size');
            $table->boolean('is_pdf_comic')->default(false)->after('pdf_mime_type');
            $table->json('pdf_metadata')->nullable()->after('is_pdf_comic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_file_name',
                'pdf_file_size',
                'pdf_mime_type',
                'is_pdf_comic',
                'pdf_metadata'
            ]);
        });
    }
};
