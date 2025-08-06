<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Report - {{ $data['summary']['generated_at'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007cba;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007cba;
            margin-bottom: 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        .summary-card h3 {
            margin-top: 0;
            color: #007cba;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: #007cba;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background-color: #007cba;
            color: white;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .chart-placeholder {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            border-radius: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Comic Platform Analytics Report</h1>
        <p><strong>Period:</strong> {{ $data['summary']['period'] }}</p>
        <p><strong>Generated:</strong> {{ $data['summary']['generated_at'] }}</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <h3>Total Users</h3>
            <div class="metric-value">{{ number_format($data['summary']['platform_metrics']['total_users']) }}</div>
        </div>
        <div class="summary-card">
            <h3>New Users</h3>
            <div class="metric-value">{{ number_format($data['summary']['platform_metrics']['new_users']) }}</div>
        </div>
        <div class="summary-card">
            <h3>Total Revenue</h3>
            <div class="metric-value">${{ number_format($data['summary']['platform_metrics']['total_revenue'], 2) }}</div>
        </div>
        <div class="summary-card">
            <h3>Active Readers</h3>
            <div class="metric-value">{{ number_format($data['summary']['platform_metrics']['active_readers']) }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Revenue Analytics</h2>
        <div class="summary-card">
            <p><strong>Average Transaction Value:</strong> ${{ number_format($data['revenue_analytics']['average_transaction_value'], 2) }}</p>
            <p><strong>Period Revenue:</strong> ${{ number_format($data['summary']['platform_metrics']['revenue_period'], 2) }}</p>
        </div>

        @if(isset($data['revenue_analytics']['top_earning_comics']) && count($data['revenue_analytics']['top_earning_comics']) > 0)
        <h3>Top Earning Comics</h3>
        <table>
            <thead>
                <tr>
                    <th>Comic Title</th>
                    <th>Author</th>
                    <th>Genre</th>
                    <th>Period Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['revenue_analytics']['top_earning_comics'] as $comic)
                <tr>
                    <td>{{ $comic->title }}</td>
                    <td>{{ $comic->author ?? 'Unknown' }}</td>
                    <td>{{ ucfirst($comic->genre ?? 'Unknown') }}</td>
                    <td>${{ number_format($comic->period_revenue, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="section">
        <h2>User Engagement</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Reading Completion Rate</h3>
                <div class="metric-value">{{ number_format($data['user_engagement']['reading_completion_rate'], 1) }}%</div>
            </div>
            <div class="summary-card">
                <h3>Average Session Duration</h3>
                <div class="metric-value">{{ number_format($data['user_engagement']['average_session_duration'], 1) }} min</div>
            </div>
        </div>

        @if(isset($data['user_engagement']['active_users']) && count($data['user_engagement']['active_users']) > 0)
        <h3>Most Active Users</h3>
        <table>
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Reading Sessions</th>
                    <th>Total Reading Time (min)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['user_engagement']['active_users'] as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->reading_sessions }}</td>
                    <td>{{ number_format($user->total_reading_time, 1) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="section">
        <h2>Content Performance</h2>
        
        @if(isset($data['comic_performance']['most_viewed']) && count($data['comic_performance']['most_viewed']) > 0)
        <h3>Most Viewed Comics</h3>
        <table>
            <thead>
                <tr>
                    <th>Comic Title</th>
                    <th>Author</th>
                    <th>Genre</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['comic_performance']['most_viewed'] as $comic)
                <tr>
                    <td>{{ $comic->title }}</td>
                    <td>{{ $comic->author ?? 'Unknown' }}</td>
                    <td>{{ ucfirst($comic->genre ?? 'Unknown') }}</td>
                    <td>{{ number_format($comic->view_count) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(isset($data['comic_performance']['best_rated']) && count($data['comic_performance']['best_rated']) > 0)
        <h3>Best Rated Comics</h3>
        <table>
            <thead>
                <tr>
                    <th>Comic Title</th>
                    <th>Author</th>
                    <th>Average Rating</th>
                    <th>Total Ratings</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['comic_performance']['best_rated'] as $comic)
                <tr>
                    <td>{{ $comic->title }}</td>
                    <td>{{ $comic->author ?? 'Unknown' }}</td>
                    <td>{{ number_format($comic->average_rating, 1) }}/5</td>
                    <td>{{ $comic->total_ratings }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="section">
        <h2>Conversion Metrics</h2>
        <div class="summary-card">
            <p><strong>Overall Conversion Rate:</strong> {{ number_format($data['conversion_metrics']['overall_conversion_rate'], 2) }}%</p>
            <p><strong>Total Views:</strong> {{ number_format($data['conversion_metrics']['total_views']) }}</p>
            <p><strong>Total Purchases:</strong> {{ number_format($data['conversion_metrics']['total_purchases']) }}</p>
        </div>
    </div>

    @if(isset($data['realtime_metrics']))
    <div class="section">
        <h2>Real-time Metrics</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Online Users</h3>
                <div class="metric-value">{{ $data['realtime_metrics']['online_users'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Active Reading Sessions</h3>
                <div class="metric-value">{{ $data['realtime_metrics']['active_reading_sessions'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Revenue Today</h3>
                <div class="metric-value">${{ number_format($data['realtime_metrics']['revenue_today'], 2) }}</div>
            </div>
            <div class="summary-card">
                <h3>New Users Today</h3>
                <div class="metric-value">{{ $data['realtime_metrics']['new_users_today'] }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>This report was automatically generated by the Comic Platform Analytics System</p>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>