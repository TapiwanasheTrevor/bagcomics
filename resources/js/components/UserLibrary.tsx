import React, { useState, useEffect, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { 
    BookOpen, 
    Star, 
    Clock, 
    Download, 
    Play, 
    Heart, 
    Filter, 
    Grid, 
    List, 
    Search,
    Calendar,
    TrendingUp,
    Award,
    Eye,
    ChevronDown,
    SortAsc,
    SortDesc
} from 'lucide-react';
import LibraryFilters from './LibraryFilters';
import ReadingHistory from './ReadingHistory';
import FavoritesList from './FavoritesList';
import PurchaseHistory from './PurchaseHistory';

export interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    genre?: string;
    description?: string;
    cover_image_url?: string;
    page_count?: number;
    average_rating: number;
    total_readers: number;
    is_free: boolean;
    price?: number;
    has_mature_content: boolean;
    published_at: string;
    tags?: string[];
    reading_time_estimate: number;
    is_new_release: boolean;
    publication_year?: number;
    publisher?: string;
    isbn?: string;
    language: string;
    content_warnings?: string;
    pdf_file_path?: string;
    pdf_file_name?: string;
    pdf_file_size?: number;
    is_pdf_comic?: boolean;
}

export interface LibraryEntry {
    id: number;
    comic_id: number;
    access_type: 'free' | 'purchased' | 'subscription';
    purchase_price?: number;
    purchased_at?: string;
    is_favorite: boolean;
    rating?: number;
    review?: string;
    last_accessed_at?: string;
    total_reading_time?: number;
    completion_percentage?: number;
    created_at: string;
    comic: Comic;
    progress?: {
        current_page: number;
        total_pages: number;
        reading_time_minutes: number;
        last_read_at: string;
    };
}

export interface LibraryFilters {
    search: string;
    genre: string;
    publisher: string;
    author: string;
    accessType: string;
    rating: string;
    completionStatus: string;
    dateRange: string;
    sortBy: string;
    sortOrder: 'asc' | 'desc';
    tags: string[];
    language: string;
    priceRange: [number, number];
}

interface UserLibraryProps {
    initialEntries?: LibraryEntry[];
    className?: string;
}

