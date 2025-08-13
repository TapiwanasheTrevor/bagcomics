import React, { useState } from 'react';
import { Star, Play, Bookmark, Share2, Download, ShoppingCart, Eye, Clock, Users, Facebook, Twitter, Instagram, Link, MessageCircle } from 'lucide-react';
import { getComicById } from '../data/mockComics';
import type { Page } from '../App';

// TypeScript declarations for global axios
declare global {
  interface Window {
    axios?: {
      post: (url: string, data: any) => Promise<any>;
      [key: string]: any;
    };
  }
}

interface ComicDetailProps {
  comicId: string | null;
  onNavigate: (page: Page, comicId?: string) => void;
}

const ComicDetail: React.FC<ComicDetailProps> = ({ comicId, onNavigate }) => {
  const [selectedEpisode, setSelectedEpisode] = useState(1);
  const [isBookmarked, setIsBookmarked] = useState(false);
  const [showPreview, setShowPreview] = useState(false);
  const [showShareMenu, setShowShareMenu] = useState(false);
  const [copySuccess, setCopySuccess] = useState(false);

  const comic = comicId ? getComicById(comicId) : null;

  // Helper function to generate slug from title (basic implementation)
  const generateSlug = (title: string): string => {
    return title
      .toLowerCase()
      .replace(/[^a-z0-9 -]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim();
  };

  // Share functionality
  const shareComic = async (platform: string) => {
    if (!comic) return;

    const comicSlug = generateSlug(comic.title);
    const shareUrl = `${window.location.origin}/comics/${comicSlug}`;
    const shareMessage = `Check out this amazing comic: "${comic.title}" by ${comic.creator}`;

    try {
      // Track the share via API
      if (window.axios) {
        await window.axios.post(`/api/social/comics/${comic.id}/share`, {
          platform: platform.toLowerCase(),
          share_type: 'discovery',
          metadata: {
            title: comic.title,
            url: shareUrl,
            message: shareMessage
          }
        });
      }
    } catch (error) {
      console.warn('Failed to track share:', error);
      // Continue with sharing even if tracking fails
    }

    // Generate platform-specific URLs
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedMessage = encodeURIComponent(shareMessage);
    
    let platformUrl = '';
    switch (platform.toLowerCase()) {
      case 'facebook':
        platformUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${encodedMessage}`;
        break;
      case 'twitter':
        platformUrl = `https://twitter.com/intent/tweet?text=${encodedMessage}&url=${encodedUrl}`;
        break;
      case 'whatsapp':
        platformUrl = `https://wa.me/?text=${encodedMessage} ${encodedUrl}`;
        break;
      case 'instagram':
        // Instagram doesn't support direct URL sharing
        platformUrl = 'https://www.instagram.com/';
        break;
      default:
        return;
    }

    // Open share window
    window.open(platformUrl, '_blank', 'width=600,height=400');
    setShowShareMenu(false);
  };

  const copyShareLink = async () => {
    if (!comic) return;

    const comicSlug = generateSlug(comic.title);
    const shareUrl = `${window.location.origin}/comics/${comicSlug}`;

    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopySuccess(true);
      
      // Track the copy action
      if (window.axios) {
        await window.axios.post(`/api/social/comics/${comic.id}/share`, {
          platform: 'copy_link',
          share_type: 'discovery',
          metadata: {
            title: comic.title,
            url: shareUrl,
            message: `Check out "${comic.title}"`
          }
        });
      }

      setTimeout(() => setCopySuccess(false), 2000);
    } catch (error) {
      console.error('Failed to copy link:', error);
    }
    setShowShareMenu(false);
  };

  const handleNativeShare = async () => {
    if (!comic || !navigator.share) return;

    const comicSlug = generateSlug(comic.title);
    const shareUrl = `${window.location.origin}/comics/${comicSlug}`;
    const shareMessage = `Check out this amazing comic: "${comic.title}" by ${comic.creator}`;

    try {
      await navigator.share({
        title: comic.title,
        text: shareMessage,
        url: shareUrl
      });

      // Track the native share
      if (window.axios) {
        await window.axios.post(`/api/social/comics/${comic.id}/share`, {
          platform: 'native',
          share_type: 'discovery',
          metadata: {
            title: comic.title,
            url: shareUrl,
            message: shareMessage
          }
        });
      }
    } catch (error: any) {
      if (error.name !== 'AbortError') {
        console.error('Failed to share:', error);
      }
    }
    setShowShareMenu(false);
  };

  if (!comic) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <p className="text-xl text-gray-400 mb-4">Comic not found</p>
          <button
            onClick={() => onNavigate('home')}
            className="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 rounded-lg transition-colors"
          >
            Go Home
          </button>
        </div>
      </div>
    );
  }

  // Mock episodes data
  const episodes = Array.from({ length: comic.episodeCount }, (_, i) => ({
    id: i + 1,
    title: `Episode ${i + 1}: ${i === 0 ? 'The Beginning' : i === comic.episodeCount - 1 ? 'The Finale' : `Chapter ${i + 1}`}`,
    duration: `${Math.floor(Math.random() * 15) + 5} min read`,
    isLocked: !comic.isFree && i > 2,
    releaseDate: new Date(2024, 0, i * 7).toLocaleDateString()
  }));

  return (
    <div className="min-h-screen py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Hero Section */}
        <div className="relative mb-12">
          <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent z-10 rounded-2xl" />
          <div className="relative h-96 rounded-2xl overflow-hidden">
            <img
              src={comic.coverImage}
              alt={comic.title}
              className="w-full h-full object-cover"
            />
          </div>
          
          <div className="absolute inset-0 z-20 flex items-end">
            <div className="p-8 max-w-2xl">
              <div className="flex items-center space-x-2 mb-4">
                {comic.genre.map((g) => (
                  <span key={g} className="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded-full text-sm font-medium">
                    {g}
                  </span>
                ))}
                {comic.isNew && (
                  <span className="bg-orange-500 text-white px-3 py-1 rounded-full text-sm font-semibold">NEW</span>
                )}
              </div>
              
              <h1 className="text-5xl font-bold mb-4 bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
                {comic.title}
              </h1>
              
              <p className="text-xl text-gray-300 mb-6">by {comic.creator}</p>
              
              <div className="flex items-center space-x-6 mb-6">
                <div className="flex items-center space-x-1">
                  <Star className="w-5 h-5 text-yellow-400 fill-current" />
                  <span className="text-lg font-semibold">{comic.rating}</span>
                  <span className="text-gray-400">rating</span>
                </div>
                <div className="flex items-center space-x-1">
                  <Clock className="w-5 h-5 text-purple-400" />
                  <span className="text-gray-300">{comic.episodeCount} episodes</span>
                </div>
                <div className="flex items-center space-x-1">
                  <Users className="w-5 h-5 text-blue-400" />
                  <span className="text-gray-300">12.5k readers</span>
                </div>
              </div>
              
              <div className="flex flex-col sm:flex-row gap-4">
                <button 
                  onClick={() => onNavigate('reader', comic.id)}
                  className="flex items-center justify-center space-x-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-emerald-500/25"
                >
                  <Play className="w-5 h-5" />
                  <span>Read Now</span>
                </button>
                
                {!comic.isFree && (
                  <button className="flex items-center justify-center space-x-2 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 hover:scale-105">
                    <ShoppingCart className="w-5 h-5" />
                    <span>Buy for ${comic.price}</span>
                  </button>
                )}
                
                <button 
                  onClick={() => setShowPreview(true)}
                  className="flex items-center justify-center space-x-2 border-2 border-gray-600 text-gray-300 hover:border-gray-500 hover:text-white px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300"
                >
                  <Eye className="w-5 h-5" />
                  <span>Preview</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
          
          {/* Main Content */}
          <div className="lg:col-span-2 space-y-8">
            
            {/* Description */}
            <section>
              <h2 className="text-2xl font-bold mb-4">About This Comic</h2>
              <p className="text-gray-300 text-lg leading-relaxed">{comic.description}</p>
            </section>

            {/* Episodes List */}
            <section>
              <h2 className="text-2xl font-bold mb-6">Episodes</h2>
              <div className="space-y-3">
                {episodes.map((episode) => (
                  <div 
                    key={episode.id}
                    className={`flex items-center justify-between p-4 rounded-lg border transition-all duration-300 cursor-pointer ${
                      selectedEpisode === episode.id
                        ? 'bg-emerald-500/10 border-emerald-500/50'
                        : 'bg-gray-800/50 border-gray-700/50 hover:border-gray-600/50 hover:bg-gray-800/80'
                    }`}
                    onClick={() => setSelectedEpisode(episode.id)}
                  >
                    <div className="flex items-center space-x-4">
                      <div className={`w-12 h-12 rounded-lg flex items-center justify-center font-bold ${
                        episode.isLocked 
                          ? 'bg-gray-700 text-gray-400' 
                          : 'bg-gradient-to-r from-emerald-500 to-purple-500 text-white'
                      }`}>
                        {episode.isLocked ? '🔒' : episode.id}
                      </div>
                      <div>
                        <h3 className={`font-semibold ${episode.isLocked ? 'text-gray-400' : 'text-white'}`}>
                          {episode.title}
                        </h3>
                        <div className="flex items-center space-x-4 text-sm text-gray-400">
                          <span>{episode.duration}</span>
                          <span>Released {episode.releaseDate}</span>
                        </div>
                      </div>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                      {episode.isLocked ? (
                        <span className="text-sm text-purple-400 font-medium">Unlock to read</span>
                      ) : (
                        <button 
                          onClick={(e) => {
                            e.stopPropagation();
                            onNavigate('reader', comic.id);
                          }}
                          className="p-2 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
                        >
                          <Play className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          </div>

          {/* Sidebar */}
          <div className="space-y-8">
            
            {/* Quick Actions */}
            <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
              <h3 className="text-xl font-bold mb-4">Quick Actions</h3>
              <div className="space-y-3">
                <button 
                  onClick={() => setIsBookmarked(!isBookmarked)}
                  className={`w-full flex items-center justify-center space-x-2 px-4 py-3 rounded-lg transition-all ${
                    isBookmarked 
                      ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' 
                      : 'bg-gray-700/50 hover:bg-gray-700 text-gray-300'
                  }`}
                >
                  <Bookmark className="w-5 h-5" />
                  <span>{isBookmarked ? 'Bookmarked' : 'Add to Library'}</span>
                </button>
                
                <div className="relative">
                  <button 
                    onClick={() => {
                      if (typeof navigator !== 'undefined' && 'share' in navigator) {
                        handleNativeShare();
                      } else {
                        setShowShareMenu(!showShareMenu);
                      }
                    }}
                    className="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-gray-700/50 hover:bg-gray-700 text-gray-300 rounded-lg transition-colors"
                  >
                    <Share2 className="w-5 h-5" />
                    <span>Share Comic</span>
                  </button>

                  {/* Share Menu */}
                  {showShareMenu && (
                    <>
                      {/* Backdrop */}
                      <div 
                        className="fixed inset-0 z-40"
                        onClick={() => setShowShareMenu(false)}
                      />
                      
                      {/* Share Menu */}
                      <div className="absolute top-full mt-2 right-0 z-50 w-64 bg-gray-800 rounded-xl border border-gray-700 shadow-xl">
                        <div className="p-4">
                          <h4 className="font-medium mb-3 text-white">Share this comic</h4>
                          
                          <div className="space-y-2">
                            <button
                              onClick={() => shareComic('facebook')}
                              className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors"
                            >
                              <Facebook className="w-5 h-5" />
                              <span>Share on Facebook</span>
                            </button>
                            
                            <button
                              onClick={() => shareComic('twitter')}
                              className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-sky-500 hover:bg-sky-600 text-white transition-colors"
                            >
                              <Twitter className="w-5 h-5" />
                              <span>Share on Twitter</span>
                            </button>
                            
                            <button
                              onClick={() => shareComic('whatsapp')}
                              className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white transition-colors"
                            >
                              <MessageCircle className="w-5 h-5" />
                              <span>Share on WhatsApp</span>
                            </button>
                            
                            <button
                              onClick={() => shareComic('instagram')}
                              className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white transition-colors"
                            >
                              <Instagram className="w-5 h-5" />
                              <span>Open Instagram</span>
                            </button>
                            
                            <button
                              onClick={copyShareLink}
                              className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-gray-600 hover:bg-gray-700 text-white transition-colors"
                            >
                              <Link className="w-5 h-5" />
                              <span>{copySuccess ? 'Copied!' : 'Copy Link'}</span>
                            </button>
                          </div>

                          {/* Comic Preview */}
                          <div className="mt-4 p-3 bg-gray-900 rounded-lg">
                            <div className="flex items-center gap-3">
                              <img
                                src={comic.coverImage}
                                alt={comic.title}
                                className="w-12 h-16 object-cover rounded"
                              />
                              <div className="flex-1 min-w-0">
                                <h5 className="font-medium text-sm text-white truncate">{comic.title}</h5>
                                <p className="text-xs text-gray-400 mt-1">by {comic.creator}</p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </>
                  )}
                </div>
                
                {!comic.isFree && (
                  <button className="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-gray-700/50 hover:bg-gray-700 text-gray-300 rounded-lg transition-colors">
                    <Download className="w-5 h-5" />
                    <span>Download</span>
                  </button>
                )}
              </div>
            </div>

            {/* Comic Stats */}
            <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
              <h3 className="text-xl font-bold mb-4">Comic Info</h3>
              <div className="space-y-4">
                <div className="flex justify-between">
                  <span className="text-gray-400">Language</span>
                  <span className="text-white">{comic.language}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-400">Episodes</span>
                  <span className="text-white">{comic.episodeCount}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-400">Status</span>
                  <span className="text-emerald-400">Ongoing</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-400">Price</span>
                  <span className="text-white">{comic.isFree ? 'Free' : `$${comic.price}`}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-400">Rating</span>
                  <div className="flex items-center space-x-1">
                    <Star className="w-4 h-4 text-yellow-400 fill-current" />
                    <span className="text-white">{comic.rating}/5</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Creator Info */}
            <div className="bg-gray-800/50 rounded-xl p-6 border border-gray-700/50">
              <h3 className="text-xl font-bold mb-4">About the Creator</h3>
              <div className="flex items-center space-x-3 mb-4">
                <div className="w-12 h-12 bg-gradient-to-r from-emerald-500 to-purple-500 rounded-full flex items-center justify-center font-bold text-lg">
                  {comic.creator.charAt(0)}
                </div>
                <div>
                  <p className="font-semibold">{comic.creator}</p>
                  <p className="text-sm text-gray-400">Comic Creator</p>
                </div>
              </div>
              <p className="text-gray-300 text-sm mb-4">
                Award-winning storyteller passionate about African narratives and cultural heritage.
              </p>
              <button className="w-full px-4 py-2 bg-gradient-to-r from-emerald-500 to-purple-500 hover:from-emerald-600 hover:to-purple-600 rounded-lg font-semibold transition-all duration-300">
                View More Comics
              </button>
            </div>
          </div>
        </div>

        {/* Preview Modal */}
        {showPreview && (
          <div className="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div className="max-w-2xl max-h-full bg-gray-900 rounded-xl overflow-hidden">
              <div className="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 className="text-lg font-semibold">Preview - {comic.title}</h3>
                <button
                  onClick={() => setShowPreview(false)}
                  className="p-2 hover:bg-gray-700 rounded-lg transition-colors"
                >
                  ×
                </button>
              </div>
              <div className="p-4">
                <img
                  src={comic.coverImage}
                  alt="Preview"
                  className="w-full h-96 object-contain bg-gray-800 rounded-lg"
                />
                <div className="mt-4 flex justify-between">
                  <button
                    onClick={() => setShowPreview(false)}
                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
                  >
                    Close
                  </button>
                  <button 
                    onClick={() => {
                      setShowPreview(false);
                      onNavigate('reader', comic.id);
                    }}
                    className="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 rounded-lg transition-colors"
                  >
                    Read Full Comic
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ComicDetail;