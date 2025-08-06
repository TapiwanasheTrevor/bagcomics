import { useState, useEffect, useCallback } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { Home, Library, User, Menu, X, Book, Grid, List, Settings, LogOut, ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import ComicGrid, { type Comic } from '@/components/ComicGrid';
import FilterSidebar, { type FilterOptions } from '@/components/FilterSidebar';
import SearchBar from '@/components/SearchBar';
import SortDropdown, { discoverySortOptions } from '@/components/SortDropdown';

interface ComicsResponse {
    data: Comic[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

// User Avatar Dropdown Component
interface UserAvatarDropdownProps {
    user: any;
}

function UserAvatarDropdown({ user }: UserAvatarDropdownProps) {
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="flex items-center space-x-2 px-3 py-2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg transition-all duration-300 hover:bg-emerald-500/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold text-sm">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <ChevronDown className="h-4 w-4 opacity-70" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end">
                <DropdownMenuLabel>
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium">{user.name}</p>
                        <p className="text-xs text-muted-foreground">{user.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href="/dashboard" className="flex items-center cursor-pointer">
                            <User className="mr-2 h-4 w-4" />
                            <span>Profile</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function ComicsIndex() {
    const { auth } = usePage<SharedData>().props;
    const [comics, setComics] = useState<Comic[]>([]);
    const [loading, setLoading] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const [search, setSearch] = useState('');
    const [sortBy, setSortBy] = useState('published_at');
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [recentSearches, setRecentSearches] = useState<string[]>([]);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: 0,
    });

    // Filter state
    const [filters, setFilters] = useState<FilterOptions>({
        genres: [],
        tags: [],
        languages: [],
        priceRange: 'all',
        rating: 0,
        matureContent: false,
        newReleases: false,
        selectedGenres: [],
        selectedTags: [],
        selectedLanguages: [],
    });

    // Load initial comics and recent searches
    useEffect(() => {
        fetchComics(true);
        loadRecentSearches();
    }, []);

    // Refetch when filters change
    useEffect(() => {
        if (pagination.current_page === 1) {
            fetchComics(true);
        } else {
            setPagination(prev => ({ ...prev, current_page: 1 }));
        }
    }, [search, sortBy, filters]);

    // Refetch when page changes
    useEffect(() => {
        if (pagination.current_page > 1) {
            fetchComics(false);
        }
    }, [pagination.current_page]);

    const loadRecentSearches = () => {
        try {
            const saved = localStorage.getItem('comic-recent-searches');
            if (saved) {
                setRecentSearches(JSON.parse(saved));
            }
        } catch (error) {
            console.error('Error loading recent searches:', error);
        }
    };

    const saveRecentSearch = (searchTerm: string) => {
        try {
            const updated = [searchTerm, ...recentSearches.filter(s => s !== searchTerm)].slice(0, 5);
            setRecentSearches(updated);
            localStorage.setItem('comic-recent-searches', JSON.stringify(updated));
        } catch (error) {
            console.error('Error saving recent search:', error);
        }
    };

    const clearRecentSearches = () => {
        setRecentSearches([]);
        localStorage.removeItem('comic-recent-searches');
    };

    const fetchComics = async (reset = false) => {
        setLoading(true);
        try {
            const page = reset ? 1 : pagination.current_page;
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: pagination.per_page.toString(),
                sort_by: sortBy,
                sort_order: 'desc',
            });

            // Add search
            if (search) params.append('search', search);

            // Add filters
            if (filters.selectedGenres.length > 0) {
                params.append('genre', filters.selectedGenres.join(','));
            }
            if (filters.selectedTags.length > 0) {
                params.append('tags', filters.selectedTags.join(','));
            }
            if (filters.selectedLanguages.length > 0) {
                params.append('language', filters.selectedLanguages.join(','));
            }
            if (filters.priceRange !== 'all') {
                params.append('is_free', (filters.priceRange === 'free').toString());
            }
            if (filters.matureContent) {
                params.append('has_mature_content', 'true');
            }

            const response = await fetch(`/api/comics?${params}`);
            const data: ComicsResponse = await response.json();

            if (reset) {
                setComics(data.data);
            } else {
                setComics(prev => [...prev, ...data.data]);
            }

            setPagination(data.pagination);
            setHasMore(data.pagination.current_page < data.pagination.last_page);
        } catch (error) {
            console.error('Error fetching comics:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleLoadMore = useCallback(() => {
        if (!loading && hasMore) {
            setPagination(prev => ({ ...prev, current_page: prev.current_page + 1 }));
        }
    }, [loading, hasMore]);

    const handleSearch = (value: string) => {
        setSearch(value);
        if (value.trim()) {
            saveRecentSearch(value.trim());
        }
    };

    const handleFiltersChange = (newFilters: Partial<FilterOptions>) => {
        setFilters(prev => ({ ...prev, ...newFilters }));
    };

    const handleClearFilters = () => {
        setFilters({
            genres: filters.genres,
            tags: filters.tags,
            languages: filters.languages,
            priceRange: 'all',
            rating: 0,
            matureContent: false,
            newReleases: false,
            selectedGenres: [],
            selectedTags: [],
            selectedLanguages: [],
        });
    };

    const handleComicAction = async (comic: Comic, action: 'bookmark' | 'favorite') => {
        if (!auth.user) {
            window.location.href = '/login';
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (action === 'bookmark') {
                console.log('Bookmark comic:', comic.title);
            } else if (action === 'favorite') {
                console.log('Favorite comic:', comic.title);
            }
        } catch (error) {
            console.error(`Error ${action}ing comic:`, error);
        }
    }; 
   return (
        <>
            <Head title="Comics - BagComics">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-gray-900 text-white">
                {/* Header */}
                <header className="bg-gray-800/95 backdrop-blur-sm border-b border-gray-700 sticky top-0 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Logo */}
                            <div className="flex items-center space-x-4">
                                <Link href="/" className="text-2xl font-bold bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
                                    BAG Comics
                                </Link>
                            </div>

                            {/* Desktop Navigation */}
                            <nav className="hidden md:flex items-center space-x-8">
                                <Link
                                    href="/"
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                >
                                    <Home className="w-4 h-4" />
                                    <span>Home</span>
                                </Link>
                                <Link
                                    href="/comics"
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                                >
                                    <Book className="w-4 h-4" />
                                    <span>Explore</span>
                                </Link>
                                {auth.user && (
                                    <Link
                                        href="/library"
                                        className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                    >
                                        <Library className="w-4 h-4" />
                                        <span>Library</span>
                                    </Link>
                                )}
                            </nav>

                            {/* Search Bar */}
                            <div className="hidden md:flex items-center space-x-4">
                                <SearchBar
                                    value={search}
                                    onChange={setSearch}
                                    onSearch={handleSearch}
                                    placeholder="Search comics, authors, genres..."
                                    className="w-80"
                                    recentSearches={recentSearches}
                                    onRecentSearchClick={handleSearch}
                                    onClearRecentSearches={clearRecentSearches}
                                />

                                {/* User Account */}
                                {auth.user ? (
                                    <UserAvatarDropdown user={auth.user} />
                                ) : (
                                    <Link
                                        href="/login"
                                        className="flex items-center space-x-2 px-4 py-2 bg-purple-500/20 text-purple-400 border border-purple-500/30 hover:bg-purple-500/30 rounded-lg transition-all duration-300"
                                    >
                                        <User className="w-4 h-4" />
                                        <span className="text-sm">Sign In</span>
                                    </Link>
                                )}
                            </div>

                            {/* Mobile Menu Button */}
                            <button
                                onClick={() => setIsMenuOpen(!isMenuOpen)}
                                className="md:hidden p-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors"
                            >
                                {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                            </button>
                        </div>
                    </div>
                </header>       
         {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-4xl font-bold mb-4 bg-gradient-to-r from-emerald-400 to-purple-400 bg-clip-text text-transparent">
                            Explore Comics
                        </h1>
                        <p className="text-gray-300 text-lg">
                            Discover amazing African stories from our diverse collection
                        </p>
                    </div>

                    {/* Mobile Search */}
                    <div className="md:hidden mb-6">
                        <SearchBar
                            value={search}
                            onChange={setSearch}
                            onSearch={handleSearch}
                            placeholder="Search comics, authors, genres..."
                            recentSearches={recentSearches}
                            onRecentSearchClick={handleSearch}
                            onClearRecentSearches={clearRecentSearches}
                        />
                    </div>

                    <div className="flex gap-8">
                        {/* Desktop Sidebar */}
                        <div className="hidden lg:block">
                            <FilterSidebar
                                filters={filters}
                                onFiltersChange={handleFiltersChange}
                                onClearFilters={handleClearFilters}
                                loading={loading}
                            />
                        </div>

                        {/* Main Content */}
                        <div className="flex-1">
                            {/* Controls */}
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <div className="flex items-center space-x-4">
                                    {/* Mobile Filter Button */}
                                    <div className="lg:hidden">
                                        <FilterSidebar
                                            filters={filters}
                                            onFiltersChange={handleFiltersChange}
                                            onClearFilters={handleClearFilters}
                                            loading={loading}
                                            isMobile={true}
                                        />
                                    </div>

                                    <SortDropdown
                                        value={sortBy}
                                        onChange={setSortBy}
                                        options={discoverySortOptions}
                                    />
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Button
                                        variant={viewMode === 'grid' ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => setViewMode('grid')}
                                        className="p-2"
                                    >
                                        <Grid className="w-4 h-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'list' ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => setViewMode('list')}
                                        className="p-2"
                                    >
                                        <List className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>

                            {/* Results Count */}
                            <div className="mb-6">
                                <p className="text-gray-400">
                                    {pagination.total > 0 ? (
                                        <>Showing {comics.length} of {pagination.total} comics</>
                                    ) : (
                                        'No comics found'
                                    )}
                                </p>
                            </div>

                            {/* Comics Grid */}
                            <ComicGrid
                                comics={comics}
                                loading={loading}
                                hasMore={hasMore}
                                onLoadMore={handleLoadMore}
                                viewMode={viewMode}
                                onComicAction={handleComicAction}
                            />
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}