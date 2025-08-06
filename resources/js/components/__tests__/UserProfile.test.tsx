import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { UserProfile } from '../UserProfile';

const mockUser = {
  id: 1,
  name: 'John Doe',
  email: 'john@example.com',
  avatar_path: '/avatar.jpg',
  created_at: '2024-01-01T00:00:00Z'
};

const mockStats = {
  total_comics_read: 25,
  total_pages_read: 1250,
  total_reading_time: 3600, // 60 hours
  average_rating_given: 4.2,
  favorite_genres: ['Action', 'Adventure', 'Sci-Fi'],
  reading_streak: 7,
  comics_this_month: 5,
  reviews_written: 12,
  helpful_votes_received: 45
};

const mockAchievements = [
  {
    id: 1,
    name: 'First Steps',
    description: 'Read your first comic',
    icon: '📚',
    earned_at: '2024-01-01T00:00:00Z',
    rarity: 'common' as const
  },
  {
    id: 2,
    name: 'Speed Reader',
    description: 'Read 10 comics in a week',
    icon: '⚡',
    earned_at: '2024-01-15T00:00:00Z',
    rarity: 'rare' as const
  },
  {
    id: 3,
    name: 'Legendary Collector',
    description: 'Own 100 comics',
    icon: '👑',
    earned_at: '2024-02-01T00:00:00Z',
    rarity: 'legendary' as const
  }
];

const mockRecentActivity = [
  {
    id: 1,
    type: 'read' as const,
    comic: {
      id: 1,
      title: 'Amazing Comic #1',
      cover_image_path: '/comic1.jpg'
    },
    created_at: '2024-02-01T00:00:00Z'
  },
  {
    id: 2,
    type: 'achievement' as const,
    achievement: mockAchievements[0],
    created_at: '2024-01-31T00:00:00Z'
  },
  {
    id: 3,
    type: 'review' as const,
    comic: {
      id: 2,
      title: 'Great Comic #2',
      cover_image_path: '/comic2.jpg'
    },
    created_at: '2024-01-30T00:00:00Z'
  }
];

