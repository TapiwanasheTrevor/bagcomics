import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../services/api';

interface Plan {
  id: number;
  slug: string;
  name: string;
  interval: string;
  price: number;
  originalPrice: number | null;
  savingsPercent: number | null;
  description: string;
  features: string[];
  isFeatured: boolean;
}

export const PricingPage: React.FC = () => {
  const navigate = useNavigate();
  const [plans, setPlans] = useState<Plan[]>([]);
  const [loading, setLoading] = useState(true);
  const [subscribing, setSubscribing] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [currentSub, setCurrentSub] = useState<any>(null);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const plansRes = await api.getSubscriptionPlans();
        const plansData = plansRes?.data?.data || plansRes?.data || [];
        setPlans(Array.isArray(plansData) ? plansData : []);

        if (api.isAuthenticated()) {
          try {
            const subRes = await api.getCurrentSubscription();
            setCurrentSub(subRes);
          } catch {}
        }
      } catch (err) {
        console.error('Failed to fetch plans:', err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const handleSubscribe = async (plan: Plan) => {
    if (!api.isAuthenticated()) {
      localStorage.setItem('bag_comics_return_url', '/pricing');
      navigate('/login');
      return;
    }

    if (plan.slug === 'free') return;

    setSubscribing(plan.slug);
    setError(null);

    try {
      const result = await api.createSubscription(plan.interval);

      // Redirect to Stripe or use the client_secret for in-page checkout
      // For now, show that the intent was created successfully
      // In production, integrate with Stripe Elements here
      setError(null);
      setSubscribing(null);

      // If using Stripe redirect checkout:
      if (result.client_secret) {
        // Store for the payment modal
        localStorage.setItem('bag_subscription_secret', result.client_secret);
        localStorage.setItem('bag_subscription_intent', result.payment_intent_id);
        localStorage.setItem('bag_subscription_plan', plan.interval);
        navigate('/pricing?checkout=true');
        window.location.reload(); // Reload to trigger checkout flow
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to start subscription. Please try again.');
      setSubscribing(null);
    }
  };

  const handleCancel = async () => {
    if (!confirm('Are you sure you want to cancel your subscription? You will retain access until the end of your billing period.')) {
      return;
    }

    try {
      const result = await api.cancelSubscription();
      alert(result.message);
      setCurrentSub((prev: any) => prev ? { ...prev, status: 'canceled' } : prev);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to cancel subscription.');
    }
  };

  if (loading) {
    return (
      <div className="max-w-5xl mx-auto px-4 sm:px-6 py-16">
        <div className="text-center animate-pulse">
          <div className="h-8 bg-gray-800 rounded w-64 mx-auto mb-4" />
          <div className="h-4 bg-gray-800 rounded w-96 mx-auto mb-12" />
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {[1, 2, 3].map(i => (
              <div key={i} className="h-96 bg-gray-800 rounded-2xl" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  const isSubscribed = currentSub?.hasSubscription;

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-16">
      {/* Header */}
      <div className="text-center mb-12">
        <h1 className="text-3xl sm:text-4xl font-bold text-white mb-3">
          Choose Your Plan
        </h1>
        <p className="text-gray-400 text-lg max-w-xl mx-auto">
          Unlock unlimited access to the full BAG Comics library.
        </p>
      </div>

      {/* Current subscription status */}
      {isSubscribed && (
        <div className="bg-green-500/10 border border-green-500/30 rounded-xl p-5 mb-10 flex items-center justify-between flex-wrap gap-4">
          <div>
            <p className="text-green-400 font-semibold">
              Active: {currentSub.displayName}
            </p>
            <p className="text-gray-400 text-sm">
              {currentSub.status === 'canceled'
                ? `Cancels on ${new Date(currentSub.expiresAt).toLocaleDateString()}`
                : `${currentSub.daysRemaining} days remaining`}
            </p>
          </div>
          {currentSub.status !== 'canceled' && (
            <button
              onClick={handleCancel}
              className="text-red-400 hover:text-red-300 text-sm font-medium transition-colors"
            >
              Cancel Subscription
            </button>
          )}
        </div>
      )}

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-8 text-center">
          <p className="text-red-400 text-sm">{error}</p>
        </div>
      )}

      {/* Plan cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {plans.map((plan) => {
          const isCurrent = isSubscribed && currentSub?.type === plan.interval;
          const isFree = plan.slug === 'free';

          return (
            <div
              key={plan.slug}
              className={`relative rounded-2xl border p-6 flex flex-col ${
                plan.isFeatured
                  ? 'border-[#DC2626] bg-[#DC2626]/5'
                  : 'border-gray-800 bg-[#0f0f0f]'
              }`}
            >
              {/* Featured badge */}
              {plan.isFeatured && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="bg-[#DC2626] text-white text-xs font-bold px-4 py-1 rounded-full uppercase tracking-wider">
                    Best Value
                  </span>
                </div>
              )}

              {/* Plan name */}
              <h3 className="text-xl font-bold text-white mb-1">{plan.name}</h3>
              <p className="text-gray-500 text-sm mb-5">{plan.description}</p>

              {/* Price */}
              <div className="mb-6">
                {plan.originalPrice && (
                  <span className="text-gray-500 line-through text-sm mr-2">
                    ${plan.originalPrice.toFixed(2)}
                  </span>
                )}
                <span className="text-3xl font-bold text-white">
                  {isFree ? 'Free' : `$${plan.price.toFixed(2)}`}
                </span>
                {!isFree && (
                  <span className="text-gray-500 text-sm ml-1">
                    /{plan.interval === 'monthly' ? 'mo' : 'yr'}
                  </span>
                )}
                {plan.savingsPercent && (
                  <span className="ml-2 bg-green-500/20 text-green-400 text-xs font-bold px-2 py-0.5 rounded-full">
                    Save {plan.savingsPercent}%
                  </span>
                )}
              </div>

              {/* Features */}
              <ul className="space-y-3 mb-8 flex-1">
                {plan.features.map((feature, i) => (
                  <li key={i} className="flex items-start gap-2 text-sm text-gray-300">
                    <svg className="w-4 h-4 text-green-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    {feature}
                  </li>
                ))}
              </ul>

              {/* CTA button */}
              {isCurrent ? (
                <button
                  disabled
                  className="w-full py-3 rounded-xl font-semibold bg-green-500/20 text-green-400 cursor-default"
                >
                  Current Plan
                </button>
              ) : isFree ? (
                <Link
                  to="/register"
                  className="w-full py-3 rounded-xl font-semibold text-center bg-[#1a1a1a] text-white hover:bg-[#2a2a2a] transition-colors border border-gray-700"
                >
                  Get Started
                </Link>
              ) : (
                <button
                  onClick={() => handleSubscribe(plan)}
                  disabled={subscribing === plan.slug || isSubscribed}
                  className={`w-full py-3 rounded-xl font-semibold transition-colors disabled:opacity-50 ${
                    plan.isFeatured
                      ? 'bg-[#DC2626] hover:bg-[#B91C1C] text-white'
                      : 'bg-white hover:bg-gray-100 text-black'
                  }`}
                >
                  {subscribing === plan.slug
                    ? 'Processing...'
                    : isSubscribed
                      ? 'Already Subscribed'
                      : 'Subscribe'}
                </button>
              )}
            </div>
          );
        })}
      </div>

      {/* FAQ */}
      <div className="mt-16 text-center">
        <h2 className="text-xl font-bold text-white mb-6">Frequently Asked Questions</h2>
        <div className="max-w-2xl mx-auto space-y-6 text-left">
          {[
            { q: 'Can I cancel anytime?', a: 'Yes — cancel whenever you want. You keep access until the end of your billing period.' },
            { q: 'What payment methods do you accept?', a: 'We accept all major credit and debit cards through Stripe.' },
            { q: 'Can I still buy individual comics?', a: 'Absolutely. Per-comic purchases are always available from the store, regardless of subscription status.' },
            { q: 'What happens to comics I purchased if I subscribe?', a: 'Purchased comics remain yours permanently, even if your subscription ends.' },
          ].map((faq, i) => (
            <div key={i} className="bg-[#0f0f0f] border border-gray-800 rounded-xl p-5">
              <h3 className="font-semibold text-white mb-1">{faq.q}</h3>
              <p className="text-gray-400 text-sm">{faq.a}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default PricingPage;
