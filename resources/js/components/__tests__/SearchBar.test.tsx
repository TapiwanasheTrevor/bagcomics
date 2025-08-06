import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import SearchBar from '../SearchBar';

// Mock lodash debounce
vi.mock('lodash', () => ({
    debounce: (fn: any) => fn, // Return function immediately for testing
}));

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

vi.mock('@/components/ui/separator', () => ({
    Separator: (props: any) => <hr {...props} />,
}));

describe('SearchBar', () => {
    const defaultProps = {
        value: '',
        onChange: vi.fn(),
        onSearch: vi.fn(),
        placeholder: 'Search comics...',
        recentSearches: ['Marvel', 'DC Comics'],
        onRecentSearchClick: vi.fn(),
        onClearRecentSearches: vi.fn(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
        
        // Mock successful API response
        (fetch as any).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                suggestions: [
                    {
                        id: '1',
                        type: 'comic',
                        title: 'Spider-Man',
                        subtitle: 'Marvel Comics',
                        image: 'https://example.com/spiderman.jpg',
                        slug: 'spider-man',
                    },
                    {
                        id: '2',
                        type: 'author',
                        title: 'Stan Lee',
                        subtitle: 'Comic Writer',
                    },
                ],
            }),
        });
    });

    it('renders search input with placeholder', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        expect(input).toBeInTheDocument();
    });

    it('calls onChange when input value changes', () => {
        const onChange = vi.fn();
        render(<SearchBar {...defaultProps} onChange={onChange} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.change(input, { target: { value: 'test search' } });
        
        expect(onChange).toHaveBeenCalledWith('test search');
    });

    it('shows dropdown when input is focused', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Recent')).toBeInTheDocument();
        expect(screen.getByText('Trending')).toBeInTheDocument();
    });

    it('displays recent searches', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Marvel')).toBeInTheDocument();
        expect(screen.getByText('DC Comics')).toBeInTheDocument();
    });

    it('displays trending searches when no input value', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Trending')).toBeInTheDocument();
        // Should show some trending items
        expect(screen.getByText('Marvel')).toBeInTheDocument(); // From trending list
    });

    it('fetches and displays suggestions when typing', async () => {
        render(<SearchBar {...defaultProps} value="spider" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            expect(screen.getByText('Spider-Man')).toBeInTheDocument();
            expect(screen.getByText('Stan Lee')).toBeInTheDocument();
        });
    });

    it('handles suggestion click for comics', async () => {
        // Mock window.location
        delete (window as any).location;
        window.location = { href: '' } as any;
        
        render(<SearchBar {...defaultProps} value="spider" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            const suggestion = screen.getByText('Spider-Man');
            fireEvent.click(suggestion);
        });
        
        expect(window.location.href).toBe('/comics/spider-man');
    });

    it('handles suggestion click for non-comic items', async () => {
        const onSearch = vi.fn();
        render(<SearchBar {...defaultProps} value="stan" onSearch={onSearch} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            const suggestion = screen.getByText('Stan Lee');
            fireEvent.click(suggestion);
        });
        
        expect(onSearch).toHaveBeenCalledWith('Stan Lee');
    });

    it('handles recent search click', () => {
        const onRecentSearchClick = vi.fn();
        render(<SearchBar {...defaultProps} onRecentSearchClick={onRecentSearchClick} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        const recentSearch = screen.getByText('Marvel');
        fireEvent.click(recentSearch);
        
        expect(onRecentSearchClick).toHaveBeenCalledWith('Marvel');
    });

    it('handles clear recent searches', () => {
        const onClearRecentSearches = vi.fn();
        render(<SearchBar {...defaultProps} onClearRecentSearches={onClearRecentSearches} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        const clearButton = screen.getByText('Clear');
        fireEvent.click(clearButton);
        
        expect(onClearRecentSearches).toHaveBeenCalled();
    });

    it('shows clear button when input has value', () => {
        render(<SearchBar {...defaultProps} value="test" />);
        
        const clearButton = screen.getByRole('button');
        expect(clearButton).toBeInTheDocument();
    });

    it('clears input when clear button is clicked', () => {
        const onChange = vi.fn();
        render(<SearchBar {...defaultProps} value="test" onChange={onChange} />);
        
        const clearButton = screen.getByRole('button');
        fireEvent.click(clearButton);
        
        expect(onChange).toHaveBeenCalledWith('');
    });

    it('handles keyboard navigation', async () => {
        render(<SearchBar {...defaultProps} value="spider" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            expect(screen.getByText('Spider-Man')).toBeInTheDocument();
        });
        
        // Test arrow down
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        
        // Test enter key
        fireEvent.keyDown(input, { key: 'Enter' });
        
        // Should trigger search or navigation
        expect(fetch).toHaveBeenCalled();
    });

    it('handles escape key to close dropdown', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Recent')).toBeInTheDocument();
        
        fireEvent.keyDown(input, { key: 'Escape' });
        
        // Dropdown should be closed (Recent text should not be visible)
        expect(screen.queryByText('Recent')).not.toBeInTheDocument();
    });

    it('shows loading state when fetching suggestions', async () => {
        // Mock a delayed response
        (fetch as any).mockImplementation(() => 
            new Promise(resolve => setTimeout(() => resolve({
                ok: true,
                json: () => Promise.resolve({ suggestions: [] }),
            }), 100))
        );
        
        render(<SearchBar {...defaultProps} value="test" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Searching...')).toBeInTheDocument();
    });

    it('handles API errors gracefully', async () => {
        (fetch as any).mockRejectedValue(new Error('API Error'));
        
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        
        render(<SearchBar {...defaultProps} value="test" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            expect(consoleSpy).toHaveBeenCalledWith('Error fetching suggestions:', expect.any(Error));
        });
        
        consoleSpy.mockRestore();
    });

    it('falls back to basic search when suggestions endpoint fails', async () => {
        // Mock suggestions endpoint failure, but basic search success
        (fetch as any).mockImplementation((url: string) => {
            if (url.includes('suggestions')) {
                return Promise.resolve({ ok: false });
            }
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    data: [
                        {
                            id: 1,
                            title: 'Test Comic',
                            author: 'Test Author',
                            slug: 'test-comic',
                        },
                    ],
                }),
            });
        });
        
        render(<SearchBar {...defaultProps} value="test" />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        await waitFor(() => {
            expect(screen.getByText('Test Comic')).toBeInTheDocument();
        });
    });

    it('closes dropdown when clicking outside', () => {
        render(<SearchBar {...defaultProps} />);
        
        const input = screen.getByPlaceholderText('Search comics...');
        fireEvent.focus(input);
        
        expect(screen.getByText('Recent')).toBeInTheDocument();
        
        // Click outside
        fireEvent.mouseDown(document.body);
        
        expect(screen.queryByText('Recent')).not.toBeInTheDocument();
    });
});