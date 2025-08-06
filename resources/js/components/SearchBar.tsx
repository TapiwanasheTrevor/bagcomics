import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Search, X, Clock, TrendingUp, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { debounce } from 'lodash';

interface SearchSuggestion {
    id: string;
    type: 'comic' | 'author' | 'genre' | 'tag';
    title: string;
    subtitle?: string;
    image?: string;
    slug?: string;
}

interface SearchBarProps {
    value: string;
    onChange: (value: string) => void;
    onSearch?: (query: string) => void;
    placeholder?: string;
    className?: string;
    showSuggestions?: boolean;
    recentSearches?: string[];
    onRecentSearchClick?: (search: string) => void;
    onClearRecentSearches?: () => void;
}

export const SearchBar: React.FC<SearchBarProps> = ({
    value,
    onChange,
    onSearch,
    placeholder = "Search comics, authors, genres...",
    className = "",
    showSuggestions = true,
    recentSearches = [],
    onRecentSearchClick,
    onClearRecentSearches
}) => {
    const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
    const [showDropdown, setShowDropdown] = useState(false);
    const [loading, setLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(-1);
    const [trendingSearches] = useState<string[]>([
        'Marvel', 'DC Comics', 'Manga', 'Superhero', 'Fantasy'
    ]);

    const inputRef = useRef<HTMLInputElement>(null);
    const dropdownRef = useRef<HTMLDivElement>(null);

    // Debounced search function
    const debouncedFetchSuggestions = useCallback(
        debounce(async (query: string) => {
            if (query.length < 2) {
                setSuggestions([]);
                setLoading(false);
                return;
            }

            try {
                const response = await fetch(`/api/comics/search/suggestions?q=${encodeURIComponent(query)}`);
                if (response.ok) {
                    const data = await response.json();
                    setSuggestions(data.suggestions || []);
                } else {
                    // Fallback to basic search if suggestions endpoint doesn't exist
                    const searchResponse = await fetch(`/api/comics?search=${encodeURIComponent(query)}&limit=5`);
                    if (searchResponse.ok) {
                        const searchData = await searchResponse.json();
                        const comicSuggestions: SearchSuggestion[] = searchData.data.map((comic: any) => ({
                            id: comic.id.toString(),
                            type: 'comic' as const,
                            title: comic.title,
                            subtitle: comic.author,
                            image: comic.cover_image_url,
                            slug: comic.slug
                        }));
                        setSuggestions(comicSuggestions);
                    }
                }
            } catch (error) {
                console.error('Error fetching suggestions:', error);
                setSuggestions([]);
            } finally {
                setLoading(false);
            }
        }, 300),
        []
    );

    useEffect(() => {
        if (value && showSuggestions) {
            setLoading(true);
            debouncedFetchSuggestions(value);
        } else {
            setSuggestions([]);
            setLoading(false);
        }
    }, [value, showSuggestions, debouncedFetchSuggestions]);

    // Handle click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target as Node) &&
                !inputRef.current?.contains(event.target as Node)
            ) {
                setShowDropdown(false);
                setSelectedIndex(-1);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newValue = e.target.value;
        onChange(newValue);
        setShowDropdown(true);
        setSelectedIndex(-1);
    };

    const handleInputFocus = () => {
        setShowDropdown(true);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!showDropdown) return;

        const totalItems = suggestions.length + recentSearches.length + trendingSearches.length;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setSelectedIndex(prev => (prev < totalItems - 1 ? prev + 1 : -1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                setSelectedIndex(prev => (prev > -1 ? prev - 1 : totalItems - 1));
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0) {
                    handleSuggestionClick(selectedIndex);
                } else {
                    handleSearch();
                }
                break;
            case 'Escape':
                setShowDropdown(false);
                setSelectedIndex(-1);
                inputRef.current?.blur();
                break;
        }
    };

    const handleSearch = () => {
        if (value.trim()) {
            onSearch?.(value.trim());
            setShowDropdown(false);
            setSelectedIndex(-1);
            
            // Add to recent searches
            if (onRecentSearchClick && !recentSearches.includes(value.trim())) {
                // This would typically be handled by the parent component
                console.log('Add to recent searches:', value.trim());
            }
        }
    };

    const handleSuggestionClick = (index: number) => {
        let selectedItem: string | SearchSuggestion;
        let currentIndex = 0;

        // Check recent searches first
        if (index < recentSearches.length) {
            selectedItem = recentSearches[index];
            onChange(selectedItem);
            onRecentSearchClick?.(selectedItem);
        } else {
            currentIndex += recentSearches.length;
            
            // Check trending searches
            if (index < currentIndex + trendingSearches.length) {
                selectedItem = trendingSearches[index - currentIndex];
                onChange(selectedItem);
                onSearch?.(selectedItem);
            } else {
                currentIndex += trendingSearches.length;
                
                // Check suggestions
                if (index < currentIndex + suggestions.length) {
                    selectedItem = suggestions[index - currentIndex];
                    if (selectedItem.type === 'comic' && selectedItem.slug) {
                        window.location.href = `/comics/${selectedItem.slug}`;
                    } else {
                        onChange(selectedItem.title);
                        onSearch?.(selectedItem.title);
                    }
                }
            }
        }

        setShowDropdown(false);
        setSelectedIndex(-1);
    };

    const clearSearch = () => {
        onChange('');
        inputRef.current?.focus();
        setShowDropdown(false);
    };

    const getSuggestionIcon = (type: SearchSuggestion['type']) => {
        switch (type) {
            case 'comic':
                return <BookOpen className="h-4 w-4 text-emerald-400" />;
            case 'author':
                return <div className="h-4 w-4 rounded-full bg-purple-400" />;
            case 'genre':
                return <div className="h-4 w-4 rounded bg-blue-400" />;
            case 'tag':
                return <div className="h-4 w-4 rounded bg-orange-400" />;
            default:
                return <Search className="h-4 w-4 text-gray-400" />;
        }
    };

    const renderDropdownContent = () => {
        const hasContent = recentSearches.length > 0 || suggestions.length > 0 || trendingSearches.length > 0;
        
        if (!hasContent && !loading) {
            return (
                <div className="p-4 text-center text-gray-400 text-sm">
                    {value ? 'No suggestions found' : 'Start typing to search...'}
                </div>
            );
        }

        let currentIndex = 0;

        return (
            <div className="py-2">
                {/* Recent Searches */}
                {recentSearches.length > 0 && (
                    <>
                        <div className="px-4 py-2 flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Clock className="h-4 w-4 text-gray-400" />
                                <span className="text-sm font-medium text-gray-300">Recent</span>
                            </div>
                            {onClearRecentSearches && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={onClearRecentSearches}
                                    className="text-xs text-gray-400 hover:text-white h-6 px-2"
                                >
                                    Clear
                                </Button>
                            )}
                        </div>
                        {recentSearches.map((search, index) => (
                            <button
                                key={`recent-${index}`}
                                className={`w-full px-4 py-2 text-left hover:bg-gray-700/50 transition-colors flex items-center space-x-3 ${
                                    selectedIndex === currentIndex + index ? 'bg-gray-700/50' : ''
                                }`}
                                onClick={() => handleSuggestionClick(currentIndex + index)}
                            >
                                <Clock className="h-4 w-4 text-gray-400" />
                                <span className="text-sm">{search}</span>
                            </button>
                        ))}
                        <Separator className="my-2" />
                    </>
                )}

                {/* Update current index */}
                {(() => { currentIndex += recentSearches.length; return null; })()}

                {/* Trending Searches */}
                {!value && trendingSearches.length > 0 && (
                    <>
                        <div className="px-4 py-2 flex items-center space-x-2">
                            <TrendingUp className="h-4 w-4 text-gray-400" />
                            <span className="text-sm font-medium text-gray-300">Trending</span>
                        </div>
                        {trendingSearches.map((search, index) => (
                            <button
                                key={`trending-${index}`}
                                className={`w-full px-4 py-2 text-left hover:bg-gray-700/50 transition-colors flex items-center space-x-3 ${
                                    selectedIndex === currentIndex + index ? 'bg-gray-700/50' : ''
                                }`}
                                onClick={() => handleSuggestionClick(currentIndex + index)}
                            >
                                <TrendingUp className="h-4 w-4 text-emerald-400" />
                                <span className="text-sm">{search}</span>
                                <Badge variant="secondary" className="ml-auto text-xs">
                                    Popular
                                </Badge>
                            </button>
                        ))}
                        <Separator className="my-2" />
                    </>
                )}

                {/* Update current index */}
                {(() => { currentIndex += trendingSearches.length; return null; })()}

                {/* Loading */}
                {loading && (
                    <div className="px-4 py-3 text-center">
                        <div className="inline-flex items-center space-x-2 text-sm text-gray-400">
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-400"></div>
                            <span>Searching...</span>
                        </div>
                    </div>
                )}

                {/* Suggestions */}
                {suggestions.length > 0 && (
                    <>
                        <div className="px-4 py-2 flex items-center space-x-2">
                            <Search className="h-4 w-4 text-gray-400" />
                            <span className="text-sm font-medium text-gray-300">Suggestions</span>
                        </div>
                        {suggestions.map((suggestion, index) => (
                            <button
                                key={`suggestion-${suggestion.id}`}
                                className={`w-full px-4 py-2 text-left hover:bg-gray-700/50 transition-colors flex items-center space-x-3 ${
                                    selectedIndex === currentIndex + index ? 'bg-gray-700/50' : ''
                                }`}
                                onClick={() => handleSuggestionClick(currentIndex + index)}
                            >
                                {suggestion.image ? (
                                    <img
                                        src={suggestion.image}
                                        alt={suggestion.title}
                                        className="h-8 w-6 object-cover rounded"
                                    />
                                ) : (
                                    getSuggestionIcon(suggestion.type)
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="text-sm font-medium truncate">{suggestion.title}</div>
                                    {suggestion.subtitle && (
                                        <div className="text-xs text-gray-400 truncate">{suggestion.subtitle}</div>
                                    )}
                                </div>
                                <Badge variant="outline" className="text-xs capitalize">
                                    {suggestion.type}
                                </Badge>
                            </button>
                        ))}
                    </>
                )}
            </div>
        );
    };

    return (
        <div className={`relative ${className}`}>
            <div className="relative">
                <Search className="w-4 h-4 text-gray-400 absolute left-2 sm:left-3 top-1/2 transform -translate-y-1/2 pointer-events-none" />
                <input
                    ref={inputRef}
                    type="text"
                    placeholder={placeholder}
                    value={value}
                    onChange={handleInputChange}
                    onFocus={handleInputFocus}
                    onKeyDown={handleKeyDown}
                    className="w-full bg-gray-700/50 border border-gray-600 rounded-lg pl-8 sm:pl-10 pr-8 sm:pr-10 py-2 sm:py-2.5 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors placeholder-gray-400"
                />
                {value && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={clearSearch}
                        className="absolute right-1 top-1/2 transform -translate-y-1/2 h-6 w-6 sm:h-8 sm:w-8 p-0 hover:bg-gray-600"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Dropdown */}
            {showDropdown && (
                <div
                    ref={dropdownRef}
                    className="absolute top-full left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50 max-h-80 sm:max-h-96 overflow-y-auto"
                >
                    {renderDropdownContent()}
                </div>
            )}
        </div>
    );
};

export default SearchBar;