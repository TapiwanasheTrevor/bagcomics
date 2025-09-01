import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Compass, Search, Tag, Calendar, Award, Users, BookOpen, Star, TrendingUp, Clock, Filter, Grid, List } from 'lucide-react';
import NavBar from '@/components/NavBar';
import { type SharedData } from '@/types';

interface DiscoverCategory {
    id: string;
    title: string;
    description: string;
    icon: any;
    color: string;
    query: string;
    count?: number;
}

interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    genre?: string;
    description?: string;
    cover_image_url?: string;
    average_rating: number;
    total_ratings: number;
    page_count?: number;
    is_free: boolean;
    price?: number;
    published_at: string;
    tags?: string[];
}

const DISCOVER_CATEGORIES: DiscoverCategory[] = [
    {
        id: 'new_releases',
        title: 'New Releases',
        description: 'Fresh comics added this week',
        icon: Calendar,
        color: 'blue',
        query: 'sort=newest&days=7'
    },
    {
        id: 'top_rated',
        title: 'Top Rated',
        description: 'Highest rated comics of all time',
        icon: Star,
        color: 'yellow',
        query: 'sort=rating&min_rating=4.5'
    },
    {
        id: 'most_popular',
        title: 'Most Popular',
        description: 'Comics with the most readers',
        icon: Users,
        color: 'green',
        query: 'sort=popularity'
    },
    {
        id: 'hidden_gems',
        title: 'Hidden Gems',
        description: 'Great comics you might have missed',
        icon: Award,
        color: 'purple',
        query: 'min_rating=4&max_readers=100'
    },
    {
        id: 'quick_reads',
        title: 'Quick Reads',
        description: 'Comics under 50 pages',
        icon: Clock,
        color: 'orange',
        query: 'max_pages=50'
    },
    {
        id: 'free_comics',
        title: 'Free Comics',
        description: 'Great stories at no cost',
        icon: BookOpen,
        color: 'red',
        query: 'is_free=true'
    }
];

const POPULAR_TAGS = [
    'superhero', 'manga', 'fantasy', 'sci-fi', 'romance', 
    'horror', 'comedy', 'action', 'mystery', 'thriller',
    'slice-of-life', 'historical', 'adventure', 'drama', 'supernatural'
];

const GENRE_COLORS: Record<string, string> = {
    'Action': 'bg-red-500/20 text-red-400 border-red-500',
    'Adventure': 'bg-green-500/20 text-green-400 border-green-500',
    'Comedy': 'bg-yellow-500/20 text-yellow-400 border-yellow-500',
    'Drama': 'bg-purple-500/20 text-purple-400 border-purple-500',
    'Fantasy': 'bg-indigo-500/20 text-indigo-400 border-indigo-500',
    'Horror': 'bg-gray-500/20 text-gray-400 border-gray-500',
    'Mystery': 'bg-blue-500/20 text-blue-400 border-blue-500',
    'Romance': 'bg-pink-500/20 text-pink-400 border-pink-500',
    'Sci-Fi': 'bg-cyan-500/20 text-cyan-400 border-cyan-500',
    'Thriller': 'bg-orange-500/20 text-orange-400 border-orange-500'
};

