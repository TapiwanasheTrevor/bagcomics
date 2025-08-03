import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, Star, Play, Bookmark, TrendingUp, Clock, Gift } from 'lucide-react';
import { mockComics, getTrendingComics, getNewComics, getFreeComics } from '../data/mockComics';
import type { Page } from '../App';

interface HomepageProps {
  onNavigate: (page: Page, comicId?: string) => void;
}

const Homepage: React.FC<HomepageProps> = ({ onNavigate }) => {
  const [currentSlide, setCurrentSlide] = useState(0);
  const [featuredComics] = useState(getTrendingComics().slice(0, 3));

  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % featuredComics.length);
    }, 5000);
    return () => clearInterval(interval);
  }, [featuredComics.length]);

  const ComicCard: React.FC<{ comic: any; size?: 'small' | 'medium' | 'large' }> = ({ 
    comic, 
    size = 'medium' 
  }) => {
    const sizeClasses = {
      small: 'w-32 h-48',
      medium: 'w-40 h-60',
      large: 'w-48 h-72'
    };

    return (
      <div 
        className={`${sizeClasses[size]} group cursor-pointer relative overflow-hidden rounded-xl bg-gray-800 border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/20`}
        onClick={() => onNavigate('detail', comic.id)}
      >
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent z-10" />
        <img 
          src={comic.coverImage} 
          alt={comic.title}
          className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
        />
        
        {/* Badges */}
        <div className="absolute top-2 left-2 z-20 flex flex-col gap-1">
          {comic.isFree && (
            <span className="bg-emerald-500 text-xs px-2 py-1 rounded-full font-semibold">FREE</span>
          )}
          {comic.isNew && (
            <span className="bg-orange-500 text-xs px-2 py-1 rounded-full font-semibold">NEW</span>
          )}
        </div>

        {/* Content */}
        <div className="absolute bottom-0 left-0 right-0 p-3 z-20">
          <h3 className="font-bold text-sm mb-1 line-clamp-2">{comic.title}</h3>
          <p className="text-xs text-gray-300 mb-2">{comic.creator}</p>
          
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-1">
              <Star className="w-3 h-3 text-yellow-400 fill-current" />
              <span className="text-xs text-gray-300">{comic.rating}</span>
            </div>
            <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button 
                className="p-1.5 bg-emerald-500 hover:bg-emerald-600 rounded-full transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  onNavigate('reader', comic.id);
                }}
              >
                <Play className="w-3 h-3" />
              </button>
              <button 
                className="p-1.5 bg-gray-600 hover:bg-gray-500 rounded-full transition-colors"
                onClick={(e) => e.stopPropagation()}
              >
                <Bookmark className="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section className="relative h-[70vh] overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent z-10" />
        
        {featuredComics.map((comic, index) => (
          <div
            key={comic.id}
            className={`absolute inset-0 transition-opacity duration-1000 ${
              index === currentSlide ? 'opacity-100' : 'opacity-0'
            }`}
          >
            <img
              src={comic.coverImage}
              alt={comic.title}
              className="w-full h-full object-cover"
            />
          </div>
        ))}

        <div className="relative z-20 h-full flex items-center">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="max-w-2xl">
              <h1 className="text-5xl md:text-7xl font-bold mb-6 bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
                African Stories, Boldly Told
              </h1>
              <p className="text-xl text-gray-200 mb-8 leading-relaxed">
                Discover epic tales from the motherland. Heroes, folklore, and futuristic adventures 
                await in our collection of African comics.
              </p>
              
              <div className="flex flex-col sm:flex-row gap-4">
                <button 
                  onClick={() => onNavigate('catalogue')}
                  className="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-emerald-500/25"
                >
                  Explore Comics
                </button>
                <button 
                  onClick={() => onNavigate('reader', featuredComics[currentSlide].id)}
                  className="border-2 border-purple-500 text-purple-400 hover:bg-purple-500 hover:text-white px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 hover:scale-105"
                >
                  Read Featured
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Slide Navigation */}
        <div className="absolute bottom-8 left-1/2 transform -translate-x-1/2 z-20 flex space-x-2">
          {featuredComics.map((_, index) => (
            <button
              key={index}
              onClick={() => setCurrentSlide(index)}
              className={`w-3 h-3 rounded-full transition-all duration-300 ${
                index === currentSlide 
                  ? 'bg-emerald-500 w-8' 
                  : 'bg-gray-500 hover:bg-gray-400'
              }`}
            />
          ))}
        </div>

        {/* Navigation Arrows */}
        <button
          onClick={() => setCurrentSlide((prev) => prev === 0 ? featuredComics.length - 1 : prev - 1)}
          className="absolute left-4 top-1/2 transform -translate-y-1/2 z-20 p-3 bg-black/50 hover:bg-black/70 rounded-full transition-colors"
        >
          <ChevronLeft className="w-6 h-6" />
        </button>
        <button
          onClick={() => setCurrentSlide((prev) => (prev + 1) % featuredComics.length)}
          className="absolute right-4 top-1/2 transform -translate-y-1/2 z-20 p-3 bg-black/50 hover:bg-black/70 rounded-full transition-colors"
        >
          <ChevronRight className="w-6 h-6" />
        </button>
      </section>

      {/* Content Sections */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">
        
        {/* Trending Comics */}
        <section>
          <div className="flex items-center justify-between mb-8">
            <div className="flex items-center space-x-3">
              <TrendingUp className="w-6 h-6 text-emerald-500" />
              <h2 className="text-3xl font-bold">Trending Now</h2>
            </div>
            <button 
              onClick={() => onNavigate('catalogue')}
              className="text-emerald-400 hover:text-emerald-300 font-semibold"
            >
              View All
            </button>
          </div>
          
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            {getTrendingComics().map((comic) => (
              <ComicCard key={comic.id} comic={comic} size="medium" />
            ))}
          </div>
        </section>

        {/* New Releases */}
        <section>
          <div className="flex items-center justify-between mb-8">
            <div className="flex items-center space-x-3">
              <Clock className="w-6 h-6 text-orange-500" />
              <h2 className="text-3xl font-bold">New Releases</h2>
            </div>
            <button 
              onClick={() => onNavigate('catalogue')}
              className="text-orange-400 hover:text-orange-300 font-semibold"
            >
              View All
            </button>
          </div>
          
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            {getNewComics().map((comic) => (
              <ComicCard key={comic.id} comic={comic} size="medium" />
            ))}
          </div>
        </section>

        {/* Free Comics */}
        <section>
          <div className="flex items-center justify-between mb-8">
            <div className="flex items-center space-x-3">
              <Gift className="w-6 h-6 text-purple-500" />
              <h2 className="text-3xl font-bold">Free Comics</h2>
            </div>
            <button 
              onClick={() => onNavigate('catalogue')}
              className="text-purple-400 hover:text-purple-300 font-semibold"
            >
              View All
            </button>
          </div>
          
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            {getFreeComics().map((comic) => (
              <ComicCard key={comic.id} comic={comic} size="medium" />
            ))}
          </div>
        </section>

        {/* Call to Action */}
        <section className="bg-gradient-to-r from-gray-800 to-gray-900 rounded-2xl p-12 text-center">
          <h3 className="text-4xl font-bold mb-4 bg-gradient-to-r from-emerald-400 to-purple-400 bg-clip-text text-transparent">
            Start Your African Adventure
          </h3>
          <p className="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
            Join thousands of readers exploring the rich tapestry of African storytelling. 
            From ancient myths to futuristic tales, your next great adventure awaits.
          </p>
          <button 
            onClick={() => onNavigate('catalogue')}
            className="bg-gradient-to-r from-emerald-500 to-purple-500 hover:from-emerald-600 hover:to-purple-600 px-10 py-4 rounded-xl font-semibold text-lg transition-all duration-300 hover:scale-105 hover:shadow-xl hover:shadow-emerald-500/25"
          >
            Browse Collection
          </button>
        </section>
      </div>
    </div>
  );
};

export default Homepage;