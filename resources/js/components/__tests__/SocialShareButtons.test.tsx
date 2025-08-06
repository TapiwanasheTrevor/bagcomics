import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { SocialShareButtons } from '../SocialShareButtons';

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({
  router: {
    post: vi.fn()
  }
}));

// Mock window.open
const mockWindowOpen = vi.fn();
Object.defineProperty(window, 'open', {
  value: mockWindowOpen,
  writable: true
});

// Mock navigator.clipboard
const mockClipboard = {
  writeText: vi.fn()
};
Object.defineProperty(navigator, 'clipboard', {
  value: mockClipboard,
  writable: true
});

// Mock navigator.share
const mockShare = vi.fn();
Object.defineProperty(navigator, 'share', {
  value: mockShare,
  writable: true
});

describe('SocialShareButtons', () => {
  const defaultProps = {
    comicId: 1,
    comicTitle: 'Amazing Comic',
    shareUrl: 'https://example.com/comics/1',
    comicCover: '/cover.jpg'
  };

  beforeEach(() => {
    vi.clearAllMocks();
    // Reset navigator.share
    Object.defineProperty(navigator, 'share', {
      value: undefined,
      writable: true
    });
  });

  it('renders share button', () => {
    render(<SocialShareButtons {...defaultProps} />);
    expect(screen.getByText('Share')).toBeInTheDocument();
  });

  it('opens share menu when clicked (no native share)', () => {
    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    
    expect(screen.getByText('Share this comic')).toBeInTheDocument();
    expect(screen.getByText('Share on Facebook')).toBeInTheDocument();
    expect(screen.getByText('Share on Twitter')).toBeInTheDocument();
    expect(screen.getByText('Share on Instagram')).toBeInTheDocument();
    expect(screen.getByText('Share on WhatsApp')).toBeInTheDocument();
    expect(screen.getByText('Copy Link')).toBeInTheDocument();
  });

  it('uses native share when available', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    
    Object.defineProperty(navigator, 'share', {
      value: mockShare,
      writable: true
    });
    mockShare.mockResolvedValue(undefined);
    mockRouter.post.mockResolvedValue({});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    
    await waitFor(() => {
      expect(mockShare).toHaveBeenCalledWith({
        title: 'Amazing Comic',
        text: 'Discovered an awesome comic: "Amazing Comic"',
        url: 'https://example.com/comics/1'
      });
    });

    expect(mockRouter.post).toHaveBeenCalledWith('/api/comics/1/share', {
      platform: 'native',
      share_type: 'discovery',
      metadata: {
        title: 'Amazing Comic',
        url: 'https://example.com/comics/1',
        message: 'Discovered an awesome comic: "Amazing Comic"'
      }
    });
  });

  it('shares to Facebook correctly', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockResolvedValue({});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    fireEvent.click(screen.getByText('Share on Facebook'));
    
    await waitFor(() => {
      expect(mockRouter.post).toHaveBeenCalledWith('/api/comics/1/share', {
        platform: 'facebook',
        share_type: 'discovery',
        metadata: {
          title: 'Amazing Comic',
          url: 'https://example.com/comics/1',
          message: 'Discovered an awesome comic: "Amazing Comic"'
        }
      });
    });

    expect(mockWindowOpen).toHaveBeenCalledWith(
      expect.stringContaining('facebook.com/sharer'),
      '_blank',
      'width=600,height=400'
    );
  });

  it('shares to Twitter correctly', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockResolvedValue({});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    fireEvent.click(screen.getByText('Share on Twitter'));
    
    await waitFor(() => {
      expect(mockRouter.post).toHaveBeenCalledWith('/api/comics/1/share', {
        platform: 'twitter',
        share_type: 'discovery',
        metadata: {
          title: 'Amazing Comic',
          url: 'https://example.com/comics/1',
          message: 'Discovered an awesome comic: "Amazing Comic"'
        }
      });
    });

    expect(mockWindowOpen).toHaveBeenCalledWith(
      expect.stringContaining('twitter.com/intent/tweet'),
      '_blank',
      'width=600,height=400'
    );
  });

  it('copies link to clipboard', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockClipboard.writeText.mockResolvedValue(undefined);
    mockRouter.post.mockResolvedValue({});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    fireEvent.click(screen.getByText('Copy Link'));
    
    await waitFor(() => {
      expect(mockClipboard.writeText).toHaveBeenCalledWith('https://example.com/comics/1');
    });

    expect(mockRouter.post).toHaveBeenCalledWith('/api/comics/1/share', {
      platform: 'copy_link',
      share_type: 'discovery',
      metadata: {
        title: 'Amazing Comic',
        url: 'https://example.com/comics/1',
        message: 'Discovered an awesome comic: "Amazing Comic"'
      }
    });

    // Should show success message
    expect(screen.getByText('Copied!')).toBeInTheDocument();
  });

  it('generates correct message for achievement share type', () => {
    render(
      <SocialShareButtons 
        {...defaultProps} 
        shareType="achievement"
      />
    );
    
    fireEvent.click(screen.getByText('Share'));
    
    // The message should be visible in the preview
    expect(screen.getByText('Just finished reading "Amazing Comic"! 🎉')).toBeInTheDocument();
  });

  it('generates correct message for recommendation share type', () => {
    render(
      <SocialShareButtons 
        {...defaultProps} 
        shareType="recommendation"
      />
    );
    
    fireEvent.click(screen.getByText('Share'));
    
    expect(screen.getByText('Check out this amazing comic: "Amazing Comic" 📚')).toBeInTheDocument();
  });

  it('generates correct message for review share type', () => {
    render(
      <SocialShareButtons 
        {...defaultProps} 
        shareType="review"
      />
    );
    
    fireEvent.click(screen.getByText('Share'));
    
    expect(screen.getByText('Just reviewed "Amazing Comic" - what did you think?')).toBeInTheDocument();
  });

  it('displays comic preview when cover is provided', () => {
    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    
    const coverImage = screen.getByAltText('Amazing Comic');
    expect(coverImage).toBeInTheDocument();
    expect(coverImage).toHaveAttribute('src', '/cover.jpg');
  });

  it('closes share menu when backdrop is clicked', () => {
    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    expect(screen.getByText('Share this comic')).toBeInTheDocument();
    
    // Click backdrop
    const backdrop = document.querySelector('.fixed.inset-0');
    fireEvent.click(backdrop!);
    
    expect(screen.queryByText('Share this comic')).not.toBeInTheDocument();
  });

  it('handles share tracking failure gracefully', async () => {
    const { router } = await import('@inertiajs/react');
    const mockRouter = vi.mocked(router);
    mockRouter.post.mockRejectedValue(new Error('Network error'));
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    fireEvent.click(screen.getByText('Share on Facebook'));
    
    await waitFor(() => {
      expect(consoleSpy).toHaveBeenCalledWith('Failed to track share:', expect.any(Error));
    });

    // Should still open the share URL
    expect(mockWindowOpen).toHaveBeenCalled();
    
    consoleSpy.mockRestore();
  });

  it('handles clipboard failure gracefully', async () => {
    mockClipboard.writeText.mockRejectedValue(new Error('Clipboard error'));
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    render(<SocialShareButtons {...defaultProps} />);
    
    fireEvent.click(screen.getByText('Share'));
    fireEvent.click(screen.getByText('Copy Link'));
    
    await waitFor(() => {
      expect(consoleSpy).toHaveBeenCalledWith('Failed to copy link:', expect.any(Error));
    });
    
    consoleSpy.mockRestore();
  });

  it('applies custom className', () => {
    const { container } = render(
      <SocialShareButtons {...defaultProps} className="custom-class" />
    );
    
    expect(container.firstChild).toHaveClass('custom-class');
  });
});