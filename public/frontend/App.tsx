
import React, { useState, useEffect, useCallback } from 'react';
import { Header } from './components/Header';
import { ComicCard } from './components/ComicCard';
import { Reader } from './components/Reader';
import { Library } from './components/Library';
import { ViewMode, Comic } from './types';
import api from './services/api';

const App: React.FC = () => {
  const [view, setView] = useState<ViewMode>(ViewMode.HOME);
  const [selectedComic, setSelectedComic] = useState<Comic | null>(null);
  const [bookmarks, setBookmarks] = useState<string[]>([]);
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  // API state
  const [comics, setComics] = useState<Comic[]>([]);
  const [featuredComics, setFeaturedComics] = useState<Comic[]>([]);
  const [genres, setGenres] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedGenre, setSelectedGenre] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  // Fetch comics from API
  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);
      try {
        const [recentRes, featuredRes, genresRes] = await Promise.all([
          api.getRecent(),
          api.getFeatured(),
          api.getGenres(),
        ]);
        // API returns {data: {data: [...], meta: {...}}} structure
        const recentData = recentRes?.data?.data || recentRes?.data || [];
        const featuredData = featuredRes?.data?.data || featuredRes?.data || [];
        const genresData = genresRes?.data?.data || genresRes?.data || [];
        setComics(Array.isArray(recentData) ? recentData : []);
        setFeaturedComics(Array.isArray(featuredData) ? featuredData : []);
        setGenres(Array.isArray(genresData) ? genresData : []);
      } catch (err) {
        console.error('Failed to fetch comics:', err);
        setError('Failed to load comics. Please try again.');
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  // Load bookmarks from localStorage
  useEffect(() => {
    const saved = localStorage.getItem('bag_comics_bookmarks');
    if (saved) {
      setBookmarks(JSON.parse(saved));
    }
  }, []);

  const handleToggleBookmark = useCallback((id: string) => {
    setBookmarks(prev => {
      const next = prev.includes(id) ? prev.filter(b => b !== id) : [...prev, id];
      localStorage.setItem('bag_comics_bookmarks', JSON.stringify(next));
      return next;
    });
  }, []);

  const handleRead = async (comic: Comic) => {
    // Fetch full comic data with pages if not already loaded
    if (!comic.pages || comic.pages.length === 0) {
      try {
        const res = await api.getComic(comic.slug);
        setSelectedComic(res.data);
      } catch (err) {
        console.error('Failed to load comic:', err);
        setSelectedComic(comic);
      }
    } else {
      setSelectedComic(comic);
    }
    setView(ViewMode.READER);
    window.scrollTo(0, 0);
  };

  const navigateTo = (newView: ViewMode) => {
    setView(newView);
    setSelectedComic(null);
    setSelectedGenre(null);
    setSearchQuery('');
    window.scrollTo(0, 0);
  };

  const handleGenreClick = async (genre: string) => {
    setSelectedGenre(genre);
    setView(ViewMode.EXPLORE);
    setLoading(true);
    try {
      const res = await api.getComics({ genre, limit: 20 });
      const comicsData = res?.data?.data || res?.data || [];
      setComics(Array.isArray(comicsData) ? comicsData : []);
    } catch (err) {
      console.error('Failed to filter by genre:', err);
    } finally {
      setLoading(false);
    }
  };

  const bookmarkedComics = comics.filter(c => bookmarks.includes(c.id));

  // Loading skeleton component
  const ComicSkeleton = () => (
    <div className="animate-pulse">
      <div className="aspect-[3/4] bg-gray-800 rounded-lg mb-3" />
      <div className="h-3 bg-gray-800 rounded w-3/4 mb-2" />
      <div className="h-3 bg-gray-800 rounded w-1/2" />
    </div>
  );

  return (
    <div className="min-h-screen flex flex-col bg-[#0a0a0a]">
      {view !== ViewMode.READER && (
        <Header
          currentView={view}
          onNavigate={navigateTo}
          isLoggedIn={isLoggedIn}
          onSignIn={() => setIsLoggedIn(true)}
        />
      )}

      <main className="flex-1">
        {view === ViewMode.HOME && (
          <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            {/* Featured Section */}
            {featuredComics.length > 0 && (
              <div className="mb-12">
                <h2 className="text-xl sm:text-2xl font-semibold text-white mb-6">
                  Featured Comics
                </h2>
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                  {featuredComics.map(comic => (
                    <ComicCard
                      key={comic.id}
                      comic={comic}
                      onRead={handleRead}
                    />
                  ))}
                </div>
              </div>
            )}

            {/* Recently Added Section */}
            <div className="mb-12">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl sm:text-2xl font-semibold text-white">
                  Recently Added
                </h2>
                <button
                  onClick={() => navigateTo(ViewMode.STORE)}
                  className="text-[#DC2626] hover:text-[#F87171] text-sm font-medium transition-colors"
                >
                  View All →
                </button>
              </div>

              {/* Comic Grid */}
              {loading ? (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                  {[...Array(6)].map((_, i) => <ComicSkeleton key={i} />)}
                </div>
              ) : error ? (
                <div className="text-center py-12">
                  <p className="text-red-500 mb-4">{error}</p>
                  <button
                    onClick={() => window.location.reload()}
                    className="bg-[#DC2626] text-white px-4 py-2 rounded-lg hover:bg-[#B91C1C] transition-colors"
                  >
                    Retry
                  </button>
                </div>
              ) : comics.length === 0 ? (
                <div className="text-center py-12">
                  <p className="text-gray-400">No comics available yet. Check back soon!</p>
                </div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                  {comics.map(comic => (
                    <ComicCard
                      key={comic.id}
                      comic={comic}
                      onRead={handleRead}
                    />
                  ))}
                </div>
              )}
            </div>

            {/* Popular Genres Section */}
            <div className="mb-12">
              <h2 className="text-xl sm:text-2xl font-semibold text-white mb-6">
                Popular Genres
              </h2>
              <div className="flex flex-wrap gap-3">
                {(genres.length > 0 ? genres : ['Action', 'Fantasy', 'Sci-Fi', 'Horror', 'Mystery', 'Romance']).map(genre => (
                  <button
                    key={genre}
                    onClick={() => handleGenreClick(genre)}
                    className="px-5 py-2.5 bg-[#1a1a1a] hover:bg-[#DC2626] text-white text-sm font-medium rounded-full transition-all border border-gray-800 hover:border-[#DC2626]"
                  >
                    {genre}
                  </button>
                ))}
              </div>
            </div>

            {/* Newsletter Section */}
            <section className="bg-gradient-to-br from-[#1a1a1a] to-[#0d0d0d] p-8 sm:p-10 rounded-2xl border border-gray-800">
              <div className="flex flex-col md:flex-row items-center gap-6 justify-between">
                <div className="text-center md:text-left">
                  <h3 className="text-2xl font-bold text-white mb-2">
                    Never miss a Chapter
                  </h3>
                  <p className="text-gray-400">
                    Subscribe to get notified when new episodes are published.
                  </p>
                </div>
                <div className="flex w-full md:w-auto gap-3">
                  <input
                    type="email"
                    placeholder="Enter your email"
                    className="flex-1 md:w-72 bg-black border border-gray-700 rounded-full px-5 py-3 text-white text-sm focus:outline-none focus:border-[#DC2626]"
                  />
                  <button className="bg-[#DC2626] text-white px-6 py-3 rounded-full font-semibold text-sm hover:bg-[#B91C1C] transition-colors whitespace-nowrap">
                    Subscribe
                  </button>
                </div>
              </div>
            </section>
          </div>
        )}

        {view === ViewMode.STORE && (
          <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div className="flex items-center justify-between mb-8">
              <h1 className="text-3xl font-bold text-white">Comic Store</h1>
              <div className="flex gap-3">
                <select
                  className="bg-[#1a1a1a] border border-gray-700 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-[#DC2626]"
                  onChange={(e) => e.target.value && handleGenreClick(e.target.value)}
                  value={selectedGenre || ''}
                >
                  <option value="">All Genres</option>
                  {genres.map(genre => (
                    <option key={genre} value={genre}>{genre}</option>
                  ))}
                </select>
              </div>
            </div>

            {loading ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                {[...Array(12)].map((_, i) => <ComicSkeleton key={i} />)}
              </div>
            ) : comics.length === 0 ? (
              <div className="text-center py-16">
                <p className="text-gray-400 text-lg">No comics found in this category.</p>
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                {comics.map(comic => (
                  <ComicCard
                    key={comic.id}
                    comic={comic}
                    onRead={handleRead}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        {view === ViewMode.EXPLORE && (
          <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div className="mb-8">
              <h1 className="text-3xl font-bold text-white mb-2">
                {selectedGenre ? `${selectedGenre} Comics` : 'Explore Comics'}
              </h1>
              {selectedGenre && (
                <button
                  onClick={() => {
                    setSelectedGenre(null);
                    navigateTo(ViewMode.HOME);
                  }}
                  className="text-[#DC2626] hover:text-[#F87171] text-sm font-medium transition-colors"
                >
                  ← Back to Home
                </button>
              )}
            </div>

            {/* Genre Tags */}
            <div className="flex flex-wrap gap-3 mb-8">
              {genres.map(genre => (
                <button
                  key={genre}
                  onClick={() => handleGenreClick(genre)}
                  className={`px-4 py-2 rounded-full text-sm font-medium transition-all ${
                    selectedGenre === genre
                      ? 'bg-[#DC2626] text-white border-[#DC2626]'
                      : 'bg-[#1a1a1a] text-white border border-gray-700 hover:border-[#DC2626]'
                  }`}
                >
                  {genre}
                </button>
              ))}
            </div>

            {loading ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                {[...Array(12)].map((_, i) => <ComicSkeleton key={i} />)}
              </div>
            ) : comics.length === 0 ? (
              <div className="text-center py-16">
                <p className="text-gray-400 text-lg">No comics found. Try a different genre!</p>
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                {comics.map(comic => (
                  <ComicCard
                    key={comic.id}
                    comic={comic}
                    onRead={handleRead}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        {view === ViewMode.LIBRARY && (
          <Library bookmarks={bookmarkedComics} onRead={handleRead} />
        )}

        {view === ViewMode.READER && selectedComic && (
          <Reader
            comic={selectedComic}
            onBack={() => navigateTo(ViewMode.HOME)}
            isBookmarked={bookmarks.includes(selectedComic.id)}
            onToggleBookmark={handleToggleBookmark}
          />
        )}

        {view === ViewMode.BLOG && (
          <div className="max-w-7xl mx-auto px-4 sm:px-6 py-16 text-center">
            <h2 className="text-3xl font-bold text-white mb-4">Blog</h2>
            <p className="text-gray-400 mb-8">Stay tuned for news, creator interviews, and behind-the-scenes content.</p>
            <div className="bg-[#1a1a1a] rounded-2xl p-8 border border-gray-800 max-w-md mx-auto">
              <h3 className="text-xl font-semibold text-white mb-4">Coming Soon</h3>
              <p className="text-gray-400 text-sm">
                We're working on bringing you amazing content from the world of African comics.
              </p>
            </div>
          </div>
        )}
      </main>

      {view !== ViewMode.READER && (
        <footer className="bg-[#0d0d0d] py-12 px-4 sm:px-6 border-t border-gray-900">
          <div className="max-w-7xl mx-auto">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
              {/* Logo & Description */}
              <div className="col-span-1 md:col-span-2">
                <div className="flex items-center gap-2 mb-4">
                  <img
                    src="/images/bagcomics.jpeg"
                    alt="BAG Comics"
                    className="w-8 h-8 object-cover rounded-md"
                  />
                  <h1 className="text-xl font-bold text-white">
                    BAG<span className="font-light text-[#DC2626]">Comics</span>
                  </h1>
                </div>
                <p className="text-gray-500 text-sm leading-relaxed max-w-md">
                  Premium digital comic experience with native vertical scrolling and immersive storytelling.
                </p>
              </div>

              {/* Links */}
              <div>
                <h4 className="text-white font-semibold text-sm uppercase tracking-wider mb-4">Links</h4>
                <ul className="space-y-2">
                  <li><button onClick={() => navigateTo(ViewMode.STORE)} className="text-gray-500 hover:text-white text-sm transition-colors">Store</button></li>
                  <li><button onClick={() => navigateTo(ViewMode.EXPLORE)} className="text-gray-500 hover:text-white text-sm transition-colors">Explore</button></li>
                  <li><a href="#" className="text-gray-500 hover:text-white text-sm transition-colors">Publish with us</a></li>
                  <li><button onClick={() => navigateTo(ViewMode.BLOG)} className="text-gray-500 hover:text-white text-sm transition-colors">Blog</button></li>
                </ul>
              </div>

              {/* Social */}
              <div>
                <h4 className="text-white font-semibold text-sm uppercase tracking-wider mb-4">Connect</h4>
                <div className="flex gap-3">
                  {['instagram', 'twitter', 'facebook'].map((social, i) => (
                    <a
                      key={i}
                      href="#"
                      className="w-10 h-10 rounded-lg bg-[#1a1a1a] flex items-center justify-center text-gray-400 hover:bg-[#DC2626] hover:text-white transition-all"
                    >
                      <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        {social === 'instagram' && (
                          <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        )}
                        {social === 'twitter' && (
                          <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                        )}
                        {social === 'facebook' && (
                          <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                        )}
                      </svg>
                    </a>
                  ))}
                </div>
              </div>
            </div>

            <div className="mt-10 pt-8 border-t border-gray-800 text-center">
              <p className="text-gray-600 text-sm">
                &copy; {new Date().getFullYear()} BAG Comics. All rights reserved.
              </p>
            </div>
          </div>
        </footer>
      )}
    </div>
  );
};

export default App;
