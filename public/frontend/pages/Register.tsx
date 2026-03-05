import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../services/api';

export const RegisterPage: React.FC = () => {
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await api.register(name, email, password, passwordConfirmation);
      navigate('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] px-4">
      <div className="w-full max-w-md bg-[#0f0f0f] border border-gray-800 rounded-2xl p-8">
        <h1 className="text-2xl font-bold text-white mb-6">Create account</h1>
        {error && <p className="text-sm text-red-400 mb-4">{error}</p>}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-gray-400 mb-2">Name</label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white"
            />
          </div>
          <div>
            <label className="block text-sm text-gray-400 mb-2">Email</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white"
            />
          </div>
          <div>
            <label className="block text-sm text-gray-400 mb-2">Password</label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white"
            />
          </div>
          <div>
            <label className="block text-sm text-gray-400 mb-2">Confirm password</label>
            <input
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-[#DC2626] hover:bg-[#B91C1C] text-white py-3 rounded-lg font-semibold transition-colors disabled:opacity-50"
          >
            {loading ? 'Creating account...' : 'Create account'}
          </button>
        </form>
        <p className="text-sm text-gray-400 mt-6">
          Already have an account?{' '}
          <Link to="/login" className="text-white hover:text-gray-200">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
};

export default RegisterPage;
