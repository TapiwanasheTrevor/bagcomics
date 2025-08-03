import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { User, Settings, Library, LogOut, Menu, X, Home, Book, ArrowLeft, Search } from 'lucide-react';
import { Transition } from '@headlessui/react';

import UserAvatarDropdown from '@/components/UserAvatarDropdown';
import UserMobileMenu from '@/components/UserMobileMenu';
import { type SharedData } from '@/types';

interface UserPreferences {
    id: number;
    user_id: number;
    reading_view_mode: 'single' | 'continuous';
    reading_direction: 'ltr' | 'rtl';
    reading_zoom_level: number;
    auto_hide_controls: boolean;
    control_hide_delay: number;
    reduce_motion: boolean;
    high_contrast: boolean;
    email_notifications: boolean;
    new_releases_notifications: boolean;
    reading_reminders: boolean;
    created_at: string;
    updated_at: string;
}

interface PreferencesPageProps {
    preferences: UserPreferences;
}

export default function Preferences({ preferences }: PreferencesPageProps) {
    const { auth } = usePage<SharedData>().props;
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        reading_view_mode: preferences.reading_view_mode,
        reading_direction: preferences.reading_direction,
        reading_zoom_level: preferences.reading_zoom_level,
        auto_hide_controls: preferences.auto_hide_controls,
        control_hide_delay: preferences.control_hide_delay,
        reduce_motion: preferences.reduce_motion,
        high_contrast: preferences.high_contrast,
        email_notifications: preferences.email_notifications,
        new_releases_notifications: preferences.new_releases_notifications,
        reading_reminders: preferences.reading_reminders,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('settings.preferences.update'));
    };

    return (
        <>
            <Head title="Preferences">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-gray-900 text-white">
                <header className="bg-gray-800/95 backdrop-blur-sm border-b border-gray-700 sticky top-0 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
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

                            <button
                                onClick={() => setIsMenuOpen(!isMenuOpen)}
                                className="md:hidden p-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-700/50 transition-colors"
                            >
                                {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                            </button>
                        </div>

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

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="mb-6">
                        <Link
                            href="/dashboard"
                            className="flex items-center space-x-2 text-gray-400 hover:text-white transition-colors"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            <span>Back to Dashboard</span>
                        </Link>
                    </div>

                    <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl border border-gray-700 p-8">
                        <div className="mb-8">
                            <h1 className="text-3xl font-bold text-white mb-2">Reading Preferences</h1>
                            <p className="text-gray-400">Customize your reading experience and notification settings</p>
                        </div>

                        <form onSubmit={submit} className="space-y-8">
                            {/* Reading Settings */}
                            <div className="space-y-6">
                                <h2 className="text-xl font-semibold text-white border-b border-gray-600 pb-2">Reading Settings</h2>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="space-y-3">
                                        <label className="block text-sm font-medium text-gray-300">View Mode</label>
                                        <select
                                            value={data.reading_view_mode}
                                            onChange={(e) => setData('reading_view_mode', e.target.value as 'single' | 'continuous')}
                                            className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="single">Single Page</option>
                                            <option value="continuous">Continuous Scroll</option>
                                        </select>
                                        <p className="text-xs text-gray-400">Choose how comic pages are displayed</p>
                                        {errors.reading_view_mode && <p className="text-red-400 text-sm">{errors.reading_view_mode}</p>}
                                    </div>

                                    <div className="space-y-3">
                                        <label className="block text-sm font-medium text-gray-300">Reading Direction</label>
                                        <select
                                            value={data.reading_direction}
                                            onChange={(e) => setData('reading_direction', e.target.value as 'ltr' | 'rtl')}
                                            className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="ltr">Left to Right</option>
                                            <option value="rtl">Right to Left</option>
                                        </select>
                                        <p className="text-xs text-gray-400">Set page navigation direction</p>
                                        {errors.reading_direction && <p className="text-red-400 text-sm">{errors.reading_direction}</p>}
                                    </div>

                                    <div className="space-y-3">
                                        <label className="block text-sm font-medium text-gray-300">Zoom Level</label>
                                        <div className="space-y-2">
                                            <input
                                                type="range"
                                                min="50"
                                                max="200"
                                                step="10"
                                                value={data.reading_zoom_level}
                                                onChange={(e) => setData('reading_zoom_level', parseInt(e.target.value))}
                                                className="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                                            />
                                            <div className="flex justify-between text-xs text-gray-400">
                                                <span>50%</span>
                                                <span className="text-emerald-400 font-medium">{data.reading_zoom_level}%</span>
                                                <span>200%</span>
                                            </div>
                                        </div>
                                        <p className="text-xs text-gray-400">Default zoom level for comic pages</p>
                                        {errors.reading_zoom_level && <p className="text-red-400 text-sm">{errors.reading_zoom_level}</p>}
                                    </div>

                                    <div className="space-y-3">
                                        <label className="block text-sm font-medium text-gray-300">Control Hide Delay</label>
                                        <div className="space-y-2">
                                            <input
                                                type="range"
                                                min="1"
                                                max="10"
                                                step="1"
                                                value={data.control_hide_delay}
                                                onChange={(e) => setData('control_hide_delay', parseInt(e.target.value))}
                                                className="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                                            />
                                            <div className="flex justify-between text-xs text-gray-400">
                                                <span>1s</span>
                                                <span className="text-emerald-400 font-medium">{data.control_hide_delay}s</span>
                                                <span>10s</span>
                                            </div>
                                        </div>
                                        <p className="text-xs text-gray-400">How long before controls auto-hide</p>
                                        {errors.control_hide_delay && <p className="text-red-400 text-sm">{errors.control_hide_delay}</p>}
                                    </div>
                                </div>

                                {/* Toggle Settings */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">Auto-hide Controls</h3>
                                            <p className="text-xs text-gray-400">Automatically hide reading controls after delay</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('auto_hide_controls', !data.auto_hide_controls)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.auto_hide_controls ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.auto_hide_controls ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>

                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">Reduce Motion</h3>
                                            <p className="text-xs text-gray-400">Minimize animations and transitions</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('reduce_motion', !data.reduce_motion)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.reduce_motion ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.reduce_motion ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>

                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">High Contrast</h3>
                                            <p className="text-xs text-gray-400">Increase contrast for better readability</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('high_contrast', !data.high_contrast)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.high_contrast ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.high_contrast ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Notification Settings */}
                            <div className="space-y-6">
                                <h2 className="text-xl font-semibold text-white border-b border-gray-600 pb-2">Notification Settings</h2>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">Email Notifications</h3>
                                            <p className="text-xs text-gray-400">Receive general updates via email</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('email_notifications', !data.email_notifications)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.email_notifications ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.email_notifications ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>

                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">New Releases</h3>
                                            <p className="text-xs text-gray-400">Get notified about new comic releases</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('new_releases_notifications', !data.new_releases_notifications)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.new_releases_notifications ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.new_releases_notifications ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>

                                    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                                        <div>
                                            <h3 className="text-sm font-medium text-white">Reading Reminders</h3>
                                            <p className="text-xs text-gray-400">Reminders to continue reading your comics</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('reading_reminders', !data.reading_reminders)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 ${
                                                data.reading_reminders ? 'bg-emerald-500' : 'bg-gray-600'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    data.reading_reminders ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-6 border-t border-gray-600">
                                <div className="flex items-center space-x-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-3 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
                                    >
                                        {processing ? 'Saving Preferences...' : 'Save Preferences'}
                                    </button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-emerald-400 font-medium">Preferences saved successfully!</p>
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