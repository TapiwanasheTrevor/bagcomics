import { useState, useEffect } from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { BookOpen, Clock, Star, TrendingUp, Heart, Calendar, Play, Bookmark, Home, Library, User, Menu, X, Book, Search } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import UserAvatarDropdown from '@/components/UserAvatarDropdown';
import UserMobileMenu from '@/components/UserMobileMenu';



interface Comic {
    id: number;
    title: string;
    author?: string;
    cover_image_url?: string;
    genre?: string;
}

interface UserProgress {
    id: number;
    comic: Comic;
    current_page: number;
    total_pages?: number;
    progress_percentage: number;
    is_completed: boolean;
    last_read_at: string;
}

interface ReadingStats {
    total_comics_read: number;
    total_comics_in_progress: number;
    total_reading_time_minutes: number;
    total_bookmarks: number;
    favorite_genres: string[];
    reading_streak_days: number;
}

export default function Dashboard() {
    const { auth } = usePage().props as any;
    const [stats, setStats] = useState<ReadingStats | null>(null);
    const [recentlyRead, setRecentlyRead] = useState<UserProgress[]>([]);
    const [continueReading, setContinueReading] = useState<UserProgress[]>([]);
    const [featuredComics, setFeaturedComics] = useState<Comic[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    useEffect(() => {
        if (auth.user) {
            fetchDashboardData();
        }
    }, [auth.user]);

    const fetchDashboardData = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const [statsRes, recentRes, continueRes, featuredRes] = await Promise.all([
                fetch('/api/progress/stats', {
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    }
                }),
                fetch('/api/progress/recently-read', {
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    }
                }),
                fetch('/api/progress/continue-reading', {
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    }
                }),
                fetch('/api/comics/featured')
            ]);

            const [statsData, recentData, continueData, featuredData] = await Promise.all([
                statsRes.json(),
                recentRes.json(),
                continueRes.json(),
                featuredRes.json()
            ]);

            setStats(statsData);
            setRecentlyRead(recentData);
            setContinueReading(continueData);
            setFeaturedComics(featuredData);
        } catch (error) {
            console.error('Error fetching dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    const formatReadingTime = (minutes: number) => {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0) {
            return `${hours}h ${mins}m`;
        }
        return `${mins}m`;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString();
    };

    if (!auth.user) {
        return (
            <div className="min-h-screen bg-black text-white">
                <Head title="Dashboard" />
                <div className="container mx-auto px-4 py-8">
                    <div className="text-center">
                        <h1 className="text-3xl font-bold mb-4">Welcome to BagComics</h1>
                        <p className="text-gray-300 mb-8">Please log in to access your reading dashboard.</p>
                        <Link href="/login">
                            <Button className="bg-red-500 hover:bg-red-600">Login</Button>
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-black text-white">
            <Head title="Dashboard" />

            {/* Navigation Header */}
            <header className="bg-gray-800/95 backdrop-blur-sm border-b border-gray-700 sticky top-0 z-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Logo */}
                        <div className="flex items-center space-x-4">
                            <Link href="/" className="flex items-center space-x-3">
                                <img 
                                    src="/images/image.png" 
                                    alt="BAG Comics Logo" 
                                    className="h-8 w-auto"
                                />
                                <div className="text-xl font-bold bg-gradient-to-r from-red-500 via-red-400 to-red-300 bg-clip-text text-transparent">
                                    BAG Comics
                                </div>
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
                            <Link
                                href="/library"
                                className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                            >
                                <Library className="w-4 h-4" />
                                <span>Library</span>
                            </Link>
                        </nav>

                        {/* Search Bar */}
                        <div className="hidden md:flex items-center space-x-4">
                            <div className="relative">
                                <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                <input
                                    type="text"
                                    placeholder="Search comics..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors"
                                />
                            </div>

                            {/* User Account */}
                            {auth.user ? (
                                <UserAvatarDropdown user={auth.user} />
                            ) : (
                                <Link
                                    href="/login"
                                    className="flex items-center space-x-2 px-4 py-2 bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30 rounded-lg transition-all duration-300"
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
                                    className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    <Library className="w-5 h-5" />
                                    <span>Library</span>
                                </Link>

                                {/* Mobile Search */}
                                <div className="px-4 py-2">
                                    <div className="relative">
                                        <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                        <input
                                            type="text"
                                            placeholder="Search comics..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="w-full bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors"
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

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-4xl font-bold mb-4 bg-gradient-to-r from-red-500 to-red-300 bg-clip-text text-transparent">
                        Welcome back, {auth.user.name}!
                    </h1>
                    <p className="text-gray-300 text-lg">
                        {stats && (stats.total_comics_read > 0 || stats.total_comics_in_progress > 0)
                            ? "Your personal African comics reading dashboard"
                            : "Start your African comics reading journey today!"
                        }
                    </p>
                </div>

                {/* Stats Cards */}
                {loading ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="bg-gray-800 rounded-xl p-6 animate-pulse">
                                <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                <div className="h-8 bg-gray-700 rounded"></div>
                            </div>
                        ))}
                    </div>
                ) : stats && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        <div className="bg-gradient-to-r from-red-500/20 to-red-600/20 border border-red-500/30 rounded-xl p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-red-400 text-sm font-medium">Comics Read</p>
                                    <p className="text-2xl font-bold text-white">{stats.total_comics_read}</p>
                                </div>
                                <BookOpen className="w-8 h-8 text-red-400" />
                            </div>
                        </div>

                        <div className="bg-gradient-to-r from-red-600/20 to-red-700/20 border border-red-600/30 rounded-xl p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-purple-400 text-sm font-medium">In Progress</p>
                                    <p className="text-2xl font-bold text-white">{stats.total_comics_in_progress}</p>
                                </div>
                                <TrendingUp className="w-8 h-8 text-purple-400" />
                            </div>
                        </div>

                        <div className="bg-gradient-to-r from-orange-500/20 to-orange-600/20 border border-orange-500/30 rounded-xl p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-orange-400 text-sm font-medium">Reading Time</p>
                                    <p className="text-2xl font-bold text-white">{formatReadingTime(stats.total_reading_time_minutes)}</p>
                                </div>
                                <Clock className="w-8 h-8 text-orange-400" />
                            </div>
                        </div>

                        <div className="bg-gradient-to-r from-pink-500/20 to-pink-600/20 border border-pink-500/30 rounded-xl p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-pink-400 text-sm font-medium">Bookmarks</p>
                                    <p className="text-2xl font-bold text-white">{stats.total_bookmarks}</p>
                                </div>
                                <Bookmark className="w-8 h-8 text-pink-400" />
                            </div>
                        </div>

                        <div className="bg-gradient-to-r from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-xl p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-blue-400 text-sm font-medium">Reading Streak</p>
                                    <p className="text-2xl font-bold text-white">{stats.reading_streak_days} days</p>
                                </div>
                                <Calendar className="w-8 h-8 text-blue-400" />
                            </div>
                        </div>
                    </div>
                )}

                {/* Continue Reading */}
                <div className="mb-12">
                    <div className="flex items-center justify-between mb-8">
                        <div className="flex items-center space-x-3">
                            <Clock className="w-6 h-6 text-red-500" />
                            <h2 className="text-3xl font-bold">Continue Reading</h2>
                        </div>
                        <Link href="/library" className="text-red-400 hover:text-red-300 font-semibold">
                            View All
                        </Link>
                    </div>

                    {continueReading.length > 0 ? (
                        <div className="space-y-4">
                            {continueReading.slice(0, 3).map((progress) => (
                                <div key={progress.id} className="flex bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 group">
                                    <div className="relative w-32 h-48 flex-shrink-0">
                                        {progress.comic.cover_image_url ? (
                                            <img
                                                src={progress.comic.cover_image_url}
                                                alt={progress.comic.title}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-gray-700 flex items-center justify-center">
                                                <BookOpen className="w-8 h-8 text-gray-400" />
                                            </div>
                                        )}

                                        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2">
                                            <div className="w-full bg-gray-600 rounded-full h-1.5">
                                                <div
                                                    className="bg-gradient-to-r from-red-500 to-red-600 h-1.5 rounded-full transition-all duration-300"
                                                    style={{ width: `${Number(progress.progress_percentage || 0)}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-white font-medium">{Number(progress.progress_percentage || 0).toFixed(0)}%</span>
                                        </div>
                                    </div>

                                    <div className="flex-1 p-4 flex flex-col justify-between">
                                        <div>
                                            <div className="flex items-start justify-between mb-2">
                                                <div>
                                                    <h3 className="font-bold text-lg mb-1 group-hover:text-red-400 transition-colors cursor-pointer">
                                                        {progress.comic.title}
                                                    </h3>
                                                    {progress.comic.author && (
                                                        <p className="text-gray-400 text-sm">{progress.comic.author}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-3">
                                                <Link href={`/comics/${progress.comic.slug}`}>
                                                    <button className="flex items-center space-x-1 px-3 py-2 bg-red-500 hover:bg-red-600 rounded-lg transition-colors text-sm font-medium">
                                                        <Play className="w-4 h-4" />
                                                        <span>Continue</span>
                                                    </button>
                                                </Link>
                                            </div>

                                            <div className="text-right text-sm text-gray-400">
                                                <div>Page {progress.current_page}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Clock className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">
                                No comics in progress
                            </h3>
                            <p className="text-gray-500 mb-6">
                                Start reading some comics to see them here
                            </p>
                            <Link href="/comics">
                                <button className="px-6 py-3 bg-red-500 hover:bg-red-600 rounded-lg font-semibold transition-colors">
                                    Browse Comics
                                </button>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Recently Read */}
                <div className="mb-12">
                    <div className="flex items-center justify-between mb-8">
                        <div className="flex items-center space-x-3">
                            <BookOpen className="w-6 h-6 text-red-600" />
                            <h2 className="text-3xl font-bold">Recently Read</h2>
                        </div>
                        <Link href="/library" className="text-purple-400 hover:text-purple-300 font-semibold">
                            View All
                        </Link>
                    </div>

                    {recentlyRead.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {recentlyRead.slice(0, 6).map((progress) => (
                                <div key={progress.id} className="bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-red-600/50 transition-all duration-300 group">
                                    <div className="relative aspect-[2/3] overflow-hidden">
                                        {progress.comic.cover_image_url ? (
                                            <img
                                                src={progress.comic.cover_image_url}
                                                alt={progress.comic.title}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-gray-700 flex items-center justify-center">
                                                <BookOpen className="w-12 h-12 text-gray-400" />
                                            </div>
                                        )}

                                        {progress.is_completed && (
                                            <div className="absolute top-2 right-2 bg-red-500 text-xs px-2 py-1 rounded-full font-semibold">
                                                COMPLETED
                                            </div>
                                        )}
                                    </div>

                                    <div className="p-4">
                                        <h3 className="font-bold text-lg mb-1 group-hover:text-purple-400 transition-colors cursor-pointer truncate">
                                            {progress.comic.title}
                                        </h3>
                                        {progress.comic.author && (
                                            <p className="text-gray-400 text-sm mb-2">{progress.comic.author}</p>
                                        )}
                                        <p className="text-xs text-gray-500 mb-3">
                                            Last read: {formatDate(progress.last_read_at)}
                                        </p>

                                        <Link href={`/comics/${progress.comic.slug}`}>
                                            <button className="w-full px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors text-sm font-medium">
                                                {progress.is_completed ? 'Read Again' : 'Continue Reading'}
                                            </button>
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                <BookOpen className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">
                                No reading history yet
                            </h3>
                            <p className="text-gray-500 mb-6">
                                Start exploring our comics to build your reading history
                            </p>
                            <Link href="/comics">
                                <button className="px-6 py-3 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition-colors">
                                    Browse Comics
                                </button>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Featured Comics */}
                <div className="mb-12">
                    <div className="flex items-center justify-between mb-8">
                        <div className="flex items-center space-x-3">
                            <Star className="w-6 h-6 text-orange-500" />
                            <h2 className="text-3xl font-bold">Featured Comics</h2>
                        </div>
                        <Link href="/comics" className="text-orange-400 hover:text-orange-300 font-semibold">
                            View All
                        </Link>
                    </div>

                    {featuredComics.length > 0 ? (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
                            {featuredComics.slice(0, 6).map((comic) => (
                                <div key={comic.id} className="group cursor-pointer">
                                    <div className="relative aspect-[2/3] rounded-xl overflow-hidden mb-3 bg-gray-800 border border-gray-700/50 hover:border-orange-500/50 transition-all duration-300">
                                        {comic.cover_image_url ? (
                                            <img
                                                src={comic.cover_image_url}
                                                alt={comic.title}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center">
                                                <BookOpen className="w-12 h-12 text-gray-400" />
                                            </div>
                                        )}

                                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />

                                        <div className="absolute bottom-3 left-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <Link href={`/comics/${comic.slug}`}>
                                                <button className="w-full px-3 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors text-sm font-medium">
                                                    Read Now
                                                </button>
                                            </Link>
                                        </div>
                                    </div>

                                    <h3 className="font-semibold mb-1 truncate group-hover:text-orange-400 transition-colors">{comic.title}</h3>
                                    {comic.author && (
                                        <p className="text-sm text-gray-400 mb-2 truncate">{comic.author}</p>
                                    )}
                                    <div className="flex items-center space-x-1">
                                        <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                        <span className="text-sm text-gray-300">{Number(comic.rating || 0).toFixed(1)}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-16">
                            <div className="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Star className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-xl font-semibold text-gray-300 mb-2">
                                No featured comics available
                            </h3>
                            <p className="text-gray-500 mb-6">
                                Check back later for new featured content
                            </p>
                            <Link href="/comics">
                                <button className="px-6 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold transition-colors">
                                    Browse All Comics
                                </button>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Reading Goal & Insights */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Reading Goal */}
                    <div className="bg-gradient-to-r from-gray-800 to-gray-900 rounded-2xl p-8">
                        <div className="flex items-center justify-between mb-6">
                            <div>
                                <h3 className="text-2xl font-bold mb-2">Reading Goal 2024</h3>
                                <p className="text-gray-400">
                                    {stats && stats.total_comics_read > 0
                                        ? "Keep up the great work!"
                                        : "Set a goal and start your reading adventure!"
                                    }
                                </p>
                            </div>
                            <div className="text-right">
                                <div className="text-3xl font-bold text-red-400">
                                    {stats ? stats.total_comics_read : 0}/{stats && stats.total_comics_read > 25 ? 100 : 50}
                                </div>
                                <div className="text-sm text-gray-400">Comics read</div>
                            </div>
                        </div>

                        {stats && (
                            <>
                                <div className="w-full bg-gray-700 rounded-full h-3 mb-4">
                                    <div
                                        className="bg-gradient-to-r from-red-500 to-red-600 h-3 rounded-full transition-all duration-1000"
                                        style={{
                                            width: `${Math.min((stats.total_comics_read / (stats.total_comics_read > 25 ? 100 : 50)) * 100, 100)}%`
                                        }}
                                    />
                                </div>

                                <div className="flex justify-between text-sm text-gray-400">
                                    <span>
                                        {Math.min((stats.total_comics_read / (stats.total_comics_read > 25 ? 100 : 50)) * 100, 100).toFixed(0)}% complete
                                    </span>
                                    <span>
                                        {Math.max((stats.total_comics_read > 25 ? 100 : 50) - stats.total_comics_read, 0)} comics to go
                                    </span>
                                </div>
                            </>
                        )}
                    </div>

                    {/* Reading Insights */}
                    <div className="bg-gradient-to-r from-gray-800 to-gray-900 rounded-2xl p-8">
                        <h3 className="text-2xl font-bold mb-6">Reading Insights</h3>

                        {stats ? (
                            stats.total_comics_read > 0 || stats.total_comics_in_progress > 0 ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-300">Average reading time</span>
                                        <span className="text-white font-semibold">
                                            {stats.total_comics_read > 0
                                                ? formatReadingTime(Math.round(stats.total_reading_time_minutes / stats.total_comics_read))
                                                : '0m'
                                            } per comic
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-300">Comics with bookmarks</span>
                                        <span className="text-white font-semibold">{stats.total_bookmarks}</span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-300">Current streak</span>
                                        <span className="text-white font-semibold">
                                            {stats.reading_streak_days} day{stats.reading_streak_days !== 1 ? 's' : ''}
                                        </span>
                                    </div>

                                    {stats.favorite_genres && stats.favorite_genres.length > 0 && (
                                        <div>
                                            <span className="text-gray-300 block mb-2">Favorite genres</span>
                                            <div className="flex flex-wrap gap-2">
                                                {stats.favorite_genres.slice(0, 3).map((genre, index) => (
                                                    <span
                                                        key={index}
                                                        className="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-sm border border-emerald-500/30"
                                                    >
                                                        {genre}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="text-center text-gray-400 py-8">
                                    <BookOpen className="w-12 h-12 text-gray-500 mx-auto mb-4" />
                                    <p className="text-lg mb-2">No reading activity yet</p>
                                    <p className="text-sm">Start reading comics to unlock insights!</p>
                                    <Link href="/comics">
                                        <button className="mt-4 px-6 py-2 bg-red-500 hover:bg-red-600 rounded-lg font-semibold text-white transition-colors">
                                            Explore Comics
                                        </button>
                                    </Link>
                                </div>
                            )
                        ) : (
                            <div className="text-center text-gray-400">
                                <p>Loading insights...</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
