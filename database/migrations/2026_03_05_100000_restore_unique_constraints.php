<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any duplicate progress rows before restoring unique constraint
        if (Schema::hasTable('user_comic_progress')) {
            // Keep only the most recently updated row per user+comic
            DB::statement("
                DELETE FROM user_comic_progress
                WHERE id NOT IN (
                    SELECT MAX(id) FROM user_comic_progress
                    GROUP BY user_id, comic_id
                )
            ");

            Schema::table('user_comic_progress', function (Blueprint $table) {
                $table->unique(['user_id', 'comic_id'], 'user_comic_progress_user_comic_unique');
            });
        }

        // Note: payments.stripe_payment_intent_id is intentionally NOT unique
        // because bundle payments create multiple rows sharing the same intent ID.
    }

    public function down(): void
    {
        if (Schema::hasTable('user_comic_progress')) {
            Schema::table('user_comic_progress', function (Blueprint $table) {
                $table->dropUnique('user_comic_progress_user_comic_unique');
            });
        }

        // No payments unique constraint to drop (intentionally not added).
    }
};
