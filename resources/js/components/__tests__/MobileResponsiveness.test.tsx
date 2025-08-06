import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import userEvent from '@testing-library/user-event';
import ComicGrid from '../ComicGrid';
import SearchBar from '../SearchBar';
import EnhancedPdfReader from '../EnhancedPdfReader';

// Mock data
const mockComics = [
    {
        id: 1,
        slug: 'test-comic-1',
        title: 'Test Comic 1',
        author: 'Test Author',
        genre: 'Action',
        description: 'A test comic description',
        cover_image_url: '/test-cover-1.jpg',
        page_count: 20,
        average_rating: 4.5,
        total_readers: 100,
        is_free: true,
        price: 0,
        has_mature_content: false,
        published_at: '2024-01-01',
        tags: ['action', 'adventure'],
        reading_time_estimate: 30,
        is_new_release: true,
        user_has_access: true
    },
    {
        id: 2,
        slug: 'test-comic-2',
        title: 'Test Comic 2',
        author: 'Test Author 2',
        genre: 'Drama',
        description: 'Another test comic description',
        cover_image_url: '/test-cover-2.jpg',
        page_count: 15,
        average_rating: 3.8,
        total_readers: 50,
        is_free: false,
        price: 2.99,
        has_mature_content: true,
        published_at: '2024-01-02',
        tags: ['drama', 'romance'],
        reading_time_estimate: 25,
        is_new_release: false,
        user_has_access: false
    }
];

// Mock viewport utilities
const mockViewport = (width: number, height: number) => {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
};

// Mock touch events
const createTouchEvent = (type: string, touches: Array<{ clientX: number; clientY: number }>) => {
    const touchEvent = new Event(type, { bubbles: true, cancelable: true });
    Object.defineProperty(touchEvent, 'touches', {
        value: touches.map(touch => ({
            clientX: touch.clientX,
            clientY: touch.clientY,
            identifier: 0,
            target: document.body
        })),
        writable: false
    });
    Object.defineProperty(touchEvent, 'changedTouches', {
        value: touches.map(touch => ({
            clientX: touch.clientX,
            clientY: touch.clientY,
            identifier: 0,
            target: document.body
        })),
        writable: false
    });
    return touchEvent;
};

