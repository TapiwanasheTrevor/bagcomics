import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import NavBar from '@/components/NavBar';

type ProfileForm = {
    name: string;
    email: string;
};

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<Required<ProfileForm>>({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'), {
            preserveScroll: true,
        });
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
                    </div>
                </main>
            </div>
        </>
    );
}
