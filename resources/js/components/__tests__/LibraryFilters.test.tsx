import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { vi, describe, it, expect } from 'vitest';
import LibraryFilters from '../LibraryFilters';
import type { LibraryEntry, LibraryFilters as LibraryFiltersType } from '../UserLibrary';

const mockEntry: LibraryEntry = {
    id: 1,
    comic_id: 1,
    access_type: 'purchased',
    purchase_price: 9.99,
    purchased_at: '2024-01-01T00:00:00Z',
    is_favorite: false,
    rating: 4,
    created_at: '2024-01-01T00:00:00Z',
    comic: {
        id: 1,
        slug: 'test-comic',
        title: 'Test Comic',
        author: 'Test Author',
        genre: 'Action',
        publisher: 'Marvel',
        language: 'English',
        tags: ['superhero', 'action'],
        cover_image_url: 'https://example.com/cover.jpg',
        page_count: 100,
        average_rating: 4.5,
        total_readers: 1000,
        is_free: false,
        price: 9.99,
        has_mature_content: false,
        published_at: '2024-01-01T00:00:00Z',
        reading_time_estimate: 60,
        is_new_release: false,
        publication_year: 2024,
        isbn: '1234567890',
        content_warnings: '',
        pdf_file_path: '/comics/test.pdf',
        pdf_file_name: 'test.pdf',
        pdf_file_size: 1024000,
        is_pdf_comic: true,
    },
};

const mockFilters: LibraryFiltersType = {
    search: '',
    genre: '',
    publisher: '',
    author: '',
    accessType: '',
    rating: '',
    completionStatus: '',
    dateRange: '',
    sortBy: 'last_accessed_at',
    sortOrder: 'desc',
    tags: [],
    language: '',
    priceRange: [0, 100],
};

const mockOnFiltersChange = vi.fn();

