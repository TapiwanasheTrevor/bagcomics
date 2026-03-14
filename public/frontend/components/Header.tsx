
import React, { useState, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ViewMode } from '../types';
import api from '../services/api';

interface HeaderProps {
  currentView: ViewMode;
  onNavigate: (view: ViewMode) => void;
  isLoggedIn?: boolean;
  onSignIn?: () => void;
}

export const Header: React.FC<HeaderProps> = ({ currentView, onNavigate, isLoggedIn = false, onSignIn }) => {
  const [menuOpen, setMenuOpen] = useState(false);
  const [user, setUser] = useState<{ name: string; email: string } | null>(null);
  const menuRef = useRef<HTMLDivElement>(null);

  // Fetch user info when logged in
  useEffect(() => {
    if (isLoggedIn && !user) {
      api.getUser().then(data => {
        if (data?.user) setUser(data.user);
      }).catch(() => {});
    }
  }, [isLoggedIn]);

  // Close menu on outside click
  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setMenuOpen(false);
      }
    };
    if (menuOpen) document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [menuOpen]);

  const handleLogout = async () => {
    setMenuOpen(false);
    try {
      await api.logout();
      window.location.href = '/';
    } catch {
      window.location.href = '/';
    }
  };

  const initials = user?.name
    ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
    : '?';

  return (
    <header className="bg-[#0d0d0d] px-6 py-4 flex items-center justify-between sticky top-0 z-50">
      <div className="flex items-center gap-10">
        {/* Logo */}
        <div
          className="flex items-center gap-2 cursor-pointer group"
          onClick={() => onNavigate(ViewMode.HOME)}
        >
          <div className="transform group-hover:scale-110 transition-transform">
            <img
              src="/images/bagcomics.jpeg"
              alt="BAG Comics"
              className="w-9 h-9 object-cover rounded-md"
            />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">
            BAG<span className="font-light text-[#DC2626]">Comics</span>
          </h1>
        </div>

        {/* Navigation */}
        <nav className="hidden lg:flex items-center gap-8">
          {[
            { label: 'Home', view: ViewMode.HOME },
            { label: 'Store', view: ViewMode.STORE },
            { label: 'My List', view: ViewMode.LIBRARY },
            { label: 'Explore', view: ViewMode.EXPLORE },
            { label: 'Blog', view: ViewMode.BLOG }
          ].map((item) => (
            <button
              key={item.label}
              onClick={() => onNavigate(item.view)}
              className={`text-base font-medium transition-colors ${
                currentView === item.view
                  ? 'text-white'
                  : 'text-gray-400 hover:text-white'
              }`}
            >
              {item.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Right side - Search & Auth */}
      <div className="flex items-center gap-4">
        {/* Search */}
        <div className="relative hidden md:block">
          <input
            type="text"
            placeholder="Search for your favourite comics"
            className="w-72 bg-[#1a1a1a] border border-gray-700 rounded-full px-4 py-2.5 text-sm text-gray-300 focus:outline-none focus:border-gray-500 pl-10"
          />
          <svg className="w-4 h-4 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        {/* Mobile search icon */}
        <button className="md:hidden p-2 text-gray-400 hover:text-white">
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>

        {/* Auth */}
        {isLoggedIn ? (
          <div className="relative" ref={menuRef}>
            <button
              onClick={() => setMenuOpen(!menuOpen)}
              className="flex items-center gap-2 cursor-pointer group"
            >
              <div className="w-9 h-9 rounded-full bg-[#DC2626] flex items-center justify-center text-white font-bold text-sm border-2 border-gray-600 group-hover:border-white transition-colors">
                {initials}
              </div>
              <svg className={`w-4 h-4 text-gray-400 transition-transform ${menuOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            {/* Dropdown menu */}
            {menuOpen && (
              <div className="absolute right-0 top-full mt-2 w-64 bg-[#111] border border-gray-800 rounded-xl shadow-2xl overflow-hidden z-50">
                {/* User info */}
                <div className="px-4 py-3 border-b border-gray-800">
                  <p className="text-white font-semibold text-sm truncate">{user?.name || 'User'}</p>
                  <p className="text-gray-500 text-xs truncate">{user?.email || ''}</p>
                </div>

                {/* Menu items */}
                <div className="py-1">
                  <Link
                    to="/library"
                    onClick={() => setMenuOpen(false)}
                    className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-800/50 hover:text-white transition-colors"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                    My Library
                  </Link>
                  <Link
                    to="/pricing"
                    onClick={() => setMenuOpen(false)}
                    className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-800/50 hover:text-white transition-colors"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Subscription
                  </Link>
                  <Link
                    to="/publish"
                    onClick={() => setMenuOpen(false)}
                    className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-800/50 hover:text-white transition-colors"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Publish with us
                  </Link>
                </div>

                {/* Logout */}
                <div className="border-t border-gray-800 py-1">
                  <button
                    onClick={handleLogout}
                    className="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Sign out
                  </button>
                </div>
              </div>
            )}
          </div>
        ) : (
          <button
            onClick={onSignIn}
            className="bg-transparent border border-gray-600 hover:border-gray-400 text-white px-5 py-2 rounded-full font-medium text-sm transition-colors"
          >
            Sign in
          </button>
        )}
      </div>
    </header>
  );
};
