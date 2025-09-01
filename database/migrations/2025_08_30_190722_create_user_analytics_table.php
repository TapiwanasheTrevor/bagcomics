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
        Schema::create('user_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('metric_type'); // 'reading', 'engagement', 'discovery', 'social'
            $table->string('metric_name'); // 'comics_read', 'reading_time_minutes', etc.
            $table->decimal('value', 10, 2); // Numeric value for the metric
            $table->json('additional_data')->nullable(); // Extra context data
            $table->date('date'); // Date for this metric
            $table->string('period')->default('daily'); // 'daily', 'weekly', 'monthly'
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'metric_type']);
            $table->index(['user_id', 'date']);
            $table->index(['metric_type', 'metric_name']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_analytics');
    }
};
