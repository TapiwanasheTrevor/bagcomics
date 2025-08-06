import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { vi } from 'vitest';
import EnhancedPdfReader from '../EnhancedPdfReader';

// Mock PDF.js
vi.mock('react-pdf', () => ({
    Document: ({ children, onLoadSuccess, onLoadError }: any) => {
        React.useEffect(() => {
            // Simulate successful PDF load
            setTimeout(() => {
                onLoadSuccess({ numPages: 10 });
            }, 100);
        }, [onLoadSuccess]);

        return <div data-testid="pdf-document">{children}</div>;
    },
    Page: ({ pageNumber, onLoadSuccess }: any) => {
        React.useEffect(() => {
            if (onLoadSuccess) {
                onLoadSuccess();
            }
        }, [onLoadSuccess]);

        return <div data-testid={`pdf-page-${pageNumber}`}>Page {pageNumber}</div>;
    },
    pdfjs: {
        GlobalWorkerOptions: { workerSrc: '' },
        VerbosityLevel: { ERRORS: 0 },
        version: '3.0.0'
    }
}));

// Mock fetch for API calls
global.fetch = vi.fn();

const mockProps = {
    fileUrl: 'https://example.com/test.pdf',
    fileName: 'Test Comic.pdf',
    downloadUrl: 'https://example.com/download/test.pdf',
    userHasDownloadAccess: true,
    comicSlug: 'test-comic',
    initialPage: 1,
    onPageChange: vi.fn(),
    onClose: vi.fn()
};

