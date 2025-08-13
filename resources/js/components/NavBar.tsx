import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Home, Library, User, Menu, X, Book, Search, Settings, LogOut } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';

interface NavBarProps {
    auth: {
        user?: {
            id: number;
            name: string;
            email: string;
            avatar?: string;
        };
    };
    currentPage?: 'home' | 'comics' | 'library';
    className?: string;
    onSearch?: (query: string) => void;
    searchValue?: string;
    onSearchChange?: (value: string) => void;
}

function UserAvatarDropdown({ user }: { user: any }) {
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="flex items-center justify-center px-2 py-2 bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg transition-all duration-300 hover:bg-red-500/30 focus:outline-none focus:ring-2 focus:ring-red-500/50">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold text-sm">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56 bg-black border border-red-500/30 shadow-lg" align="end">
                <DropdownMenuLabel>
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium text-white">{user.name}</p>
                        <p className="text-xs text-gray-400">{user.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href="/dashboard" className="flex items-center cursor-pointer text-white hover:bg-red-500/20 hover:!text-white focus:!text-white data-[highlighted]:!text-white">
                            <User className="mr-2 h-4 w-4" />
                            <span>Profile</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/settings/profile" className="flex items-center cursor-pointer text-white hover:bg-red-500/20 hover:!text-white focus:!text-white data-[highlighted]:!text-white">
                            <Settings className="mr-2 h-4 w-4" />
                            <span>Settings</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/library" className="flex items-center cursor-pointer text-white hover:bg-red-500/20 hover:!text-white focus:!text-white data-[highlighted]:!text-white">
                            <Library className="mr-2 h-4 w-4" />
                            <span>My Library</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center cursor-pointer w-full text-red-400 hover:bg-red-500/20 hover:!text-white focus:!text-white data-[highlighted]:!text-white"
                    >
                        <LogOut className="mr-2 h-4 w-4" />
                        <span>Log out</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function NavBar({ auth, currentPage = 'home', className = '', onSearch, searchValue = '', onSearchChange }: NavBarProps) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState(searchValue);

    // Update local state when prop changes
    React.useEffect(() => {
        setSearchQuery(searchValue);
    }, [searchValue]);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchQuery(value);
        onSearchChange?.(value);
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSearch?.(searchQuery);
    };

    const getNavLinkClass = (page: string) => {
        const isActive = currentPage === page;
        return isActive
            ? "flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 bg-red-500/20 text-red-400 border border-red-500/30"
            : "flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50";
    };

    return (
        <header className={`bg-black/95 backdrop-blur-sm border-b border-red-900/30 sticky top-0 z-50 ${className}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Logo */}
                    <div className="flex items-center space-x-4">
                        <Link href="/" className="flex items-center space-x-3">
                            <img 
                                src="/images/bagcomics.jpeg" 
                                alt="BAG Comics Logo" 
                                className="h-8 w-8 object-cover rounded-md"
                            />
                            <div className="text-xl font-bold bg-gradient-to-r from-red-500 via-red-400 to-red-300 bg-clip-text text-transparent">
                                BAG Comics
                            </div>
                        </Link>
                    </div>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center space-x-8">
                        <Link href="/" className={getNavLinkClass('home')}>
                            <Home className="w-4 h-4" />
                            <span>Home</span>
                        </Link>
                        <Link href="/comics" className={getNavLinkClass('comics')}>
                            <Book className="w-4 h-4" />
                            <span>Explore</span>
                        </Link>
                        {auth.user && (
                            <Link href="/library" className={getNavLinkClass('library')}>
                                <Library className="w-4 h-4" />
                                <span>Library</span>
                            </Link>
                        )}
                    </nav>

                    {/* Search Bar */}
                    <div className="hidden md:flex items-center space-x-4">
                        <form onSubmit={handleSearchSubmit} className="relative">
                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search comics, authors, genres..."
                                value={searchQuery}
                                onChange={handleSearchChange}
                                className="bg-red-500/20 border border-red-500/30 rounded-lg pl-10 pr-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors hover:bg-red-500/30 w-80"
                            />
                        </form>

                        {/* User Account - Enhanced for visibility */}
                        {auth.user ? (
                            <UserAvatarDropdown user={auth.user} />
                        ) : (
                            <div className="flex items-center space-x-3">
                                <Link
                                    href="/register"
                                    className="flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105 shadow-lg"
                                >
                                    <User className="w-4 h-4" />
                                    <span className="text-sm">Sign Up</span>
                                </Link>
                                <Link
                                    href="/login"
                                    className="flex items-center space-x-2 px-4 py-2 bg-transparent text-red-400 border border-red-500/50 hover:bg-red-500/10 hover:border-red-400 rounded-lg transition-all duration-300"
                                >
                                    <User className="w-4 h-4" />
                                    <span className="text-sm">Log In</span>
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Mobile Menu Button */}
                    <button
                        onClick={() => setIsMenuOpen(!isMenuOpen)}
                        className="md:hidden p-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors"
                    >
                        {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                    </button>
                </div>

                {/* Mobile Menu */}
                {isMenuOpen && (
                    <div className="md:hidden py-4 border-t border-red-900/30">
                        <div className="flex flex-col space-y-2">
                            <Link
                                href="/"
                                className={getNavLinkClass('home')}
                                onClick={() => setIsMenuOpen(false)}
                            >
                                <Home className="w-4 h-4" />
                                <span>Home</span>
                            </Link>
                            <Link
                                href="/comics"
                                className={getNavLinkClass('comics')}
                                onClick={() => setIsMenuOpen(false)}
                            >
                                <Book className="w-4 h-4" />
                                <span>Explore</span>
                            </Link>
                            {auth.user && (
                                <Link
                                    href="/library"
                                    className={getNavLinkClass('library')}
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    <Library className="w-4 h-4" />
                                    <span>Library</span>
                                </Link>
                            )}

                            {/* Mobile Search */}
                            <div className="px-4 py-2">
                                <form onSubmit={handleSearchSubmit} className="relative">
                                    <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                    <input
                                        type="text"
                                        placeholder="Search comics, authors, genres..."
                                        value={searchQuery}
                                        onChange={handleSearchChange}
                                        className="w-full bg-red-500/20 border border-red-500/30 rounded-lg pl-10 pr-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors hover:bg-red-500/30"
                                    />
                                </form>
                            </div>

                            <div className="pt-4 border-t border-red-900/30">
                                {auth.user ? (
                                    <div className="flex items-center space-x-3 px-3 py-2">
                                        <Avatar className="h-8 w-8">
                                            <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                            <AvatarFallback className="bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold text-sm">
                                                {auth.user.name.slice(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <p className="text-sm font-medium text-white">{auth.user.name}</p>
                                            <p className="text-xs text-gray-400">{auth.user.email}</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-col space-y-3 px-3">
                                        <Link
                                            href="/register"
                                            className="flex items-center justify-center space-x-2 px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <User className="w-4 h-4" />
                                            <span>Sign Up</span>
                                        </Link>
                                        <Link
                                            href="/login"
                                            className="flex items-center justify-center space-x-2 px-4 py-3 bg-transparent text-red-400 border border-red-500/50 hover:bg-red-500/10 hover:border-red-400 rounded-lg transition-all duration-300"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <User className="w-4 h-4" />
                                            <span>Log In</span>
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}