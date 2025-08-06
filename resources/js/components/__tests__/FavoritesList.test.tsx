import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { vi, describe, it, expect } from 'vitest';
import FavoritesList from '../FavoritesList';
import type { LibraryEntry } from '../UserLibrary';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
}));

const mockFavoriteEntry: LibraryEntry = {
    id: 1,
    comic_id: 1,
    access_type: 'purchased',
    purchase_price: 9.99,
    purchased_at: '2024-01-01T00:00:00Z',
    is_favorite: true,
    rating: 5,
    review: 'Amazing comic!',
    last_accessed_at: '2024-01-15T00:00:00Z',
    total_reading_time: 3600,
    completion_percentage: 100,
    created_at: '2024-01-01T00:00:00Z',
    comic: {
        id: 1,
        slug: 'favorite-comic',
        title: 'Favorite Comic',
        author: 'Favorite Author',
        genre: 'Adventure',
        publisher: 'DC Comics',
        language: 'English',
        tags: ['adventure', 'fantasy'],
        cover_image_url: 'https://example.com/favorite-cover.jpg',
        page_count: 150,
        average_rating: 4.8,
        total_readers: 2000,
        is_free: false,
        price: 9.99,
        has_mature_content: false,
        published_at: '2024-01-01T00:00:00Z',
        reading_time_estimate: 90,
        is_new_release: false,
        publication_year: 2024,
        isbn: '9876543210',
        content_warnings: '',
        pdf_file_path: '/comics/favorite.pdf',
        pdf_file_name: 'favorite.pdf',
        pdf_file_size: 2048000,
        is_pdf_comic: true,
    },
};

const mockNonFavoriteEntry: LibraryEntry = {
    ...mockFavoriteEntry,
    id: 2,
    is_favorite: false,
    comic: {
        ...mockFavoriteEntry.comic,
        id: 2,
        slug: 'non-favorite-comic',
        title: 'Non-Favorite Comic',
    },
};

const mockOnToggleFavorite = vi.fn();

