import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { Comic } from '../types';
import api from '../services/api';

export const ComicReader: React.FC = () => {
  const { slug } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const [comic, setComic] = useState<Comic | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [controlsVisible, setControlsVisible] = useState(true);
  const [currentPage, setCurrentPage] = useState(0);
  const [progress, setProgress] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const hideTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Fetch comic data
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

  const pages = comic?.pages || [];
  const totalPages = pages.length;

  // Auto-hide controls after 3 seconds
  const resetHideTimer = useCallback(() => {
    if (hideTimeoutRef.current) {
      clearTimeout(hideTimeoutRef.current);
    }
    hideTimeoutRef.current = setTimeout(() => {
      setControlsVisible(false);
    }, 3000);
  }, []);

  useEffect(() => {
    if (controlsVisible) {
      resetHideTimer();
    }
    return () => {
      if (hideTimeoutRef.current) {
        clearTimeout(hideTimeoutRef.current);
      }
    };
  }, [controlsVisible, resetHideTimer]);

  // Debounced progress save
  const progressSaveTimeout = useRef<NodeJS.Timeout | null>(null);
  const lastSavedPage = useRef<number>(-1);

  const saveProgress = useCallback((page: number) => {
    if (!slug || !api.isAuthenticated() || page === lastSavedPage.current) return;

    if (progressSaveTimeout.current) {
      clearTimeout(progressSaveTimeout.current);
    }

    progressSaveTimeout.current = setTimeout(() => {
      api.updateProgress(slug, page + 1, totalPages).catch(() => {});
      lastSavedPage.current = page;
    }, 2000); // Save after 2 seconds of stability
  }, [slug, totalPages]);

  // Track scroll progress
  useEffect(() => {
    const handleScroll = () => {
      const winScroll = document.documentElement.scrollTop;
      const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
      if (height > 0 && totalPages > 0) {
        const scrolled = (winScroll / height) * 100;
        setProgress(scrolled);
        const pageIndex = Math.floor((scrolled / 100) * totalPages);
        const newPage = Math.min(pageIndex, totalPages - 1);
        setCurrentPage(newPage);
        saveProgress(newPage);
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => {
      window.removeEventListener('scroll', handleScroll);
      if (progressSaveTimeout.current) {
        clearTimeout(progressSaveTimeout.current);
      }
    };
  }, [totalPages, saveProgress]);

  const toggleControls = () => {
    setControlsVisible(!controlsVisible);
  };

  const scrollToPage = (pageIndex: number) => {
    const pageElements = containerRef.current?.querySelectorAll('.comic-page');
    if (pageElements && pageElements[pageIndex]) {
      pageElements[pageIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const goToPreviousPage = () => {
    if (currentPage > 0) {
      scrollToPage(currentPage - 1);
    }
  };

  const goToNextPage = () => {
    if (currentPage < totalPages - 1) {
      scrollToPage(currentPage + 1);
    }
  };

  // Keyboard navigation
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        goToPreviousPage();
      } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') {
        goToNextPage();
      } else if (e.key === 'Escape') {
        navigate(`/comics/${slug}`);
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [currentPage, totalPages, navigate, slug]);

  const handleBack = () => {
    navigate(`/comics/${slug}`);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-black flex items-center justify-center">
        <div className="animate-pulse text-white">Loading...</div>
      </div>
    );
  }

  if (error || !comic) {
    return (
      <div className="min-h-screen bg-black flex flex-col items-center justify-center p-4">
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

  // Check access for paid content
  if (comic && !comic.isFree && !comic.hasAccess) {
    return (
      <div className="min-h-screen bg-black flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h2 className="text-xl font-bold text-white mb-4">{comic.title}</h2>
          <p className="text-gray-400 mb-4">This comic requires purchase to read.</p>
          <p className="text-yellow-400 font-semibold mb-6">
            ${comic.price?.toFixed(2) || '0.00'}
          </p>
          <Link
            to={`/comics/${slug}`}
            className="bg-[#DC2626] text-white px-6 py-3 rounded-lg hover:bg-[#B91C1C] transition-colors"
          >
            Go to Details
          </Link>
        </div>
      </div>
    );
  }

  if (totalPages === 0) {
    return (
      <div className="min-h-screen bg-black flex flex-col items-center justify-center p-4">
        <div className="text-center">
          <h2 className="text-xl font-bold text-white mb-4">{comic.title}</h2>
          <p className="text-gray-400 mb-6">No pages available for this comic yet.</p>
          <Link
            to={`/comics/${slug}`}
            className="bg-[#DC2626] text-white px-6 py-3 rounded-lg hover:bg-[#B91C1C] transition-colors"
          >
            Back to Details
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-black select-none" ref={containerRef}>
      {/* Top Header */}
      <div
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
          controlsVisible ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-full pointer-events-none'
        }`}
      >
        <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-b from-black/90 via-black/60 to-transparent">
          {/* Back Button */}
          <button
            onClick={handleBack}
            className="p-2.5 rounded-xl bg-black/50 hover:bg-black/70 transition-colors"
          >
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          {/* Logo */}
          <Link to="/" className="flex items-center gap-2">
            <img
              src="/images/bagcomics.jpeg"
              alt="BAG Comics"
              className="w-7 h-7 object-cover rounded-md"
            />
            <span className="text-white font-bold text-lg">BAG<span className="font-light text-[#DC2626]">Comics</span></span>
          </Link>

          {/* Close Button */}
          <button
            onClick={handleBack}
            className="p-2.5 rounded-xl bg-black/50 hover:bg-black/70 transition-colors"
          >
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      {/* Main Content - Comic Pages */}
      <main className="w-full max-w-3xl mx-auto pt-16 pb-32" onClick={toggleControls}>
        <div className="flex flex-col">
          {pages.map((page, index) => (
            <div key={index} className="comic-page relative">
              <img
                src={page}
                alt={`Page ${index + 1}`}
                className="w-full h-auto block"
                loading={index < 3 ? 'eager' : 'lazy'}
              />
              {index === pages.length - 1 && (
                <div className="absolute bottom-8 left-0 right-0 text-center">
                  <h2 className="text-2xl md:text-3xl font-black italic tracking-wide text-[#FFD700] uppercase drop-shadow-lg">
                    CHAPTER ONE
                  </h2>
                </div>
              )}
            </div>
          ))}
        </div>

        {/* End of Chapter Marker */}
        <div className="py-16 flex flex-col items-center gap-4 bg-black">
          <div className="text-gray-500 text-sm uppercase tracking-widest">End of Chapter</div>
          <button
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className="text-[#DC2626] hover:text-[#F87171] transition-colors text-sm font-medium"
          >
            Back to Top
          </button>
        </div>
      </main>

      {/* Progress Dots - Right Side */}
      <div
        className={`fixed right-3 top-1/2 -translate-y-1/2 z-40 flex flex-col gap-2 transition-all duration-300 ${
          controlsVisible ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
      >
        {Array.from({ length: Math.min(totalPages, 12) }).map((_, i) => {
          const pageIndex = Math.floor((i / 12) * totalPages);
          const isActive = currentPage >= pageIndex && (i === 11 || currentPage < Math.floor(((i + 1) / 12) * totalPages));
          return (
            <button
              key={i}
              onClick={() => scrollToPage(Math.floor((i / 12) * totalPages))}
              className={`w-2 h-2 rounded-full transition-all duration-200 ${
                isActive
                  ? 'bg-white scale-125 ring-2 ring-white/30'
                  : 'bg-gray-600 hover:bg-gray-400'
              }`}
            />
          );
        })}
      </div>

      {/* Navigation Arrows */}
      <div
        className={`fixed inset-y-0 left-0 right-0 pointer-events-none flex items-center justify-between px-2 md:px-4 z-30 transition-all duration-300 ${
          controlsVisible ? 'opacity-100' : 'opacity-0'
        }`}
      >
        <button
          onClick={goToPreviousPage}
          disabled={currentPage === 0}
          className={`pointer-events-auto p-3 rounded-xl transition-all ${
            currentPage === 0
              ? 'opacity-30 cursor-not-allowed'
              : 'bg-[#DC2626]/80 hover:bg-[#DC2626] shadow-lg'
          }`}
        >
          <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" />
          </svg>
        </button>
        <button
          onClick={goToNextPage}
          disabled={currentPage === totalPages - 1}
          className={`pointer-events-auto p-3 rounded-xl transition-all ${
            currentPage === totalPages - 1
              ? 'opacity-30 cursor-not-allowed'
              : 'bg-[#DC2626]/80 hover:bg-[#DC2626] shadow-lg'
          }`}
        >
          <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" />
          </svg>
        </button>
      </div>

      {/* Bottom Toolbar */}
      <div
        className={`fixed bottom-0 left-0 right-0 z-50 transition-all duration-300 ${
          controlsVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-full pointer-events-none'
        }`}
      >
        <div className="bg-black/95 backdrop-blur-md border-t border-gray-800 px-4 py-4">
          {/* Progress Bar */}
          <div className="w-full h-1 bg-gray-800 rounded-full mb-4 overflow-hidden">
            <div
              className="h-full bg-[#DC2626] rounded-full transition-all duration-150"
              style={{ width: `${progress}%` }}
            />
          </div>

          {/* Page Counter */}
          <div className="text-center text-gray-400 text-xs mb-4">
            {currentPage + 1} / {totalPages}
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-around">
            <button className="flex flex-col items-center gap-1.5 group">
              <div className="p-2.5 border-2 border-gray-700 rounded-lg group-hover:border-[#4ade80] transition-colors">
                <svg className="w-5 h-5 text-gray-400 group-hover:text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 11.37 9.19 15.878 5.412 19" />
                </svg>
              </div>
              <span className="text-[10px] font-semibold text-gray-500 uppercase tracking-wider group-hover:text-white">Subtitle</span>
            </button>

            <button className="flex flex-col items-center gap-1.5 group">
              <div className="p-2.5 border-2 border-[#4ade80] rounded-lg bg-[#4ade80]/10">
                <svg className="w-5 h-5 text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                </svg>
              </div>
              <span className="text-[10px] font-semibold text-[#4ade80] uppercase tracking-wider">Read-Out</span>
            </button>

            <button className="flex flex-col items-center gap-1.5 group">
              <div className="p-2.5 border-2 border-gray-700 rounded-lg group-hover:border-[#4ade80] transition-colors">
                <svg className="w-5 h-5 text-gray-400 group-hover:text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
              </div>
              <span className="text-[10px] font-semibold text-gray-500 uppercase tracking-wider group-hover:text-white">Highlight</span>
            </button>

            <button className="flex flex-col items-center gap-1.5 group">
              <div className="p-2.5 border-2 border-gray-700 rounded-lg group-hover:border-[#4ade80] transition-colors">
                <svg className="w-5 h-5 text-gray-400 group-hover:text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <span className="text-[10px] font-semibold text-gray-500 uppercase tracking-wider group-hover:text-white">Autoplay</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
