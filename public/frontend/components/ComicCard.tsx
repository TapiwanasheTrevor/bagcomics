
import React from 'react';
import { Comic } from '../types';

interface ComicCardProps {
  comic: Comic;
  onRead: (comic: Comic) => void;
}

export const ComicCard: React.FC<ComicCardProps> = ({ comic, onRead }) => {
  return (
    <div
      className="group cursor-pointer flex flex-col"
      onClick={() => onRead(comic)}
    >
      {/* Cover Image */}
      <div className="relative aspect-[3/4] overflow-hidden rounded-lg bg-gray-900 mb-3">
        <img
          src={comic.coverImage}
          alt={comic.title}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          loading="lazy"
        />
        {/* Hover overlay */}
        <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
          <div className="w-14 h-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
            <svg className="w-7 h-7 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z"/>
            </svg>
          </div>
        </div>
      </div>

      {/* Comic Info */}
      <div className="flex flex-col gap-1">
        {/* Genres */}
        <p className="text-gray-400 text-xs font-medium truncate">
          {comic.genre.join(' | ')}
        </p>

        {/* Episode Count */}
        <p className="text-[#DC2626] text-xs font-semibold">
          {comic.episodes || 1} {(comic.episodes || 1) === 1 ? 'Episode' : 'Episodes'}
        </p>

        {/* Title */}
        <h3 className="text-white text-sm font-semibold truncate leading-tight mt-0.5">
          {comic.title}
        </h3>

        {/* Star Rating */}
        <div className="flex gap-0.5 mt-1">
          {[1, 2, 3, 4, 5].map((star) => (
            <svg
              key={star}
              className={`w-3.5 h-3.5 ${
                star <= Math.round(comic.rating)
                  ? 'text-yellow-400'
                  : 'text-gray-600'
              } fill-current`}
              viewBox="0 0 20 20"
            >
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
          ))}
        </div>
      </div>
    </div>
  );
};
