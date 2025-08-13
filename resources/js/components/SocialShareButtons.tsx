import React, { useState } from 'react';
import { Share2, Facebook, Twitter, Instagram, Link, MessageCircle } from 'lucide-react';
import { Card, CardContent } from './ui/card';
import { router } from '@inertiajs/react';

interface SocialShareButtonsProps {
  comicId: number;
  comicTitle: string;
  comicCover?: string;
  shareUrl: string;
  shareType?: 'discovery' | 'achievement' | 'recommendation' | 'review';
  className?: string;
}

interface SharePlatform {
  name: string;
  icon: React.ReactNode;
  color: string;
  shareUrl: (url: string, title: string, description?: string) => string;
}

export const SocialShareButtons: React.FC<SocialShareButtonsProps> = ({
  comicId,
  comicTitle,
  comicCover,
  shareUrl,
  shareType = 'discovery',
  className = ''
}) => {
  const [showShareMenu, setShowShareMenu] = useState(false);
  const [copySuccess, setCopySuccess] = useState(false);

  const getShareMessage = () => {
    switch (shareType) {
      case 'achievement':
        return `Just finished reading "${comicTitle}"! 🎉`;
      case 'recommendation':
        return `Check out this amazing comic: "${comicTitle}" 📚`;
      case 'review':
        return `Just reviewed "${comicTitle}" - what did you think?`;
      default:
        return `Discovered an awesome comic: "${comicTitle}"`;
    }
  };

  const shareMessage = getShareMessage();
  const description = `${shareMessage} Read it now!`;

  const platforms: SharePlatform[] = [
    {
      name: 'Facebook',
      icon: <Facebook className="w-5 h-5" />,
      color: 'bg-blue-600 hover:bg-blue-700',
      shareUrl: (url, title, desc) => 
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}&quote=${encodeURIComponent(`📚 ${desc || title}`)}`
    },
    {
      name: 'Twitter',
      icon: <Twitter className="w-5 h-5" />,
      color: 'bg-sky-500 hover:bg-sky-600',
      shareUrl: (url, title, desc) => 
        `https://twitter.com/intent/tweet?text=${encodeURIComponent(`📚 ${desc || title}`)}&url=${encodeURIComponent(url)}&hashtags=comics,reading`
    },
    {
      name: 'Instagram',
      icon: <Instagram className="w-5 h-5" />,
      color: 'bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600',
      shareUrl: (url, title) => 
        `https://www.instagram.com/` // Instagram doesn't support direct sharing, opens profile
    },
    {
      name: 'WhatsApp',
      icon: <MessageCircle className="w-5 h-5" />,
      color: 'bg-green-500 hover:bg-green-600',
      shareUrl: (url, title, desc) => 
        `https://wa.me/?text=${encodeURIComponent(`📚 ${desc || title}\n\n🔗 Read it here: ${url}`)}`
    }
  ];

  const handleShare = async (platform: SharePlatform) => {
    // Open share URL first
    if (platform.name === 'Instagram') {
      // For Instagram, we can't directly share, so just open Instagram
      window.open('https://www.instagram.com/', '_blank');
    } else {
      const url = platform.shareUrl(shareUrl, comicTitle, description);
      window.open(url, '_blank', 'width=600,height=400');
    }

    // Try to track the share (but don't block the sharing if it fails)
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      
      await fetch(`/api/social/comics/${comicId}/share`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken || ''
        },
        body: JSON.stringify({
          platform: platform.name.toLowerCase(),
          share_type: shareType,
          metadata: {
            title: comicTitle,
            url: shareUrl,
            message: shareMessage,
            cover_image: comicCover
          }
        })
      });
    } catch (error) {
      // Silently fail - sharing should work even without tracking
      console.debug('Share tracking failed:', error);
    }

    setShowShareMenu(false);
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopySuccess(true);
      setTimeout(() => setCopySuccess(false), 2000);

      // Try to track the copy action (but don't block if it fails)
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        await fetch(`/api/social/comics/${comicId}/share`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken || ''
          },
          body: JSON.stringify({
            platform: 'copy_link',
            share_type: shareType,
            metadata: {
              title: comicTitle,
              url: shareUrl,
              message: shareMessage,
              cover_image: comicCover
            }
          })
        });
      } catch (trackingError) {
        console.debug('Copy link tracking failed:', trackingError);
      }
    } catch (error) {
      console.error('Failed to copy link:', error);
    }
  };

  const handleNativeShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: comicTitle,
          text: shareMessage,
          url: shareUrl
        });

        // Try to track the native share (but don't block if it fails)
        try {
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
          
          await fetch(`/api/social/comics/${comicId}/share`, {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({
              platform: 'native',
              share_type: shareType,
              metadata: {
                title: comicTitle,
                url: shareUrl,
                message: shareMessage,
                cover_image: comicCover
              }
            })
          });
        } catch (trackingError) {
          console.debug('Native share tracking failed:', trackingError);
        }
      } catch (error) {
        if (error.name !== 'AbortError') {
          console.error('Failed to share:', error);
        }
      }
    }
  };

  return (
    <div className={`relative ${className}`}>
      <button
        onClick={() => {
          if (navigator.share) {
            handleNativeShare();
          } else {
            setShowShareMenu(!showShareMenu);
          }
        }}
        className="w-full flex items-center justify-center space-x-2 px-6 py-3 bg-gray-800/50 border border-gray-600 text-white rounded-xl hover:bg-gray-700/50 transition-all duration-300"
      >
        <Share2 className="w-5 h-5" />
        <span>Share</span>
      </button>

      {showShareMenu && (
        <>
          {/* Modal Backdrop */}
          <div 
            className="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] flex items-center justify-center p-4"
            onClick={() => setShowShareMenu(false)}
          >
            {/* Share Modal */}
            <div 
              className="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-md mx-auto"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                  <h4 className="text-xl font-semibold text-white">Share this comic</h4>
                  <button
                    onClick={() => setShowShareMenu(false)}
                    className="p-2 hover:bg-gray-800 rounded-lg transition-colors"
                  >
                    <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                {/* Comic Preview */}
                {comicCover && (
                  <div className="mb-6 p-4 bg-gray-800/50 rounded-lg border border-gray-700">
                    <div className="flex items-center gap-4">
                      <img
                        src={comicCover}
                        alt={comicTitle}
                        className="w-16 h-20 object-cover rounded-lg"
                      />
                      <div className="flex-1 min-w-0">
                        <h5 className="font-semibold text-white truncate text-lg">{comicTitle}</h5>
                        <p className="text-sm text-gray-300 mt-1">{shareMessage}</p>
                      </div>
                    </div>
                  </div>
                )}

                {/* Share URL Display */}
                <div className="mb-6 p-3 bg-gray-800/50 rounded-lg border border-gray-700">
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-400 flex-shrink-0">Link:</span>
                    <span className="text-sm text-gray-300 truncate flex-1">{shareUrl}</span>
                    <button
                      onClick={handleCopyLink}
                      className="flex items-center gap-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors flex-shrink-0"
                    >
                      <Link className="w-4 h-4" />
                      <span>{copySuccess ? 'Copied!' : 'Copy'}</span>
                    </button>
                  </div>
                </div>
                
                {/* Social Platform Buttons */}
                <div className="space-y-3">
                  <p className="text-sm text-gray-400 mb-3">Share on social media:</p>
                  <div className="grid grid-cols-2 gap-3">
                    {platforms.map((platform) => (
                      <button
                        key={platform.name}
                        onClick={() => handleShare(platform)}
                        className={`
                          flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-white transition-all duration-300 hover:scale-105
                          ${platform.color} shadow-lg
                        `}
                      >
                        {platform.icon}
                        <span className="font-medium">{platform.name}</span>
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
};