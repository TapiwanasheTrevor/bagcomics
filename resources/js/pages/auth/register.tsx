import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Eye, EyeOff, Mail, Lock, User, ArrowRight, BookOpen, Star, Users, Check } from 'lucide-react';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    email_notifications?: boolean;
    new_releases_notifications?: boolean;
};

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        email_notifications: true,
        new_releases_notifications: true,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const passwordStrength = () => {
        const password = data.password;
        let strength = 0;

        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        return strength;
    };

    const getStrengthColor = (strength: number) => {
        if (strength <= 2) return 'bg-red-500';
        if (strength <= 3) return 'bg-yellow-500';
        return 'bg-red-500';
    };

    const getStrengthText = (strength: number) => {
        if (strength <= 2) return 'Weak';
        if (strength <= 3) return 'Medium';
        return 'Strong';
    };

    const benefits = [
        'Access to 500+ African comics',
        'Bookmark and track reading progress',
        'Download comics for offline reading',
        'Get notified of new releases',
        'Support African creators directly'
    ];

    const stats = [
        { icon: BookOpen, label: 'Comics', value: '500+' },
        { icon: Star, label: 'Creators', value: '50+' },
        { icon: Users, label: 'Readers', value: '10K+' }
    ];

    return (
        <>
            <Head title="Register - BagComics">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <div className="min-h-screen flex relative">
                {/* Full Screen Background */}
                <div className="absolute inset-0 z-0">
                    <img
                        src="https://images.pexels.com/photos/17867321/pexels-photo-17867321.jpeg?auto=compress&cs=tinysrgb&w=1920"
                        alt="African Comic Art Background"
                        className="w-full h-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-black/90 via-black/70 to-black/90" />
                    <div className="absolute inset-0 bg-gradient-to-b from-transparent via-black/20 to-black/60" />
                </div>

                {/* Left Side - Branding */}
                <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden z-10">
                    <div className="absolute inset-0 bg-gradient-to-br from-red-500/30 via-red-600/30 to-red-700/30" />

                    {/* Animated Background Elements */}
                    <div className="absolute top-20 left-20 w-32 h-32 bg-red-500/20 rounded-full blur-xl animate-pulse" />
                    <div className="absolute bottom-40 right-20 w-48 h-48 bg-red-600/20 rounded-full blur-xl animate-pulse delay-1000" />
                    <div className="absolute top-1/2 left-1/3 w-24 h-24 bg-red-700/20 rounded-full blur-xl animate-pulse delay-500" />

                    <div className="relative z-10 flex flex-col justify-center items-center p-12 text-center">
                        <div className="mb-8">
                            {/* Prominent Logo */}
                            <Link href="/" className="block mb-8 group">
                                <img 
                                    src="/images/bagcomics.jpeg" 
                                    alt="BAG Comics Logo" 
                                    className="h-32 w-32 object-cover mx-auto transition-transform duration-300 group-hover:scale-105 rounded-2xl"
                                />
                            </Link>
                            <p className="text-2xl text-gray-300 mb-8">
                                Join the Community
                            </p>
                            <p className="text-lg text-gray-400 max-w-md leading-relaxed mb-8">
                                Become part of a growing community of readers passionate about African storytelling.
                            </p>
                        </div>

                        {/* Benefits */}
                        <div className="space-y-4 mb-8">
                            {benefits.map((benefit, index) => (
                                <div key={index} className="flex items-center space-x-3 text-left">
                                    <div className="w-6 h-6 bg-red-500/30 rounded-full flex items-center justify-center border border-red-500/50 backdrop-blur-sm">
                                        <Check className="w-4 h-4 text-red-400" />
                                    </div>
                                    <span className="text-gray-300">{benefit}</span>
                                </div>
                            ))}
                        </div>

                        {/* Stats */}
                        <div className="grid grid-cols-3 gap-8">
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

                {/* Right Side - Registration Form */}
                <div className="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10 overflow-y-auto">
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-sm lg:bg-transparent lg:backdrop-blur-none" />
                    <div className="w-full max-w-md">
                        {/* Mobile Logo */}
                        <div className="lg:hidden text-center mb-8">
                            <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-red-400 via-red-500 to-red-600 bg-clip-text text-transparent">
                                BAG Comics
                            </h1>
                            <p className="text-gray-400">African Stories, Boldly Told</p>
                        </div>

                        <div className="bg-gray-800/80 backdrop-blur-md rounded-2xl p-8 border border-gray-700/50 shadow-2xl relative z-10">
                            <div className="text-center mb-8">
                                <h2 className="text-3xl font-bold text-white mb-2">Create Account</h2>
                                <p className="text-gray-400">Join thousands of readers exploring African stories</p>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                {/* Name Field */}
                                <div>
                                    <label htmlFor="name" className="block text-sm font-medium text-gray-300 mb-2">
                                        Full Name
                                    </label>
                                    <div className="relative">
                                        <User className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                        <input
                                            type="text"
                                            id="name"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            disabled={processing}
                                            className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                                                errors.name
                                                    ? 'border-red-500 focus:ring-red-500/50'
                                                    : 'border-gray-600 focus:border-red-500 focus:ring-red-500/50'
                                            }`}
                                            placeholder="Enter your full name"
                                        />
                                    </div>
                                    {errors.name && (
                                        <p className="mt-1 text-sm text-red-400">{errors.name}</p>
                                    )}
                                </div>

                                {/* Email Field */}
                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-gray-300 mb-2">
                                        Email Address
                                    </label>
                                    <div className="relative">
                                        <Mail className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                        <input
                                            type="email"
                                            id="email"
                                            required
                                            tabIndex={2}
                                            autoComplete="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            disabled={processing}
                                            className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                                                errors.email
                                                    ? 'border-red-500 focus:ring-red-500/50'
                                                    : 'border-gray-600 focus:border-red-500 focus:ring-red-500/50'
                                            }`}
                                            placeholder="Enter your email"
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="mt-1 text-sm text-red-400">{errors.email}</p>
                                    )}
                                </div>

                                {/* Password Field */}
                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-gray-300 mb-2">
                                        Password
                                    </label>
                                    <div className="relative">
                                        <Lock className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            id="password"
                                            required
                                            tabIndex={3}
                                            autoComplete="new-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            disabled={processing}
                                            className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-12 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                                                errors.password
                                                    ? 'border-red-500 focus:ring-red-500/50'
                                                    : 'border-gray-600 focus:border-red-500 focus:ring-red-500/50'
                                            }`}
                                            placeholder="Create a password"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-colors"
                                        >
                                            {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                        </button>
                                    </div>

                                    {/* Password Strength Indicator */}
                                    {data.password && (
                                        <div className="mt-2">
                                            <div className="flex items-center space-x-2">
                                                <div className="flex-1 bg-gray-600 rounded-full h-2">
                                                    <div
                                                        className={`h-2 rounded-full transition-all duration-300 ${getStrengthColor(passwordStrength())}`}
                                                        style={{ width: `${(passwordStrength() / 5) * 100}%` }}
                                                    />
                                                </div>
                                                <span className={`text-xs font-medium ${
                                                    passwordStrength() <= 2 ? 'text-red-400' :
                                                    passwordStrength() <= 3 ? 'text-yellow-400' : 'text-red-400'
                                                }`}>
                                                    {getStrengthText(passwordStrength())}
                                                </span>
                                            </div>
                                        </div>
                                    )}

                                    {errors.password && (
                                        <p className="mt-1 text-sm text-red-400">{errors.password}</p>
                                    )}
                                </div>

                                {/* Confirm Password Field */}
                                <div>
                                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-300 mb-2">
                                        Confirm Password
                                    </label>
                                    <div className="relative">
                                        <Lock className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                                        <input
                                            type={showConfirmPassword ? 'text' : 'password'}
                                            id="password_confirmation"
                                            required
                                            tabIndex={4}
                                            autoComplete="new-password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            disabled={processing}
                                            className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-12 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                                                errors.password_confirmation
                                                    ? 'border-red-500 focus:ring-red-500/50'
                                                    : 'border-gray-600 focus:border-red-500 focus:ring-red-500/50'
                                            }`}
                                            placeholder="Confirm your password"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                            className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-colors"
                                        >
                                            {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && (
                                        <p className="mt-1 text-sm text-red-400">{errors.password_confirmation}</p>
                                    )}
                                </div>

                                {/* Communication Preferences */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-3">
                                        Communication Preferences
                                    </label>
                                    <div className="space-y-3">
                                        <label className="flex items-start space-x-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.email_notifications}
                                                onChange={(e) => setData('email_notifications', e.target.checked)}
                                                className="mt-0.5 w-4 h-4 text-red-600 bg-gray-700/50 border-gray-600 rounded focus:ring-red-500 focus:ring-2"
                                            />
                                            <div>
                                                <span className="text-gray-300 text-sm">Email notifications</span>
                                                <p className="text-gray-400 text-xs">Receive important updates and account information via email</p>
                                            </div>
                                        </label>
                                        
                                        <label className="flex items-start space-x-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.new_releases_notifications}
                                                onChange={(e) => setData('new_releases_notifications', e.target.checked)}
                                                disabled={!data.email_notifications}
                                                className="mt-0.5 w-4 h-4 text-red-600 bg-gray-700/50 border-gray-600 rounded focus:ring-red-500 focus:ring-2 disabled:opacity-50"
                                            />
                                            <div>
                                                <span className="text-gray-300 text-sm">New comic notifications</span>
                                                <p className="text-gray-400 text-xs">Get notified when new comics are added to the platform</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {/* Submit Button */}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    tabIndex={5}
                                    className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 disabled:from-gray-600 disabled:to-gray-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-red-500/25 disabled:hover:scale-100 disabled:hover:shadow-none flex items-center justify-center space-x-2"
                                >
                                    {processing ? (
                                        <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                    ) : (
                                        <>
                                            <span>Create Account</span>
                                            <ArrowRight className="w-5 h-5" />
                                        </>
                                    )}
                                </button>
                            </form>

                            {/* Sign In Link */}
                            <div className="mt-8 text-center">
                                <p className="text-gray-400">
                                    Already have an account?{' '}
                                    <Link
                                        href={route('login')}
                                        tabIndex={6}
                                        className="text-red-400 hover:text-red-300 font-semibold transition-colors"
                                    >
                                        Sign in
                                    </Link>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
