import React, { useState, useEffect, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { 
    Clock, 
    BookOpen, 
    Calendar, 
    TrendingUp, 
    Eye, 
    Star,
    ChevronRight,
    Filter,
    BarChart3,
    Activity,
    Target,
    Award,
    Trophy
} from 'lucide-react';

interface ReadingSession {
    id: number;
    comic_id: number;
    user_id: number;
    started_at: string;
    ended_at?: string;
    pages_read: number;
    reading_time_minutes: number;
    device_type: string;
    comic: {
        id: number;
        title: string;
        slug: string;
        author?: string;
        cover_image_url?: string;
        page_count?: number;
    };
}

interface ReadingStats {
    total_reading_time: number;
    comics_read: number;
    pages_read: number;
    average_session_time: number;
    reading_streak: number;
    favorite_genre: string;
    most_read_comic: string;
    reading_goals_met: number;
}

interface ReadingHistoryProps {
    className?: string;
}

const ReadingHistory: React.FC<ReadingHistoryProps> = ({ className = '' }) => {
    const [sessions, setSessions] = useState<ReadingSession[]>([]);
    const [stats, setStats] = useState<ReadingStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [timeRange, setTimeRange] = useState<'week' | 'month' | 'year' | 'all'>('month');
    const [viewMode, setViewMode] = useState<'sessions' | 'stats'>('sessions');

    useEffect(() => {
        fetchReadingHistory();
        fetchReadingStats();
    }, [timeRange]);

    const fetchReadingHistory = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                time_range: timeRange,
                limit: '50'
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/reading-history?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) throw new Error('Failed to fetch reading history');

            const data = await response.json();
            setSessions(data.sessions || []);
        } catch (error) {
            console.error('Error fetching reading history:', error);
            setSessions([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchReadingStats = async () => {
        try {
            const params = new URLSearchParams({
                time_range: timeRange
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/reading-stats?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) throw new Error('Failed to fetch reading stats');

            const data = await response.json();
            setStats(data.stats);
        } catch (error) {
            console.error('Error fetching reading stats:', error);
            setStats(null);
        }
    };

    const formatDuration = (minutes: number): string => {
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    };

    const formatDate = (dateString: string): string => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

        if (diffInDays === 0) return 'Today';
        if (diffInDays === 1) return 'Yesterday';
        if (diffInDays < 7) return `${diffInDays} days ago`;
        
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
        });
    };

    const groupedSessions = useMemo(() => {
        const groups: { [key: string]: ReadingSession[] } = {};
        
        sessions.forEach(session => {
            const date = new Date(session.started_at).toDateString();
            if (!groups[date]) groups[date] = [];
            groups[date].push(session);
        });

        return Object.entries(groups).sort(([a], [b]) => 
            new Date(b).getTime() - new Date(a).getTime()
        );
    }, [sessions]);

    const ReadingSessionCard: React.FC<{ session: ReadingSession }> = ({ session }) => (
        <div className="bg-gray-800/50 rounded-lg p-4 hover:bg-gray-800/70 transition-colors">
            <div className="flex items-center space-x-4">
                <Link href={`/comics/${session.comic.slug}`} className="flex-shrink-0">
                    {session.comic.cover_image_url ? (
                        <img
                            src={session.comic.cover_image_url}
                            alt={session.comic.title}
                            className="w-12 h-16 object-cover rounded-lg"
                        />
                    ) : (
                        <div className="w-12 h-16 bg-gradient-to-br from-gray-700 to-gray-800 rounded-lg flex items-center justify-center">
                            <BookOpen className="h-6 w-6 text-gray-500" />
                        </div>
                    )}
                </Link>

                <div className="flex-1 min-w-0">
                    <Link 
                        href={`/comics/${session.comic.slug}`}
                        className="font-medium text-white hover:text-emerald-400 transition-colors line-clamp-1"
                    >
                        {session.comic.title}
                    </Link>
                    {session.comic.author && (
                        <p className="text-sm text-gray-400 mt-1">{session.comic.author}</p>
                    )}

                    <div className="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                        <div className="flex items-center space-x-1">
                            <Clock className="h-4 w-4" />
                            <span>{formatDuration(session.reading_time_minutes)}</span>
                        </div>
                        <div className="flex items-center space-x-1">
                            <BookOpen className="h-4 w-4" />
                            <span>{session.pages_read} pages</span>
                        </div>
                        <div className="flex items-center space-x-1">
                            <Calendar className="h-4 w-4" />
                            <span>{new Date(session.started_at).toLocaleTimeString('en-US', { 
                                hour: 'numeric', 
                                minute: '2-digit',
                                hour12: true 
                            })}</span>
                        </div>
                    </div>
                </div>

                <div className="flex items-center text-gray-400">
                    <ChevronRight className="h-5 w-5" />
                </div>
            </div>
        </div>
    );

    const StatsCard: React.FC<{ 
        title: string; 
        value: string | number; 
        icon: React.ReactNode; 
        color: string;
        subtitle?: string;
    }> = ({ title, value, icon, color, subtitle }) => (
        <div className="bg-gray-800/50 rounded-lg p-6">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-gray-400 mb-1">{title}</p>
                    <p className={`text-2xl font-bold ${color}`}>{value}</p>
                    {subtitle && (
                        <p className="text-xs text-gray-500 mt-1">{subtitle}</p>
                    )}
                </div>
                <div className={`p-3 rounded-lg ${color.replace('text-', 'bg-').replace('400', '500/20')}`}>
                    {icon}
                </div>
            </div>
        </div>
    );

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-2xl font-bold text-white mb-2">Reading History</h2>
                    <p className="text-gray-400">Track your reading progress and habits</p>
                </div>

                <div className="flex items-center space-x-2">
                    {/* View Mode Toggle */}
                    <div className="flex bg-gray-800/50 rounded-lg p-1">
                        <button
                            onClick={() => setViewMode('sessions')}
                            className={`px-3 py-1 rounded-md text-sm font-medium transition-colors ${
                                viewMode === 'sessions'
                                    ? 'bg-emerald-500 text-white'
                                    : 'text-gray-400 hover:text-white'
                            }`}
                        >
                            Sessions
                        </button>
                        <button
                            onClick={() => setViewMode('stats')}
                            className={`px-3 py-1 rounded-md text-sm font-medium transition-colors ${
                                viewMode === 'stats'
                                    ? 'bg-emerald-500 text-white'
                                    : 'text-gray-400 hover:text-white'
                            }`}
                        >
                            Statistics
                        </button>
                    </div>

                    {/* Time Range Filter */}
                    <select
                        value={timeRange}
                        onChange={(e) => setTimeRange(e.target.value as any)}
                        className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>

            {/* Statistics View */}
            {viewMode === 'stats' && stats && (
                <div className="space-y-6">
                    {/* Key Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatsCard
                            title="Total Reading Time"
                            value={formatDuration(stats.total_reading_time)}
                            icon={<Clock className="h-6 w-6" />}
                            color="text-emerald-400"
                        />
                        <StatsCard
                            title="Comics Read"
                            value={stats.comics_read}
                            icon={<BookOpen className="h-6 w-6" />}
                            color="text-blue-400"
                        />
                        <StatsCard
                            title="Pages Read"
                            value={stats.pages_read.toLocaleString()}
                            icon={<Eye className="h-6 w-6" />}
                            color="text-purple-400"
                        />
                        <StatsCard
                            title="Reading Streak"
                            value={`${stats.reading_streak} days`}
                            icon={<TrendingUp className="h-6 w-6" />}
                            color="text-orange-400"
                        />
                    </div>

                    {/* Additional Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <StatsCard
                            title="Average Session"
                            value={formatDuration(stats.average_session_time)}
                            icon={<BarChart3 className="h-6 w-6" />}
                            color="text-yellow-400"
                            subtitle="Per reading session"
                        />
                        <StatsCard
                            title="Favorite Genre"
                            value={stats.favorite_genre || 'N/A'}
                            icon={<Star className="h-6 w-6" />}
                            color="text-pink-400"
                            subtitle="Most read genre"
                        />
                        <StatsCard
                            title="Goals Achieved"
                            value={stats.reading_goals_met}
                            icon={<Award className="h-6 w-6" />}
                            color="text-green-400"
                            subtitle="Reading milestones"
                        />
                    </div>

                    {/* Most Read Comic */}
                    {stats.most_read_comic && (
                        <div className="bg-gray-800/50 rounded-lg p-6">
                            <h3 className="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                                <Trophy className="h-5 w-5 text-yellow-400" />
                                <span>Most Read Comic</span>
                            </h3>
                            <p className="text-emerald-400 font-medium">{stats.most_read_comic}</p>
                        </div>
                    )}
                </div>
            )}

            {/* Sessions View */}
            {viewMode === 'sessions' && (
                <>
                    {loading ? (
                        <div className="space-y-4">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="animate-pulse bg-gray-800/50 rounded-lg p-4">
                                    <div className="flex items-center space-x-4">
                                        <div className="w-12 h-16 bg-gray-700 rounded-lg"></div>
                                        <div className="flex-1">
                                            <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                            <div className="h-3 bg-gray-700 rounded w-1/2 mb-2"></div>
                                            <div className="h-3 bg-gray-700 rounded w-1/3"></div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : groupedSessions.length > 0 ? (
                        <div className="space-y-6">
                            {groupedSessions.map(([date, dateSessions]) => (
                                <div key={date}>
                                    <h3 className="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                                        <Calendar className="h-5 w-5 text-emerald-400" />
                                        <span>{formatDate(date)}</span>
                                        <span className="text-sm text-gray-400 font-normal">
                                            ({dateSessions.length} session{dateSessions.length !== 1 ? 's' : ''})
                                        </span>
                                    </h3>
                                    <div className="space-y-3">
                                        {dateSessions.map(session => (
                                            <ReadingSessionCard key={session.id} session={session} />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-12">
                            <Activity className="h-16 w-16 text-gray-500 mx-auto mb-4" />
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">No Reading History</h3>
                            <p className="text-gray-500 mb-6">
                                Start reading comics to see your reading history here
                            </p>
                            <Link
                                href="/comics"
                                className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300"
                            >
                                <BookOpen className="w-5 h-5" />
                                <span>Start Reading</span>
                            </Link>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

export default ReadingHistory;