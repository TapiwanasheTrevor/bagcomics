import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { Progress } from '@/components/ui/progress';
import { Star, Clock, BookOpen, Heart, Download, Play, Bookmark, ArrowLeft, Eye, Calendar, Globe, ShoppingCart, User } from 'lucide-react';
import EnhancedPdfReader from '@/components/EnhancedPdfReader';
import PaymentModal from '@/components/PaymentModal';
import ReviewModal from '@/components/ReviewModal';
import NavBar from '@/components/NavBar';
import { SocialShareButtons } from '@/components/SocialShareButtons';

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
    pdf_stream_url?: string;
    pdf_download_url?: string;
    user_has_access?: boolean;
    view_count?: number;
    unique_viewers?: number;
    total_ratings?: number;
    user_progress?: {
        current_page: number;
        total_pages?: number;
        progress_percentage: number;
        is_completed: boolean;
        is_bookmarked: boolean;
        last_read_at?: string;
    };
}

interface Review {
    id: number;
    user: {
        id: number;
        name: string;
    };
    rating: number;
    title?: string;
    content: string;
    is_spoiler: boolean;
    created_at: string;
    updated_at: string;
}

interface ComicShowProps {
    comic: Comic;
}


export default function ComicShow({ comic: initialComic }: ComicShowProps) {
    const { auth } = usePage<SharedData>().props;
    const [comic, setComic] = useState<Comic>(initialComic);
    const [isInLibrary, setIsInLibrary] = useState(false);
    const [isFavorite, setIsFavorite] = useState(false);
    const [loading, setLoading] = useState(false);
    const [showPdfViewer, setShowPdfViewer] = useState(false);
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    
    // Reviews state
    const [reviews, setReviews] = useState<Review[]>([]);
    const [reviewStats, setReviewStats] = useState<any>(null);
    const [showReviewModal, setShowReviewModal] = useState(false);
    const [userReview, setUserReview] = useState<Review | null>(null);

    useEffect(() => {
        if (auth.user) {
            checkLibraryStatus();
        }
        // Track comic view
        trackComicView();
        // Load reviews
        fetchReviews();
    }, [auth.user]);

    const fetchReviews = async () => {
        try {
            const response = await fetch(`/api/reviews/comics/${comic.slug}`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                setReviews(data.data.reviews || []);
                setReviewStats(data.data.statistics || {});
                
                // Check if current user has a review
                const currentUserReview = data.data.reviews?.find(
                    (review: Review) => review.user.id === auth.user?.id
                );
                setUserReview(currentUserReview || null);
            }
        } catch (error) {
            console.error('Error fetching reviews:', error);
        }
    };

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

            const response = await fetch(`/api/library/comics/${comic.slug}/add`, {
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
                method: 'POST',
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

    const getShareMessage = () => {
        return `Discovered an awesome comic: "${comic.title}"`;
    };

    const getAbsoluteImageUrl = (imageUrl?: string) => {
        if (!imageUrl) return '';
        if (imageUrl.startsWith('http')) return imageUrl;
        // Use a safe approach for both client and server side
        const origin = typeof window !== 'undefined' ? window.location.origin : '';
        return `${origin}${imageUrl.startsWith('/') ? imageUrl : '/' + imageUrl}`;
    };

    const getShareUrl = () => {
        if (typeof window !== 'undefined') {
            return `${window.location.origin}/comics/${comic.slug}`;
        }
        return `/comics/${comic.slug}`;
    };
    
    const shareUrl = getShareUrl();

    return (
        <>
            <Head title={`${comic.title} - BagComics`}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-black text-white">
                <NavBar 
                    auth={auth}
                    onSearch={(query) => {
                        // Universal search - redirect to comics page with search
                        window.location.href = `/comics?search=${encodeURIComponent(query)}`;
                    }}
                />

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Breadcrumb */}
                    <div className="flex items-center space-x-2 text-sm text-gray-400 mb-8">
                        <Link href="/" className="hover:text-red-400 transition-colors">Home</Link>
                        <span>/</span>
                        <Link href="/comics" className="hover:text-red-400 transition-colors">Comics</Link>
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
                                            <span className="px-3 py-1 bg-red-500/90 text-red-100 text-sm font-semibold rounded-full">
                                                New Release
                                            </span>
                                        )}
                                        {comic.is_free && (
                                            <span className="px-3 py-1 bg-red-600/90 text-red-100 text-sm font-semibold rounded-full">
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
                                                    className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105 shadow-lg"
                                                    onClick={() => comic.is_pdf_comic ? setShowPdfViewer(true) : null}
                                                >
                                                    <Play className="w-5 h-5" />
                                                    <span>{(comic.user_progress?.current_page || 1) > 1 ? 'Continue Reading' : 'Start Reading'}</span>
                                                </button>
                                            ) : (
                                                <button
                                                    className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
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

                                            <div className="w-full">
                                                <SocialShareButtons
                                                    comicId={comic.id}
                                                    comicTitle={comic.title}
                                                    comicCover={getAbsoluteImageUrl(comic.cover_image_url)}
                                                    shareUrl={shareUrl}
                                                    shareType="discovery"
                                                    className="w-full"
                                                />
                                            </div>
                                        </>
                                    ) : (
                                        <Link
                                            href="/login"
                                            className="w-full flex items-center justify-center space-x-2 px-6 py-4 bg-gradient-to-r from-red-600 to-red-500 text-white font-semibold rounded-xl hover:from-red-700 hover:to-red-600 transition-all duration-300 transform hover:scale-105 shadow-lg"
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
                                                <div className="flex items-center justify-center space-x-2 px-3 py-2 bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg">
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
                                    <h1 className="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-red-500 via-red-400 to-red-300 bg-clip-text text-transparent">
                                        {comic.title}
                                    </h1>
                                    {comic.author && (
                                        <p className="text-xl text-gray-300 mb-6">by {comic.author}</p>
                                    )}

                                    <div className="flex flex-wrap items-center gap-4 mb-6">
                                        {comic.genre && (
                                            <span className="px-3 py-1 bg-red-500/20 text-red-300 text-sm rounded-full border border-red-500/30">
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

                                    <div className="text-3xl font-bold text-red-400 mb-6">
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
                                {comic.tags && Array.isArray(comic.tags) && comic.tags.length > 0 && (
                                    <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
                                        <h2 className="text-2xl font-bold text-white mb-4">Tags</h2>
                                        <div className="flex flex-wrap gap-3">
                                            {comic.tags.map((tag, index) => (
                                                <span
                                                    key={index}
                                                    className="px-3 py-2 bg-red-500/20 text-red-300 border border-red-500/30 rounded-lg text-sm font-medium hover:bg-red-500/30 transition-colors cursor-pointer"
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
                                            <Calendar className="w-5 h-5 text-red-400" />
                                            <div>
                                                <span className="text-gray-400 text-sm">Published</span>
                                                <p className="text-white font-semibold">{formatDate(comic.published_at)}</p>
                                            </div>
                                        </div>
                                        {comic.publication_year && (
                                            <div className="flex items-center space-x-3">
                                                <Calendar className="w-5 h-5 text-red-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">Year</span>
                                                    <p className="text-white font-semibold">{comic.publication_year}</p>
                                                </div>
                                            </div>
                                        )}
                                        {comic.publisher && (
                                            <div className="flex items-center space-x-3">
                                                <BookOpen className="w-5 h-5 text-red-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">Publisher</span>
                                                    <p className="text-white font-semibold">{comic.publisher}</p>
                                                </div>
                                            </div>
                                        )}
                                        {comic.isbn && (
                                            <div className="flex items-center space-x-3">
                                                <BookOpen className="w-5 h-5 text-red-400" />
                                                <div>
                                                    <span className="text-gray-400 text-sm">ISBN</span>
                                                    <p className="text-white font-semibold">{comic.isbn}</p>
                                                </div>
                                            </div>
                                        )}
                                        <div className="flex items-center space-x-3">
                                            <Globe className="w-5 h-5 text-red-400" />
                                            <div>
                                                <span className="text-gray-400 text-sm">Language</span>
                                                <p className="text-white font-semibold">{comic.language.toUpperCase()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-3">
                                            <Eye className="w-5 h-5 text-red-400" />
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

            {/* Reviews and Rating Section */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="bg-gray-900/50 backdrop-blur rounded-2xl p-8">
                    <div className="flex items-center justify-between mb-8">
                        <h2 className="text-3xl font-bold bg-gradient-to-r from-red-400 to-red-600 bg-clip-text text-transparent">
                            Ratings & Reviews
                        </h2>
                        {auth.user && isInLibrary && (
                            <button 
                                className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105"
                                onClick={() => setShowReviewModal(true)}
                            >
                                {userReview ? 'Edit Your Review' : 'Write a Review'}
                            </button>
                        )}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                        {/* Rating Overview */}
                        <div className="lg:col-span-1">
                            <div className="text-center p-6 border border-gray-800 rounded-xl">
                                <div className="flex items-center justify-center space-x-2 mb-4">
                                    <Star className="w-8 h-8 text-yellow-400 fill-current" />
                                    <span className="text-4xl font-bold text-white">
                                        {Number(comic.average_rating || 0).toFixed(1)}
                                    </span>
                                </div>
                                <p className="text-gray-400 mb-2">
                                    Based on {comic.total_ratings || 0} review{(comic.total_ratings || 0) !== 1 ? 's' : ''}
                                </p>
                                
                                {/* Rating Distribution */}
                                <div className="space-y-2 mt-6">
                                    {[5, 4, 3, 2, 1].map(rating => {
                                        const count = reviewStats?.[`${rating}_stars`] || 0;
                                        const total = comic.total_ratings || 1;
                                        const percentage = (count / total) * 100;
                                        
                                        return (
                                            <div key={rating} className="flex items-center space-x-2">
                                                <span className="text-sm text-gray-400 w-3">{rating}</span>
                                                <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                                <div className="flex-1 bg-gray-700 rounded-full h-2">
                                                    <div 
                                                        className="bg-yellow-400 rounded-full h-2 transition-all duration-300"
                                                        style={{ width: `${percentage}%` }}
                                                    />
                                                </div>
                                                <span className="text-sm text-gray-400 w-8">{count}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        {/* Reviews List */}
                        <div className="lg:col-span-2 space-y-6">
                            {reviews.length > 0 ? (
                                reviews.slice(0, 3).map((review) => (
                                    <div key={review.id} className="border border-gray-800 rounded-xl p-6">
                                        <div className="flex items-start justify-between mb-4">
                                            <div>
                                                <div className="flex items-center space-x-2 mb-2">
                                                    <div className="flex space-x-1">
                                                        {[...Array(5)].map((_, i) => (
                                                            <Star
                                                                key={i}
                                                                className={`w-4 h-4 ${
                                                                    i < review.rating
                                                                        ? 'text-yellow-400 fill-current'
                                                                        : 'text-gray-600'
                                                                }`}
                                                            />
                                                        ))}
                                                    </div>
                                                    <span className="text-sm text-gray-400">
                                                        {new Date(review.created_at).toLocaleDateString()}
                                                    </span>
                                                </div>
                                                <h4 className="text-white font-semibold mb-1">
                                                    {review.title || `Review by ${review.user.name}`}
                                                </h4>
                                                <p className="text-gray-400 text-sm">by {review.user.name}</p>
                                            </div>
                                        </div>
                                        <p className="text-gray-300 leading-relaxed">
                                            {review.content}
                                        </p>
                                        {review.is_spoiler && (
                                            <div className="mt-3 px-3 py-1 bg-yellow-900/30 border border-yellow-500/30 rounded-lg inline-block">
                                                <span className="text-yellow-400 text-sm">⚠️ Contains Spoilers</span>
                                            </div>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-12 text-gray-400">
                                    <Star className="w-12 h-12 mx-auto mb-4 text-gray-600" />
                                    <p className="text-lg">No reviews yet</p>
                                    <p className="text-sm">Be the first to share your thoughts!</p>
                                </div>
                            )}
                            
                            {reviews.length > 3 && (
                                <div className="text-center">
                                    <button className="px-6 py-3 border border-red-500/50 text-red-400 rounded-lg hover:bg-red-500/10 transition-colors">
                                        View All {reviews.length} Reviews
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Payment Modal */}
            <PaymentModal
                comic={comic}
                isOpen={showPaymentModal}
                onClose={() => setShowPaymentModal(false)}
                onSuccess={handlePaymentSuccess}
            />

            {/* Review Modal */}
            <ReviewModal
                comic={comic}
                isOpen={showReviewModal}
                onClose={() => setShowReviewModal(false)}
                existingReview={userReview}
                onReviewSubmitted={fetchReviews}
            />

            {/* Enhanced PDF Reader */}
            {showPdfViewer && comic.is_pdf_comic && (comic.pdf_stream_url || comic.pdf_file_path) && (
                <EnhancedPdfReader
                    fileUrl={comic.pdf_stream_url || `/comics/${comic.slug}/stream`}
                    fileName={comic.pdf_file_name || `${comic.title}.pdf`}
                    downloadUrl={comic.user_has_access ? (comic.pdf_download_url || `/comics/${comic.slug}/download`) : undefined}
                    userHasDownloadAccess={comic.user_has_access}
                    comicSlug={comic.slug}
                    initialPage={comic.user_progress?.current_page || 1}
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
                    onClose={() => setShowPdfViewer(false)}
                />
            )}
        </>
    );
}
