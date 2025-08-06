import React, { useState } from 'react';
import { RatingStars } from './RatingStars';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';
import { Input } from './ui/input';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Badge } from './ui/badge';
import { ThumbsUp, ThumbsDown, Flag, User } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Review {
  id: number;
  user: {
    id: number;
    name: string;
    avatar_path?: string;
  };
  rating: number;
  title: string;
  content: string;
  is_spoiler: boolean;
  helpful_votes: number;
  total_votes: number;
  created_at: string;
  user_vote?: 'helpful' | 'not_helpful' | null;
}

interface ReviewSectionProps {
  comicId: number;
  reviews: Review[];
  averageRating: number;
  totalReviews: number;
  userCanReview: boolean;
  userReview?: Review;
  className?: string;
}

export const ReviewSection: React.FC<ReviewSectionProps> = ({
  comicId,
  reviews,
  averageRating,
  totalReviews,
  userCanReview,
  userReview,
  className = ''
}) => {
  const [showReviewForm, setShowReviewForm] = useState(false);
  const [reviewForm, setReviewForm] = useState({
    rating: 0,
    title: '',
    content: '',
    is_spoiler: false
  });
  const [sortBy, setSortBy] = useState<'newest' | 'oldest' | 'rating' | 'helpful'>('newest');
  const [showSpoilers, setShowSpoilers] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmitReview = async (e: React.FormEvent) => {
    e.preventDefault();
    if (reviewForm.rating === 0) return;

    setSubmitting(true);
    try {
      await router.post(`/api/comics/${comicId}/reviews`, reviewForm);
      setShowReviewForm(false);
      setReviewForm({ rating: 0, title: '', content: '', is_spoiler: false });
    } catch (error) {
      console.error('Failed to submit review:', error);
    } finally {
      setSubmitting(false);
    }
  };

  const handleVoteOnReview = async (reviewId: number, helpful: boolean) => {
    try {
      await router.post(`/api/reviews/${reviewId}/vote`, { helpful });
    } catch (error) {
      console.error('Failed to vote on review:', error);
    }
  };

  const handleReportReview = async (reviewId: number) => {
    try {
      await router.post(`/api/reviews/${reviewId}/report`);
    } catch (error) {
      console.error('Failed to report review:', error);
    }
  };

  const sortedReviews = [...reviews].sort((a, b) => {
    switch (sortBy) {
      case 'newest':
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
      case 'oldest':
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
      case 'rating':
        return b.rating - a.rating;
      case 'helpful':
        return (b.helpful_votes / Math.max(b.total_votes, 1)) - (a.helpful_votes / Math.max(a.total_votes, 1));
      default:
        return 0;
    }
  });

  const filteredReviews = sortedReviews.filter(review => 
    showSpoilers || !review.is_spoiler
  );

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Review Summary */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <span>Reviews & Ratings</span>
            <div className="flex items-center gap-2">
              <RatingStars rating={averageRating} showValue />
              <span className="text-sm text-gray-600">({totalReviews} reviews)</span>
            </div>
          </CardTitle>
        </CardHeader>
        <CardContent>
          {userCanReview && !userReview && (
            <Button 
              onClick={() => setShowReviewForm(!showReviewForm)}
              className="mb-4"
            >
              Write a Review
            </Button>
          )}

          {/* Review Form */}
          {showReviewForm && (
            <form onSubmit={handleSubmitReview} className="space-y-4 p-4 border rounded-lg mb-6">
              <div>
                <label className="block text-sm font-medium mb-2">Your Rating</label>
                <RatingStars
                  rating={reviewForm.rating}
                  interactive
                  onRatingChange={(rating) => setReviewForm(prev => ({ ...prev, rating }))}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium mb-2">Review Title</label>
                <Input
                  value={reviewForm.title}
                  onChange={(e) => setReviewForm(prev => ({ ...prev, title: e.target.value }))}
                  placeholder="Summarize your review"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Review Content</label>
                <Textarea
                  value={reviewForm.content}
                  onChange={(e) => setReviewForm(prev => ({ ...prev, content: e.target.value }))}
                  placeholder="Share your thoughts about this comic..."
                  rows={4}
                  required
                />
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="spoiler-warning"
                  checked={reviewForm.is_spoiler}
                  onChange={(e) => setReviewForm(prev => ({ ...prev, is_spoiler: e.target.checked }))}
                />
                <label htmlFor="spoiler-warning" className="text-sm">
                  This review contains spoilers
                </label>
              </div>

              <div className="flex gap-2">
                <Button type="submit" disabled={submitting || reviewForm.rating === 0}>
                  {submitting ? 'Submitting...' : 'Submit Review'}
                </Button>
                <Button 
                  type="button" 
                  variant="outline" 
                  onClick={() => setShowReviewForm(false)}
                >
                  Cancel
                </Button>
              </div>
            </form>
          )}
        </CardContent>
      </Card>

      {/* Review Controls */}
      {reviews.length > 0 && (
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="border rounded px-3 py-1 text-sm"
            >
              <option value="newest">Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="rating">Highest Rated</option>
              <option value="helpful">Most Helpful</option>
            </select>
            
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={showSpoilers}
                onChange={(e) => setShowSpoilers(e.target.checked)}
              />
              Show spoiler reviews
            </label>
          </div>
        </div>
      )}

      {/* Reviews List */}
      <div className="space-y-4">
        {filteredReviews.map((review) => (
          <Card key={review.id} className="relative">
            {review.is_spoiler && (
              <Badge variant="destructive" className="absolute top-2 right-2">
                Spoiler
              </Badge>
            )}
            
            <CardHeader className="pb-3">
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  {review.user.avatar_path ? (
                    <img
                      src={review.user.avatar_path}
                      alt={review.user.name}
                      className="w-10 h-10 rounded-full"
                    />
                  ) : (
                    <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                      <User className="w-5 h-5 text-gray-500" />
                    </div>
                  )}
                  <div>
                    <h4 className="font-medium">{review.user.name}</h4>
                    <div className="flex items-center gap-2">
                      <RatingStars rating={review.rating} size="sm" />
                      <span className="text-sm text-gray-500">
                        {new Date(review.created_at).toLocaleDateString()}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </CardHeader>

            <CardContent>
              <h5 className="font-medium mb-2">{review.title}</h5>
              <p className="text-gray-700 mb-4">{review.content}</p>
              
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <button
                    onClick={() => handleVoteOnReview(review.id, true)}
                    className={`flex items-center gap-1 text-sm ${
                      review.user_vote === 'helpful' 
                        ? 'text-green-600' 
                        : 'text-gray-500 hover:text-green-600'
                    }`}
                  >
                    <ThumbsUp className="w-4 h-4" />
                    Helpful ({review.helpful_votes})
                  </button>
                  
                  <button
                    onClick={() => handleVoteOnReview(review.id, false)}
                    className={`flex items-center gap-1 text-sm ${
                      review.user_vote === 'not_helpful' 
                        ? 'text-red-600' 
                        : 'text-gray-500 hover:text-red-600'
                    }`}
                  >
                    <ThumbsDown className="w-4 h-4" />
                    Not Helpful
                  </button>
                </div>
                
                <button
                  onClick={() => handleReportReview(review.id)}
                  className="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600"
                >
                  <Flag className="w-4 h-4" />
                  Report
                </button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {filteredReviews.length === 0 && reviews.length > 0 && (
        <div className="text-center py-8 text-gray-500">
          No reviews to show. Try enabling spoiler reviews.
        </div>
      )}

      {reviews.length === 0 && (
        <div className="text-center py-8 text-gray-500">
          No reviews yet. Be the first to review this comic!
        </div>
      )}
    </div>
  );
};