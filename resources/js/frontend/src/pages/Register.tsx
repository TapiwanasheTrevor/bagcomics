import React, { useState } from 'react';
import { Eye, EyeOff, Mail, Lock, User, ArrowRight, BookOpen, Star, Users, Check } from 'lucide-react';
import type { Page } from '../App';

interface RegisterProps {
  onNavigate: (page: Page) => void;
  onLogin: (userData: any) => void;
}

const Register: React.FC<RegisterProps> = ({ onNavigate, onLogin }) => {
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    password: '',
    confirmPassword: '',
    agreeToTerms: false,
    subscribeNewsletter: true
  });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
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
    
    if (!formData.firstName.trim()) {
      newErrors.firstName = 'First name is required';
    }
    
    if (!formData.lastName.trim()) {
      newErrors.lastName = 'Last name is required';
    }
    
    if (!formData.email) {
      newErrors.email = 'Email is required';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'Please enter a valid email';
    }
    
    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
    } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(formData.password)) {
      newErrors.password = 'Password must contain uppercase, lowercase, and number';
    }
    
    if (!formData.confirmPassword) {
      newErrors.confirmPassword = 'Please confirm your password';
    } else if (formData.password !== formData.confirmPassword) {
      newErrors.confirmPassword = 'Passwords do not match';
    }
    
    if (!formData.agreeToTerms) {
      newErrors.agreeToTerms = 'You must agree to the terms and conditions';
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
        name: `${formData.firstName} ${formData.lastName}`,
        firstName: formData.firstName,
        lastName: formData.lastName,
        avatar: null
      });
      onNavigate('home');
    }, 2000);
  };

  const passwordStrength = () => {
    const password = formData.password;
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
    return 'bg-emerald-500';
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
                <div className="w-6 h-6 bg-emerald-500/30 rounded-full flex items-center justify-center border border-emerald-500/50 backdrop-blur-sm">
                  <Check className="w-4 h-4 text-emerald-400" />
                </div>
                <span className="text-gray-300">{benefit}</span>
              </div>
            ))}
          </div>
          
          {/* Stats */}
          <div className="grid grid-cols-3 gap-8">
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

      {/* Right Side - Registration Form */}
      <div className="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10 overflow-y-auto">
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
              <h2 className="text-3xl font-bold text-white mb-2">Sign Up</h2>
              <p className="text-gray-400">Join thousands of readers exploring African stories</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Name Fields */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="firstName" className="block text-sm font-medium text-gray-300 mb-2">
                    First Name
                  </label>
                  <div className="relative">
                    <User className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                    <input
                      type="text"
                      id="firstName"
                      name="firstName"
                      value={formData.firstName}
                      onChange={handleInputChange}
                      className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                        errors.firstName 
                          ? 'border-red-500 focus:ring-red-500/50' 
                          : 'border-gray-600 focus:border-emerald-500 focus:ring-emerald-500/50'
                      }`}
                      placeholder="First name"
                    />
                  </div>
                  {errors.firstName && (
                    <p className="mt-1 text-sm text-red-400">{errors.firstName}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="lastName" className="block text-sm font-medium text-gray-300 mb-2">
                    Last Name
                  </label>
                  <input
                    type="text"
                    id="lastName"
                    name="lastName"
                    value={formData.lastName}
                    onChange={handleInputChange}
                    className={`w-full bg-gray-700/50 border rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                      errors.lastName 
                        ? 'border-red-500 focus:ring-red-500/50' 
                        : 'border-gray-600 focus:border-emerald-500 focus:ring-emerald-500/50'
                    }`}
                    placeholder="Last name"
                  />
                  {errors.lastName && (
                    <p className="mt-1 text-sm text-red-400">{errors.lastName}</p>
                  )}
                </div>
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
                {formData.password && (
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
                        passwordStrength() <= 3 ? 'text-yellow-400' : 'text-emerald-400'
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
                <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-300 mb-2">
                  Confirm Password
                </label>
                <div className="relative">
                  <Lock className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                  <input
                    type={showConfirmPassword ? 'text' : 'password'}
                    id="confirmPassword"
                    name="confirmPassword"
                    value={formData.confirmPassword}
                    onChange={handleInputChange}
                    className={`w-full bg-gray-700/50 border rounded-lg pl-10 pr-12 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 transition-all duration-300 ${
                      errors.confirmPassword 
                        ? 'border-red-500 focus:ring-red-500/50' 
                        : 'border-gray-600 focus:border-emerald-500 focus:ring-emerald-500/50'
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
                {errors.confirmPassword && (
                  <p className="mt-1 text-sm text-red-400">{errors.confirmPassword}</p>
                )}
              </div>

              {/* Checkboxes */}
              <div className="space-y-4">
                <label className="flex items-start space-x-3">
                  <input
                    type="checkbox"
                    name="agreeToTerms"
                    checked={formData.agreeToTerms}
                    onChange={handleInputChange}
                    className="w-4 h-4 text-emerald-500 bg-gray-700 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2 mt-1"
                  />
                  <span className="text-sm text-gray-300">
                    I agree to the{' '}
                    <button type="button" className="text-emerald-400 hover:text-emerald-300 underline">
                      Terms of Service
                    </button>{' '}
                    and{' '}
                    <button type="button" className="text-emerald-400 hover:text-emerald-300 underline">
                      Privacy Policy
                    </button>
                  </span>
                </label>
                {errors.agreeToTerms && (
                  <p className="text-sm text-red-400">{errors.agreeToTerms}</p>
                )}

                <label className="flex items-center space-x-3">
                  <input
                    type="checkbox"
                    name="subscribeNewsletter"
                    checked={formData.subscribeNewsletter}
                    onChange={handleInputChange}
                    className="w-4 h-4 text-emerald-500 bg-gray-700 border-gray-600 rounded focus:ring-emerald-500 focus:ring-2"
                  />
                  <span className="text-sm text-gray-300">
                    Subscribe to newsletter for new releases and updates
                  </span>
                </label>
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
                    <span>Sign Up</span>
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

            {/* Social Registration */}
            <div className="space-y-3">
              <button className="w-full bg-white hover:bg-gray-100 text-gray-900 font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 flex items-center justify-center space-x-2">
                <svg className="w-5 h-5" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>Sign up with Google</span>
              </button>
              
              <button className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 hover:scale-105 flex items-center justify-center space-x-2">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span>Sign up with Facebook</span>
              </button>
            </div>

            {/* Sign In Link */}
            <div className="mt-8 text-center">
              <p className="text-gray-400">
                Already have an account?{' '}
                <button
                  onClick={() => onNavigate('login')}
                  className="text-emerald-400 hover:text-emerald-300 font-semibold transition-colors"
                >
                  Sign in
                </button>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Register;