import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, X, Bookmark, Share2, Download, RotateCcw, Settings } from 'lucide-react';
import { getComicById } from '../data/mockComics';
import type { Page } from '../App';

interface ComicReaderProps {
  comicId: string | null;
  onNavigate: (page: Page) => void;
}

const ComicReader: React.FC<ComicReaderProps> = ({ comicId, onNavigate }) => {
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages] = useState(24); // Mock total pages
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [readingMode, setReadingMode] = useState<'horizontal' | 'vertical'>('horizontal');
  const [showControls, setShowControls] = useState(true);
  const [isBookmarked, setIsBookmarked] = useState(false);

  const comic = comicId ? getComicById(comicId) : null;

  useEffect(() => {
    let timeout: NodeJS.Timeout;
    if (showControls) {
      timeout = setTimeout(() => setShowControls(false), 3000);
    }
    return () => clearTimeout(timeout);
  }, [showControls, currentPage]);

  useEffect(() => {
    const handleKeyPress = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft' && currentPage > 1) {
        setCurrentPage(prev => prev - 1);
      } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
        setCurrentPage(prev => prev + 1);
      } else if (e.key === 'Escape') {
        onNavigate('detail');
      }
    };

    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, [currentPage, totalPages, onNavigate]);

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

  // Mock comic pages (using the same image for demo purposes)
  const comicPages = Array.from({ length: totalPages }, (_, i) => ({
    id: i + 1,
    image: comic.coverImage,
    alt: `${comic.title} - Page ${i + 1}`
  }));

  const handlePageNavigation = (direction: 'prev' | 'next') => {
    if (direction === 'prev' && currentPage > 1) {
      setCurrentPage(prev => prev - 1);
    } else if (direction === 'next' && currentPage < totalPages) {
      setCurrentPage(prev => prev + 1);
    }
    setShowControls(true);
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
      setIsFullscreen(true);
    } else {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  const progress = (currentPage / totalPages) * 100;

  return (
    <div className="relative min-h-screen bg-black text-white">
      {/* Header Controls */}
      <div className={`absolute top-0 left-0 right-0 z-50 bg-gradient-to-b from-black/80 to-transparent transition-all duration-300 ${
        showControls ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-full'
      }`}>
        <div className="flex items-center justify-between p-4">
          <div className="flex items-center space-x-4">
            <button
              onClick={() => onNavigate('detail')}
              className="p-2 hover:bg-gray-700/50 rounded-lg transition-colors"
            >
              <X className="w-6 h-6" />
            </button>
            <div>
              <h1 className="font-bold text-lg">{comic.title}</h1>
              <p className="text-sm text-gray-400">{comic.creator}</p>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            <button
              onClick={() => setIsBookmarked(!isBookmarked)}
              className={`p-2 rounded-lg transition-colors ${
                isBookmarked ? 'bg-emerald-500 text-white' : 'hover:bg-gray-700/50'
              }`}
            >
              <Bookmark className="w-5 h-5" />
            </button>
            <button className="p-2 hover:bg-gray-700/50 rounded-lg transition-colors">
              <Share2 className="w-5 h-5" />
            </button>
            <button className="p-2 hover:bg-gray-700/50 rounded-lg transition-colors">
              <Download className="w-5 h-5" />
            </button>
            <button
              onClick={toggleFullscreen}
              className="p-2 hover:bg-gray-700/50 rounded-lg transition-colors"
            >
              <Settings className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="px-4 pb-4">
          <div className="flex items-center space-x-4">
            <span className="text-sm text-gray-400 whitespace-nowrap">
              {currentPage} / {totalPages}
            </span>
            <div className="flex-1 bg-gray-700 rounded-full h-2">
              <div 
                className="bg-gradient-to-r from-emerald-500 to-purple-500 h-2 rounded-full transition-all duration-300"
                style={{ width: `${progress}%` }}
              />
            </div>
            <span className="text-sm text-gray-400 whitespace-nowrap">
              {Math.round(progress)}%
            </span>
          </div>
        </div>
      </div>

      {/* Reading Mode Toggle */}
      <div className={`absolute top-20 right-4 z-40 transition-all duration-300 ${
        showControls ? 'opacity-100' : 'opacity-0'
      }`}>
        <div className="bg-gray-800/90 backdrop-blur-sm rounded-lg p-2">
          <button
            onClick={() => setReadingMode('horizontal')}
            className={`px-3 py-2 rounded text-sm transition-colors ${
              readingMode === 'horizontal' 
                ? 'bg-emerald-500 text-white' 
                : 'text-gray-300 hover:text-white'
            }`}
          >
            Horizontal
          </button>
          <button
            onClick={() => setReadingMode('vertical')}
            className={`px-3 py-2 rounded text-sm transition-colors ${
              readingMode === 'vertical' 
                ? 'bg-emerald-500 text-white' 
                : 'text-gray-300 hover:text-white'
            }`}
          >
            Vertical
          </button>
        </div>
      </div>

      {/* Comic Content */}
      <div 
        className="relative h-screen flex items-center justify-center cursor-pointer"
        onClick={() => setShowControls(!showControls)}
      >
        {readingMode === 'horizontal' ? (
          <div className="relative max-w-4xl max-h-full">
            <img
              src={comicPages[currentPage - 1].image}
              alt={comicPages[currentPage - 1].alt}
              className="max-w-full max-h-full object-contain"
            />
          </div>
        ) : (
          <div className="relative max-w-2xl max-h-full overflow-y-auto">
            {comicPages.slice(currentPage - 1, currentPage + 2).map((page, index) => (
              <img
                key={page.id}
                src={page.image}
                alt={page.alt}
                className="w-full mb-4 object-contain"
              />
            ))}
          </div>
        )}

        {/* Navigation Areas (for horizontal mode) */}
        {readingMode === 'horizontal' && (
          <>
            <button
              onClick={(e) => {
                e.stopPropagation();
                handlePageNavigation('prev');
              }}
              disabled={currentPage === 1}
              className="absolute left-0 top-0 w-1/3 h-full flex items-center justify-start pl-4 disabled:cursor-not-allowed group"
            >
              <div className={`p-3 bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity ${
                currentPage === 1 ? 'invisible' : 'visible'
              }`}>
                <ChevronLeft className="w-8 h-8" />
              </div>
            </button>
            
            <button
              onClick={(e) => {
                e.stopPropagation();
                handlePageNavigation('next');
              }}
              disabled={currentPage === totalPages}
              className="absolute right-0 top-0 w-1/3 h-full flex items-center justify-end pr-4 disabled:cursor-not-allowed group"
            >
              <div className={`p-3 bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity ${
                currentPage === totalPages ? 'invisible' : 'visible'
              }`}>
                <ChevronRight className="w-8 h-8" />
              </div>
            </button>
          </>
        )}
      </div>

      {/* Bottom Controls */}
      <div className={`absolute bottom-0 left-0 right-0 z-50 bg-gradient-to-t from-black/80 to-transparent transition-all duration-300 ${
        showControls ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-full'
      }`}>
        <div className="flex items-center justify-between p-4">
          <button
            onClick={() => handlePageNavigation('prev')}
            disabled={currentPage === 1}
            className="flex items-center space-x-2 px-4 py-2 bg-gray-700/50 hover:bg-gray-600/50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeft className="w-5 h-5" />
            <span>Previous</span>
          </button>

          <div className="flex items-center space-x-4">
            <button
              onClick={() => setCurrentPage(1)}
              className="p-2 hover:bg-gray-700/50 rounded-lg transition-colors"
            >
              <RotateCcw className="w-5 h-5" />
            </button>
            <span className="text-sm font-medium">
              Page {currentPage} of {totalPages}
            </span>
          </div>

          <button
            onClick={() => handlePageNavigation('next')}
            disabled={currentPage === totalPages}
            className="flex items-center space-x-2 px-4 py-2 bg-gray-700/50 hover:bg-gray-600/50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span>Next</span>
            <ChevronRight className="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  );
};

export default ComicReader;