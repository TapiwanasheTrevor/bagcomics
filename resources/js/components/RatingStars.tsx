import React, { useState } from 'react';
import { Star } from 'lucide-react';

interface RatingStarsProps {
  rating: number;
  maxRating?: number;
  size?: 'sm' | 'md' | 'lg';
  interactive?: boolean;
  showValue?: boolean;
  onRatingChange?: (rating: number) => void;
  className?: string;
}

export const RatingStars: React.FC<RatingStarsProps> = ({
  rating,
  maxRating = 5,
  size = 'md',
  interactive = false,
  showValue = false,
  onRatingChange,
  className = ''
}) => {
  const [hoverRating, setHoverRating] = useState<number | null>(null);

  const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6'
  };

  const handleStarClick = (starRating: number) => {
    if (interactive && onRatingChange) {
      onRatingChange(starRating);
    }
  };

  const handleStarHover = (starRating: number) => {
    if (interactive) {
      setHoverRating(starRating);
    }
  };

  const handleMouseLeave = () => {
    if (interactive) {
      setHoverRating(null);
    }
  };

  const getStarFill = (starIndex: number) => {
    const currentRating = hoverRating ?? rating;
    if (starIndex <= currentRating) {
      return 'fill-yellow-400 text-yellow-400';
    }
    return 'fill-gray-200 text-gray-200';
  };

  return (
    <div className={`flex items-center gap-1 ${className}`}>
      <div 
        className="flex items-center gap-0.5"
        onMouseLeave={handleMouseLeave}
      >
        {Array.from({ length: maxRating }, (_, index) => {
          const starRating = index + 1;
          return (
            <Star
              key={starRating}
              className={`
                ${sizeClasses[size]} 
                ${getStarFill(starRating)}
                ${interactive ? 'cursor-pointer hover:scale-110 transition-transform' : ''}
              `}
              onClick={() => handleStarClick(starRating)}
              onMouseEnter={() => handleStarHover(starRating)}
            />
          );
        })}
      </div>
      {showValue && (
        <span className="text-sm text-gray-600 ml-1">
          {rating.toFixed(1)}
        </span>
      )}
    </div>
  );
};