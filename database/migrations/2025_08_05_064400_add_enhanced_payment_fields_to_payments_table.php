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
            $table->string('stripe_refund_id')->nullable()->after('stripe_payment_method_id');
            $table->decimal('refund_amount', 8, 2)->nullable()->after('amount');
            $table->enum('payment_type', ['single', 'bundle', 'subscription'])->default('single')->after('status');
            $table->string('subscription_type')->nullable()->after('payment_type');
            $table->decimal('bundle_discount_percent', 5, 2)->nullable()->after('subscription_type');
            $table->integer('retry_count')->default(0)->after('failure_reason');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            
            // Add indexes for better performance
            $table->index('payment_type');
            $table->index('subscription_type');
            $table->index(['user_id', 'payment_type']);
            $table->index(['status', 'payment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_type']);
            $table->dropIndex(['user_id', 'payment_type']);
            $table->dropIndex(['subscription_type']);
            $table->dropIndex(['payment_type']);
            
            $table->dropColumn([
                'stripe_refund_id',
                'refund_amount',
                'payment_type',
                'subscription_type',
                'bundle_discount_percent',
                'retry_count',
                'last_retry_at',
            ]);
        });
    }
};
