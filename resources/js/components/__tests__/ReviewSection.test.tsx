import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ReviewSection } from '../ReviewSection';

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({
  router: {
    post: vi.fn()
  }
}));

const mockReviews = [
  {
    id: 1,
    user: {
      id: 1,
      name: 'John Doe',
      avatar_path: '/avatar1.jpg'
    },
    rating: 5,
    title: 'Amazing comic!',
    content: 'This is a fantastic comic with great artwork.',
    is_spoiler: false,
    helpful_votes: 10,
    total_votes: 12,
    created_at: '2024-01-01T00:00:00Z',
    user_vote: null
  },
  {
    id: 2,
    user: {
      id: 2,
      name: 'Jane Smith'
    },
    rating: 3,
    title: 'Decent read',
    content: 'It was okay, but the ending was disappointing.',
    is_spoiler: true,
    helpful_votes: 5,
    total_votes: 8,
    created_at: '2024-01-02T00:00:00Z',
    user_vote: 'helpful'
  }
];

describe('ReviewSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders review summary correctly', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={true}
      />
    );

    expect(screen.getByText('Reviews & Ratings')).toBeInTheDocument();
    expect(screen.getByText('(2 reviews)')).toBeInTheDocument();
  });

  it('shows write review button when user can review', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={true}
      />
    );

    expect(screen.getByText('Write a Review')).toBeInTheDocument();
  });

  it('does not show write review button when user cannot review', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={false}
      />
    );

    expect(screen.queryByText('Write a Review')).not.toBeInTheDocument();
  });

  it('opens review form when write review button is clicked', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={true}
      />
    );

    fireEvent.click(screen.getByText('Write a Review'));
    
    expect(screen.getByText('Your Rating')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Summarize your review')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Share your thoughts about this comic...')).toBeInTheDocument();
  });

  it('submits review form with correct data', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockResolvedValue({});

    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={true}
      />
    );

    // Open form
    fireEvent.click(screen.getByText('Write a Review'));

    // Fill form
    const titleInput = screen.getByPlaceholderText('Summarize your review');
    const contentTextarea = screen.getByPlaceholderText('Share your thoughts about this comic...');
    const spoilerCheckbox = screen.getByLabelText('This review contains spoilers');

    fireEvent.change(titleInput, { target: { value: 'Great comic!' } });
    fireEvent.change(contentTextarea, { target: { value: 'I really enjoyed this comic.' } });
    fireEvent.click(spoilerCheckbox);

    // Set rating by clicking on stars
    const stars = screen.getAllByRole('button', { hidden: true });
    fireEvent.click(stars[3]); // 4 stars

    // Submit form
    fireEvent.click(screen.getByText('Submit Review'));

    await waitFor(() => {
      expect(mockRouter.post).toHaveBeenCalledWith('/api/comics/1/reviews', {
        rating: 4,
        title: 'Great comic!',
        content: 'I really enjoyed this comic.',
        is_spoiler: true
      });
    });
  });

  it('displays reviews correctly', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    expect(screen.getByText('John Doe')).toBeInTheDocument();
    expect(screen.getByText('Amazing comic!')).toBeInTheDocument();
    expect(screen.getByText('This is a fantastic comic with great artwork.')).toBeInTheDocument();
    
    expect(screen.getByText('Jane Smith')).toBeInTheDocument();
    expect(screen.getByText('Decent read')).toBeInTheDocument();
  });

  it('shows spoiler badge for spoiler reviews', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    expect(screen.getByText('Spoiler')).toBeInTheDocument();
  });

  it('filters out spoiler reviews by default', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    // Should show non-spoiler review
    expect(screen.getByText('Amazing comic!')).toBeInTheDocument();
    
    // Should not show spoiler review content initially
    expect(screen.queryByText('It was okay, but the ending was disappointing.')).not.toBeInTheDocument();
  });

  it('shows spoiler reviews when checkbox is checked', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    const spoilerCheckbox = screen.getByLabelText('Show spoiler reviews');
    fireEvent.click(spoilerCheckbox);

    // Should now show spoiler review content
    expect(screen.getByText('It was okay, but the ending was disappointing.')).toBeInTheDocument();
  });

  it('sorts reviews correctly', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    const sortSelect = screen.getByDisplayValue('Newest First');
    
    // Change to rating sort
    fireEvent.change(sortSelect, { target: { value: 'rating' } });
    
    // Should sort by rating (highest first)
    const reviewTitles = screen.getAllByRole('heading', { level: 5 });
    expect(reviewTitles[0]).toHaveTextContent('Amazing comic!'); // 5 stars
  });

  it('handles voting on reviews', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockResolvedValue({});

    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    const helpfulButton = screen.getAllByText(/Helpful/)[0];
    fireEvent.click(helpfulButton);

    await waitFor(() => {
      expect(mockRouter.post).toHaveBeenCalledWith('/api/reviews/1/vote', {
        helpful: true
      });
    });
  });

  it('handles reporting reviews', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockResolvedValue({});

    render(
      <ReviewSection
        comicId={1}
        reviews={mockReviews}
        averageRating={4.0}
        totalReviews={2}
        userCanReview={false}
      />
    );

    const reportButton = screen.getAllByText('Report')[0];
    fireEvent.click(reportButton);

    await waitFor(() => {
      expect(mockRouter.post).toHaveBeenCalledWith('/api/reviews/1/report');
    });
  });

  it('shows empty state when no reviews', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={false}
      />
    );

    expect(screen.getByText('No reviews yet. Be the first to review this comic!')).toBeInTheDocument();
  });

  it('prevents form submission without rating', () => {
    render(
      <ReviewSection
        comicId={1}
        reviews={[]}
        averageRating={0}
        totalReviews={0}
        userCanReview={true}
      />
    );

    // Open form
    fireEvent.click(screen.getByText('Write a Review'));

    // Fill form without rating
    const titleInput = screen.getByPlaceholderText('Summarize your review');
    fireEvent.change(titleInput, { target: { value: 'Great comic!' } });

    // Submit button should be disabled
    const submitButton = screen.getByText('Submit Review');
    expect(submitButton).toBeDisabled();
  });
});