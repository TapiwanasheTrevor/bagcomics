import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState, useEffect } from 'react';
import { ArrowLeft, Mail, Bell } from 'lucide-react';
import NavBar from '@/components/NavBar';
import axios from 'axios';

type ProfileForm = {
    name: string;
    email: string;
};

type NotificationPreferences = {
    email_notifications: boolean;
    new_releases_notifications: boolean;
    reading_reminders: boolean;
};

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [notifications, setNotifications] = useState<NotificationPreferences>({
        email_notifications: true,
        new_releases_notifications: true,
        reading_reminders: false,
    });
    const [notificationsLoading, setNotificationsLoading] = useState(false);
    const [notificationsSaved, setNotificationsSaved] = useState(false);

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<Required<ProfileForm>>({
        name: auth.user.name,
        email: auth.user.email,
    });

    // Load notification preferences on component mount
    useEffect(() => {
        const loadNotificationPreferences = async () => {
            try {
                const response = await axios.get('/api/preferences/notifications');
                if (response.data.success) {
                    setNotifications(response.data.notifications);
                }
            } catch (error) {
                console.error('Failed to load notification preferences:', error);
            }
        };

        loadNotificationPreferences();
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'), {
            preserveScroll: true,
        });
    };

    const handleNotificationChange = (key: keyof NotificationPreferences, value: boolean) => {
        setNotifications(prev => ({ ...prev, [key]: value }));
    };

    const saveNotificationPreferences = async () => {
        setNotificationsLoading(true);
        setNotificationsSaved(false);
        
        try {
            const response = await axios.put('/api/preferences/notifications', notifications);
            if (response.data.success) {
                setNotificationsSaved(true);
                setTimeout(() => setNotificationsSaved(false), 3000);
            }
        } catch (error) {
            console.error('Failed to save notification preferences:', error);
        } finally {
            setNotificationsLoading(false);
        }
    };

    return (
        <>
            <Head title="Profile Settings">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="min-h-screen bg-black text-white">
                <NavBar 
                    auth={auth}
                    searchValue={searchQuery}
                    onSearchChange={setSearchQuery}
                    onSearch={(query) => {
                        window.location.href = `/comics?search=${encodeURIComponent(query)}`;
                    }}
                />

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
                            <h1 className="text-3xl font-bold text-white mb-2">Profile Settings</h1>
                            <p className="text-gray-400">Update your name and email address</p>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <label htmlFor="name" className="block text-sm font-medium text-gray-300">
                                    Name
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />
                                {errors.name && (
                                    <p className="text-red-400 text-sm">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label htmlFor="email" className="block text-sm font-medium text-gray-300">
                                    Email Address
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />
                                {errors.email && (
                                    <p className="text-red-400 text-sm">{errors.email}</p>
                                )}
                            </div>

                            {mustVerifyEmail && auth.user.email_verified_at === null && (
                                <div className="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                                    <p className="text-sm text-yellow-300">
                                        Your email address is unverified.{' '}
                                        <Link
                                            href={route('verification.send')}
                                            method="post"
                                            as="button"
                                            className="text-yellow-400 underline hover:text-yellow-300 transition-colors"
                                        >
                                            Click here to resend the verification email.
                                        </Link>
                                    </p>

                                    {status === 'verification-link-sent' && (
                                        <div className="mt-2 text-sm font-medium text-green-400">
                                            A new verification link has been sent to your email address.
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center gap-4 pt-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </button>

                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-red-400 font-medium">Saved successfully!</p>
                                </Transition>
                            </div>
                        </form>

                        {/* Communication Preferences Section */}
                        <div className="mt-12 pt-8 border-t border-gray-700">
                            <div className="mb-8">
                                <h2 className="text-2xl font-bold text-white mb-2 flex items-center space-x-2">
                                    <Mail className="w-6 h-6 text-red-500" />
                                    <span>Communication Preferences</span>
                                </h2>
                                <p className="text-gray-400">Manage your email notifications and communication settings</p>
                            </div>

                            <div className="space-y-6">
                                <div className="flex items-start space-x-4">
                                    <div className="flex items-center h-5">
                                        <input
                                            id="email_notifications"
                                            type="checkbox"
                                            className="w-4 h-4 text-red-600 bg-gray-700 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                                            checked={notifications.email_notifications}
                                            onChange={(e) => handleNotificationChange('email_notifications', e.target.checked)}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <label htmlFor="email_notifications" className="text-sm font-medium text-gray-300 cursor-pointer">
                                            Email Notifications
                                        </label>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Receive general email communications from BAG Comics
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-4">
                                    <div className="flex items-center h-5">
                                        <input
                                            id="new_releases_notifications"
                                            type="checkbox"
                                            className="w-4 h-4 text-red-600 bg-gray-700 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                                            checked={notifications.new_releases_notifications}
                                            onChange={(e) => handleNotificationChange('new_releases_notifications', e.target.checked)}
                                            disabled={!notifications.email_notifications}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <label htmlFor="new_releases_notifications" className={`text-sm font-medium cursor-pointer ${
                                            notifications.email_notifications ? 'text-gray-300' : 'text-gray-500'
                                        }`}>
                                            New Comic Releases
                                        </label>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Get notified when new comics are published on the platform
                                        </p>
                                        {!notifications.email_notifications && (
                                            <p className="text-xs text-yellow-400 mt-1">
                                                <Bell className="w-3 h-3 inline mr-1" />
                                                Requires email notifications to be enabled
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-start space-x-4">
                                    <div className="flex items-center h-5">
                                        <input
                                            id="reading_reminders"
                                            type="checkbox"
                                            className="w-4 h-4 text-red-600 bg-gray-700 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                                            checked={notifications.reading_reminders}
                                            onChange={(e) => handleNotificationChange('reading_reminders', e.target.checked)}
                                            disabled={!notifications.email_notifications}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <label htmlFor="reading_reminders" className={`text-sm font-medium cursor-pointer ${
                                            notifications.email_notifications ? 'text-gray-300' : 'text-gray-500'
                                        }`}>
                                            Reading Reminders
                                        </label>
                                        <p className="text-sm text-gray-500 mt-1">
                                            Receive reminders about comics in your library (coming soon)
                                        </p>
                                        {!notifications.email_notifications && (
                                            <p className="text-xs text-yellow-400 mt-1">
                                                <Bell className="w-3 h-3 inline mr-1" />
                                                Requires email notifications to be enabled
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 pt-6">
                                <button
                                    type="button"
                                    onClick={saveNotificationPreferences}
                                    disabled={notificationsLoading}
                                    className="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {notificationsLoading ? 'Saving...' : 'Save Preferences'}
                                </button>

                                <Transition
                                    show={notificationsSaved}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-red-400 font-medium">Preferences saved!</p>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
