<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

class DatabaseOptimizationService
{
    /**
     * Add performance indexes to improve query speed
     */
    public function addOptimizedIndexes(): void
    {
        Log::info('Adding optimized database indexes');
        
        // Comics table indexes
        $this->addIndexIfNotExists('comics', 'comics_performance_idx', [
            'is_visible', 'genre', 'view_count', 'average_rating'
        ]);
        
        $this->addIndexIfNotExists('comics', 'comics_search_idx', [
            'title', 'author', 'genre'
        ]);
        
        $this->addIndexIfNotExists('comics', 'comics_published_idx', [
            'is_visible', 'published_at'
        ]);
        
        // Comic views table indexes
        $this->addIndexIfNotExists('comic_views', 'comic_views_trending_idx', [
            'comic_id', 'viewed_at'
        ]);
        
        $this->addIndexIfNotExists('comic_views', 'comic_views_analytics_idx', [
            'viewed_at', 'user_id'
        ]);
        
        // User comic progress table indexes
        $this->addIndexIfNotExists('user_comic_progress', 'user_progress_activity_idx', [
            'user_id', 'last_read_at', 'is_completed'
        ]);
        
        $this->addIndexIfNotExists('user_comic_progress', 'user_progress_comic_idx', [
            'comic_id', 'progress_percentage'
        ]);
        
        // Payments table indexes
        $this->addIndexIfNotExists('payments', 'payments_analytics_idx', [
            'status', 'paid_at', 'amount'
        ]);
        
        $this->addIndexIfNotExists('payments', 'payments_user_idx', [
            'user_id', 'status', 'paid_at'
        ]);
        
        // User libraries table indexes
        $this->addIndexIfNotExists('user_libraries', 'user_libraries_favorites_idx', [
            'user_id', 'is_favorite', 'created_at'
        ]);
        
        // Comic reviews table indexes
        $this->addIndexIfNotExists('comic_reviews', 'comic_reviews_approved_idx', [
            'comic_id', 'is_approved', 'created_at'
        ]);
        
        $this->addIndexIfNotExists('comic_reviews', 'comic_reviews_moderation_idx', [
            'is_flagged', 'is_approved', 'created_at'
        ]);
        
        Log::info('Database indexes optimization completed');
    }

    /**
     * Optimize specific query patterns
     */
    public function optimizeCommonQueries(): array
    {
        $optimizations = [];
        
        // Create materialized view for comic statistics (MySQL equivalent using table)
        $this->createComicStatsTable();
        $optimizations[] = 'Created comic_statistics table for faster analytics';
        
        // Create user activity summary table
        $this->createUserActivitySummaryTable();
        $optimizations[] = 'Created user_activity_summary table for user analytics';
        
        // Optimize reading progress queries
        $this->optimizeReadingProgressQueries();
        $optimizations[] = 'Optimized reading progress queries';
        
        return $optimizations;
    }

    /**
     * Create comic statistics table for faster analytics
     */
    private function createComicStatsTable(): void
    {
        if (!Schema::hasTable('comic_statistics')) {
            Schema::create('comic_statistics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comic_id')->constrained()->onDelete('cascade');
                $table->integer('view_count')->default(0);
                $table->integer('total_readers')->default(0);
                $table->decimal('average_rating', 3, 2)->default(0);
                $table->integer('total_ratings')->default(0);
                $table->integer('purchase_count')->default(0);
                $table->decimal('total_revenue', 10, 2)->default(0);
                $table->integer('completion_count')->default(0);
                $table->decimal('completion_rate', 5, 2)->default(0);
                $table->timestamp('last_updated')->useCurrent();
                
                $table->unique('comic_id');
                $table->index(['view_count', 'average_rating']);
                $table->index(['total_revenue', 'purchase_count']);
            });
            