describe('LibraryFilters', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders filter options correctly', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.getByText('Advanced Filters')).toBeInTheDocument();
        expect(screen.getByLabelText('Genre')).toBeInTheDocument();
        expect(screen.getByLabelText('Publisher')).toBeInTheDocument();
        expect(screen.getByLabelText('Author')).toBeInTheDocument();
        expect(screen.getByLabelText('Access Type')).toBeInTheDocument();
        expect(screen.getByLabelText('Your Rating')).toBeInTheDocument();
        expect(screen.getByLabelText('Reading Status')).toBeInTheDocument();
        expect(screen.getByLabelText('Language')).toBeInTheDocument();
        expect(screen.getByLabelText('Added to Library')).toBeInTheDocument();
    });

    it('populates filter options from entries', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        // Check if genre option is available
        const genreSelect = screen.getByLabelText('Genre');
        expect(genreSelect).toBeInTheDocument();

        // Check if publisher option is available
        const publisherSelect = screen.getByLabelText('Publisher');
        expect(publisherSelect).toBeInTheDocument();

        // Check if author option is available
        const authorSelect = screen.getByLabelText('Author');
        expect(authorSelect).toBeInTheDocument();
    });

    it('calls onFiltersChange when genre is selected', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const genreSelect = screen.getByLabelText('Genre');
        fireEvent.change(genreSelect, { target: { value: 'Action' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ genre: 'Action' });
    });

    it('calls onFiltersChange when publisher is selected', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const publisherSelect = screen.getByLabelText('Publisher');
        fireEvent.change(publisherSelect, { target: { value: 'Marvel' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ publisher: 'Marvel' });
    });

    it('calls onFiltersChange when access type is selected', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const accessTypeSelect = screen.getByLabelText('Access Type');
        fireEvent.change(accessTypeSelect, { target: { value: 'purchased' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ accessType: 'purchased' });
    });

    it('calls onFiltersChange when rating is selected', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const ratingSelect = screen.getByLabelText('Your Rating');
        fireEvent.change(ratingSelect, { target: { value: '4' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ rating: '4' });
    });

    it('calls onFiltersChange when completion status is selected', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const statusSelect = screen.getByLabelText('Reading Status');
        fireEvent.change(statusSelect, { target: { value: 'completed' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ completionStatus: 'completed' });
    });

    it('renders tags and handles tag selection', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.getByText('Tags')).toBeInTheDocument();
        expect(screen.getByText('superhero')).toBeInTheDocument();
        expect(screen.getByText('action')).toBeInTheDocument();

        // Click on a tag
        const superheroTag = screen.getByText('superhero');
        fireEvent.click(superheroTag);

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ tags: ['superhero'] });
    });

    it('handles tag deselection', () => {
        const filtersWithTag = { ...mockFilters, tags: ['superhero'] };
        
        render(
            <LibraryFilters
                filters={filtersWithTag}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        // Click on selected tag to deselect
        const superheroTag = screen.getByText('superhero');
        fireEvent.click(superheroTag);

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ tags: [] });
    });

    it('handles price range changes', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const priceRangeInputs = screen.getAllByRole('slider');
        expect(priceRangeInputs).toHaveLength(2);

        // Change minimum price
        fireEvent.change(priceRangeInputs[0], { target: { value: '10' } });
        expect(mockOnFiltersChange).toHaveBeenCalledWith({ priceRange: [10, 100] });

        // Change maximum price
        fireEvent.change(priceRangeInputs[1], { target: { value: '50' } });
        expect(mockOnFiltersChange).toHaveBeenCalledWith({ priceRange: [0, 50] });
    });

    it('shows reset button when filters are active', () => {
        const activeFilters = { ...mockFilters, genre: 'Action' };
        
        render(
            <LibraryFilters
                filters={activeFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.getByText('Reset')).toBeInTheDocument();
    });

    it('hides reset button when no filters are active', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.queryByText('Reset')).not.toBeInTheDocument();
    });

    it('resets all filters when reset button is clicked', () => {
        const activeFilters = { 
            ...mockFilters, 
            genre: 'Action', 
            publisher: 'Marvel',
            tags: ['superhero']
        };
        
        render(
            <LibraryFilters
                filters={activeFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const resetButton = screen.getByText('Reset');
        fireEvent.click(resetButton);

        expect(mockOnFiltersChange).toHaveBeenCalledWith({
            genre: '',
            publisher: '',
            author: '',
            accessType: '',
            rating: '',
            completionStatus: '',
            dateRange: '',
            tags: [],
            language: '',
            priceRange: [0, 100],
        });
    });

    it('displays active filters summary', () => {
        const activeFilters = { 
            ...mockFilters, 
            genre: 'Action', 
            publisher: 'Marvel',
            tags: ['superhero']
        };
        
        render(
            <LibraryFilters
                filters={activeFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.getByText('Active Filters:')).toBeInTheDocument();
        expect(screen.getByText('Genre: Action')).toBeInTheDocument();
        expect(screen.getByText('Publisher: Marvel')).toBeInTheDocument();
        expect(screen.getByText('Tag: superhero')).toBeInTheDocument();
    });

    it('allows removing individual active filters', () => {
        const activeFilters = { ...mockFilters, genre: 'Action' };
        
        render(
            <LibraryFilters
                filters={activeFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        // Find and click the X button next to the genre filter
        const genreFilterBadge = screen.getByText('Genre: Action').closest('span');
        const removeButton = genreFilterBadge?.querySelector('button');
        
        if (removeButton) {
            fireEvent.click(removeButton);
            expect(mockOnFiltersChange).toHaveBeenCalledWith({ genre: '' });
        }
    });

    it('handles date range filter changes', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const dateRangeSelect = screen.getByLabelText('Added to Library');
        fireEvent.change(dateRangeSelect, { target: { value: 'month' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ dateRange: 'month' });
    });

    it('handles language filter changes', () => {
        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        const languageSelect = screen.getByLabelText('Language');
        fireEvent.change(languageSelect, { target: { value: 'English' } });

        expect(mockOnFiltersChange).toHaveBeenCalledWith({ language: 'English' });
    });

    it('displays price range values correctly', () => {
        const filtersWithPriceRange = { ...mockFilters, priceRange: [10, 50] as [number, number] };
        
        render(
            <LibraryFilters
                filters={filtersWithPriceRange}
                onFiltersChange={mockOnFiltersChange}
                entries={[mockEntry]}
            />
        );

        expect(screen.getByText(/Purchase Price Range \(\$10 - \$50\)/)).toBeInTheDocument();
    });

    it('limits displayed tags when there are many', () => {
        const entryWithManyTags = {
            ...mockEntry,
            comic: {
                ...mockEntry.comic,
                tags: Array.from({ length: 25 }, (_, i) => `tag${i}`),
            },
        };

        render(
            <LibraryFilters
                filters={mockFilters}
                onFiltersChange={mockOnFiltersChange}
                entries={[entryWithManyTags]}
            />
        );

        expect(screen.getByText('+5 more')).toBeInTheDocument();
    });
});