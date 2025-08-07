import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import NavBar from '@/components/NavBar';
import { type SharedData } from '@/types';

export default function Appearance() {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');

    return (
        <>
            <Head title="Appearance Settings">
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
                            <h1 className="text-3xl font-bold text-white mb-2">Appearance Settings</h1>
                            <p className="text-gray-400">Update your account's appearance settings</p>
                        </div>

                        <AppearanceTabs />
                    </div>
                </main>
            </div>
        </>
    );
}
