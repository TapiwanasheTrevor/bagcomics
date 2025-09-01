import React from 'react';
import { Trophy, Lock, Star, Zap, BookOpen, Users, Target, Award, Crown, Flame, MessageCircle, List, Compass, Book, CheckCircle, Books, Library } from 'lucide-react';

interface Progress {
    type: string;
    current: number;
    target: number;
    percentage: number;
    description: string;
}

interface Achievement {
    id: number;
    key: string;
    name: string;
    description: string;
    category: string;
    type: string;
    icon: string;
    color: string;
    rarity: string;
    rarity_display: string;
    rarity_color: string;
    points: number;
    requirements?: any[];
    progress?: Progress[];
    is_unlocked?: boolean;
    unlocked_at?: string;
    is_seen?: boolean;
    unlock_order?: number;
}

interface AchievementCardProps {
    achievement: Achievement;
    variant?: 'default' | 'compact' | 'detailed';
    showProgress?: boolean;
    onClick?: () => void;
}

const iconMap: Record<string, any> = {
    'book-open': BookOpen,
    'library': Library,
    'books': Books,
    'check-circle': CheckCircle,
    'zap': Zap,
    'flame': Flame,
    'message-circle': MessageCircle,
    'list': List,
    'users': Users,
    'compass': Compass,
    'book': Book,
    'crown': Crown,
    'trophy': Trophy,
    'award': Award,
    'star': Star,
    'target': Target
};

const colorMap: Record<string, string> = {
    'blue': 'text-blue-400 bg-blue-500/20 border-blue-500/30',
    'green': 'text-green-400 bg-green-500/20 border-green-500/30',
    'purple': 'text-purple-400 bg-purple-500/20 border-purple-500/30',
    'yellow': 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30',
    'orange': 'text-orange-400 bg-orange-500/20 border-orange-500/30',
    'red': 'text-red-400 bg-red-500/20 border-red-500/30',
    'pink': 'text-pink-400 bg-pink-500/20 border-pink-500/30',
    'teal': 'text-teal-400 bg-teal-500/20 border-teal-500/30',
    'indigo': 'text-indigo-400 bg-indigo-500/20 border-indigo-500/30',
    'gold': 'text-yellow-400 bg-yellow-500/20 border-yellow-500/30'
};

const rarityGlow: Record<string, string> = {
    'common': '',
    'uncommon': 'shadow-lg shadow-green-500/20',
    'rare': 'shadow-lg shadow-blue-500/20',
    'epic': 'shadow-lg shadow-purple-500/30',
    'legendary': 'shadow-lg shadow-yellow-500/40'
};

