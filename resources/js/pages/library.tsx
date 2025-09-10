import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { BookOpen, Star, Download, Play, Heart, Grid, List, Search, Book, User } from 'lucide-react';
import NavBar from '@/components/NavBar';

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
    reading_progress?: {
        current_page: number;
        total_pages: number;
        percentage: number;
        last_read_at: string;
        status: 'not_started' | 'reading' | 'completed';
    };
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
            } else if (activeTab === 'reading') {
                params.append('status', 'reading');
            } else if (activeTab === 'completed') {
                params.append('status', 'completed');
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
        { id: 'reading', label: 'Reading', count: libraryEntries.filter(e => e.reading_progress?.status === 'reading').length },
        { id: 'completed', label: 'Completed', count: libraryEntries.filter(e => e.reading_progress?.status === 'completed').length },
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
                                className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300"
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

                    {/* Overlay with actions */}
                    <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                        <div className="flex space-x-2">
                            <button className="p-2 bg-red-500 rounded-full text-white hover:bg-red-600 transition-colors">
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
                                ? 'bg-red-600/20 text-red-400 border border-red-600/30'
                                : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                        }`}>
                            {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Subscription'}
                        </span>
                    </div>

                    {/* Reading progress bar */}
                    {entry.reading_progress && entry.reading_progress.percentage > 0 && (
                        <div className="absolute bottom-0 left-0 right-0 bg-black/70 p-2">
                            <div className="flex items-center justify-between text-xs text-white mb-1">
                                <span>
                                    {entry.reading_progress.status === 'completed' 
                                        ? 'Completed' 
                                        : `${entry.reading_progress.percentage}% Read`}
                                </span>
                                <span>{entry.reading_progress.current_page}/{entry.reading_progress.total_pages}</span>
                            </div>
                            <div className="w-full bg-gray-700 rounded-full h-1.5">
                                <div 
                                    className={`h-1.5 rounded-full ${
                                        entry.reading_progress.status === 'completed' 
                                            ? 'bg-green-500' 
                                            : 'bg-red-500'
                                    }`}
                                    style={{ width: `${entry.reading_progress.percentage}%` }}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div className="p-4">
                    <h3 className="font-semibold text-white mb-1 line-clamp-2 group-hover:text-red-400 transition-colors">
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
                            <span className="text-xs text-red-400 font-medium">PDF</span>
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
                                        ? 'bg-red-600/20 text-red-400 border border-red-600/30'
                                        : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'
                                }`}>
                                    {entry.access_type === 'free' ? 'Free' : entry.access_type === 'purchased' ? 'Owned' : 'Subscription'}
                                </span>

                                {entry.comic.is_pdf_comic && (
                                    <span className="text-xs text-red-400 font-medium">PDF</span>
                                )}

                                <span className="text-xs text-gray-500">
                                    Added {formatDate(entry.created_at)}
                                </span>
                            </div>

                            {/* Reading progress */}
                            {entry.reading_progress && entry.reading_progress.percentage > 0 && (
                                <div className="mt-2">
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className={`${
                                            entry.reading_progress.status === 'completed' 
                                                ? 'text-green-400' 
                                                : 'text-gray-400'
                                        }`}>
                                            {entry.reading_progress.status === 'completed' 
                                                ? 'Completed' 
                                                : `Reading: ${entry.reading_progress.percentage}%`}
                                        </span>
                                        <span className="text-gray-500">
                                            {entry.reading_progress.current_page}/{entry.reading_progress.total_pages} pages
                                        </span>
                                    </div>
                                    <div className="w-full bg-gray-700 rounded-full h-1.5">
                                        <div 
                                            className={`h-1.5 rounded-full ${
                                                entry.reading_progress.status === 'completed' 
                                                    ? 'bg-green-500' 
                                                    : 'bg-red-500'
                                            }`}
                                            style={{ width: `${entry.reading_progress.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            )}

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
                                className="px-3 py-1 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600 transition-colors"
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
            <div className="min-h-screen bg-black text-white">
                <NavBar 
                    auth={auth} 
                    currentPage="library"
                    searchValue={searchQuery}
                    onSearchChange={setSearchQuery}
                    onSearch={(query) => {
                        // Library search functionality can be added here
                        console.log('Library search:', query);
                    }}
                />

                {/* Main Content */}
                <main className="container mx-auto px-4 py-8">
                    {/* Page Header */}
                    <div className="mb-8">
                        <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-red-500 to-red-300 bg-clip-text text-transparent">
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
                                                ? 'bg-red-500 text-white'
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
                                            ? 'bg-red-500 text-white'
                                            : 'bg-gray-800 text-gray-400 hover:text-white'
                                    }`}
                                >
                                    <Grid className="h-4 w-4" />
                                </button>
                                <button
                                    onClick={() => setViewMode('list')}
                                    className={`p-2 rounded-lg transition-colors ${
                                        viewMode === 'list'
                                            ? 'bg-red-500 text-white'
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
                                className="w-full pl-10 pr-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
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
                                    className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300"
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
