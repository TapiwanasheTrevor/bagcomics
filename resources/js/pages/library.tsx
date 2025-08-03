import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { BookOpen, Star, Clock, Download, Play, Heart, Filter, Grid, List, Search, Home, Library as LibraryIcon, User, Menu, X, Book, Settings, LogOut, ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import UserAvatarDropdown from '@/components/UserAvatarDropdown';
import UserMobileMenu from '@/components/UserMobileMenu';

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

interface LibraryEntry {
    id: number;
    comic_id: number;
    access_type: 'free' | 'purchased' | 'subscription';
    purchase_price?: number;
    purchased_at?: string;
    is_favorite: boolean;
    rating?: number;
    review?: string;
    created_at: string;
    comic: Comic;
}

interface LibraryResponse {
    data: LibraryEntry[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}



export default function Library() {
    const { auth } = usePage<SharedData>().props;
    const [libraryEntries, setLibraryEntries] = useState<LibraryEntry[]>([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'all' | 'favorites' | 'reading' | 'completed'>('all');
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [search, setSearch] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: 0,
    });

    useEffect(() => {
        if (auth.user) {
            fetchLibrary();
        }
    }, [auth.user, activeTab]);

    // Separate effect for pagination to avoid infinite loops
    useEffect(() => {
        if (auth.user && pagination.current_page > 1) {
            fetchLibrary();
        }
    }, [pagination.current_page]);

    const fetchLibrary = async () => {
        if (!auth.user) return;
        
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: pagination.current_page.toString(),
                per_page: pagination.per_page.toString(),
            });

            if (activeTab === 'favorites') {
                params.append('is_favorite', 'true');
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(`/api/library?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });
            const data: LibraryResponse = await response.json();

            setLibraryEntries(data.data);
            setPagination(data.pagination);
        } catch (error) {
            console.error('Error fetching library:', error);
        } finally {
            setLoading(false);
        }
    };

    const formatPrice = (price: number | string) => {
        const numPrice = Number(price || 0);
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(numPrice);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString();
    };

    const filteredEntries = libraryEntries.filter(entry => {
        if (search) {
            return entry.comic.title.toLowerCase().includes(search.toLowerCase()) ||
                   entry.comic.author?.toLowerCase().includes(search.toLowerCase());
        }
        return true;
    });

    const tabs = [
        { id: 'all', label: 'All Comics', count: libraryEntries.length },
        { id: 'favorites', label: 'Favorites', count: libraryEntries.filter(e => e.is_favorite).length },
        { id: 'reading', label: 'Reading', count: 0 }, // TODO: Add reading progress filter
        { id: 'completed', label: 'Completed', count: 0 }, // TODO: Add completed filter
    ];

    if (!auth.user) {
        return (
            <>
                <Head title="Library - BagComics" />
                <div className="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white">
                    <div className="flex items-center justify-center min-h-screen">
                        <div className="text-center">
                            <h1 className="text-3xl font-bold mb-4">Access Your Library</h1>
                            <p className="text-gray-400 mb-8">Please log in to view your comic collection.</p>
                            <Link
                                href="/login"
                                className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300"
                            >
                                <User className="w-5 h-5" />
                                <span>Login</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </>
        );
    }

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
                        </div>
                    </div>

                    {/* Favorite indicator */}
                    {entry.is_favorite && (
                        <div className="absolute top-2 right-2">
                            <Heart className="h-5 w-5 text-red-500 fill-current" />
                        </div>
                    )}

                    {/* Access type badge */}
                    <div className="absolute top-2 left-2">
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                            entry.access_type === 'free'
                                ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                : entry.access_type === 'purchased'
                                ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                                : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                        }`}>
                            {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Subscription'}
                        </span>
                    </div>
                </div>

                <div className="p-4">
                    <h3 className="font-semibold text-white mb-1 line-clamp-2 group-hover:text-emerald-400 transition-colors">
                        {entry.comic.title}
                    </h3>
                    {entry.comic.author && (
                        <p className="text-sm text-gray-400 mb-2">{entry.comic.author}</p>
                    )}

                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-1">
                            <Star className="h-3 w-3 text-yellow-400 fill-current" />
                            <span className="text-xs text-gray-400">{Number(entry.comic.average_rating || 0).toFixed(1)}</span>
                        </div>
                        {entry.comic.is_pdf_comic && (
                            <span className="text-xs text-emerald-400 font-medium">PDF</span>
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

                                {entry.comic.is_pdf_comic && (
                                    <span className="text-xs text-emerald-400 font-medium">PDF</span>
                                )}

                                <span className="text-xs text-gray-500">
                                    Added {formatDate(entry.created_at)}
                                </span>
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
                            {entry.is_favorite && (
                                <Heart className="h-5 w-5 text-red-500 fill-current" />
                            )}

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
        <>
            <Head title="Library - BagComics" />
            <div className="min-h-screen bg-gray-900 text-white">
                {/* Header */}
                <header className="bg-gray-800/95 backdrop-blur-sm border-b border-gray-700 sticky top-0 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Logo */}
                            <div className="flex items-center space-x-4">
                                <div className="text-2xl font-bold bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
                                    BAG Comics
                                </div>
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
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                >
                                    <Book className="w-4 h-4" />
                                    <span>Explore</span>
                                </Link>
                                <Link
                                    href="/library"
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                                >
                                    <LibraryIcon className="w-4 h-4" />
                                    <span>Library</span>
                                </Link>
                            </nav>

                            {/* Search Bar */}
                            <div className="hidden md:flex items-center space-x-4">
                                <div className="relative">
                                    <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                    <input
                                        type="text"
                                        placeholder="Search library..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
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
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <Book className="w-5 h-5" />
                                        <span>Explore</span>
                                    </Link>
                                    <Link
                                        href="/library"
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <LibraryIcon className="w-5 h-5" />
                                        <span>Library</span>
                                    </Link>

                                    {/* Mobile Search */}
                                    <div className="px-4 py-2">
                                        <div className="relative">
                                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                            <input
                                                type="text"
                                                placeholder="Search library..."
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                className="w-full bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                            />
                                        </div>
                                    </div>

                                    {/* Mobile User Menu */}
                                    {auth.user && (
                                        <UserMobileMenu user={auth.user} onClose={() => setIsMenuOpen(false)} />
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </header>

                {/* Main Content */}
                <main className="container mx-auto px-4 py-8">
                    {/* Page Header */}
                    <div className="mb-8">
                        <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-emerald-400 to-purple-400 bg-clip-text text-transparent">
                            My Library
                        </h1>
                        <p className="text-gray-400">Your personal collection of comics</p>
                    </div>

                    {/* Controls */}
                    <div className="mb-8 space-y-4">
                        {/* Tabs and View Mode */}
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            {/* Tabs */}
                            <div className="flex space-x-1 bg-gray-800/50 rounded-lg p-1">
                                {tabs.map((tab) => (
                                    <button
                                        key={tab.id}
                                        onClick={() => setActiveTab(tab.id as any)}
                                        className={`px-4 py-2 rounded-md text-sm font-medium transition-all duration-300 ${
                                            activeTab === tab.id
                                                ? 'bg-emerald-500 text-white'
                                                : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
                                        }`}
                                    >
                                        {tab.label} ({tab.count})
                                    </button>
                                ))}
                            </div>

                            {/* View Mode */}
                            <div className="flex items-center space-x-2">
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

                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search your library..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-10 pr-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    {/* Library Content */}
                    {loading ? (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
                            {Array.from({ length: 12 }).map((_, i) => (
                                <div key={i} className="animate-pulse">
                                    <div className="aspect-[2/3] bg-gray-700 rounded-lg mb-3"></div>
                                    <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                    <div className="h-3 bg-gray-700 rounded w-2/3"></div>
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
                        </>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-24 h-24 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-6">
                                <BookOpen className="w-12 h-12 text-gray-600" />
                            </div>
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">
                                {search ? 'No comics found' : 'Your library is empty'}
                            </h3>
                            <p className="text-gray-500 mb-6">
                                {search 
                                    ? 'Try adjusting your search terms'
                                    : 'Start building your collection by exploring our comics'
                                }
                            </p>
                            {!search && (
                                <Link
                                    href="/comics"
                                    className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300"
                                >
                                    <Book className="w-5 h-5" />
                                    <span>Explore Comics</span>
                                </Link>
                            )}
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
