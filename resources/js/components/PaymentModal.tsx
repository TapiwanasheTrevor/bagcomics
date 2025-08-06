import React, { useState, useEffect } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import {
    Elements,
    CardElement,
    PaymentElement,
    useStripe,
    useElements
} from '@stripe/react-stripe-js';
import { X, CreditCard, Lock, AlertCircle, CheckCircle, RefreshCw, Receipt, Download } from 'lucide-react';

// Initialize Stripe
const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY || 'pk_test_51H0LkYH5gAovQC3PEyptqm0gdcEmIUlOzBA8Mtv9C3LvHFVHr273e5z3ZLggyg9vQFGVwZt4bBpACJC6SV16Nnqe00HVabhZkM');

interface Comic {
    id: number;
    title: string;
    author?: string;
    price: number;
    slug: string;
    cover_image_url?: string;
}

interface PaymentModalProps {
    comic: Comic;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

interface PurchaseConfirmationProps {
    payment: PaymentResult;
    comic: Comic;
    onClose: () => void;
    onDownloadReceipt?: () => void;
}

interface PaymentFormProps {
    comic: Comic;
    onSuccess: (payment: PaymentResult) => void;
    onClose: () => void;
}

interface PaymentResult {
    id: string;
    type: string;
    amount: string;
    receipt_url?: string;
}

interface PaymentError {
    code: string;
    message: string;
    retryable: boolean;
}

const PaymentForm: React.FC<PaymentFormProps> = ({ comic, onSuccess, onClose }) => {
    const stripe = useStripe();
    const elements = useElements();
    const [isProcessing, setIsProcessing] = useState(false);
    const [error, setError] = useState<PaymentError | null>(null);
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [paymentIntentId, setPaymentIntentId] = useState<string | null>(null);
    const [retryCount, setRetryCount] = useState(0);
    const [useModernPaymentElement, setUseModernPaymentElement] = useState(true);

    // Create payment intent when component mounts
    useEffect(() => {
        createPaymentIntent();
    }, []);

    const createPaymentIntent = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch(`/api/payments/comics/${comic.slug}/intent`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    return_url: window.location.href
                })
            });

            const data = await response.json();

            if (!response.ok) {
                const errorCode = data.error_code || 'PAYMENT_INTENT_FAILED';
                const retryable = ['NETWORK_ERROR', 'TEMPORARY_FAILURE'].includes(errorCode);
                
                setError({
                    code: errorCode,
                    message: data.error || 'Failed to create payment intent',
                    retryable
                });
                return;
            }

            setClientSecret(data.client_secret);
            setPaymentIntentId(data.payment_intent_id);
            setError(null);
        } catch (err) {
            setError({
                code: 'NETWORK_ERROR',
                message: err instanceof Error ? err.message : 'Failed to initialize payment',
                retryable: true
            });
        }
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!stripe || !elements || !clientSecret) {
            return;
        }

        setIsProcessing(true);
        setError(null);

        try {
            let result;
            
            if (useModernPaymentElement) {
                // Use modern Payment Element for multiple payment methods
                result = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: window.location.href,
                    },
                    redirect: 'if_required',
                });
            } else {
                // Fallback to Card Element
                const cardElement = elements.getElement(CardElement);
                if (!cardElement) {
                    throw new Error('Payment element not found');
                }

                result = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: cardElement,
                    }
                });
            }

            if (result.error) {
                const errorCode = result.error.code || 'PAYMENT_FAILED';
                const retryable = ['card_declined', 'insufficient_funds', 'processing_error'].includes(errorCode);
                
                setError({
                    code: errorCode,
                    message: result.error.message || 'Payment failed',
                    retryable
                });
                return;
            }

            if (result.paymentIntent?.status === 'succeeded') {
                // Confirm payment with our backend
                const confirmData = await confirmPaymentWithBackend(result.paymentIntent.id);
                
                onSuccess({
                    id: confirmData.payment.id,
                    type: confirmData.payment.type,
                    amount: confirmData.payment.amount,
                    receipt_url: result.paymentIntent.charges?.data[0]?.receipt_url
                });
            }
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Payment processing failed';
            setError({
                code: 'PROCESSING_ERROR',
                message: errorMessage,
                retryable: true
            });
        } finally {
            setIsProcessing(false);
        }
    };

    const confirmPaymentWithBackend = async (paymentIntentId: string) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/api/payments/confirm', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({
                payment_intent_id: paymentIntentId
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Failed to confirm payment');
        }

        return data;
    };

    const handleRetry = async () => {
        if (retryCount >= 3) {
            setError({
                code: 'MAX_RETRIES_EXCEEDED',
                message: 'Maximum retry attempts exceeded. Please try again later.',
                retryable: false
            });
            return;
        }

        setRetryCount(prev => prev + 1);
        setError(null);
        await createPaymentIntent();
    };

    const formatPrice = (price: number): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(price);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Comic Info */}
            <div className="flex items-center space-x-4 p-4 bg-gray-800/50 rounded-lg border border-gray-700">
                {comic.cover_image_url && (
                    <img
                        src={comic.cover_image_url}
                        alt={comic.title}
                        className="w-16 h-24 object-cover rounded"
                    />
                )}
                <div className="flex-1">
                    <h3 className="text-lg font-semibold text-white">{comic.title}</h3>
                    {comic.author && (
                        <p className="text-gray-400">by {comic.author}</p>
                    )}
                    <p className="text-2xl font-bold text-emerald-400 mt-2">
                        {formatPrice(comic.price)}
                    </p>
                </div>
            </div>

            {/* Payment Form */}
            <div className="space-y-4">
                <div className="flex items-center space-x-2 text-gray-300">
                    <CreditCard className="h-5 w-5" />
                    <span className="font-medium">Payment Information</span>
                </div>

                <div className="p-4 border border-gray-600 rounded-lg bg-gray-800/30">
                    {useModernPaymentElement ? (
                        <PaymentElement
                            options={{
                                layout: 'tabs',
                                paymentMethodOrder: ['card', 'apple_pay', 'google_pay'],
                            }}
                        />
                    ) : (
                        <CardElement
                            options={{
                                style: {
                                    base: {
                                        fontSize: '16px',
                                        color: '#ffffff',
                                        '::placeholder': {
                                            color: '#9ca3af',
                                        },
                                    },
                                    invalid: {
                                        color: '#ef4444',
                                    },
                                },
                            }}
                        />
                    )}
                </div>

                {/* Payment Method Toggle */}
                <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-400">Payment Methods</span>
                    <button
                        type="button"
                        onClick={() => setUseModernPaymentElement(!useModernPaymentElement)}
                        className="text-emerald-400 hover:text-emerald-300 transition-colors"
                    >
                        {useModernPaymentElement ? 'Use Card Only' : 'More Options'}
                    </button>
                </div>

                {error && (
                    <div className="bg-red-900/20 p-4 rounded-lg border border-red-800 space-y-3">
                        <div className="flex items-center space-x-2 text-red-400">
                            <AlertCircle className="h-5 w-5 flex-shrink-0" />
                            <span className="text-sm font-medium">Payment Error</span>
                        </div>
                        <p className="text-sm text-red-300">{error.message}</p>
                        {error.retryable && (
                            <button
                                type="button"
                                onClick={handleRetry}
                                disabled={retryCount >= 3}
                                className="flex items-center space-x-2 text-sm text-red-400 hover:text-red-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <RefreshCw className="h-4 w-4" />
                                <span>Retry Payment ({3 - retryCount} attempts left)</span>
                            </button>
                        )}
                    </div>
                )}

                <div className="flex items-center space-x-2 text-gray-400 text-sm">
                    <Lock className="h-4 w-4" />
                    <span>Your payment information is secure and encrypted</span>
                </div>
            </div>

            {/* Action Buttons */}
            <div className="flex space-x-3 pt-4">
                <button
                    type="button"
                    onClick={onClose}
                    className="flex-1 px-4 py-3 text-gray-300 bg-gray-700 hover:bg-gray-600 rounded-lg font-medium transition-colors"
                    disabled={isProcessing}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!stripe || isProcessing || !clientSecret}
                    className="flex-1 px-4 py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white rounded-lg font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {isProcessing ? (
                        <div className="flex items-center justify-center space-x-2">
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                            <span>Processing...</span>
                        </div>
                    ) : (
                        `Purchase for ${formatPrice(comic.price)}`
                    )}
                </button>
            </div>
        </form>
    );
};

