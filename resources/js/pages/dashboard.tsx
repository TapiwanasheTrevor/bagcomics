import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { BookOpen, TrendingUp, Clock, Star, Award, Users } from 'lucide-react';
import NavBar from '@/components/NavBar';
import GamificationSection from '@/components/GamificationSection';
import RecommendationsSection from '@/components/RecommendationsSection';
import { type SharedData } from '@/types';

interface DashboardData {
    recently_read: Array<{
        id: number;
        title: string;
        slug: string;
        cover_image_url?: string;
        author?: string;
        progress?: {
            current_page: number;
            total_pages: number;
            percentage: number;
        };
    }>;
    library_stats: {
        total_comics: number;
        completed_comics: number;
        in_progress_comics: number;
        favorite_comics: number;
        average_rating: number;
        total_reading_time: number;
    };
    reading_activity: Array<{
        date: string;
        comics_read: number;
        minutes_read: number;
    }>;
}

export default function Dashboard() {
    const { auth, dashboard_data } = usePage<SharedData & { dashboard_data?: DashboardData }>().props;
    const [searchQuery, setSearchQuery] = useState('');

    if (!auth.user) {
        window.location.href = '/login';
        return null;
    }

    // Provide default values if dashboard_data is undefined
    const { 
        recently_read = [], 
        library_stats = {
            total_comics: 0,
            completed_comics: 0,
            in_progress_comics: 0,
            favorite_comics: 0,
            average_rating: 0,
            total_reading_time: 0
        }, 
        reading_activity = []
    } = dashboard_data || {};

    return (
        <>
            <Head title="Dashboard - BagComics">
                <meta name="description" content="Your personal comic reading dashboard with stats, streaks, and recommendations." />
            </Head>
            
            <div className="min-h-screen bg-black text-white">
                <NavBar 
                    auth={auth}
                    searchValue={searchQuery}
                    onSearchChange={setSearchQuery}
                    onSearch={(query) => {
                        window.location.href = `/comics?search=${encodeURIComponent(query)}`;
                    }}
                />

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Welcome Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-white mb-2">
                            Welcome back, {auth.user.name.split(' ')[0]}!
                        </h1>
                        <p className="text-gray-400">
                            Let's continue your comic reading journey
                        </p>
                    </div>

                    {/* Quick Stats */}
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <BookOpen className="w-5 h-5 text-blue-400" />
                                <span className="text-sm text-gray-400">Library</span>
                            </div>
                            <div className="text-2xl font-bold text-white">{library_stats.total_comics}</div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <TrendingUp className="w-5 h-5 text-green-400" />
                                <span className="text-sm text-gray-400">Completed</span>
                            </div>
                            <div className="text-2xl font-bold text-white">{library_stats.completed_comics}</div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <Clock className="w-5 h-5 text-orange-400" />
                                <span className="text-sm text-gray-400">In Progress</span>
                            </div>
                            <div className="text-2xl font-bold text-white">{library_stats.in_progress_comics}</div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <Star className="w-5 h-5 text-yellow-400" />
                                <span className="text-sm text-gray-400">Avg Rating</span>
                            </div>
                            <div className="text-2xl font-bold text-white">{(library_stats.average_rating || 0).toFixed(1)}</div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <Award className="w-5 h-5 text-purple-400" />
                                <span className="text-sm text-gray-400">Favorites</span>
                            </div>
                            <div className="text-2xl font-bold text-white">{library_stats.favorite_comics}</div>
                        </div>

                        <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                            <div className="flex items-center space-x-2 mb-2">
                                <Clock className="w-5 h-5 text-red-400" />
                                <span className="text-sm text-gray-400">Hours Read</span>
                            </div>
                            <div className="text-2xl font-bold text-white">
                                {Math.round(library_stats.total_reading_time / 60)}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Left Column */}
                        <div className="lg:col-span-2 space-y-8">
                            {/* Continue Reading */}
                            {recently_read.length > 0 && (
                                <div className="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
                                    <h2 className="text-xl font-bold text-white mb-6 flex items-center space-x-2">
                                        <Clock className="w-5 h-5 text-orange-400" />
                                        <span>Continue Reading</span>
                                    </h2>
                                    
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        {recently_read.slice(0, 4).map(comic => (
                                            <a
                                                key={comic.id}
                                                href={`/comics/${comic.slug}`}
                                                className="group flex items-center space-x-4 p-3 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition-colors"
                                            >
                                                <img
                                                    src={comic.cover_image_url || '/images/default-comic-cover.svg'}
                                                    alt={comic.title}
                                                    className="w-12 h-16 object-cover rounded"
                                                    onError={(e) => {
                                                        const target = e.target as HTMLImageElement;
                                                        target.onerror = null;
                                                        target.src = '/images/default-comic-cover.svg';
                                                    }}
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <h3 className="font-medium text-white truncate group-hover:text-red-400 transition-colors">
                                                        {comic.title}
                                                    </h3>
                                                    <p className="text-sm text-gray-400 truncate">
                                                        {comic.author || 'Unknown Author'}
                                                    </p>
                                                    {comic.progress && (
                                                        <div className="mt-2">
                                                            <div className="w-full bg-gray-700 rounded-full h-1">
                                                                <div 
                                                                    className="bg-red-500 h-1 rounded-full transition-all duration-300"
                                                                    style={{ width: `${comic.progress.percentage}%` }}
                                                                ></div>
                                                            </div>
                                                            <p className="text-xs text-gray-500 mt-1">
                                                                {comic.progress.current_page} / {comic.progress.total_pages} pages
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Personalized Recommendations */}
                            <RecommendationsSection
                                title="Recommended for You"
                                subtitle="Based on your reading preferences"
                                limit={8}
                                showRefresh={false}
                                showReasons={false}
                                className="bg-gray-800/30 rounded-xl p-6 border border-gray-700"
                            />
                        </div>

                        {/* Right Column */}
                        <div className="space-y-6">
                            {/* Gamification Section */}
                            <GamificationSection showCreateGoal={true} />

                            {/* Reading Activity */}
                            <div className="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
                                <h3 className="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                                    <TrendingUp className="w-5 h-5 text-green-400" />
                                    <span>Reading Activity</span>
                                </h3>
                                
                                <div className="space-y-3">
                                    {reading_activity.slice(0, 7).map((activity, index) => (
                                        <div key={activity.date} className="flex items-center justify-between text-sm">
                                            <div className="text-gray-400">
                                                {new Date(activity.date).toLocaleDateString(undefined, { 
                                                    weekday: 'short', 
                                                    month: 'short', 
                                                    day: 'numeric' 
                                                })}
                                            </div>
                                            <div className="flex items-center space-x-4">
                                                <div className="flex items-center space-x-1">
                                                    <BookOpen className="w-3 h-3 text-blue-400" />
                                                    <span className="text-white">{activity.comics_read}</span>
                                                </div>
                                                <div className="flex items-center space-x-1">
                                                    <Clock className="w-3 h-3 text-orange-400" />
                                                    <span className="text-white">{activity.minutes_read}m</span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    
                                    {reading_activity.length === 0 && (
                                        <div className="text-center py-8">
                                            <BookOpen className="w-8 h-8 mx-auto text-gray-400 mb-2" />
                                            <p className="text-gray-400 text-sm">No recent activity</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Quick Actions */}
                            <div className="bg-gray-800/30 rounded-xl p-6 border border-gray-700">
                                <h3 className="text-lg font-semibold text-white mb-4">Quick Actions</h3>
                                
                                <div className="space-y-3">
                                    <a
                                        href="/library"
                                        className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition-colors group"
                                    >
                                        <BookOpen className="w-5 h-5 text-blue-400" />
                                        <span className="text-white group-hover:text-blue-400 transition-colors">My Library</span>
                                    </a>
                                    
                                    <a
                                        href="/recommendations"
                                        className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition-colors group"
                                    >
                                        <Star className="w-5 h-5 text-yellow-400" />
                                        <span className="text-white group-hover:text-yellow-400 transition-colors">Discover Comics</span>
                                    </a>
                                    
                                    <a
                                        href="/comics"
                                        className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg hover:bg-gray-700/50 transition-colors group"
                                    >
                                        <TrendingUp className="w-5 h-5 text-green-400" />
                                        <span className="text-white group-hover:text-green-400 transition-colors">Browse All</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}