import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import NavBar from '@/components/NavBar';

export default function Password() {
    const { auth } = usePage<SharedData>().props;
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
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
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
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
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
                                        className="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
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
                                        className="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
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
                                        <p className="text-sm text-red-400 font-medium">Password updated successfully!</p>
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
