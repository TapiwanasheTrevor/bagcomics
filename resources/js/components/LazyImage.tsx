import React, { useState, useRef, useEffect } from 'react';
import { ImageIcon } from 'lucide-react';

interface LazyImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src: string;
    alt: string;
    fallback?: string;
    placeholder?: React.ReactNode;
    className?: string;
    containerClassName?: string;
    blurDataURL?: string;
    priority?: boolean;
    onLoad?: () => void;
    onError?: () => void;
    threshold?: number;
    rootMargin?: string;
}

export default function LazyImage({
    src,
    alt,
    fallback = '/images/default-comic-cover.svg',
    placeholder,
    className = '',
    containerClassName = '',
    blurDataURL,
    priority = false,
    onLoad,
    onError,
    threshold = 0.1,
    rootMargin = '50px',
    ...props
}: LazyImageProps) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [isInView, setIsInView] = useState(priority);
    const [hasError, setHasError] = useState(false);
    const [currentSrc, setCurrentSrc] = useState(priority ? src : '');
    const imgRef = useRef<HTMLImageElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    // Intersection Observer for lazy loading
    useEffect(() => {
        if (priority || isInView) return;

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setIsInView(true);
                        setCurrentSrc(src);
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold,
                rootMargin
            }
        );

        const container = containerRef.current;
        if (container) {
            observer.observe(container);
        }

        return () => {
            if (container) {
                observer.unobserve(container);
            }
        };
    }, [src, priority, isInView, threshold, rootMargin]);

    // Preload image when in view
    useEffect(() => {
        if (!currentSrc || isLoaded) return;

        const img = new Image();
        img.onload = () => {
            setIsLoaded(true);
            onLoad?.();
        };
        
        img.onerror = () => {
            setHasError(true);
            if (fallback && fallback !== currentSrc) {
                setCurrentSrc(fallback);
                setHasError(false);
                return;
            }
            onError?.();
        };

        img.src = currentSrc;
    }, [currentSrc, fallback, isLoaded, onLoad, onError]);

    const defaultPlaceholder = (
        <div className="flex items-center justify-center w-full h-full bg-gray-800">
            <ImageIcon className="w-8 h-8 text-gray-400" />
        </div>
    );

    const blurPlaceholder = blurDataURL ? (
        <img
            src={blurDataURL}
            alt=""
            className="absolute inset-0 w-full h-full object-cover blur-sm scale-105"
            aria-hidden="true"
        />
    ) : null;

    return (
        <div 
            ref={containerRef}
            className={`relative overflow-hidden ${containerClassName}`}
        >
            {/* Blur placeholder */}
            {blurPlaceholder && !isLoaded && (
                <div className="absolute inset-0">
                    {blurPlaceholder}
                </div>
            )}

            {/* Loading placeholder */}
            {!isLoaded && !blurDataURL && (
                <div className="absolute inset-0">
                    {placeholder || defaultPlaceholder}
                </div>
            )}

            {/* Shimmer effect */}
            {!isLoaded && (
                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent animate-pulse">
                    <div className="h-full w-full bg-gradient-to-r from-transparent via-white/5 to-transparent animate-shimmer" />
                </div>
            )}

            {/* Actual image */}
            {currentSrc && (
                <img
                    ref={imgRef}
                    src={currentSrc}
                    alt={alt}
                    className={`transition-opacity duration-300 ${
                        isLoaded ? 'opacity-100' : 'opacity-0'
                    } ${className}`}
                    onLoad={() => {
                        setIsLoaded(true);
                        onLoad?.();
                    }}
                    onError={() => {
                        if (!hasError && fallback && fallback !== currentSrc) {
                            setHasError(true);
                            setCurrentSrc(fallback);
                        } else {
                            onError?.();
                        }
                    }}
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                    {...props}
                />
            )}

            {/* Error state */}
            {hasError && !isLoaded && (
                <div className="absolute inset-0 flex items-center justify-center bg-gray-800 text-gray-400">
                    <div className="text-center">
                        <ImageIcon className="w-8 h-8 mx-auto mb-2" />
                        <p className="text-xs">Failed to load</p>
                    </div>
                </div>
            )}

            {/* Loading indicator */}
            {isInView && !isLoaded && !hasError && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="w-6 h-6 border-2 border-gray-600 border-t-white rounded-full animate-spin" />
                </div>
            )}
        </div>
    );
}

// Utility component for comic cover images
export function LazyComicCover({
    comic,
    size = 'medium',
    priority = false,
    className = '',
    ...props
}: {
    comic: {
        title: string;
        cover_image_url?: string;
        slug?: string;
    };
    size?: 'small' | 'medium' | 'large';
    priority?: boolean;
    className?: string;
} & Omit<LazyImageProps, 'src' | 'alt'>) {
    const sizeClasses = {
        small: 'w-16 h-24',
        medium: 'w-32 h-48',
        large: 'w-48 h-72'
    };

    const aspectRatio = 'aspect-[2/3]';

    return (
        <LazyImage
            src={comic.cover_image_url || '/images/default-comic-cover.svg'}
            alt={`${comic.title} cover`}
            fallback="/images/default-comic-cover.svg"
            priority={priority}
            className={`object-cover ${className}`}
            containerClassName={`${aspectRatio} ${sizeClasses[size]} rounded-lg overflow-hidden`}
            placeholder={
                <div className="flex items-center justify-center w-full h-full bg-gray-800 rounded-lg">
                    <div className="text-center">
                        <ImageIcon className="w-6 h-6 mx-auto text-gray-400 mb-1" />
                        <p className="text-xs text-gray-500 px-2">{comic.title}</p>
                    </div>
                </div>
            }
            {...props}
        />
    );
}