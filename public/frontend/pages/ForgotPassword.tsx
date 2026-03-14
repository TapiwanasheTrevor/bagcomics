import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';

export const ForgotPasswordPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      await api.forgotPassword(email);
      setSent(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send reset link');
    } finally {
      setLoading(false);
    }
  };

  if (sent) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] px-4">
        <div className="w-full max-w-md bg-[#0f0f0f] border border-gray-800 rounded-2xl p-8 text-center">
          <svg className="w-12 h-12 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h1 className="text-xl font-bold text-white mb-2">Check your email</h1>
          <p className="text-gray-400 text-sm mb-6">
            If an account exists for <span className="text-white">{email}</span>, we've sent a password reset link.
          </p>
          <Link to="/login" className="text-[#DC2626] hover:text-[#F87171] text-sm font-medium">
            Back to Sign in
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] px-4">
      <div className="w-full max-w-md bg-[#0f0f0f] border border-gray-800 rounded-2xl p-8">
        <h1 className="text-2xl font-bold text-white mb-2">Forgot password?</h1>
        <p className="text-gray-400 text-sm mb-6">Enter your email and we'll send you a reset link.</p>
        {error && <p className="text-sm text-red-400 mb-4">{error}</p>}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-gray-400 mb-2">Email</label>
            <input
              type="email" required value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
            />
          </div>
          <button
            type="submit" disabled={loading}
            className="w-full bg-[#DC2626] hover:bg-[#B91C1C] text-white py-3 rounded-lg font-semibold transition-colors disabled:opacity-50"
          >
            {loading ? 'Sending...' : 'Send reset link'}
          </button>
        </form>
        <p className="text-sm text-gray-400 mt-6">
          Remember your password?{' '}
          <Link to="/login" className="text-white hover:text-gray-200">Sign in</Link>
        </p>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
