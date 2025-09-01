import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { Star, BookOpen, Play, Bookmark, Heart, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';

export interface Comic {
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
    user_has_access?: boolean;
    user_progress?: {
        current_page: number;
        total_pages?: number;
        progress_percentage: number;
        is_completed: boolean;
        is_bookmarked: boolean;
        last_read_at?: string;
    };
}

interface ComicGridProps {
    comics: Comic[];
    loading: boolean;
    hasMore: boolean;
    onLoadMore: () => void;
    viewMode?: 'grid' | 'list';
    onComicAction?: (comic: Comic, action: 'bookmark' | 'favorite') => void;
}

const ComicCard: React.FC<{ 
    comic: Comic; 
    viewMode: 'grid' | 'list';
    onAction?: (comic: Comic, action: 'bookmark' | 'favorite') => void;
}> = ({ comic, viewMode, onAction }) => {
    const formatPrice = (price?: number | string) => {
        const numPrice = Number(price || 0);
        return numPrice > 0 ? `$${numPrice.toFixed(2)}` : 'Free';
    };

    const formatRating = (rating: number | string) => {
        const numRating = Number(rating || 0);
        return numRating > 0 ? numRating.toFixed(1) : 'No ratings';
    };

    const formatReadingTime = (minutes: number) => {
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    };

    if (viewMode === 'list') {
        return (
            <div className="flex bg-gray-900/70 rounded-xl overflow-hidden border border-gray-800/50 hover:border-red-500/50 transition-all duration-300 cursor-pointer hover:shadow-lg hover:shadow-red-500/20 group">
                <Link href={`/comics/${comic.slug}`} className="flex w-full">
                    <div className="relative w-16 h-24 sm:w-20 sm:h-30 md:w-24 md:h-36 flex-shrink-0">
                        {comic.cover_image_url ? (
                            <img
                                src={comic.cover_image_url}
                                alt={comic.title}
                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                                onError={(e) => {
                                    const target = e.target as HTMLImageElement;
                                    target.onerror = null;
                                    target.src = '/images/default-comic-cover.svg';
                                }}
                            />
                        ) : (
                            <div className="w-full h-full bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                                <BookOpen className="h-8 w-8 text-gray-500" />
                            </div>
                        )}
                        
                        {/* Progress indicator */}
                        {comic.user_progress && comic.user_progress.progress_percentage > 0 && (
                            <div className="absolute bottom-0 left-0 right-0 h-1 bg-gray-600">
                                <div 
                                    className="h-full bg-red-500 transition-all duration-300"
                                    style={{ width: `${comic.user_progress.progress_percentage}%` }}
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex-1 p-2 sm:p-3 md:p-4 flex flex-col justify-between">
                        <div>
                            <div className="flex items-start justify-between mb-2">
                                <div className="flex-1 min-w-0">
                                    <h3 className="font-bold text-lg mb-1 truncate group-hover:text-red-400 transition-colors">
                                        {comic.title}
                                    </h3>
                                    <p className="text-gray-400 text-sm truncate">{comic.author || 'Unknown Author'}</p>
                                </div>
                                
                                <div className="flex items-center space-x-1 sm:space-x-2 ml-2 sm:ml-4 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="p-1 sm:p-2 h-6 w-6 sm:h-8 sm:w-8"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            onAction?.(comic, 'bookmark');
                                        }}
                                    >
                                        <Bookmark className="w-3 h-3 sm:w-4 sm:h-4" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="p-1 sm:p-2 h-6 w-6 sm:h-8 sm:w-8"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            onAction?.(comic, 'favorite');
                                        }}
                                    >
                                        <Heart className="w-3 h-3 sm:w-4 sm:h-4" />
                                    </Button>
                                </div>
                            </div>
                            
                            <p className="text-gray-300 text-sm mb-3 line-clamp-2">
                                {comic.description || 'No description available'}
                            </p>
                        </div>

                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="flex items-center space-x-1">
                                    <Star className="w-4 h-4 text-yellow-400 fill-current" />
                                    <span className="text-sm text-gray-300">{formatRating(comic.average_rating)}</span>
                                </div>
                                <span className="text-gray-500 text-sm">{comic.page_count || 0} pages</span>
                                <div className="flex items-center space-x-1 text-gray-500 text-sm">
                                    <Clock className="w-3 h-3" />
                                    <span>{formatReadingTime(comic.reading_time_estimate)}</span>
                                </div>
                                {comic.genre && (
                                    <Badge variant="secondary" className="text-xs">
                                        {comic.genre}
                                    </Badge>
                                )}
                            </div>
                            
                            <div className="flex items-center space-x-2">
                                {comic.is_new_release && (
                                    <Badge className="bg-red-600 hover:bg-red-700">NEW</Badge>
                                )}
                                {comic.is_free && (
                                    <Badge className="bg-red-500 hover:bg-red-600">FREE</Badge>
                                )}
                                <div className="text-red-400 font-semibold">
                                    {formatPrice(comic.price)}
                                </div>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
        );
    }

    // Grid view
    return (
        <div className="group cursor-pointer bg-gray-900/70 rounded-xl overflow-hidden border border-gray-800/50 hover:border-red-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-red-500/20">
            <Link href={`/comics/${comic.slug}`} className="block">
                <div className="relative">
                    {comic.cover_image_url ? (
                        <img
                            src={comic.cover_image_url}
                            alt={comic.title}
                            className="w-full h-48 sm:h-56 md:h-64 object-cover group-hover:scale-110 transition-transform duration-500"
                            loading="lazy"
                            onError={(e) => {
                                const target = e.target as HTMLImageElement;
                                target.onerror = null;
                                target.src = '/images/default-comic-cover.svg';
                            }}
                        />
                    ) : (
                        <div className="w-full h-48 sm:h-56 md:h-64 bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                            <BookOpen className="h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 text-gray-500" />
                        </div>
                    )}
                    
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

                    {/* Badges */}
                    <div className="absolute top-2 left-2 sm:top-3 sm:left-3 flex flex-col gap-1">
                        {comic.is_free && (
                            <Badge className="bg-red-500 hover:bg-red-600">FREE</Badge>
                        )}
                        {comic.is_new_release && (
                            <Badge className="bg-red-600 hover:bg-red-700">NEW</Badge>
                        )}
                        {comic.has_mature_content && (
                            <Badge variant="destructive">18+</Badge>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="absolute top-2 right-2 sm:top-3 sm:right-3 flex flex-col gap-1 sm:gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                        <Button
                            size="sm"
                            className="p-1 sm:p-2 h-6 w-6 sm:h-8 sm:w-8 bg-red-500 hover:bg-red-600"
                            onClick={(e) => {
                                e.preventDefault();
                                window.location.href = `/comics/${comic.slug}`;
                            }}
                        >
                            <Play className="w-3 h-3 sm:w-4 sm:h-4" />
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            className="p-1 sm:p-2 h-6 w-6 sm:h-8 sm:w-8"
                            onClick={(e) => {
                                e.preventDefault();
                                onAction?.(comic, 'bookmark');
                            }}
                        >
                            <Bookmark className="w-3 h-3 sm:w-4 sm:h-4" />
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            className="p-1 sm:p-2 h-6 w-6 sm:h-8 sm:w-8"
                            onClick={(e) => {
                                e.preventDefault();
                                onAction?.(comic, 'favorite');
                            }}
                        >
                            <Heart className="w-3 h-3 sm:w-4 sm:h-4" />
                        </Button>
                    </div>

                    {/* Progress indicator */}
                    {comic.user_progress && comic.user_progress.progress_percentage > 0 && (
                        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gray-600">
                            <div 
                                className="h-full bg-red-500 transition-all duration-300"
                                style={{ width: `${comic.user_progress.progress_percentage}%` }}
                            />
                        </div>
                    )}
                </div>

                <div className="p-2 sm:p-3 md:p-4">
                    <h3 className="font-bold text-lg mb-1 line-clamp-1 group-hover:text-red-400 transition-colors">
                        {comic.title}
                    </h3>
                    <p className="text-gray-400 text-sm mb-2 truncate">{comic.author || 'Unknown Author'}</p>
                    <p className="text-gray-300 text-sm mb-3 line-clamp-2">
                        {comic.description || 'No description available'}
                    </p>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-1">
                            <Star className="w-4 h-4 text-yellow-400 fill-current" />
                            <span className="text-sm text-gray-300">{formatRating(comic.average_rating)}</span>
                            <span className="text-gray-500 text-sm">
                                ({(comic as any).total_ratings || 0} rating{((comic as any).total_ratings || 0) !== 1 ? 's' : ''})
                            </span>
                        </div>
                        <div className="text-red-400 font-semibold">
                            {formatPrice(comic.price)}
                        </div>
                    </div>
                    
                    <div className="flex items-center justify-between mt-2">
                        <div className="flex items-center space-x-1 text-gray-500 text-sm">
                            <Clock className="w-3 h-3" />
                            <span>{formatReadingTime(comic.reading_time_estimate)}</span>
                        </div>
                        {comic.genre && (
                            <Badge variant="secondary" className="text-xs">
                                {comic.genre}
                            </Badge>
                        )}
                    </div>
                </div>
            </Link>
        </div>
    );
};

const ComicGridSkeleton: React.FC<{ viewMode: 'grid' | 'list' }> = ({ viewMode }) => {
    if (viewMode === 'list') {
        return (
            <div className="flex bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50 p-4">
                <Skeleton className="w-24 h-36 flex-shrink-0" />
                <div className="flex-1 ml-4 space-y-2">
                    <Skeleton className="h-6 w-3/4" />
                    <Skeleton className="h-4 w-1/2" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-2/3" />
                    <div className="flex justify-between items-center mt-4">
                        <Skeleton className="h-4 w-20" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50">
            <Skeleton className="w-full h-64" />
            <div className="p-4 space-y-2">
                <Skeleton className="h-6 w-3/4" />
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="h-4 w-full" />
                <div className="flex justify-between items-center">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-4 w-16" />
                </div>
            </div>
        </div>
    );
};

export const ComicGrid: React.FC<ComicGridProps> = ({
    comics,
    loading,
    hasMore,
    onLoadMore,
    viewMode = 'grid',
    onComicAction
}) => {
    const observerRef = useRef<IntersectionObserver | null>(null);
    const loadMoreRef = useRef<HTMLDivElement | null>(null);

    const handleObserver = useCallback((entries: IntersectionObserverEntry[]) => {
        const [target] = entries;
        if (target.isIntersecting && hasMore && !loading) {
            onLoadMore();
        }
    }, [hasMore, loading, onLoadMore]);

    useEffect(() => {
        const element = loadMoreRef.current;
        if (!element) return;

        if (observerRef.current) {
            observerRef.current.disconnect();
        }

        observerRef.current = new IntersectionObserver(handleObserver, {
            threshold: 0.1,
            rootMargin: '100px'
        });

        observerRef.current.observe(element);

        return () => {
            if (observerRef.current) {
                observerRef.current.disconnect();
            }
        };
    }, [handleObserver]);

    if (comics.length === 0 && !loading) {
        return (
            <div className="text-center py-12">
                <BookOpen className="h-16 w-16 text-gray-500 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-300 mb-2">No comics found</h3>
                <p className="text-gray-500">Try adjusting your search or filter criteria.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className={
                viewMode === 'grid' 
                    ? "grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6"
                    : "space-y-3 sm:space-y-4"
            }>
                {comics.map((comic) => (
                    <ComicCard
                        key={comic.id}
                        comic={comic}
                        viewMode={viewMode}
                        onAction={onComicAction}
                    />
                ))}
            </div>

            {/* Loading skeletons */}
            {loading && (
                <div className={
                    viewMode === 'grid' 
                        ? "grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6"
                        : "space-y-3 sm:space-y-4"
                }>
                    {Array.from({ length: 8 }).map((_, index) => (
                        <ComicGridSkeleton key={index} viewMode={viewMode} />
                    ))}
                </div>
            )}

            {/* Infinite scroll trigger */}
            <div ref={loadMoreRef} className="h-10" />

            {/* Load more button fallback */}
            {hasMore && !loading && (
                <div className="text-center">
                    <Button onClick={onLoadMore} variant="outline">
                        Load More Comics
                    </Button>
                </div>
            )}
        </div>
    );
};

export default ComicGrid;