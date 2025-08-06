import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { vi } from 'vitest';
import ReadingProgressVisualization from '../ReadingProgressVisualization';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { it } from 'node:test';
import { beforeEach } from 'node:test';
import { describe } from 'node:test';

// Mock fetch
global.fetch = vi.fn();

const mockProps = {
    comicSlug: 'test-comic',
    currentPage: 5,
    totalPages: 20
};

const mockProgressData = {
    current_page: 5,
    total_pages: 20,
    progress_percentage: 25,
    reading_time_minutes: 45,
    is_completed: false,
    first_read_at: '2024-01-01T10:00:00Z',
    last_read_at: '2024-01-01T15:00:00Z',
    completed_at: null,
    reading_sessions: [
        {
            id: '1',
            started_at: '2024-01-01T10:00:00Z',
            ended_at: '2024-01-01T10:30:00Z',
            start_page: 1,
            end_page: 3,
            pages_read: 2,
            duration_minutes: 30,
            is_active: false
        },
        {
            id: '2',
            started_at: '2024-01-01T14:00:00Z',
            ended_at: '2024-01-01T14:15:00Z',
            start_page: 3,
            end_page: 5,
            pages_read: 2,
            duration_minutes: 15,
            is_active: false
        }
    ],
    total_reading_sessions: 2,
    average_session_duration: 22.5,
    pages_per_session_avg: 2,
    reading_speed_pages_per_minute: 0.089,
    bookmark_count: 3
};

