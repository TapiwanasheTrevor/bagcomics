import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { vi } from 'vitest';
import SortDropdown, { discoverySortOptions, librarySortOptions } from '../SortDropdown';

// Mock UI components
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: any) => (
        <button onClick={onClick} {...props}>
            {children}
        </button>
    ),
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: ({ children }: any) => <div>{children}</div>,
    DropdownMenuContent: ({ children }: any) => <div role="menu">{children}</div>,
    DropdownMenuItem: ({ children, onClick, ...props }: any) => (
        <div role="menuitem" onClick={onClick} {...props}>
            {children}
        </div>
    ),
    DropdownMenuLabel: ({ children }: any) => <div role="label">{children}</div>,
    DropdownMenuSeparator: () => <hr />,
    DropdownMenuTrigger: ({ children, asChild, ...props }: any) => 
        asChild ? React.cloneElement(children, props) : <div {...props}>{children}</div>,
}));

describe('SortDropdown', () => {
    const defaultProps = {
        value: 'published_at',
        onChange: vi.fn(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders with default sort options', () => {
        render(<SortDropdown {...defaultProps} />);
        
        expect(screen.getByText('Sort by:')).toBeInTheDocument();
        expect(screen.getByText('Newest First')).toBeInTheDocument();
    });

    it('displays selected option correctly', () => {
        render(<SortDropdown {...defaultProps} value="average_rating" />);
        
        expect(screen.getByText('Highest Rated')).toBeInTheDocument();
    });

    it('renders all default sort options in dropdown', () => {
        render(<SortDropdown {...defaultProps} />);
        
        expect(screen.getByText('Sort Comics By')).toBeInTheDocument();
        expect(screen.getByText('Newest First')).toBeInTheDocument();
        expect(screen.getByText('Highest Rated')).toBeInTheDocument();
        expect(screen.getByText('Most Popular')).toBeInTheDocument();
        expect(screen.getByText('Alphabetical')).toBeInTheDocument();
        expect(screen.getByText('Page Count')).toBeInTheDocument();
        expect(screen.getByText('Trending')).toBeInTheDocument();
        expect(screen.getByText('Recently Added')).toBeInTheDocument();
    });

    it('shows descriptions for sort options', () => {
        render(<SortDropdown {...defaultProps} />);
        
        expect(screen.getByText('Recently published comics')).toBeInTheDocument();
        expect(screen.getByText('Best rated by users')).toBeInTheDocument();
        expect(screen.getByText('Most read comics')).toBeInTheDocument();
    });

    it('calls onChange when option is selected', () => {
        const onChange = vi.fn();
        render(<SortDropdown {...defaultProps} onChange={onChange} />);
        
        const ratingOption = screen.getByText('Highest Rated');
        fireEvent.click(ratingOption);
        
        expect(onChange).toHaveBeenCalledWith('average_rating');
    });

    it('highlights selected option', () => {
        render(<SortDropdown {...defaultProps} value="average_rating" />);
        
        const selectedOption = screen.getByText('Highest Rated').closest('div');
        expect(selectedOption).toHaveClass('bg-emerald-500/10', 'text-emerald-400');
    });

    it('shows active indicator for selected option', () => {
        render(<SortDropdown {...defaultProps} value="average_rating" />);
        
        // Should show the green dot indicator
        const indicator = document.querySelector('.bg-emerald-500.rounded-full');
        expect(indicator).toBeInTheDocument();
    });

    it('renders with custom sort options', () => {
        const customOptions = [
            {
                value: 'custom_sort',
                label: 'Custom Sort',
                icon: <span>📊</span>,
                description: 'Custom sorting option',
            },
        ];
        
        render(<SortDropdown {...defaultProps} options={customOptions} />);
        
        expect(screen.getByText('Custom Sort')).toBeInTheDocument();
        expect(screen.getByText('Custom sorting option')).toBeInTheDocument();
    });

    it('shows correct count of sorting options', () => {
        render(<SortDropdown {...defaultProps} />);
        
        expect(screen.getByText('7 sorting options available')).toBeInTheDocument();
    });

    it('renders icons for sort options', () => {
        render(<SortDropdown {...defaultProps} />);
        
        // Check for various icons (they should be rendered as SVG elements)
        const icons = document.querySelectorAll('svg');
        expect(icons.length).toBeGreaterThan(0);
    });

    it('applies custom className', () => {
        render(<SortDropdown {...defaultProps} className="custom-class" />);
        
        const button = screen.getByRole('button');
        expect(button).toHaveClass('custom-class');
    });

    it('handles missing selected option gracefully', () => {
        render(<SortDropdown {...defaultProps} value="non_existent_option" />);
        
        // Should fall back to first option
        expect(screen.getByText('Newest First')).toBeInTheDocument();
    });

    describe('Discovery Sort Options', () => {
        it('contains expected discovery options', () => {
            expect(discoverySortOptions).toHaveLength(5);
            expect(discoverySortOptions[0].value).toBe('published_at');
            expect(discoverySortOptions[1].value).toBe('average_rating');
            expect(discoverySortOptions[2].value).toBe('total_readers');
            expect(discoverySortOptions[3].value).toBe('trending');
            expect(discoverySortOptions[4].value).toBe('title');
        });

        it('renders discovery options correctly', () => {
            render(<SortDropdown {...defaultProps} options={discoverySortOptions} />);
            
            expect(screen.getByText('Newest')).toBeInTheDocument();
            expect(screen.getByText('Top Rated')).toBeInTheDocument();
            expect(screen.getByText('Popular')).toBeInTheDocument();
            expect(screen.getByText('Trending')).toBeInTheDocument();
            expect(screen.getByText('A-Z')).toBeInTheDocument();
        });
    });

    describe('Library Sort Options', () => {
        it('contains expected library options', () => {
            expect(librarySortOptions).toHaveLength(5);
            expect(librarySortOptions[0].value).toBe('recently_added');
            expect(librarySortOptions[1].value).toBe('last_read');
            expect(librarySortOptions[2].value).toBe('progress');
            expect(librarySortOptions[3].value).toBe('title');
            expect(librarySortOptions[4].value).toBe('rating');
        });

        it('renders library options correctly', () => {
            render(<SortDropdown {...defaultProps} options={librarySortOptions} />);
            
            expect(screen.getByText('Recently Added')).toBeInTheDocument();
            expect(screen.getByText('Recently Read')).toBeInTheDocument();
            expect(screen.getByText('Reading Progress')).toBeInTheDocument();
            expect(screen.getByText('Title A-Z')).toBeInTheDocument();
            expect(screen.getByText('My Rating')).toBeInTheDocument();
        });
    });

    it('handles keyboard navigation', () => {
        render(<SortDropdown {...defaultProps} />);
        
        const button = screen.getByRole('button');
        
        // Test Enter key
        fireEvent.keyDown(button, { key: 'Enter' });
        
        // Test Arrow keys
        fireEvent.keyDown(button, { key: 'ArrowDown' });
        fireEvent.keyDown(button, { key: 'ArrowUp' });
    });

    it('shows hover states correctly', () => {
        render(<SortDropdown {...defaultProps} />);
        
        const option = screen.getByText('Highest Rated');
        fireEvent.mouseEnter(option);
        
        expect(option.closest('div')).toHaveClass('hover:bg-gray-700/50');
    });
});