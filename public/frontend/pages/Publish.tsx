import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';

export const PublishPage: React.FC = () => {
  const [form, setForm] = useState({
    name: '',
    email: '',
    portfolio_url: '',
    comic_title: '',
    genre: '',
    synopsis: '',
    sample_pages_url: '',
  });
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const genres = [
    'Action', 'Adventure', 'Comedy', 'Drama', 'Fantasy',
    'Horror', 'Mystery', 'Romance', 'Sci-Fi', 'Superhero', 'Thriller',
  ];

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const result = await api.submitCreatorApplication({
        ...form,
        portfolio_url: form.portfolio_url || undefined,
        sample_pages_url: form.sample_pages_url || undefined,
      });
      setSuccess(result.message);
      setForm({ name: '', email: '', portfolio_url: '', comic_title: '', genre: '', synopsis: '', sample_pages_url: '' });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Submission failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 py-12">
      <div className="text-center mb-10">
        <h1 className="text-3xl sm:text-4xl font-bold text-white mb-3">Publish with BAG Comics</h1>
        <p className="text-gray-400 text-lg max-w-xl mx-auto">
          Share your stories with readers worldwide. Submit your comic and our team will review it.
        </p>
      </div>

      {success ? (
        <div className="bg-green-500/10 border border-green-500/30 rounded-2xl p-8 text-center">
          <svg className="w-12 h-12 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h2 className="text-xl font-bold text-white mb-2">Submission Received!</h2>
          <p className="text-gray-300 mb-6">{success}</p>
          <Link to="/" className="text-[#DC2626] hover:text-[#F87171] font-medium transition-colors">
            Back to Home
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="bg-[#0f0f0f] border border-gray-800 rounded-2xl p-6 sm:p-8 space-y-6">
          {error && (
            <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
              <p className="text-red-400 text-sm">{error}</p>
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm text-gray-400 mb-2">Your Name *</label>
              <input
                type="text" name="name" required value={form.name} onChange={handleChange}
                className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-400 mb-2">Email *</label>
              <input
                type="email" name="email" required value={form.email} onChange={handleChange}
                className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-2">Portfolio URL</label>
            <input
              type="url" name="portfolio_url" value={form.portfolio_url} onChange={handleChange}
              placeholder="https://your-portfolio.com"
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm text-gray-400 mb-2">Comic Title *</label>
              <input
                type="text" name="comic_title" required value={form.comic_title} onChange={handleChange}
                className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
              />
            </div>
            <div>
              <label className="block text-sm text-gray-400 mb-2">Genre *</label>
              <select
                name="genre" required value={form.genre} onChange={handleChange}
                className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
              >
                <option value="">Select genre</option>
                {genres.map(g => <option key={g} value={g.toLowerCase()}>{g}</option>)}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-2">Synopsis *</label>
            <textarea
              name="synopsis" required value={form.synopsis} onChange={handleChange}
              rows={4} maxLength={2000}
              placeholder="Tell us about your comic — story, characters, themes..."
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626] resize-none"
            />
            <p className="text-gray-600 text-xs mt-1">{form.synopsis.length}/2000</p>
          </div>

          <div>
            <label className="block text-sm text-gray-400 mb-2">Sample Pages URL</label>
            <input
              type="url" name="sample_pages_url" value={form.sample_pages_url} onChange={handleChange}
              placeholder="https://drive.google.com/... or similar"
              className="w-full bg-[#0a0a0a] border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#DC2626]"
            />
            <p className="text-gray-600 text-xs mt-1">Link to a Google Drive, Dropbox, or portfolio with sample pages</p>
          </div>

          <button
            type="submit" disabled={loading}
            className="w-full bg-[#DC2626] hover:bg-[#B91C1C] text-white py-4 rounded-xl font-semibold text-lg transition-colors disabled:opacity-50"
          >
            {loading ? 'Submitting...' : 'Submit Your Comic'}
          </button>
        </form>
      )}
    </div>
  );
};

export default PublishPage;