const PurchaseConfirmation: React.FC<PurchaseConfirmationProps> = ({ 
    payment, 
    comic, 
    onClose, 
    onDownloadReceipt 
}) => {
    const handleDownloadReceipt = async () => {
        if (payment.receipt_url) {
            window.open(payment.receipt_url, '_blank');
        } else if (onDownloadReceipt) {
            onDownloadReceipt();
        }
    };

    return (
        <div className="text-center space-y-6">
            <div className="mx-auto w-20 h-20 bg-emerald-600 rounded-full flex items-center justify-center">
                <CheckCircle className="h-10 w-10 text-white" />
            </div>
            
            <div className="space-y-2">
                <h3 className="text-2xl font-bold text-white">
                    Purchase Successful!
                </h3>
                <p className="text-gray-400">
                    Thank you for your purchase of "{comic.title}"
                </p>
            </div>

            <div className="bg-gray-800/50 rounded-lg p-4 space-y-3">
                <div className="flex justify-between items-center">
                    <span className="text-gray-400">Payment ID:</span>
                    <span className="text-white font-mono text-sm">{payment.id}</span>
                </div>
                <div className="flex justify-between items-center">
                    <span className="text-gray-400">Amount:</span>
                    <span className="text-emerald-400 font-semibold">{payment.amount}</span>
                </div>
                <div className="flex justify-between items-center">
                    <span className="text-gray-400">Type:</span>
                    <span className="text-white">{payment.type}</span>
                </div>
            </div>

            <div className="flex space-x-3">
                {(payment.receipt_url || onDownloadReceipt) && (
                    <button
                        onClick={handleDownloadReceipt}
                        className="flex-1 flex items-center justify-center space-x-2 px-4 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors"
                    >
                        <Receipt className="h-4 w-4" />
                        <span>Receipt</span>
                    </button>
                )}
                <button
                    onClick={onClose}
                    className="flex-1 px-4 py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white rounded-lg font-medium transition-all"
                >
                    Start Reading
                </button>
            </div>
        </div>
    );
};

