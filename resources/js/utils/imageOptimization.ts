interface ImageOptimizationOptions {
    quality?: number;
    format?: 'webp' | 'avif' | 'jpeg' | 'png';
    width?: number;
    height?: number;
    placeholder?: boolean;
}

export class ImageOptimizer {
    private static instance: ImageOptimizer;
    private canvas: HTMLCanvasElement;
    private ctx: CanvasRenderingContext2D;

    static getInstance(): ImageOptimizer {
        if (!ImageOptimizer.instance) {
            ImageOptimizer.instance = new ImageOptimizer();
        }
        return ImageOptimizer.instance;
    }

    constructor() {
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d')!;
    }

    async generateBlurDataURL(src: string, blur: number = 10): Promise<string> {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            img.onload = () => {
                // Create small canvas for blur effect
                const smallCanvas = document.createElement('canvas');
                const smallCtx = smallCanvas.getContext('2d')!;
                
                // Reduce size for performance
                const smallWidth = 40;
                const smallHeight = (img.height / img.width) * smallWidth;
                
                smallCanvas.width = smallWidth;
                smallCanvas.height = smallHeight;
                
                // Apply blur filter
                smallCtx.filter = `blur(${blur}px)`;
                smallCtx.drawImage(img, 0, 0, smallWidth, smallHeight);
                
                resolve(smallCanvas.toDataURL('image/jpeg', 0.4));
            };
            
            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = src;
        });
    }

    async compressImage(file: File, options: ImageOptimizationOptions = {}): Promise<Blob> {
        const {
            quality = 0.8,
            format = 'webp',
            width,
            height,
        } = options;

        return new Promise((resolve, reject) => {
            const img = new Image();
            
            img.onload = () => {
                const targetWidth = width || img.width;
                const targetHeight = height || img.height;
                
                this.canvas.width = targetWidth;
                this.canvas.height = targetHeight;
                
                this.ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                
                this.canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Canvas to Blob conversion failed'));
                        }
                    },
                    `image/${format}`,
                    quality
                );
            };
            
            img.onerror = () => reject(new Error('Failed to load image'));
            img.src = URL.createObjectURL(file);
        });
    }

    getOptimizedImageUrl(url: string, options: ImageOptimizationOptions = {}): string {
        const { width, height, quality = 80, format = 'webp' } = options;
        
        // If it's a local image, return as-is
        if (url.startsWith('/') || url.startsWith('./')) {
            return url;
        }
        
        // For external images, you could implement a proxy service
        // For now, return original URL
        return url;
    }

    generateSrcSet(baseUrl: string, widths: number[]): string {
        return widths
            .map(width => `${this.getOptimizedImageUrl(baseUrl, { width })} ${width}w`)
            .join(', ');
    }

    generateSizes(breakpoints: { size: string; width: string }[]): string {
        return breakpoints
            .map(bp => `(max-width: ${bp.size}) ${bp.width}`)
            .join(', ');
    }

    async preloadImage(src: string): Promise<HTMLImageElement> {
        return new Promise((resolve, reject) => {
            const img = new Image();
            
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error(`Failed to preload image: ${src}`));
            
            img.src = src;
        });
    }

    async preloadImages(urls: string[]): Promise<HTMLImageElement[]> {
        return Promise.all(urls.map(url => this.preloadImage(url)));
    }

    isWebPSupported(): boolean {
        const canvas = document.createElement('canvas');
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    isAVIFSupported(): boolean {
        const canvas = document.createElement('canvas');
        return canvas.toDataURL('image/avif').indexOf('data:image/avif') === 0;
    }

    getBestFormat(): 'avif' | 'webp' | 'jpeg' {
        if (this.isAVIFSupported()) return 'avif';
        if (this.isWebPSupported()) return 'webp';
        return 'jpeg';
    }
}

export const imageOptimizer = ImageOptimizer.getInstance();

export default imageOptimizer;