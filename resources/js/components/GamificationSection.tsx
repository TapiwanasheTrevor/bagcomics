import React, { useState, useEffect } from 'react';
import { Zap, Target, Trophy, Calendar, Clock, BookOpen, Star, Users, TrendingUp, Award } from 'lucide-react';

interface Streak {
    id: number;
    type: string;
    current_count: number;
    longest_count: number;
    status: string;
    days_until_break: number;
    started_at: string;
    last_activity: string;
    display_name: string;
    description: string;
    icon: string;
    color: string;
}

interface Goal {
    id: number;
    type: string;
    title: string;
    description: string;
    target_value: number;
    current_progress: number;
    progress_percentage: number;
    remaining: number;
    days_remaining: number;
    is_completed: boolean;
    is_overdue: boolean;
    period_type: string;
    period_start: string;
    period_end: string;
    completed_at?: string;
    difficulty: string;
    icon: string;
    color: string;
}

interface GamificationStats {
    streaks: {
        total_active: number;
        longest_streak: number;
        current_best_streak: number;
        streaks_broken_this_month: number;
    };
    goals: {
        total_set: number;
        completed: number;
        active: number;
        completion_rate: number;
        this_month_completed: number;
    };
    achievements: {
        level: number;
        total_points: number;
        next_level_points: number;
        recent_achievements: Array<{
            type: string;
            title: string;
            achieved_at: string;
            icon: string;
            color: string;
        }>;
    };
    reading_stats: {
        total_comics: number;
        completed_comics: number;
        average_rating: number;
        favorite_genres: string[];
        reading_days_this_month: number;
        pages_read_this_month: number;
    };
}

interface GamificationSectionProps {
    className?: string;
    showCreateGoal?: boolean;
}

const iconMap: Record<string, any> = {
    'book-open': BookOpen,
    'check-circle': Target,
    'star': Star,
    'compass': Users,
    'zap': Zap,
    'book': BookOpen,
    'file-text': BookOpen,
    'clock': Clock,
    'check-square': Target,
    'users': Users,
    'map': TrendingUp,
    'edit-3': Star,
    'target': Target
};

const colorMap: Record<string, string> = {
    'blue': 'text-blue-400 border-blue-400 bg-blue-500/10',
    'green': 'text-green-400 border-green-400 bg-green-500/10',
    'yellow': 'text-yellow-400 border-yellow-400 bg-yellow-500/10',
    'purple': 'text-purple-400 border-purple-400 bg-purple-500/10',
    'red': 'text-red-400 border-red-400 bg-red-500/10',
    'indigo': 'text-indigo-400 border-indigo-400 bg-indigo-500/10',
    'emerald': 'text-emerald-400 border-emerald-400 bg-emerald-500/10',
    'pink': 'text-pink-400 border-pink-400 bg-pink-500/10',
    'orange': 'text-orange-400 border-orange-400 bg-orange-500/10',
    'gray': 'text-gray-400 border-gray-400 bg-gray-500/10'
};

