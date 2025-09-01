import React, { useState, useEffect } from 'react';
import { Star, ChevronRight, RefreshCw, ThumbsUp, ThumbsDown, Sparkles, TrendingUp, Clock, BookOpen } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
    genre?: string;
    description?: string;
    cover_image_url?: string;
    average_rating: number;
    total_ratings: number;
    page_count?: number;
    is_free: boolean;
    price?: number;
    reading_time_estimate?: number;
    published_at: string;
    tags?: string[];
}

interface Recommendation {
    comic: Comic;
    recommendation_score: number;
    recommendation_type: string;
    reasons: string[];
    confidence: string;
}

interface RecommendationsSectionProps {
    title?: string;
    subtitle?: string;
    limit?: number;
    type?: 'all' | 'collaborative' | 'content' | 'trending' | 'new_releases';
    showRefresh?: boolean;
    showReasons?: boolean;
    className?: string;
}

export default function RecommendationsSection({
    title = "Recommended for You",
    subtitle = "Discover your next great read",
    limit = 12,
    type = 'all',
    showRefresh = true,
    showReasons = true,
    className = ""
}: RecommendationsSectionProps) {
    const [recommendations, setRecommendations] = useState<Recommendation[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchRecommendations();
    }, [type, limit]);

    const fetchRecommendations = async (refresh = false) => {
        try {
            if (refresh) setRefreshing(true);
            else setLoading(true);
            
            const response = await fetch(`/api/recommendations?limit=${limit}&type=${type}&refresh=${refresh}`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                setRecommendations(data.data.recommendations || []);
                setError(null);
            } else {
                throw new Error('Failed to fetch recommendations');
            }
        } catch (err) {
            console.error('Error fetching recommendations:', err);
            setError('Failed to load recommendations');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const trackInteraction = async (comicId: number, action: string, recommendationType: string) => {
        try {
            await fetch('/api/recommendations/track', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    comic_id: comicId,
                    action: action,
                    recommendation_type: recommendationType
                })
            });
        } catch (error) {
            console.error('Error tracking interaction:', error);
        }
    };

    const handleComicClick = (recommendation: Recommendation) => {
        trackInteraction(recommendation.comic.id, 'clicked', recommendation.recommendation_type);
    };

    const handleDismiss = (recommendation: Recommendation) => {
        trackInteraction(recommendation.comic.id, 'dismissed', recommendation.recommendation_type);
        setRecommendations(prev => prev.filter(r => r.comic.id !== recommendation.comic.id));
    };

    const getTypeIcon = (recType: string) => {
        switch (recType) {
            case 'collaborative_filtering':
                return <ThumbsUp className="w-4 h-4 text-blue-400" />;
            case 'trending':
                return <TrendingUp className="w-4 h-4 text-green-400" />;
            case 'new_release':
                return <Clock className="w-4 h-4 text-purple-400" />;
            case 'content_based':
                return <BookOpen className="w-4 h-4 text-orange-400" />;
            default:
                return <Sparkles className="w-4 h-4 text-yellow-400" />;
        }
    };

    const getConfidenceColor = (confidence: string) => {
        switch (confidence) {
            case 'very_high': return 'text-green-400 border-green-400';
            case 'high': return 'text-blue-400 border-blue-400';
            case 'medium': return 'text-yellow-400 border-yellow-400';
            default: return 'text-gray-400 border-gray-400';
        }
    };

    const formatPrice = (price?: number, isFree?: boolean) => {
        if (isFree) return 'FREE';
        return price ? `$${price.toFixed(2)}` : 'FREE';
    };

    if (loading) {
        return (
            <div className={`space-y-6 ${className}`}>
                <div className="flex items-center justify-between">
                    <div>
                        <div className="h-8 bg-gray-700 rounded w-48 animate-pulse"></div>
                        <div className="h-4 bg-gray-800 rounded w-32 mt-2 animate-pulse"></div>
                    </div>
                </div>
                
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {Array.from({ length: 8 }).map((_, i) => (
                        <div key={i} className="bg-gray-800 rounded-xl overflow-hidden animate-pulse">
                            <div className="aspect-[2/3] bg-gray-700"></div>
                            <div className="p-4 space-y-3">
                                <div className="h-4 bg-gray-700 rounded"></div>
                                <div className="h-3 bg-gray-800 rounded w-3/4"></div>
                                <div className="h-3 bg-gray-800 rounded w-1/2"></div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (error || recommendations.length === 0) {
        return (
            <div className={`text-center py-12 ${className}`}>
                <Sparkles className="w-12 h-12 mx-auto text-gray-400 mb-4" />
                <h3 className="text-lg font-semibold text-white mb-2">
                    {error ? 'Failed to Load' : 'No Recommendations Yet'}
                </h3>
                <p className="text-gray-400 mb-6">
                    {error || 'Start reading comics to get personalized recommendations!'}
                </p>
                {error && (
                    <button
                        onClick={() => fetchRecommendations()}
                        className="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors"
                    >
                        Try Again
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold bg-gradient-to-r from-red-400 to-red-600 bg-clip-text text-transparent">
                        {title}
                    </h2>
                    <p className="text-gray-400 mt-1">{subtitle}</p>
                </div>
                
                {showRefresh && (
                    <div className="flex items-center space-x-2">
                        <button
                            onClick={() => fetchRecommendations(true)}
                            disabled={refreshing}
                            className="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white transition-all duration-200 disabled:opacity-50"
                            title="Refresh recommendations"
                        >
                            <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
                        </button>
                    </div>
                )}
            </div>

            {/* Recommendations Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                {recommendations.map((recommendation) => (
                    <div
                        key={recommendation.comic.id}
                        className="group bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 hover:transform hover:scale-[1.02]"
                    >
                        <Link
                            href={`/comics/${recommendation.comic.slug}`}
                            onClick={() => handleComicClick(recommendation)}
                            className="block"
                        >
                            {/* Cover Image */}
                            <div className="relative aspect-[2/3] overflow-hidden">
                                <img
                                    src={recommendation.comic.cover_image_url || '/images/default-comic-cover.svg'}
                                    alt={recommendation.comic.title}
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy"
                                    onError={(e) => {
                                        const target = e.target as HTMLImageElement;
                                        target.onerror = null;
                                        target.src = '/images/default-comic-cover.svg';
                                    }}
                                />
                                
                                {/* Confidence Badge */}
                                <div className={`absolute top-2 right-2 px-2 py-1 rounded-full text-xs border backdrop-blur-sm ${getConfidenceColor(recommendation.confidence)}`}>
                                    {Math.round(recommendation.recommendation_score * 100)}% match
                                </div>
                                
                                {/* Type Badge */}
                                <div className="absolute top-2 left-2 p-1.5 rounded-full bg-black/60 backdrop-blur-sm">
                                    {getTypeIcon(recommendation.recommendation_type)}
                                </div>
                                
                                {/* Price Badge */}
                                <div className="absolute bottom-2 right-2 px-2 py-1 bg-black/80 backdrop-blur-sm rounded text-xs font-medium text-white">
                                    {formatPrice(recommendation.comic.price, recommendation.comic.is_free)}
                                </div>
                            </div>
                        </Link>

                        {/* Content */}
                        <div className="p-4">
                            <Link
                                href={`/comics/${recommendation.comic.slug}`}
                                onClick={() => handleComicClick(recommendation)}
                                className="block"
                            >
                                <h3 className="font-bold text-white mb-1 line-clamp-1 group-hover:text-red-400 transition-colors">
                                    {recommendation.comic.title}
                                </h3>
                                
                                <p className="text-gray-400 text-sm mb-2 truncate">
                                    {recommendation.comic.author || 'Unknown Author'}
                                </p>

                                {/* Rating and Stats */}
                                <div className="flex items-center space-x-2 mb-3">
                                    <div className="flex items-center space-x-1">
                                        <Star className="w-3 h-3 text-yellow-400 fill-current" />
                                        <span className="text-xs text-gray-300">
                                            {recommendation.comic.average_rating.toFixed(1)}
                                        </span>
                                    </div>
                                    <span className="text-gray-500 text-xs">•</span>
                                    <span className="text-xs text-gray-400">
                                        {recommendation.comic.total_ratings} ratings
                                    </span>
                                    {recommendation.comic.reading_time_estimate && (
                                        <>
                                            <span className="text-gray-500 text-xs">•</span>
                                            <span className="text-xs text-gray-400">
                                                {Math.round(recommendation.comic.reading_time_estimate / 60)}m read
                                            </span>
                                        </>
                                    )}
                                </div>

                                {/* Reasons */}
                                {showReasons && recommendation.reasons.length > 0 && (
                                    <div className="space-y-1">
                                        {recommendation.reasons.slice(0, 2).map((reason, index) => (
                                            <div
                                                key={index}
                                                className="text-xs text-green-400 bg-green-400/10 px-2 py-1 rounded-full inline-block mr-1"
                                            >
                                                {reason}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Link>

                            {/* Actions */}
                            <div className="flex items-center justify-between mt-3 pt-3 border-t border-gray-700">
                                <div className="flex items-center space-x-1">
                                    <button
                                        onClick={() => handleDismiss(recommendation)}
                                        className="p-1 text-gray-500 hover:text-red-400 transition-colors"
                                        title="Not interested"
                                    >
                                        <ThumbsDown className="w-3 h-3" />
                                    </button>
                                </div>
                                
                                <Link
                                    href={`/comics/${recommendation.comic.slug}`}
                                    onClick={() => handleComicClick(recommendation)}
                                    className="flex items-center space-x-1 text-xs text-red-400 hover:text-red-300 transition-colors"
                                >
                                    <span>View</span>
                                    <ChevronRight className="w-3 h-3" />
                                </Link>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* View More Link */}
            {recommendations.length >= limit && (
                <div className="text-center">
                    <Link
                        href="/recommendations"
                        className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300"
                    >
                        <span>View All Recommendations</span>
                        <ChevronRight className="w-4 h-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}