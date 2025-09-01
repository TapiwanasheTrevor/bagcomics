import { useEffect, useCallback, useRef } from 'react';

interface PreloadOptions {
    priority?: 'high' | 'medium' | 'low';
    timeout?: number;
    retries?: number;
}

interface PreloadItem {
    url: string;
    type: 'image' | 'page' | 'api';
    options?: PreloadOptions;
}

class PreloadManager {
    private static instance: PreloadManager;
    private preloadQueue: PreloadItem[] = [];
    private preloadCache = new Map<string, any>();
    private loadingPromises = new Map<string, Promise<any>>();
    private observer: IntersectionObserver | null = null;

    static getInstance(): PreloadManager {
        if (!PreloadManager.instance) {
            PreloadManager.instance = new PreloadManager();
        }
        return PreloadManager.instance;
    }

    constructor() {
        this.setupIntersectionObserver();
        this.setupIdleCallback();
    }

    private setupIntersectionObserver() {
        if (typeof window !== 'undefined' && 'IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const element = entry.target as HTMLElement;
                            const preloadData = element.dataset.preload;
                            if (preloadData) {
                                const items: PreloadItem[] = JSON.parse(preloadData);
                                items.forEach(item => this.preload(item.url, item.type, item.options));
                                this.observer?.unobserve(element);
                            }
                        }
                    });
                },
                {
                    rootMargin: '200px 0px',
                    threshold: 0.1
                }
            );
        }
    }

    private setupIdleCallback() {
        if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
            const processQueue = () => {
                if (this.preloadQueue.length > 0) {
                    window.requestIdleCallback(() => {
                        const item = this.preloadQueue.shift();
                        if (item) {
                            this.executePreload(item);
                        }
                        if (this.preloadQueue.length > 0) {
                            processQueue();
                        }
                    });
                }
            };

            setInterval(() => {
                if (this.preloadQueue.length > 0) {
                    processQueue();
                }
            }, 100);
        }
    }

    preload(url: string, type: 'image' | 'page' | 'api', options: PreloadOptions = {}): Promise<any> {
        if (this.preloadCache.has(url)) {
            return Promise.resolve(this.preloadCache.get(url));
        }

        if (this.loadingPromises.has(url)) {
            return this.loadingPromises.get(url)!;
        }

        const item: PreloadItem = { url, type, options };

        if (options.priority === 'high') {
            return this.executePreload(item);
        } else {
            this.preloadQueue.push(item);
            const promise = new Promise((resolve) => {
                const checkCache = () => {
                    if (this.preloadCache.has(url)) {
                        resolve(this.preloadCache.get(url));
                    } else {
                        setTimeout(checkCache, 50);
                    }
                };
                checkCache();
            });
            this.loadingPromises.set(url, promise);
            return promise;
        }
    }

    private async executePreload(item: PreloadItem): Promise<any> {
        const { url, type, options = {} } = item;
        const { timeout = 10000, retries = 2 } = options;

        try {
            let result: any;

            switch (type) {
                case 'image':
                    result = await this.preloadImage(url, timeout);
                    break;
                case 'page':
                    result = await this.preloadPage(url, timeout);
                    break;
                case 'api':
                    result = await this.preloadApi(url, timeout);
                    break;
                default:
                    throw new Error(`Unknown preload type: ${type}`);
            }

            this.preloadCache.set(url, result);
            this.loadingPromises.delete(url);
            return result;

        } catch (error) {
            if (retries > 0) {
                return this.executePreload({
                    ...item,
                    options: { ...options, retries: retries - 1 }
                });
            }
            this.loadingPromises.delete(url);
            throw error;
        }
    }

    private preloadImage(url: string, timeout: number): Promise<HTMLImageElement> {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const timer = setTimeout(() => {
                reject(new Error(`Image preload timeout: ${url}`));
            }, timeout);

            img.onload = () => {
                clearTimeout(timer);
                resolve(img);
            };

            img.onerror = () => {
                clearTimeout(timer);
                reject(new Error(`Image preload failed: ${url}`));
            };

            img.src = url;
        });
    }

    private async preloadPage(url: string, timeout: number): Promise<string> {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                credentials: 'include'
            });
            clearTimeout(timer);
            
            if (!response.ok) {
                throw new Error(`Page preload failed: ${response.status}`);
            }

            return await response.text();
        } catch (error) {
            clearTimeout(timer);
            throw error;
        }
    }

    private async preloadApi(url: string, timeout: number): Promise<any> {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            clearTimeout(timer);
            
            if (!response.ok) {
                throw new Error(`API preload failed: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            clearTimeout(timer);
            throw error;
        }
    }

    observeElement(element: HTMLElement, preloadItems: PreloadItem[]) {
        if (this.observer) {
            element.dataset.preload = JSON.stringify(preloadItems);
            this.observer.observe(element);
        }
    }

    getFromCache(url: string) {
        return this.preloadCache.get(url);
    }

    clearCache() {
        this.preloadCache.clear();
        this.loadingPromises.clear();
    }

    getCacheSize() {
        return this.preloadCache.size;
    }
}

export function usePreloader() {
    const manager = useRef(PreloadManager.getInstance());

    const preloadImages = useCallback((urls: string[], options: PreloadOptions = {}) => {
        return Promise.allSettled(
            urls.map(url => manager.current.preload(url, 'image', options))
        );
    }, []);

    const preloadPages = useCallback((urls: string[], options: PreloadOptions = {}) => {
        return Promise.allSettled(
            urls.map(url => manager.current.preload(url, 'page', options))
        );
    }, []);

    const preloadApi = useCallback((urls: string[], options: PreloadOptions = {}) => {
        return Promise.allSettled(
            urls.map(url => manager.current.preload(url, 'api', options))
        );
    }, []);

    const preloadComic = useCallback((comicSlug: string, priority: 'high' | 'medium' | 'low' = 'medium') => {
        const baseUrl = `/comics/${comicSlug}`;
        const apiUrl = `/api/comics/${comicSlug}`;
        
        return Promise.allSettled([
            manager.current.preload(baseUrl, 'page', { priority }),
            manager.current.preload(apiUrl, 'api', { priority })
        ]);
    }, []);

    const preloadComicImages = useCallback((comics: Array<{ cover_image_url?: string; slug: string }>) => {
        const imageUrls = comics
            .map(comic => comic.cover_image_url)
            .filter(Boolean) as string[];
        
        return preloadImages(imageUrls, { priority: 'low' });
    }, [preloadImages]);

    const observeForPreload = useCallback((element: HTMLElement, items: PreloadItem[]) => {
        manager.current.observeElement(element, items);
    }, []);

    const getFromCache = useCallback((url: string) => {
        return manager.current.getFromCache(url);
    }, []);

    const clearCache = useCallback(() => {
        manager.current.clearCache();
    }, []);

    const getCacheInfo = useCallback(() => {
        return {
            size: manager.current.getCacheSize(),
            memoryUsage: typeof (performance as any).memory !== 'undefined' 
                ? (performance as any).memory 
                : null
        };
    }, []);

    return {
        preloadImages,
        preloadPages,
        preloadApi,
        preloadComic,
        preloadComicImages,
        observeForPreload,
        getFromCache,
        clearCache,
        getCacheInfo
    };
}

export default usePreloader;