import { Head, Link } from '@inertiajs/react';
import { BookOpen, Star, Users } from 'lucide-react';

export default function ForgotPassword() {
    const stats = [
        { icon: BookOpen, label: 'Comics', value: '500+' },
        { icon: Star, label: 'Creators', value: '50+' },
        { icon: Users, label: 'Readers', value: '10K+' }
    ];

    return (
        <>
            <Head title="Forgot Password - BagComics">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <div className="min-h-screen flex relative">
                {/* Full Screen Background */}
                <div className="absolute inset-0 z-0">
                    <img
                        src="https://images.pexels.com/photos/17867069/pexels-photo-17867069.jpeg?auto=compress&cs=tinysrgb&w=1920"
                        alt="African Comic Art Background"
                        className="w-full h-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-black/90 via-black/70 to-black/90" />
                    <div className="absolute inset-0 bg-gradient-to-b from-transparent via-black/20 to-black/60" />
                </div>

                {/* Left Side - Branding */}
                <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden z-10">
                    <div className="absolute inset-0 bg-gradient-to-br from-red-500/30 via-red-600/30 to-red-700/30" />

                    <div className="absolute top-20 left-20 w-32 h-32 bg-red-500/20 rounded-full blur-xl animate-pulse" />
                    <div className="absolute bottom-40 right-20 w-48 h-48 bg-red-600/20 rounded-full blur-xl animate-pulse delay-1000" />
                    <div className="absolute top-1/2 left-1/3 w-24 h-24 bg-red-700/20 rounded-full blur-xl animate-pulse delay-500" />

                    <div className="relative z-10 flex flex-col justify-center items-center p-12 text-center">
                        <div className="mb-8">
                            <Link href="/" className="block mb-8 group">
                                <img
                                    src="/images/bagcomics.jpeg"
                                    alt="BAG Comics Logo"
                                    className="h-32 w-32 object-cover mx-auto transition-transform duration-300 group-hover:scale-105 rounded-2xl"
                                />
                            </Link>
                            <p className="text-2xl text-gray-300 mb-8">
                                Password Recovery
                            </p>
                            <p className="text-lg text-gray-400 max-w-md leading-relaxed">
                                Don't worry, it happens to the best of us. Contact an administrator to get a temporary password and get back to reading.
                            </p>
                        </div>

                        <div className="grid grid-cols-3 gap-8 mt-12">
                            {stats.map(({ icon: Icon, label, value }) => (
                                <div key={label} className="text-center">
                                    <div className="w-16 h-16 bg-gradient-to-r from-red-500/30 to-red-600/30 rounded-full flex items-center justify-center mx-auto mb-3 border border-red-500/50 backdrop-blur-sm">
                                        <Icon className="w-8 h-8 text-red-400" />
                                    </div>
                                    <div className="text-2xl font-bold text-white">{value}</div>
                                    <div className="text-sm text-gray-400">{label}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Right Side - Contact Admin Message */}
                <div className="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10">
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-sm lg:bg-transparent lg:backdrop-blur-none" />
                    <div className="w-full max-w-md">
                        <div className="lg:hidden text-center mb-8">
                            <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-red-400 via-red-500 to-red-600 bg-clip-text text-transparent">
                                BAG Comics
                            </h1>
                            <p className="text-gray-400">African Stories, Boldly Told</p>
                        </div>

                        <div className="bg-gray-800/80 backdrop-blur-md rounded-2xl p-8 border border-gray-700/50 shadow-2xl relative z-10 text-center">
                            <div className="mb-6">
                                <svg className="w-16 h-16 text-yellow-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <h2 className="text-3xl font-bold text-white mb-2">Forgot Password?</h2>
                                <p className="text-gray-400 leading-relaxed">
                                    To reset your password, please contact an administrator. They will provide you with a temporary password that you can use to sign in and create a new one.
                                </p>
                            </div>

                            <div className="bg-gray-700/30 border border-gray-600 rounded-lg p-4 mb-6">
                                <p className="text-gray-300 text-sm">
                                    Reach out to us via any of our social media channels or contact your administrator directly.
                                </p>
                            </div>

                            <Link
                                href={route('login')}
                                className="inline-flex items-center justify-center w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-red-500/25"
                            >
                                Back to Login
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