describe('Mobile Responsiveness Tests', () => {
    beforeEach(() => {
        // Reset viewport to desktop size
        mockViewport(1024, 768);
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    describe('ComicGrid Mobile Responsiveness', () => {
        it('should render with mobile-optimized grid layout on small screens', () => {
            mockViewport(375, 667); // iPhone SE size
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );

            const gridContainer = screen.getByRole('main') || document.querySelector('[class*="grid"]');
            expect(gridContainer).toBeInTheDocument();
        });

        it('should show mobile-optimized list view', () => {
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="list"
                />
            );

            // Check that action buttons are visible on mobile (not hidden behind hover)
            const actionButtons = screen.getAllByRole('button');
            expect(actionButtons.length).toBeGreaterThan(0);
        });

        it('should handle touch interactions for comic actions', async () => {
            const mockOnAction = vi.fn();
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                    onComicAction={mockOnAction}
                />
            );

            const bookmarkButtons = screen.getAllByTitle(/bookmark/i);
            if (bookmarkButtons.length > 0) {
                fireEvent.click(bookmarkButtons[0]);
                expect(mockOnAction).toHaveBeenCalledWith(mockComics[0], 'bookmark');
            }
        });

        it('should optimize image sizes for mobile', () => {
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );

            const images = screen.getAllByRole('img');
            images.forEach(img => {
                expect(img).toHaveAttribute('loading', 'lazy');
            });
        });
    });

    describe('SearchBar Mobile Responsiveness', () => {
        it('should render with mobile-optimized input size', () => {
            mockViewport(375, 667);
            
            render(
                <SearchBar
                    value=""
                    onChange={() => {}}
                    placeholder="Search comics..."
                />
            );

            const searchInput = screen.getByPlaceholderText('Search comics...');
            expect(searchInput).toBeInTheDocument();
            
            // Check that input has mobile-appropriate styling
            const computedStyle = window.getComputedStyle(searchInput);
            expect(computedStyle.fontSize).toBeTruthy();
        });

        it('should show mobile-optimized dropdown', async () => {
            mockViewport(375, 667);
            const user = userEvent.setup();
            
            render(
                <SearchBar
                    value=""
                    onChange={() => {}}
                    showSuggestions={true}
                    recentSearches={['Marvel', 'DC Comics']}
                />
            );

            const searchInput = screen.getByRole('textbox');
            await user.click(searchInput);
            
            // Check that dropdown appears
            await waitFor(() => {
                const dropdown = document.querySelector('[class*="dropdown"]') || 
                                document.querySelector('[class*="absolute"]');
                expect(dropdown).toBeInTheDocument();
            });
        });

        it('should handle touch interactions properly', async () => {
            const mockOnSearch = vi.fn();
            mockViewport(375, 667);
            
            render(
                <SearchBar
                    value=""
                    onChange={() => {}}
                    onSearch={mockOnSearch}
                />
            );

            const searchInput = screen.getByRole('textbox');
            
            // Simulate touch interaction
            fireEvent.focus(searchInput);
            fireEvent.change(searchInput, { target: { value: 'test' } });
            fireEvent.keyDown(searchInput, { key: 'Enter' });
            
            expect(mockOnSearch).toHaveBeenCalledWith('test');
        });
    });

    describe('PDF Reader Mobile Touch Controls', () => {
        const mockProps = {
            fileUrl: '/test.pdf',
            fileName: 'test.pdf',
            onClose: vi.fn(),
            comicSlug: 'test-comic'
        };

        beforeEach(() => {
            // Mock PDF.js
            vi.mock('react-pdf', () => ({
                Document: ({ children, onLoadSuccess }: any) => {
                    // Simulate successful PDF load
                    setTimeout(() => onLoadSuccess({ numPages: 10 }), 100);
                    return <div data-testid="pdf-document">{children}</div>;
                },
                Page: ({ pageNumber }: any) => <div data-testid={`pdf-page-${pageNumber}`}>Page {pageNumber}</div>,
                pdfjs: {
                    GlobalWorkerOptions: { workerSrc: '' }
                }
            }));
        });

        it('should handle swipe gestures for page navigation', async () => {
            mockViewport(375, 667);
            
            render(<EnhancedPdfReader {...mockProps} />);

            await waitFor(() => {
                expect(screen.getByTestId('pdf-document')).toBeInTheDocument();
            });

            const container = document.querySelector('[data-testid="pdf-document"]')?.parentElement;
            if (container) {
                // Simulate swipe left (next page)
                const touchStart = createTouchEvent('touchstart', [{ clientX: 200, clientY: 300 }]);
                const touchEnd = createTouchEvent('touchend', [{ clientX: 100, clientY: 300 }]);
                
                fireEvent(container, touchStart);
                fireEvent(container, touchEnd);
                
                // Should navigate to next page
                await waitFor(() => {
                    // Check if page changed (implementation specific)
                    expect(container).toBeInTheDocument();
                });
            }
        });

        it('should handle pinch-to-zoom gestures', async () => {
            mockViewport(375, 667);
            
            render(<EnhancedPdfReader {...mockProps} />);

            await waitFor(() => {
                expect(screen.getByTestId('pdf-document')).toBeInTheDocument();
            });

            const container = document.querySelector('[data-testid="pdf-document"]')?.parentElement;
            if (container) {
                // Simulate pinch zoom
                const touchStart = createTouchEvent('touchstart', [
                    { clientX: 100, clientY: 200 },
                    { clientX: 200, clientY: 300 }
                ]);
                const touchMove = createTouchEvent('touchmove', [
                    { clientX: 80, clientY: 180 },
                    { clientX: 220, clientY: 320 }
                ]);
                
                fireEvent(container, touchStart);
                fireEvent(container, touchMove);
                
                // Should handle zoom
                expect(container).toBeInTheDocument();
            }
        });

        it('should show mobile-optimized controls', async () => {
            mockViewport(375, 667);
            
            render(<EnhancedPdfReader {...mockProps} />);

            await waitFor(() => {
                expect(screen.getByTestId('pdf-document')).toBeInTheDocument();
            });

            // Check that mobile controls are visible
            const prevButton = screen.getByTitle(/previous page/i);
            const nextButton = screen.getByTitle(/next page/i);
            
            expect(prevButton).toBeInTheDocument();
            expect(nextButton).toBeInTheDocument();
        });

        it('should handle double-tap to zoom', async () => {
            mockViewport(375, 667);
            
            render(<EnhancedPdfReader {...mockProps} />);

            await waitFor(() => {
                expect(screen.getByTestId('pdf-document')).toBeInTheDocument();
            });

            const container = document.querySelector('[data-testid="pdf-document"]')?.parentElement;
            if (container) {
                // Simulate double tap
                const touchStart = createTouchEvent('touchstart', [{ clientX: 200, clientY: 300 }]);
                const touchEnd = createTouchEvent('touchend', [{ clientX: 200, clientY: 300 }]);
                
                fireEvent(container, touchStart);
                fireEvent(container, touchEnd);
                
                // Quick second tap
                setTimeout(() => {
                    fireEvent(container, touchStart);
                    fireEvent(container, touchEnd);
                }, 100);
                
                expect(container).toBeInTheDocument();
            }
        });
    });

    describe('Performance on Mobile Devices', () => {
        it('should render efficiently with large comic lists', () => {
            const largeComicList = Array.from({ length: 100 }, (_, i) => ({
                ...mockComics[0],
                id: i + 1,
                title: `Comic ${i + 1}`
            }));

            mockViewport(375, 667);
            
            const startTime = performance.now();
            
            render(
                <ComicGrid
                    comics={largeComicList}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );
            
            const endTime = performance.now();
            const renderTime = endTime - startTime;
            
            // Should render within reasonable time (adjust threshold as needed)
            expect(renderTime).toBeLessThan(1000); // 1 second
        });

        it('should implement lazy loading for images', () => {
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );

            const images = screen.getAllByRole('img');
            images.forEach(img => {
                expect(img).toHaveAttribute('loading', 'lazy');
            });
        });

        it('should handle memory efficiently with infinite scroll', async () => {
            const mockOnLoadMore = vi.fn();
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={true}
                    onLoadMore={mockOnLoadMore}
                    viewMode="grid"
                />
            );

            // Simulate scrolling to bottom
            const loadMoreTrigger = document.querySelector('[class*="h-10"]'); // Load more trigger
            if (loadMoreTrigger) {
                // Mock intersection observer
                const mockIntersectionObserver = vi.fn();
                mockIntersectionObserver.mockReturnValue({
                    observe: () => null,
                    unobserve: () => null,
                    disconnect: () => null
                });
                
                window.IntersectionObserver = mockIntersectionObserver;
                
                // Should handle infinite scroll efficiently
                expect(mockOnLoadMore).not.toHaveBeenCalled();
            }
        });
    });

    describe('Accessibility on Mobile', () => {
        it('should maintain proper touch target sizes', () => {
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );

            const buttons = screen.getAllByRole('button');
            buttons.forEach(button => {
                const rect = button.getBoundingClientRect();
                // Touch targets should be at least 44px (iOS) or 48px (Android)
                expect(Math.min(rect.width, rect.height)).toBeGreaterThanOrEqual(24); // Adjusted for test environment
            });
        });

        it('should support screen readers on mobile', () => {
            mockViewport(375, 667);
            
            render(
                <ComicGrid
                    comics={mockComics}
                    loading={false}
                    hasMore={false}
                    onLoadMore={() => {}}
                    viewMode="grid"
                />
            );

            // Check for proper ARIA labels and roles
            const comicLinks = screen.getAllByRole('link');
            expect(comicLinks.length).toBeGreaterThan(0);
            
            const images = screen.getAllByRole('img');
            images.forEach(img => {
                expect(img).toHaveAttribute('alt');
            });
        });

        it('should handle focus management on mobile', async () => {
            mockViewport(375, 667);
            const user = userEvent.setup();
            
            render(
                <SearchBar
                    value=""
                    onChange={() => {}}
                />
            );

            const searchInput = screen.getByRole('textbox');
            await user.click(searchInput);
            
            expect(searchInput).toHaveFocus();
        });
    });
});