const UserLibrary: React.FC<UserLibraryProps> = ({ 
    initialEntries = [], 
    className = '' 
}) => {
    const [libraryEntries, setLibraryEntries] = useState<LibraryEntry[]>(initialEntries);
    const [loading, setLoading] = useState(initialEntries.length === 0);
    const [activeTab, setActiveTab] = useState<'all' | 'favorites' | 'reading' | 'completed' | 'history' | 'purchases'>('all');
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [showFilters, setShowFilters] = useState(false);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 0,
    });

    const [filters, setFilters] = useState<LibraryFilters>({
        search: '',
        genre: '',
        publisher: '',
        author: '',
        accessType: '',
        rating: '',
        completionStatus: '',
        dateRange: '',
        sortBy: 'last_accessed_at',
        sortOrder: 'desc',
        tags: [],
        language: '',
        priceRange: [0, 100],
    });

    // Fetch library data
    const fetchLibrary = async (page = 1) => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: pagination.per_page.toString(),
                ...Object.fromEntries(
                    Object.entries(filters).filter(([_, value]) => 
                        value !== '' && value !== null && 
                        (Array.isArray(value) ? value.length > 0 : true)
                    )
                ),
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/library?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) throw new Error('Failed to fetch library');

            const data = await response.json();
            setLibraryEntries(data.data);
            setPagination(data.pagination);
        } catch (error) {
            console.error('Error fetching library:', error);
        } finally {
            setLoading(false);
        }
    };

    // Effect to fetch data when filters change
    useEffect(() => {
        if (initialEntries.length === 0) {
            fetchLibrary(1);
        }
    }, [filters, activeTab, initialEntries.length]);

    // Filter entries based on active tab
    const filteredEntries = useMemo(() => {
        let entries = libraryEntries;

        switch (activeTab) {
            case 'favorites':
                entries = entries.filter(entry => entry.is_favorite);
                break;
            case 'reading':
                entries = entries.filter(entry => 
                    entry.completion_percentage && 
                    entry.completion_percentage > 0 && 
                    entry.completion_percentage < 100
                );
                break;
            case 'completed':
                entries = entries.filter(entry => 
                    entry.completion_percentage && entry.completion_percentage >= 100
                );
                break;
        }

        return entries;
    }, [libraryEntries, activeTab]);

    // Statistics
    const stats = useMemo(() => {
        const totalComics = libraryEntries.length;
        const favorites = libraryEntries.filter(e => e.is_favorite).length;
        const reading = libraryEntries.filter(e => 
            e.completion_percentage && e.completion_percentage > 0 && e.completion_percentage < 100
        ).length;
        const completed = libraryEntries.filter(e => 
            e.completion_percentage && e.completion_percentage >= 100
        ).length;
        const totalReadingTime = libraryEntries.reduce((sum, e) => 
            sum + (e.total_reading_time || 0), 0
        );

        return {
            totalComics,
            favorites,
            reading,
            completed,
            totalReadingTime: Math.floor(totalReadingTime / 60), // Convert to minutes
        };
    }, [libraryEntries]);

    const tabs = [
        { id: 'all', label: 'All Comics', count: stats.totalComics, icon: BookOpen },
        { id: 'favorites', label: 'Favorites', count: stats.favorites, icon: Heart },
        { id: 'reading', label: 'Reading', count: stats.reading, icon: Clock },
        { id: 'completed', label: 'Completed', count: stats.completed, icon: Award },
        { id: 'history', label: 'History', count: 0, icon: Eye },
        { id: 'purchases', label: 'Purchases', count: 0, icon: TrendingUp },
    ];

    const handleFilterChange = (newFilters: Partial<LibraryFilters>) => {
        setFilters(prev => ({ ...prev, ...newFilters }));
    };

    const handleToggleFavorite = async (entryId: number) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/library/${entryId}/favorite`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                setLibraryEntries(prev => 
                    prev.map(entry => 
                        entry.id === entryId 
                            ? { ...entry, is_favorite: !entry.is_favorite }
                            : entry
                    )
                );
            }
        } catch (error) {
            console.error('Error toggling favorite:', error);
        }
    };

    const formatReadingTime = (minutes: number): string => {
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    };

    const LibraryGridCard: React.FC<{ entry: LibraryEntry }> = ({ entry }) => (
        <div className="group cursor-pointer bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/20">
            <Link href={`/comics/${entry.comic.slug}`} className="block">
                <div className="relative">
                    {entry.comic.cover_image_url ? (
                        <img
                            src={entry.comic.cover_image_url}
                            alt={entry.comic.title}
                            className="w-full aspect-[2/3] object-cover group-hover:scale-110 transition-transform duration-500"
                        />
                    ) : (
                        <div className="w-full aspect-[2/3] bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                            <BookOpen className="h-16 w-16 text-gray-500" />
                        </div>
                    )}

                    {/* Progress bar */}
                    {entry.completion_percentage && entry.completion_percentage > 0 && (
                        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gray-600">
                            <div 
                                className="h-full bg-emerald-500 transition-all duration-300"
                                style={{ width: `${entry.completion_percentage}%` }}
                            />
                        </div>
                    )}

                    {/* Overlay with actions */}
                    <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                        <div className="flex space-x-2">
                            <button className="p-2 bg-emerald-500 rounded-full text-white hover:bg-emerald-600 transition-colors">
                                <Play className="h-4 w-4" />
                            </button>
                            {entry.comic.is_pdf_comic && (
                                <button className="p-2 bg-blue-500 rounded-full text-white hover:bg-blue-600 transition-colors">
                                    <Download className="h-4 w-4" />
                                </button>
                            )}
                            <button 
                                onClick={(e) => {
                                    e.preventDefault();
                                    handleToggleFavorite(entry.id);
                                }}
                                className="p-2 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors"
                            >
                                <Heart className={`h-4 w-4 ${entry.is_favorite ? 'fill-current' : ''}`} />
                            </button>
                        </div>
                    </div>

                    {/* Status badges */}
                    <div className="absolute top-2 left-2 flex flex-col space-y-1">
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                            entry.access_type === 'free'
                                ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                : entry.access_type === 'purchased'
                                ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                                : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                        }`}>
                            {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Sub'}
                        </span>
                        
                        {entry.completion_percentage === 100 && (
                            <span className="px-2 py-1 text-xs font-medium rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                Complete
                            </span>
                        )}
                    </div>

                    {/* Favorite indicator */}
                    {entry.is_favorite && (
                        <div className="absolute top-2 right-2">
                            <Heart className="h-5 w-5 text-red-500 fill-current" />
                        </div>
                    )}
                </div>

                <div className="p-4">
                    <h3 className="font-semibold text-white mb-1 line-clamp-2 group-hover:text-emerald-400 transition-colors">
                        {entry.comic.title}
                    </h3>
                    {entry.comic.author && (
                        <p className="text-sm text-gray-400 mb-2">{entry.comic.author}</p>
                    )}

                    <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center space-x-1">
                            <Star className="h-3 w-3 text-yellow-400 fill-current" />
                            <span className="text-xs text-gray-400">{Number(entry.comic.average_rating || 0).toFixed(1)}</span>
                        </div>
                        {entry.total_reading_time && entry.total_reading_time > 0 && (
                            <span className="text-xs text-gray-500">
                                {formatReadingTime(Math.floor(entry.total_reading_time / 60))}
                            </span>
                        )}
                    </div>

                    {entry.rating && (
                        <div className="flex items-center space-x-1">
                            <span className="text-xs text-gray-500">Your rating:</span>
                            <div className="flex">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Star
                                        key={i}
                                        className={`h-3 w-3 ${
                                            i < entry.rating! ? 'text-yellow-400 fill-current' : 'text-gray-600'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </Link>
        </div>
    );

    const LibraryListCard: React.FC<{ entry: LibraryEntry }> = ({ entry }) => (
        <div className="bg-gray-800 rounded-xl border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 p-4">
            <div className="flex items-center space-x-4">
                <Link href={`/comics/${entry.comic.slug}`} className="flex-shrink-0">
                    {entry.comic.cover_image_url ? (
                        <img
                            src={entry.comic.cover_image_url}
                            alt={entry.comic.title}
                            className="w-16 h-24 object-cover rounded-lg"
                        />
                    ) : (
                        <div className="w-16 h-24 bg-gradient-to-br from-gray-700 to-gray-800 rounded-lg flex items-center justify-center">
                            <BookOpen className="h-8 w-8 text-gray-500" />
                        </div>
                    )}
                </Link>

                <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between">
                        <div className="flex-1 min-w-0">
                            <Link href={`/comics/${entry.comic.slug}`}>
                                <h3 className="font-semibold text-white hover:text-emerald-400 transition-colors line-clamp-1">
                                    {entry.comic.title}
                                </h3>
                            </Link>
                            {entry.comic.author && (
                                <p className="text-sm text-gray-400 mt-1">{entry.comic.author}</p>
                            )}

                            <div className="flex items-center space-x-4 mt-2">
                                <div className="flex items-center space-x-1">
                                    <Star className="h-4 w-4 text-yellow-400 fill-current" />
                                    <span className="text-sm text-gray-400">{Number(entry.comic.average_rating || 0).toFixed(1)}</span>
                                </div>

                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                    entry.access_type === 'free'
                                        ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                        : entry.access_type === 'purchased'
                                        ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                                        : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                                }`}>
                                    {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Subscription'}
                                </span>

                                {entry.completion_percentage && (
                                    <span className="text-xs text-gray-500">
                                        {entry.completion_percentage.toFixed(0)}% complete
                                    </span>
                                )}

                                {entry.total_reading_time && entry.total_reading_time > 0 && (
                                    <span className="text-xs text-gray-500">
                                        {formatReadingTime(Math.floor(entry.total_reading_time / 60))} read
                                    </span>
                                )}
                            </div>

                            {entry.rating && (
                                <div className="mt-2 flex items-center space-x-1">
                                    <span className="text-xs text-gray-500">Your rating:</span>
                                    <div className="flex">
                                        {Array.from({ length: 5 }).map((_, i) => (
                                            <Star
                                                key={i}
                                                className={`h-3 w-3 ${
                                                    i < entry.rating! ? 'text-yellow-400 fill-current' : 'text-gray-600'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex items-center space-x-2 ml-4">
                            <button
                                onClick={() => handleToggleFavorite(entry.id)}
                                className={`p-2 rounded-lg transition-colors ${
                                    entry.is_favorite 
                                        ? 'bg-red-500 text-white' 
                                        : 'bg-gray-700 text-gray-400 hover:text-white'
                                }`}
                            >
                                <Heart className={`h-4 w-4 ${entry.is_favorite ? 'fill-current' : ''}`} />
                            </button>

                            <Link
                                href={`/comics/${entry.comic.slug}`}
                                className="px-3 py-1 bg-emerald-500 text-white text-sm rounded-lg hover:bg-emerald-600 transition-colors"
                            >
                                Read
                            </Link>

                            {entry.comic.is_pdf_comic && (
                                <button className="p-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                                    <Download className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Library Stats */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div className="bg-gray-800/50 rounded-lg p-4 text-center">
                    <div className="text-2xl font-bold text-emerald-400">{stats.totalComics}</div>
                    <div className="text-sm text-gray-400">Total Comics</div>
                </div>
                <div className="bg-gray-800/50 rounded-lg p-4 text-center">
                    <div className="text-2xl font-bold text-red-400">{stats.favorites}</div>
                    <div className="text-sm text-gray-400">Favorites</div>
                </div>
                <div className="bg-gray-800/50 rounded-lg p-4 text-center">
                    <div className="text-2xl font-bold text-yellow-400">{stats.reading}</div>
                    <div className="text-sm text-gray-400">Reading</div>
                </div>
                <div className="bg-gray-800/50 rounded-lg p-4 text-center">
                    <div className="text-2xl font-bold text-green-400">{stats.completed}</div>
                    <div className="text-sm text-gray-400">Completed</div>
                </div>
                <div className="bg-gray-800/50 rounded-lg p-4 text-center">
                    <div className="text-2xl font-bold text-purple-400">{formatReadingTime(stats.totalReadingTime)}</div>
                    <div className="text-sm text-gray-400">Reading Time</div>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const Icon = tab.icon;
                    return (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id as any)}
                            className={`flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 ${
                                activeTab === tab.id
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-gray-800/50 text-gray-400 hover:text-white hover:bg-gray-700/50'
                            }`}
                        >
                            <Icon className="w-4 h-4" />
                            <span>{tab.label}</span>
                            <span className="bg-gray-700 text-gray-300 px-2 py-0.5 rounded-full text-xs">
                                {tab.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Controls */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                {/* Search and Filters */}
                <div className="flex items-center space-x-4 flex-1">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search your library..."
                            value={filters.search}
                            onChange={(e) => handleFilterChange({ search: e.target.value })}
                            className="w-full pl-10 pr-4 py-2 bg-gray-800/50 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                    </div>
                    
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className={`flex items-center space-x-2 px-4 py-2 rounded-lg transition-colors ${
                            showFilters
                                ? 'bg-emerald-500 text-white'
                                : 'bg-gray-800 text-gray-400 hover:text-white'
                        }`}
                    >
                        <Filter className="h-4 w-4" />
                        <span>Filters</span>
                    </button>
                </div>

                {/* View Mode and Sort */}
                <div className="flex items-center space-x-2">
                    <select
                        value={`${filters.sortBy}-${filters.sortOrder}`}
                        onChange={(e) => {
                            const [sortBy, sortOrder] = e.target.value.split('-');
                            handleFilterChange({ sortBy, sortOrder: sortOrder as 'asc' | 'desc' });
                        }}
                        className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="last_accessed_at-desc">Recently Read</option>
                        <option value="created_at-desc">Recently Added</option>
                        <option value="title-asc">Title A-Z</option>
                        <option value="title-desc">Title Z-A</option>
                        <option value="rating-desc">Highest Rated</option>
                        <option value="completion_percentage-desc">Most Progress</option>
                        <option value="total_reading_time-desc">Most Read</option>
                    </select>

                    <button
                        onClick={() => setViewMode('grid')}
                        className={`p-2 rounded-lg transition-colors ${
                            viewMode === 'grid'
                                ? 'bg-emerald-500 text-white'
                                : 'bg-gray-800 text-gray-400 hover:text-white'
                        }`}
                    >
                        <Grid className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => setViewMode('list')}
                        className={`p-2 rounded-lg transition-colors ${
                            viewMode === 'list'
                                ? 'bg-emerald-500 text-white'
                                : 'bg-gray-800 text-gray-400 hover:text-white'
                        }`}
                    >
                        <List className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Filters Panel */}
            {showFilters && (
                <LibraryFilters
                    filters={filters}
                    onFiltersChange={handleFilterChange}
                    entries={libraryEntries}
                />
            )}

            {/* Content based on active tab */}
            {activeTab === 'history' ? (
                <ReadingHistory />
            ) : activeTab === 'purchases' ? (
                <PurchaseHistory />
            ) : activeTab === 'favorites' ? (
                <FavoritesList 
                    entries={filteredEntries}
                    viewMode={viewMode}
                    loading={loading}
                    onToggleFavorite={handleToggleFavorite}
                />
            ) : (
                <>
                    {/* Library Content */}
                    {loading ? (
                        <div className={viewMode === 'grid' 
                            ? "grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6"
                            : "space-y-4"
                        }>
                            {Array.from({ length: viewMode === 'grid' ? 12 : 6 }).map((_, i) => (
                                <div key={i} className="animate-pulse">
                                    {viewMode === 'grid' ? (
                                        <>
                                            <div className="aspect-[2/3] bg-gray-700 rounded-lg mb-3"></div>
                                            <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                            <div className="h-3 bg-gray-700 rounded w-2/3"></div>
                                        </>
                                    ) : (
                                        <div className="bg-gray-800 rounded-xl p-4">
                                            <div className="flex items-center space-x-4">
                                                <div className="w-16 h-24 bg-gray-700 rounded-lg"></div>
                                                <div className="flex-1">
                                                    <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                                    <div className="h-3 bg-gray-700 rounded w-1/2 mb-2"></div>
                                                    <div className="h-3 bg-gray-700 rounded w-1/3"></div>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : filteredEntries.length > 0 ? (
                        <>
                            {viewMode === 'grid' ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
                                    {filteredEntries.map((entry) => (
                                        <LibraryGridCard key={entry.id} entry={entry} />
                                    ))}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {filteredEntries.map((entry) => (
                                        <LibraryListCard key={entry.id} entry={entry} />
                                    ))}
                                </div>
                            )}

                            {/* Pagination */}
                            {pagination.last_page > 1 && (
                                <div className="flex justify-center items-center space-x-2 mt-8">
                                    <button
                                        onClick={() => fetchLibrary(pagination.current_page - 1)}
                                        disabled={pagination.current_page === 1}
                                        className="px-4 py-2 bg-gray-800 text-gray-400 rounded-lg hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Previous
                                    </button>
                                    
                                    <span className="text-gray-400">
                                        Page {pagination.current_page} of {pagination.last_page}
                                    </span>
                                    
                                    <button
                                        onClick={() => fetchLibrary(pagination.current_page + 1)}
                                        disabled={pagination.current_page === pagination.last_page}
                                        className="px-4 py-2 bg-gray-800 text-gray-400 rounded-lg hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Next
                                    </button>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-center py-12">
                            <BookOpen className="h-16 w-16 text-gray-500 mx-auto mb-4" />
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">
                                {activeTab === 'all' ? 'No comics in your library' : 
                                 activeTab === 'favorites' ? 'No favorite comics yet' :
                                 activeTab === 'reading' ? 'No comics currently being read' :
                                 'No completed comics yet'}
                            </h3>
                            <p className="text-gray-500 mb-6">
                                {activeTab === 'all' ? 'Start building your collection by exploring our catalog' :
                                 activeTab === 'favorites' ? 'Mark comics as favorites to see them here' :
                                 activeTab === 'reading' ? 'Start reading some comics to track your progress' :
                                 'Finish reading comics to see them here'}
                            </p>
                            <Link
                                href="/comics"
                                className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300"
                            >
                                <BookOpen className="w-5 h-5" />
                                <span>Explore Comics</span>
                            </Link>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

export default UserLibrary;