describe('UserProfile', () => {
  const defaultProps = {
    user: mockUser,
    stats: mockStats,
    achievements: mockAchievements,
    recentActivity: mockRecentActivity
  };

  it('renders user information correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    expect(screen.getByText('John Doe')).toBeInTheDocument();
    expect(screen.getByText(/Member since/)).toBeInTheDocument();
    
    const avatar = screen.getByAltText('John Doe');
    expect(avatar).toHaveAttribute('src', '/avatar.jpg');
  });

  it('displays reading statistics correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    expect(screen.getByText('25')).toBeInTheDocument(); // Comics read
    expect(screen.getByText('12')).toBeInTheDocument(); // Reviews
    expect(screen.getByText('3')).toBeInTheDocument(); // Achievements
    expect(screen.getByText('7')).toBeInTheDocument(); // Reading streak
  });

  it('shows default avatar when no avatar_path', () => {
    const userWithoutAvatar = { ...mockUser, avatar_path: undefined };
    render(<UserProfile {...defaultProps} user={userWithoutAvatar} />);
    
    // Should show default user icon
    const defaultAvatar = document.querySelector('.w-24.h-24.rounded-full.bg-gray-200');
    expect(defaultAvatar).toBeInTheDocument();
  });

  it('switches between tabs correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Should start on overview tab
    expect(screen.getByText('Reading Statistics')).toBeInTheDocument();
    
    // Click achievements tab
    const achievementsTab = screen.getAllByText('Achievements')[1]; // Get the tab button, not the stats label
    fireEvent.click(achievementsTab);
    expect(screen.getByText('First Steps')).toBeInTheDocument();
    expect(screen.getByText('Speed Reader')).toBeInTheDocument();
    
    // Click activity tab
    const activityTab = screen.getAllByText('Recent Activity')[0]; // Get the tab button
    fireEvent.click(activityTab);
    expect(screen.getByText('Finished reading "Amazing Comic #1"')).toBeInTheDocument();
  });

  it('displays reading statistics in overview tab', () => {
    render(<UserProfile {...defaultProps} />);
    
    expect(screen.getByText('1,250')).toBeInTheDocument(); // Total pages
    expect(screen.getByText('2d 12h')).toBeInTheDocument(); // Reading time formatted
    expect(screen.getByText('5')).toBeInTheDocument(); // Comics this month
    expect(screen.getByText('45')).toBeInTheDocument(); // Helpful votes
  });

  it('displays favorite genres correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    expect(screen.getByText('Action')).toBeInTheDocument();
    expect(screen.getByText('Adventure')).toBeInTheDocument();
    expect(screen.getByText('Sci-Fi')).toBeInTheDocument();
  });

  it('formats reading time correctly', () => {
    const statsWithDifferentTimes = [
      { time: 30, expected: '30m' },
      { time: 90, expected: '1h 30m' },
      { time: 1500, expected: '1d 1h' }
    ];

    statsWithDifferentTimes.forEach(({ time, expected }) => {
      const statsWithTime = { ...mockStats, total_reading_time: time };
      const { unmount } = render(
        <UserProfile {...defaultProps} stats={statsWithTime} />
      );
      
      expect(screen.getByText(expected)).toBeInTheDocument();
      unmount();
    });
  });

  it('displays achievements with correct rarity colors', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Switch to achievements tab
    const achievementsTab = screen.getAllByText('Achievements')[1]; // Get the tab button
    fireEvent.click(achievementsTab);
    
    // Check for achievement names
    expect(screen.getByText('First Steps')).toBeInTheDocument();
    expect(screen.getByText('Speed Reader')).toBeInTheDocument();
    expect(screen.getByText('Legendary Collector')).toBeInTheDocument();
    
    // Check for rarity badges
    expect(screen.getByText('common')).toBeInTheDocument();
    expect(screen.getByText('rare')).toBeInTheDocument();
    expect(screen.getByText('legendary')).toBeInTheDocument();
  });

  it('displays recent activity correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Switch to activity tab
    fireEvent.click(screen.getByText('Recent Activity'));
    
    expect(screen.getByText('Finished reading "Amazing Comic #1"')).toBeInTheDocument();
    expect(screen.getByText('Earned "First Steps" achievement')).toBeInTheDocument();
    expect(screen.getByText('Reviewed "Great Comic #2"')).toBeInTheDocument();
  });

  it('shows "View All" button for achievements in overview', () => {
    render(<UserProfile {...defaultProps} />);
    
    const viewAllButton = screen.getByText('View All');
    expect(viewAllButton).toBeInTheDocument();
    
    // Clicking should switch to achievements tab
    fireEvent.click(viewAllButton);
    expect(screen.getByText('First Steps')).toBeInTheDocument();
  });

  it('displays activity icons correctly', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Switch to activity tab
    fireEvent.click(screen.getByText('Recent Activity'));
    
    // Should have icons for different activity types
    const activityItems = document.querySelectorAll('.w-8.h-8.rounded-full');
    expect(activityItems).toHaveLength(3); // One for each activity
  });

  it('shows comic covers in activity when available', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Switch to activity tab
    fireEvent.click(screen.getByText('Recent Activity'));
    
    const comicCover = screen.getByAltText('Amazing Comic #1');
    expect(comicCover).toHaveAttribute('src', '/comic1.jpg');
  });

  it('applies custom className', () => {
    const { container } = render(
      <UserProfile {...defaultProps} className="custom-class" />
    );
    
    expect(container.firstChild).toHaveClass('custom-class');
  });

  it('handles empty achievements array', () => {
    render(<UserProfile {...defaultProps} achievements={[]} />);
    
    // Should show 0 achievements in stats
    expect(screen.getByText('0')).toBeInTheDocument();
    
    // Switch to achievements tab
    const achievementsTab = screen.getAllByText('Achievements')[1]; // Get the tab button
    fireEvent.click(achievementsTab);
    
    // Should handle empty state gracefully
    expect(screen.queryByText('First Steps')).not.toBeInTheDocument();
  });

  it('handles empty activity array', () => {
    render(<UserProfile {...defaultProps} recentActivity={[]} />);
    
    // Switch to activity tab
    fireEvent.click(screen.getByText('Recent Activity'));
    
    // Should handle empty state gracefully
    expect(screen.queryByText('Finished reading')).not.toBeInTheDocument();
  });

  it('displays average rating with stars', () => {
    render(<UserProfile {...defaultProps} />);
    
    // Should show rating stars for average rating
    const ratingContainer = screen.getByText('4.2').closest('div');
    expect(ratingContainer).toBeInTheDocument();
  });
});