export default function AchievementCard({ 
    achievement, 
    variant = 'default', 
    showProgress = true,
    onClick 
}: AchievementCardProps) {
    const Icon = iconMap[achievement.icon] || Trophy;
    const isUnlocked = achievement.is_unlocked;
    
    const cardClasses = `
        group relative bg-gray-800/50 rounded-xl border transition-all duration-300 overflow-hidden
        ${isUnlocked 
            ? `${achievement.rarity_color.split(' ')[2]} hover:scale-105 ${rarityGlow[achievement.rarity]}` 
            : 'border-gray-700 opacity-75'
        }
        ${onClick ? 'cursor-pointer hover:bg-gray-800/70' : ''}
    `;

    if (variant === 'compact') {
        return (
            <div className={cardClasses} onClick={onClick}>
                <div className="flex items-center p-3">
                    <div className={`flex-shrink-0 p-2 rounded-lg ${colorMap[achievement.color] || 'text-gray-400 bg-gray-700'} ${!isUnlocked ? 'grayscale' : ''}`}>
                        {isUnlocked ? <Icon className="w-5 h-5" /> : <Lock className="w-5 h-5" />}
                    </div>
                    
                    <div className="ml-3 flex-1 min-w-0">
                        <div className="flex items-center space-x-2">
                            <h4 className={`font-medium truncate ${isUnlocked ? 'text-white' : 'text-gray-400'}`}>
                                {achievement.name}
                            </h4>
                            {isUnlocked && (
                                <span className={`px-2 py-0.5 text-xs rounded ${achievement.rarity_color}`}>
                                    {achievement.rarity_display}
                                </span>
                            )}
                        </div>
                        <p className={`text-sm truncate ${isUnlocked ? 'text-gray-300' : 'text-gray-500'}`}>
                            {achievement.description}
                        </p>
                    </div>
                    
                    <div className="flex-shrink-0 text-right">
                        <div className={`text-lg font-bold ${isUnlocked ? 'text-yellow-400' : 'text-gray-500'}`}>
                            {achievement.points}
                        </div>
                        <div className="text-xs text-gray-500">points</div>
                    </div>
                </div>
            </div>
        );
    }

    if (variant === 'detailed') {
        return (
            <div className={cardClasses} onClick={onClick}>
                {/* Header */}
                <div className="p-6">
                    <div className="flex items-start justify-between">
                        <div className="flex items-start space-x-4">
                            <div className={`p-3 rounded-xl ${colorMap[achievement.color] || 'text-gray-400 bg-gray-700'} ${!isUnlocked ? 'grayscale' : ''}`}>
                                {isUnlocked ? <Icon className="w-8 h-8" /> : <Lock className="w-8 h-8" />}
                            </div>
                            
                            <div className="flex-1">
                                <div className="flex items-center space-x-2 mb-2">
                                    <h3 className={`text-xl font-bold ${isUnlocked ? 'text-white' : 'text-gray-400'}`}>
                                        {achievement.name}
                                    </h3>
                                    {isUnlocked && (
                                        <span className={`px-2 py-1 text-sm rounded ${achievement.rarity_color}`}>
                                            {achievement.rarity_display}
                                        </span>
                                    )}
                                </div>
                                
                                <p className={`text-sm mb-3 ${isUnlocked ? 'text-gray-300' : 'text-gray-500'}`}>
                                    {achievement.description}
                                </p>
                                
                                <div className="flex items-center space-x-4 text-sm">
                                    <span className={`px-3 py-1 rounded-full ${colorMap[achievement.color] || 'bg-gray-700 text-gray-400'}`}>
                                        {achievement.category}
                                    </span>
                                    <span className="text-gray-400">"</span>
                                    <span className="text-gray-400 capitalize">{achievement.type}</span>
                                    {isUnlocked && achievement.unlocked_at && (
                                        <>
                                            <span className="text-gray-400">"</span>
                                            <span className="text-gray-400">
                                                {new Date(achievement.unlocked_at).toLocaleDateString()}
                                            </span>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                        
                        <div className="text-right">
                            <div className={`text-2xl font-bold ${isUnlocked ? 'text-yellow-400' : 'text-gray-500'}`}>
                                {achievement.points}
                            </div>
                            <div className="text-sm text-gray-400">points</div>
                        </div>
                    </div>
                </div>

                {/* Progress Section */}
                {!isUnlocked && showProgress && achievement.progress && achievement.progress.length > 0 && (
                    <div className="px-6 pb-6">
                        <div className="border-t border-gray-700 pt-4">
                            <h4 className="text-sm font-medium text-gray-300 mb-3">Progress</h4>
                            <div className="space-y-3">
                                {achievement.progress.map((progress, index) => (
                                    <div key={index}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="text-gray-400">{progress.description}</span>
                                            <span className="text-white font-medium">{progress.percentage}%</span>
                                        </div>
                                        <div className="w-full bg-gray-700 rounded-full h-2">
                                            <div 
                                                className={`h-2 rounded-full transition-all duration-500 ${
                                                    progress.percentage >= 100 ? 'bg-green-500' :
                                                    progress.percentage >= 75 ? 'bg-blue-500' :
                                                    progress.percentage >= 50 ? 'bg-yellow-500' : 'bg-gray-500'
                                                }`}
                                                style={{ width: `${Math.min(progress.percentage, 100)}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        );
    }

    // Default variant
    return (
        <div className={cardClasses} onClick={onClick}>
            {/* Rarity indicator */}
            {isUnlocked && achievement.rarity !== 'common' && (
                <div className="absolute top-0 right-0 w-0 h-0 border-l-[20px] border-l-transparent border-t-[20px] border-t-yellow-500">
                    <Star className="absolute -top-4 -right-1 w-3 h-3 text-white" />
                </div>
            )}

            <div className="p-4">
                <div className="flex items-start space-x-3">
                    <div className={`p-3 rounded-lg ${colorMap[achievement.color] || 'text-gray-400 bg-gray-700'} ${!isUnlocked ? 'grayscale' : ''}`}>
                        {isUnlocked ? <Icon className="w-6 h-6" /> : <Lock className="w-6 h-6" />}
                    </div>
                    
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between mb-1">
                            <h3 className={`font-semibold ${isUnlocked ? 'text-white' : 'text-gray-400'}`}>
                                {achievement.name}
                            </h3>
                            <span className={`text-lg font-bold ${isUnlocked ? 'text-yellow-400' : 'text-gray-500'}`}>
                                {achievement.points}
                            </span>
                        </div>
                        
                        <p className={`text-sm mb-3 ${isUnlocked ? 'text-gray-300' : 'text-gray-500'}`}>
                            {achievement.description}
                        </p>
                        
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                {isUnlocked ? (
                                    <span className={`px-2 py-1 text-xs rounded ${achievement.rarity_color}`}>
                                        {achievement.rarity_display}
                                    </span>
                                ) : (
                                    <span className="px-2 py-1 text-xs rounded bg-gray-700 text-gray-400">
                                        Locked
                                    </span>
                                )}
                                <span className={`text-xs px-2 py-1 rounded-full ${colorMap[achievement.color] || 'bg-gray-700 text-gray-400'}`}>
                                    {achievement.category}
                                </span>
                            </div>
                            
                            {isUnlocked && achievement.unlocked_at && (
                                <span className="text-xs text-gray-500">
                                    {new Date(achievement.unlocked_at).toLocaleDateString()}
                                </span>
                            )}
                        </div>

                        {/* Progress bar for locked achievements */}
                        {!isUnlocked && showProgress && achievement.progress && achievement.progress.length > 0 && (
                            <div className="mt-3 space-y-2">
                                {achievement.progress.slice(0, 1).map((progress, index) => (
                                    <div key={index}>
                                        <div className="flex justify-between text-xs mb-1">
                                            <span className="text-gray-500 truncate">{progress.description}</span>
                                            <span className="text-gray-400">{progress.percentage}%</span>
                                        </div>
                                        <div className="w-full bg-gray-700 rounded-full h-1.5">
                                            <div 
                                                className={`h-1.5 rounded-full transition-all duration-500 ${
                                                    progress.percentage >= 100 ? 'bg-green-500' :
                                                    progress.percentage >= 75 ? 'bg-blue-500' :
                                                    progress.percentage >= 50 ? 'bg-yellow-500' : 'bg-gray-500'
                                                }`}
                                                style={{ width: `${Math.min(progress.percentage, 100)}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}