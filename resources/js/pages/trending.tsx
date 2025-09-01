import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { TrendingUp, Clock, Star, Users, BookOpen, Filter, Calendar, Award, Zap, BarChart3 } from 'lucide-react';
import NavBar from '@/components/NavBar';
import { type SharedData } from '@/types';

interface TrendingComic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    genre?: string;
    description?: string;
    cover_image_url?: string;
    average_rating: number;
    total_ratings: number;
    total_readers: number;
    page_count?: number;
    is_free: boolean;
    price?: number;
    published_at: string;
    recent_additions?: number;
    recent_avg_rating?: number;
    trending_score?: number;
    trending_rank?: number;
}

interface TrendingData {
    trending_comics: TrendingComic[];
    timeframe: string;
    total: number;
    generated_at: string;
}

const TIMEFRAMES = [
    { value: 'day', label: 'Today', icon: Clock, color: 'blue' },
    { value: 'week', label: 'This Week', icon: Calendar, color: 'green' },
    { value: 'month', label: 'This Month', icon: TrendingUp, color: 'purple' },
    { value: 'all_time', label: 'All Time', icon: Award, color: 'yellow' }
];

const GENRES = [
    'All Genres',
    'Action',
    'Adventure',
    'Comedy',
    'Drama',
    'Fantasy',
    'Horror',
    'Mystery',
    'Romance',
    'Sci-Fi',
    'Thriller'
];

