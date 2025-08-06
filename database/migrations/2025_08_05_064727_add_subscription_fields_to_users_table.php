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
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_type')->nullable()->after('avatar_path');
            $table->enum('subscription_status', ['active', 'canceled', 'expired'])->nullable()->after('subscription_type');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            
            // Add indexes
            $table->index('subscription_status');
            $table->index('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['subscription_expires_at']);
            $table->dropIndex(['subscription_status']);
            
            $table->dropColumn([
                'subscription_type',
                'subscription_status',
                'subscription_expires_at',
            ]);
        });
    }
};
