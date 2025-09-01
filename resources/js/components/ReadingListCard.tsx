import React, { useState } from 'react';
import { Heart, Users, BookOpen, Eye, Share2, Plus, MoreHorizontal, Lock } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Comic {
    id: number;
    title: string;
    cover_image_url: string;
}

interface ReadingList {
    id: number;
    name: string;
    slug: string;
    description?: string;
    is_public: boolean;
    is_featured: boolean;
    cover_image_url?: string;
    tags: string[];
    comics_count: number;
    followers_count: number;
    likes_count: number;
    user?: {
        id: number;
        name: string;
    };
    preview_comics?: Comic[];
    is_owner?: boolean;
    is_following?: boolean;
    is_liked?: boolean;
    created_at: string;
}

interface ReadingListCardProps {
    list: ReadingList;
    onFollow?: (listId: number) => void;
    onUnfollow?: (listId: number) => void;
    onLike?: (listId: number) => void;
    onUnlike?: (listId: number) => void;
    onDuplicate?: (listId: number) => void;
    showActions?: boolean;
    variant?: 'default' | 'compact' | 'featured';
}

export default function ReadingListCard({
    list,
    onFollow,
    onUnfollow,
    onLike,
    onUnlike,
    onDuplicate,
    showActions = true,
    variant = 'default'
}: ReadingListCardProps) {
    const [isLiked, setIsLiked] = useState(list.is_liked || false);
    const [isFollowing, setIsFollowing] = useState(list.is_following || false);
    const [likesCount, setLikesCount] = useState(list.likes_count);
    const [followersCount, setFollowersCount] = useState(list.followers_count);
    const [showMenu, setShowMenu] = useState(false);

    const handleLike = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (isLiked) {
            setIsLiked(false);
            setLikesCount(prev => Math.max(0, prev - 1));
            onUnlike?.(list.id);
        } else {
            setIsLiked(true);
            setLikesCount(prev => prev + 1);
            onLike?.(list.id);
        }
    };

    const handleFollow = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (isFollowing) {
            setIsFollowing(false);
            setFollowersCount(prev => Math.max(0, prev - 1));
            onUnfollow?.(list.id);
        } else {
            setIsFollowing(true);
            setFollowersCount(prev => prev + 1);
            onFollow?.(list.id);
        }
    };

    const handleShare = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (navigator.share) {
            await navigator.share({
                title: list.name,
                text: list.description,
                url: `/lists/${list.slug}`
            });
        } else {
            await navigator.clipboard.writeText(`${window.location.origin}/lists/${list.slug}`);
            // Could show a toast notification here
        }
    };

    const handleDuplicate = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        onDuplicate?.(list.id);
        setShowMenu(false);
    };

    if (variant === 'compact') {
        return (
            <Link
                href={`/lists/${list.slug}`}
                className="group flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg hover:bg-gray-800/70 transition-colors"
            >
                <div className="flex-shrink-0">
                    {list.preview_comics && list.preview_comics.length > 0 ? (
                        <img
                            src={list.preview_comics[0].cover_image_url}
                            alt={list.name}
                            className="w-12 h-16 object-cover rounded"
                        />
                    ) : (
                        <div className="w-12 h-16 bg-gray-700 rounded flex items-center justify-center">
                            <BookOpen className="w-6 h-6 text-gray-400" />
                        </div>
                    )}
                </div>
                
                <div className="flex-1 min-w-0">
                    <div className="flex items-center space-x-2">
                        <h3 className="font-medium text-white truncate group-hover:text-red-400 transition-colors">
                            {list.name}
                        </h3>
                        {!list.is_public && <Lock className="w-3 h-3 text-gray-400" />}
                        {list.is_featured && (
                            <span className="px-2 py-0.5 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">
                                Featured
                            </span>
                        )}
                    </div>
                    
                    <div className="flex items-center space-x-4 text-xs text-gray-400 mt-1">
                        <span>{list.comics_count} comics</span>
                        <span>{followersCount} followers</span>
                        {list.user && <span>by {list.user.name}</span>}
                    </div>
                </div>
            </Link>
        );
    }

    if (variant === 'featured') {
        return (
            <Link
                href={`/lists/${list.slug}`}
                className="group relative bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl overflow-hidden border border-gray-700 hover:border-yellow-500/50 transition-all duration-300"
            >
                {/* Featured Badge */}
                <div className="absolute top-4 left-4 z-10">
                    <span className="px-3 py-1 bg-gradient-to-r from-yellow-400 to-yellow-600 text-black text-sm font-bold rounded-full">
                        P Featured
                    </span>
                </div>

                {/* Preview Comics Grid */}
                <div className="relative aspect-[16/10] overflow-hidden">
                    {list.preview_comics && list.preview_comics.length > 0 ? (
                        <div className="grid grid-cols-4 gap-1 p-4 h-full">
                            {list.preview_comics.slice(0, 4).map((comic, index) => (
                                <div key={comic.id} className="relative">
                                    <img
                                        src={comic.cover_image_url}
                                        alt={comic.title}
                                        className="w-full h-full object-cover rounded group-hover:scale-105 transition-transform duration-500"
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex items-center justify-center h-full bg-gray-700">
                            <BookOpen className="w-16 h-16 text-gray-400" />
                        </div>
                    )}
                    
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                </div>

                {/* Content */}
                <div className="p-6">
                    <div className="flex items-start justify-between mb-3">
                        <h3 className="text-xl font-bold text-white group-hover:text-yellow-400 transition-colors line-clamp-2">
                            {list.name}
                        </h3>
                    </div>
                    
                    <p className="text-gray-300 text-sm mb-4 line-clamp-2">
                        {list.description || 'No description available'}
                    </p>
                    
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4 text-sm text-gray-400">
                            <div className="flex items-center space-x-1">
                                <BookOpen className="w-4 h-4" />
                                <span>{list.comics_count}</span>
                            </div>
                            <div className="flex items-center space-x-1">
                                <Users className="w-4 h-4" />
                                <span>{followersCount}</span>
                            </div>
                            <div className="flex items-center space-x-1">
                                <Heart className={`w-4 h-4 ${isLiked ? 'fill-current text-red-400' : ''}`} />
                                <span>{likesCount}</span>
                            </div>
                        </div>
                        
                        {list.user && (
                            <span className="text-sm text-gray-400">
                                by {list.user.name}
                            </span>
                        )}
                    </div>
                </div>
            </Link>
        );
    }

    // Default variant
    return (
        <div className="group bg-gray-800/50 rounded-xl border border-gray-700/50 hover:border-red-500/50 transition-all duration-300 overflow-hidden">
            {/* Header */}
            <div className="p-4 border-b border-gray-700/50">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="flex items-center space-x-2 mb-1">
                            <Link
                                href={`/lists/${list.slug}`}
                                className="font-semibold text-white hover:text-red-400 transition-colors line-clamp-1"
                            >
                                {list.name}
                            </Link>
                            {!list.is_public && <Lock className="w-4 h-4 text-gray-400" />}
                            {list.is_featured && (
                                <span className="px-2 py-0.5 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">
                                    Featured
                                </span>
                            )}
                        </div>
                        
                        {list.user && (
                            <Link
                                href={`/users/${list.user.id}`}
                                className="text-sm text-gray-400 hover:text-gray-300 transition-colors"
                            >
                                by {list.user.name}
                            </Link>
                        )}
                        
                        {list.description && (
                            <p className="text-sm text-gray-300 mt-2 line-clamp-2">
                                {list.description}
                            </p>
                        )}
                    </div>
                    
                    {showActions && (
                        <div className="relative">
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    setShowMenu(!showMenu);
                                }}
                                className="p-1 text-gray-400 hover:text-white transition-colors"
                            >
                                <MoreHorizontal className="w-4 h-4" />
                            </button>
                            
                            {showMenu && (
                                <div className="absolute right-0 top-full mt-1 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-20">
                                    <div className="py-1">
                                        <button
                                            onClick={handleShare}
                                            className="flex items-center space-x-2 w-full px-4 py-2 text-sm text-gray-300 hover:bg-gray-700"
                                        >
                                            <Share2 className="w-4 h-4" />
                                            <span>Share</span>
                                        </button>
                                        {!list.is_owner && onDuplicate && (
                                            <button
                                                onClick={handleDuplicate}
                                                className="flex items-center space-x-2 w-full px-4 py-2 text-sm text-gray-300 hover:bg-gray-700"
                                            >
                                                <Plus className="w-4 h-4" />
                                                <span>Duplicate</span>
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
                
                {/* Tags */}
                {list.tags && list.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-2">
                        {list.tags.slice(0, 3).map((tag) => (
                            <span
                                key={tag}
                                className="px-2 py-1 bg-gray-700/50 text-gray-300 text-xs rounded-full"
                            >
                                {tag}
                            </span>
                        ))}
                        {list.tags.length > 3 && (
                            <span className="px-2 py-1 bg-gray-700/50 text-gray-400 text-xs rounded-full">
                                +{list.tags.length - 3}
                            </span>
                        )}
                    </div>
                )}
            </div>

            {/* Preview Comics */}
            <Link href={`/lists/${list.slug}`} className="block">
                <div className="p-4">
                    {list.preview_comics && list.preview_comics.length > 0 ? (
                        <div className="grid grid-cols-4 gap-2">
                            {list.preview_comics.slice(0, 4).map((comic) => (
                                <div key={comic.id} className="relative aspect-[2/3] overflow-hidden rounded">
                                    <img
                                        src={comic.cover_image_url}
                                        alt={comic.title}
                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        loading="lazy"
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="aspect-[4/3] bg-gray-700/50 rounded flex items-center justify-center">
                            <div className="text-center">
                                <BookOpen className="w-8 h-8 text-gray-400 mx-auto mb-2" />
                                <p className="text-sm text-gray-400">Empty list</p>
                            </div>
                        </div>
                    )}
                </div>
            </Link>

            {/* Footer */}
            <div className="px-4 pb-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4 text-sm text-gray-400">
                        <div className="flex items-center space-x-1">
                            <BookOpen className="w-4 h-4" />
                            <span>{list.comics_count} comics</span>
                        </div>
                        <div className="flex items-center space-x-1">
                            <Users className="w-4 h-4" />
                            <span>{followersCount}</span>
                        </div>
                    </div>
                    
                    {showActions && (
                        <div className="flex items-center space-x-2">
                            {!list.is_owner && onFollow && (
                                <button
                                    onClick={handleFollow}
                                    className={`p-1.5 rounded-lg transition-colors ${
                                        isFollowing
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-gray-700 text-gray-400 hover:bg-gray-600 hover:text-white'
                                    }`}
                                    title={isFollowing ? 'Unfollow' : 'Follow'}
                                >
                                    <Eye className="w-4 h-4" />
                                </button>
                            )}
                            
                            <button
                                onClick={handleLike}
                                className={`p-1.5 rounded-lg transition-colors ${
                                    isLiked
                                        ? 'bg-red-500 text-white'
                                        : 'bg-gray-700 text-gray-400 hover:bg-gray-600 hover:text-white'
                                }`}
                                title={isLiked ? 'Unlike' : 'Like'}
                            >
                                <Heart className={`w-4 h-4 ${isLiked ? 'fill-current' : ''}`} />
                            </button>
                            
                            <span className="text-xs text-gray-400">{likesCount}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}