describe('FavoritesList', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders favorites list header correctly', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Favorite Comics')).toBeInTheDocument();
        expect(screen.getByText('1 favorite')).toBeInTheDocument();
    });

    it('shows correct count for multiple favorites', () => {
        const multipleFavorites = [mockFavoriteEntry, { ...mockFavoriteEntry, id: 2 }];
        
        render(
            <FavoritesList
                entries={multipleFavorites}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('2 favorites')).toBeInTheDocument();
    });

    it('filters to show only favorite entries', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry, mockNonFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();
        expect(screen.queryByText('Non-Favorite Comic')).not.toBeInTheDocument();
        expect(screen.getByText('1 favorite')).toBeInTheDocument();
    });

    it('displays favorite comics in grid view', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();
        expect(screen.getByText('Favorite Author')).toBeInTheDocument();
        expect(screen.getByText('4.8')).toBeInTheDocument();
        expect(screen.getByText('1h 0m')).toBeInTheDocument();
    });

    it('displays favorite comics in list view', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();
        expect(screen.getByText('Favorite Author')).toBeInTheDocument();
        expect(screen.getByText('Adventure')).toBeInTheDocument();
        expect(screen.getByText('100% complete')).toBeInTheDocument();
    });

    it('shows heart icon for favorite comics', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const heartIcons = document.querySelectorAll('.text-red-500.fill-current');
        expect(heartIcons.length).toBeGreaterThan(0);
    });

    it('handles search functionality', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const searchInput = screen.getByPlaceholderText('Search favorites...');
        fireEvent.change(searchInput, { target: { value: 'Favorite' } });

        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();

        fireEvent.change(searchInput, { target: { value: 'NonExistent' } });
        expect(screen.queryByText('Favorite Comic')).not.toBeInTheDocument();
    });

    it('handles genre filtering', () => {
        const multipleGenres = [
            mockFavoriteEntry,
            {
                ...mockFavoriteEntry,
                id: 2,
                comic: { ...mockFavoriteEntry.comic, id: 2, genre: 'Action', title: 'Action Comic' },
            },
        ];

        render(
            <FavoritesList
                entries={multipleGenres}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const genreSelect = screen.getByDisplayValue('All Genres');
        fireEvent.change(genreSelect, { target: { value: 'Adventure' } });

        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();
        expect(screen.queryByText('Action Comic')).not.toBeInTheDocument();
    });

    it('handles sorting by title', () => {
        const multipleComics = [
            mockFavoriteEntry,
            {
                ...mockFavoriteEntry,
                id: 2,
                comic: { ...mockFavoriteEntry.comic, id: 2, title: 'Another Comic' },
            },
        ];

        render(
            <FavoritesList
                entries={multipleComics}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const sortSelect = screen.getByDisplayValue('Date Added');
        fireEvent.change(sortSelect, { target: { value: 'title' } });

        // Should still show both comics
        expect(screen.getByText('Favorite Comic')).toBeInTheDocument();
        expect(screen.getByText('Another Comic')).toBeInTheDocument();
    });

    it('toggles sort order', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const sortOrderButton = screen.getByTitle('Sort ascending');
        fireEvent.click(sortOrderButton);

        expect(screen.getByTitle('Sort descending')).toBeInTheDocument();
    });

    it('calls onToggleFavorite when favorite button is clicked', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const favoriteButton = screen.getByTitle('Remove from favorites');
        fireEvent.click(favoriteButton);

        expect(mockOnToggleFavorite).toHaveBeenCalledWith(1);
    });

    it('shows loading state', () => {
        render(
            <FavoritesList
                entries={[]}
                viewMode="grid"
                loading={true}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const skeletons = screen.getAllByRole('generic').filter(el => 
            el.classList.contains('animate-pulse')
        );
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no favorites', () => {
        render(
            <FavoritesList
                entries={[]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('No favorite comics yet')).toBeInTheDocument();
        expect(screen.getByText('Mark comics as favorites to see them here')).toBeInTheDocument();
    });

    it('shows empty state when search has no results', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const searchInput = screen.getByPlaceholderText('Search favorites...');
        fireEvent.change(searchInput, { target: { value: 'NonExistent' } });

        expect(screen.getByText('No matching favorites')).toBeInTheDocument();
        expect(screen.getByText('Try adjusting your search or filters')).toBeInTheDocument();
    });

    it('displays user rating correctly', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Your rating:')).toBeInTheDocument();
        
        // Should show 5 filled stars
        const filledStars = document.querySelectorAll('.text-yellow-400.fill-current');
        expect(filledStars.length).toBeGreaterThan(0);
    });

    it('displays access type badges', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('Owned')).toBeInTheDocument();
    });

    it('shows completion percentage in list view', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('100% complete')).toBeInTheDocument();
    });

    it('displays reading time correctly', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText('1h 0m read')).toBeInTheDocument();
    });

    it('shows date added and last read in list view', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="list"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        expect(screen.getByText(/Added Jan 1, 2024/)).toBeInTheDocument();
        expect(screen.getByText(/Last read Jan 15, 2024/)).toBeInTheDocument();
    });

    it('links to comic pages correctly', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const comicLinks = screen.getAllByRole('link');
        const comicLink = comicLinks.find(link => 
            link.getAttribute('href') === '/comics/favorite-comic'
        );
        expect(comicLink).toBeInTheDocument();
    });

    it('shows progress bar for partially completed comics', () => {
        const partiallyReadEntry = {
            ...mockFavoriteEntry,
            completion_percentage: 60,
        };

        render(
            <FavoritesList
                entries={[partiallyReadEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const progressBars = document.querySelectorAll('[style*="width: 60%"]');
        expect(progressBars.length).toBeGreaterThan(0);
    });

    it('displays cover images correctly', () => {
        render(
            <FavoritesList
                entries={[mockFavoriteEntry]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        const coverImage = screen.getByAltText('Favorite Comic');
        expect(coverImage).toBeInTheDocument();
        expect(coverImage.getAttribute('src')).toBe('https://example.com/favorite-cover.jpg');
    });

    it('shows fallback when no cover image', () => {
        const entryWithoutCover = {
            ...mockFavoriteEntry,
            comic: {
                ...mockFavoriteEntry.comic,
                cover_image_url: undefined,
            },
        };

        render(
            <FavoritesList
                entries={[entryWithoutCover]}
                viewMode="grid"
                loading={false}
                onToggleFavorite={mockOnToggleFavorite}
            />
        );

        // Should show BookOpen icon as fallback
        const fallbackIcons = document.querySelectorAll('svg');
        const bookOpenIcon = Array.from(fallbackIcons).find(icon => 
            icon.classList.contains('h-16') && icon.classList.contains('w-16')
        );
        expect(bookOpenIcon).toBeInTheDocument();
    });
});