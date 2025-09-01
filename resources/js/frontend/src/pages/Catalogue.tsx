import React, { useState, useMemo } from 'react';
import { Filter, Grid, List, Star, Play, Bookmark, Search } from 'lucide-react';
import { mockComics } from '../data/mockComics';
import type { Page } from '../App';

interface CatalogueProps {
  onNavigate: (page: Page, comicId?: string) => void;
}

const Catalogue: React.FC<CatalogueProps> = ({ onNavigate }) => {
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [sortBy, setSortBy] = useState('newest');
  const [filterGenre, setFilterGenre] = useState('all');
  const [filterPrice, setFilterPrice] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [showFilters, setShowFilters] = useState(false);

  const genres = ['all', ...Array.from(new Set(mockComics.flatMap(comic => comic.genre)))];

  const filteredAndSortedComics = useMemo(() => {
    const filtered = mockComics.filter(comic => {
      const matchesSearch = comic.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                           comic.creator.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesGenre = filterGenre === 'all' || comic.genre.includes(filterGenre);
      const matchesPrice = filterPrice === 'all' || 
                          (filterPrice === 'free' && comic.isFree) ||
                          (filterPrice === 'paid' && !comic.isFree);
      
      return matchesSearch && matchesGenre && matchesPrice;
    });

    return filtered.sort((a, b) => {
      switch (sortBy) {
        case 'newest':
          return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
        case 'rating':
          return b.rating - a.rating;
        case 'title':
          return a.title.localeCompare(b.title);
        case 'episodes':
          return b.episodeCount - a.episodeCount;
        default:
          return 0;
      }
    });
  }, [searchQuery, filterGenre, filterPrice, sortBy]);

  const ComicGridCard: React.FC<{ comic: any }> = ({ comic }) => (
    <div 
      className="group cursor-pointer bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/20"
      onClick={() => onNavigate('detail', comic.id)}
    >
      <div className="relative">
        <img 
          src={comic.coverImage} 
          alt={comic.title}
          className="w-full h-64 object-cover group-hover:scale-110 transition-transform duration-500"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
        
        {/* Badges */}
        <div className="absolute top-3 left-3 flex flex-col gap-1">
          {comic.isFree && (
            <span className="bg-emerald-500 text-xs px-2 py-1 rounded-full font-semibold">FREE</span>
          )}
          {comic.isNew && (
            <span className="bg-orange-500 text-xs px-2 py-1 rounded-full font-semibold">NEW</span>
          )}
        </div>

        {/* Action Buttons */}
        <div className="absolute top-3 right-3 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
          <button 
            className="p-2 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
            onClick={(e) => {
              e.stopPropagation();
              onNavigate('reader', comic.id);
            }}
          >
            <Play className="w-4 h-4" />
          </button>
          <button 
            className="p-2 bg-gray-600 hover:bg-gray-500 rounded-full transition-colors"
            onClick={(e) => e.stopPropagation()}
          >
            <Bookmark className="w-4 h-4" />
          </button>
        </div>
      </div>
      
      <div className="p-4">
        <h3 className="font-bold text-lg mb-1 line-clamp-1">{comic.title}</h3>
        <p className="text-gray-400 text-sm mb-2">{comic.creator}</p>
        <p className="text-gray-300 text-sm mb-3 line-clamp-2">{comic.description}</p>
        
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-1">
            <Star className="w-4 h-4 text-yellow-400 fill-current" />
            <span className="text-sm text-gray-300">{comic.rating}</span>
            <span className="text-gray-500 text-sm">({comic.episodeCount} episodes)</span>
          </div>
          <div className="text-emerald-400 font-semibold">
            {comic.isFree ? 'FREE' : `$${comic.price}`}
          </div>
        </div>
      </div>
    </div>
  );

  const ComicListCard: React.FC<{ comic: any }> = ({ comic }) => (
    <div 
      className="flex bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 cursor-pointer hover:shadow-lg hover:shadow-emerald-500/10"
      onClick={() => onNavigate('detail', comic.id)}
    >
      <div className="relative w-24 h-36 flex-shrink-0">
        <img 
          src={comic.coverImage} 
          alt={comic.title}
          className="w-full h-full object-cover"
        />
        {comic.isFree && (
          <span className="absolute top-1 left-1 bg-emerald-500 text-xs px-1.5 py-0.5 rounded-full font-semibold">FREE</span>
        )}
      </div>
      
      <div className="flex-1 p-4 flex flex-col justify-between">
        <div>
          <div className="flex items-start justify-between mb-2">
            <div>
              <h3 className="font-bold text-lg mb-1">{comic.title}</h3>
              <p className="text-gray-400 text-sm">{comic.creator}</p>
            </div>
            <div className="flex items-center space-x-2">
              <button 
                className="p-2 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  onNavigate('reader', comic.id);
                }}
              >
                <Play className="w-4 h-4" />
              </button>
              <button 
                className="p-2 bg-gray-600 hover:bg-gray-500 rounded-full transition-colors"
                onClick={(e) => e.stopPropagation()}
              >
                <Bookmark className="w-4 h-4" />
              </button>
            </div>
          </div>
          <p className="text-gray-300 text-sm mb-3 line-clamp-2">{comic.description}</p>
        </div>
        
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <div className="flex items-center space-x-1">
              <Star className="w-4 h-4 text-yellow-400 fill-current" />
              <span className="text-sm text-gray-300">{comic.rating}</span>
            </div>
            <span className="text-gray-500 text-sm">{comic.episodeCount} episodes</span>
            <div className="flex flex-wrap gap-1">
              {comic.genre.slice(0, 2).map((g: string) => (
                <span key={g} className="bg-gray-700 text-xs px-2 py-1 rounded-full">{g}</span>
              ))}
            </div>
          </div>
          <div className="text-emerald-400 font-semibold">
            {comic.isFree ? 'FREE' : `$${comic.price}`}
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold mb-4 bg-gradient-to-r from-emerald-400 to-purple-400 bg-clip-text text-transparent">
            Explore Comics
          </h1>
          <p className="text-gray-300 text-lg">
            Discover amazing African stories from our diverse collection
          </p>
        </div>

        {/* Search and Controls */}
        <div className="mb-8 space-y-4">
          {/* Search Bar */}
          <div className="relative max-w-md">
            <Search className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
            <input
              type="text"
              placeholder="Search comics or creators..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-gray-800 border border-gray-600 rounded-lg pl-10 pr-4 py-3 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
            />
          </div>

          {/* Controls */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center space-x-2 px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg hover:border-gray-500 transition-colors"
              >
                <Filter className="w-4 h-4" />
                <span>Filters</span>
              </button>
              
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500"
              >
                <option value="newest">Newest</option>
                <option value="rating">Highest Rated</option>
                <option value="title">Title A-Z</option>
                <option value="episodes">Most Episodes</option>
              </select>
            </div>

            <div className="flex items-center space-x-2">
              <button
                onClick={() => setViewMode('grid')}
                className={`p-2 rounded-lg transition-colors ${
                  viewMode === 'grid' 
                    ? 'bg-emerald-500 text-white' 
                    : 'bg-gray-800 text-gray-300 hover:text-white'
                }`}
              >
                <Grid className="w-5 h-5" />
              </button>
              <button
                onClick={() => setViewMode('list')}
                className={`p-2 rounded-lg transition-colors ${
                  viewMode === 'list' 
                    ? 'bg-emerald-500 text-white' 
                    : 'bg-gray-800 text-gray-300 hover:text-white'
                }`}
              >
                <List className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Filters */}
          {showFilters && (
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Genre</label>
                  <select
                    value={filterGenre}
                    onChange={(e) => setFilterGenre(e.target.value)}
                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
                  >
                    {genres.map(genre => (
                      <option key={genre} value={genre}>
                        {genre === 'all' ? 'All Genres' : genre}
                      </option>
                    ))}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-300 mb-2">Price</label>
                  <select
                    value={filterPrice}
                    onChange={(e) => setFilterPrice(e.target.value)}
                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
                  >
                    <option value="all">All Prices</option>
                    <option value="free">Free Only</option>
                    <option value="paid">Paid Only</option>
                  </select>
                </div>
                
                <div className="flex items-end">
                  <button
                    onClick={() => {
                      setFilterGenre('all');
                      setFilterPrice('all');
                      setSearchQuery('');
                    }}
                    className="w-full px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg transition-colors"
                  >
                    Clear Filters
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Results */}
        <div className="mb-6">
          <p className="text-gray-400">
            Showing {filteredAndSortedComics.length} comics
          </p>
        </div>

        {/* Comics Grid/List */}
        {viewMode === 'grid' ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {filteredAndSortedComics.map((comic) => (
              <ComicGridCard key={comic.id} comic={comic} />
            ))}
          </div>
        ) : (
          <div className="space-y-4">
            {filteredAndSortedComics.map((comic) => (
              <ComicListCard key={comic.id} comic={comic} />
            ))}
          </div>
        )}

        {filteredAndSortedComics.length === 0 && (
          <div className="text-center py-16">
            <p className="text-xl text-gray-400 mb-4">No comics found</p>
            <p className="text-gray-500">Try adjusting your search or filters</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default Catalogue;