/**
 * BagComics API Service
 * Connects the new frontend to Laravel backend
 */

const API_BASE = '/api/v2';

// Token storage
const TOKEN_KEY = 'bag_comics_token';

const getToken = (): string | null => {
  return localStorage.getItem(TOKEN_KEY);
};

const setToken = (token: string): void => {
  localStorage.setItem(TOKEN_KEY, token);
};

const removeToken = (): void => {
  localStorage.removeItem(TOKEN_KEY);
};

// Fetch wrapper with auth
const fetchWithAuth = async (url: string, options: RequestInit = {}): Promise<Response> => {
  const token = getToken();
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(options.headers || {}),
  };

  if (token) {
    (headers as Record<string, string>)['Authorization'] = `Bearer ${token}`;
  }

  return fetch(url, {
    ...options,
    headers,
  });
};

// API Response types
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
  likesCount: number;
  commentsCount?: number;
  isLiked: boolean;
  isBookmarked: boolean;
  isFree: boolean;
  price?: number;
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
  isSpoiler: boolean;
}

export interface User {
  id: string;
  name: string;
  email: string;
  avatar: string | null;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface ApiResponse<T> {
  data: T;
  meta?: {
    current_page: number;
    total: number;
    per_page?: number;
    last_page?: number;
  };
}

// API methods
export const api = {
  // ============================================
  // Comics
  // ============================================

  /**
   * Get list of comics with optional filters
   */
  async getComics(params?: {
    genre?: string;
    is_free?: boolean;
    search?: string;
    sort?: 'created_at' | 'rating' | 'popular' | 'title';
    limit?: number;
    page?: number;
  }): Promise<ApiResponse<Comic[]>> {
    const searchParams = new URLSearchParams();
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined) {
          searchParams.append(key, String(value));
        }
      });
    }
    const response = await fetchWithAuth(`${API_BASE}/comics?${searchParams}`);
    return response.json();
  },

  /**
   * Get featured/trending comics
   */
  async getFeatured(): Promise<ApiResponse<Comic[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/featured`);
    return response.json();
  },

  /**
   * Get recently added comics
   */
  async getRecent(): Promise<ApiResponse<Comic[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/recent`);
    return response.json();
  },

  /**
   * Get single comic with pages
   */
  async getComic(slug: string): Promise<ApiResponse<Comic>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}`);
    return response.json();
  },

  /**
   * Get comic pages only
   */
  async getPages(slug: string): Promise<ApiResponse<string[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/pages`);
    return response.json();
  },

  /**
   * Get all genres
   */
  async getGenres(): Promise<ApiResponse<string[]>> {
    const response = await fetchWithAuth(`${API_BASE}/genres`);
    return response.json();
  },

  // ============================================
  // Authentication
  // ============================================

  /**
   * Login user
   */
  async login(email: string, password: string): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Login failed');
    }

    const data = await response.json();
    setToken(data.token);
    return data;
  },

  /**
   * Register new user
   */
  async register(name: string, email: string, password: string, password_confirmation: string): Promise<AuthResponse> {
    const response = await fetch(`${API_BASE}/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ name, email, password, password_confirmation }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Registration failed');
    }

    const data = await response.json();
    setToken(data.token);
    return data;
  },

  /**
   * Logout user
   */
  async logout(): Promise<void> {
    try {
      await fetchWithAuth(`${API_BASE}/auth/logout`, { method: 'POST' });
    } finally {
      removeToken();
    }
  },

  /**
   * Get current user
   */
  async getUser(): Promise<{ user: User } | null> {
    const token = getToken();
    if (!token) return null;

    try {
      const response = await fetchWithAuth(`${API_BASE}/auth/user`);
      if (!response.ok) {
        removeToken();
        return null;
      }
      return response.json();
    } catch {
      removeToken();
      return null;
    }
  },

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return !!getToken();
  },

  // ============================================
  // Library (Bookmarks)
  // ============================================

  /**
   * Get user's library
   */
  async getLibrary(): Promise<ApiResponse<Comic[]>> {
    const response = await fetchWithAuth(`${API_BASE}/library`);
    return response.json();
  },

  /**
   * Add comic to library
   */
  async addToLibrary(slug: string): Promise<{ isBookmarked: boolean }> {
    const response = await fetchWithAuth(`${API_BASE}/library/${slug}`, {
      method: 'POST',
    });
    return response.json();
  },

  /**
   * Remove comic from library
   */
  async removeFromLibrary(slug: string): Promise<{ isBookmarked: boolean }> {
    const response = await fetchWithAuth(`${API_BASE}/library/${slug}`, {
      method: 'DELETE',
    });
    return response.json();
  },

  /**
   * Update reading progress
   */
  async updateProgress(slug: string, currentPage: number, totalPages?: number): Promise<{
    currentPage: number;
    totalPages: number;
    percentage: number;
  }> {
    const response = await fetchWithAuth(`${API_BASE}/library/${slug}/progress`, {
      method: 'PATCH',
      body: JSON.stringify({ current_page: currentPage, total_pages: totalPages }),
    });
    return response.json();
  },

  // ============================================
  // Engagement (Likes, Ratings, Comments)
  // ============================================

  /**
   * Toggle like on a comic
   */
  async toggleLike(slug: string): Promise<{ isLiked: boolean; likesCount: number }> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/like`, {
      method: 'POST',
    });
    return response.json();
  },

  /**
   * Rate a comic
   */
  async rateComic(slug: string, rating: number): Promise<{ rating: number; averageRating: number }> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/rate`, {
      method: 'POST',
      body: JSON.stringify({ rating }),
    });
    return response.json();
  },

  /**
   * Get comments for a comic
   */
  async getComments(slug: string): Promise<ApiResponse<Comment[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/comments`);
    return response.json();
  },

  /**
   * Add a comment
   */
  async addComment(slug: string, content: string, isSpoiler = false): Promise<ApiResponse<Comment>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content, is_spoiler: isSpoiler }),
    });
    return response.json();
  },
};

export default api;