describe('EnhancedPdfReader', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        (fetch as any).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] })
        });

        // Mock CSRF token
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('renders the PDF reader interface', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        expect(screen.getByText('Test Comic.pdf')).toBeInTheDocument();
        expect(screen.getByTitle('Close Reader')).toBeInTheDocument();
        expect(screen.getByTitle('Previous Page')).toBeInTheDocument();
        expect(screen.getByTitle('Next Page')).toBeInTheDocument();
        expect(screen.getByTitle('Zoom In')).toBeInTheDocument();
        expect(screen.getByTitle('Zoom Out')).toBeInTheDocument();

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });
    });

    it('handles page navigation correctly', async () => {
        const user = userEvent.setup();
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Navigate to next page
        const nextButton = screen.getByTitle('Next Page');
        await user.click(nextButton);

        expect(mockProps.onPageChange).toHaveBeenCalledWith(2);
    });

    it('handles zoom controls correctly', async () => {
        const user = userEvent.setup();
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('120%')).toBeInTheDocument();
        });

        // Zoom in
        const zoomInButton = screen.getByTitle('Zoom In');
        await user.click(zoomInButton);

        expect(screen.getByText('140%')).toBeInTheDocument();

        // Zoom out
        const zoomOutButton = screen.getByTitle('Zoom Out');
        await user.click(zoomOutButton);

        expect(screen.getByText('120%')).toBeInTheDocument();
    });

    it('handles bookmark functionality', async () => {
        const user = userEvent.setup();
        (fetch as any)
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: [] })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: [] })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({
                    id: '1',
                    page: 1,
                    note: 'Bookmark on page 1',
                    created_at: new Date().toISOString()
                })
            });

        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Add bookmark
        const bookmarkButton = screen.getByTitle('Add Bookmark');
        await user.click(bookmarkButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/bookmarks',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json'
                    }),
                    body: JSON.stringify({
                        page_number: 1,
                        note: 'Bookmark on page 1'
                    })
                })
            );
        });
    });

    it('shows bookmark panel when requested', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                data: [
                    {
                        id: '1',
                        page: 1,
                        note: 'Test bookmark',
                        created_at: new Date().toISOString()
                    }
                ]
            })
        });

        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Open bookmark panel
        const bookmarkPanelButton = screen.getByTitle('Show Bookmarks');
        await user.click(bookmarkPanelButton);

        await waitFor(() => {
            expect(screen.getByText('Bookmarks')).toBeInTheDocument();
        });
    });

    it('shows settings panel when requested', async () => {
        const user = userEvent.setup();
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Open settings panel
        const settingsButton = screen.getByTitle('Reader Settings');
        await user.click(settingsButton);

        expect(screen.getByText('Reader Settings')).toBeInTheDocument();
    });

    it('handles auto-play functionality', async () => {
        const user = userEvent.setup();
        vi.useFakeTimers();

        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Start auto-play
        const autoPlayButton = screen.getByTitle('Start Auto-advance');
        await user.click(autoPlayButton);

        // Fast-forward time to trigger auto-advance
        act(() => {
            vi.advanceTimersByTime(5000);
        });

        expect(mockProps.onPageChange).toHaveBeenCalledWith(2);

        vi.useRealTimers();
    });

    it('handles keyboard shortcuts', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Test arrow key navigation
        fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect(mockProps.onPageChange).toHaveBeenCalledWith(2);

        fireEvent.keyDown(document, { key: 'ArrowLeft' });
        expect(mockProps.onPageChange).toHaveBeenCalledWith(1);

        // Test zoom shortcuts
        fireEvent.keyDown(document, { key: '+' });
        expect(screen.getByText('140%')).toBeInTheDocument();

        fireEvent.keyDown(document, { key: '-' });
        expect(screen.getByText('120%')).toBeInTheDocument();
    });

    it('updates reading progress', async () => {
        (fetch as vi.Mock)
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ data: [] })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({
                    current_page: 1,
                    total_pages: 10,
                    progress_percentage: 10,
                    reading_time_minutes: 5,
                    is_completed: false
                })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({})
            });

        const user = userEvent.setup();
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Navigate to next page to trigger progress update
        const nextButton = screen.getByTitle('Next Page');
        await user.click(nextButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/progress',
                expect.objectContaining({
                    method: 'PATCH',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json'
                    }),
                    body: expect.stringContaining('"current_page":2')
                })
            );
        });
    });

    it('handles touch gestures on mobile', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        const container = screen.getByTestId('pdf-document').parentElement;

        // Simulate swipe left (next page)
        fireEvent.touchStart(container!, {
            touches: [{ clientX: 100, clientY: 100 }]
        });

        fireEvent.touchEnd(container!, {
            changedTouches: [{ clientX: 50, clientY: 100 }]
        });

        expect(mockProps.onPageChange).toHaveBeenCalledWith(2);
    });

    it('handles PDF loading errors gracefully', async () => {
        const errorProps = { ...mockProps };
        
        // Mock PDF loading error
        vi.doMock('react-pdf', () => ({
            Document: ({ onLoadError }: any) => {
                React.useEffect(() => {
                    onLoadError(new Error('Failed to load PDF'));
                }, [onLoadError]);
                return <div data-testid="pdf-document">Error</div>;
            },
            Page: () => <div>Page</div>,
            pdfjs: {
                GlobalWorkerOptions: { workerSrc: '' },
                VerbosityLevel: { ERRORS: 0 },
                version: '3.0.0'
            }
        }));

        render(<EnhancedPdfReader {...errorProps} />);

        await waitFor(() => {
            expect(screen.getByText('Failed to Load PDF')).toBeInTheDocument();
        });
    });

    it('calls onClose when close button is clicked', async () => {
        const user = userEvent.setup();
        render(<EnhancedPdfReader {...mockProps} />);

        const closeButton = screen.getByTitle('Close Reader');
        await user.click(closeButton);

        expect(mockProps.onClose).toHaveBeenCalled();
    });

    it('handles escape key to close reader', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        fireEvent.keyDown(document, { key: 'Escape' });

        expect(mockProps.onClose).toHaveBeenCalled();
    });

    it('disables navigation buttons at boundaries', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // At first page, previous buttons should be disabled
        expect(screen.getByTitle('First Page')).toBeDisabled();
        expect(screen.getByTitle('Previous Page')).toBeDisabled();

        // Navigate to last page
        const user = userEvent.setup();
        for (let i = 1; i < 10; i++) {
            const nextButton = screen.getByTitle('Next Page');
            await user.click(nextButton);
        }

        // At last page, next buttons should be disabled
        expect(screen.getByTitle('Next Page')).toBeDisabled();
        expect(screen.getByTitle('Last Page')).toBeDisabled();
    });

    it('shows progress bar with correct percentage', async () => {
        render(<EnhancedPdfReader {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Page 1 of 10')).toBeInTheDocument();
        });

        // Check initial progress (10% for page 1 of 10)
        const progressBar = document.querySelector('[style*="width: 10%"]');
        expect(progressBar).toBeInTheDocument();
    });
});