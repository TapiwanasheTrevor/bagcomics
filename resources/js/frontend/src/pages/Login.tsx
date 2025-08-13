import React, { useState } from 'react';
import { Eye, EyeOff, Mail, Lock, ArrowRight, BookOpen, Star, Users } from 'lucide-react';
import type { Page } from '../App';

interface LoginProps {
  onNavigate: (page: Page) => void;
  onLogin: (userData: any) => void;
}

const Login: React.FC<LoginProps> = ({ onNavigate, onLogin }) => {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    rememberMe: false
  });
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }));
    }
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.email) {
      newErrors.email = 'Email is required';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'Please enter a valid email';
    }
    
    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 6) {
      newErrors.password = 'Password must be at least 6 characters';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    setIsLoading(true);
    
    // Simulate API call
    setTimeout(() => {
      setIsLoading(false);
      onLogin({
        email: formData.email,
        name: formData.email.split('@')[0],
        avatar: null
      });
      onNavigate('home');
    }, 1500);
  };

  const stats = [
    { icon: BookOpen, label: 'Comics', value: '500+' },
    { icon: Star, label: 'Creators', value: '50+' },
    { icon: Users, label: 'Readers', value: '10K+' }
  ];

  return (
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
        <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/30 via-purple-500/30 to-orange-500/30" />
        
        {/* Animated Background Elements */}
        <div className="absolute top-20 left-20 w-32 h-32 bg-emerald-500/20 rounded-full blur-xl animate-pulse" />
        <div className="absolute bottom-40 right-20 w-48 h-48 bg-purple-500/20 rounded-full blur-xl animate-pulse delay-1000" />
        <div className="absolute top-1/2 left-1/3 w-24 h-24 bg-orange-500/20 rounded-full blur-xl animate-pulse delay-500" />
        
        <div className="relative z-10 flex flex-col justify-center items-center p-12 text-center">
          <div className="mb-8">
            <h1 className="text-6xl font-bold mb-4 bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
              BAG Comics
            </h1>
            <p className="text-2xl text-gray-300 mb-8">
              African Stories, Boldly Told
            </p>
            <p className="text-lg text-gray-400 max-w-md leading-relaxed">
              Discover epic tales from the motherland. Heroes, folklore, and futuristic adventures 
              await in our collection of African comics.
            </p>
          </div>
          
          {/* Stats */}
          <div className="grid grid-cols-3 gap-8 mt-12">
            {stats.map(({ icon: Icon, label, value }) => (
              <div key={label} className="text-center">
                <div className="w-16 h-16 bg-gradient-to-r from-emerald-500/30 to-purple-500/30 rounded-full flex items-center justify-center mx-auto mb-3 border border-emerald-500/50 backdrop-blur-sm">
                  <Icon className="w-8 h-8 text-emerald-400" />
                </div>
                <div className="text-2xl font-bold text-white">{value}</div>
                <div className="text-sm text-gray-400">{label}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Right Side - Login Form */}
      <div className="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10">
        <div className="absolute inset-0 bg-black/40 backdrop-blur-sm lg:bg-transparent lg:backdrop-blur-none" />
        <div className="w-full max-w-md">
          {/* Mobile Logo */}
          <div className="lg:hidden text-center mb-8">
            <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-emerald-400 via-orange-400 to-purple-400 bg-clip-text text-transparent">
              BAG Comics
            </h1>
            <p className="text-gray-400">African Stories, Boldly Told</p>
          </div>

          <div className="bg-gray-800/80 backdrop-blur-md rounded-2xl p-8 border border-gray-700/50 shadow-2xl relative z-10">
            <div className="text-center mb-8">
              <h2 className="text-3xl font-bold text-white mb-2">Welcome Back</h2>
              <p className="text-gray-400">Sign in to continue your reading journey</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
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
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                      errors.email 
                        ? 'border-red-500 focus:ring-red-500/50' 
                        : 'border-gray-600 focus:border-emerald-500 focus:ring-emerald-500/50'
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
                    name="password"
                    value={formData.password}
                    onChange={handleInputChange}
                    className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-12 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                      errors.password 
                        ? 'border-red-500 focus:ring-red-500/50' 
                        : 'border-gray-600 focus:border-emerald-500 focus:ring-emerald-500/50'
                    }`}
                    placeholder="Enter your password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-colors"
                  >
                    {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                  </button>
                </div>
                {errors.password && (
                  <p className="mt-1 text-sm text-red-400">{errors.password}</p>
                )}
              </div>

              {/* Remember Me & Forgot Password */}
              <div className="flex items-center justify-between">
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    name="rememberMe"
                    checked={formData.rememberMe}
                    onChange={handleInputChange}
                    className="w-4 h-4 text-emerald-500 bg-gray-700 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2"
                  />
                  <span className="ml-2 text-sm text-gray-300">Remember me</span>
                </label>
                <button
                  type="button"
                  className="text-sm text-emerald-400 hover:text-emerald-300 transition-colors"
                >
                  Forgot password?
                </button>
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                disabled={isLoading}
                className="w-full bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 disabled:from-gray-600 disabled:to-gray-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-emerald-500/25 disabled:hover:scale-100 disabled:hover:shadow-none flex items-center justify-center space-x-2"
              >
                {isLoading ? (
                  <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : (
                  <>
                    <span>Log In</span>
                    <ArrowRight className="w-5 h-5" />
                  </>
                )}
              </button>
            </form>

            {/* Divider */}
            <div className="my-8 flex items-center">
              <div className="flex-1 border-t border-gray-600"></div>
              <span className="px-4 text-sm text-gray-400">or</span>
              <div className="flex-1 border-t border-gray-600"></div>
            </div>

            {/* Social Login */}
            <div className="space-y-3">
              <button className="w-full bg-white hover:bg-gray-100 text-gray-900 font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 flex items-center justify-center space-x-2">
                <svg className="w-5 h-5" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>Continue with Google</span>
              </button>
              
              <button className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 flex items-center justify-center space-x-2">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span>Continue with Facebook</span>
              </button>
            </div>

            {/* Sign Up Link */}
            <div className="mt-8 text-center">
              <p className="text-gray-400">
                Don't have an account?{' '}
                <button
                  onClick={() => onNavigate('register')}
                  className="text-emerald-400 hover:text-emerald-300 font-semibold transition-colors"
                >
                  Sign up for free
                </button>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;