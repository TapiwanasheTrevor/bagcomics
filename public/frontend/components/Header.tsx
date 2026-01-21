
import React from 'react';
import { ViewMode } from '../types';

interface HeaderProps {
  currentView: ViewMode;
  onNavigate: (view: ViewMode) => void;
  isLoggedIn?: boolean;
  onSignIn?: () => void;
}

export const Header: React.FC<HeaderProps> = ({ currentView, onNavigate, isLoggedIn = false, onSignIn }) => {
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

        {/* Auth buttons */}
        {isLoggedIn ? (
          <>
            <button className="hidden sm:flex bg-[#DC2626] hover:bg-[#B91C1C] text-white px-5 py-2 rounded-full font-semibold text-sm transition-colors items-center gap-1">
              Publish
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div className="flex items-center gap-2 cursor-pointer">
              <div className="w-9 h-9 rounded-full border-2 border-gray-600 overflow-hidden">
                <img src="https://picsum.photos/seed/user/100/100" alt="Avatar" className="w-full h-full object-cover" />
              </div>
            </div>
          </>
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
