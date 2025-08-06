import React, { useState } from 'react';
import { Sparkles, TrendingUp, Users, BookOpen, RefreshCw } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Button } from './ui/button';
import { Badge } from './ui/badge';
import { RatingStars } from './RatingStars';
import { router } from '@inertiajs/react';

interface Comic {
  id: number;
  title: string;
  author: string;
  genre: string;
  cover_image_path?: string;
  average_rating: number;
  total_ratings: number;
  price: number;
  is_free: boolean;
  similarity_score?: number;
  recommendation_reason?: string;
}

interface RecommendationSection {
  title: string;
  description: string;
  icon: React.ReactNode;
  comics: Comic[];
  type: 'personalized' | 'trending' | 'similar' | 'collaborative';
}

interface RecommendationEngineProps {
  userId?: number;
  currentComicId?: number;
  recommendations: RecommendationSection[];
  isLoading?: boolean;
  className?: string;
}

export const RecommendationEngine: React.FC<RecommendationEngineProps> = ({
  userId,
  currentComicId,
  recommendations: initialRecommendations,
  isLoading = false,
  className = ''
}) => {
  const [recommendations, setRecommendations] = useState<RecommendationSection[]>(initialRecommendations);
  const [refreshing, setRefreshing] = useState(false);
  const [activeSection, setActiveSection] = useState(0);

  const refreshRecommendations = async () => {
    setRefreshing(true);
    try {
      const response = await fetch(`/api/recommendations${userId ? `?user_id=${userId}` : ''}${currentComicId ? `&comic_id=${currentComicId}` : ''}`);
      const data = await response.json();
      setRecommendations(data.recommendations);
    } catch (error) {
      console.error('Failed to refresh recommendations:', error);
    } finally {
      setRefreshing(false);
    }
  };

  const handleComicClick = (comic: Comic) => {
    router.visit(`/comics/${comic.id}`);
  };

  const getRecommendationIcon = (type: RecommendationSection['type']) => {
    switch (type) {
      case 'personalized':
        return <Sparkles className="w-5 h-5" />;
      case 'trending':
        return <TrendingUp className="w-5 h-5" />;
      case 'similar':
        return <BookOpen className="w-5 h-5" />;
      case 'collaborative':
        return <Users className="w-5 h-5" />;
      default:
        return <Sparkles className="w-5 h-5" />;
    }
  };

  const getReasonBadgeColor = (reason: string) => {
    if (reason.includes('genre')) return 'bg-blue-100 text-blue-800';
    if (reason.includes('author')) return 'bg-green-100 text-green-800';
    if (reason.includes('rating')) return 'bg-yellow-100 text-yellow-800';
    if (reason.includes('similar')) return 'bg-purple-100 text-purple-800';
    return 'bg-gray-100 text-gray-800';
  };

  if (isLoading) {
    return (
      <div className={`space-y-6 ${className}`}>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-center">
              <RefreshCw className="w-6 h-6 animate-spin text-gray-400" />
              <span className="ml-2 text-gray-600">Loading recommendations...</span>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!recommendations.length) {
    return (
      <div className={`space-y-6 ${className}`}>
        <Card>
          <CardContent className="p-6 text-center">
            <Sparkles className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">No Recommendations Yet</h3>
            <p className="text-gray-600 mb-4">
              Start reading comics to get personalized recommendations!
            </p>
            <Button onClick={() => router.visit('/comics')}>
              Browse Comics
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold flex items-center gap-2">
            <Sparkles className="w-6 h-6 text-purple-600" />
            Recommendations
          </h2>
          <p className="text-gray-600 mt-1">Discover your next favorite comic</p>
        </div>
        
        <Button
          variant="outline"
          size="sm"
          onClick={refreshRecommendations}
          disabled={refreshing}
          className="flex items-center gap-2"
        >
          <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

      {/* Section Navigation */}
      <div className="flex space-x-1 bg-gray-100 p-1 rounded-lg overflow-x-auto">
        {recommendations.map((section, index) => (
          <button
            key={index}
            onClick={() => setActiveSection(index)}
            className={`
              flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-colors whitespace-nowrap
              ${activeSection === index 
                ? 'bg-white text-gray-900 shadow-sm' 
                : 'text-gray-600 hover:text-gray-900'
              }
            `}
          >
            {getRecommendationIcon(section.type)}
            {section.title}
          </button>
        ))}
      </div>

      {/* Active Section */}
      {recommendations[activeSection] && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              {getRecommendationIcon(recommendations[activeSection].type)}
              {recommendations[activeSection].title}
            </CardTitle>
            <p className="text-gray-600">{recommendations[activeSection].description}</p>
          </CardHeader>
          
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {recommendations[activeSection].comics.map((comic) => (
                <div
                  key={comic.id}
                  className="group cursor-pointer"
                  onClick={() => handleComicClick(comic)}
                >
                  <div className="relative overflow-hidden rounded-lg bg-gray-100 aspect-[3/4] mb-3">
                    {comic.cover_image_path ? (
                      <img
                        src={comic.cover_image_path}
                        alt={comic.title}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center">
                        <BookOpen className="w-12 h-12 text-gray-400" />
                      </div>
                    )}
                    
                    {comic.similarity_score && (
                      <div className="absolute top-2 right-2">
                        <Badge variant="secondary" className="text-xs">
                          {Math.round(comic.similarity_score * 100)}% match
                        </Badge>
                      </div>
                    )}

                    {comic.is_free && (
                      <div className="absolute top-2 left-2">
                        <Badge className="bg-green-600 text-white text-xs">
                          Free
                        </Badge>
                      </div>
                    )}
                  </div>

                  <div className="space-y-2">
                    <h3 className="font-medium text-sm line-clamp-2 group-hover:text-blue-600 transition-colors">
                      {comic.title}
                    </h3>
                    
                    <p className="text-xs text-gray-600">{comic.author}</p>
                    
                    <div className="flex items-center justify-between">
                      <RatingStars 
                        rating={comic.average_rating} 
                        size="sm" 
                      />
                      <span className="text-xs text-gray-500">
                        ({comic.total_ratings})
                      </span>
                    </div>

                    {comic.recommendation_reason && (
                      <Badge 
                        variant="outline" 
                        className={`text-xs ${getReasonBadgeColor(comic.recommendation_reason)}`}
                      >
                        {comic.recommendation_reason}
                      </Badge>
                    )}

                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium">
                        {comic.is_free ? 'Free' : `$${comic.price.toFixed(2)}`}
                      </span>
                      <Badge variant="outline" className="text-xs">
                        {comic.genre}
                      </Badge>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {recommendations[activeSection].comics.length === 0 && (
              <div className="text-center py-8 text-gray-500">
                <BookOpen className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                <p>No recommendations available for this category yet.</p>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Quick Actions */}
      <Card>
        <CardContent className="p-4">
          <div className="flex items-center justify-between">
            <div>
              <h4 className="font-medium">Want more personalized recommendations?</h4>
              <p className="text-sm text-gray-600">Rate more comics and add them to your favorites</p>
            </div>
            <div className="flex gap-2">
              <Button 
                variant="outline" 
                size="sm"
                onClick={() => router.visit('/library')}
              >
                My Library
              </Button>
              <Button 
                size="sm"
                onClick={() => router.visit('/comics')}
              >
                Browse Comics
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};