describe('ReadingProgressVisualization', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders progress visualization interface', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        expect(screen.getByText('Reading Progress')).toBeInTheDocument();
        expect(screen.getByText('Overview')).toBeInTheDocument();
        expect(screen.getByText('Sessions')).toBeInTheDocument();
        expect(screen.getByText('Stats')).toBeInTheDocument();

        await waitFor(() => {
            expect(screen.getByText('25%')).toBeInTheDocument();
            expect(screen.getByText('Page 5 of 20')).toBeInTheDocument();
        });
    });

    it('loads progress data on mount', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                '/api/comics/test-comic/progress',
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

    it('displays progress percentage correctly', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('25%')).toBeInTheDocument();
        });
    });

    it('shows completion status when comic is completed', async () => {
        const completedProgressData = {
            ...mockProgressData,
            is_completed: true,
            progress_percentage: 100,
            completed_at: '2024-01-01T16:00:00Z'
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(completedProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Completed!')).toBeInTheDocument();
        });
    });

    it('displays reading statistics in overview tab', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('45m')).toBeInTheDocument(); // Reading time
            expect(screen.getByText('2')).toBeInTheDocument(); // Sessions
            expect(screen.getByText('3')).toBeInTheDocument(); // Bookmarks
        });
    });

    it('switches between tabs correctly', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('25%')).toBeInTheDocument();
        });

        // Switch to Sessions tab
        const sessionsTab = screen.getByText('Sessions');
        await user.click(sessionsTab);

        expect(screen.getByText('Session #2')).toBeInTheDocument();
        expect(screen.getByText('Session #1')).toBeInTheDocument();

        // Switch to Stats tab
        const statsTab = screen.getByText('Stats');
        await user.click(statsTab);

        expect(screen.getByText('Reading Speed')).toBeInTheDocument();
        expect(screen.getByText('Session Averages')).toBeInTheDocument();
    });

    it('displays reading sessions correctly', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Sessions tab
        const sessionsTab = screen.getByText('Sessions');
        await user.click(sessionsTab);

        await waitFor(() => {
            expect(screen.getByText('Session #2')).toBeInTheDocument();
            expect(screen.getByText('Session #1')).toBeInTheDocument();
            expect(screen.getByText('30m')).toBeInTheDocument(); // Duration
            expect(screen.getByText('15m')).toBeInTheDocument(); // Duration
            expect(screen.getByText('1-3')).toBeInTheDocument(); // Page range
            expect(screen.getByText('3-5')).toBeInTheDocument(); // Page range
        });
    });

    it('shows detailed statistics in stats tab', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Stats tab
        const statsTab = screen.getByText('Stats');
        await user.click(statsTab);

        await waitFor(() => {
            expect(screen.getByText('0.09')).toBeInTheDocument(); // Pages per minute
            expect(screen.getByText('23m')).toBeInTheDocument(); // Average session duration
            expect(screen.getByText('2')).toBeInTheDocument(); // Pages per session
        });
    });

    it('displays timeline information', async () => {
        const user = userEvent.setup();
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockProgressData)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Stats tab
        const statsTab = screen.getByText('Stats');
        await user.click(statsTab);

        await waitFor(() => {
            expect(screen.getByText('Started reading')).toBeInTheDocument();
            expect(screen.getByText('Last read')).toBeInTheDocument();
        });
    });

    it('shows achievement badges', async () => {
        const user = userEvent.setup();
        const progressWithAchievements = {
            ...mockProgressData,
            is_completed: true,
            bookmark_count: 5,
            total_reading_sessions: 10
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithAchievements)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Stats tab
        const statsTab = screen.getByText('Stats');
        await user.click(statsTab);

        await waitFor(() => {
            expect(screen.getByText('Completed')).toBeInTheDocument();
            expect(screen.getByText('Bookworm')).toBeInTheDocument();
            expect(screen.getByText('Dedicated')).toBeInTheDocument();
        });
    });

    it('calculates reading streak correctly', async () => {
        const progressWithStreak = {
            ...mockProgressData,
            reading_sessions: [
                {
                    id: '1',
                    started_at: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(), // Yesterday
                    ended_at: new Date(Date.now() - 24 * 60 * 60 * 1000 + 30 * 60 * 1000).toISOString(),
                    start_page: 1,
                    end_page: 3,
                    pages_read: 2,
                    duration_minutes: 30,
                    is_active: false
                },
                {
                    id: '2',
                    started_at: new Date().toISOString(), // Today
                    ended_at: new Date(Date.now() + 30 * 60 * 1000).toISOString(),
                    start_page: 3,
                    end_page: 5,
                    pages_read: 2,
                    duration_minutes: 30,
                    is_active: false
                }
            ]
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithStreak)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText(/\d+ days?/)).toBeInTheDocument(); // Streak days
        });
    });

    it('shows reading velocity trend', async () => {
        const progressWithVelocity = {
            ...mockProgressData,
            reading_sessions: [
                // Older sessions with lower pages read
                {
                    id: '1',
                    started_at: '2024-01-01T10:00:00Z',
                    ended_at: '2024-01-01T10:30:00Z',
                    start_page: 1,
                    end_page: 2,
                    pages_read: 1,
                    duration_minutes: 30,
                    is_active: false
                },
                {
                    id: '2',
                    started_at: '2024-01-02T10:00:00Z',
                    ended_at: '2024-01-02T10:30:00Z',
                    start_page: 2,
                    end_page: 3,
                    pages_read: 1,
                    duration_minutes: 30,
                    is_active: false
                },
                // Recent sessions with higher pages read
                {
                    id: '3',
                    started_at: '2024-01-03T10:00:00Z',
                    ended_at: '2024-01-03T10:30:00Z',
                    start_page: 3,
                    end_page: 6,
                    pages_read: 3,
                    duration_minutes: 30,
                    is_active: false
                },
                {
                    id: '4',
                    started_at: '2024-01-04T10:00:00Z',
                    ended_at: '2024-01-04T10:30:00Z',
                    start_page: 6,
                    end_page: 9,
                    pages_read: 3,
                    duration_minutes: 30,
                    is_active: false
                }
            ]
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithVelocity)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('Reading Velocity')).toBeInTheDocument();
            expect(screen.getByText(/increasing|decreasing/)).toBeInTheDocument();
        });
    });

    it('handles empty progress data', async () => {
        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(null)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('No reading progress data available')).toBeInTheDocument();
        });
    });

    it('shows loading state initially', () => {
        (fetch as vi.Mock).mockImplementation(() => new Promise(() => {})); // Never resolves

        render(<ReadingProgressVisualization {...mockProps} />);

        expect(screen.getByRole('status', { hidden: true })).toBeInTheDocument(); // Loading spinner
    });

    it('handles API errors gracefully', async () => {
        (fetch as vi.Mock).mockRejectedValue(new Error('API Error'));

        render(<ReadingProgressVisualization {...mockProps} />);

        // Should not crash and should eventually show no data message
        await waitFor(() => {
            expect(screen.getByText('No reading progress data available')).toBeInTheDocument();
        });
    });

    it('formats duration correctly', async () => {
        const progressWithLongDuration = {
            ...mockProgressData,
            reading_time_minutes: 125 // 2h 5m
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithLongDuration)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        await waitFor(() => {
            expect(screen.getByText('2h 5m')).toBeInTheDocument();
        });
    });

    it('shows empty sessions message when no sessions exist', async () => {
        const user = userEvent.setup();
        const progressWithoutSessions = {
            ...mockProgressData,
            reading_sessions: [],
            total_reading_sessions: 0
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithoutSessions)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Sessions tab
        const sessionsTab = screen.getByText('Sessions');
        await user.click(sessionsTab);

        await waitFor(() => {
            expect(screen.getByText('No reading sessions yet')).toBeInTheDocument();
        });
    });

    it('filters out active sessions from display', async () => {
        const user = userEvent.setup();
        const progressWithActiveSessions = {
            ...mockProgressData,
            reading_sessions: [
                ...mockProgressData.reading_sessions,
                {
                    id: '3',
                    started_at: '2024-01-01T16:00:00Z',
                    ended_at: null,
                    start_page: 5,
                    end_page: null,
                    pages_read: 0,
                    duration_minutes: 0,
                    is_active: true
                }
            ]
        };

        (fetch as vi.Mock).mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(progressWithActiveSessions)
        });

        render(<ReadingProgressVisualization {...mockProps} />);

        // Switch to Sessions tab
        const sessionsTab = screen.getByText('Sessions');
        await user.click(sessionsTab);

        await waitFor(() => {
            // Should only show completed sessions
            expect(screen.getByText('Session #2')).toBeInTheDocument();
            expect(screen.getByText('Session #1')).toBeInTheDocument();
            expect(screen.queryByText('Session #3')).not.toBeInTheDocument();
        });
    });
});