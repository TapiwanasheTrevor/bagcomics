<?php

use App\Models\Comic;
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
        // Find all comics that don't have a slug or have an empty slug
        $comics = Comic::whereNull('slug')
                      ->orWhere('slug', '')
                      ->orWhere('slug', '""')
                      ->get();

        foreach ($comics as $comic) {
            // Save the comic to trigger the slug generation
            $comic->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't need to reverse this migration
        // Slugs can remain populated
    }
};