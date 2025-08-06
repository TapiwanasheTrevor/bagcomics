import React, { useState, useEffect, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { 
    CreditCard, 
    BookOpen, 
    Calendar, 
    DollarSign, 
    Download, 
    RefreshCw,
    CheckCircle,
    XCircle,
    Clock,
    AlertCircle,
    Filter,
    Search,
    Receipt,
    TrendingUp,
    Package,
    Gift
} from 'lucide-react';

interface Payment {
    id: number;
    comic_id: number;
    amount: number;
    refund_amount?: number;
    currency: string;
    status: 'succeeded' | 'pending' | 'failed' | 'canceled' | 'refunded';
    payment_type: 'single' | 'bundle' | 'subscription';
    subscription_type?: string;
    bundle_discount_percent?: number;
    paid_at?: string;
    refunded_at?: string;
    failure_reason?: string;
    created_at: string;
    comic: {
        id: number;
        title: string;
        slug: string;
        author?: string;
        cover_image_url?: string;
        publisher?: string;
    };
}

interface PurchaseStats {
    total_spent: number;
    total_purchases: number;
    successful_purchases: number;
    refunded_amount: number;
    average_purchase: number;
    most_expensive_purchase: number;
    favorite_payment_type: string;
}

interface PurchaseHistoryProps {
    className?: string;
}

const PurchaseHistory: React.FC<PurchaseHistoryProps> = ({ className = '' }) => {
    const [payments, setPayments] = useState<Payment[]>([]);
    const [stats, setStats] = useState<PurchaseStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [timeRange, setTimeRange] = useState<'week' | 'month' | 'year' | 'all'>('all');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [typeFilter, setTypeFilter] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {
        fetchPurchaseHistory();
        fetchPurchaseStats();
    }, [timeRange]);

    const fetchPurchaseHistory = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                time_range: timeRange,
                limit: '100'
            });

            if (statusFilter) params.append('status', statusFilter);
            if (typeFilter) params.append('type', typeFilter);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/purchase-history?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) throw new Error('Failed to fetch purchase history');

            const data = await response.json();
            setPayments(data.payments || []);
        } catch (error) {
            console.error('Error fetching purchase history:', error);
            setPayments([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchPurchaseStats = async () => {
        try {
            const params = new URLSearchParams({
                time_range: timeRange
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/api/purchase-stats?${params}`, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
            });

            if (!response.ok) throw new Error('Failed to fetch purchase stats');

            const data = await response.json();
            setStats(data.stats);
        } catch (error) {
            console.error('Error fetching purchase stats:', error);
            setStats(null);
        }
    };

    const filteredPayments = useMemo(() => {
        let filtered = payments;

        // Apply search filter
        if (searchQuery) {
            filtered = filtered.filter(payment =>
                payment.comic.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                payment.comic.author?.toLowerCase().includes(searchQuery.toLowerCase()) ||
                payment.comic.publisher?.toLowerCase().includes(searchQuery.toLowerCase())
            );
        }

        // Apply status filter
        if (statusFilter) {
            filtered = filtered.filter(payment => payment.status === statusFilter);
        }

        // Apply type filter
        if (typeFilter) {
            filtered = filtered.filter(payment => payment.payment_type === typeFilter);
        }

        return filtered;
    }, [payments, searchQuery, statusFilter, typeFilter]);

    const formatCurrency = (amount: number, currency = 'USD'): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'succeeded':
                return <CheckCircle className="h-5 w-5 text-green-400" />;
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

    const getStatusColor = (status: string): string => {
        switch (status) {
            case 'succeeded':
                return 'text-green-400 bg-green-500/20 border-green-500/30';
            case 'pending':
                return 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30';
            case 'failed':
            case 'canceled':
                return 'text-red-400 bg-red-500/20 border-red-500/30';
            case 'refunded':
                return 'text-blue-400 bg-blue-500/20 border-blue-500/30';
            default:
                return 'text-gray-400 bg-gray-500/20 border-gray-500/30';
        }
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'single':
                return <BookOpen className="h-4 w-4" />;
            case 'bundle':
                return <Package className="h-4 w-4" />;
            case 'subscription':
                return <Gift className="h-4 w-4" />;
            default:
                return <CreditCard className="h-4 w-4" />;
        }
    };

    const PaymentCard: React.FC<{ payment: Payment }> = ({ payment }) => (
        <div className="bg-gray-800 rounded-xl border border-gray-700/50 hover:border-emerald-500/50 transition-all duration-300 p-6">
            <div className="flex items-start space-x-4">
                {/* Comic Cover */}
                <Link href={`/comics/${payment.comic.slug}`} className="flex-shrink-0">
                    {payment.comic.cover_image_url ? (
                        <img
                            src={payment.comic.cover_image_url}
                            alt={payment.comic.title}
                            className="w-16 h-24 object-cover rounded-lg"
                        />
                    ) : (
                        <div className="w-16 h-24 bg-gradient-to-br from-gray-700 to-gray-800 rounded-lg flex items-center justify-center">
                            <BookOpen className="h-8 w-8 text-gray-500" />
                        </div>
                    )}
                </Link>

                {/* Payment Details */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between">
                        <div className="flex-1 min-w-0">
                            <Link 
                                href={`/comics/${payment.comic.slug}`}
                                className="font-semibold text-white hover:text-emerald-400 transition-colors line-clamp-1"
                            >
                                {payment.comic.title}
                            </Link>
                            {payment.comic.author && (
                                <p className="text-sm text-gray-400 mt-1">{payment.comic.author}</p>
                            )}

                            {/* Payment Info */}
                            <div className="flex items-center space-x-4 mt-3">
                                <div className="flex items-center space-x-2">
                                    {getStatusIcon(payment.status)}
                                    <span className={`px-2 py-1 text-xs font-medium rounded-full border ${getStatusColor(payment.status)}`}>
                                        {payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                    </span>
                                </div>

                                <div className="flex items-center space-x-1 text-sm text-gray-400">
                                    {getTypeIcon(payment.payment_type)}
                                    <span>
                                        {payment.payment_type === 'single' ? 'Single Purchase' :
                                         payment.payment_type === 'bundle' ? 'Bundle' : 'Subscription'}
                                    </span>
                                </div>

                                <div className="flex items-center space-x-1 text-sm text-gray-400">
                                    <Calendar className="h-4 w-4" />
                                    <span>{formatDate(payment.created_at)}</span>
                                </div>
                            </div>

                            {/* Additional Details */}
                            <div className="mt-2 space-y-1">
                                {payment.bundle_discount_percent && (
                                    <p className="text-xs text-green-400">
                                        Bundle discount: {payment.bundle_discount_percent}% off
                                    </p>
                                )}
                                {payment.failure_reason && (
                                    <p className="text-xs text-red-400">
                                        Failure reason: {payment.failure_reason}
                                    </p>
                                )}
                                {payment.refunded_at && (
                                    <p className="text-xs text-blue-400">
                                        Refunded on {formatDate(payment.refunded_at)}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Amount */}
                        <div className="text-right ml-4">
                            <div className="text-lg font-bold text-white">
                                {formatCurrency(payment.amount, payment.currency)}
                            </div>
                            {payment.refund_amount && payment.refund_amount > 0 && (
                                <div className="text-sm text-blue-400">
                                    -{formatCurrency(payment.refund_amount, payment.currency)} refunded
                                </div>
                            )}
                            <div className="text-xs text-gray-500 mt-1">
                                Payment #{payment.id}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    const StatsCard: React.FC<{ 
        title: string; 
        value: string | number; 
        icon: React.ReactNode; 
        color: string;
        subtitle?: string;
    }> = ({ title, value, icon, color, subtitle }) => (
        <div className="bg-gray-800/50 rounded-lg p-6">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-gray-400 mb-1">{title}</p>
                    <p className={`text-2xl font-bold ${color}`}>{value}</p>
                    {subtitle && (
                        <p className="text-xs text-gray-500 mt-1">{subtitle}</p>
                    )}
                </div>
                <div className={`p-3 rounded-lg ${color.replace('text-', 'bg-').replace('400', '500/20')}`}>
                    {icon}
                </div>
            </div>
        </div>
    );

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-2xl font-bold text-white mb-2 flex items-center space-x-2">
                        <Receipt className="h-6 w-6 text-emerald-400" />
                        <span>Purchase History</span>
                    </h2>
                    <p className="text-gray-400">Track your comic purchases and payments</p>
                </div>

                <div className="flex items-center space-x-2">
                    {/* Time Range Filter */}
                    <select
                        value={timeRange}
                        onChange={(e) => setTimeRange(e.target.value as any)}
                        className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>

            {/* Statistics */}
            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatsCard
                        title="Total Spent"
                        value={formatCurrency(stats.total_spent)}
                        icon={<DollarSign className="h-6 w-6" />}
                        color="text-emerald-400"
                    />
                    <StatsCard
                        title="Total Purchases"
                        value={stats.total_purchases}
                        icon={<CreditCard className="h-6 w-6" />}
                        color="text-blue-400"
                    />
                    <StatsCard
                        title="Success Rate"
                        value={`${Math.round((stats.successful_purchases / stats.total_purchases) * 100)}%`}
                        icon={<CheckCircle className="h-6 w-6" />}
                        color="text-green-400"
                        subtitle={`${stats.successful_purchases} successful`}
                    />
                    <StatsCard
                        title="Average Purchase"
                        value={formatCurrency(stats.average_purchase)}
                        icon={<TrendingUp className="h-6 w-6" />}
                        color="text-purple-400"
                    />
                </div>
            )}

            {/* Filters */}
            <div className="flex flex-col sm:flex-row gap-4">
                {/* Search */}
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search purchases..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 bg-gray-800/50 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    />
                </div>

                {/* Status Filter */}
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">All Status</option>
                    <option value="succeeded">Successful</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>

                {/* Type Filter */}
                <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value)}
                    className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">All Types</option>
                    <option value="single">Single Purchase</option>
                    <option value="bundle">Bundle</option>
                    <option value="subscription">Subscription</option>
                </select>
            </div>

            {/* Purchase History */}
            {loading ? (
                <div className="space-y-4">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="animate-pulse bg-gray-800 rounded-xl p-6">
                            <div className="flex items-center space-x-4">
                                <div className="w-16 h-24 bg-gray-700 rounded-lg"></div>
                                <div className="flex-1">
                                    <div className="h-4 bg-gray-700 rounded mb-2"></div>
                                    <div className="h-3 bg-gray-700 rounded w-1/2 mb-2"></div>
                                    <div className="h-3 bg-gray-700 rounded w-1/3"></div>
                                </div>
                                <div className="w-20 h-8 bg-gray-700 rounded"></div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : filteredPayments.length > 0 ? (
                <div className="space-y-4">
                    {filteredPayments.map((payment) => (
                        <PaymentCard key={payment.id} payment={payment} />
                    ))}
                </div>
            ) : (
                <div className="text-center py-12">
                    <Receipt className="h-16 w-16 text-gray-500 mx-auto mb-4" />
                    <h3 className="text-xl font-semibold text-gray-300 mb-2">
                        {searchQuery || statusFilter || typeFilter ? 'No matching purchases' : 'No purchases yet'}
                    </h3>
                    <p className="text-gray-500 mb-6">
                        {searchQuery || statusFilter || typeFilter 
                            ? 'Try adjusting your search or filters'
                            : 'Start purchasing comics to see your payment history here'
                        }
                    </p>
                    {!searchQuery && !statusFilter && !typeFilter && (
                        <Link
                            href="/comics"
                            className="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-purple-500 text-white font-semibold rounded-xl hover:from-emerald-600 hover:to-purple-600 transition-all duration-300"
                        >
                            <CreditCard className="w-5 h-5" />
                            <span>Browse Comics</span>
                        </Link>
                    )}
                </div>
            )}
        </div>
    );
};

export default PurchaseHistory;