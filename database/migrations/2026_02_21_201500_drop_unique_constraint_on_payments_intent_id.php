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
            try {
                $table->dropUnique('payments_stripe_payment_intent_id_unique');
            } catch (\Throwable $e) {
                // Constraint may already be absent in some environments.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('stripe_payment_intent_id');
        });
    }
};
