import React, { useState, useMemo } from 'react';
import { X, Calendar, Star, Filter, RotateCcw } from 'lucide-react';
import type { LibraryEntry, LibraryFilters as LibraryFiltersType } from './UserLibrary';

interface LibraryFiltersProps {
    filters: LibraryFiltersType;
    onFiltersChange: (filters: Partial<LibraryFiltersType>) => void;
    entries: LibraryEntry[];
    className?: string;
}

const LibraryFilters: React.FC<LibraryFiltersProps> = ({
    filters,
    onFiltersChange,
    entries,
    className = ''
}) => {
    // Extract unique values from entries for filter options
    const filterOptions = useMemo(() => {
        const genres = [...new Set(entries.map(e => e.comic.genre).filter(Boolean))].sort();
        const publishers = [...new Set(entries.map(e => e.comic.publisher).filter(Boolean))].sort();
        const authors = [...new Set(entries.map(e => e.comic.author).filter(Boolean))].sort();
        const languages = [...new Set(entries.map(e => e.comic.language).filter(Boolean))].sort();
        const allTags = entries.flatMap(e => e.comic.tags || []);
        const tags = [...new Set(allTags)].sort();

        return { genres, publishers, authors, languages, tags };
    }, [entries]);

    const resetFilters = () => {
        onFiltersChange({
            genre: '',
            publisher: '',
            author: '',
            accessType: '',
            rating: '',
            completionStatus: '',
            dateRange: '',
            tags: [],
            language: '',
            priceRange: [0, 100],
        });
    };

    const hasActiveFilters = useMemo(() => {
        return filters.genre || filters.publisher || filters.author || filters.accessType ||
               filters.rating || filters.completionStatus || filters.dateRange ||
               filters.tags.length > 0 || filters.language ||
               (filters.priceRange[0] > 0 || filters.priceRange[1] < 100);
    }, [filters]);

    const handleTagToggle = (tag: string) => {
        const newTags = filters.tags.includes(tag)
            ? filters.tags.filter(t => t !== tag)
            : [...filters.tags, tag];
        onFiltersChange({ tags: newTags });
    };

    const handlePriceRangeChange = (index: number, value: number) => {
        const newRange: [number, number] = [...filters.priceRange];
        newRange[index] = value;
        onFiltersChange({ priceRange: newRange });
    };

    return (
        <div className={`bg-gray-800/50 rounded-lg p-6 space-y-6 ${className}`}>
            {/* Filter Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                    <Filter className="h-5 w-5 text-emerald-400" />
                    <h3 className="text-lg font-semibold text-white">Advanced Filters</h3>
                </div>
                {hasActiveFilters && (
                    <button
                        onClick={resetFilters}
                        className="flex items-center space-x-2 px-3 py-1 text-sm text-gray-400 hover:text-white transition-colors"
                    >
                        <RotateCcw className="h-4 w-4" />
                        <span>Reset</span>
                    </button>
                )}
            </div>

            {/* Filter Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {/* Genre Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Genre</label>
                    <select
                        value={filters.genre}
                        onChange={(e) => onFiltersChange({ genre: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Genres</option>
                        {filterOptions.genres.map(genre => (
                            <option key={genre} value={genre}>{genre}</option>
                        ))}
                    </select>
                </div>

                {/* Publisher Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Publisher</label>
                    <select
                        value={filters.publisher}
                        onChange={(e) => onFiltersChange({ publisher: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Publishers</option>
                        {filterOptions.publishers.map(publisher => (
                            <option key={publisher} value={publisher}>{publisher}</option>
                        ))}
                    </select>
                </div>

                {/* Author Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Author</label>
                    <select
                        value={filters.author}
                        onChange={(e) => onFiltersChange({ author: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Authors</option>
                        {filterOptions.authors.map(author => (
                            <option key={author} value={author}>{author}</option>
                        ))}
                    </select>
                </div>

                {/* Access Type Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Access Type</label>
                    <select
                        value={filters.accessType}
                        onChange={(e) => onFiltersChange({ accessType: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Types</option>
                        <option value="free">Free</option>
                        <option value="purchased">Purchased</option>
                        <option value="subscription">Subscription</option>
                    </select>
                </div>

                {/* Rating Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Your Rating</label>
                    <select
                        value={filters.rating}
                        onChange={(e) => onFiltersChange({ rating: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4+ Stars</option>
                        <option value="3">3+ Stars</option>
                        <option value="2">2+ Stars</option>
                        <option value="1">1+ Stars</option>
                        <option value="unrated">Unrated</option>
                    </select>
                </div>

                {/* Completion Status Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Reading Status</label>
                    <select
                        value={filters.completionStatus}
                        onChange={(e) => onFiltersChange({ completionStatus: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Status</option>
                        <option value="unread">Unread (0%)</option>
                        <option value="reading">In Progress (1-99%)</option>
                        <option value="completed">Completed (100%)</option>
                    </select>
                </div>

                {/* Language Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Language</label>
                    <select
                        value={filters.language}
                        onChange={(e) => onFiltersChange({ language: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Languages</option>
                        {filterOptions.languages.map(language => (
                            <option key={language} value={language}>{language}</option>
                        ))}
                    </select>
                </div>

                {/* Date Range Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">Added to Library</label>
                    <select
                        value={filters.dateRange}
                        onChange={(e) => onFiltersChange({ dateRange: e.target.value })}
                        className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="3months">Last 3 Months</option>
                        <option value="6months">Last 6 Months</option>
                        <option value="year">This Year</option>
                    </select>
                </div>

                {/* Price Range Filter */}
                <div className="space-y-2">
                    <label className="block text-sm font-medium text-gray-300">
                        Purchase Price Range (${filters.priceRange[0]} - ${filters.priceRange[1]})
                    </label>
                    <div className="space-y-2">
                        <div className="flex items-center space-x-2">
                            <span className="text-xs text-gray-400 w-8">$0</span>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value={filters.priceRange[0]}
                                onChange={(e) => handlePriceRangeChange(0, parseInt(e.target.value))}
                                className="flex-1 h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer slider"
                            />
                            <span className="text-xs text-gray-400 w-12">$100+</span>
                        </div>
                        <div className="flex items-center space-x-2">
                            <span className="text-xs text-gray-400 w-8">$0</span>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value={filters.priceRange[1]}
                                onChange={(e) => handlePriceRangeChange(1, parseInt(e.target.value))}
                                className="flex-1 h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer slider"
                            />
                            <span className="text-xs text-gray-400 w-12">$100+</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Tags Filter */}
            {filterOptions.tags.length > 0 && (
                <div className="space-y-3">
                    <label className="block text-sm font-medium text-gray-300">Tags</label>
                    <div className="flex flex-wrap gap-2">
                        {filterOptions.tags.slice(0, 20).map(tag => (
                            <button
                                key={tag}
                                onClick={() => handleTagToggle(tag)}
                                className={`px-3 py-1 text-sm rounded-full border transition-colors ${
                                    filters.tags.includes(tag)
                                        ? 'bg-emerald-500 text-white border-emerald-500'
                                        : 'bg-gray-700 text-gray-300 border-gray-600 hover:border-emerald-500 hover:text-emerald-400'
                                }`}
                            >
                                {tag}
                            </button>
                        ))}
                        {filterOptions.tags.length > 20 && (
                            <span className="px-3 py-1 text-sm text-gray-400">
                                +{filterOptions.tags.length - 20} more
                            </span>
                        )}
                    </div>
                </div>
            )}

            {/* Active Filters Summary */}
            {hasActiveFilters && (
                <div className="border-t border-gray-700 pt-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-400">Active Filters:</span>
                        <div className="flex flex-wrap gap-2">
                            {filters.genre && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Genre: {filters.genre}</span>
                                    <button onClick={() => onFiltersChange({ genre: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.publisher && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Publisher: {filters.publisher}</span>
                                    <button onClick={() => onFiltersChange({ publisher: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.author && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Author: {filters.author}</span>
                                    <button onClick={() => onFiltersChange({ author: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.accessType && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Type: {filters.accessType}</span>
                                    <button onClick={() => onFiltersChange({ accessType: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.rating && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Rating: {filters.rating === 'unrated' ? 'Unrated' : `${filters.rating}+ Stars`}</span>
                                    <button onClick={() => onFiltersChange({ rating: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.completionStatus && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Status: {filters.completionStatus}</span>
                                    <button onClick={() => onFiltersChange({ completionStatus: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.language && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Language: {filters.language}</span>
                                    <button onClick={() => onFiltersChange({ language: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.dateRange && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Added: {filters.dateRange}</span>
                                    <button onClick={() => onFiltersChange({ dateRange: '' })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                            {filters.tags.map(tag => (
                                <span key={tag} className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Tag: {tag}</span>
                                    <button onClick={() => handleTagToggle(tag)}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            ))}
                            {(filters.priceRange[0] > 0 || filters.priceRange[1] < 100) && (
                                <span className="inline-flex items-center space-x-1 px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                                    <span>Price: ${filters.priceRange[0]}-${filters.priceRange[1]}</span>
                                    <button onClick={() => onFiltersChange({ priceRange: [0, 100] })}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default LibraryFilters;