import { useState, useEffect, useCallback } from 'react';
import { api, Comic } from '../services/api';

interface UseComicsOptions {
  genre?: string;
  is_free?: boolean;
  search?: string;
  sort?: 'created_at' | 'rating' | 'popular' | 'title';
  limit?: number;
}

interface UseComicsResult {
  comics: Comic[];
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

export const useComics = (options?: UseComicsOptions): UseComicsResult => {
  const [comics, setComics] = useState<Comic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchComics = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.getComics(options);
      setComics(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch comics');
    } finally {
      setLoading(false);
    }
  }, [options?.genre, options?.is_free, options?.search, options?.sort, options?.limit]);

  useEffect(() => {
    fetchComics();
  }, [fetchComics]);

  return { comics, loading, error, refetch: fetchComics };
};

export const useRecentComics = (): UseComicsResult => {
  const [comics, setComics] = useState<Comic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchComics = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.getRecent();
      setComics(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch comics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchComics();
  }, [fetchComics]);

  return { comics, loading, error, refetch: fetchComics };
};

export const useFeaturedComics = (): UseComicsResult => {
  const [comics, setComics] = useState<Comic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchComics = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.getFeatured();
      setComics(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch comics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchComics();
  }, [fetchComics]);

  return { comics, loading, error, refetch: fetchComics };
};

interface UseComicResult {
  comic: Comic | null;
  loading: boolean;
  error: string | null;
  refetch: () => Promise<void>;
}

export const useComic = (slug: string): UseComicResult => {
  const [comic, setComic] = useState<Comic | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchComic = useCallback(async () => {
    if (!slug) return;
    setLoading(true);
    setError(null);
    try {
      const response = await api.getComic(slug);
      setComic(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch comic');
    } finally {
      setLoading(false);
    }
  }, [slug]);

  useEffect(() => {
    fetchComic();
  }, [fetchComic]);

  return { comic, loading, error, refetch: fetchComic };
};

export default useComics;
