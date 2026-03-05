import React, { useEffect, useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { Comic } from '../types';
import api from '../services/api';

const stripePublishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY as string | undefined;
const stripePromise = stripePublishableKey ? loadStripe(stripePublishableKey) : null;

interface PaymentModalProps {
  comic: Comic;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const PaymentForm: React.FC<{ clientSecret: string; onSuccess: () => void }> = ({ clientSecret, onSuccess }) => {
  const stripe = useStripe();
  const elements = useElements();
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!stripe || !elements) return;

    setIsProcessing(true);
    setError(null);

    const result = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: window.location.href,
      },
      redirect: 'if_required',
    });

    if (result.error) {
      setError(result.error.message || 'Payment failed');
      setIsProcessing(false);
      return;
    }

    if (result.paymentIntent?.status === 'succeeded') {
      try {
        await api.confirmPayment(result.paymentIntent.id);
        onSuccess();
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Payment confirmation failed');
      }
    }

    setIsProcessing(false);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <PaymentElement />
      {error && <p className="text-sm text-red-400">{error}</p>}
      <button
        type="submit"
        disabled={!stripe || isProcessing}
        className="w-full bg-[#DC2626] hover:bg-[#B91C1C] text-white py-3 rounded-lg font-semibold transition-colors disabled:opacity-50"
      >
        {isProcessing ? 'Processing...' : 'Pay now'}
      </button>
    </form>
  );
};

export const PaymentModal: React.FC<PaymentModalProps> = ({ comic, isOpen, onClose, onSuccess }) => {
  const [clientSecret, setClientSecret] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen) return;
    if (!stripePromise) {
      setError('Stripe publishable key is missing.');
      return;
    }

    const createIntent = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const data = await api.createPaymentIntent(comic.slug);
        setClientSecret(data.client_secret);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to create payment intent');
      } finally {
        setIsLoading(false);
      }
    };

    createIntent();
  }, [isOpen, comic.slug]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="w-full max-w-md bg-[#0f0f0f] border border-gray-800 rounded-2xl p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-white">Purchase Comic</h3>
          <button onClick={onClose} className="text-gray-400 hover:text-white">✕</button>
        </div>

        <div className="mb-4 text-sm text-gray-400">
          {comic.title} · ${comic.price?.toFixed(2) || '0.00'}
        </div>

        {error && <p className="text-sm text-red-400 mb-4">{error}</p>}
        {isLoading && <p className="text-sm text-gray-400">Initializing payment...</p>}

        {clientSecret && stripePromise && (
          <Elements stripe={stripePromise} options={{ clientSecret }}>
            <PaymentForm clientSecret={clientSecret} onSuccess={onSuccess} />
          </Elements>
        )}
      </div>
    </div>
  );
};

export default PaymentModal;
