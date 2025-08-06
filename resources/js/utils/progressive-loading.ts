// Progressive loading utilities for better mobile performance

export interface ProgressiveImageOptions {
    lowQualitySrc?: string;
    highQualitySrc: string;
    placeholder?: string;
    onLoad?: () => void;
    onError?: (error: Error) => void;
}

export class ProgressiveImageLoader {
    private static cache = new Map<string, HTMLImageElement>();
    
    static async loadImage(options: ProgressiveImageOptions): Promise<HTMLImageElement> {
        const { lowQualitySrc, highQualitySrc, placeholder, onLoad, onError } = options;
        
        // Check cache first
        if (this.cache.has(highQualitySrc)) {
            const cachedImage = this.cache.get(highQualitySrc)!;
            onLoad?.();
            return cachedImage;
        }
        
        return new Promise((resolve, reject) => {
            const img = new Image();
            
            // Load low quality first if available
            if (lowQualitySrc) {
                const lowQualityImg = new Image();
                lowQualityImg.onload = () => {
                    // Low quality loaded, now load high quality
                    img.onload = () => {
                        this.cache.set(highQualitySrc, img);
                        onLoad?.();
                        resolve(img);
                    };
                    
                    img.onerror = (error) => {
                        const err = new Error('Failed to load high quality image');
                        onError?.(err);
                        reject(err);
                    };
                    
                    img.src = highQualitySrc;
                };
                
                lowQualityImg.onerror = () => {
                    // Fallback to high quality directly
                    img.onload = () => {
                        this.cache.set(highQualitySrc, img);
                        onLoad?.();
                        resolve(img);
                    };
                    
                    img.onerror = (error) => {
                        const err = new Error('Failed to load image');
                        onError?.(err);
                        reject(err);
                    };
                    
                    img.src = highQualitySrc;
                };
                
                lowQualityImg.src = lowQualitySrc;
            } else {
                // Load high quality directly
                img.onload = () => {
                    this.cache.set(highQualitySrc, img);
                    onLoad?.();
                    resolve(img);
                };
                
                img.onerror = (error) => {
                    const err = new Error('Failed to load image');
                    onError?.(err);
                    reject(err);
                };
                
                img.src = highQualitySrc;
            }
        });
    }
    
    static preloadImages(urls: string[]): Promise<HTMLImageElement[]> {
        return Promise.all(
            urls.map(url => this.loadImage({ highQualitySrc: url }))
        );
    }
    
    static clearCache(): void {
        this.cache.clear();
    }
}

// Intersection Observer for lazy loading
export class LazyLoader {
    private static observer: IntersectionObserver | null = null;
    private static callbacks = new Map<Element, () => void>();
    
    static observe(element: Element, callback: () => void, options?: IntersectionObserverInit): void {
        if (!this.observer) {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const callback = this.callbacks.get(entry.target);
                        if (callback) {
                            callback();
                            this.unobserve(entry.target);
                        }
                    }
                });
            }, {
                rootMargin: '50px',
                threshold: 0.1,
                ...options
            });
        }
        
        this.callbacks.set(element, callback);
        this.observer.observe(element);
    }
    
    static unobserve(element: Element): void {
        if (this.observer) {
            this.observer.unobserve(element);
            this.callbacks.delete(element);
        }
    }
    
    static disconnect(): void {
        if (this.observer) {
            this.observer.disconnect();
            this.callbacks.clear();
            this.observer = null;
        }
    }
}

// Progressive content loading for comics
export class ComicContentLoader {
    private static loadingQueue: Array<() => Promise<void>> = [];
    private static isProcessing = false;
    
    static async queueLoad(loadFunction: () => Promise<void>): Promise<void> {
        return new Promise((resolve, reject) => {
            this.loadingQueue.push(async () => {
                try {
                    await loadFunction();
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
            
            this.processQueue();
        });
    }
    
    private static async processQueue(): Promise<void> {
        if (this.isProcessing || this.loadingQueue.length === 0) {
            return;
        }
        
        this.isProcessing = true;
        
        while (this.loadingQueue.length > 0) {
            const loadFunction = this.loadingQueue.shift();
            if (loadFunction) {
                try {
                    await loadFunction();
                } catch (error) {
                    console.error('Failed to load content:', error);
                }
                
                // Small delay to prevent overwhelming the browser
                await new Promise(resolve => setTimeout(resolve, 10));
            }
        }
        
        this.isProcessing = false;
    }
}

// Network-aware loading
export class NetworkAwareLoader {
    private static connection: any = null;
    
    static init(): void {
        // @ts-ignore - NetworkInformation is not in TypeScript types yet
        this.connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    }
    
    static getConnectionType(): 'slow' | 'fast' | 'unknown' {
        if (!this.connection) {
            return 'unknown';
        }
        
        const effectiveType = this.connection.effectiveType;
        
        if (effectiveType === 'slow-2g' || effectiveType === '2g') {
            return 'slow';
        }
        
        if (effectiveType === '3g' || effectiveType === '4g') {
            return 'fast';
        }
        
        return 'unknown';
    }
    
    static shouldLoadHighQuality(): boolean {
        const connectionType = this.getConnectionType();
        
        // Load high quality on fast connections or when unknown
        return connectionType === 'fast' || connectionType === 'unknown';
    }
    
    static getOptimalImageQuality(): 'low' | 'medium' | 'high' {
        const connectionType = this.getConnectionType();
        
        switch (connectionType) {
            case 'slow':
                return 'low';
            case 'fast':
                return 'high';
            default:
                return 'medium';
        }
    }
}

// Initialize network awareness
NetworkAwareLoader.init();

// Utility for responsive image URLs
export function getResponsiveImageUrl(baseUrl: string, quality: 'low' | 'medium' | 'high' = 'medium'): string {
    if (!baseUrl) return '';
    
    const qualityParams = {
        low: 'w=400&q=60',
        medium: 'w=800&q=75',
        high: 'w=1200&q=90'
    };
    
    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}${qualityParams[quality]}`;
}

// Preload critical resources
export async function preloadCriticalResources(): Promise<void> {
    const criticalImages = [
        '/favicon-192.png',
        '/favicon-512.png'
    ];
    
    try {
        await ProgressiveImageLoader.preloadImages(criticalImages);
        console.log('Critical resources preloaded');
    } catch (error) {
        console.warn('Failed to preload some critical resources:', error);
    }
}