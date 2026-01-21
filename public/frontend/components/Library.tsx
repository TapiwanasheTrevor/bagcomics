
import React from 'react';
import { Comic } from '../types';
import { ComicCard } from './ComicCard';

interface LibraryProps {
  bookmarks: Comic[];
  onRead: (comic: Comic) => void;
}

export const Library: React.FC<LibraryProps> = ({ bookmarks, onRead }) => {
  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-4 mb-8">
        <h2 className="text-3xl font-black italic tracking-tighter uppercase">My <span className="text-[#D32F2F]">Library</span></h2>
        <div className="h-0.5 flex-1 bg-gradient-to-r from-[#D32F2F] to-transparent"></div>
      </div>

      {bookmarks.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 bg-gray-900/50 rounded-2xl border border-dashed border-gray-800">
           <svg className="w-16 h-16 text-gray-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          <p className="text-gray-500 font-medium">Your library is currently empty.</p>
          <p className="text-gray-600 text-sm mt-1">Start exploring and bookmark your favorite comics!</p>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
          {bookmarks.map(comic => (
            <ComicCard key={comic.id} comic={comic} onRead={onRead} />
          ))}
        </div>
      )}
    </div>
  );
};
