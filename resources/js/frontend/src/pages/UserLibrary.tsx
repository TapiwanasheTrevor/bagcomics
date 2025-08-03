import React, { useState } from 'react';
import { Book, Clock, Star, Bookmark, Download, Play, Trash2, Filter } from 'lucide-react';
import { mockComics } from '../data/mockComics';
import type { Page } from '../App';

interface UserLibraryProps {
  onNavigate: (page: Page, comicId?: string) => void;
}

const UserLibrary: React.FC<UserLibraryProps> = ({ onNavigate }) => {
  const [activeTab, setActiveTab] = useState<'reading' | 'bookmarked' | 'purchased' | 'downloaded'>('reading');
  const [sortBy, setSortBy] = useState('recent');

  // Mock user library data
  const userLibrary = {
    reading: mockComics.filter(comic => comic.readProgress && comic.readProgress > 0 && comic.readProgress < 100),
    bookmarked: mockComics.filter(comic => comic.isBookmarked),
    purchased: mockComics.filter(comic => !comic.isFree),
    downloaded: mockComics.slice(0, 3)
  };

  const tabs = [
    { id: 'reading', label: 'Continue Reading', icon: Clock, count: userLibrary.reading.length },
    { id: 'bookmarked', label: 'Bookmarked', icon: Bookmark, count: userLibrary.bookmarked.length },
    { id: 'purchased', label: 'Purchased', icon: Book, count: userLibrary.purchased.length },
    { id: 'downloaded', label: 'Downloaded', icon: Download, count: userLibrary.downloaded.length },
  ];

  const getCurrentTabData = () => {
    return userLibrary[activeTab] || [];
  };

  const ComicLibraryCard: React.FC<{ comic: any; showProgress?: boolean }> = ({ comic, showProgress = false }) => (
    <div className="flex bg-gray-800 rounded-xl overflow-hidden border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 group">
      <div className="relative w-32 h-48 flex-shrink-0">
        <img 
          src={comic.coverImage} 
          alt={comic.title}
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
        />
        {showProgress && comic.readProgress && (
          <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2">
            <div className="w-full bg-gray-600 rounded-full h-1.5">
              <div 
                className="bg-gradient-to-r from-emerald-500 to-purple-500 h-1.5 rounded-full transition-all duration-300"
                style={{ width: `${comic.readProgress}%` }}
              />
            </div>
            <span className="text-xs text-white font-medium">{comic.readProgress}%</span>
          </div>
        )}
        
        {comic.isFree && (
          <span className="absolute top-2 left-2 bg-emerald-500 text-xs px-2 py-1 rounded-full font-semibold">FREE</span>
        )}
      </div>
      
      <div className="flex-1 p-4 flex flex-col justify-between">
        <div>
          <div className="flex items-start justify-between mb-2">
            <div>
              <h3 className="font-bold text-lg mb-1 group-hover:text-emerald-400 transition-colors cursor-pointer" 
                  onClick={() => onNavigate('detail', comic.id)}>
                {comic.title}
              </h3>
              <p className="text-gray-400 text-sm">{comic.creator}</p>
            </div>
            <div className="flex items-center space-x-1">
              <Star className="w-4 h-4 text-yellow-400 fill-current" />
              <span className="text-sm text-gray-300">{comic.rating}</span>
            </div>
          </div>
          
          <p className="text-gray-300 text-sm mb-3 line-clamp-2">{comic.description}</p>
          
          <div className="flex flex-wrap gap-1 mb-3">
            {comic.genre.slice(0, 2).map((g: string) => (
              <span key={g} className="bg-gray-700 text-xs px-2 py-1 rounded-full">{g}</span>
            ))}
          </div>
        </div>
        
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <button 
              onClick={() => onNavigate('reader', comic.id)}
              className="flex items-center space-x-1 px-3 py-2 bg-emerald-500 hover:bg-emerald-600 rounded-lg transition-colors text-sm font-medium"
            >
              <Play className="w-4 h-4" />
              <span>{showProgress && comic.readProgress ? 'Continue' : 'Read'}</span>
            </button>
            
            <button className="p-2 text-gray-400 hover:text-red-400 transition-colors">
              <Trash2 className="w-4 h-4" />
            </button>
          </div>
          
          <div className="text-right text-sm text-gray-400">
            <div>{comic.episodeCount} episodes</div>
            {showProgress && comic.readProgress && (
              <div className="text-emerald-400">
                {Math.round((comic.readProgress / 100) * comic.episodeCount)} / {comic.episodeCount} read
              </div>
            )}
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
            My Library
          </h1>
          <p className="text-gray-300 text-lg">
            Your personal collection of African stories
          </p>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div className="bg-gradient-to-r from-emerald-500/20 to-emerald-600/20 border border-emerald-500/30 rounded-xl p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-emerald-400 text-sm font-medium">Comics Read</p>
                <p className="text-2xl font-bold text-white">24</p>
              </div>
              <Book className="w-8 h-8 text-emerald-400" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-purple-500/20 to-purple-600/20 border border-purple-500/30 rounded-xl p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-purple-400 text-sm font-medium">Hours Read</p>
                <p className="text-2xl font-bold text-white">156</p>
              </div>
              <Clock className="w-8 h-8 text-purple-400" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-orange-500/20 to-orange-600/20 border border-orange-500/30 rounded-xl p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-orange-400 text-sm font-medium">Bookmarks</p>
                <p className="text-2xl font-bold text-white">{userLibrary.bookmarked.length}</p>
              </div>
              <Bookmark className="w-8 h-8 text-orange-400" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-xl p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-blue-400 text-sm font-medium">Streak</p>
                <p className="text-2xl font-bold text-white">12 days</p>
              </div>
              <Star className="w-8 h-8 text-blue-400" />
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="mb-8">
          <div className="flex flex-wrap gap-2 mb-6">
            {tabs.map(({ id, label, icon: Icon, count }) => (
              <button
                key={id}
                onClick={() => setActiveTab(id as any)}
                className={`flex items-center space-x-2 px-4 py-3 rounded-lg font-medium transition-all duration-300 ${
                  activeTab === id
                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                    : 'text-gray-300 hover:text-white hover:bg-gray-700/50'
                }`}
              >
                <Icon className="w-5 h-5" />
                <span>{label}</span>
                <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                  activeTab === id ? 'bg-emerald-500/30' : 'bg-gray-600'
                }`}>
                  {count}
                </span>
              </button>
            ))}
          </div>

          {/* Controls */}
          <div className="flex items-center justify-between">
            <div className="text-gray-400">
              Showing {getCurrentTabData().length} comics
            </div>
            
            <div className="flex items-center space-x-4">
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500"
              >
                <option value="recent">Recently Accessed</option>
                <option value="title">Title A-Z</option>
                <option value="rating">Highest Rated</option>
                <option value="progress">Most Progress</option>
              </select>
              
              <button className="flex items-center space-x-2 px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg hover:border-gray-500 transition-colors">
                <Filter className="w-4 h-4" />
                <span>Filter</span>
              </button>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="space-y-4">
          {getCurrentTabData().length > 0 ? (
            getCurrentTabData().map((comic) => (
              <ComicLibraryCard 
                key={comic.id} 
                comic={comic} 
                showProgress={activeTab === 'reading'}
              />
            ))
          ) : (
            <div className="text-center py-16">
              <div className="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                {tabs.find(tab => tab.id === activeTab)?.icon && 
                  React.createElement(tabs.find(tab => tab.id === activeTab)!.icon, { 
                    className: "w-8 h-8 text-gray-400" 
                  })
                }
              </div>
              <h3 className="text-xl font-semibold text-gray-300 mb-2">
                No comics in {tabs.find(tab => tab.id === activeTab)?.label.toLowerCase()}
              </h3>
              <p className="text-gray-500 mb-6">
                {activeTab === 'reading' && "Start reading some comics to see them here"}
                {activeTab === 'bookmarked' && "Bookmark comics you want to read later"}
                {activeTab === 'purchased' && "Purchase comics to build your collection"}
                {activeTab === 'downloaded' && "Download comics for offline reading"}
              </p>
              <button 
                onClick={() => onNavigate('catalogue')}
                className="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 rounded-lg font-semibold transition-colors"
              >
                Browse Comics
              </button>
            </div>
          )}
        </div>

        {/* Reading Goal */}
        <div className="mt-16 bg-gradient-to-r from-gray-800 to-gray-900 rounded-2xl p-8">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h3 className="text-2xl font-bold mb-2">Reading Goal 2024</h3>
              <p className="text-gray-400">You're doing great! Keep it up.</p>
            </div>
            <div className="text-right">
              <div className="text-3xl font-bold text-emerald-400">24/50</div>
              <div className="text-sm text-gray-400">Comics read</div>
            </div>
          </div>
          
          <div className="w-full bg-gray-700 rounded-full h-3 mb-4">
            <div 
              className="bg-gradient-to-r from-emerald-500 to-purple-500 h-3 rounded-full transition-all duration-1000"
              style={{ width: '48%' }}
            />
          </div>
          
          <div className="flex justify-between text-sm text-gray-400">
            <span>48% complete</span>
            <span>26 comics to go</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default UserLibrary;