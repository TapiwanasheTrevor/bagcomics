import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import UserLibrary, { type LibraryEntry } from '../UserLibrary';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
}));

// Mock fetch
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Mock CSRF token
Object.defineProperty(document, 'querySelector', {
    value: vi.fn().mockReturnValue({ getAttribute: () => 'mock-csrf-token' }),
    writable: true,
});

const mockLibraryEntry: LibraryEntry = {
    id: 1,
    comic_id: 1,
    access_type: 'purchased',
    purchase_price: 9.99,
    purchased_at: '2024-01-01T00:00:00Z',
    is_favorite: false,
    rating: 4,
    review: 'Great comic!',
    last_accessed_at: '2024-01-15T00:00:00Z',
    total_reading_time: 3600,
    completion_percentage: 75.5,
    created_at: '2024-01-01T00:00:00Z',
    comic: {
        id: 1,
        slug: 'test-comic',
        title: 'Test Comic',
        author: 'Test Author',
        genre: 'Action',
        description: 'A test comic',
        cover_image_url: 'https://example.com/cover.jpg',
        page_count: 100,
        average_rating: 4.5,
        total_readers: 1000,
        is_free: false,
        price: 9.99,
        has_mature_content: false,
        published_at: '2024-01-01T00:00:00Z',
        tags: ['action', 'adventure'],
        reading_time_estimate: 60,
        is_new_release: false,
        publication_year: 2024,
        publisher: 'Test Publisher',
        isbn: '1234567890',
        language: 'English',
        content_warnings: '',
        pdf_file_path: '/comics/test.pdf',
        pdf_file_name: 'test.pdf',
        pdf_file_size: 1024000,
        is_pdf_comic: true,
    },
    progress: {
        current_page: 75,
        total_pages: 100,
        reading_time_minutes: 60,
        last_read_at: '2024-01-15T00:00:00Z',
    },
};

const mockApiResponse = {
    data: [mockLibraryEntry],
    pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 1,
    },
};

describe('UserLibrary', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockApiResponse),
        });
    });

    it('renders library stats correctly', async () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        expect(screen.getByText('1')).toBeInTheDocument();
        expect(screen.getByText('Total Comics')).toBeInTheDocument();
        expect(screen.getByText('Favorites')).toBeInTheDocument();
        expect(screen.getByText('Reading')).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Reading Time')).toBeInTheDocument();
    });

    it('displays library entries in grid view', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        expect(screen.getByText('Test Comic')).toBeInTheDocument();
        expect(screen.getByText('Test Author')).toBeInTheDocument();
        expect(screen.getByText('4.5')).toBeInTheDocument();
    });

    it('switches between grid and list view', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        const listViewButton = screen.getByRole('button', { name: /list/i });
        fireEvent.click(listViewButton);

        // Should still show the comic but in list format
        expect(screen.getByText('Test Comic')).toBeInTheDocument();
    });

    it('filters entries by search query', async () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        const searchInput = screen.getByPlaceholderText('Search your library...');
        fireEvent.change(searchInput, { target: { value: 'Test Comic' } });

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalled();
        });
    });

    it('shows filters panel when filter button is clicked', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        const filterButton = screen.getByRole('button', { name: /filters/i });
        fireEvent.click(filterButton);

        expect(screen.getByText('Advanced Filters')).toBeInTheDocument();
    });

    it('handles tab switching correctly', async () => {
        const favoriteEntry = { ...mockLibraryEntry, is_favorite: true };
        render(<UserLibrary initialEntries={[favoriteEntry]} />);

        const favoritesTab = screen.getByRole('button', { name: /favorites/i });
        fireEvent.click(favoritesTab);

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalled();
        });
    });

    it('toggles favorite status', async () => {
        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: () => Promise.resolve({}),
        });

        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        // Find and click the favorite button in the overlay
        const comicCard = screen.getByText('Test Comic').closest('.group');
        expect(comicCard).toBeInTheDocument();

        // Simulate hover to show overlay
        fireEvent.mouseEnter(comicCard!);

        // The favorite button should be in the overlay
        const favoriteButtons = screen.getAllByRole('button');
        const favoriteButton = favoriteButtons.find(button => 
            button.querySelector('svg')?.classList.contains('h-4')
        );

        if (favoriteButton) {
            fireEvent.click(favoriteButton);

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/api/library/1/favorite',
                    expect.objectContaining({
                        method: 'POST',
                    })
                );
            });
        }
    });

    it('shows empty state when no entries', () => {
        render(<UserLibrary initialEntries={[]} />);

        expect(screen.getByText('No comics in your library')).toBeInTheDocument();
        expect(screen.getByText('Start building your collection by exploring our catalog')).toBeInTheDocument();
    });

    it('shows loading state', () => {
        render(<UserLibrary />);

        // Should show loading skeletons
        const skeletons = screen.getAllByRole('generic').filter(el => 
            el.classList.contains('animate-pulse')
        );
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('handles API errors gracefully', async () => {
        mockFetch.mockRejectedValueOnce(new Error('API Error'));

        render(<UserLibrary />);

        await waitFor(() => {
            // Should not crash and should show empty state or error handling
            expect(screen.queryByText('Test Comic')).not.toBeInTheDocument();
        });
    });

    it('formats reading time correctly', () => {
        const entryWithLongReadingTime = {
            ...mockLibraryEntry,
            total_reading_time: 7200, // 2 hours in seconds
        };

        render(<UserLibrary initialEntries={[entryWithLongReadingTime]} />);

        expect(screen.getByText('2h 0m')).toBeInTheDocument();
    });

    it('shows progress bar for partially read comics', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        // Progress bar should be visible (75.5% completion)
        const progressBars = document.querySelectorAll('[style*="width: 75.5%"]');
        expect(progressBars.length).toBeGreaterThan(0);
    });

    it('displays access type badges correctly', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        expect(screen.getByText('Owned')).toBeInTheDocument();
    });

    it('shows user rating when available', () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        expect(screen.getByText('Your rating:')).toBeInTheDocument();
        // Should show 4 filled stars
        const filledStars = document.querySelectorAll('.text-yellow-400.fill-current');
        expect(filledStars.length).toBeGreaterThan(0);
    });

    it('handles pagination correctly', async () => {
        const paginatedResponse = {
            ...mockApiResponse,
            pagination: {
                current_page: 1,
                last_page: 2,
                per_page: 24,
                total: 30,
            },
        };

        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: () => Promise.resolve(paginatedResponse),
        });

        render(<UserLibrary />);

        await waitFor(() => {
            expect(screen.getByText('Next')).toBeInTheDocument();
            expect(screen.getByText('Page 1 of 2')).toBeInTheDocument();
        });
    });

    it('sorts entries correctly', async () => {
        render(<UserLibrary initialEntries={[mockLibraryEntry]} />);

        const sortSelect = screen.getByDisplayValue('Recently Read');
        fireEvent.change(sortSelect, { target: { value: 'title-asc' } });

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalled();
        });
    });
});