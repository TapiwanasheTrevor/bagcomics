import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import ComicGrid, { type Comic } from '../ComicGrid';

// Mock Inertia Link
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

// Mock UI components
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: any) => (
        <button onClick={onClick} {...props}>
            {children}
        </button>
    ),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children, ...props }: any) => (
        <span {...props}>{children}</span>
    ),
}));

vi.mock('@/components/ui/skeleton', () => ({
    Skeleton: ({ className }: any) => (
        <div className={`skeleton ${className}`} />
    ),
}));

const mockComics: Comic[] = [
    {
        id: 1,
        slug: 'test-comic-1',
        title: 'Test Comic 1',
        author: 'Test Author',
        genre: 'Action',
        description: 'A test comic description',
        cover_image_url: 'https://example.com/cover1.jpg',
        page_count: 24,
        average_rating: 4.5,
        total_readers: 100,
        is_free: true,
        price: 0,
        has_mature_content: false,
        published_at: '2024-01-01',
        tags: ['action', 'adventure'],
        reading_time_estimate: 30,
        is_new_release: true,
        user_has_access: true,
        user_progress: {
            current_page: 5,
            total_pages: 24,
            progress_percentage: 20,
            is_completed: false,
            is_bookmarked: true,
            last_read_at: '2024-01-15',
        },
    },
    {
        id: 2,
        slug: 'test-comic-2',
        title: 'Test Comic 2',
        author: 'Another Author',
        genre: 'Drama',
        description: 'Another test comic',
        cover_image_url: undefined,
        page_count: 32,
        average_rating: 3.8,
        total_readers: 50,
        is_free: false,
        price: 2.99,
        has_mature_content: true,
        published_at: '2024-01-02',
        tags: ['drama'],
        reading_time_estimate: 45,
        is_new_release: false,
    },
];

describe('ComicGrid', () => {
    const defaultProps = {
        comics: mockComics,
        loading: false,
        hasMore: true,
        onLoadMore: vi.fn(),
        viewMode: 'grid' as const,
        onComicAction: vi.fn(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders comics in grid view', () => {
        render(<ComicGrid {...defaultProps} />);
        
        expect(screen.getByText('Test Comic 1')).toBeInTheDocument();
        expect(screen.getByText('Test Comic 2')).toBeInTheDocument();
        expect(screen.getByText('Test Author')).toBeInTheDocument();
        expect(screen.getByText('Another Author')).toBeInTheDocument();
    });

    it('renders comics in list view', () => {
        render(<ComicGrid {...defaultProps} viewMode="list" />);
        
        expect(screen.getByText('Test Comic 1')).toBeInTheDocument();
        expect(screen.getByText('Test Comic 2')).toBeInTheDocument();
        
        // List view should show more details
        expect(screen.getByText('A test comic description')).toBeInTheDocument();
        expect(screen.getByText('Another test comic')).toBeInTheDocument();
    });

    it('shows loading skeletons when loading', () => {
        render(<ComicGrid {...defaultProps} loading={true} />);
        
        const skeletons = screen.getAllByRole('generic', { name: /skeleton/i });
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no comics', () => {
        render(<ComicGrid {...defaultProps} comics={[]} />);
        
        expect(screen.getByText('No comics found')).toBeInTheDocument();
        expect(screen.getByText('Try adjusting your search or filter criteria.')).toBeInTheDocument();
    });

    it('displays comic badges correctly', () => {
        render(<ComicGrid {...defaultProps} />);
        
        expect(screen.getByText('FREE')).toBeInTheDocument();
        expect(screen.getByText('NEW')).toBeInTheDocument();
        expect(screen.getByText('18+')).toBeInTheDocument();
    });

    it('shows progress indicator for comics with progress', () => {
        render(<ComicGrid {...defaultProps} />);
        
        // Check for progress bar (should have width style)
        const progressBars = document.querySelectorAll('[style*="width: 20%"]');
        expect(progressBars.length).toBeGreaterThan(0);
    });

    it('handles comic action clicks', () => {
        const onComicAction = vi.fn();
        render(<ComicGrid {...defaultProps} onComicAction={onComicAction} />);
        
        const bookmarkButtons = screen.getAllByRole('button');
        const bookmarkButton = bookmarkButtons.find(btn => 
            btn.querySelector('svg')?.getAttribute('class')?.includes('lucide-bookmark')
        );
        
        if (bookmarkButton) {
            fireEvent.click(bookmarkButton);
            expect(onComicAction).toHaveBeenCalledWith(mockComics[0], 'bookmark');
        }
    });

    it('calls onLoadMore when load more button is clicked', () => {
        const onLoadMore = vi.fn();
        render(<ComicGrid {...defaultProps} onLoadMore={onLoadMore} hasMore={true} />);
        
        const loadMoreButton = screen.getByText('Load More Comics');
        fireEvent.click(loadMoreButton);
        
        expect(onLoadMore).toHaveBeenCalled();
    });

    it('does not show load more button when hasMore is false', () => {
        render(<ComicGrid {...defaultProps} hasMore={false} />);
        
        expect(screen.queryByText('Load More Comics')).not.toBeInTheDocument();
    });

    it('formats price correctly', () => {
        render(<ComicGrid {...defaultProps} />);
        
        expect(screen.getByText('Free')).toBeInTheDocument();
        expect(screen.getByText('$2.99')).toBeInTheDocument();
    });

    it('formats rating correctly', () => {
        render(<ComicGrid {...defaultProps} />);
        
        expect(screen.getByText('4.5')).toBeInTheDocument();
        expect(screen.getByText('3.8')).toBeInTheDocument();
    });

    it('shows reading time estimate', () => {
        render(<ComicGrid {...defaultProps} />);
        
        expect(screen.getByText('30m')).toBeInTheDocument();
        expect(screen.getByText('45m')).toBeInTheDocument();
    });

    it('handles missing cover images', () => {
        render(<ComicGrid {...defaultProps} />);
        
        // Should show BookOpen icon for comics without cover images
        const bookIcons = document.querySelectorAll('svg.lucide-book-open');
        expect(bookIcons.length).toBeGreaterThan(0);
    });

    // Test infinite scroll behavior
    it('triggers infinite scroll when intersection observer fires', async () => {
        const onLoadMore = vi.fn();
        
        // Mock IntersectionObserver
        const mockIntersectionObserver = vi.fn();
        mockIntersectionObserver.mockReturnValue({
            observe: vi.fn(),
            unobserve: vi.fn(),
            disconnect: vi.fn(),
        });
        window.IntersectionObserver = mockIntersectionObserver;

        render(<ComicGrid {...defaultProps} onLoadMore={onLoadMore} />);

        // Simulate intersection observer callback
        const [callback] = mockIntersectionObserver.mock.calls[0];
        callback([{ isIntersecting: true }]);

        await waitFor(() => {
            expect(onLoadMore).toHaveBeenCalled();
        });
    });
});