import React, { useState } from 'react';
import { Share2, Facebook, Twitter, Instagram, Link, MessageCircle } from 'lucide-react';
import { Button } from './ui/button';
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
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}&quote=${encodeURIComponent(desc || title)}`
    },
    {
      name: 'Twitter',
      icon: <Twitter className="w-5 h-5" />,
      color: 'bg-sky-500 hover:bg-sky-600',
      shareUrl: (url, title, desc) => 
        `https://twitter.com/intent/tweet?text=${encodeURIComponent(desc || title)}&url=${encodeURIComponent(url)}`
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
        `https://wa.me/?text=${encodeURIComponent(`${desc || title} ${url}`)}`
    }
  ];

  const handleShare = async (platform: SharePlatform) => {
    try {
      // Track the share
      await router.post(`/api/comics/${comicId}/share`, {
        platform: platform.name.toLowerCase(),
        share_type: shareType,
        metadata: {
          title: comicTitle,
          url: shareUrl,
          message: shareMessage
        }
      });

      // Open share URL
      if (platform.name === 'Instagram') {
        // For Instagram, we can't directly share, so just open Instagram
        window.open('https://www.instagram.com/', '_blank');
      } else {
        const url = platform.shareUrl(shareUrl, comicTitle, description);
        window.open(url, '_blank', 'width=600,height=400');
      }
    } catch (error) {
      console.error('Failed to track share:', error);
      // Still open the share URL even if tracking fails
      const url = platform.shareUrl(shareUrl, comicTitle, description);
      window.open(url, '_blank', 'width=600,height=400');
    }

    setShowShareMenu(false);
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopySuccess(true);
      
      // Track the copy action
      await router.post(`/api/comics/${comicId}/share`, {
        platform: 'copy_link',
        share_type: shareType,
        metadata: {
          title: comicTitle,
          url: shareUrl,
          message: shareMessage
        }
      });

      setTimeout(() => setCopySuccess(false), 2000);
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

        // Track the native share
        await router.post(`/api/comics/${comicId}/share`, {
          platform: 'native',
          share_type: shareType,
          metadata: {
            title: comicTitle,
            url: shareUrl,
            message: shareMessage
          }
        });
      } catch (error) {
        if (error.name !== 'AbortError') {
          console.error('Failed to share:', error);
        }
      }
    }
  };

  return (
    <div className={`relative ${className}`}>
      <Button
        variant="outline"
        size="sm"
        onClick={() => {
          if (navigator.share) {
            handleNativeShare();
          } else {
            setShowShareMenu(!showShareMenu);
          }
        }}
        className="flex items-center gap-2"
      >
        <Share2 className="w-4 h-4" />
        Share
      </Button>

      {showShareMenu && (
        <>
          {/* Backdrop */}
          <div 
            className="fixed inset-0 z-40"
            onClick={() => setShowShareMenu(false)}
          />
          
          {/* Share Menu */}
          <Card className="absolute top-full mt-2 right-0 z-50 w-64">
            <CardContent className="p-4">
              <h4 className="font-medium mb-3">Share this comic</h4>
              
              <div className="space-y-2">
                {platforms.map((platform) => (
                  <button
                    key={platform.name}
                    onClick={() => handleShare(platform)}
                    className={`
                      w-full flex items-center gap-3 px-3 py-2 rounded-lg text-white transition-colors
                      ${platform.color}
                    `}
                  >
                    {platform.icon}
                    <span>Share on {platform.name}</span>
                  </button>
                ))}
                
                <button
                  onClick={handleCopyLink}
                  className="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-gray-600 hover:bg-gray-700 text-white transition-colors"
                >
                  <Link className="w-5 h-5" />
                  <span>{copySuccess ? 'Copied!' : 'Copy Link'}</span>
                </button>
              </div>

              {comicCover && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg">
                  <div className="flex items-center gap-3">
                    <img
                      src={comicCover}
                      alt={comicTitle}
                      className="w-12 h-16 object-cover rounded"
                    />
                    <div className="flex-1 min-w-0">
                      <h5 className="font-medium text-sm truncate">{comicTitle}</h5>
                      <p className="text-xs text-gray-600 mt-1">{shareMessage}</p>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
};