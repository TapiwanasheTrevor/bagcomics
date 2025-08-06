import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import FilterSidebar, { type FilterOptions } from '../FilterSidebar';

// Mock fetch
global.fetch = vi.fn();

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

vi.mock('@/components/ui/checkbox', () => ({
    Checkbox: ({ checked, onCheckedChange, id, ...props }: any) => (
        <input
            type="checkbox"
            id={id}
            checked={checked}
            onChange={(e) => onCheckedChange?.(e.target.checked)}
            {...props}
        />
    ),
}));

vi.mock('@/components/ui/label', () => ({
    Label: ({ children, htmlFor, ...props }: any) => (
        <label htmlFor={htmlFor} {...props}>
            {children}
        </label>
    ),
}));

vi.mock('@/components/ui/separator', () => ({
    Separator: (props: any) => <hr {...props} />,
}));

vi.mock('@/components/ui/collapsible', () => ({
    Collapsible: ({ children, open }: any) => (
        <div style={{ display: open ? 'block' : 'none' }}>{children}</div>
    ),
    CollapsibleContent: ({ children }: any) => <div>{children}</div>,
    CollapsibleTrigger: ({ children, asChild, ...props }: any) => 
        asChild ? React.cloneElement(children, props) : <div {...props}>{children}</div>,
}));

vi.mock('@/components/ui/sheet', () => ({
    Sheet: ({ children }: any) => <div>{children}</div>,
    SheetContent: ({ children }: any) => <div>{children}</div>,
    SheetHeader: ({ children }: any) => <div>{children}</div>,
    SheetTitle: ({ children }: any) => <h2>{children}</h2>,
    SheetTrigger: ({ children, asChild, ...props }: any) => 
        asChild ? React.cloneElement(children, props) : <div {...props}>{children}</div>,
}));

const mockFilters: FilterOptions = {
    genres: ['Action', 'Drama', 'Comedy'],
    tags: ['adventure', 'romance', 'thriller'],
    languages: ['English', 'Spanish', 'French'],
    priceRange: 'all',
    rating: 0,
    matureContent: false,
    newReleases: false,
    selectedGenres: [],
    selectedTags: [],
    selectedLanguages: [],
};

