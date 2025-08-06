import React, { useState, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { 
    Heart, 
    BookOpen, 
    Star, 
    Clock, 
    Download, 
    Play, 
    Grid, 
    List,
    SortAsc,
    SortDesc,
    Filter,
    Search,
    Calendar,
    User,
    Tag
} from 'lucide-react';
import type { LibraryEntry } from './UserLibrary';

interface FavoritesListProps {
    entries: LibraryEntry[];
    viewMode: 'grid' | 'list';
    loading: boolean;
    onToggleFavorite: (entryId: number) => void;
    className?: string;
}

const FavoritesList: React.FC<FavoritesListProps> = ({
    entries,
    viewMode,
    loading,
    onToggleFavorite,
    className = ''
}) => {
    const [sortBy, setSortBy] = useState<'title' | 'author' | 'rating' | 'added_date' | 'last_read'>('added_date');
    const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
    const [filterGenre, setFilterGenre] = useState('');
    const [searchQuery, setSearchQuery] = useState('');

    // Get unique genres from favorites
    const availableGenres = useMemo(() => {
        const genres = [...new Set(entries.map(e => e.comic.genre).filter(Boolean))].sort();
        return genres;
    }, [entries]);

    // Filter and sort entries
    const filteredAndSortedEntries = useMemo(() => {
        let filtered = entries.filter(entry => entry.is_favorite);

        // Apply search filter
        if (searchQuery) {
            filtered = filtered.filter(entry =>
                entry.comic.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                entry.comic.author?.toLowerCase().includes(searchQuery.toLowerCase()) ||
                entry.comic.genre?.toLowerCase().includes(searchQuery.toLowerCase())
            );
        }

        // Apply genre filter
        if (filterGenre) {
            filtered = filtered.filter(entry => entry.comic.genre === filterGenre);
        }

        // Sort entries
        filtered.sort((a, b) => {
            let aValue: any, bValue: any;

            switch (sortBy) {
                case 'title':
                    aValue = a.comic.title.toLowerCase();
                    bValue = b.comic.title.toLowerCase();
                    break;
                case 'author':
                    aValue = (a.comic.author || '').toLowerCase();
                    bValue = (b.comic.author || '').toLowerCase();
                    break;
                case 'rating':
                    aValue = a.rating || 0;
                    bValue = b.rating || 0;
                    break;
                case 'added_date':
                    aValue = new Date(a.created_at).getTime();
                    bValue = new Date(b.created_at).getTime();
                    break;
                case 'last_read':
                    aValue = a.last_accessed_at ? new Date(a.last_accessed_at).getTime() : 0;
                    bValue = b.last_accessed_at ? new Date(b.last_accessed_at).getTime() : 0;
                    break;
                default:
                    return 0;
            }

            if (aValue < bValue) return sortOrder === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortOrder === 'asc' ? 1 : -1;
            return 0;
        });

        return filtered;
    }, [entries, searchQuery, filterGenre, sortBy, sortOrder]);

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    };

    const formatReadingTime = (minutes: number): string => {
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    };

    const FavoriteGridCard: React.FC<{ entry: LibraryEntry }> = ({ entry }) => (
        <div className="group cursor-pointer bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-red-500/20">
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
                                className="h-full bg-red-500 transition-all duration-300"
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
                                    onToggleFavorite(entry.id);
                                }}
                                className="p-2 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors"
                            >
                                <Heart className="h-4 w-4 fill-current" />
                            </button>
                        </div>
                    </div>

                    {/* Favorite heart - always visible for favorites */}
                    <div className="absolute top-2 right-2">
                        <Heart className="h-6 w-6 text-red-500 fill-current drop-shadow-lg" />
                    </div>

                    {/* Access type badge */}
                    <div className="absolute top-2 left-2">
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                            entry.access_type === 'free'
                                ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                : entry.access_type === 'purchased'
                                ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                                : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                        }`}>
                            {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Sub'}
                        </span>
                    </div>
                </div>

                <div className="p-4">
                    <h3 className="font-semibold text-white mb-1 line-clamp-2 group-hover:text-red-400 transition-colors">
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

                    <div className="mt-2 text-xs text-gray-500">
                        Added {formatDate(entry.created_at)}
                    </div>
                </div>
            </Link>
        </div>
    );

    const FavoriteListCard: React.FC<{ entry: LibraryEntry }> = ({ entry }) => (
        <div className="bg-gray-800 rounded-xl border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 p-4">
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
                                <h3 className="font-semibold text-white hover:text-red-400 transition-colors line-clamp-1">
                                    {entry.comic.title}
                                </h3>
                            </Link>
                            {entry.comic.author && (
                                <p className="text-sm text-gray-400 mt-1 flex items-center space-x-1">
                                    <User className="h-3 w-3" />
                                    <span>{entry.comic.author}</span>
                                </p>
                            )}

                            <div className="flex items-center space-x-4 mt-2">
                                <div className="flex items-center space-x-1">
                                    <Star className="h-4 w-4 text-yellow-400 fill-current" />
                                    <span className="text-sm text-gray-400">{Number(entry.comic.average_rating || 0).toFixed(1)}</span>
                                </div>

                                {entry.comic.genre && (
                                    <div className="flex items-center space-x-1">
                                        <Tag className="h-3 w-3 text-gray-500" />
                                        <span className="text-xs text-gray-500">{entry.comic.genre}</span>
                                    </div>
                                )}

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
                            </div>

                            <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                <div className="flex items-center space-x-1">
                                    <Calendar className="h-3 w-3" />
                                    <span>Added {formatDate(entry.created_at)}</span>
                                </div>
                                {entry.last_accessed_at && (
                                    <div className="flex items-center space-x-1">
                                        <Clock className="h-3 w-3" />
                                        <span>Last read {formatDate(entry.last_accessed_at)}</span>
                                    </div>
                                )}
                                {entry.total_reading_time && entry.total_reading_time > 0 && (
                                    <span>{formatReadingTime(Math.floor(entry.total_reading_time / 60))} read</span>
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
                                onClick={() => onToggleFavorite(entry.id)}
                                className="p-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors"
                                title="Remove from favorites"
                            >
                                <Heart className="h-4 w-4 fill-current" />
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
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <Heart className="h-6 w-6 text-red-500 fill-current" />
                    <div>
                        <h2 className="text-xl font-bold text-white">Favorite Comics</h2>
                        <p className="text-sm text-gray-400">
                            {filteredAndSortedEntries.length} favorite{filteredAndSortedEntries.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                </div>
            </div>

            {/* Controls */}
            <div className="flex flex-col sm:flex-row gap-4">
                {/* Search */}
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search favorites..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 bg-gray-800/50 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    />
                </div>

                {/* Genre Filter */}
                {availableGenres.length > 0 && (
                    <select
                        value={filterGenre}
                        onChange={(e) => setFilterGenre(e.target.value)}
                        className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        <option value="">All Genres</option>
                        {availableGenres.map(genre => (
                            <option key={genre} value={genre}>{genre}</option>
                        ))}
                    </select>
                )}

                {/* Sort Controls */}
                <div className="flex items-center space-x-2">
                    <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value as any)}
                        className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        <option value="added_date">Date Added</option>
                        <option value="last_read">Last Read</option>
                        <option value="title">Title</option>
                        <option value="author">Author</option>
                        <option value="rating">Your Rating</option>
                    </select>

                    <button
                        onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                        className="p-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-400 hover:text-white transition-colors"
                        title={`Sort ${sortOrder === 'asc' ? 'descending' : 'ascending'}`}
                    >
                        {sortOrder === 'asc' ? <SortAsc className="h-4 w-4" /> : <SortDesc className="h-4 w-4" />}
                    </button>
                </div>
            </div>

            {/* Content */}
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
            ) : filteredAndSortedEntries.length > 0 ? (
                <>
                    {viewMode === 'grid' ? (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
                            {filteredAndSortedEntries.map((entry) => (
                                <FavoriteGridCard key={entry.id} entry={entry} />
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {filteredAndSortedEntries.map((entry) => (
                                <FavoriteListCard key={entry.id} entry={entry} />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="text-center py-12">
                    <Heart className="h-16 w-16 text-gray-500 mx-auto mb-4" />
                    <h3 className="text-xl font-semibold text-gray-300 mb-2">
                        {searchQuery || filterGenre ? 'No matching favorites' : 'No favorite comics yet'}
                    </h3>
                    <p className="text-gray-500 mb-6">
                        {searchQuery || filterGenre 
                            ? 'Try adjusting your search or filters'
                            : 'Mark comics as favorites to see them here'
                        }
                    </p>
                    {!searchQuery && !filterGenre && (
                        <Link
                            href="/comics"
                            className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white font-semibold rounded-xl hover:from-red-600 hover:to-pink-600 transition-all duration-300"
                        >
                            <Heart className="w-5 h-5" />
                            <span>Find Comics to Love</span>
                        </Link>
                    )}
                </div>
            )}
        </div>
    );
};

export default FavoritesList;