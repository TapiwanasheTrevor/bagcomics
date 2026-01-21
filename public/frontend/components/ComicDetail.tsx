import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { Comic } from '../types';
import api from '../services/api';

export const ComicDetail: React.FC = () => {
  const { slug } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const [comic, setComic] = useState<Comic | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchComic = async () => {
      if (!slug) return;

      setLoading(true);
      setError(null);

      try {
        const res = await api.getComic(slug);
        const comicData = res?.data?.data || res?.data || res;
        setComic(comicData);
      } catch (err) {
        console.error('Failed to fetch comic:', err);
        setError('Comic not found');
      } finally {
        setLoading(false);
      }
    };

    fetchComic();
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen bg-[#0a0a0a] flex items-center justify-center">
        <div className="animate-pulse text-white">Loading...</div>
      </div>
    );
  }

  if (error || !comic) {
    return (
      <div className="min-h-screen bg-[#0a0a0a] flex flex-col items-center justify-center p-4">
        <h2 className="text-xl font-bold text-white mb-4">Comic Not Found</h2>
        <p className="text-gray-400 mb-6">{error || 'The comic you are looking for does not exist.'}</p>
        <Link
          to="/"
          className="bg-[#DC2626] text-white px-6 py-3 rounded-lg hover:bg-[#B91C1C] transition-colors"
        >
          Go Home
        </Link>
      </div>
    );
  }

  const handleStartReading = () => {
    navigate(`/comics/${slug}/read`);
  };

  const shareUrl = `${window.location.origin}/comics/${slug}`;

  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: comic.title,
          text: comic.description || `Check out ${comic.title} on BAG Comics!`,
          url: shareUrl,
        });
      } catch (err) {
        // User cancelled or share failed
        copyToClipboard();
      }
    } else {
      copyToClipboard();
    }
  };

  const copyToClipboard = () => {
    navigator.clipboard.writeText(shareUrl);
    alert('Link copied to clipboard!');
  };

  return (
    <div className="min-h-screen bg-[#0a0a0a]">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-[#0a0a0a]/95 backdrop-blur-md border-b border-gray-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <img
              src="/images/bagcomics.jpeg"
              alt="BAG Comics"
              className="w-8 h-8 object-cover rounded-md"
            />
            <span className="text-xl font-bold text-white">
              BAG<span className="font-light text-[#DC2626]">Comics</span>
            </span>
          </Link>
          <button
            onClick={handleShare}
            className="p-2 rounded-lg bg-[#1a1a1a] hover:bg-[#2a2a2a] transition-colors"
            title="Share"
          >
            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
          </button>
        </div>
      </header>

      {/* Hero Section */}
      <div className="relative">
        {/* Background blur */}
        <div
          className="absolute inset-0 bg-cover bg-center blur-2xl opacity-20"
          style={{ backgroundImage: `url(${comic.coverImage})` }}
        />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 py-8 md:py-12">
          <div className="flex flex-col md:flex-row gap-8">
            {/* Cover Image */}
            <div className="flex-shrink-0 w-full md:w-80">
              <div className="aspect-[3/4] rounded-xl overflow-hidden shadow-2xl">
                <img
                  src={comic.coverImage}
                  alt={comic.title}
                  className="w-full h-full object-cover"
                />
              </div>
            </div>

            {/* Comic Info */}
            <div className="flex-1 flex flex-col">
              {/* Genres */}
              <div className="flex flex-wrap gap-2 mb-4">
                {(comic.genre || []).map((g, i) => (
                  <span
                    key={i}
                    className="px-3 py-1 bg-[#DC2626]/20 text-[#DC2626] text-xs font-medium rounded-full"
                  >
                    {g}
                  </span>
                ))}
                {comic.isFree && (
                  <span className="px-3 py-1 bg-green-500/20 text-green-400 text-xs font-medium rounded-full">
                    FREE
                  </span>
                )}
              </div>

              {/* Title */}
              <h1 className="text-3xl md:text-4xl font-bold text-white mb-2">
                {comic.title}
              </h1>

              {/* Author */}
              <p className="text-gray-400 text-lg mb-4">
                by <span className="text-white">{comic.author}</span>
              </p>

              {/* Rating */}
              <div className="flex items-center gap-2 mb-6">
                <div className="flex gap-0.5">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <svg
                      key={star}
                      className={`w-5 h-5 ${
                        star <= Math.round(comic.rating || 0)
                          ? 'text-yellow-400'
                          : 'text-gray-600'
                      } fill-current`}
                      viewBox="0 0 20 20"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  ))}
                </div>
                <span className="text-gray-400 text-sm">
                  {comic.rating?.toFixed(1) || '0.0'}
                </span>
                {comic.likesCount !== undefined && (
                  <span className="text-gray-500 text-sm ml-4">
                    {comic.likesCount} likes
                  </span>
                )}
              </div>

              {/* Description */}
              <p className="text-gray-300 leading-relaxed mb-8 max-w-2xl">
                {comic.description}
              </p>

              {/* Stats */}
              <div className="flex flex-wrap gap-6 mb-8 text-sm">
                <div>
                  <span className="text-gray-500">Pages</span>
                  <p className="text-white font-semibold">{comic.totalChapters || 0}</p>
                </div>
                <div>
                  <span className="text-gray-500">Episodes</span>
                  <p className="text-white font-semibold">{comic.episodes || 1}</p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-wrap gap-4">
                <button
                  onClick={handleStartReading}
                  className="flex items-center gap-2 bg-[#DC2626] hover:bg-[#B91C1C] text-white px-8 py-4 rounded-xl font-semibold text-lg transition-colors"
                >
                  <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                  </svg>
                  Start Reading
                </button>

                <button
                  onClick={handleShare}
                  className="flex items-center gap-2 bg-[#1a1a1a] hover:bg-[#2a2a2a] text-white px-6 py-4 rounded-xl font-medium transition-colors border border-gray-700"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                  </svg>
                  Share
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <footer className="bg-[#0d0d0d] py-8 px-4 border-t border-gray-900 mt-12">
        <div className="max-w-7xl mx-auto text-center">
          <p className="text-gray-600 text-sm">
            &copy; {new Date().getFullYear()} BAG Comics. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
};
