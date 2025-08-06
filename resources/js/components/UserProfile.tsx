import React, { useState } from 'react';
import { User, BookOpen, Star, Trophy, Calendar, TrendingUp, Heart, MessageSquare } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Badge } from './ui/badge';
import { Button } from './ui/button';
import { RatingStars } from './RatingStars';

interface ReadingStats {
  total_comics_read: number;
  total_pages_read: number;
  total_reading_time: number; // in minutes
  average_rating_given: number;
  favorite_genres: string[];
  reading_streak: number;
  comics_this_month: number;
  reviews_written: number;
  helpful_votes_received: number;
}

interface Achievement {
  id: number;
  name: string;
  description: string;
  icon: string;
  earned_at: string;
  rarity: 'common' | 'rare' | 'epic' | 'legendary';
}

interface RecentActivity {
  id: number;
  type: 'read' | 'review' | 'favorite' | 'achievement';
  comic?: {
    id: number;
    title: string;
    cover_image_path?: string;
  };
  achievement?: Achievement;
  created_at: string;
  metadata?: any;
}

interface UserProfileProps {
  user: {
    id: number;
    name: string;
    email: string;
    avatar_path?: string;
    created_at: string;
  };
  stats: ReadingStats;
  achievements: Achievement[];
  recentActivity: RecentActivity[];
  isOwnProfile?: boolean;
  className?: string;
}