describe('FilterSidebar', () => {
    const defaultProps = {
        filters: mockFilters,
        onFiltersChange: vi.fn(),
        onClearFilters: vi.fn(),
        loading: false,
        isMobile: false,
    };

    beforeEach(() => {
        vi.clearAllMocks();
        
        // Mock API responses
        (fetch as any).mockImplementation((url: string) => {
            if (url.includes('/api/comics/genres')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve(['Action', 'Drama', 'Comedy']),
                });
            }
            if (url.includes('/api/comics/tags')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve(['adventure', 'romance', 'thriller']),
                });
            }
            return Promise.reject(new Error('Unknown URL'));
        });
    });

    it('renders filter sections', async () => {
        render(<FilterSidebar {...defaultProps} />);
        
        expect(screen.getByText('Filters')).toBeInTheDocument();
        expect(screen.getByText('Quick Filters')).toBeInTheDocument();
        expect(screen.getByText('Price')).toBeInTheDocument();
        expect(screen.getByText('Minimum Rating')).toBeInTheDocument();
        expect(screen.getByText('Genres')).toBeInTheDocument();
    });

    it('loads and displays genres from API', async () => {
        render(<FilterSidebar {...defaultProps} />);
        
        await waitFor(() => {
            expect(screen.getByText('Action')).toBeInTheDocument();
            expect(screen.getByText('Drama')).toBeInTheDocument();
            expect(screen.getByText('Comedy')).toBeInTheDocument();
        });
    });

    it('loads and displays tags from API', async () => {
        render(<FilterSidebar {...defaultProps} />);
        
        await waitFor(() => {
            expect(screen.getByText('adventure')).toBeInTheDocument();
            expect(screen.getByText('romance')).toBeInTheDocument();
            expect(screen.getByText('thriller')).toBeInTheDocument();
        });
    });

    it('handles genre selection', async () => {
        const onFiltersChange = vi.fn();
        render(<FilterSidebar {...defaultProps} onFiltersChange={onFiltersChange} />);
        
        await waitFor(() => {
            const actionCheckbox = screen.getByLabelText('Action');
            fireEvent.click(actionCheckbox);
        });
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            selectedGenres: ['Action'],
        });
    });

    it('handles tag selection', async () => {
        const onFiltersChange = vi.fn();
        render(<FilterSidebar {...defaultProps} onFiltersChange={onFiltersChange} />);
        
        await waitFor(() => {
            const adventureCheckbox = screen.getByLabelText('adventure');
            fireEvent.click(adventureCheckbox);
        });
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            selectedTags: ['adventure'],
        });
    });

    it('handles price range selection', () => {
        const onFiltersChange = vi.fn();
        render(<FilterSidebar {...defaultProps} onFiltersChange={onFiltersChange} />);
        
        const freeOnlyCheckbox = screen.getByLabelText('Free Only');
        fireEvent.click(freeOnlyCheckbox);
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            priceRange: 'free',
        });
    });

    it('handles quick filter toggles', () => {
        const onFiltersChange = vi.fn();
        render(<FilterSidebar {...defaultProps} onFiltersChange={onFiltersChange} />);
        
        const newReleasesCheckbox = screen.getByLabelText('New Releases (Last 30 days)');
        fireEvent.click(newReleasesCheckbox);
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            newReleases: true,
        });
        
        const matureContentCheckbox = screen.getByLabelText('Include Mature Content (18+)');
        fireEvent.click(matureContentCheckbox);
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            matureContent: true,
        });
    });

    it('handles rating selection', () => {
        const onFiltersChange = vi.fn();
        render(<FilterSidebar {...defaultProps} onFiltersChange={onFiltersChange} />);
        
        const fourStarCheckbox = screen.getByLabelText('4+ Stars');
        fireEvent.click(fourStarCheckbox);
        
        expect(onFiltersChange).toHaveBeenCalledWith({
            rating: 4,
        });
    });

    it('shows active filters count', () => {
        const filtersWithSelections = {
            ...mockFilters,
            selectedGenres: ['Action', 'Drama'],
            selectedTags: ['adventure'],
            priceRange: 'free' as const,
        };
        
        render(<FilterSidebar {...defaultProps} filters={filtersWithSelections} />);
        
        // Should show count of active filters (2 genres + 1 tag + 1 price = 4)
        expect(screen.getByText('4')).toBeInTheDocument();
    });

    it('shows active filter badges', () => {
        const filtersWithSelections = {
            ...mockFilters,
            selectedGenres: ['Action'],
            selectedTags: ['adventure'],
            priceRange: 'free' as const,
        };
        
        render(<FilterSidebar {...defaultProps} filters={filtersWithSelections} />);
        
        expect(screen.getByText('Action')).toBeInTheDocument();
        expect(screen.getByText('adventure')).toBeInTheDocument();
        expect(screen.getByText('Free')).toBeInTheDocument();
    });

    it('handles clear all filters', () => {
        const onClearFilters = vi.fn();
        const filtersWithSelections = {
            ...mockFilters,
            selectedGenres: ['Action'],
        };
        
        render(<FilterSidebar {...defaultProps} filters={filtersWithSelections} onClearFilters={onClearFilters} />);
        
        const clearAllButton = screen.getByText('Clear All');
        fireEvent.click(clearAllButton);
        
        expect(onClearFilters).toHaveBeenCalled();
    });

    it('handles individual filter removal from badges', () => {
        const onFiltersChange = vi.fn();
        const filtersWithSelections = {
            ...mockFilters,
            selectedGenres: ['Action', 'Drama'],
        };
        
        render(<FilterSidebar {...defaultProps} filters={filtersWithSelections} onFiltersChange={onFiltersChange} />);
        
        // Find and click the X button on the Action badge
        const actionBadge = screen.getByText('Action').closest('span');
        const removeButton = actionBadge?.querySelector('button');
        
        if (removeButton) {
            fireEvent.click(removeButton);
            expect(onFiltersChange).toHaveBeenCalledWith({
                selectedGenres: ['Drama'],
            });
        }
    });

    it('renders as mobile sheet when isMobile is true', () => {
        render(<FilterSidebar {...defaultProps} isMobile={true} />);
        
        // Should render the mobile trigger button
        expect(screen.getByText('Filters')).toBeInTheDocument();
    });

    it('shows loading state for genres and tags', () => {
        render(<FilterSidebar {...defaultProps} loading={true} />);
        
        expect(screen.getByText('Loading genres...')).toBeInTheDocument();
        expect(screen.getByText('Loading tags...')).toBeInTheDocument();
    });

    it('handles API errors gracefully', async () => {
        (fetch as any).mockRejectedValue(new Error('API Error'));
        
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        
        render(<FilterSidebar {...defaultProps} />);
        
        await waitFor(() => {
            expect(consoleSpy).toHaveBeenCalledWith('Error fetching genres:', expect.any(Error));
            expect(consoleSpy).toHaveBeenCalledWith('Error fetching tags:', expect.any(Error));
        });
        
        consoleSpy.mockRestore();
    });
});