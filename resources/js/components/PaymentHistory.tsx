import React, { useState, useEffect } from 'react';
import { 
    Receipt, 
    Download, 
    RefreshCw, 
    Filter, 
    Calendar,
    CreditCard,
    AlertCircle,
    CheckCircle,
    Clock,
    XCircle
} from 'lucide-react';

interface Payment {
    id: string;
    status: 'pending' | 'succeeded' | 'failed' | 'canceled' | 'refunded';
    type: string;
    amount: string;
    refund_amount?: string;
    currency: string;
    paid_at?: string;
    refunded_at?: string;
    comic?: {
        id: number;
        title: string;
        slug: string;
        cover_image_path?: string;
    };
}

interface PaymentHistoryFilters {
    status?: string;
    payment_type?: string;
    from_date?: string;
    to_date?: string;
}

interface PaymentHistoryProps {
    className?: string;
}

const PaymentHistory: React.FC<PaymentHistoryProps> = ({ className = '' }) => {
    const [payments, setPayments] = useState<Payment[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [filters, setFilters] = useState<PaymentHistoryFilters>({});
    const [showFilters, setShowFilters] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    useEffect(() => {
        fetchPaymentHistory();
    }, [filters, currentPage]);

    const fetchPaymentHistory = async () => {
        try {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams({
                page: currentPage.toString(),
                per_page: '20',
                ...filters
            });

            const response = await fetch(`/api/payments/history?${params}`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch payment history');
            }

            const data = await response.json();
            setPayments(data.payments);
            setTotalPages(data.pagination.last_page);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load payment history');
        } finally {
            setLoading(false);
        }
    };

    const downloadReceipt = async (paymentId: string) => {
        try {
            const response = await fetch(`/api/payments/${paymentId}/receipt`, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/pdf',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to download receipt');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `receipt-${paymentId}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (err) {
            console.error('Failed to download receipt:', err);
        }
    };

    const getStatusIcon = (status: Payment['status']) => {
        switch (status) {
            case 'succeeded':
                return <CheckCircle className="h-5 w-5 text-emerald-400" />;
            case 'pending':
                return <Clock className="h-5 w-5 text-yellow-400" />;
            case 'failed':
            case 'canceled':
                return <XCircle className="h-5 w-5 text-red-400" />;
            case 'refunded':
                return <RefreshCw className="h-5 w-5 text-blue-400" />;
            default:
                return <AlertCircle className="h-5 w-5 text-gray-400" />;
        }
    };

    const getStatusColor = (status: Payment['status']) => {
        switch (status) {
            case 'succeeded':
                return 'text-emerald-400';
            case 'pending':
                return 'text-yellow-400';
            case 'failed':
            case 'canceled':
                return 'text-red-400';
            case 'refunded':
                return 'text-blue-400';
            default:
                return 'text-gray-400';
        }
    };

    const formatDate = (dateString?: string) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const handleFilterChange = (key: keyof PaymentHistoryFilters, value: string) => {
        setFilters(prev => ({
            ...prev,
            [key]: value || undefined
        }));
        setCurrentPage(1);
    };

    const clearFilters = () => {
        setFilters({});
        setCurrentPage(1);
    };

    if (loading && payments.length === 0) {
        return (
            <div className={`bg-gray-900 rounded-xl border border-gray-700 p-6 ${className}`}>
                <div className="animate-pulse space-y-4">
                    <div className="h-6 bg-gray-700 rounded w-1/4"></div>
                    <div className="space-y-3">
                        {[...Array(5)].map((_, i) => (
                            <div key={i} className="h-16 bg-gray-800 rounded"></div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className={`bg-gray-900 rounded-xl border border-gray-700 ${className}`}>
            {/* Header */}
            <div className="p-6 border-b border-gray-700">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <Receipt className="h-6 w-6 text-emerald-400" />
                        <h2 className="text-xl font-bold text-white">Payment History</h2>
                    </div>
                    <div className="flex items-center space-x-2">
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className={`p-2 rounded-lg transition-colors ${
                                showFilters 
                                    ? 'bg-emerald-600 text-white' 
                                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                            }`}
                        >
                            <Filter className="h-4 w-4" />
                        </button>
                        <button
                            onClick={fetchPaymentHistory}
                            disabled={loading}
                            className="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition-colors disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        </button>
                    </div>
                </div>

                {/* Filters */}
                {showFilters && (
                    <div className="mt-4 p-4 bg-gray-800/50 rounded-lg space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Status
                                </label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="succeeded">Succeeded</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Type
                                </label>
                                <select
                                    value={filters.payment_type || ''}
                                    onChange={(e) => handleFilterChange('payment_type', e.target.value)}
                                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                >
                                    <option value="">All Types</option>
                                    <option value="single">Single Purchase</option>
                                    <option value="bundle">Bundle Purchase</option>
                                    <option value="subscription">Subscription</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    From Date
                                </label>
                                <input
                                    type="date"
                                    value={filters.from_date || ''}
                                    onChange={(e) => handleFilterChange('from_date', e.target.value)}
                                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    To Date
                                </label>
                                <input
                                    type="date"
                                    value={filters.to_date || ''}
                                    onChange={(e) => handleFilterChange('to_date', e.target.value)}
                                    className="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <button
                                onClick={clearFilters}
                                className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="p-6">
                {error && (
                    <div className="mb-4 p-4 bg-red-900/20 border border-red-800 rounded-lg">
                        <div className="flex items-center space-x-2 text-red-400">
                            <AlertCircle className="h-5 w-5" />
                            <span className="font-medium">Error</span>
                        </div>
                        <p className="mt-1 text-sm text-red-300">{error}</p>
                    </div>
                )}

                {payments.length === 0 ? (
                    <div className="text-center py-12">
                        <Receipt className="h-12 w-12 text-gray-600 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-400 mb-2">
                            No Payment History
                        </h3>
                        <p className="text-gray-500">
                            Your payment history will appear here once you make a purchase.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {payments.map((payment) => (
                            <div
                                key={payment.id}
                                className="bg-gray-800/50 rounded-lg p-4 border border-gray-700 hover:border-gray-600 transition-colors"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-4">
                                        {payment.comic?.cover_image_path && (
                                            <img
                                                src={payment.comic.cover_image_path}
                                                alt={payment.comic.title}
                                                className="w-12 h-16 object-cover rounded"
                                            />
                                        )}
                                        <div>
                                            <div className="flex items-center space-x-2">
                                                {getStatusIcon(payment.status)}
                                                <h3 className="font-semibold text-white">
                                                    {payment.comic?.title || payment.type}
                                                </h3>
                                            </div>
                                            <div className="flex items-center space-x-4 mt-1 text-sm text-gray-400">
                                                <span className={getStatusColor(payment.status)}>
                                                    {payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                                </span>
                                                <span>{payment.type}</span>
                                                <span>{formatDate(payment.paid_at || payment.refunded_at)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center space-x-4">
                                        <div className="text-right">
                                            <div className="font-semibold text-white">
                                                ${payment.amount}
                                            </div>
                                            {payment.refund_amount && (
                                                <div className="text-sm text-blue-400">
                                                    Refunded: ${payment.refund_amount}
                                                </div>
                                            )}
                                        </div>

                                        {payment.status === 'succeeded' && (
                                            <button
                                                onClick={() => downloadReceipt(payment.id)}
                                                className="p-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition-colors"
                                                title="Download Receipt"
                                            >
                                                <Download className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between mt-6 pt-6 border-t border-gray-700">
                        <div className="text-sm text-gray-400">
                            Page {currentPage} of {totalPages}
                        </div>
                        <div className="flex space-x-2">
                            <button
                                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                disabled={currentPage === 1}
                                className="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                Previous
                            </button>
                            <button
                                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                disabled={currentPage === totalPages}
                                className="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default PaymentHistory;