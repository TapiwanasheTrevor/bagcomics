import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { vi } from 'vitest';
import BookmarkManager from '../BookmarkManager';

// Mock fetch
global.fetch = vi.fn();

const mockProps = {
    comicSlug: 'test-comic',
    currentPage: 5,
    onGoToPage: vi.fn(),
    onClose: vi.fn()
};

const mockBookmarks = [
    {
        id: '1',
        page: 3,
        note: 'Interesting scene here',
        created_at: '2024-01-01T10:00:00Z',
        updated_at: '2024-01-01T10:00:00Z'
    },
    {
        id: '2',
        page: 7,
        note: 'Great artwork',
        created_at: '2024-01-01T11:00:00Z',
        updated_at: '2024-01-01T11:00:00Z'
    },
    {
        id: '3',
        page: 12,
        note: '',
        created_at: '2024-01-01T12:00:00Z',
        updated_at: '2024-01-01T12:00:00Z'
    }
];

describe('BookmarkManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('renders bookmark manager interface', async () => {
        (fetch as any).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        expect(screen.getByText('Bookmarks')).toBeInTheDocument();
        expect(screen.getByTitle('Add Bookmark')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search bookmarks...')).toBeInTheDocument();

        await waitFor(() => {
            expect(screen.getByText('Page 3')).toBeInTheDocument();
            expect(screen.getByText('Page 7')).toBeInTheDocument();
            expect(screen.getByText('Page 12')).toBeInTheDocument();
        });
    });

    it('loads bookmarks on mount', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/bookmarks',
                expect.objectContaining({
                    credentials: 'include',
                    headers: expect.objectContaining({
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    })
                })
            );
        });
    });

    it('displays bookmark notes correctly', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Interesting scene here')).toBeInTheDocument();
            expect(screen.getByText('Great artwork')).toBeInTheDocument();
        });
    });

    it('highlights current page bookmark', async () => {
        const bookmarksWithCurrentPage = [
            ...mockBookmarks,
            {
                id: '4',
                page: 5, // Current page
                note: 'Current page bookmark',
                created_at: '2024-01-01T13:00:00Z',
                updated_at: '2024-01-01T13:00:00Z'
            }
        ];

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: bookmarksWithCurrentPage })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            const currentPageBookmark = screen.getByText('Page 5').closest('div');
            expect(currentPageBookmark).toHaveClass('bg-emerald-900/30');
        });
    });

    it('navigates to page when bookmark is clicked', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 3')).toBeInTheDocument();
        });

        const bookmarkButton = screen.getByText('Page 3');
        await user.click(bookmarkButton);

        expect(mockProps.onGoToPage).toHaveBeenCalledWith(3);
    });

    it('shows add bookmark form when add button is clicked', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] })
        });

        render(<BookmarkManager {...mockProps} />);

        const addButton = screen.getByTitle('Add Bookmark');
        await user.click(addButton);

        expect(screen.getByText('Page: 5')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Add a note for this bookmark...')).toBeInTheDocument();
        expect(screen.getByText('Add Bookmark')).toBeInTheDocument();
    });

    it('adds new bookmark successfully', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock)
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: [] })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({
                    id: '5',
                    page: 5,
                    note: 'New bookmark note',
                    created_at: new Date().toISOString()
                })
            });

        render(<BookmarkManager {...mockProps} />);

        // Open add form
        const addButton = screen.getByTitle('Add Bookmark');
        await user.click(addButton);

        // Fill in note
        const noteInput = screen.getByPlaceholderText('Add a note for this bookmark...');
        await user.type(noteInput, 'New bookmark note');

        // Submit
        const submitButton = screen.getByText('Add Bookmark');
        await user.click(submitButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/bookmarks',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json'
                    }),
                    body: JSON.stringify({
                        page_number: 5,
                        note: 'New bookmark note'
                    })
                })
            );
        });
    });

    it('prevents adding bookmark to already bookmarked page', async () => {
        const user = userEvent.setup();
        const bookmarksWithCurrentPage = [
            {
                id: '1',
                page: 5, // Current page
                note: 'Existing bookmark',
                created_at: '2024-01-01T10:00:00Z',
                updated_at: '2024-01-01T10:00:00Z'
            }
        ];

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: bookmarksWithCurrentPage })
        });

        render(<BookmarkManager {...mockProps} />);

        // Open add form
        const addButton = screen.getByTitle('Add Bookmark');
        await user.click(addButton);

        expect(screen.getByText('Already bookmarked')).toBeInTheDocument();
        expect(screen.getByText('Add Bookmark')).toBeDisabled();
    });

    it('edits bookmark note', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock)
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: mockBookmarks })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({
                    ...mockBookmarks[0],
                    note: 'Updated note'
                })
            });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Interesting scene here')).toBeInTheDocument();
        });

        // Click edit button
        const editButtons = screen.getAllByTitle('Edit bookmark');
        await user.click(editButtons[0]);

        // Edit the note
        const editInput = screen.getByDisplayValue('Interesting scene here');
        await user.clear(editInput);
        await user.type(editInput, 'Updated note');

        // Save
        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/bookmarks/1',
                expect.objectContaining({
                    method: 'PATCH',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json'
                    }),
                    body: JSON.stringify({ note: 'Updated note' })
                })
            );
        });
    });

    it('deletes bookmark', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock)
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: mockBookmarks })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({})
            });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 3')).toBeInTheDocument();
        });

        // Click delete button
        const deleteButtons = screen.getAllByTitle('Delete bookmark');
        await user.click(deleteButtons[0]);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/bookmarks/1',
                expect.objectContaining({
                    method: 'DELETE'
                })
            );
        });
    });

    it('filters bookmarks by search term', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Interesting scene here')).toBeInTheDocument();
            expect(screen.getByText('Great artwork')).toBeInTheDocument();
        });

        // Search for "artwork"
        const searchInput = screen.getByPlaceholderText('Search bookmarks...');
        await user.type(searchInput, 'artwork');

        // Should only show the bookmark with "artwork" in the note
        expect(screen.queryByText('Interesting scene here')).not.toBeInTheDocument();
        expect(screen.getByText('Great artwork')).toBeInTheDocument();
    });

    it('searches by page number', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 3')).toBeInTheDocument();
            expect(screen.getByText('Page 7')).toBeInTheDocument();
        });

        // Search for page "7"
        const searchInput = screen.getByPlaceholderText('Search bookmarks...');
        await user.type(searchInput, '7');

        // Should only show page 7 bookmark
        expect(screen.queryByText('Page 3')).not.toBeInTheDocument();
        expect(screen.getByText('Page 7')).toBeInTheDocument();
    });

    it('shows empty state when no bookmarks exist', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('No bookmarks yet')).toBeInTheDocument();
            expect(screen.getByText('Add your first bookmark to get started')).toBeInTheDocument();
        });
    });

    it('shows no results message when search returns empty', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 3')).toBeInTheDocument();
        });

        // Search for something that doesn't exist
        const searchInput = screen.getByPlaceholderText('Search bookmarks...');
        await user.type(searchInput, 'nonexistent');

        expect(screen.getByText('No bookmarks found')).toBeInTheDocument();
        expect(screen.getByText('Try a different search term')).toBeInTheDocument();
    });

    it('displays bookmark count in footer', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('3 bookmarks')).toBeInTheDocument();
        });
    });

    it('shows filtered count when searching', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('3 bookmarks')).toBeInTheDocument();
        });

        // Search to filter results
        const searchInput = screen.getByPlaceholderText('Search bookmarks...');
        await user.type(searchInput, 'artwork');

        expect(screen.getByText('1 found')).toBeInTheDocument();
    });

    it('calls onClose when close button is clicked', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] })
        });

        render(<BookmarkManager {...mockProps} />);

        const closeButton = screen.getByTitle('Close');
        await user.click(closeButton);

        expect(mockProps.onClose).toHaveBeenCalled();
    });

    it('handles API errors gracefully', async () => {
        (fetch as vi.Mock).mockRejectedValue(new Error('API Error'));

        render(<BookmarkManager {...mockProps} />);

        // Should not crash and should show loading state initially
        expect(screen.getByText('Bookmarks')).toBeInTheDocument();
    });

    it('formats dates correctly', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: mockBookmarks })
        });

        render(<BookmarkManager {...mockProps} />);

        await waitFor(() => {
            // Check that dates are formatted (exact format may vary by locale)
            expect(screen.getByText(/Jan 1, 2024/)).toBeInTheDocument();
        });
    });
});