const PaymentModal: React.FC<PaymentModalProps> = ({ comic, isOpen, onClose, onSuccess }) => {
    const [purchaseResult, setPurchaseResult] = useState<PaymentResult | null>(null);

    const handlePaymentSuccess = (payment: PaymentResult) => {
        setPurchaseResult(payment);
        setTimeout(() => {
            onSuccess();
        }, 3000); // Auto-close after 3 seconds
    };

    const handleClose = () => {
        setPurchaseResult(null);
        onClose();
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className="bg-gray-900 rounded-xl border border-gray-700 w-full max-w-md max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-700">
                    <h2 className="text-xl font-bold text-white">
                        {purchaseResult ? 'Purchase Complete!' : 'Purchase Comic'}
                    </h2>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-white transition-colors"
                        disabled={purchaseResult !== null}
                    >
                        <X className="h-6 w-6" />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6">
                    {purchaseResult ? (
                        <PurchaseConfirmation
                            payment={purchaseResult}
                            comic={comic}
                            onClose={handleClose}
                        />
                    ) : (
                        <Elements 
                            stripe={stripePromise}
                            options={{
                                appearance: {
                                    theme: 'night',
                                    variables: {
                                        colorPrimary: '#10b981',
                                        colorBackground: '#1f2937',
                                        colorText: '#ffffff',
                                        colorDanger: '#ef4444',
                                        fontFamily: 'system-ui, sans-serif',
                                        spacingUnit: '4px',
                                        borderRadius: '8px',
                                    },
                                },
                            }}
                        >
                            <PaymentForm
                                comic={comic}
                                onSuccess={handlePaymentSuccess}
                                onClose={handleClose}
                            />
                        </Elements>
                    )}
                </div>
            </div>
        </div>
    );
};

export default PaymentModal;
