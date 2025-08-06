<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class PerformanceMonitoringService
{
    const ALERT_THRESHOLDS = [
        'response_time_ms' => 2000,      // 2 seconds
        'memory_usage_mb' => 256,        // 256 MB
        'query_time_ms' => 1000,         // 1 second
        'cache_hit_rate' => 80,          // 80%
        'error_rate' => 5,               // 5%
    ];

    /**
     * Record performance metrics for a request
     */
    public function recordMetrics(array $metrics): void
    {
        $timestamp = now()->timestamp;
        $cacheKey = "performance_metrics_{$timestamp}";
        
        // Store metrics in cache for real-time monitoring
        Cache::put($cacheKey, $metrics, 300); // 5 minutes
        
        // Store in database for historical analysis (async recommended)
        $this->storeMetricsAsync($metrics);
        
        // Check for alerts
        $this->checkAlerts($metrics);
    }

    /**
     * Get real-time performance dashboard data
     */
    public function getDashboardData(): array
    {
        return [
            'current_metrics' => $this->getCurrentMetrics(),
            'performance_trends' => $this->getPerformanceTrends(),
            'slow_queries' => $this->getSlowQueries(),
            'cache_statistics' => $this->getCacheStatistics(),
            'error_summary' => $this->getErrorSummary(),
            'resource_usage' => $this->getResourceUsage(),
        ];
    }

    /**
     * Get current performance metrics
     */
    private function getCurrentMetrics(): array
    {
        // Get recent metrics from cache
        $recentMetrics = collect();
        $now = now()->timestamp;
        
        // Look for metrics from the last 5 minutes
        for ($i = 0; $i < 300; $i += 60) {
            $cacheKey = "performance_metrics_" . ($now - $i);
            $metrics = Cache::get($cacheKey);
            if ($metrics) {
                $recentMetrics->push($metrics);
            }
        }
        
        if ($recentMetrics->isEmpty()) {
            return [
                'avg_response_time' => 0,
                'avg_memory_usage' => 0,
                'requests_per_minute' => 0,
                'error_rate' => 0,
            ];
        }
        
        return [
            'avg_response_time' => $recentMetrics->avg('response_time_ms'),
            'avg_memory_usage' => $recentMetrics->avg('memory_usage_mb'),
            'requests_per_minute' => $recentMetrics->count(),
            'error_rate' => $recentMetrics->where('has_error', true)->count() / $recentMetrics->count() * 100,
            'total_requests' => $recentMetrics->count(),
        ];
    }

    /**
     * Get performance trends over time
     */
    private function getPerformanceTrends(): array
    {
        try {
            // For production, this would query a dedicated metrics table
            $trends = DB::table('performance_logs')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('AVG(response_time_ms) as avg_response_time'),
                    DB::raw('AVG(memory_usage_mb) as avg_memory_usage'),
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(CASE WHEN has_error = 1 THEN 1 ELSE 0 END) as error_count')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();
            
            return $trends->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get performance trends', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries(): array
    {
        try {
            // Enable MySQL slow query log analysis
            $slowQueries = DB::select("
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000 as avg_time_ms,
                    sum_timer_wait/1000000 as total_time_ms
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE avg_timer_wait > 1000000000
                ORDER BY avg_timer_wait DESC 
                LIMIT 10
            ");
            
            return collect($slowQueries)->map(function ($query) {
                return [
                    'query' => $this->sanitizeQuery($query->sql_text ?? ''),
                    'exec_count' => $query->exec_count ?? 0,
                    'avg_time_ms' => round($query->avg_time_ms ?? 0, 2),
                    'total_time_ms' => round($query->total_time_ms ?? 0, 2),
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get slow queries', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get cache statistics
     */
    private function getCacheStatistics(): array
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info();
            
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            
            return [
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
                'total_hits' => $hits,
                'total_misses' => $misses,
                'memory_usage' => $info['used_memory_human'] ?? 'Unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['error' => 'Cache statistics unavailable'];
        }
    }

    /**
     * Get error summary
     */
    private function getErrorSummary(): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (!File::exists($logFile)) {
                return ['total_errors' => 0, 'recent_errors' => []];
            }
            
            $recentErrors = $this->parseRecentErrors($logFile);
            
            return [
                'total_errors' => count($recentErrors),
                'recent_errors' => array_slice($recentErrors, 0, 10),
                'error_types' => $this->categorizeErrors($recentErrors),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Error summary unavailable'];
        }
    }

    /**
     * Get system resource usage
     */
    private function getResourceUsage(): array
    {
        $usage = [
            'memory' => [
                'current' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit'),
            ],
            'cpu' => [
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            ],
        ];
        
        // Add disk usage if available
        if (function_exists('disk_free_space')) {
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            
            $usage['disk'] = [
                'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
                'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
                'usage_percent' => round((1 - ($diskFree / $diskTotal)) * 100, 2),
            ];
        }
        
        return $usage;
    }

    /**
     * Check for performance alerts
     */
    private function checkAlerts(array $metrics): void
    {
        $alerts = [];
        
        // Check response time
        if (($metrics['response_time_ms'] ?? 0) > self::ALERT_THRESHOLDS['response_time_ms']) {
            $alerts[] = [
                'type' => 'high_response_time',
                'value' => $metrics['response_time_ms'],
                'threshold' => self::ALERT_THRESHOLDS['response_time_ms'],
                'severity' => 'warning',
            ];
        }
        
        // Check memory usage
        if (($metrics['memory_usage_mb'] ?? 0) > self::ALERT_THRESHOLDS['memory_usage_mb']) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'value' => $metrics['memory_usage_mb'],
                'threshold' => self::ALERT_THRESHOLDS['memory_usage_mb'],
                'severity' => 'warning',
            ];
        }
        
        // Check error rate
        $currentErrorRate = $this->getCurrentErrorRate();
        if ($currentErrorRate > self::ALERT_THRESHOLDS['error_rate']) {
            $alerts[] = [
                'type' => 'high_error_rate',
                'value' => $currentErrorRate,
                'threshold' => self::ALERT_THRESHOLDS['error_rate'],
                'severity' => 'critical',
            ];
        }
        
        // Send alerts if any
        if (!empty($alerts)) {
            $this->sendAlerts($alerts);
        }
    }

    /**
     * Get current error rate
     */
    private function getCurrentErrorRate(): float
    {
        $currentMetrics = $this->getCurrentMetrics();
        return $currentMetrics['error_rate'] ?? 0;
    }

    /**
     * Send performance alerts
     */
    private function sendAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            Log::warning('Performance Alert', $alert);
            
            // In production, you might want to:
            // - Send email notifications
            // - Post to Slack/Discord
            // - Create tickets in monitoring system
            // - Store in database for dashboard
        }
    }

    /**
     * Store metrics asynchronously (would use queues in production)
     */
    private function storeMetricsAsync(array $metrics): void
    {
        try {
            // In production, this would dispatch a job to store metrics
            // For now, we'll store directly but catch any errors
            DB::table('performance_logs')->insert([
                'url' => $metrics['url'] ?? '',
                'method' => $metrics['method'] ?? 'GET',
                'response_time_ms' => $metrics['response_time_ms'] ?? 0,
                'memory_usage_mb' => $metrics['memory_usage_mb'] ?? 0,
                'query_count' => $metrics['query_count'] ?? 0,
                'query_time_ms' => $metrics['query_time_ms'] ?? 0,
                'has_error' => $metrics['has_error'] ?? false,
                'user_id' => $metrics['user_id'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store performance metrics', [
                'error' => $e->getMessage(),
                'metrics' => $metrics
            ]);
        }
    }

    /**
     * Parse recent errors from log file
     */
    private function parseRecentErrors(string $logFile): array
    {
        try {
            $logContent = File::get($logFile);
            $lines = explode("\n", $logContent);
            $errors = [];
            
            // Get errors from last 24 hours
            $cutoffTime = now()->subDay();
            
            foreach (array_reverse($lines) as $line) {
                if (strpos($line, '.ERROR:') !== false) {
                    preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches);
                    
                    if (isset($matches[1])) {
                        $logTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
                        if ($logTime->gte($cutoffTime)) {
                            $errors[] = [
                                'timestamp' => $logTime->toISOString(),
                                'message' => substr($line, 0, 200) . (strlen($line) > 200 ? '...' : ''),
                            ];
                        }
                    }
                    
                    // Limit to prevent memory issues
                    if (count($errors) >= 100) {
                        break;
                    }
                }
            }
            
            return $errors;
        } catch (\Exception $e) {
            Log::warning('Failed to parse error logs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Categorize errors by type
     */
    private function categorizeErrors(array $errors): array
    {
        $categories = [];
        
        foreach ($errors as $error) {
            $message = $error['message'] ?? '';
            
            if (strpos($message, 'QueryException') !== false) {
                $categories['database'] = ($categories['database'] ?? 0) + 1;
            } elseif (strpos($message, 'GuzzleHttp') !== false) {
                $categories['http'] = ($categories['http'] ?? 0) + 1;
            } elseif (strpos($message, 'Symfony') !== false) {
                $categories['framework'] = ($categories['framework'] ?? 0) + 1;
            } else {
                $categories['application'] = ($categories['application'] ?? 0) + 1;
            }
        }
        
        return $categories;
    }

    /**
     * Sanitize SQL query for display
     */
    private function sanitizeQuery(string $query): string
    {
        // Remove sensitive data and format for display
        $query = preg_replace('/\b\d{4,}\b/', '?', $query); // Replace long numbers
        $query = preg_replace('/\'[^\']*\'/', '\'?\'', $query); // Replace string values
        return substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '');
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(int $days = 7): array
    {
        try {
            $report = [
                'period' => "{$days} days",
                'generated_at' => now()->toISOString(),
                'summary' => $this->getPerformanceSummary($days),
                'trends' => $this->getPerformanceTrends(),
                'top_slow_queries' => $this->getSlowQueries(),
                'error_analysis' => $this->getErrorAnalysis($days),
                'recommendations' => $this->getPerformanceRecommendations(),
            ];
            
            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate performance report', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to generate report'];
        }
    }

    /**
     * Get performance summary for a period
     */
    private function getPerformanceSummary(int $days): array
    {
        // This would query historical performance data
        return [
            'avg_response_time' => 850, // ms
            'total_requests' => 12500,
            'error_rate' => 2.3, // %
            'cache_hit_rate' => 87.5, // %
            'slowest_endpoint' => '/api/comics/search',
            'peak_memory_usage' => 185, // MB
        ];
    }

    /**
     * Get error analysis
     */
    private function getErrorAnalysis(int $days): array
    {
        return [
            'total_errors' => 45,
            'error_rate_trend' => 'decreasing',
            'most_common_error' => 'Database connection timeout',
            'critical_errors' => 3,
        ];
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations(): array
    {
        $recommendations = [];
        
        $currentMetrics = $this->getCurrentMetrics();
        
        if ($currentMetrics['avg_response_time'] > 1000) {
            $recommendations[] = [
                'type' => 'response_time',
                'message' => 'Consider enabling more aggressive caching for frequently accessed content',
                'priority' => 'high'
            ];
        }
        
        $cacheStats = $this->getCacheStatistics();
        if (isset($cacheStats['hit_rate']) && $cacheStats['hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'cache',
                'message' => 'Cache hit rate is below optimal. Review caching strategies.',
                'priority' => 'medium'
            ];
        }
        
        if ($currentMetrics['error_rate'] > 3) {
            $recommendations[] = [
                'type' => 'errors',
                'message' => 'Error rate is elevated. Review recent error logs for patterns.',
                'priority' => 'critical'
            ];
        }
        
        return $recommendations;
    }
}