            // Populate initial data
            $this->populateComicStatistics();
        }
    }

    /**
     * Create user activity summary table
     */
    private function createUserActivitySummaryTable(): void
    {
        if (!Schema::hasTable('user_activity_summary')) {
            Schema::create('user_activity_summary', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('total_comics_read')->default(0);
                $table->integer('total_comics_completed')->default(0);
                $table->integer('total_reading_time_minutes')->default(0);
                $table->integer('current_reading_streak')->default(0);
                $table->date('last_reading_date')->nullable();
                $table->integer('total_purchases')->default(0);
                $table->decimal('total_spent', 10, 2)->default(0);
                $table->timestamp('last_updated')->useCurrent();
                
                $table->unique('user_id');
                $table->index(['total_reading_time_minutes', 'current_reading_streak']);
                $table->index(['total_spent', 'total_purchases']);
            });
            
            // Populate initial data
            $this->populateUserActivitySummary();
        }
    }

    /**
     * Populate comic statistics table
     */
    private function populateComicStatistics(): void
    {
        $sql = "
            INSERT INTO comic_statistics (comic_id, view_count, total_readers, average_rating, total_ratings, purchase_count, total_revenue, completion_count, completion_rate)
            SELECT 
                c.id as comic_id,
                COALESCE(c.view_count, 0) as view_count,
                COALESCE(c.total_readers, 0) as total_readers,
                COALESCE(c.average_rating, 0) as average_rating,
                COALESCE(c.total_ratings, 0) as total_ratings,
                COALESCE(purchase_stats.purchase_count, 0) as purchase_count,
                COALESCE(purchase_stats.total_revenue, 0) as total_revenue,
                COALESCE(progress_stats.completion_count, 0) as completion_count,
                CASE 
                    WHEN COALESCE(c.total_readers, 0) > 0 THEN (COALESCE(progress_stats.completion_count, 0) / c.total_readers) * 100
                    ELSE 0 
                END as completion_rate
            FROM comics c
            LEFT JOIN (
                SELECT 
                    comic_id,
                    COUNT(*) as purchase_count,
                    SUM(amount) as total_revenue
                FROM payments 
                WHERE status = 'succeeded'
                GROUP BY comic_id
            ) purchase_stats ON c.id = purchase_stats.comic_id
            LEFT JOIN (
                SELECT 
                    comic_id,
                    COUNT(*) as completion_count
                FROM user_comic_progress 
                WHERE is_completed = 1
                GROUP BY comic_id
            ) progress_stats ON c.id = progress_stats.comic_id
            ON DUPLICATE KEY UPDATE
                view_count = VALUES(view_count),
                total_readers = VALUES(total_readers),
                average_rating = VALUES(average_rating),
                total_ratings = VALUES(total_ratings),
                purchase_count = VALUES(purchase_count),
                total_revenue = VALUES(total_revenue),
                completion_count = VALUES(completion_count),
                completion_rate = VALUES(completion_rate),
                last_updated = CURRENT_TIMESTAMP
        ";
        
        DB::statement($sql);
    }

    /**
     * Populate user activity summary table
     */
    private function populateUserActivitySummary(): void
    {
        $sql = "
            INSERT INTO user_activity_summary (user_id, total_comics_read, total_comics_completed, total_reading_time_minutes, last_reading_date, total_purchases, total_spent)
            SELECT 
                u.id as user_id,
                COALESCE(reading_stats.total_comics_read, 0) as total_comics_read,
                COALESCE(reading_stats.total_comics_completed, 0) as total_comics_completed,
                COALESCE(reading_stats.total_reading_time_minutes, 0) as total_reading_time_minutes,
                reading_stats.last_reading_date,
                COALESCE(payment_stats.total_purchases, 0) as total_purchases,
                COALESCE(payment_stats.total_spent, 0) as total_spent
            FROM users u
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(DISTINCT comic_id) as total_comics_read,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as total_comics_completed,
                    SUM(reading_time_minutes) as total_reading_time_minutes,
                    MAX(DATE(last_read_at)) as last_reading_date
                FROM user_comic_progress 
                GROUP BY user_id
            ) reading_stats ON u.id = reading_stats.user_id
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as total_purchases,
                    SUM(amount) as total_spent
                FROM payments 
                WHERE status = 'succeeded'
                GROUP BY user_id
            ) payment_stats ON u.id = payment_stats.user_id
            ON DUPLICATE KEY UPDATE
                total_comics_read = VALUES(total_comics_read),
                total_comics_completed = VALUES(total_comics_completed),
                total_reading_time_minutes = VALUES(total_reading_time_minutes),
                last_reading_date = VALUES(last_reading_date),
                total_purchases = VALUES(total_purchases),
                total_spent = VALUES(total_spent),
                last_updated = CURRENT_TIMESTAMP
        ";
        
        DB::statement($sql);
    }

    /**
     * Create optimized views for reading progress
     */
    private function optimizeReadingProgressQueries(): void
    {
        // Create indexed computed columns for common filters
        if (!Schema::hasColumn('user_comic_progress', 'completion_percentage_range')) {
            Schema::table('user_comic_progress', function (Blueprint $table) {
                $table->string('completion_percentage_range', 20)->nullable()->after('progress_percentage');
                $table->index('completion_percentage_range');
            });
            
            // Populate the computed column
            DB::statement("
                UPDATE user_comic_progress 
                SET completion_percentage_range = CASE 
                    WHEN progress_percentage = 0 THEN 'not_started'
                    WHEN progress_percentage < 25 THEN '0-25'
                    WHEN progress_percentage < 50 THEN '25-50'
                    WHEN progress_percentage < 75 THEN '50-75'
                    WHEN progress_percentage < 100 THEN '75-99'
                    ELSE 'completed'
                END
            ");
        }
    }

    /**
     * Add database index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        try {
            // Check if index exists
            $indexes = collect(DB::select("SHOW INDEXES FROM {$table}"))
                ->pluck('Key_name')
                ->unique();
            
            if (!$indexes->contains($indexName)) {
                $columnList = implode(', ', $columns);
                DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
                Log::info("Created index {$indexName} on table {$table}", ['columns' => $columns]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to create index {$indexName} on table {$table}", [
                'error' => $e->getMessage(),
                'columns' => $columns
            ]);
        }
    }

    /**
     * Analyze table performance and suggest optimizations
     */
    public function analyzeTablePerformance(): array
    {
        $analysis = [];
        
        $tables = ['comics', 'users', 'comic_views', 'user_comic_progress', 'payments', 'user_libraries'];
        
        foreach ($tables as $table) {
            try {
                $tableStatus = collect(DB::select("SHOW TABLE STATUS LIKE '{$table}'")->first());
                $indexInfo = collect(DB::select("SHOW INDEXES FROM {$table}"));
                
                $analysis[$table] = [
                    'rows' => $tableStatus['Rows'] ?? 0,
                    'data_length' => $this->formatBytes($tableStatus['Data_length'] ?? 0),
                    'index_length' => $this->formatBytes($tableStatus['Index_length'] ?? 0),
                    'index_count' => $indexInfo->count(),
                    'unique_indexes' => $indexInfo->where('Non_unique', 0)->count(),
                    'fragmentation' => $this->calculateFragmentation($tableStatus),
                ];
                
                // Add suggestions
                $analysis[$table]['suggestions'] = $this->getOptimizationSuggestions($table, $analysis[$table]);
                
            } catch (\Exception $e) {
                $analysis[$table] = ['error' => $e->getMessage()];
            }
        }
        
        return $analysis;
    }

    /**
     * Get optimization suggestions for a table
     */
    private function getOptimizationSuggestions(string $table, array $stats): array
    {
        $suggestions = [];
        
        // Check for tables with high row count but few indexes
        if ($stats['rows'] > 10000 && $stats['index_count'] < 3) {
            $suggestions[] = "Consider adding more indexes for better query performance";
        }
        
        // Check for fragmentation
        if ($stats['fragmentation'] > 15) {
            $suggestions[] = "Table fragmentation is high, consider running OPTIMIZE TABLE";
        }
        
        // Check index to data ratio
        $indexToDataRatio = $stats['rows'] > 0 ? ($stats['index_count'] / $stats['rows']) * 100 : 0;
        if ($indexToDataRatio < 0.1 && $stats['rows'] > 1000) {
            $suggestions[] = "Index coverage might be insufficient for this table size";
        }
        
        return $suggestions;
    }

    /**
     * Calculate table fragmentation percentage
     */
    private function calculateFragmentation(object $tableStatus): float
    {
        $dataLength = $tableStatus->Data_length ?? 0;
        $dataFree = $tableStatus->Data_free ?? 0;
        
        if ($dataLength == 0) return 0;
        
        return ($dataFree / ($dataLength + $dataFree)) * 100;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Update statistics tables (run periodically)
     */
    public function updateStatistics(): void
    {
        Log::info('Updating database statistics');
        
        $this->populateComicStatistics();
        $this->populateUserActivitySummary();
        
        // Update reading streaks
        $this->updateReadingStreaks();
        
        Log::info('Database statistics update completed');
    }

    /**
     * Update user reading streaks
     */
    private function updateReadingStreaks(): void
    {
        $sql = "
            UPDATE user_activity_summary uas
            SET current_reading_streak = (
                SELECT COALESCE(MAX(streak_days), 0)
                FROM (
                    SELECT 
                        user_id,
                        COUNT(*) as streak_days
                    FROM (
                        SELECT 
                            user_id,
                            DATE(last_read_at) as read_date,
                            DATE(last_read_at) - INTERVAL ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY DATE(last_read_at)) DAY as streak_group
                        FROM user_comic_progress 
                        WHERE last_read_at >= CURDATE() - INTERVAL 30 DAY
                        GROUP BY user_id, DATE(last_read_at)
                    ) t
                    WHERE read_date >= CURDATE() - INTERVAL 7 DAY
                    GROUP BY user_id, streak_group
                ) streaks
                WHERE streaks.user_id = uas.user_id
            ),
            last_updated = CURRENT_TIMESTAMP
        ";
        
        DB::statement($sql);
    }

    /**
     * Optimize tables for better performance
     */
    public function optimizeTables(): array
    {
        $results = [];
        $tables = ['comics', 'users', 'comic_views', 'user_comic_progress', 'payments'];
        
        foreach ($tables as $table) {
            try {
                DB::statement("OPTIMIZE TABLE {$table}");
                $results[$table] = 'Optimized successfully';
                Log::info("Optimized table: {$table}");
            } catch (\Exception $e) {
                $results[$table] = 'Optimization failed: ' . $e->getMessage();
                Log::error("Failed to optimize table: {$table}", ['error' => $e->getMessage()]);
            }
        }
        
        return $results;
    }
}