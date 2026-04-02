import React from 'react';
import { Link } from 'react-router-dom';

export const ForgotPasswordPage: React.FC = () => {
  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] px-4">
      <div className="w-full max-w-md bg-[#0f0f0f] border border-gray-800 rounded-2xl p-8 text-center">
        <svg className="w-12 h-12 text-yellow-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
        <h1 className="text-2xl font-bold text-white mb-2">Forgot your password?</h1>
        <p className="text-gray-400 text-sm mb-6 leading-relaxed">
          To reset your password, please contact an administrator. They will provide you with a temporary password that you can use to sign in and create a new one.
        </p>
        <div className="bg-[#1a1a1a] border border-gray-700 rounded-lg p-4 mb-6">
          <p className="text-gray-300 text-sm">
            Reach out to us via any of our social media channels or contact your administrator directly.
          </p>
        </div>
        <Link
          to="/login"
          className="inline-block bg-[#DC2626] hover:bg-[#B91C1C] text-white px-6 py-3 rounded-lg font-semibold transition-colors"
        >
          Back to Sign in
        </Link>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