export default function Trending() {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [trendingData, setTrendingData] = useState<TrendingData | null>(null);
    const [loading, setLoading] = useState(true);
    const [selectedTimeframe, setSelectedTimeframe] = useState('week');
    const [selectedGenre, setSelectedGenre] = useState('All Genres');
    const [sortBy, setSortBy] = useState<'trending' | 'rating' | 'readers'>('trending');
    const [showStats, setShowStats] = useState(false);

    useEffect(() => {
        fetchTrendingComics();
    }, [selectedTimeframe]);

    const fetchTrendingComics = async () => {
        try {
            setLoading(true);
            const response = await fetch(`/api/recommendations/trending?timeframe=${selectedTimeframe}&limit=50`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setTrendingData(data.data);
            }
        } catch (error) {
            console.error('Error fetching trending comics:', error);
        } finally {
            setLoading(false);
        }
    };

    const getFilteredAndSortedComics = () => {
        if (!trendingData) return [];
        
        let comics = [...trendingData.trending_comics];
        
        // Filter by genre
        if (selectedGenre !== 'All Genres') {
            comics = comics.filter(comic => comic.genre === selectedGenre);
        }
        
        // Sort
        switch (sortBy) {
            case 'rating':
                comics.sort((a, b) => (b.recent_avg_rating || b.average_rating) - (a.recent_avg_rating || a.average_rating));
                break;
            case 'readers':
                comics.sort((a, b) => (b.recent_additions || 0) - (a.recent_additions || 0));
                break;
            case 'trending':
            default:
                comics.sort((a, b) => (b.trending_score || 0) - (a.trending_score || 0));
                break;
        }
        
        // Add ranking
        return comics.map((comic, index) => ({
            ...comic,
            trending_rank: index + 1
        }));
    };

    const getRankBadgeColor = (rank: number) => {
        if (rank === 1) return 'bg-gradient-to-r from-yellow-400 to-yellow-600 text-black';
        if (rank === 2) return 'bg-gradient-to-r from-gray-300 to-gray-400 text-black';
        if (rank === 3) return 'bg-gradient-to-r from-orange-400 to-orange-600 text-white';
        if (rank <= 10) return 'bg-gradient-to-r from-purple-500 to-purple-600 text-white';
        return 'bg-gray-700 text-gray-300';
    };

    const getRankIcon = (rank: number) => {
        if (rank === 1) return '>G';
        if (rank === 2) return '>H';
        if (rank === 3) return '>I';
        if (rank <= 10) return '=%';
        return null;
    };

    const filteredComics = getFilteredAndSortedComics();

    return (
        <>
            <Head title="Trending Comics - BagComics">
                <meta name="description" content="Discover the hottest trending comics that everyone is reading right now on BagComics." />
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
                            <div className="p-3 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl">
                                <TrendingUp className="w-8 h-8 text-white" />
                            </div>
                            <h1 className="text-4xl font-bold bg-gradient-to-r from-orange-400 to-red-600 bg-clip-text text-transparent">
                                Trending Now
                            </h1>
                        </div>
                        <p className="text-lg text-gray-400 max-w-2xl mx-auto">
                            Discover what's hot in the comic world. See what everyone's reading right now!
                        </p>
                    </div>

                    {/* Filters and Controls */}
                    <div className="space-y-6 mb-8">
                        {/* Timeframe Selection */}
                        <div className="flex flex-wrap items-center justify-center gap-3">
                            {TIMEFRAMES.map((timeframe) => {
                                const Icon = timeframe.icon;
                                const isSelected = selectedTimeframe === timeframe.value;
                                
                                return (
                                    <button
                                        key={timeframe.value}
                                        onClick={() => setSelectedTimeframe(timeframe.value)}
                                        className={`flex items-center space-x-2 px-5 py-2.5 rounded-lg font-medium transition-all duration-200 ${
                                            isSelected
                                                ? `bg-${timeframe.color}-500 text-white shadow-lg shadow-${timeframe.color}-500/30`
                                                : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
                                        }`}
                                    >
                                        <Icon className="w-4 h-4" />
                                        <span>{timeframe.label}</span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Secondary Filters */}
                        <div className="flex flex-wrap items-center justify-center gap-4">
                            {/* Genre Filter */}
                            <div className="flex items-center space-x-2">
                                <Filter className="w-4 h-4 text-gray-400" />
                                <select
                                    value={selectedGenre}
                                    onChange={(e) => setSelectedGenre(e.target.value)}
                                    className="bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                >
                                    {GENRES.map(genre => (
                                        <option key={genre} value={genre}>{genre}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Sort Options */}
                            <div className="flex items-center space-x-2">
                                <BarChart3 className="w-4 h-4 text-gray-400" />
                                <select
                                    value={sortBy}
                                    onChange={(e) => setSortBy(e.target.value as any)}
                                    className="bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                >
                                    <option value="trending">Trending Score</option>
                                    <option value="rating">Highest Rated</option>
                                    <option value="readers">Most Readers</option>
                                </select>
                            </div>

                            {/* Stats Toggle */}
                            <button
                                onClick={() => setShowStats(!showStats)}
                                className="flex items-center space-x-2 px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition-colors"
                            >
                                <Zap className="w-4 h-4" />
                                <span>{showStats ? 'Hide' : 'Show'} Stats</span>
                            </button>
                        </div>
                    </div>

                    {/* Trending Stats Overview */}
                    {showStats && !loading && trendingData && (
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-white mb-1">{trendingData.total}</div>
                                <div className="text-sm text-gray-400">Trending Comics</div>
                            </div>
                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-yellow-400 mb-1">
                                    {filteredComics[0]?.average_rating.toFixed(1) || '0.0'}
                                </div>
                                <div className="text-sm text-gray-400">#1 Comic Rating</div>
                            </div>
                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-green-400 mb-1">
                                    {filteredComics.reduce((sum, c) => sum + (c.recent_additions || 0), 0)}
                                </div>
                                <div className="text-sm text-gray-400">Total New Readers</div>
                            </div>
                            <div className="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
                                <div className="text-3xl font-bold text-purple-400 mb-1">
                                    {(filteredComics.reduce((sum, c) => sum + c.average_rating, 0) / filteredComics.length).toFixed(1)}
                                </div>
                                <div className="text-sm text-gray-400">Avg Rating</div>
                            </div>
                        </div>
                    )}

                    {/* Loading State */}
                    {loading && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {Array.from({ length: 12 }).map((_, i) => (
                                <div key={i} className="bg-gray-800 rounded-xl overflow-hidden animate-pulse">
                                    <div className="aspect-[2/3] bg-gray-700"></div>
                                    <div className="p-4 space-y-3">
                                        <div className="h-4 bg-gray-700 rounded"></div>
                                        <div className="h-3 bg-gray-700 rounded w-3/4"></div>
                                        <div className="h-3 bg-gray-700 rounded w-1/2"></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Trending Comics Grid */}
                    {!loading && filteredComics.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {filteredComics.map((comic) => (
                                <div
                                    key={comic.id}
                                    className="group relative bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 hover:transform hover:scale-[1.02]"
                                >
                                    {/* Rank Badge */}
                                    {comic.trending_rank && comic.trending_rank <= 10 && (
                                        <div className={`absolute top-3 left-3 z-10 px-3 py-1.5 rounded-full font-bold text-sm ${getRankBadgeColor(comic.trending_rank)}`}>
                                            <span className="flex items-center space-x-1">
                                                {getRankIcon(comic.trending_rank) && <span>{getRankIcon(comic.trending_rank)}</span>}
                                                <span>#{comic.trending_rank}</span>
                                            </span>
                                        </div>
                                    )}

                                    <a href={`/comics/${comic.slug}`} className="block">
                                        {/* Cover Image */}
                                        <div className="relative aspect-[2/3] overflow-hidden">
                                            <img
                                                src={comic.cover_image_url || '/images/default-comic-cover.svg'}
                                                alt={comic.title}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                loading="lazy"
                                                onError={(e) => {
                                                    const target = e.target as HTMLImageElement;
                                                    target.onerror = null;
                                                    target.src = '/images/default-comic-cover.svg';
                                                }}
                                            />
                                            
                                            {/* Trending Indicator */}
                                            <div className="absolute bottom-2 right-2 px-2 py-1 bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs font-bold rounded-full">
                                                =% {comic.recent_additions || 0} new readers
                                            </div>
                                        </div>

                                        {/* Content */}
                                        <div className="p-4">
                                            <h3 className="font-bold text-white mb-1 line-clamp-1 group-hover:text-red-400 transition-colors">
                                                {comic.title}
                                            </h3>
                                            
                                            <p className="text-gray-400 text-sm mb-2 truncate">
                                                {comic.author || 'Unknown Author'}
                                            </p>

                                            {/* Stats */}
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center space-x-3">
                                                    <div className="flex items-center space-x-1">
                                                        <Star className="w-3 h-3 text-yellow-400 fill-current" />
                                                        <span className="text-xs text-gray-300">
                                                            {(comic.recent_avg_rating || comic.average_rating).toFixed(1)}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center space-x-1">
                                                        <Users className="w-3 h-3 text-blue-400" />
                                                        <span className="text-xs text-gray-300">
                                                            {comic.total_readers || comic.total_ratings}
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                {comic.genre && (
                                                    <span className="text-xs px-2 py-1 bg-gray-700 text-gray-300 rounded">
                                                        {comic.genre}
                                                    </span>
                                                )}
                                            </div>

                                            {/* Trending Score Bar */}
                                            {comic.trending_score !== undefined && (
                                                <div className="space-y-1">
                                                    <div className="flex justify-between text-xs">
                                                        <span className="text-gray-500">Trending Score</span>
                                                        <span className="text-orange-400 font-medium">
                                                            {(comic.trending_score * 100).toFixed(0)}%
                                                        </span>
                                                    </div>
                                                    <div className="w-full bg-gray-700 rounded-full h-1.5">
                                                        <div 
                                                            className="bg-gradient-to-r from-orange-500 to-red-500 h-1.5 rounded-full transition-all duration-300"
                                                            style={{ width: `${Math.min(comic.trending_score * 100, 100)}%` }}
                                                        ></div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </a>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* No Results */}
                    {!loading && filteredComics.length === 0 && (
                        <div className="text-center py-16">
                            <TrendingUp className="w-16 h-16 mx-auto text-gray-400 mb-4" />
                            <h3 className="text-xl font-semibold text-white mb-2">No Trending Comics Found</h3>
                            <p className="text-gray-400 mb-6">
                                Try adjusting your filters or check back later for new trending content.
                            </p>
                            <button
                                onClick={() => {
                                    setSelectedGenre('All Genres');
                                    setSelectedTimeframe('week');
                                }}
                                className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300"
                            >
                                Reset Filters
                            </button>
                        </div>
                    )}

                    {/* Bottom CTA */}
                    <div className="mt-16 text-center">
                        <div className="inline-flex flex-col sm:flex-row gap-4">
                            <a
                                href="/recommendations"
                                className="px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300"
                            >
                                Get Personalized Picks
                            </a>
                            <a
                                href="/comics"
                                className="px-6 py-3 border border-gray-600 text-gray-300 font-semibold rounded-lg hover:bg-gray-800 transition-all duration-300"
                            >
                                Browse All Comics
                            </a>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}