import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import PurchaseHistory from '../PurchaseHistory';

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

const mockPayment = {
    id: 1,
    comic_id: 1,
    amount: 9.99,
    refund_amount: 0,
    currency: 'USD',
    status: 'succeeded',
    payment_type: 'single',
    subscription_type: null,
    bundle_discount_percent: null,
    paid_at: '2024-01-01T12:00:00Z',
    refunded_at: null,
    failure_reason: null,
    created_at: '2024-01-01T10:00:00Z',
    comic: {
        id: 1,
        title: 'Test Comic',
        slug: 'test-comic',
        author: 'Test Author',
        cover_image_url: 'https://example.com/cover.jpg',
        publisher: 'Test Publisher',
    },
};

const mockPurchaseStats = {
    total_spent: 49.95,
    total_purchases: 5,
    successful_purchases: 4,
    refunded_amount: 9.99,
    average_purchase: 9.99,
    most_expensive_purchase: 19.99,
    favorite_payment_type: 'single',
};

const mockPaymentsResponse = {
    payments: [mockPayment],
};

const mockStatsResponse = {
    stats: mockPurchaseStats,
};

describe('PurchaseHistory', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockPaymentsResponse),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });
    });

    it('renders purchase history header correctly', async () => {
        render(<PurchaseHistory />);

        expect(screen.getByText('Purchase History')).toBeInTheDocument();
        expect(screen.getByText('Track your comic purchases and payments')).toBeInTheDocument();

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    it('renders time range filter', async () => {
        render(<PurchaseHistory />);

        const timeRangeSelect = screen.getByDisplayValue('All Time');
        expect(timeRangeSelect).toBeInTheDocument();

        await waitFor(() => {
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    it('displays purchase statistics correctly', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Total Spent')).toBeInTheDocument();
            expect(screen.getByText('$49.95')).toBeInTheDocument();
            expect(screen.getByText('Total Purchases')).toBeInTheDocument();
            expect(screen.getByText('5')).toBeInTheDocument();
            expect(screen.getByText('Success Rate')).toBeInTheDocument();
            expect(screen.getByText('80%')).toBeInTheDocument();
            expect(screen.getByText('Average Purchase')).toBeInTheDocument();
            expect(screen.getByText('$9.99')).toBeInTheDocument();
        });
    });

    it('displays payment entries correctly', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Test Comic')).toBeInTheDocument();
            expect(screen.getByText('Test Author')).toBeInTheDocument();
            expect(screen.getByText('$9.99')).toBeInTheDocument();
            expect(screen.getByText('Succeeded')).toBeInTheDocument();
            expect(screen.getByText('Single Purchase')).toBeInTheDocument();
        });
    });

    it('shows correct status icons and colors', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const statusBadge = screen.getByText('Succeeded');
            expect(statusBadge).toHaveClass('text-green-400');
        });
    });

    it('handles search functionality', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const searchInput = screen.getByPlaceholderText('Search purchases...');
            fireEvent.change(searchInput, { target: { value: 'Test Comic' } });

            expect(screen.getByText('Test Comic')).toBeInTheDocument();
        });
    });

    it('handles status filtering', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const statusFilter = screen.getByDisplayValue('All Status');
            fireEvent.change(statusFilter, { target: { value: 'succeeded' } });

            expect(screen.getByText('Test Comic')).toBeInTheDocument();
        });
    });

    it('handles type filtering', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const typeFilter = screen.getByDisplayValue('All Types');
            fireEvent.change(typeFilter, { target: { value: 'single' } });

            expect(screen.getByText('Test Comic')).toBeInTheDocument();
        });
    });

    it('changes time range filter', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const timeRangeSelect = screen.getByDisplayValue('All Time');
            fireEvent.change(timeRangeSelect, { target: { value: 'month' } });

            expect(mockFetch).toHaveBeenCalledWith(
                expect.stringContaining('time_range=month'),
                expect.any(Object)
            );
        });
    });

    it('displays failed payment with failure reason', async () => {
        const failedPayment = {
            ...mockPayment,
            id: 2,
            status: 'failed',
            failure_reason: 'Insufficient funds',
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [failedPayment] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Failed')).toBeInTheDocument();
            expect(screen.getByText('Failure reason: Insufficient funds')).toBeInTheDocument();
        });
    });

    it('displays refunded payment correctly', async () => {
        const refundedPayment = {
            ...mockPayment,
            id: 3,
            status: 'refunded',
            refund_amount: 9.99,
            refunded_at: '2024-01-02T12:00:00Z',
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [refundedPayment] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Refunded')).toBeInTheDocument();
            expect(screen.getByText('-$9.99 refunded')).toBeInTheDocument();
            expect(screen.getByText(/Refunded on Jan 2, 2024/)).toBeInTheDocument();
        });
    });

    it('displays bundle payment with discount', async () => {
        const bundlePayment = {
            ...mockPayment,
            id: 4,
            payment_type: 'bundle',
            bundle_discount_percent: 20,
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [bundlePayment] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Bundle')).toBeInTheDocument();
            expect(screen.getByText('Bundle discount: 20% off')).toBeInTheDocument();
        });
    });

    it('shows loading state', () => {
        // Don't resolve the fetch promises immediately
        mockFetch.mockImplementation(() => new Promise(() => {}));

        render(<PurchaseHistory />);

        // Should show loading skeletons
        const skeletons = screen.getAllByRole('generic').filter(el => 
            el.classList.contains('animate-pulse')
        );
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no purchases', async () => {
        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('No purchases yet')).toBeInTheDocument();
            expect(screen.getByText('Start purchasing comics to see your payment history here')).toBeInTheDocument();
        });
    });

    it('shows empty state when search has no results', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const searchInput = screen.getByPlaceholderText('Search purchases...');
            fireEvent.change(searchInput, { target: { value: 'NonExistent' } });

            expect(screen.getByText('No matching purchases')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search or filters')).toBeInTheDocument();
        });
    });

    it('handles API errors gracefully', async () => {
        mockFetch.mockRejectedValue(new Error('API Error'));

        render(<PurchaseHistory />);

        await waitFor(() => {
            // Should not crash and should show empty state
            expect(screen.getByText('No purchases yet')).toBeInTheDocument();
        });
    });

    it('formats currency correctly', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('$9.99')).toBeInTheDocument();
        });
    });

    it('formats dates correctly', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText(/Jan 1, 2024/)).toBeInTheDocument();
        });
    });

    it('displays payment ID', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Payment #1')).toBeInTheDocument();
        });
    });

    it('links to comic pages correctly', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const comicLinks = screen.getAllByRole('link');
            const comicLink = comicLinks.find(link => 
                link.getAttribute('href') === '/comics/test-comic'
            );
            expect(comicLink).toBeInTheDocument();
        });
    });

    it('displays comic cover images', async () => {
        render(<PurchaseHistory />);

        await waitFor(() => {
            const coverImage = screen.getByAltText('Test Comic');
            expect(coverImage).toBeInTheDocument();
            expect(coverImage.getAttribute('src')).toBe('https://example.com/cover.jpg');
        });
    });

    it('shows fallback when no cover image', async () => {
        const paymentWithoutCover = {
            ...mockPayment,
            comic: {
                ...mockPayment.comic,
                cover_image_url: undefined,
            },
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [paymentWithoutCover] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            // Should show BookOpen icon as fallback
            const fallbackIcons = document.querySelectorAll('svg');
            const bookOpenIcon = Array.from(fallbackIcons).find(icon => 
                icon.classList.contains('h-8') && icon.classList.contains('w-8')
            );
            expect(bookOpenIcon).toBeInTheDocument();
        });
    });

    it('displays different payment types with correct icons', async () => {
        const subscriptionPayment = {
            ...mockPayment,
            id: 5,
            payment_type: 'subscription',
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [subscriptionPayment] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Subscription')).toBeInTheDocument();
        });
    });

    it('shows pending payment status', async () => {
        const pendingPayment = {
            ...mockPayment,
            id: 6,
            status: 'pending',
        };

        mockFetch
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ payments: [pendingPayment] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockStatsResponse),
            });

        render(<PurchaseHistory />);

        await waitFor(() => {
            expect(screen.getByText('Pending')).toBeInTheDocument();
            const statusBadge = screen.getByText('Pending');
            expect(statusBadge).toHaveClass('text-yellow-400');
        });
    });
});