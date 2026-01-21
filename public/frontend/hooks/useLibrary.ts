import { useState, useEffect, useCallback } from 'react';
import { api, Comic } from '../services/api';

interface UseLibraryResult {
  library: Comic[];
  loading: boolean;
  error: string | null;
  isInLibrary: (comicId: string) => boolean;
  addToLibrary: (slug: string) => Promise<void>;
  removeFromLibrary: (slug: string) => Promise<void>;
  toggleBookmark: (comic: Comic) => Promise<void>;
  refetch: () => Promise<void>;
}

export const useLibrary = (): UseLibraryResult => {
  const [library, setLibrary] = useState<Comic[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLibrary = useCallback(async () => {
    if (!api.isAuthenticated()) {
      setLibrary([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await api.getLibrary();
      setLibrary(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch library');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchLibrary();
  }, [fetchLibrary]);

  const isInLibrary = useCallback((comicId: string): boolean => {
    return library.some(comic => comic.id === comicId);
  }, [library]);

  const addToLibrary = useCallback(async (slug: string) => {
    try {
      await api.addToLibrary(slug);
      await fetchLibrary();
    } catch (err) {
      throw err;
    }
  }, [fetchLibrary]);

  const removeFromLibrary = useCallback(async (slug: string) => {
    try {
      await api.removeFromLibrary(slug);
      await fetchLibrary();
    } catch (err) {
      throw err;
    }
  }, [fetchLibrary]);

  const toggleBookmark = useCallback(async (comic: Comic) => {
    if (isInLibrary(comic.id)) {
      await removeFromLibrary(comic.slug);
    } else {
      await addToLibrary(comic.slug);
    }
  }, [isInLibrary, addToLibrary, removeFromLibrary]);

  return {
    library,
    loading,
    error,
    isInLibrary,
    addToLibrary,
    removeFromLibrary,
    toggleBookmark,
    refetch: fetchLibrary,
  };
};

export default useLibrary;
