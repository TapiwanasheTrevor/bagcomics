
export interface Comic {
  id: string;
  slug: string;
  title: string;
  author: string;
  description: string;
  coverImage: string;
  genre: string[];
  rating: number;
  totalChapters: number;
  episodes: number;
  pages?: string[];
  // Engagement
  likesCount?: number;
  commentsCount?: number;
  isLiked?: boolean;
  isBookmarked?: boolean;
  // Pricing
  isFree?: boolean;
  price?: number;
  // Progress
  userProgress?: {
    currentPage: number;
    totalPages: number;
    percentage: number;
  };
}

export interface Comment {
  id: string;
  user: string;
  text: string;
  date: string;
  isSpoiler?: boolean;
}

export interface User {
  id: string;
  name: string;
  email: string;
  avatar: string | null;
}

export enum ViewMode {
  HOME = 'home',
  READER = 'reader',
  LIBRARY = 'library',
  STORE = 'store',
  EXPLORE = 'explore',
  BLOG = 'blog'
}
