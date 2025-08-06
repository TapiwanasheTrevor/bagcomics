import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { RecommendationEngine } from '../RecommendationEngine';

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({
  router: {
    visit: vi.fn()
  }
}));

// Mock fetch
global.fetch = vi.fn();

const mockComics = [
  {
    id: 1,
    title: 'Amazing Comic #1',
    author: 'John Author',
    genre: 'Action',
    cover_image_path: '/comic1.jpg',
    average_rating: 4.5,
    total_ratings: 100,
    price: 9.99,
    is_free: false,
    similarity_score: 0.95,
    recommendation_reason: 'Similar genre preferences'
  },
  {
    id: 2,
    title: 'Free Comic #2',
    author: 'Jane Author',
    genre: 'Adventure',
    cover_image_path: '/comic2.jpg',
    average_rating: 4.2,
    total_ratings: 50,
    price: 0,
    is_free: true,
    recommendation_reason: 'Highly rated by similar users'
  }
];

const mockRecommendations = [
  {
    title: 'For You',
    description: 'Personalized recommendations based on your reading history',
    icon: 'sparkles',
    comics: mockComics,
    type: 'personalized' as const
  },
  {
    title: 'Trending Now',
    description: 'Popular comics that everyone is reading',
    icon: 'trending',
    comics: [mockComics[1]],
    type: 'trending' as const
  },
  {
    title: 'Similar Comics',
    description: 'Comics similar to ones you\'ve enjoyed',
    icon: 'similar',
    comics: [mockComics[0]],
    type: 'similar' as const
  }
];

describe('RecommendationEngine', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading state correctly', () => {
    render(
      <RecommendationEngine
        recommendations={[]}
        isLoading={true}
      />
    );

    expect(screen.getByText('Loading recommendations...')).toBeInTheDocument();
  });

  it('renders empty state when no recommendations', () => {
    render(
      <RecommendationEngine
        recommendations={[]}
        isLoading={false}
      />
    );

    expect(screen.getByText('No Recommendations Yet')).toBeInTheDocument();
    expect(screen.getByText('Start reading comics to get personalized recommendations!')).toBeInTheDocument();
    expect(screen.getByText('Browse Comics')).toBeInTheDocument();
  });

  it('renders recommendations correctly', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('Recommendations')).toBeInTheDocument();
    expect(screen.getByText('Discover your next favorite comic')).toBeInTheDocument();
    
    // Should show section tabs
    expect(screen.getByText('For You')).toBeInTheDocument();
    expect(screen.getByText('Trending Now')).toBeInTheDocument();
    expect(screen.getByText('Similar Comics')).toBeInTheDocument();
  });

  it('displays comics in active section', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    // Should show comics from first section by default
    expect(screen.getByText('Amazing Comic #1')).toBeInTheDocument();
    expect(screen.getByText('Free Comic #2')).toBeInTheDocument();
    expect(screen.getByText('John Author')).toBeInTheDocument();
    expect(screen.getByText('Jane Author')).toBeInTheDocument();
  });

  it('switches between recommendation sections', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    // Click on trending section
    fireEvent.click(screen.getByText('Trending Now'));
    
    // Should show only trending comics
    expect(screen.getByText('Free Comic #2')).toBeInTheDocument();
    expect(screen.queryByText('Amazing Comic #1')).not.toBeInTheDocument();
  });

  it('displays comic information correctly', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    // Check comic details
    expect(screen.getByText('$9.99')).toBeInTheDocument();
    expect(screen.getByText('Free')).toBeInTheDocument();
    expect(screen.getByText('Action')).toBeInTheDocument();
    expect(screen.getByText('Adventure')).toBeInTheDocument();
  });

  it('shows similarity scores when available', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('95% match')).toBeInTheDocument();
  });

  it('shows recommendation reasons', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('Similar genre preferences')).toBeInTheDocument();
    expect(screen.getByText('Highly rated by similar users')).toBeInTheDocument();
  });

  it('shows free badge for free comics', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('Free')).toBeInTheDocument();
  });

  it('navigates to comic when clicked', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    const comicTitle = screen.getByText('Amazing Comic #1');
    const comicElement = comicTitle.closest('.group');
    if (comicElement) {
      fireEvent.click(comicElement);
    }

    expect(mockRouter.visit).toHaveBeenCalledWith('/comics/1');
  });

  it('refreshes recommendations when refresh button clicked', async () => {
    const mockFetch = vi.mocked(fetch);
    mockFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ recommendations: mockRecommendations })
    } as Response);

    render(
      <RecommendationEngine
        userId={1}
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    const refreshButton = screen.getByText('Refresh');
    fireEvent.click(refreshButton);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith('/api/recommendations?user_id=1');
    });
  });

  it('includes comic ID in refresh request when provided', async () => {
    const mockFetch = vi.mocked(fetch);
    mockFetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ recommendations: mockRecommendations })
    } as Response);

    render(
      <RecommendationEngine
        userId={1}
        currentComicId={5}
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    const refreshButton = screen.getByText('Refresh');
    fireEvent.click(refreshButton);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith('/api/recommendations?user_id=1&comic_id=5');
    });
  });

  it('handles refresh error gracefully', async () => {
    const mockFetch = vi.mocked(fetch);
    mockFetch.mockRejectedValueOnce(new Error('Network error'));
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    const refreshButton = screen.getByText('Refresh');
    fireEvent.click(refreshButton);

    await waitFor(() => {
      expect(consoleSpy).toHaveBeenCalledWith('Failed to refresh recommendations:', expect.any(Error));
    });

    consoleSpy.mockRestore();
  });

  it('shows empty section message when section has no comics', () => {
    const emptyRecommendations = [
      {
        title: 'Empty Section',
        description: 'This section has no comics',
        icon: 'sparkles',
        comics: [],
        type: 'personalized' as const
      }
    ];

    render(
      <RecommendationEngine
        recommendations={emptyRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('No recommendations available for this category yet.')).toBeInTheDocument();
  });

  it('displays rating stars correctly', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    // Should show rating information
    expect(screen.getByText('(100)')).toBeInTheDocument();
    expect(screen.getByText('(50)')).toBeInTheDocument();
  });

  it('shows quick actions section', () => {
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    expect(screen.getByText('Want more personalized recommendations?')).toBeInTheDocument();
    expect(screen.getByText('My Library')).toBeInTheDocument();
    expect(screen.getByText('Browse Comics')).toBeInTheDocument();
  });

  it('navigates to library when My Library clicked', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    fireEvent.click(screen.getByText('My Library'));
    expect(mockRouter.visit).toHaveBeenCalledWith('/library');
  });

  it('navigates to comics when Browse Comics clicked', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    
    render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
      />
    );

    fireEvent.click(screen.getByText('Browse Comics'));
    expect(mockRouter.visit).toHaveBeenCalledWith('/comics');
  });

  it('applies custom className', () => {
    const { container } = render(
      <RecommendationEngine
        recommendations={mockRecommendations}
        isLoading={false}
        className="custom-class"
      />
    );

    expect(container.firstChild).toHaveClass('custom-class');
  });

  it('shows default cover when comic has no cover image', () => {
    const recommendationsWithoutCover = [
      {
        ...mockRecommendations[0],
        comics: [
          {
            ...mockComics[0],
            cover_image_path: undefined
          }
        ]
      }
    ];

    render(
      <RecommendationEngine
        recommendations={recommendationsWithoutCover}
        isLoading={false}
      />
    );

    // Should show default book icon
    const bookIcon = document.querySelector('.w-12.h-12.text-gray-400');
    expect(bookIcon).toBeInTheDocument();
  });
});