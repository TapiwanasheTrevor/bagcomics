
import React, { useState } from 'react';
import { Comic } from '../types';
import { ComicCard } from './ComicCard';
import { Link } from 'react-router-dom';

interface LibraryProps {
  bookmarks: Comic[];
  onRead: (comic: Comic) => void;
}

type Tab = 'all' | 'reading' | 'favorites';

export const Library: React.FC<LibraryProps> = ({ bookmarks, onRead }) => {
  const [activeTab, setActiveTab] = useState<Tab>('all');

  const tabs: { id: Tab; label: string; icon: string }[] = [
    { id: 'all', label: 'All', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
    { id: 'reading', label: 'Reading', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { id: 'favorites', label: 'Favorites', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
  ];

  const filtered = bookmarks.filter(comic => {
    if (activeTab === 'favorites') return comic.isLiked;
    if (activeTab === 'reading') return comic.userProgress && comic.userProgress.percentage < 100;
    return true;
  });

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Header */}
      <div className="flex items-center gap-4 mb-6">
        <h2 className="text-3xl font-black italic tracking-tighter uppercase">
          My <span className="text-[#D32F2F]">Library</span>
        </h2>
        <div className="h-0.5 flex-1 bg-gradient-to-r from-[#D32F2F] to-transparent" />
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-8 border-b border-gray-800 pb-px">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`flex items-center gap-2 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px ${
              activeTab === tab.id
                ? 'text-white border-[#DC2626]'
                : 'text-gray-500 border-transparent hover:text-gray-300'
            }`}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={tab.icon} />
            </svg>
            {tab.label}
            {tab.id === 'all' && bookmarks.length > 0 && (
              <span className="bg-gray-800 text-gray-400 text-xs px-1.5 py-0.5 rounded-full">{bookmarks.length}</span>
            )}
          </button>
        ))}
      </div>

      {/* Content */}
      {bookmarks.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 bg-gray-900/50 rounded-2xl border border-dashed border-gray-800">
          <svg className="w-16 h-16 text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          <p className="text-gray-500 font-medium mb-1">Your library is empty</p>
          <p className="text-gray-600 text-sm mb-6">Browse comics and bookmark your favorites!</p>
          <Link
            to="/store"
            className="bg-[#DC2626] hover:bg-[#B91C1C] text-white px-6 py-3 rounded-xl font-semibold text-sm transition-colors"
          >
            Explore Comics
          </Link>
        </div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-16">
          <p className="text-gray-500 font-medium">
            {activeTab === 'favorites' ? 'No favorites yet. Like comics to see them here!' : 'No comics in progress.'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
          {filtered.map(comic => (
            <ComicCard key={comic.id} comic={comic} onRead={onRead} />
          ))}
        </div>
      )}
    </div>
  );
};
