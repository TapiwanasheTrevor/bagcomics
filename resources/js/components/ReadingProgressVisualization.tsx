import React, { useState, useEffect } from 'react';
import { Clock, BookOpen, Target, TrendingUp, Calendar, Award, BarChart3, Eye, Bookmark } from 'lucide-react';

interface ReadingSession {
    id: string;
    started_at: string;
    ended_at?: string;
    start_page: number;
    end_page?: number;
    pages_read: number;
    duration_minutes: number;
    is_active: boolean;
}

interface ReadingProgress {
    current_page: number;
    total_pages: number;
    progress_percentage: number;
    reading_time_minutes: number;
    is_completed: boolean;
    first_read_at?: string;
    last_read_at?: string;
    completed_at?: string;
    reading_sessions: ReadingSession[];
    total_reading_sessions: number;
    average_session_duration: number;
    pages_per_session_avg: number;
    reading_speed_pages_per_minute: number;
    bookmark_count: number;
}

interface ReadingProgressVisualizationProps {
    comicSlug: string;
    currentPage: number;
    totalPages: number;
}

const ReadingProgressVisualization: React.FC<ReadingProgressVisualizationProps> = ({
    comicSlug,
    currentPage,
    totalPages
}) => {
    const [progress, setProgress] = useState<ReadingProgress | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [activeTab, setActiveTab] = useState<'overview' | 'sessions' | 'stats'>('overview');

    useEffect(() => {
        loadProgress();
    }, [comicSlug]);

    const loadProgress = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/comics/${comicSlug}/progress`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setProgress(data);
            } else {
                console.error('Failed to load reading progress');
            }
        } catch (error) {
            console.error('Error loading reading progress:', error);
        } finally {
            setLoading(false);
        }
    };

    const formatDuration = (minutes: number): string => {
        if (minutes < 60) {
            return `${Math.round(minutes)}m`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.round(minutes % 60);
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getProgressColor = (percentage: number): string => {
        if (percentage >= 100) return 'text-green-400';
        if (percentage >= 75) return 'text-emerald-400';
        if (percentage >= 50) return 'text-yellow-400';
        if (percentage >= 25) return 'text-orange-400';
        return 'text-red-400';
    };

    const getProgressBgColor = (percentage: number): string => {
        if (percentage >= 100) return 'bg-green-500';
        if (percentage >= 75) return 'bg-emerald-500';
        if (percentage >= 50) return 'bg-yellow-500';
        if (percentage >= 25) return 'bg-orange-500';
        return 'bg-red-500';
    };

    const calculateReadingStreak = (): number => {
        if (!progress?.reading_sessions) return 0;
        
        const sessions = progress.reading_sessions
            .filter(s => !s.is_active)
            .sort((a, b) => new Date(b.started_at).getTime() - new Date(a.started_at).getTime());
        
        let streak = 0;
        const currentDate = new Date();
        currentDate.setHours(0, 0, 0, 0);
        
        for (const session of sessions) {
            const sessionDate = new Date(session.started_at);
            sessionDate.setHours(0, 0, 0, 0);
            
            const daysDiff = Math.floor((currentDate.getTime() - sessionDate.getTime()) / (1000 * 60 * 60 * 24));
            
            if (daysDiff === streak) {
                streak++;
            } else if (daysDiff > streak) {
                break;
            }
        }
        
        return streak;
    };

    const getReadingVelocity = (): { trend: 'up' | 'down' | 'stable'; change: number } => {
        if (!progress?.reading_sessions || progress.reading_sessions.length < 2) {
            return { trend: 'stable', change: 0 };
        }

        const sessions = progress.reading_sessions
            .filter(s => !s.is_active && s.pages_read > 0)
            .sort((a, b) => new Date(a.started_at).getTime() - new Date(b.started_at).getTime());

        if (sessions.length < 2) return { trend: 'stable', change: 0 };

        const recentSessions = sessions.slice(-3);
        const olderSessions = sessions.slice(-6, -3);

        if (olderSessions.length === 0) return { trend: 'stable', change: 0 };

        const recentAvg = recentSessions.reduce((sum, s) => sum + s.pages_read, 0) / recentSessions.length;
        const olderAvg = olderSessions.reduce((sum, s) => sum + s.pages_read, 0) / olderSessions.length;

        const change = ((recentAvg - olderAvg) / olderAvg) * 100;

        if (Math.abs(change) < 5) return { trend: 'stable', change: 0 };
        return { trend: change > 0 ? 'up' : 'down', change: Math.abs(change) };
    };

    if (loading) {
        return (
            <div className="w-80 bg-gray-800 border-l border-gray-700 flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
            </div>
        );
    }

    if (!progress) {
        return (
            <div className="w-80 bg-gray-800 border-l border-gray-700 flex items-center justify-center p-4">
                <p className="text-gray-400 text-center">No reading progress data available</p>
            </div>
        );
    }

    const progressPercentage = (currentPage / totalPages) * 100;
    const readingStreak = calculateReadingStreak();
    const velocity = getReadingVelocity();

    return (
        <div className="w-80 bg-gray-800 border-l border-gray-700 flex flex-col h-full">
            {/* Header */}
            <div className="p-4 border-b border-gray-700">
                <h3 className="text-lg font-semibold text-white mb-4">Reading Progress</h3>
                
                {/* Tab Navigation */}
                <div className="flex space-x-1 bg-gray-700 rounded-lg p-1">
                    {[
                        { id: 'overview', label: 'Overview', icon: Eye },
                        { id: 'sessions', label: 'Sessions', icon: Clock },
                        { id: 'stats', label: 'Stats', icon: BarChart3 }
                    ].map(({ id, label, icon: Icon }) => (
                        <button
                            key={id}
                            onClick={() => setActiveTab(id as any)}
                            className={`flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                                activeTab === id
                                    ? 'bg-emerald-600 text-white'
                                    : 'text-gray-300 hover:text-white hover:bg-gray-600'
                            }`}
                        >
                            <Icon className="h-3 w-3" />
                            <span className="hidden sm:inline">{label}</span>
                        </button>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-auto p-4">
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        {/* Main Progress */}
                        <div className="text-center">
                            <div className="relative w-24 h-24 mx-auto mb-4">
                                <svg className="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="40"
                                        stroke="currentColor"
                                        strokeWidth="8"
                                        fill="none"
                                        className="text-gray-700"
                                    />
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="40"
                                        stroke="currentColor"
                                        strokeWidth="8"
                                        fill="none"
                                        strokeDasharray={`${progressPercentage * 2.51} 251`}
                                        className={getProgressColor(progressPercentage)}
                                        strokeLinecap="round"
                                    />
                                </svg>
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <span className={`text-xl font-bold ${getProgressColor(progressPercentage)}`}>
                                        {Math.round(progressPercentage)}%
                                    </span>
                                </div>
                            </div>
                            <p className="text-gray-300">
                                Page {currentPage} of {totalPages}
                            </p>
                            {progress.is_completed && (
                                <div className="flex items-center justify-center gap-2 mt-2 text-green-400">
                                    <Award className="h-4 w-4" />
                                    <span className="text-sm font-medium">Completed!</span>
                                </div>
                            )}
                        </div>

                        {/* Quick Stats */}
                        <div className="grid grid-cols-2 gap-4">
                            <div className="bg-gray-700 rounded-lg p-3 text-center">
                                <Clock className="h-5 w-5 text-emerald-400 mx-auto mb-1" />
                                <p className="text-sm text-gray-400">Reading Time</p>
                                <p className="text-lg font-semibold text-white">
                                    {formatDuration(progress.reading_time_minutes)}
                                </p>
                            </div>
                            <div className="bg-gray-700 rounded-lg p-3 text-center">
                                <BookOpen className="h-5 w-5 text-blue-400 mx-auto mb-1" />
                                <p className="text-sm text-gray-400">Sessions</p>
                                <p className="text-lg font-semibold text-white">
                                    {progress.total_reading_sessions}
                                </p>
                            </div>
                            <div className="bg-gray-700 rounded-lg p-3 text-center">
                                <Target className="h-5 w-5 text-purple-400 mx-auto mb-1" />
                                <p className="text-sm text-gray-400">Streak</p>
                                <p className="text-lg font-semibold text-white">
                                    {readingStreak} day{readingStreak !== 1 ? 's' : ''}
                                </p>
                            </div>
                            <div className="bg-gray-700 rounded-lg p-3 text-center">
                                <Bookmark className="h-5 w-5 text-yellow-400 mx-auto mb-1" />
                                <p className="text-sm text-gray-400">Bookmarks</p>
                                <p className="text-lg font-semibold text-white">
                                    {progress.bookmark_count}
                                </p>
                            </div>
                        </div>

                        {/* Reading Velocity */}
                        {velocity.trend !== 'stable' && (
                            <div className="bg-gray-700 rounded-lg p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <TrendingUp className={`h-4 w-4 ${velocity.trend === 'up' ? 'text-green-400' : 'text-red-400'}`} />
                                    <span className="text-sm font-medium text-white">Reading Velocity</span>
                                </div>
                                <p className="text-sm text-gray-300">
                                    Your reading pace is {velocity.trend === 'up' ? 'increasing' : 'decreasing'} by{' '}
                                    <span className={velocity.trend === 'up' ? 'text-green-400' : 'text-red-400'}>
                                        {Math.round(velocity.change)}%
                                    </span>{' '}
                                    compared to previous sessions.
                                </p>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'sessions' && (
                    <div className="space-y-4">
                        {progress.reading_sessions.length === 0 ? (
                            <p className="text-gray-400 text-center py-8">No reading sessions yet</p>
                        ) : (
                            progress.reading_sessions
                                .filter(session => !session.is_active)
                                .sort((a, b) => new Date(b.started_at).getTime() - new Date(a.started_at).getTime())
                                .map((session, index) => (
                                    <div key={session.id} className="bg-gray-700 rounded-lg p-4">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium text-white">
                                                Session #{progress.reading_sessions.length - index}
                                            </span>
                                            <span className="text-xs text-gray-400">
                                                {formatDate(session.started_at)}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-3 gap-4 text-sm">
                                            <div>
                                                <p className="text-gray-400">Duration</p>
                                                <p className="text-white font-medium">
                                                    {formatDuration(session.duration_minutes)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-gray-400">Pages</p>
                                                <p className="text-white font-medium">
                                                    {session.pages_read}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-gray-400">Range</p>
                                                <p className="text-white font-medium">
                                                    {session.start_page}-{session.end_page}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))
                        )}
                    </div>
                )}

                {activeTab === 'stats' && (
                    <div className="space-y-6">
                        {/* Reading Statistics */}
                        <div className="space-y-4">
                            <div className="bg-gray-700 rounded-lg p-4">
                                <h4 className="text-sm font-medium text-white mb-3">Reading Speed</h4>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-400">Pages per minute</span>
                                    <span className="text-white font-medium">
                                        {progress.reading_speed_pages_per_minute.toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            <div className="bg-gray-700 rounded-lg p-4">
                                <h4 className="text-sm font-medium text-white mb-3">Session Averages</h4>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-400">Duration</span>
                                        <span className="text-white font-medium">
                                            {formatDuration(progress.average_session_duration)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-400">Pages per session</span>
                                        <span className="text-white font-medium">
                                            {Math.round(progress.pages_per_session_avg)}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-gray-700 rounded-lg p-4">
                                <h4 className="text-sm font-medium text-white mb-3">Timeline</h4>
                                <div className="space-y-2">
                                    {progress.first_read_at && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-gray-400">Started reading</span>
                                            <span className="text-white font-medium">
                                                {formatDate(progress.first_read_at)}
                                            </span>
                                        </div>
                                    )}
                                    {progress.last_read_at && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-gray-400">Last read</span>
                                            <span className="text-white font-medium">
                                                {formatDate(progress.last_read_at)}
                                            </span>
                                        </div>
                                    )}
                                    {progress.completed_at && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-gray-400">Completed</span>
                                            <span className="text-green-400 font-medium">
                                                {formatDate(progress.completed_at)}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Achievement Badges */}
                        <div className="bg-gray-700 rounded-lg p-4">
                            <h4 className="text-sm font-medium text-white mb-3">Achievements</h4>
                            <div className="grid grid-cols-2 gap-2">
                                {progress.is_completed && (
                                    <div className="flex items-center gap-2 p-2 bg-green-900/30 rounded-lg">
                                        <Award className="h-4 w-4 text-green-400" />
                                        <span className="text-xs text-green-400">Completed</span>
                                    </div>
                                )}
                                {readingStreak >= 7 && (
                                    <div className="flex items-center gap-2 p-2 bg-purple-900/30 rounded-lg">
                                        <Target className="h-4 w-4 text-purple-400" />
                                        <span className="text-xs text-purple-400">Week Streak</span>
                                    </div>
                                )}
                                {progress.bookmark_count >= 5 && (
                                    <div className="flex items-center gap-2 p-2 bg-yellow-900/30 rounded-lg">
                                        <Bookmark className="h-4 w-4 text-yellow-400" />
                                        <span className="text-xs text-yellow-400">Bookworm</span>
                                    </div>
                                )}
                                {progress.total_reading_sessions >= 10 && (
                                    <div className="flex items-center gap-2 p-2 bg-blue-900/30 rounded-lg">
                                        <BookOpen className="h-4 w-4 text-blue-400" />
                                        <span className="text-xs text-blue-400">Dedicated</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ReadingProgressVisualization;