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

const extractErrorMessage = (payload: unknown, fallback: string): string => {
  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const data = payload as Record<string, any>;
  return data?.error?.message || data?.message || fallback;
};

const unwrapApiData = <T>(payload: unknown): T => {
  if (payload && typeof payload === 'object') {
    const data = payload as Record<string, any>;
    if (typeof data.success === 'boolean') {
      if (data.success === false) {
        throw new Error(extractErrorMessage(payload, 'Request failed'));
      }
      if ('data' in data) {
        return data.data as T;
      }
    }
  }

  return payload as T;
};

const normalizeApiResponse = <T>(payload: unknown): ApiResponse<T> => {
  if (payload && typeof payload === 'object') {
    const data = payload as Record<string, any>;

    if (typeof data.success === 'boolean' && data.success === true && 'data' in data) {
      return {
        data: data.data as T,
        meta: data.pagination ?? data.meta,
      };
    }

    if ('data' in data) {
      return data as ApiResponse<T>;
    }
  }

  return { data: payload as T };
};

const parseResponsePayload = async (response: Response): Promise<unknown> => {
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(extractErrorMessage(payload, `Request failed (${response.status})`));
  }

  return payload;
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
  hasAccess?: boolean;
  previewOnly?: boolean;
  totalPages?: number;
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
  must_reset_password?: boolean;
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
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comic[]>(payload);
  },

  /**
   * Get featured/trending comics
   */
  async getFeatured(): Promise<ApiResponse<Comic[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/featured`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comic[]>(payload);
  },

  /**
   * Get recently added comics
   */
  async getRecent(): Promise<ApiResponse<Comic[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/recent`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comic[]>(payload);
  },

  /**
   * Get single comic with pages
   */
  async getComic(slug: string): Promise<ApiResponse<Comic>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comic>(payload);
  },

  /**
   * Get comic pages only
   */
  async getPages(slug: string): Promise<ApiResponse<string[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/pages`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<string[]>(payload);
  },

  /**
   * Get all genres
   */
  async getGenres(): Promise<ApiResponse<string[]>> {
    const response = await fetchWithAuth(`${API_BASE}/genres`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<string[]>(payload);
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

    const payload = await parseResponsePayload(response);
    const data = unwrapApiData<AuthResponse>(payload);
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

    const payload = await parseResponsePayload(response);
    const data = unwrapApiData<AuthResponse>(payload);
    setToken(data.token);
    return data;
  },

  /**
   * Request password reset
   */
  async forgotPassword(email: string): Promise<{ message: string }> {
    const response = await fetch(`${API_BASE}/auth/forgot-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ email }),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ message: string }>(payload);
  },

  /**
   * Reset password with token
   */
  async resetPassword(data: { token: string; email: string; password: string; password_confirmation: string }): Promise<{ message: string }> {
    const response = await fetch(`${API_BASE}/auth/reset-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ message: string }>(payload);
  },

  /**
   * Set new password (after admin-issued temporary password)
   */
  async setNewPassword(password: string, password_confirmation: string): Promise<{ message: string }> {
    const response = await fetchWithAuth(`${API_BASE}/auth/set-new-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ password, password_confirmation }),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ message: string }>(payload);
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
      const payload = await parseResponsePayload(response);
      return unwrapApiData<{ user: User }>(payload);
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
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comic[]>(payload);
  },

  /**
   * Add comic to library
   */
  async addToLibrary(slug: string): Promise<{ isBookmarked: boolean }> {
    const response = await fetchWithAuth(`${API_BASE}/library/${slug}`, {
      method: 'POST',
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ isBookmarked: boolean }>(payload);
  },

  /**
   * Remove comic from library
   */
  async removeFromLibrary(slug: string): Promise<{ isBookmarked: boolean }> {
    const response = await fetchWithAuth(`${API_BASE}/library/${slug}`, {
      method: 'DELETE',
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ isBookmarked: boolean }>(payload);
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
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{
      currentPage: number;
      totalPages: number;
      percentage: number;
    }>(payload);
  },

  // ============================================
  // Payments
  // ============================================

  /**
   * Create payment intent for a comic
   */
  async createPaymentIntent(slug: string): Promise<{ payment_intent_id: string; client_secret: string }> {
    const response = await fetchWithAuth(`${API_BASE}/payments/comics/${slug}/intent`, {
      method: 'POST',
      body: JSON.stringify({}),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ payment_intent_id: string; client_secret: string }>(payload);
  },

  /**
   * Confirm payment intent with backend
   */
  async confirmPayment(paymentIntentId: string): Promise<{ payment: { id: string; amount: string; status: string } }> {
    const response = await fetchWithAuth(`${API_BASE}/payments/confirm`, {
      method: 'POST',
      body: JSON.stringify({ payment_intent_id: paymentIntentId }),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ payment: { id: string; amount: string; status: string } }>(payload);
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
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ isLiked: boolean; likesCount: number }>(payload);
  },

  /**
   * Rate a comic
   */
  async rateComic(slug: string, rating: number): Promise<{ rating: number; averageRating: number }> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/rate`, {
      method: 'POST',
      body: JSON.stringify({ rating }),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ rating: number; averageRating: number }>(payload);
  },

  /**
   * Get comments for a comic
   */
  async getComments(slug: string): Promise<ApiResponse<Comment[]>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/comments`);
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comment[]>(payload);
  },

  /**
   * Add a comment
   */
  async addComment(slug: string, content: string, isSpoiler = false): Promise<ApiResponse<Comment>> {
    const response = await fetchWithAuth(`${API_BASE}/comics/${slug}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content, is_spoiler: isSpoiler }),
    });
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<Comment>(payload);
  },

  // ============================================
  // Creator Submissions
  // ============================================

  // ============================================
  // Subscriptions
  // ============================================

  async getSubscriptionPlans(): Promise<ApiResponse<any[]>> {
    const response = await fetch(`${API_BASE}/subscription/plans`, {
      headers: { 'Accept': 'application/json' },
    });
    const payload = await parseResponsePayload(response);
    return normalizeApiResponse<any[]>(payload);
  },

  async getCurrentSubscription(): Promise<any> {
    const response = await fetchWithAuth(`${API_BASE}/subscription/current`);
    const payload = await parseResponsePayload(response);
    return unwrapApiData<any>(payload);
  },

  async createSubscription(plan: string): Promise<{ payment_intent_id: string; client_secret: string }> {
    const response = await fetchWithAuth(`${API_BASE}/subscription/subscribe`, {
      method: 'POST',
      body: JSON.stringify({ plan }),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ payment_intent_id: string; client_secret: string }>(payload);
  },

  async cancelSubscription(): Promise<{ message: string; expiresAt: string }> {
    const response = await fetchWithAuth(`${API_BASE}/subscription/cancel`, {
      method: 'POST',
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ message: string; expiresAt: string }>(payload);
  },

  async submitCreatorApplication(data: {
    name: string;
    email: string;
    portfolio_url?: string;
    comic_title: string;
    genre: string;
    synopsis: string;
    sample_pages_url?: string;
  }): Promise<{ message: string }> {
    const response = await fetch(`${API_BASE}/creator-submissions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data),
    });
    const payload = await parseResponsePayload(response);
    return unwrapApiData<{ message: string }>(payload);
  },
};

export default api;
