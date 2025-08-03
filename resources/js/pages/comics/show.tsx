import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { Progress } from '@/components/ui/progress';
import { Star, Clock, BookOpen, Heart, Download, Play, Bookmark, Home, Library, User, Menu, X, Book, ArrowLeft, Share2, Eye, Calendar, Globe, ShoppingCart, Settings, LogOut, ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import SmartPdfViewer from '@/components/SmartPdfViewer';

import PaymentModal from '@/components/PaymentModal';

interface Comic {
    id: number;
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
    pdf_stream_url?: string;
    pdf_download_url?: string;
    user_has_access?: boolean;
    view_count?: number;
    unique_viewers?: number;
    user_progress?: {
        current_page: number;
        total_pages?: number;
        progress_percentage: number;
        is_completed: boolean;
        is_bookmarked: boolean;
        last_read_at?: string;
    };
}

interface ComicShowProps {
    comic: Comic;
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

export default function ComicShow({ comic: initialComic }: ComicShowProps) {
    const { auth } = usePage<SharedData>().props;
    const [comic, setComic] = useState<Comic>(initialComic);
    const [isInLibrary, setIsInLibrary] = useState(false);
    const [isFavorite, setIsFavorite] = useState(false);
    const [loading, setLoading] = useState(false);
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [showPdfViewer, setShowPdfViewer] = useState(false);

    const [showPaymentModal, setShowPaymentModal] = useState(false);

    useEffect(() => {
        if (auth.user) {
            checkLibraryStatus();
        }
        // Track comic view
        trackComicView();
    }, [auth.user]);

    // Prevent body scroll when PDF viewer is open
    useEffect(() => {
        if (showPdfViewer) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }

        // Cleanup on unmount
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [showPdfViewer]);

    const trackComicView = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            await fetch(`/api/comics/${comic.slug}/view`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });
        } catch (error) {
            // Silently fail - view tracking is not critical
            console.debug('View tracking failed:', error);
        }
    };

    const checkLibraryStatus = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch('/api/library', {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) {
                // If unauthorized (401) or other auth errors, just set defaults
                if (response.status === 401 || response.status === 403) {
                    setIsInLibrary(false);
                    setIsFavorite(false);
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Handle both paginated and direct array responses
            const libraryItems = data.data || data || [];
            const libraryEntry = libraryItems.find((entry: any) => entry.comic?.slug === comic.slug);

            setIsInLibrary(!!libraryEntry);
            setIsFavorite(libraryEntry?.is_favorite || false);
        } catch (error) {
            console.error('Error checking library status:', error);
            // Set defaults on any error
            setIsInLibrary(false);
            setIsFavorite(false);
        }
    };

    const addToLibrary = async () => {
        if (!auth.user) {
            window.location.href = '/login';
            return;
        }

        // If comic is not free, show payment modal instead
        if (!comic.is_free) {
            setShowPaymentModal(true);
            return;
        }

        setLoading(true);
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(`/api/library/comics/${comic.slug}`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    access_type: 'free',
                    purchase_price: 0,
                }),
            });

            if (response.ok) {
                setIsInLibrary(true);
                setComic(prev => ({ ...prev, user_has_access: true }));
            }
        } catch (error) {
            console.error('Error adding to library:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePaymentSuccess = async () => {
        // Update library status immediately
        setIsInLibrary(true);
        setShowPaymentModal(false);

        // Refresh library status from server to ensure consistency
        if (auth.user) {
            await checkLibraryStatus();
        }

        // Show success message or redirect to library
        console.log('Payment successful! Comic added to library.');
    };

    const toggleFavorite = async () => {
        if (!auth.user || !isInLibrary) return;
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(`/api/library/comics/${comic.slug}/favorite`, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (response.ok) {
                const data = await response.json();
                setIsFavorite(data.is_favorite);
            }
        } catch (error) {
            console.error('Error toggling favorite:', error);
        }
    };

    const formatPrice = (price?: number | string) => {
        const numPrice = Number(price || 0);
        return numPrice > 0 ? `$${numPrice.toFixed(2)}` : 'Free';
    };

    const formatRating = (rating: number | string) => {
        const numRating = Number(rating || 0);
        return numRating > 0 ? numRating.toFixed(1) : 'No ratings';
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString();
    };

    return (
        <>
            <Head title={`${comic.title} - BagComics`}>
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
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
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

                            {/* User Account */}
                            <div className="hidden md:flex items-center space-x-4">
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
                                    {auth.user && (
                                        <Link
                                            href="/library"
                                            className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <Library className="w-5 h-5" />
                                            <span>Library</span>
                                        </Link>
                                    )}

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
                    {/* Breadcrumb */}
                    <div className="flex items-center space-x-2 text-sm text-gray-400 mb-8">
                        <Link href="/" className="hover:text-emerald-400 transition-colors">Home</Link>
                        <span>/</span>
                        <Link href="/comics" className="hover:text-emerald-400 transition-colors">Comics</Link>
                        <span>/</span>
                        <span className="text-gray-300">{comic.title}</span>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Cover Image */}
                        <div className="lg:col-span-1">
                            <div className="bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700 sticky top-24">
                                <div className="aspect-[2/3] relative overflow-hidden">
                                    {comic.cover_image_url ? (
                                        <img
                                            src={comic.cover_image_url}
                                            alt={comic.title}
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <div className="w-full h-full bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                                            <BookOpen className="h-20 w-20 text-gray-500" />
                                        </div>
                                    )}

                                    {/* Badges */}
                                    <div className="absolute top-4 left-4 flex flex-col gap-2">
                                        {comic.is_new_release && (
                                            <span className="px-3 py-1 bg-emerald-500/90 text-emerald-100 text-sm font-semibold rounded-full">
                                                New Release
                                            </span>
                                        )}
                                        {comic.is_free && (
                                            <span className="px-3 py-1 bg-purple-500/90 text-purple-100 text-sm font-semibold rounded-full">
                                                Free
                                            </span>
                                        )}
                                    </div>

                                    {comic.has_mature_content && (
                                        <span className="absolute top-4 right-4 px-3 py-1 bg-red-500/90 text-red-100 text-sm font-semibold rounded-full">
                                            18+
                                        </span>
                                    )}
                                </div>

                                {/* Action Buttons */}
                                <div className="p-6 space-y-3">
                                    {auth.user ? (
                                        <>
                                            {comic.user_has_access ? (
                                                <button
                                                    className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300 transform hover:scale-105 shadow-lg"
                                                    onClick={() => comic.is_pdf_comic ? setShowPdfViewer(true) : null}
                                                >
                                                    <Play className="w-5 h-5" />
                                                    <span>{comic.user_progress?.current_page > 1 ? 'Continue Reading' : 'Start Reading'}</span>
                                                </button>
                                            ) : (
                                                <button
                                                    className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                                    onClick={addToLibrary}
                                                    disabled={loading}
                                                >
                                                    {comic.is_free ? <BookOpen className="w-5 h-5" /> : <ShoppingCart className="w-5 h-5" />}
                                                    <span>{comic.is_free ? 'Add to Library' : `Purchase ${formatPrice(comic.price)}`}</span>
                                                </button>
                                            )}

                                            {isInLibrary && (
                                                <button
                                                    className="w-full flex items-center justify-center space-x-2 px-6 py-3 bg-gray-800/50 border border-gray-600 text-white rounded-xl hover:bg-gray-700/50 transition-all duration-300"
                                                    onClick={toggleFavorite}
                                                >
                                                    <Heart className={`w-5 h-5 ${isFavorite ? 'fill-red-500 text-red-500' : 'text-gray-400'}`} />
                                                    <span>{isFavorite ? 'Remove from Favorites' : 'Add to Favorites'}</span>
                                                </button>
                                            )}

                                            <button className="w-full flex items-center justify-center space-x-2 px-6 py-3 bg-gray-800/50 border border-gray-600 text-white rounded-xl hover:bg-gray-700/50 transition-all duration-300">
                                                <Share2 className="w-5 h-5" />
                                                <span>Share</span>
                                            </button>
                                        </>
                                    ) : (
                                        <Link
                                            href="/login"
                                            className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-purple-500 to-emerald-500 text-white font-semibold rounded-xl hover:from-purple-600 hover:to-emerald-600 transition-all duration-300 transform hover:scale-105 shadow-lg"
                                        >
                                            <User className="w-5 h-5" />
                                            <span>Login to Read</span>
                                        </Link>
                                    )}
                                </div>

                                {/* Reading Progress */}
                                {comic.user_progress && (
                                    <div className="border-t border-gray-700 p-6">
                                        <h3 className="text-lg font-semibold text-white mb-4">Reading Progress</h3>
                                        <div className="space-y-3">
                                            <div className="flex justify-between text-sm text-gray-300">
                                                <span>Page {comic.user_progress?.current_page || 1}</span>
                                                <span>{Number(comic.user_progress?.progress_percentage || 0).toFixed(1)}%</span>
                                            </div>
                                            <Progress value={Number(comic.user_progress?.progress_percentage || 0)} className="h-2" />
                                            {comic.user_progress?.is_completed && (
                                                <div className="flex items-center justify-center space-x-2 px-3 py-2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg">
                                                    <BookOpen className="w-4 h-4" />
                                                    <span className="font-semibold">Completed</span>
                                                </div>
                                            )}
                                            {comic.user_progress?.last_read_at && (
                                                <p className="text-xs text-gray-400">
                                                    Last read: {new Date(comic.user_progress.last_read_at).toLocaleDateString()}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Comic Details */}
                        <div className="lg:col-span-2">
                            <div className="space-y-8">
                                {/* Title and Basic Info */}
                                <div>
                                    <h1 className="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
                                        {comic.title}
                                    </h1>
                                    {comic.author && (
                                        <p className="text-xl text-gray-300 mb-6">by {comic.author}</p>
                                    )}

                                    <div className="flex flex-wrap items-center gap-4 mb-6">
                                        {comic.genre && (
                                            <span className="px-3 py-1 bg-orange-500/20 text-orange-300 text-sm rounded-full border border-orange-500/30">
                                                {comic.genre}
                                            </span>
                                        )}
                                        <div className="flex items-center gap-2 text-gray-300">
                                            <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                                            <span className="font-semibold">{formatRating(comic.average_rating)}</span>
                                            <span className="text-gray-400">({comic.total_readers.toLocaleString()} readers)</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-gray-300">
                                            <Clock className="h-5 w-5" />
                                            <span>{comic.reading_time_estimate} min read</span>
                                        </div>
                                        {comic.page_count && (
                                            <div className="flex items-center gap-2 text-gray-300">
                                                <BookOpen className="h-5 w-5" />
                                                <span>{comic.page_count} pages</span>
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2 text-gray-300">
                                            <Eye className="h-5 w-5" />
                                            <span>{(comic.view_count || comic.total_readers || 0).toLocaleString()} views</span>
                                        </div>
                                    </div>

                                    <div className="text-3xl font-bold text-emerald-400 mb-6">
                                        {formatPrice(comic.price)}
                                    </div>
                                </div>

                                {/* Description */}
                                {comic.description && (
                                    <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                        <h2 className="text-2xl font-bold text-white mb-4">Description</h2>
                                        <p className="text-gray-300 leading-relaxed text-lg">{comic.description}</p>
                                    </div>
                                )}

                                {/* Content Warnings */}
                                {comic.has_mature_content && comic.content_warnings && (
                                    <div className="bg-red-500/10 rounded-xl p-6 border border-red-500/30">
                                        <h2 className="text-2xl font-bold text-red-400 mb-4 flex items-center gap-2">
                                            <span className="w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white text-sm font-bold">!</span>
                                            Content Warnings
                                        </h2>
                                        <p className="text-red-300 leading-relaxed">{comic.content_warnings}</p>
                                    </div>
                                )}

                                {/* Tags */}
                                {comic.tags && comic.tags.length > 0 && (
                                    <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                        <h2 className="text-2xl font-bold text-white mb-4">Tags</h2>
                                        <div className="flex flex-wrap gap-3">
                                            {comic.tags.map((tag, index) => (
                                                <span
                                                    key={index}
                                                    className="px-3 py-2 bg-purple-500/20 text-purple-300 border border-purple-500/30 rounded-lg text-sm font-medium hover:bg-purple-500/30 transition-colors cursor-pointer"
                                                >
                                                    #{tag}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Publication Details */}
                                <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                    <h2 className="text-2xl font-bold text-white mb-6">Publication Details</h2>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="flex items-center space-x-3">
                                            <Calendar className="w-5 h-5 text-emerald-400" />
                                            <div>
                                                <span className="text-gray-400 text-sm">Published</span>
                                                <p className="text-white font-semibold">{formatDate(comic.published_at)}</p>
                                            </div>
                                        </div>
                                        {comic.publication_year && (
                                            <div className="flex items-center space-x-3">
                                                <Calendar className="w-5 h-5 text-orange-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">Year</span>
                                                    <p className="text-white font-semibold">{comic.publication_year}</p>
                                                </div>
                                            </div>
                                        )}
                                        {comic.publisher && (
                                            <div className="flex items-center space-x-3">
                                                <BookOpen className="w-5 h-5 text-purple-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">Publisher</span>
                                                    <p className="text-white font-semibold">{comic.publisher}</p>
                                                </div>
                                            </div>
                                        )}
                                        {comic.isbn && (
                                            <div className="flex items-center space-x-3">
                                                <BookOpen className="w-5 h-5 text-emerald-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">ISBN</span>
                                                    <p className="text-white font-semibold">{comic.isbn}</p>
                                                </div>
                                            </div>
                                        )}
                                        <div className="flex items-center space-x-3">
                                            <Globe className="w-5 h-5 text-orange-400" />
                                            <div>
                                                <span className="text-gray-400 text-sm">Language</span>
                                                <p className="text-white font-semibold">{comic.language.toUpperCase()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-3">
                                            <Eye className="w-5 h-5 text-purple-400" />
                                            <div>
                                                <span className="text-gray-400 text-sm">Total Readers</span>
                                                <p className="text-white font-semibold">{comic.total_readers.toLocaleString()}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>

            {/* PDF Viewer Modal */}
            {showPdfViewer && comic.is_pdf_comic && comic.pdf_file_path && (
                <div className="fixed inset-0 z-50 bg-black bg-opacity-90">
                    <div className="w-full h-full bg-gray-900 flex flex-col">
                        <div className="flex items-center justify-between p-4 bg-gray-800 border-b border-gray-700 flex-shrink-0">
                            <h2 className="text-xl font-bold text-white">{comic.title}</h2>
                            <button
                                onClick={() => setShowPdfViewer(false)}
                                className="p-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="flex-1 min-h-0">

                                <SmartPdfViewer
                                    fileUrl={`/comics/${comic.slug}/stream`}
                                    fileName={comic.pdf_file_name || `${comic.title}.pdf`}
                                    downloadUrl={comic.user_has_access ? `/comics/${comic.slug}/download` : undefined}
                                    initialPage={comic.user_progress?.current_page || 1}
                                    userHasDownloadAccess={comic.user_has_access}
                                    comicSlug={comic.slug}
                                    onPageChange={async (page) => {
                                    if (auth.user) {
                                        try {
                                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                                            await fetch(`/api/progress/comics/${comic.slug}`, {
                                                method: 'PATCH',
                                                credentials: 'include',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': csrfToken || ''
                                                },
                                                body: JSON.stringify({
                                                    current_page: page,
                                                    total_pages: comic.page_count,
                                                }),
                                            });
                                        } catch (error) {
                                            console.error('Error updating progress:', error);
                                        }
                                    }
                                }}
                                />
                        </div>
                    </div>
                </div>
            )}

            {/* Payment Modal */}
            <PaymentModal
                comic={comic}
                isOpen={showPaymentModal}
                onClose={() => setShowPaymentModal(false)}
                onSuccess={handlePaymentSuccess}
            />
        </>
    );
}
