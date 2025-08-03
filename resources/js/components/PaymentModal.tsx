import React, { useState, useEffect } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import {
    Elements,
    CardElement,
    useStripe,
    useElements
} from '@stripe/react-stripe-js';
import { X, CreditCard, Lock, AlertCircle, CheckCircle } from 'lucide-react';

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

interface PaymentFormProps {
    comic: Comic;
    onSuccess: () => void;
    onClose: () => void;
}

const PaymentForm: React.FC<PaymentFormProps> = ({ comic, onSuccess, onClose }) => {
    const stripe = useStripe();
    const elements = useElements();
    const [isProcessing, setIsProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [paymentId, setPaymentId] = useState<string | null>(null);

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
                throw new Error(data.error || 'Failed to create payment intent');
            }

            setClientSecret(data.client_secret);
            setPaymentId(data.payment_id);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to initialize payment');
        }
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!stripe || !elements || !clientSecret) {
            return;
        }

        setIsProcessing(true);
        setError(null);

        const cardElement = elements.getElement(CardElement);

        if (!cardElement) {
            setError('Card element not found');
            setIsProcessing(false);
            return;
        }

        try {
            // Confirm payment with Stripe
            const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement,
                }
            });

            if (stripeError) {
                setError(stripeError.message || 'Payment failed');
                setIsProcessing(false);
                return;
            }

            if (paymentIntent?.status === 'succeeded') {
                // Confirm payment with our backend
                if (paymentId) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    
                    const confirmResponse = await fetch(`/api/payments/${paymentId}/confirm`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || ''
                        }
                    });

                    const confirmData = await confirmResponse.json();

                    if (!confirmResponse.ok) {
                        throw new Error(confirmData.error || 'Failed to confirm payment');
                    }
                }

                onSuccess();
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Payment processing failed');
        } finally {
            setIsProcessing(false);
        }
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
                </div>

                {error && (
                    <div className="flex items-center space-x-2 text-red-400 bg-red-900/20 p-3 rounded-lg border border-red-800">
                        <AlertCircle className="h-5 w-5 flex-shrink-0" />
                        <span className="text-sm">{error}</span>
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

const PaymentModal: React.FC<PaymentModalProps> = ({ comic, isOpen, onClose, onSuccess }) => {
    const [showSuccess, setShowSuccess] = useState(false);

    const handleSuccess = () => {
        setShowSuccess(true);
        setTimeout(() => {
            onSuccess();
            onClose();
            setShowSuccess(false);
        }, 2000);
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className="bg-gray-900 rounded-xl border border-gray-700 w-full max-w-md max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-700">
                    <h2 className="text-xl font-bold text-white">
                        {showSuccess ? 'Payment Successful!' : 'Purchase Comic'}
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-white transition-colors"
                        disabled={showSuccess}
                    >
                        <X className="h-6 w-6" />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6">
                    {showSuccess ? (
                        <div className="text-center space-y-4">
                            <div className="mx-auto w-16 h-16 bg-emerald-600 rounded-full flex items-center justify-center">
                                <CheckCircle className="h-8 w-8 text-white" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-white mb-2">
                                    Thank you for your purchase!
                                </h3>
                                <p className="text-gray-400">
                                    You now have access to "{comic.title}". Redirecting...
                                </p>
                            </div>
                        </div>
                    ) : (
                        <Elements stripe={stripePromise}>
                            <PaymentForm
                                comic={comic}
                                onSuccess={handleSuccess}
                                onClose={onClose}
                            />
                        </Elements>
                    )}
                </div>
            </div>
        </div>
    );
};

export default PaymentModal;
