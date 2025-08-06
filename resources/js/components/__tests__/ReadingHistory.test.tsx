import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import ReadingHistory from '../ReadingHistory';

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

const mockReadingSession = {
    id: 1,
    comic_id: 1,
    user_id: 1,
    started_at: '2024-01-15T10:00:00Z',
    ended_at: '2024-01-15T11:30:00Z',
    pages_read: 25,
    reading_time_minutes: 90,
    device_type: 'desktop',
    comic: {
        id: 1,
        title: 'Test Comic',
        slug: 'test-comic',
        author: 'Test Author',
        cover_image_url: 'https://example.com/cover.jpg',
        page_count: 100,
    },
};

const mockReadingStats = {
    total_reading_time: 300, // 5 hours in minutes
    comics_read: 5,
    pages_read: 500,
    average_session_time: 60,
    reading_streak: 7,
    favorite_genre: 'Action',
    most_read_comic: 'Test Comic',
    reading_goals_met: 3,
};

const mockSessionsResponse = {
    sessions: [mockReadingSession],
};

const mockStatsResponse = {
    stats: mockReadingStats,
};

describe('ReadingHistory', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockSessionsResponse),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });
    });

    it('renders reading history header correctly', async () => {
        render(<ReadingHistory />);

        expect(screen.getByText('Reading History')).toBeInTheDocument();
        expect(screen.getByText('Track your reading progress and habits')).toBeInTheDocument();

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    it('renders view mode toggle buttons', async () => {
        render(<ReadingHistory />);

        expect(screen.getByRole('button', { name: 'Sessions' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Statistics' })).toBeInTheDocument();

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    it('renders time range filter', async () => {
        render(<ReadingHistory />);

        const timeRangeSelect = screen.getByDisplayValue('This Month');
        expect(timeRangeSelect).toBeInTheDocument();

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    it('displays reading sessions correctly', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            expect(screen.getByText('Test Comic')).toBeInTheDocument();
            expect(screen.getByText('Test Author')).toBeInTheDocument();
            expect(screen.getByText('1h 30m')).toBeInTheDocument();
            expect(screen.getByText('25 pages')).toBeInTheDocument();
        });
    });

    it('groups sessions by date', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            expect(screen.getByText('Today')).toBeInTheDocument();
            expect(screen.getByText('(1 session)')).toBeInTheDocument();
        });
    });

    it('switches to statistics view', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const statsButton = screen.getByRole('button', { name: 'Statistics' });
            fireEvent.click(statsButton);

            expect(screen.getByText('Total Reading Time')).toBeInTheDocument();
            expect(screen.getByText('5h 0m')).toBeInTheDocument();
            expect(screen.getByText('Comics Read')).toBeInTheDocument();
            expect(screen.getByText('5')).toBeInTheDocument();
        });
    });

    it('displays reading statistics correctly', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const statsButton = screen.getByRole('button', { name: 'Statistics' });
            fireEvent.click(statsButton);

            expect(screen.getByText('500')).toBeInTheDocument(); // Pages read
            expect(screen.getByText('7 days')).toBeInTheDocument(); // Reading streak
            expect(screen.getByText('Action')).toBeInTheDocument(); // Favorite genre
            expect(screen.getByText('3')).toBeInTheDocument(); // Goals achieved
        });
    });

    it('changes time range filter', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const timeRangeSelect = screen.getByDisplayValue('This Month');
            fireEvent.change(timeRangeSelect, { target: { value: 'week' } });

            expect(mockFetch).toHaveBeenCalledWith(
                expect.stringContaining('time_range=week'),
                expect.any(Object)
            );
        });
    });

    it('formats duration correctly', async () => {
        const sessionWithShortTime = {
            ...mockReadingSession,
            reading_time_minutes: 45,
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ sessions: [sessionWithShortTime] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<ReadingHistory />);

        await waitFor(() => {
            expect(screen.getByText('45m')).toBeInTheDocument();
        });
    });

    it('formats dates correctly', async () => {
        const yesterdaySession = {
            ...mockReadingSession,
            started_at: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ sessions: [yesterdaySession] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<ReadingHistory />);

        await waitFor(() => {
            expect(screen.getByText('Yesterday')).toBeInTheDocument();
        });
    });

    it('shows loading state', () => {
        // Don't resolve the fetch promises immediately
        mockFetch.mockImplementation(() => new Promise(() => {}));

        render(<ReadingHistory />);

        // Should show loading skeletons
        const skeletons = screen.getAllByRole('generic').filter(el => 
            el.classList.contains('animate-pulse')
        );
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no sessions', async () => {
        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ sessions: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<ReadingHistory />);

        await waitFor(() => {
            expect(screen.getByText('No Reading History')).toBeInTheDocument();
            expect(screen.getByText('Start reading comics to see your reading history here')).toBeInTheDocument();
        });
    });

    it('handles API errors gracefully', async () => {
        mockFetch.mockRejectedValue(new Error('API Error'));

        render(<ReadingHistory />);

        await waitFor(() => {
            // Should not crash and should show empty state
            expect(screen.getByText('No Reading History')).toBeInTheDocument();
        });
    });

    it('displays most read comic in statistics', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const statsButton = screen.getByRole('button', { name: 'Statistics' });
            fireEvent.click(statsButton);

            expect(screen.getByText('Most Read Comic')).toBeInTheDocument();
            expect(screen.getByText('Test Comic')).toBeInTheDocument();
        });
    });

    it('shows session time in correct format', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            // Should show time in 12-hour format
            expect(screen.getByText(/\d{1,2}:\d{2}\s?(AM|PM)/)).toBeInTheDocument();
        });
    });

    it('links to comic pages correctly', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const comicLinks = screen.getAllByRole('link');
            const comicLink = comicLinks.find(link => 
                link.getAttribute('href') === '/comics/test-comic'
            );
            expect(comicLink).toBeInTheDocument();
        });
    });

    it('displays comic cover images', async () => {
        render(<ReadingHistory />);

        await waitFor(() => {
            const coverImage = screen.getByAltText('Test Comic');
            expect(coverImage).toBeInTheDocument();
            expect(coverImage.getAttribute('src')).toBe('https://example.com/cover.jpg');
        });
    });

    it('shows fallback when no cover image', async () => {
        const sessionWithoutCover = {
            ...mockReadingSession,
            comic: {
                ...mockReadingSession.comic,
                cover_image_url: undefined,
            },
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ sessions: [sessionWithoutCover] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<ReadingHistory />);

        await waitFor(() => {
            // Should show BookOpen icon as fallback
            const fallbackIcons = document.querySelectorAll('svg');
            const bookOpenIcon = Array.from(fallbackIcons).find(icon => 
                icon.classList.contains('h-8') && icon.classList.contains('w-8')
            );
            expect(bookOpenIcon).toBeInTheDocument();
        });
    });
});