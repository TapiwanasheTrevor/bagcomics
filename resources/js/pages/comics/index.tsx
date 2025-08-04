import { useState, useEffect, useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { Search, Filter, Star, Clock, BookOpen, Home, Library, User, Menu, X, Book, ChevronLeft, ChevronRight, Grid, List, Play, Bookmark, Heart, Download, Settings, LogOut, ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';

interface Comic {
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
}

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
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile" className="flex items-center cursor-pointer">
                            <Settings className="mr-2 h-4 w-4" />
                            <span>Settings</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/library" className="flex items-center cursor-pointer">
                            <Library className="mr-2 h-4 w-4" />
                            <span>My Library</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild variant="destructive">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center cursor-pointer w-full"
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        <span>Log out</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function ComicsIndex() {
    const { auth } = usePage<SharedData>().props;
    const [comics, setComics] = useState<Comic[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [genre, setGenre] = useState('');
    const [sortBy, setSortBy] = useState('published_at');
    const [genres, setGenres] = useState<string[]>([]);
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [filterPrice, setFilterPrice] = useState('all');
    const [showFilters, setShowFilters] = useState(false);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: 0,
    });

    useEffect(() => {
        fetchComics();
        fetchGenres();
    }, [search, genre, sortBy, filterPrice, pagination.current_page]);

    // Filter and sort comics locally for better UX
    const filteredAndSortedComics = useMemo(() => {
        let filtered = comics.filter(comic => {
            const matchesSearch = comic.title.toLowerCase().includes(search.toLowerCase()) ||
                               (comic.author && comic.author.toLowerCase().includes(search.toLowerCase()));
            const matchesGenre = !genre || comic.genre === genre;
            const matchesPrice = filterPrice === 'all' ||
                              (filterPrice === 'free' && comic.is_free) ||
                              (filterPrice === 'paid' && !comic.is_free);

            return matchesSearch && matchesGenre && matchesPrice;
        });

        return filtered.sort((a, b) => {
            switch (sortBy) {
                case 'published_at':
                    return new Date(b.published_at).getTime() - new Date(a.published_at).getTime();
                case 'average_rating':
                    return b.average_rating - a.average_rating;
                case 'title':
                    return a.title.localeCompare(b.title);
                case 'total_readers':
                    return b.total_readers - a.total_readers;
                default:
                    return 0;
            }
        });
    }, [comics, search, genre, filterPrice, sortBy]);

    const fetchComics = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: pagination.current_page.toString(),
                per_page: pagination.per_page.toString(),
                sort_by: sortBy,
                sort_order: 'desc',
            });

            if (search) params.append('search', search);
            if (genre) params.append('genre', genre);
            if (filterPrice !== 'all') params.append('price_filter', filterPrice);

            const response = await fetch(`/api/comics?${params}`);
            const data: ComicsResponse = await response.json();

            setComics(data.data);
            if (data.pagination) {
                            setPagination(data.pagination);
                        }
        } catch (error) {
            console.error('Error fetching comics:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchGenres = async () => {
        try {
            const response = await fetch('/api/comics/genres');
            const data = await response.json();
            setGenres(data);
        } catch (error) {
            console.error('Error fetching genres:', error);
        }
    };

    const handleSearch = (value: string) => {
        setSearch(value);
        setPagination(prev => ({ ...prev, current_page: 1 }));
    };

    const handleGenreChange = (value: string) => {
        setGenre(value === 'all' ? '' : value);
        setPagination(prev => ({ ...prev, current_page: 1 }));
    };

    const handleSortChange = (value: string) => {
        setSortBy(value);
        setPagination(prev => ({ ...prev, current_page: 1 }));
    };

    const formatPrice = (price?: number | string) => {
        const numPrice = Number(price || 0);
        return numPrice > 0 ? `$${numPrice.toFixed(2)}` : 'Free';
    };

    const formatRating = (rating: number | string) => {
        const numRating = Number(rating || 0);
        return numRating > 0 ? numRating.toFixed(1) : 'No ratings';
    };

    const ComicGridCard: React.FC<{ comic: Comic }> = ({ comic }) => (
        <div className="group cursor-pointer bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/20">
            <Link href={`/comics/${comic.slug}`} className="block">
                <div className="relative">
                    {comic.cover_image_url ? (
                        <img
                            src={comic.cover_image_url}
                            alt={comic.title}
                            className="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                        />
                    ) : (
                        <div className="w-full h-64 bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                            <BookOpen className="h-16 w-16 text-gray-500" />
                        </div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

                    {/* Badges */}
                    <div className="absolute top-3 left-3 flex flex-col gap-1">
                        {comic.is_free && (
                            <span className="bg-emerald-500 text-xs px-2 py-1 rounded-full font-semibold">FREE</span>
                        )}
                        {comic.is_new_release && (
                            <span className="bg-orange-500 text-xs px-2 py-1 rounded-full font-semibold">NEW</span>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="absolute top-3 right-3 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            className="p-2 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
                            onClick={(e) => {
                                e.preventDefault();
                                window.location.href = `/comics/${comic.slug}`;
                            }}
                        >
                            <Play className="w-4 h-4" />
                        </button>
                        <button
                            className="p-2 bg-gray-600 hover:bg-gray-500 rounded-full transition-colors"
                            onClick={(e) => e.preventDefault()}
                        >
                            <Bookmark className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div className="p-4">
                    <h3 className="font-bold text-lg mb-1 line-clamp-1">{comic.title}</h3>
                    <p className="text-gray-400 text-sm mb-2">{comic.author || 'Unknown Author'}</p>
                    <p className="text-gray-300 text-sm mb-3 line-clamp-2">{comic.description || 'No description available'}</p>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-1">
                            <Star className="w-4 h-4 text-yellow-400 fill-current" />
                            <span className="text-sm text-gray-300">{formatRating(comic.average_rating)}</span>
                            <span className="text-gray-500 text-sm">({comic.page_count || 0} pages)</span>
                        </div>
                        <div className="text-emerald-400 font-semibold">
                            {comic.is_free ? 'FREE' : formatPrice(comic.price)}
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    );

    const ComicListCard: React.FC<{ comic: Comic }> = ({ comic }) => (
        <div className="flex bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 cursor-pointer hover:shadow-lg hover:shadow-emerald-500/10">
            <Link href={`/comics/${comic.slug}`} className="flex w-full">
                <div className="relative w-24 h-36 flex-shrink-0">
                    {comic.cover_image_url ? (
                        <img
                            src={comic.cover_image_url}
                            alt={comic.title}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                            <BookOpen className="h-8 w-8 text-gray-500" />
                        </div>
                    )}
                    {comic.is_free && (
                        <span className="absolute top-1 left-1 bg-emerald-500 text-xs px-1.5 py-0.5 rounded-full font-semibold">FREE</span>
                    )}
                </div>

                <div className="flex-1 p-4 flex flex-col justify-between">
                    <div>
                        <div className="flex items-start justify-between mb-2">
                            <div>
                                <h3 className="font-bold text-lg mb-1">{comic.title}</h3>
                                <p className="text-gray-400 text-sm">{comic.author || 'Unknown Author'}</p>
                            </div>
                            <div className="flex items-center space-x-2">
                                <button
                                    className="p-2 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        window.location.href = `/comics/${comic.slug}`;
                                    }}
                                >
                                    <Play className="w-4 h-4" />
                                </button>
                                <button
                                    className="p-2 bg-gray-600 hover:bg-gray-500 rounded-full transition-colors"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <Bookmark className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p className="text-gray-300 text-sm mb-3 line-clamp-2">{comic.description || 'No description available'}</p>
                    </div>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <div className="flex items-center space-x-1">
                                <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                <span className="text-sm text-gray-300">{formatRating(comic.average_rating)}</span>
                            </div>
                            <span className="text-gray-500 text-sm">{comic.page_count || 0} pages</span>
                            {comic.genre && (
                                <span className="bg-gray-700 text-xs px-2 py-1 rounded-full">{comic.genre}</span>
                            )}
                        </div>
                        <div className="text-emerald-400 font-semibold">
                            {comic.is_free ? 'FREE' : formatPrice(comic.price)}
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    );

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
                                <div className="relative">
                                    <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                    <input
                                        type="text"
                                        placeholder="Search comics..."
                                        value={search}
                                        onChange={(e) => handleSearch(e.target.value)}
                                        className="bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                    />
                                </div>

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

                        {/* Mobile Menu */}
                        {isMenuOpen && (
                            <div className="md:hidden py-4 border-t border-gray-700">
                                <div className="flex flex-col space-y-2">
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <Home className="w-5 h-5" />
                                        <span>Home</span>
                                    </Link>
                                    <Link
                                        href="/comics"
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <Book className="w-5 h-5" />
                                        <span>Explore</span>
                                    </Link>
                                    {auth.user && (
                                        <Link
                                            href="/dashboard"
                                            className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <Library className="w-5 h-5" />
                                            <span>Library</span>
                                        </Link>
                                    )}

                                    <div className="px-4 py-2">
                                        <div className="relative">
                                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                            <input
                                                type="text"
                                                placeholder="Search comics..."
                                                value={search}
                                                onChange={(e) => handleSearch(e.target.value)}
                                                className="w-full bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                            />
                                        </div>
                                    </div>

                                    {auth.user ? (
                                        <div className="mx-4 space-y-2">
                                            <div className="flex items-center space-x-3 px-4 py-3 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                                    <AvatarFallback className="bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold text-sm">
                                                        {useInitials()(auth.user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-semibold">{auth.user.name || 'User'}</p>
                                                    <p className="text-xs text-emerald-300">{auth.user.email}</p>
                                                </div>
                                            </div>
                                            <div className="space-y-1">
                                                <Link
                                                    href="/dashboard"
                                                    className="flex items-center space-x-2 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                                                    onClick={() => setIsMenuOpen(false)}
                                                >
                                                    <User className="w-4 h-4" />
                                                    <span>Profile</span>
                                                </Link>
                                                <Link
                                                    href="/settings/profile"
                                                    className="flex items-center space-x-2 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                                                    onClick={() => setIsMenuOpen(false)}
                                                >
                                                    <Settings className="w-4 h-4" />
                                                    <span>Settings</span>
                                                </Link>
                                                <Link
                                                    href="/library"
                                                    className="flex items-center space-x-2 px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                                                    onClick={() => setIsMenuOpen(false)}
                                                >
                                                    <Library className="w-4 h-4" />
                                                    <span>My Library</span>
                                                </Link>
                                                <Link
                                                    href={route('logout')}
                                                    method="post"
                                                    as="button"
                                                    className="flex items-center space-x-2 px-4 py-2 text-red-400 hover:text-red-300 hover:bg-gray-700 rounded-lg transition-colors w-full text-left"
                                                    onClick={() => setIsMenuOpen(false)}
                                                >
                                                    <LogOut className="w-4 h-4" />
                                                    <span>Log out</span>
                                                </Link>
                                            </div>
                                        </div>
                                    ) : (
                                        <Link
                                            href="/login"
                                            className="mx-4 flex items-center justify-center space-x-2 px-4 py-3 bg-purple-500/20 text-purple-400 border border-purple-500/30 rounded-lg transition-all duration-300"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <User className="w-5 h-5" />
                                            <span>Sign In</span>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
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

                    {/* Search and Controls */}
                    <div className="mb-8 space-y-4">
                        {/* Search Bar */}
                        <div className="relative max-w-md">
                            <Search className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search comics or creators..."
                                value={search}
                                onChange={(e) => handleSearch(e.target.value)}
                                className="w-full bg-gray-800 border border-gray-600 rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                            />
                        </div>

                        {/* Controls */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div className="flex items-center space-x-4">
                                <button
                                    onClick={() => setShowFilters(!showFilters)}
                                    className="flex items-center space-x-2 px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg hover:border-gray-500 transition-colors"
                                >
                                    <Filter className="w-4 h-4" />
                                    <span>Filters</span>
                                </button>

                                <select
                                    value={sortBy}
                                    onChange={(e) => handleSortChange(e.target.value)}
                                    className="bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500"
                                >
                                    <option value="published_at">Newest</option>
                                    <option value="average_rating">Highest Rated</option>
                                    <option value="title">Title A-Z</option>
                                    <option value="total_readers">Most Popular</option>
                                </select>
                            </div>

                            <div className="flex items-center space-x-2">
                                <button
                                    onClick={() => setViewMode('grid')}
                                    className={`p-2 rounded-lg transition-colors ${
                                        viewMode === 'grid'
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-gray-800 text-gray-300 hover:text-white'
                                    }`}
                                >
                                    <Grid className="w-5 h-5" />
                                </button>
                                <button
                                    onClick={() => setViewMode('list')}
                                    className={`p-2 rounded-lg transition-colors ${
                                        viewMode === 'list'
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-gray-800 text-gray-300 hover:text-white'
                                    }`}
                                >
                                    <List className="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {/* Filters */}
                        {showFilters && (
                            <div className="bg-gray-800 border border-gray-700 rounded-lg p-4">
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-300 mb-2">Genre</label>
                                        <select
                                            value={genre || 'all'}
                                            onChange={(e) => handleGenreChange(e.target.value)}
                                            className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
                                        >
                                            <option value="all">All Genres</option>
                                            {genres.map((g) => (
                                                <option key={g} value={g}>
                                                    {g.charAt(0).toUpperCase() + g.slice(1)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-300 mb-2">Price</label>
                                        <select
                                            value={filterPrice}
                                            onChange={(e) => setFilterPrice(e.target.value)}
                                            className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
                                        >
                                            <option value="all">All Prices</option>
                                            <option value="free">Free Only</option>
                                            <option value="paid">Paid Only</option>
                                        </select>
                                    </div>

                                    <div className="flex items-end">
                                        <button
                                            onClick={() => {
                                                setGenre('');
                                                setFilterPrice('all');
                                                setSearch('');
                                            }}
                                            className="w-full px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg transition-colors"
                                        >
                                            Clear Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Results */}
                    <div className="mb-6">
                        <p className="text-gray-400">
                            Showing {filteredAndSortedComics.length} comics
                        </p>
                    </div>

                    {/* Comics Grid/List */}
                    {loading ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {Array.from({ length: 8 }).map((_, i) => (
                                <div key={i} className="bg-gray-800/50 rounded-xl overflow-hidden animate-pulse border border-gray-700">
                                    <div className="aspect-[2/3] bg-gray-700"></div>
                                    <div className="p-4">
                                        <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                        <div className="h-3 bg-gray-700 rounded mb-2"></div>
                                        <div className="h-3 bg-gray-700 rounded w-1/2"></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : filteredAndSortedComics.length > 0 ? (
                        viewMode === 'grid' ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                {filteredAndSortedComics.map((comic) => (
                                    <ComicGridCard key={comic.id} comic={comic} />
                                ))}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {filteredAndSortedComics.map((comic) => (
                                    <ComicListCard key={comic.id} comic={comic} />
                                ))}
                            </div>
                        )
                    ) : (
                        <div className="text-center py-16">
                            <p className="text-xl text-gray-400 mb-4">No comics found</p>
                            <p className="text-gray-500">Try adjusting your search or filters</p>
                        </div>
                    )}

                    {/* Pagination */}
                    {pagination.last_page > 1 && (
                        <div className="flex justify-center items-center mt-12 gap-4">
                            <button
                                disabled={pagination.current_page === 1}
                                onClick={() => setPagination(prev => ({ ...prev, current_page: prev.current_page - 1 }))}
                                className="flex items-center space-x-2 px-6 py-3 bg-gray-800/50 border border-gray-600 rounded-lg text-white hover:bg-gray-700/50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                            >
                                <ChevronLeft className="w-4 h-4" />
                                <span>Previous</span>
                            </button>

                            <div className="flex items-center space-x-2">
                                <span className="text-gray-300">Page</span>
                                <span className="px-3 py-1 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg font-semibold">
                                    {pagination.current_page}
                                </span>
                                <span className="text-gray-300">of {pagination.last_page}</span>
                            </div>

                            <button
                                disabled={pagination.current_page === pagination.last_page}
                                onClick={() => setPagination(prev => ({ ...prev, current_page: prev.current_page + 1 }))}
                                className="flex items-center space-x-2 px-6 py-3 bg-gray-800/50 border border-gray-600 rounded-lg text-white hover:bg-gray-700/50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                            >
                                <span>Next</span>
                                <ChevronRight className="w-4 h-4" />
                            </button>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