export default function Discover() {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<DiscoverCategory | null>(null);
    const [selectedTags, setSelectedTags] = useState<string[]>([]);
    const [comics, setComics] = useState<Comic[]>([]);
    const [loading, setLoading] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [filterGenre, setFilterGenre] = useState('All');
    const [sortBy, setSortBy] = useState('relevance');

    useEffect(() => {
        if (selectedCategory) {
            fetchCategoryComics(selectedCategory);
        }
    }, [selectedCategory]);

    const fetchCategoryComics = async (category: DiscoverCategory) => {
        try {
            setLoading(true);
            const response = await fetch(`/api/comics/search?${category.query}&limit=24`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.ok) {
                const data = await response.json();
                setComics(data.data || []);
            }
        } catch (error) {
            console.error('Error fetching comics:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleTagClick = (tag: string) => {
        if (selectedTags.includes(tag)) {
            setSelectedTags(selectedTags.filter(t => t !== tag));
        } else {
            setSelectedTags([...selectedTags, tag]);
        }
    };

    const handleSearch = () => {
        const tags = selectedTags.join(',');
        const genre = filterGenre !== 'All' ? `&genre=${filterGenre}` : '';
        const sort = `&sort=${sortBy}`;
        window.location.href = `/comics?search=${encodeURIComponent(searchQuery)}&tags=${tags}${genre}${sort}`;
    };

    const getCategoryIcon = (IconComponent: any, color: string) => {
        const colorClasses = {
            'blue': 'bg-blue-500/20 text-blue-400',
            'yellow': 'bg-yellow-500/20 text-yellow-400',
            'green': 'bg-green-500/20 text-green-400',
            'purple': 'bg-purple-500/20 text-purple-400',
            'orange': 'bg-orange-500/20 text-orange-400',
            'red': 'bg-red-500/20 text-red-400'
        };

        return (
            <div className={`p-3 rounded-xl ${colorClasses[color as keyof typeof colorClasses]}`}>
                <IconComponent className="w-6 h-6" />
            </div>
        );
    };

    return (
        <>
            <Head title="Discover Comics - BagComics">
                <meta name="description" content="Explore and discover amazing comics across genres, tags, and categories on BagComics." />
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
                            <div className="p-3 bg-gradient-to-r from-blue-500 to-purple-500 rounded-xl">
                                <Compass className="w-8 h-8 text-white" />
                            </div>
                            <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-400 to-purple-600 bg-clip-text text-transparent">
                                Discover Comics
                            </h1>
                        </div>
                        <p className="text-lg text-gray-400 max-w-2xl mx-auto">
                            Explore new worlds, find your next favorite series, and discover comics tailored to your taste
                        </p>
                    </div>

                    {/* Advanced Search Bar */}
                    <div className="bg-gray-800/50 rounded-2xl p-6 border border-gray-700 mb-8">
                        <div className="space-y-4">
                            {/* Search Input */}
                            <div className="flex gap-3">
                                <div className="flex-1 relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                                        placeholder="Search by title, author, or description..."
                                        className="w-full pl-10 pr-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    />
                                </div>
                                <button
                                    onClick={handleSearch}
                                    className="px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white font-semibold rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300"
                                >
                                    Search
                                </button>
                            </div>

                            {/* Filter Options */}
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center space-x-2">
                                    <Filter className="w-4 h-4 text-gray-400" />
                                    <select
                                        value={filterGenre}
                                        onChange={(e) => setFilterGenre(e.target.value)}
                                        className="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-purple-500"
                                    >
                                        <option value="All">All Genres</option>
                                        {Object.keys(GENRE_COLORS).map(genre => (
                                            <option key={genre} value={genre}>{genre}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <TrendingUp className="w-4 h-4 text-gray-400" />
                                    <select
                                        value={sortBy}
                                        onChange={(e) => setSortBy(e.target.value)}
                                        className="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-purple-500"
                                    >
                                        <option value="relevance">Relevance</option>
                                        <option value="newest">Newest First</option>
                                        <option value="rating">Highest Rated</option>
                                        <option value="popularity">Most Popular</option>
                                        <option value="title">Alphabetical</option>
                                    </select>
                                </div>

                                <div className="flex items-center space-x-1 ml-auto">
                                    <button
                                        onClick={() => setViewMode('grid')}
                                        className={`p-2 rounded ${viewMode === 'grid' ? 'bg-purple-500 text-white' : 'bg-gray-800 text-gray-400'}`}
                                    >
                                        <Grid className="w-4 h-4" />
                                    </button>
                                    <button
                                        onClick={() => setViewMode('list')}
                                        className={`p-2 rounded ${viewMode === 'list' ? 'bg-purple-500 text-white' : 'bg-gray-800 text-gray-400'}`}
                                    >
                                        <List className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Browse by Category */}
                    <section className="mb-12">
                        <h2 className="text-2xl font-bold text-white mb-6">Browse by Category</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            {DISCOVER_CATEGORIES.map((category) => (
                                <button
                                    key={category.id}
                                    onClick={() => setSelectedCategory(category)}
                                    className={`group p-6 bg-gray-800/50 rounded-xl border transition-all duration-300 text-left ${
                                        selectedCategory?.id === category.id
                                            ? 'border-purple-500 bg-purple-500/10'
                                            : 'border-gray-700 hover:border-gray-600 hover:bg-gray-800/70'
                                    }`}
                                >
                                    <div className="flex items-start space-x-4">
                                        {getCategoryIcon(category.icon, category.color)}
                                        <div className="flex-1">
                                            <h3 className="font-semibold text-white mb-1 group-hover:text-purple-400 transition-colors">
                                                {category.title}
                                            </h3>
                                            <p className="text-sm text-gray-400">
                                                {category.description}
                                            </p>
                                            {category.count && (
                                                <p className="text-xs text-gray-500 mt-1">
                                                    {category.count} comics
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </section>

                    {/* Popular Tags */}
                    <section className="mb-12">
                        <h2 className="text-2xl font-bold text-white mb-6">Popular Tags</h2>
                        <div className="flex flex-wrap gap-3">
                            {POPULAR_TAGS.map((tag) => (
                                <button
                                    key={tag}
                                    onClick={() => handleTagClick(tag)}
                                    className={`px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 ${
                                        selectedTags.includes(tag)
                                            ? 'bg-purple-500 text-white'
                                            : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
                                    }`}
                                >
                                    <span className="flex items-center space-x-2">
                                        <Tag className="w-3 h-3" />
                                        <span>{tag}</span>
                                    </span>
                                </button>
                            ))}
                        </div>
                        {selectedTags.length > 0 && (
                            <div className="mt-4 p-3 bg-purple-500/10 border border-purple-500 rounded-lg">
                                <p className="text-sm text-purple-400">
                                    Selected tags: {selectedTags.join(', ')}
                                </p>
                            </div>
                        )}
                    </section>

                    {/* Category Results */}
                    {selectedCategory && (
                        <section className="mb-12">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-2xl font-bold text-white">
                                    {selectedCategory.title}
                                </h2>
                                <button
                                    onClick={() => setSelectedCategory(null)}
                                    className="text-sm text-gray-400 hover:text-white transition-colors"
                                >
                                    Clear Selection
                                </button>
                            </div>

                            {loading ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                                    {Array.from({ length: 12 }).map((_, i) => (
                                        <div key={i} className="bg-gray-800 rounded-lg animate-pulse">
                                            <div className="aspect-[2/3] bg-gray-700 rounded-t-lg"></div>
                                            <div className="p-3 space-y-2">
                                                <div className="h-3 bg-gray-700 rounded"></div>
                                                <div className="h-2 bg-gray-700 rounded w-3/4"></div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className={viewMode === 'grid' 
                                    ? "grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4"
                                    : "space-y-4"
                                }>
                                    {comics.map((comic) => (
                                        viewMode === 'grid' ? (
                                            <a
                                                key={comic.id}
                                                href={`/comics/${comic.slug}`}
                                                className="group bg-gray-800/50 rounded-lg overflow-hidden border border-gray-700 hover:border-purple-500 transition-all duration-300"
                                            >
                                                <div className="aspect-[2/3] overflow-hidden">
                                                    <img
                                                        src={comic.cover_image_url || '/images/default-comic-cover.svg'}
                                                        alt={comic.title}
                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                        loading="lazy"
                                                    />
                                                </div>
                                                <div className="p-3">
                                                    <h3 className="font-medium text-white text-sm mb-1 line-clamp-1 group-hover:text-purple-400 transition-colors">
                                                        {comic.title}
                                                    </h3>
                                                    <p className="text-xs text-gray-400 truncate">
                                                        {comic.author || 'Unknown'}
                                                    </p>
                                                    <div className="flex items-center space-x-2 mt-2">
                                                        <div className="flex items-center space-x-1">
                                                            <Star className="w-3 h-3 text-yellow-400 fill-current" />
                                                            <span className="text-xs text-gray-300">
                                                                {comic.average_rating.toFixed(1)}
                                                            </span>
                                                        </div>
                                                        {comic.genre && (
                                                            <span className={`text-xs px-2 py-0.5 rounded border ${
                                                                GENRE_COLORS[comic.genre] || 'bg-gray-700 text-gray-300 border-gray-600'
                                                            }`}>
                                                                {comic.genre}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </a>
                                        ) : (
                                            <a
                                                key={comic.id}
                                                href={`/comics/${comic.slug}`}
                                                className="group flex gap-4 p-4 bg-gray-800/50 rounded-lg border border-gray-700 hover:border-purple-500 transition-all duration-300"
                                            >
                                                <img
                                                    src={comic.cover_image_url || '/images/default-comic-cover.svg'}
                                                    alt={comic.title}
                                                    className="w-24 h-36 object-cover rounded"
                                                />
                                                <div className="flex-1">
                                                    <h3 className="font-semibold text-white mb-1 group-hover:text-purple-400 transition-colors">
                                                        {comic.title}
                                                    </h3>
                                                    <p className="text-sm text-gray-400 mb-2">
                                                        by {comic.author || 'Unknown Author'}
                                                    </p>
                                                    <p className="text-sm text-gray-300 line-clamp-2 mb-3">
                                                        {comic.description || 'No description available'}
                                                    </p>
                                                    <div className="flex items-center space-x-4">
                                                        <div className="flex items-center space-x-1">
                                                            <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                                            <span className="text-sm text-white">
                                                                {comic.average_rating.toFixed(1)}
                                                            </span>
                                                        </div>
                                                        {comic.genre && (
                                                            <span className={`text-sm px-3 py-1 rounded border ${
                                                                GENRE_COLORS[comic.genre] || 'bg-gray-700 text-gray-300 border-gray-600'
                                                            }`}>
                                                                {comic.genre}
                                                            </span>
                                                        )}
                                                        {comic.page_count && (
                                                            <span className="text-sm text-gray-400">
                                                                {comic.page_count} pages
                                                            </span>
                                                        )}
                                                        {comic.is_free && (
                                                            <span className="text-sm text-green-400 font-medium">
                                                                FREE
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </a>
                                        )
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    {/* Genre Spotlight */}
                    <section className="mb-12">
                        <h2 className="text-2xl font-bold text-white mb-6">Explore by Genre</h2>
                        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                            {Object.entries(GENRE_COLORS).map(([genre, colorClass]) => (
                                <a
                                    key={genre}
                                    href={`/comics?genre=${genre}`}
                                    className={`p-4 rounded-lg border text-center font-medium transition-all duration-300 hover:scale-105 ${colorClass}`}
                                >
                                    {genre}
                                </a>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}