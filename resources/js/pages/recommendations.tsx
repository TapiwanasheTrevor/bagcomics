import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Sparkles, TrendingUp, BookOpen, Clock, Users, Filter, BarChart3 } from 'lucide-react';
import NavBar from '@/components/NavBar';
import RecommendationsSection from '@/components/RecommendationsSection';
import { type SharedData } from '@/types';

const RECOMMENDATION_TYPES = [
    { value: 'all', label: 'All Recommendations', icon: Sparkles, description: 'Personalized mix of all types' },
    { value: 'collaborative', label: 'Readers Like You', icon: Users, description: 'Based on similar readers' choices' },
    { value: 'content', label: 'Similar Content', icon: BookOpen, description: 'Based on your reading preferences' },
    { value: 'trending', label: 'Trending Now', icon: TrendingUp, description: 'Popular among all readers' },
    { value: 'new_releases', label: 'New Releases', icon: Clock, description: 'Latest comics in your genres' }
];

export default function Recommendations() {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedType, setSelectedType] = useState('all');
    const [showStats, setShowStats] = useState(false);

    if (!auth.user) {
        return (
            <>
                <Head title="Recommendations - BagComics" />
                <div className="min-h-screen bg-black text-white">
                    <NavBar 
                        auth={auth}
                        searchValue={searchQuery}
                        onSearchChange={setSearchQuery}
                        onSearch={(query) => {
                            window.location.href = `/comics?search=${encodeURIComponent(query)}`;
                        }}
                    />
                    
                    <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div className="text-center">
                            <Sparkles className="w-16 h-16 mx-auto text-red-400 mb-6" />
                            <h1 className="text-3xl font-bold text-white mb-4">
                                Get Personalized Recommendations
                            </h1>
                            <p className="text-lg text-gray-400 mb-8 max-w-2xl mx-auto">
                                Sign in to discover comics tailored to your reading preferences and get intelligent recommendations based on your reading history.
                            </p>
                            <div className="space-y-4 sm:space-y-0 sm:space-x-4 sm:flex sm:justify-center">
                                <a
                                    href="/login"
                                    className="block sm:inline-block px-8 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300"
                                >
                                    Sign In
                                </a>
                                <a
                                    href="/register"
                                    className="block sm:inline-block px-8 py-3 border border-red-500 text-red-400 font-semibold rounded-lg hover:bg-red-500/10 transition-all duration-300"
                                >
                                    Create Account
                                </a>
                            </div>
                        </div>
                    </main>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Recommendations - BagComics">
                <meta name="description" content="Discover your next favorite comic with personalized recommendations powered by AI and community preferences." />
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
                    {/* Header */}
                    <div className="text-center mb-12">
                        <div className="flex items-center justify-center space-x-3 mb-4">
                            <Sparkles className="w-8 h-8 text-red-400" />
                            <h1 className="text-4xl font-bold bg-gradient-to-r from-red-400 to-red-600 bg-clip-text text-transparent">
                                Your Recommendations
                            </h1>
                        </div>
                        <p className="text-lg text-gray-400 max-w-2xl mx-auto">
                            Discover comics tailored to your preferences using advanced AI and community insights
                        </p>
                    </div>

                    {/* Filter Tabs */}
                    <div className="mb-8">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center space-x-2">
                                <Filter className="w-5 h-5 text-gray-400" />
                                <span className="text-sm font-medium text-gray-300">Recommendation Type</span>
                            </div>
                            <button
                                onClick={() => setShowStats(!showStats)}
                                className="flex items-center space-x-2 px-3 py-1.5 text-sm text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                <BarChart3 className="w-4 h-4" />
                                <span>{showStats ? 'Hide' : 'Show'} Stats</span>
                            </button>
                        </div>
                        
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            {RECOMMENDATION_TYPES.map((type) => {
                                const Icon = type.icon;
                                const isSelected = selectedType === type.value;
                                
                                return (
                                    <button
                                        key={type.value}
                                        onClick={() => setSelectedType(type.value)}
                                        className={`p-4 rounded-xl border-2 transition-all duration-200 text-left ${
                                            isSelected
                                                ? 'border-red-500 bg-red-500/10 text-white'
                                                : 'border-gray-700 bg-gray-800/30 text-gray-300 hover:border-gray-600 hover:bg-gray-800/50'
                                        }`}
                                    >
                                        <div className="flex items-start space-x-3">
                                            <Icon className={`w-5 h-5 mt-0.5 ${isSelected ? 'text-red-400' : 'text-gray-400'}`} />
                                            <div className="flex-1 min-w-0">
                                                <h3 className={`font-semibold text-sm mb-1 ${isSelected ? 'text-white' : 'text-gray-300'}`}>
                                                    {type.label}
                                                </h3>
                                                <p className={`text-xs ${isSelected ? 'text-red-100' : 'text-gray-500'}`}>
                                                    {type.description}
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Stats Panel */}
                    {showStats && (
                        <div className="mb-8 p-6 bg-gray-800/50 rounded-xl border border-gray-700">
                            <h3 className="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                                <BarChart3 className="w-5 h-5" />
                                <span>Your Recommendation Stats</span>
                            </h3>
                            {/* Stats content will be loaded via API */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-red-400">85%</div>
                                    <div className="text-sm text-gray-400">Accuracy Rate</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-blue-400">127</div>
                                    <div className="text-sm text-gray-400">Comics Recommended</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-green-400">23</div>
                                    <div className="text-sm text-gray-400">Added to Library</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-purple-400">4.2</div>
                                    <div className="text-sm text-gray-400">Avg Rating Given</div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Recommendations Section */}
                    <RecommendationsSection
                        key={selectedType} // Force re-render when type changes
                        title={RECOMMENDATION_TYPES.find(t => t.value === selectedType)?.label || 'Recommendations'}
                        subtitle={RECOMMENDATION_TYPES.find(t => t.value === selectedType)?.description}
                        type={selectedType as any}
                        limit={24}
                        showRefresh={true}
                        showReasons={true}
                        className="mb-12"
                    />

                    {/* How It Works */}
                    <div className="mt-16 p-8 bg-gradient-to-r from-gray-900/50 to-gray-800/50 rounded-2xl border border-gray-700">
                        <h3 className="text-2xl font-bold text-white mb-6 text-center">How Our Recommendations Work</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div className="text-center">
                                <div className="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <Users className="w-6 h-6 text-blue-400" />
                                </div>
                                <h4 className="font-semibold text-white mb-2">Collaborative Filtering</h4>
                                <p className="text-sm text-gray-400">
                                    Find comics loved by readers with similar tastes to yours
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <BookOpen className="w-6 h-6 text-green-400" />
                                </div>
                                <h4 className="font-semibold text-white mb-2">Content Analysis</h4>
                                <p className="text-sm text-gray-400">
                                    Matches based on genres, authors, and themes you enjoy
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <TrendingUp className="w-6 h-6 text-purple-400" />
                                </div>
                                <h4 className="font-semibold text-white mb-2">Trending Analysis</h4>
                                <p className="text-sm text-gray-400">
                                    Discover what's popular and highly rated right now
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <Sparkles className="w-6 h-6 text-red-400" />
                                </div>
                                <h4 className="font-semibold text-white mb-2">AI Enhancement</h4>
                                <p className="text-sm text-gray-400">
                                    Machine learning improves recommendations over time
                                </p>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}