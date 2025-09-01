import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Trophy, Award, Star, Filter, Grid, List, Search, TrendingUp, Users, BookOpen, Zap, Target } from 'lucide-react';
import NavBar from '@/components/NavBar';
import AchievementCard from '@/components/AchievementCard';
import { type SharedData } from '@/types';

interface Achievement {
    id: number;
    key: string;
    name: string;
    description: string;
    category: string;
    type: string;
    icon: string;
    color: string;
    rarity: string;
    rarity_display: string;
    rarity_color: string;
    points: number;
    requirements?: any[];
    progress?: any[];
    is_unlocked?: boolean;
    unlocked_at?: string;
    is_seen?: boolean;
    unlock_order?: number;
}

interface AchievementStats {
    total_achievements: number;
    unlocked_count: number;
    locked_count: number;
    completion_percentage: number;
    total_points: number;
    recent_achievements: number;
    rarity_breakdown: Record<string, number>;
    category_breakdown: Record<string, number>;
}

interface AchievementData {
    unlocked: Achievement[];
    locked: Achievement[];
    stats: AchievementStats;
}

export default function Achievements() {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [achievementData, setAchievementData] = useState<AchievementData | null>(null);
    const [loading, setLoading] = useState(true);
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [selectedRarity, setSelectedRarity] = useState<string>('all');
    const [showUnlockedOnly, setShowUnlockedOnly] = useState<boolean>(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [searchTerm, setSearchTerm] = useState('');

    const categories = {
        'all': 'All Categories',
        'reading': 'Reading',
        'social': 'Social',
        'collection': 'Collection',
        'engagement': 'Engagement',
        'milestone': 'Milestone',
        'special': 'Special'
    };

    const rarities = {
        'all': 'All Rarities',
        'common': 'Common',
        'uncommon': 'Uncommon',
        'rare': 'Rare',
        'epic': 'Epic',
        'legendary': 'Legendary'
    };

    useEffect(() => {
        fetchAchievements();
    }, []);

    const fetchAchievements = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/achievements', {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setAchievementData(data.data);
            }
        } catch (error) {
            console.error('Error fetching achievements:', error);
        } finally {
            setLoading(false);
        }
    };

    const getFilteredAchievements = () => {
        if (!achievementData) return [];

        let allAchievements = [...achievementData.unlocked, ...achievementData.locked];

        // Filter by search term
        if (searchTerm.trim()) {
            allAchievements = allAchievements.filter(achievement =>
                achievement.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                achievement.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        // Filter by category
        if (selectedCategory !== 'all') {
            allAchievements = allAchievements.filter(achievement =>
                achievement.category === selectedCategory
            );
        }

        // Filter by rarity
        if (selectedRarity !== 'all') {
            allAchievements = allAchievements.filter(achievement =>
                achievement.rarity === selectedRarity
            );
        }

        // Filter by unlock status
        if (showUnlockedOnly) {
            allAchievements = allAchievements.filter(achievement =>
                achievement.is_unlocked
            );
        }

        return allAchievements.sort((a, b) => {
            // Sort unlocked achievements first, then by unlock_order
            if (a.is_unlocked && !b.is_unlocked) return -1;
            if (!a.is_unlocked && b.is_unlocked) return 1;
            return (a.unlock_order || 0) - (b.unlock_order || 0);
        });
    };

    const getRarityColor = (rarity: string) => {
        const colors = {
            'common': 'text-gray-400',
            'uncommon': 'text-green-400',
            'rare': 'text-blue-400',
            'epic': 'text-purple-400',
            'legendary': 'text-yellow-400'
        };
        return colors[rarity as keyof typeof colors] || 'text-gray-400';
    };

    const filteredAchievements = getFilteredAchievements();

    if (!auth.user) {
        window.location.href = '/login';
        return null;
    }

    return (
        <>
            <Head title="Achievements - BagComics">
                <meta name="description" content="Track your reading achievements and unlock badges as you explore comics on BagComics." />
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
                    <div className="text-center mb-8">
                        <div className="flex items-center justify-center space-x-3 mb-4">
                            <div className="p-3 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl">
                                <Trophy className="w-8 h-8 text-white" />
                            </div>
                            <h1 className="text-4xl font-bold bg-gradient-to-r from-yellow-400 to-orange-600 bg-clip-text text-transparent">
                                Achievements
                            </h1>
                        </div>
                        <p className="text-lg text-gray-400 max-w-2xl mx-auto">
                            Unlock badges and earn points as you read, discover, and engage with comics
                        </p>
                    </div>

                    {/* Stats Overview */}
                    {achievementData && (
                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-yellow-400 mb-1">
                                    {achievementData.stats.total_points}
                                </div>
                                <div className="text-sm text-gray-400">Total Points</div>
                            </div>

                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-green-400 mb-1">
                                    {achievementData.stats.unlocked_count}
                                </div>
                                <div className="text-sm text-gray-400">Unlocked</div>
                            </div>

                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-blue-400 mb-1">
                                    {achievementData.stats.completion_percentage}%
                                </div>
                                <div className="text-sm text-gray-400">Complete</div>
                            </div>

                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-purple-400 mb-1">
                                    {achievementData.stats.recent_achievements}
                                </div>
                                <div className="text-sm text-gray-400">This Month</div>
                            </div>

                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-orange-400 mb-1">
                                    {achievementData.stats.rarity_breakdown.legendary || 0}
                                </div>
                                <div className="text-sm text-gray-400">Legendary</div>
                            </div>

                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-red-400 mb-1">
                                    {achievementData.stats.rarity_breakdown.epic || 0}
                                </div>
                                <div className="text-sm text-gray-400">Epic</div>
                            </div>
                        </div>
                    )}

                    {/* Filters */}
                    <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700 mb-8">
                        <div className="space-y-4">
                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                <input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    placeholder="Search achievements..."
                                    className="w-full pl-10 pr-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                                />
                            </div>

                            {/* Filter Options */}
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center space-x-2">
                                    <Filter className="w-4 h-4 text-gray-400" />
                                    <select
                                        value={selectedCategory}
                                        onChange={(e) => setSelectedCategory(e.target.value)}
                                        className="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500"
                                    >
                                        {Object.entries(categories).map(([key, label]) => (
                                            <option key={key} value={key}>{label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Star className="w-4 h-4 text-gray-400" />
                                    <select
                                        value={selectedRarity}
                                        onChange={(e) => setSelectedRarity(e.target.value)}
                                        className="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-500"
                                    >
                                        {Object.entries(rarities).map(([key, label]) => (
                                            <option key={key} value={key}>{label}</option>
                                        ))}
                                    </select>
                                </div>

                                <label className="flex items-center space-x-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={showUnlockedOnly}
                                        onChange={(e) => setShowUnlockedOnly(e.target.checked)}
                                        className="w-4 h-4 text-yellow-500 bg-gray-900 border-gray-700 rounded focus:ring-yellow-500"
                                    />
                                    <span className="text-gray-300">Unlocked only</span>
                                </label>

                                <div className="flex items-center space-x-1 ml-auto">
                                    <button
                                        onClick={() => setViewMode('grid')}
                                        className={`p-2 rounded ${viewMode === 'grid' ? 'bg-yellow-500 text-black' : 'bg-gray-800 text-gray-400'}`}
                                    >
                                        <Grid className="w-4 h-4" />
                                    </button>
                                    <button
                                        onClick={() => setViewMode('list')}
                                        className={`p-2 rounded ${viewMode === 'list' ? 'bg-yellow-500 text-black' : 'bg-gray-800 text-gray-400'}`}
                                    >
                                        <List className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Achievements Grid */}
                    {loading ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {Array.from({ length: 12 }).map((_, i) => (
                                <div key={i} className="bg-gray-800 rounded-xl p-4 animate-pulse">
                                    <div className="flex items-start space-x-3">
                                        <div className="w-12 h-12 bg-gray-700 rounded-lg"></div>
                                        <div className="flex-1">
                                            <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                            <div className="h-3 bg-gray-700 rounded w-3/4 mb-2"></div>
                                            <div className="h-2 bg-gray-700 rounded w-1/2"></div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className={
                            viewMode === 'grid' 
                                ? "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
                                : "space-y-4"
                        }>
                            {filteredAchievements.map((achievement) => (
                                <AchievementCard
                                    key={achievement.id}
                                    achievement={achievement}
                                    variant={viewMode === 'list' ? 'detailed' : 'default'}
                                    showProgress={true}
                                />
                            ))}
                        </div>
                    )}

                    {/* No Results */}
                    {!loading && filteredAchievements.length === 0 && (
                        <div className="text-center py-16">
                            <Trophy className="w-16 h-16 mx-auto text-gray-400 mb-4" />
                            <h3 className="text-xl font-semibold text-white mb-2">No achievements found</h3>
                            <p className="text-gray-400 mb-6">
                                Try adjusting your filters or start reading comics to unlock achievements!
                            </p>
                            <button
                                onClick={() => {
                                    setSelectedCategory('all');
                                    setSelectedRarity('all');
                                    setShowUnlockedOnly(false);
                                    setSearchTerm('');
                                }}
                                className="px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-black font-semibold rounded-lg hover:from-yellow-600 hover:to-orange-600 transition-all duration-300"
                            >
                                Clear Filters
                            </button>
                        </div>
                    )}

                    {/* Rarity Breakdown */}
                    {achievementData && !loading && (
                        <div className="mt-16 bg-gray-800/30 rounded-xl p-6 border border-gray-700">
                            <h3 className="text-xl font-bold text-white mb-6 flex items-center space-x-2">
                                <Award className="w-6 h-6" />
                                <span>Achievement Breakdown</span>
                            </h3>
                            
                            <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                                {Object.entries(achievementData.stats.rarity_breakdown).map(([rarity, count]) => (
                                    <div key={rarity} className="text-center">
                                        <div className={`text-2xl font-bold mb-1 ${getRarityColor(rarity)}`}>
                                            {count}
                                        </div>
                                        <div className="text-sm text-gray-400 capitalize">{rarity}</div>
                                    </div>
                                ))}
                            </div>
                            
                            {/* Progress Bar */}
                            <div className="mb-4">
                                <div className="flex justify-between text-sm mb-2">
                                    <span className="text-gray-400">Overall Progress</span>
                                    <span className="text-white font-medium">
                                        {achievementData.stats.unlocked_count} / {achievementData.stats.total_achievements}
                                    </span>
                                </div>
                                <div className="w-full bg-gray-700 rounded-full h-3">
                                    <div 
                                        className="bg-gradient-to-r from-yellow-500 to-orange-500 h-3 rounded-full transition-all duration-500"
                                        style={{ width: `${achievementData.stats.completion_percentage}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}