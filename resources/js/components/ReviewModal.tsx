import React, { useState, useEffect } from 'react';
import { X, Star, AlertTriangle } from 'lucide-react';

interface Comic {
    id: number;
    slug: string;
    title: string;
    author?: string;
}

interface Review {
    id: number;
    user: {
        id: number;
        name: string;
    };
    rating: number;
    title?: string;
    content: string;
    is_spoiler: boolean;
    created_at: string;
    updated_at: string;
}

interface ReviewModalProps {
    comic: Comic;
    isOpen: boolean;
    onClose: () => void;
    existingReview?: Review | null;
    onReviewSubmitted: () => void;
}

export default function ReviewModal({ 
    comic, 
    isOpen, 
    onClose, 
    existingReview,
    onReviewSubmitted 
}: ReviewModalProps) {
    const [rating, setRating] = useState(existingReview?.rating || 0);
    const [hoverRating, setHoverRating] = useState(0);
    const [title, setTitle] = useState(existingReview?.title || '');
    const [content, setContent] = useState(existingReview?.content || '');
    const [isSpoiler, setIsSpoiler] = useState(existingReview?.is_spoiler || false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (existingReview) {
            setRating(existingReview.rating);
            setTitle(existingReview.title || '');
            setContent(existingReview.content);
            setIsSpoiler(existingReview.is_spoiler);
        } else {
            setRating(0);
            setTitle('');
            setContent('');
            setIsSpoiler(false);
        }
        setError('');
    }, [existingReview, isOpen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setError('');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const url = existingReview 
                ? `/api/reviews/${existingReview.id}`
                : `/api/reviews/comics/${comic.slug}`;
            const method = existingReview ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    rating,
                    title: title.trim() || null,
                    content: content.trim(),
                    is_spoiler: isSpoiler,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                onReviewSubmitted();
                onClose();
            } else {
                setError(data.message || 'Failed to submit review. Please try again.');
            }
        } catch (error) {
            console.error('Error submitting review:', error);
            setError('An error occurred. Please try again.');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm">
            <div className="bg-gray-900 rounded-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-white mb-2">
                            {existingReview ? 'Edit Your Review' : 'Write a Review'}
                        </h2>
                        <p className="text-gray-400">
                            Reviewing: <span className="text-white font-semibold">{comic.title}</span>
                            {comic.author && <span className="text-gray-500"> by {comic.author}</span>}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 text-gray-400 hover:text-white transition-colors rounded-lg hover:bg-gray-800"
                    >
                        <X className="w-6 h-6" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Rating */}
                    <div>
                        <label className="block text-sm font-medium text-gray-200 mb-3">
                            Rating *
                        </label>
                        <div className="flex space-x-2">
                            {[1, 2, 3, 4, 5].map((star) => (
                                <button
                                    key={star}
                                    type="button"
                                    className="p-1 transition-colors rounded"
                                    onMouseEnter={() => setHoverRating(star)}
                                    onMouseLeave={() => setHoverRating(0)}
                                    onClick={() => setRating(star)}
                                >
                                    <Star
                                        className={`w-8 h-8 ${
                                            star <= (hoverRating || rating)
                                                ? 'text-yellow-400 fill-current'
                                                : 'text-gray-600'
                                        } transition-colors`}
                                    />
                                </button>
                            ))}
                            <span className="ml-2 text-gray-400 self-center">
                                {rating > 0 ? `${rating} star${rating !== 1 ? 's' : ''}` : 'Click to rate'}
                            </span>
                        </div>
                    </div>

                    {/* Title */}
                    <div>
                        <label htmlFor="title" className="block text-sm font-medium text-gray-200 mb-2">
                            Review Title (Optional)
                        </label>
                        <input
                            type="text"
                            id="title"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            className="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500"
                            placeholder="Give your review a title..."
                            maxLength={255}
                        />
                    </div>

                    {/* Content */}
                    <div>
                        <label htmlFor="content" className="block text-sm font-medium text-gray-200 mb-2">
                            Review Content *
                        </label>
                        <textarea
                            id="content"
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            className="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 h-32 resize-none"
                            placeholder="Share your thoughts about this comic..."
                            required
                            minLength={10}
                            maxLength={5000}
                        />
                        <div className="flex justify-between items-center mt-2">
                            <span className="text-sm text-gray-500">Minimum 10 characters</span>
                            <span className="text-sm text-gray-500">{content.length}/5000</span>
                        </div>
                    </div>

                    {/* Spoiler Warning */}
                    <div className="flex items-center space-x-3">
                        <input
                            type="checkbox"
                            id="spoiler"
                            checked={isSpoiler}
                            onChange={(e) => setIsSpoiler(e.target.checked)}
                            className="w-4 h-4 text-red-500 bg-gray-800 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                        />
                        <label htmlFor="spoiler" className="flex items-center space-x-2 text-sm text-gray-200">
                            <AlertTriangle className="w-4 h-4 text-yellow-400" />
                            <span>This review contains spoilers</span>
                        </label>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="p-4 bg-red-900/30 border border-red-500/50 rounded-lg">
                            <p className="text-red-400 text-sm">{error}</p>
                        </div>
                    )}

                    {/* Submit Button */}
                    <div className="flex justify-end space-x-4 pt-6">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-6 py-3 text-gray-400 hover:text-white border border-gray-600 hover:border-gray-500 rounded-lg transition-colors"
                            disabled={isSubmitting}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={rating === 0 || content.trim().length < 10 || isSubmitting}
                            className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isSubmitting 
                                ? (existingReview ? 'Updating...' : 'Submitting...') 
                                : (existingReview ? 'Update Review' : 'Submit Review')
                            }
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}