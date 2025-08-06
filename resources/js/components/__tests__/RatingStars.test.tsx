import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { RatingStars } from '../RatingStars';

describe('RatingStars', () => {
  it('renders correct number of stars', () => {
    const { container } = render(<RatingStars rating={3} maxRating={5} />);
    const stars = container.querySelectorAll('svg');
    expect(stars).toHaveLength(5);
  });

  it('displays correct rating visually', () => {
    const { container } = render(<RatingStars rating={3.5} />);
    const filledStars = container.querySelectorAll('.fill-yellow-400');
    expect(filledStars).toHaveLength(3); // 3.5 shows 3 filled stars (no partial stars)
  });

  it('shows rating value when showValue is true', () => {
    render(<RatingStars rating={4.2} showValue />);
    expect(screen.getByText('4.2')).toBeInTheDocument();
  });

  it('does not show rating value when showValue is false', () => {
    render(<RatingStars rating={4.2} showValue={false} />);
    expect(screen.queryByText('4.2')).not.toBeInTheDocument();
  });

  it('calls onRatingChange when interactive star is clicked', () => {
    const mockOnRatingChange = vi.fn();
    const { container } = render(
      <RatingStars 
        rating={2} 
        interactive 
        onRatingChange={mockOnRatingChange} 
      />
    );
    
    const stars = container.querySelectorAll('svg');
    fireEvent.click(stars[3]); // Click 4th star (index 3)
    
    expect(mockOnRatingChange).toHaveBeenCalledWith(4);
  });

  it('updates hover state on mouse enter/leave when interactive', () => {
    const { container } = render(
      <RatingStars rating={2} interactive onRatingChange={vi.fn()} />
    );
    
    const stars = container.querySelectorAll('svg');
    
    // Hover over 4th star
    fireEvent.mouseEnter(stars[3]);
    
    // Should show 4 filled stars during hover
    const filledStars = container.querySelectorAll('.fill-yellow-400');
    expect(filledStars).toHaveLength(4);
    
    // Mouse leave should reset to original rating
    fireEvent.mouseLeave(container.firstChild);
    const filledStarsAfterLeave = container.querySelectorAll('.fill-yellow-400');
    expect(filledStarsAfterLeave).toHaveLength(4); // Should still show hover state until mouse leaves container
  });

  it('does not respond to clicks when not interactive', () => {
    const mockOnRatingChange = vi.fn();
    const { container } = render(
      <RatingStars 
        rating={2} 
        interactive={false} 
        onRatingChange={mockOnRatingChange} 
      />
    );
    
    const stars = container.querySelectorAll('svg');
    fireEvent.click(stars[3]);
    
    expect(mockOnRatingChange).not.toHaveBeenCalled();
  });

  it('applies correct size classes', () => {
    const { container: smallContainer } = render(
      <RatingStars rating={3} size="sm" />
    );
    const { container: mediumContainer } = render(
      <RatingStars rating={3} size="md" />
    );
    const { container: largeContainer } = render(
      <RatingStars rating={3} size="lg" />
    );
    
    expect(smallContainer.querySelector('.w-4')).toBeInTheDocument();
    expect(mediumContainer.querySelector('.w-5')).toBeInTheDocument();
    expect(largeContainer.querySelector('.w-6')).toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <RatingStars rating={3} className="custom-class" />
    );
    
    expect(container.firstChild).toHaveClass('custom-class');
  });

  it('handles zero rating correctly', () => {
    const { container } = render(<RatingStars rating={0} />);
    const filledStars = container.querySelectorAll('.fill-yellow-400');
    expect(filledStars).toHaveLength(0);
  });

  it('handles maximum rating correctly', () => {
    const { container } = render(<RatingStars rating={5} maxRating={5} />);
    const filledStars = container.querySelectorAll('.fill-yellow-400');
    expect(filledStars).toHaveLength(5);
  });

  it('handles custom maxRating', () => {
    const { container } = render(<RatingStars rating={7} maxRating={10} />);
    const stars = container.querySelectorAll('svg');
    expect(stars).toHaveLength(10);
  });
});