export default function GamificationSection({ className = '', showCreateGoal = true }: GamificationSectionProps) {
    const [streaks, setStreaks] = useState<Streak[]>([]);
    const [goals, setGoals] = useState<Goal[]>([]);
    const [stats, setStats] = useState<GamificationStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'overview' | 'streaks' | 'goals'>('overview');
    const [showCreateGoalModal, setShowCreateGoalModal] = useState(false);

    useEffect(() => {
        fetchGamificationData();
    }, []);

    const fetchGamificationData = async () => {
        try {
            setLoading(true);
            
            const [streaksRes, goalsRes, statsRes] = await Promise.all([
                fetch('/api/gamification/streaks', {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' }
                }),
                fetch('/api/gamification/goals', {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' }
                }),
                fetch('/api/gamification/stats', {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json' }
                })
            ]);

            if (streaksRes.ok) {
                const streaksData = await streaksRes.json();
                setStreaks(streaksData.data?.streaks || []);
            }

            if (goalsRes.ok) {
                const goalsData = await goalsRes.json();
                setGoals(goalsData.data?.goals || []);
            }

            if (statsRes.ok) {
                const statsData = await statsRes.json();
                setStats(statsData.data || null);
            }
        } catch (error) {
            console.error('Error fetching gamification data:', error);
        } finally {
            setLoading(false);
        }
    };

    const getStreakStatusColor = (status: string) => {
        switch (status) {
            case 'active_today': return 'text-green-400';
            case 'at_risk': return 'text-yellow-400';
            case 'broken': return 'text-red-400';
            default: return 'text-gray-400';
        }
    };

    const getProgressBarColor = (percentage: number, isCompleted: boolean) => {
        if (isCompleted) return 'bg-green-500';
        if (percentage >= 80) return 'bg-blue-500';
        if (percentage >= 50) return 'bg-yellow-500';
        return 'bg-gray-500';
    };

    if (loading) {
        return (
            <div className={`space-y-6 ${className}`}>
                <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-gray-700 rounded animate-pulse"></div>
                    <div className="h-6 bg-gray-700 rounded w-32 animate-pulse"></div>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="bg-gray-800 rounded-xl p-4 animate-pulse">
                            <div className="h-4 bg-gray-700 rounded w-3/4 mb-2"></div>
                            <div className="h-3 bg-gray-700 rounded w-1/2"></div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <Trophy className="w-8 h-8 text-yellow-400" />
                    <h2 className="text-2xl font-bold bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
                        Your Progress
                    </h2>
                </div>
                
                {showCreateGoal && (
                    <button
                        onClick={() => setShowCreateGoalModal(true)}
                        className="px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300"
                    >
                        Set Goal
                    </button>
                )}
            </div>

            {/* Tab Navigation */}
            <div className="flex space-x-1 bg-gray-800/50 p-1 rounded-lg">
                {[
                    { key: 'overview', label: 'Overview', icon: Trophy },
                    { key: 'streaks', label: 'Streaks', icon: Zap },
                    { key: 'goals', label: 'Goals', icon: Target }
                ].map(tab => {
                    const Icon = tab.icon;
                    return (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key as any)}
                            className={`flex-1 flex items-center justify-center space-x-2 px-4 py-2 rounded-md transition-all duration-200 ${
                                activeTab === tab.key
                                    ? 'bg-red-500 text-white'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-700'
                            }`}
                        >
                            <Icon className="w-4 h-4" />
                            <span className="text-sm font-medium">{tab.label}</span>
                        </button>
                    );
                })}
            </div>

            {/* Overview Tab */}
            {activeTab === 'overview' && stats && (
                <div className="space-y-6">
                    {/* Quick Stats */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-blue-500/20 rounded-lg">
                                    <Award className="w-5 h-5 text-blue-400" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-white">{stats.achievements?.level || 1}</div>
                                    <div className="text-sm text-gray-400">Level</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-yellow-500/20 rounded-lg">
                                    <Zap className="w-5 h-5 text-yellow-400" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-white">{stats.streaks?.current_best_streak || 0}</div>
                                    <div className="text-sm text-gray-400">Best Streak</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-green-500/20 rounded-lg">
                                    <Target className="w-5 h-5 text-green-400" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-white">{stats.goals?.completion_rate || 0}%</div>
                                    <div className="text-sm text-gray-400">Goal Rate</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-purple-500/20 rounded-lg">
                                    <BookOpen className="w-5 h-5 text-purple-400" />
                                </div>
                                <div>
                                    <div className="text-2xl font-bold text-white">{stats.reading_stats?.reading_days_this_month || 0}</div>
                                    <div className="text-sm text-gray-400">Days Read</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Recent Achievements */}
                    {stats.achievements?.recent_achievements?.length > 0 && (
                        <div className="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
                            <h3 className="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                                <Award className="w-5 h-5 text-yellow-400" />
                                <span>Recent Achievements</span>
                            </h3>
                            <div className="space-y-3">
                                {stats.achievements?.recent_achievements?.slice(0, 3).map((achievement, index) => {
                                    const Icon = iconMap[achievement.icon] || Award;
                                    return (
                                        <div key={index} className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg">
                                            <div className={`p-2 rounded-lg ${colorMap[achievement.color] || colorMap.gray}`}>
                                                <Icon className="w-4 h-4" />
                                            </div>
                                            <div className="flex-1">
                                                <div className="font-medium text-white">{achievement.title}</div>
                                                <div className="text-sm text-gray-400">
                                                    {new Date(achievement.achieved_at).toLocaleDateString()}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Streaks Tab */}
            {activeTab === 'streaks' && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {streaks?.map(streak => {
                        const Icon = iconMap[streak.icon] || Zap;
                        return (
                            <div key={streak.id} className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                <div className="flex items-center justify-between mb-4">
                                    <div className="flex items-center space-x-3">
                                        <div className={`p-2 rounded-lg ${colorMap[streak.color] || colorMap.gray}`}>
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-white">{streak.display_name}</h3>
                                            <p className="text-sm text-gray-400">{streak.description}</p>
                                        </div>
                                    </div>
                                    <div className={`text-lg font-bold ${getStreakStatusColor(streak.status)}`}>
                                        {streak.current_count}
                                    </div>
                                </div>
                                
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-400">Best: {streak.longest_count} days</span>
                                        <span className={`${getStreakStatusColor(streak.status)}`}>
                                            {streak.status === 'active_today' ? 'Active' : 
                                             streak.status === 'at_risk' ? 'At Risk' : 'Broken'}
                                        </span>
                                    </div>
                                    
                                    {streak.status === 'at_risk' && (
                                        <div className="text-xs text-yellow-400 bg-yellow-500/10 px-2 py-1 rounded">
                                            � {streak.days_until_break} day(s) to continue streak
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                    
                    {(!streaks || streaks.length === 0) && (
                        <div className="col-span-full text-center py-12">
                            <Zap className="w-12 h-12 mx-auto text-gray-400 mb-4" />
                            <h3 className="text-lg font-semibold text-white mb-2">No Active Streaks</h3>
                            <p className="text-gray-400">Start reading to build your streaks!</p>
                        </div>
                    )}
                </div>
            )}

            {/* Goals Tab */}
            {activeTab === 'goals' && (
                <div className="space-y-6">
                    {goals?.map(goal => {
                        const Icon = iconMap[goal.icon] || Target;
                        return (
                            <div key={goal.id} className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="flex items-start space-x-3">
                                        <div className={`p-2 rounded-lg ${colorMap[goal.color] || colorMap.gray}`}>
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-white mb-1">{goal.title}</h3>
                                            <p className="text-sm text-gray-400 mb-2">{goal.description}</p>
                                            <div className="flex items-center space-x-4 text-xs text-gray-500">
                                                <span>{goal.period_type}</span>
                                                <span>{goal.days_remaining} days left</span>
                                                <span className={`px-2 py-1 rounded ${
                                                    goal.difficulty === 'easy' ? 'bg-green-500/20 text-green-400' :
                                                    goal.difficulty === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
                                                    'bg-red-500/20 text-red-400'
                                                }`}>
                                                    {goal.difficulty}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {goal.is_completed && (
                                        <div className="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm font-medium">
                                            Completed!
                                        </div>
                                    )}
                                    
                                    {goal.is_overdue && !goal.is_completed && (
                                        <div className="px-3 py-1 bg-red-500/20 text-red-400 rounded-full text-sm font-medium">
                                            Overdue
                                        </div>
                                    )}
                                </div>
                                
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-400">Progress</span>
                                        <span className="text-white font-medium">
                                            {goal.current_progress} / {goal.target_value}
                                        </span>
                                    </div>
                                    
                                    <div className="w-full bg-gray-700 rounded-full h-2">
                                        <div 
                                            className={`h-2 rounded-full transition-all duration-300 ${getProgressBarColor(goal.progress_percentage, goal.is_completed)}`}
                                            style={{ width: `${Math.min(goal.progress_percentage, 100)}%` }}
                                        ></div>
                                    </div>
                                    
                                    <div className="text-xs text-gray-400">
                                        {goal.progress_percentage.toFixed(1)}% complete
                                        {!goal.is_completed && ` " ${goal.remaining} remaining`}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                    
                    {(!goals || goals.length === 0) && (
                        <div className="text-center py-12">
                            <Target className="w-12 h-12 mx-auto text-gray-400 mb-4" />
                            <h3 className="text-lg font-semibold text-white mb-2">No Active Goals</h3>
                            <p className="text-gray-400 mb-6">Set your first goal to start tracking progress!</p>
                            <button
                                onClick={() => setShowCreateGoalModal(true)}
                                className="px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300"
                            >
                                Create Your First Goal
                            </button>
                        </div>
                    )}
                </div>
            )}

            {/* Goal Creation Modal */}
            {showCreateGoalModal && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-gray-900 rounded-xl max-w-md w-full p-6 border border-gray-700">
                        <h3 className="text-xl font-bold text-white mb-4">Create New Goal</h3>
                        
                        <form onSubmit={async (e) => {
                            e.preventDefault();
                            const formData = new FormData(e.currentTarget);
                            
                            try {
                                const response = await fetch('/api/gamification/goals', {
                                    method: 'POST',
                                    credentials: 'include',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                    },
                                    body: JSON.stringify({
                                        type: formData.get('type'),
                                        target_value: parseInt(formData.get('target_value') as string),
                                        period: formData.get('period'),
                                        title: formData.get('title'),
                                        description: formData.get('description')
                                    })
                                });
                                
                                if (response.ok) {
                                    setShowCreateGoalModal(false);
                                    fetchGamificationData(); // Refresh the data
                                }
                            } catch (error) {
                                console.error('Failed to create goal:', error);
                            }
                        }}>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-1">Goal Title</label>
                                    <input
                                        type="text"
                                        name="title"
                                        required
                                        className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"
                                        placeholder="e.g., Read 5 comics this week"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-1">Goal Type</label>
                                    <select
                                        name="type"
                                        required
                                        className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"
                                    >
                                        <option value="comics_read">Comics to Read</option>
                                        <option value="pages_read">Pages to Read</option>
                                        <option value="reading_streak">Daily Reading Streak</option>
                                        <option value="reading_time">Reading Time (minutes)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-1">Target</label>
                                    <input
                                        type="number"
                                        name="target_value"
                                        required
                                        min="1"
                                        className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"
                                        placeholder="e.g., 5"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-1">Time Period</label>
                                    <select
                                        name="period"
                                        required
                                        className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-1">Description (optional)</label>
                                    <textarea
                                        name="description"
                                        rows={3}
                                        className="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"
                                        placeholder="Add a description for your goal..."
                                    />
                                </div>
                            </div>
                            
                            <div className="flex space-x-3 mt-6">
                                <button
                                    type="submit"
                                    className="flex-1 px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300"
                                >
                                    Create Goal
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowCreateGoalModal(false)}
                                    className="flex-1 px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}