export const UserProfile: React.FC<UserProfileProps> = ({
  user,
  stats,
  achievements,
  recentActivity,
  isOwnProfile = false,
  className = ''
}) => {
  const [activeTab, setActiveTab] = useState<'overview' | 'achievements' | 'activity'>('overview');

  const formatReadingTime = (minutes: number) => {
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ${minutes % 60}m`;
    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
  };

  const getAchievementColor = (rarity: Achievement['rarity']) => {
    switch (rarity) {
      case 'legendary': return 'bg-gradient-to-r from-yellow-400 to-orange-500';
      case 'epic': return 'bg-gradient-to-r from-purple-500 to-pink-500';
      case 'rare': return 'bg-gradient-to-r from-blue-500 to-cyan-500';
      default: return 'bg-gray-500';
    }
  };

  const getActivityIcon = (activity: RecentActivity) => {
    switch (activity.type) {
      case 'read': return <BookOpen className="w-4 h-4" />;
      case 'review': return <MessageSquare className="w-4 h-4" />;
      case 'favorite': return <Heart className="w-4 h-4" />;
      case 'achievement': return <Trophy className="w-4 h-4" />;
      default: return <BookOpen className="w-4 h-4" />;
    }
  };

  const getActivityDescription = (activity: RecentActivity) => {
    switch (activity.type) {
      case 'read':
        return `Finished reading "${activity.comic?.title}"`;
      case 'review':
        return `Reviewed "${activity.comic?.title}"`;
      case 'favorite':
        return `Added "${activity.comic?.title}" to favorites`;
      case 'achievement':
        return `Earned "${activity.achievement?.name}" achievement`;
      default:
        return 'Unknown activity';
    }
  };

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Profile Header */}
      <Card>
        <CardContent className="p-6">
          <div className="flex items-start gap-6">
            {user.avatar_path ? (
              <img
                src={user.avatar_path}
                alt={user.name}
                className="w-24 h-24 rounded-full object-cover"
              />
            ) : (
              <div className="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center">
                <User className="w-12 h-12 text-gray-500" />
              </div>
            )}
            
            <div className="flex-1">
              <h1 className="text-2xl font-bold mb-2">{user.name}</h1>
              <p className="text-gray-600 mb-4">
                Member since {new Date(user.created_at).toLocaleDateString()}
              </p>
              
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="text-center">
                  <div className="text-2xl font-bold text-blue-600">{stats.total_comics_read}</div>
                  <div className="text-sm text-gray-600">Comics Read</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-600">{stats.reviews_written}</div>
                  <div className="text-sm text-gray-600">Reviews</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-purple-600">{achievements.length}</div>
                  <div className="text-sm text-gray-600">Achievements</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-orange-600">{stats.reading_streak}</div>
                  <div className="text-sm text-gray-600">Day Streak</div>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Navigation Tabs */}
      <div className="flex space-x-1 bg-gray-100 p-1 rounded-lg">
        {[
          { key: 'overview', label: 'Overview' },
          { key: 'achievements', label: 'Achievements' },
          { key: 'activity', label: 'Recent Activity' }
        ].map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key as any)}
            className={`
              flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors
              ${activeTab === tab.key 
                ? 'bg-white text-gray-900 shadow-sm' 
                : 'text-gray-600 hover:text-gray-900'
              }
            `}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      {activeTab === 'overview' && (
        <div className="grid md:grid-cols-2 gap-6">
          {/* Reading Statistics */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingUp className="w-5 h-5" />
                Reading Statistics
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex justify-between">
                <span>Total Pages Read</span>
                <span className="font-medium">{stats.total_pages_read.toLocaleString()}</span>
              </div>
              <div className="flex justify-between">
                <span>Reading Time</span>
                <span className="font-medium">{formatReadingTime(stats.total_reading_time)}</span>
              </div>
              <div className="flex justify-between">
                <span>Average Rating Given</span>
                <RatingStars rating={stats.average_rating_given} size="sm" showValue />
              </div>
              <div className="flex justify-between">
                <span>Comics This Month</span>
                <span className="font-medium">{stats.comics_this_month}</span>
              </div>
              <div className="flex justify-between">
                <span>Helpful Votes Received</span>
                <span className="font-medium">{stats.helpful_votes_received}</span>
              </div>
            </CardContent>
          </Card>

          {/* Favorite Genres */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Heart className="w-5 h-5" />
                Favorite Genres
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-2">
                {stats.favorite_genres.map((genre) => (
                  <Badge key={genre} variant="secondary">
                    {genre}
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Recent Achievements */}
          <Card className="md:col-span-2">
            <CardHeader>
              <CardTitle className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Trophy className="w-5 h-5" />
                  Recent Achievements
                </div>
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={() => setActiveTab('achievements')}
                >
                  View All
                </Button>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {achievements.slice(0, 3).map((achievement) => (
                  <div
                    key={achievement.id}
                    className={`
                      p-4 rounded-lg text-white text-center
                      ${getAchievementColor(achievement.rarity)}
                    `}
                  >
                    <div className="text-2xl mb-2">{achievement.icon}</div>
                    <h4 className="font-medium mb-1">{achievement.name}</h4>
                    <p className="text-sm opacity-90">{achievement.description}</p>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {activeTab === 'achievements' && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {achievements.map((achievement) => (
            <Card key={achievement.id}>
              <CardContent className="p-4">
                <div className={`
                  w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center text-white text-2xl
                  ${getAchievementColor(achievement.rarity)}
                `}>
                  {achievement.icon}
                </div>
                <h3 className="font-medium text-center mb-2">{achievement.name}</h3>
                <p className="text-sm text-gray-600 text-center mb-3">{achievement.description}</p>
                <div className="flex items-center justify-between">
                  <Badge variant={achievement.rarity === 'legendary' ? 'default' : 'secondary'}>
                    {achievement.rarity}
                  </Badge>
                  <span className="text-xs text-gray-500">
                    {new Date(achievement.earned_at).toLocaleDateString()}
                  </span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {activeTab === 'activity' && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="w-5 h-5" />
              Recent Activity
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {recentActivity.map((activity) => (
                <div key={activity.id} className="flex items-start gap-4 p-3 rounded-lg hover:bg-gray-50">
                  <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                    {getActivityIcon(activity)}
                  </div>
                  
                  <div className="flex-1">
                    <p className="text-sm">{getActivityDescription(activity)}</p>
                    <p className="text-xs text-gray-500 mt-1">
                      {new Date(activity.created_at).toLocaleDateString()}
                    </p>
                  </div>

                  {activity.comic?.cover_image_path && (
                    <img
                      src={activity.comic.cover_image_path}
                      alt={activity.comic.title}
                      className="w-12 h-16 object-cover rounded"
                    />
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
};