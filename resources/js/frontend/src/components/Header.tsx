import React, { useState } from 'react';
import { Search, User, Menu, X, Book, Library, Home } from 'lucide-react';
import type { Page } from '../App';

interface HeaderProps {
  currentPage: Page;
  onNavigate: (page: Page) => void;
  isLoggedIn: boolean;
  userData?: any;
  onLogin: () => void;
  onLogout: () => void;
}

const Header: React.FC<HeaderProps> = ({ currentPage, onNavigate, isLoggedIn, userData, onLogin, onLogout }) => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [showUserMenu, setShowUserMenu] = useState(false);

  // Close user menu when clicking outside
  React.useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      const target = event.target as Element;
      if (!target.closest('.relative')) {
        setShowUserMenu(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const navigationItems = [
    { id: 'home', label: 'Home', icon: Home },
    { id: 'catalogue', label: 'Explore', icon: Book },
    { id: 'library', label: 'Library', icon: Library },
  ];

  return (
    <header className="bg-background/95 backdrop-blur-sm border-b border-border sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <div className="flex items-center space-x-4">
            <div
                          className="text-2xl font-bold bg-gradient-to-r from-destructive via-primary to-secondary bg-clip-text text-transparent cursor-pointer"
                          onClick={() => onNavigate('home')}
                        >
              BAG Comics
            </div>
          </div>

          {/* Desktop Navigation */}
          <nav className="hidden md:flex items-center space-x-8">
            {navigationItems.map(({ id, label, icon: Icon }) => (
              <button
                key={id}
                onClick={() => onNavigate(id as Page)}
                className={`flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 ${
                                  currentPage === id
                                    ? 'bg-primary/20 text-primary border border-primary/30'
                                    : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                                }`}
              >
                <Icon className="w-4 h-4" />
                <span>{label}</span>
              </button>
            ))}
          </nav>

          {/* Search Bar */}
          <div className="hidden md:flex items-center space-x-4">
            <div className="relative">
              <Search className="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 transform -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search comics..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="bg-accent/50 border border-border rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
              />
            </div>

            {/* User Account */}
            {isLoggedIn ? (
              <div className="relative">
                <button
                  onClick={() => setShowUserMenu(!showUserMenu)}
                  className="flex items-center space-x-2 px-4 py-2 bg-primary/20 text-primary border border-primary/30 rounded-lg transition-all duration-300 hover:bg-primary/30"
                >
                  <div className="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center font-bold text-sm">
                    {userData?.name?.charAt(0) || userData?.email?.charAt(0) || 'U'}
                  </div>
                  <span className="text-sm hidden sm:block">{userData?.name || 'User'}</span>
                </button>
                
                {showUserMenu && (
                  <div className="absolute right-0 mt-2 w-48 bg-background border border-border rounded-lg shadow-xl z-50">
                    <div className="p-3 border-b border-border">
                      <p className="font-semibold text-foreground">{userData?.name || 'User'}</p>
                      <p className="text-sm text-muted-foreground">{userData?.email}</p>
                    </div>
                    <div className="py-2">
                      <button
                        onClick={() => {
                          onNavigate('library');
                          setShowUserMenu(false);
                        }}
                        className="w-full text-left px-4 py-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                      >
                        My Library
                      </button>
                      <button
                        onClick={() => {
                          setShowUserMenu(false);
                        }}
                        className="w-full text-left px-4 py-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
                      >
                        Settings
                      </button>
                      <hr className="my-2 border-border" />
                      <button
                        onClick={() => {
                          onLogout();
                          setShowUserMenu(false);
                        }}
                        className="w-full text-left px-4 py-2 text-destructive hover:bg-accent transition-colors"
                      >
                        Sign Out
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <button
                onClick={onLogin}
                className="flex items-center space-x-2 px-4 py-2 bg-secondary/20 text-secondary border border-secondary/30 hover:bg-secondary/30 rounded-lg transition-all duration-300"
              >
                <User className="w-4 h-4" />
                <span className="text-sm">Log In</span>
              </button>
            )}
          </div>

          {/* Mobile Menu Button */}
          <button
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            className="md:hidden p-2 rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors"
          >
            {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>

        {/* Mobile Menu */}
        {isMenuOpen && (
          <div className="md:hidden py-4 border-t border-border">
            <div className="flex flex-col space-y-2">
              {navigationItems.map(({ id, label, icon: Icon }) => (
                <button
                  key={id}
                  onClick={() => {
                    onNavigate(id as Page);
                    setIsMenuOpen(false);
                  }}
                  className={`flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 ${
                                      currentPage === id
                                        ? 'bg-primary/20 text-primary border border-primary/30'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                                    }`}
                >
                  <Icon className="w-5 h-5" />
                  <span>{label}</span>
                </button>
              ))}
              
              <div className="px-4 py-2">
                <div className="relative">
                  <Search className="w-4 h-4 text-muted-foreground absolute left-3 top-1/2 transform -translate-y-1/2" />
                  <input
                    type="text"
                    placeholder="Search comics..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full bg-accent/50 border border-border rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                  />
                </div>
              </div>

              {isLoggedIn ? (
                <div className="mx-4 space-y-2">
                  <div className="flex items-center space-x-3 px-4 py-3 bg-primary/20 text-primary border border-primary/30 rounded-lg">
                    <div className="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center font-bold text-sm">
                      {userData?.name?.charAt(0) || userData?.email?.charAt(0) || 'U'}
                    </div>
                    <div>
                      <p className="font-semibold">{userData?.name || 'User'}</p>
                      <p className="text-xs text-primary/80">{userData?.email}</p>
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      onLogout();
                      setIsMenuOpen(false);
                    }}
                    className="w-full px-4 py-2 text-destructive hover:bg-accent rounded-lg transition-colors"
                  >
                    Sign Out
                  </button>
                </div>
              ) : (
                <button
                  onClick={() => {
                    onLogin();
                    setIsMenuOpen(false);
                  }}
                  className="mx-4 flex items-center justify-center space-x-2 px-4 py-3 bg-secondary/20 text-secondary border border-secondary/30 rounded-lg transition-all duration-300"
                >
                  <User className="w-5 h-5" />
                  <span>Log In</span>
                </button>
              )}
            </div>
          </div>
        )}
      </div>
    </header>
  );
};

export default Header;