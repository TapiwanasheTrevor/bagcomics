import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { User, Settings, Library, LogOut, Menu, X, Home, Book, ArrowLeft, Search } from 'lucide-react';

import UserAvatarDropdown from '@/components/UserAvatarDropdown';
import UserMobileMenu from '@/components/UserMobileMenu';

export default function Password() {
    const { auth } = usePage<SharedData>().props;
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <>
            <Head title="Password Settings">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-gray-900 text-white">
                {/* Header */}
                <header className="bg-gray-800/95 backdrop-blur-sm border-b border-gray-700 sticky top-0 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Logo */}
                            <div className="flex items-center space-x-4">
                                <div className="text-2xl font-bold bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
                                    BAG Comics
                                </div>
                            </div>

                            {/* Desktop Navigation */}
                            <nav className="hidden md:flex items-center space-x-8">
                                <Link
                                    href="/"
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                >
                                    <Home className="w-4 h-4" />
                                    <span>Home</span>
                                </Link>
                                <Link
                                    href="/comics"
                                    className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                >
                                    <Book className="w-4 h-4" />
                                    <span>Explore</span>
                                </Link>
                                {auth.user && (
                                    <Link
                                        href="/library"
                                        className="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                    >
                                        <Library className="w-4 h-4" />
                                        <span>Library</span>
                                    </Link>
                                )}
                            </nav>

                            {/* Search Bar */}
                            <div className="hidden md:flex items-center space-x-4">
                                <div className="relative">
                                    <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                    <input
                                        type="text"
                                        placeholder="Search comics..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                    />
                                </div>

                                {/* User Account */}
                                {auth.user ? (
                                    <UserAvatarDropdown user={auth.user} />
                                ) : (
                                    <Link
                                        href="/login"
                                        className="flex items-center space-x-2 px-4 py-2 bg-purple-500/20 text-purple-400 border border-purple-500/30 hover:bg-purple-500/30 rounded-lg transition-all duration-300"
                                    >
                                        <User className="w-4 h-4" />
                                        <span className="text-sm">Sign In</span>
                                    </Link>
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
                            <div className="md:hidden py-4 border-t border-gray-700">
                                <div className="flex flex-col space-y-2">
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <Home className="w-5 h-5" />
                                        <span>Home</span>
                                    </Link>
                                    <Link
                                        href="/comics"
                                        className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                        onClick={() => setIsMenuOpen(false)}
                                    >
                                        <Book className="w-5 h-5" />
                                        <span>Explore</span>
                                    </Link>
                                    {auth.user && (
                                        <Link
                                            href="/library"
                                            className="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all duration-300 text-gray-300 hover:text-white hover:bg-gray-700/50"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <Library className="w-5 h-5" />
                                            <span>Library</span>
                                        </Link>
                                    )}

                                    {/* Mobile Search */}
                                    <div className="px-4 py-2">
                                        <div className="relative">
                                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                            <input
                                                type="text"
                                                placeholder="Search comics..."
                                                value={searchQuery}
                                                onChange={(e) => setSearchQuery(e.target.value)}
                                                className="w-full bg-gray-700/50 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                                            />
                                        </div>
                                    </div>

                                    {/* Mobile User Menu */}
                                    {auth.user && (
                                        <UserMobileMenu user={auth.user} onClose={() => setIsMenuOpen(false)} />
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </header>

                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Back Navigation */}
                    <div className="mb-6">
                        <Link
                            href="/dashboard"
                            className="flex items-center space-x-2 text-gray-400 hover:text-white transition-colors"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            <span>Back to Dashboard</span>
                        </Link>
                    </div>

                    {/* Settings Content */}
                    <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-8">
                        <div className="mb-8">
                            <h1 className="text-3xl font-bold text-white mb-2">Password Settings</h1>
                            <p className="text-gray-400">Ensure your account is using a long, random password to stay secure</p>
                        </div>

                        <form onSubmit={updatePassword} className="space-y-8">
                            {/* Password Requirements Info */}
                            <div className="bg-blue-500/10 border border-blue-500/20 rounded-lg p-4">
                                <h3 className="text-sm font-medium text-blue-400 mb-2">Password Requirements</h3>
                                <ul className="text-sm text-blue-300 space-y-1">
                                    <li>• At least 8 characters long</li>
                                    <li>• Contains at least one uppercase letter</li>
                                    <li>• Contains at least one lowercase letter</li>
                                    <li>• Contains at least one number</li>
                                    <li>• Contains at least one special character</li>
                                </ul>
                            </div>

                            <div className="space-y-6">
                                <div>
                                    <label htmlFor="current_password" className="block text-sm font-medium text-gray-300 mb-2">
                                        Current Password
                                    </label>
                                    <input
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        type="password"
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        value={data.current_password}
                                        onChange={(e) => setData('current_password', e.target.value)}
                                        autoComplete="current-password"
                                        placeholder="Enter your current password"
                                        required
                                    />
                                    {errors.current_password && (
                                        <p className="mt-2 text-sm text-red-400">{errors.current_password}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-gray-300 mb-2">
                                        New Password
                                    </label>
                                    <input
                                        id="password"
                                        ref={passwordInput}
                                        type="password"
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Enter your new password"
                                        required
                                        minLength={8}
                                    />
                                    {errors.password && (
                                        <p className="mt-2 text-sm text-red-400">{errors.password}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-300 mb-2">
                                        Confirm New Password
                                    </label>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Confirm your new password"
                                        required
                                        minLength={8}
                                    />
                                    {errors.password_confirmation && (
                                        <p className="mt-2 text-sm text-red-400">{errors.password_confirmation}</p>
                                    )}
                                </div>
                            </div>

                            {/* Security Tips */}
                            <div className="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4">
                                <h3 className="text-sm font-medium text-amber-400 mb-2">Security Tips</h3>
                                <ul className="text-sm text-amber-300 space-y-1">
                                    <li>• Use a unique password that you don't use elsewhere</li>
                                    <li>• Consider using a password manager</li>
                                    <li>• Avoid using personal information in your password</li>
                                    <li>• Change your password regularly</li>
                                </ul>
                            </div>

                            <div className="flex items-center justify-between pt-6 border-t border-gray-600">
                                <div className="flex items-center space-x-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-3 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
                                    >
                                        {processing ? 'Updating Password...' : 'Update Password'}
                                    </button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-emerald-400 font-medium">Password updated successfully!</p>
                                    </Transition>
                                </div>

                                <Link
                                    href="/dashboard"
                                    className="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </>
    );
}
