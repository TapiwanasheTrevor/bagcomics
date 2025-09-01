import React, { useState, useEffect, useMemo } from 'react';
import { imageOptimizer } from '@/utils/imageOptimization';

interface OptimizedImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src: string;
    alt: string;
    width?: number;
    height?: number;
    quality?: number;
    format?: 'webp' | 'avif' | 'jpeg' | 'png';
    responsive?: boolean;
    breakpoints?: number[];
    priority?: boolean;
    blurDataURL?: string;
    generateBlur?: boolean;
    className?: string;
}

export default function OptimizedImage({
    src,
    alt,
    width,
    height,
    quality = 80,
    format,
    responsive = false,
    breakpoints = [640, 768, 1024, 1280],
    priority = false,
    blurDataURL,
    generateBlur = false,
    className = '',
    ...props
}: OptimizedImageProps) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [generatedBlur, setGeneratedBlur] = useState<string>('');
    const [error, setError] = useState(false);

    // Determine best format based on browser support
    const optimizedFormat = useMemo(() => {
        if (format) return format;
        return imageOptimizer.getBestFormat();
    }, [format]);

    // Generate optimized URLs
    const optimizedSrc = useMemo(() => {
        return imageOptimizer.getOptimizedImageUrl(src, {
            width,
            height,
            quality,
            format: optimizedFormat
        });
    }, [src, width, height, quality, optimizedFormat]);

    // Generate srcSet for responsive images
    const srcSet = useMemo(() => {
        if (!responsive || !breakpoints.length) return undefined;
        return imageOptimizer.generateSrcSet(src, breakpoints);
    }, [src, responsive, breakpoints]);

    // Generate sizes attribute
    const sizes = useMemo(() => {
        if (!responsive || !breakpoints.length) return undefined;
        
        const sizeBreakpoints = breakpoints.map((width, index) => ({
            size: `${width}px`,
            width: index === breakpoints.length - 1 ? '100vw' : `${width}px`
        }));
        
        return imageOptimizer.generateSizes(sizeBreakpoints);
    }, [responsive, breakpoints]);

    // Generate blur placeholder if requested
    useEffect(() => {
        if (!generateBlur || blurDataURL || generatedBlur) return;

        const generateBlurPlaceholder = async () => {
            try {
                const blur = await imageOptimizer.generateBlurDataURL(src, 10);
                setGeneratedBlur(blur);
            } catch (error) {
                console.warn('Failed to generate blur placeholder:', error);
            }
        };

        generateBlurPlaceholder();
    }, [src, generateBlur, blurDataURL, generatedBlur]);

    // Preload if priority
    useEffect(() => {
        if (priority) {
            imageOptimizer.preloadImage(optimizedSrc).catch(console.warn);
        }
    }, [optimizedSrc, priority]);

    // Handle image load
    const handleLoad = () => {
        setIsLoaded(true);
        setError(false);
    };

    // Handle image error
    const handleError = () => {
        setError(true);
        setIsLoaded(false);
    };

    const placeholderSrc = blurDataURL || generatedBlur;

    return (
        <div className={`relative overflow-hidden ${className}`}>
            {/* Blur placeholder */}
            {placeholderSrc && !isLoaded && (
                <img
                    src={placeholderSrc}
                    alt=""
                    className="absolute inset-0 w-full h-full object-cover blur-sm scale-105 transition-opacity duration-300"
                    aria-hidden="true"
                />
            )}

            {/* Loading shimmer */}
            {!isLoaded && !error && (
                <div className="absolute inset-0 bg-gradient-to-r from-gray-800 via-gray-700 to-gray-800 animate-pulse" />
            )}

            {/* Main image */}
            <img
                src={optimizedSrc}
                srcSet={srcSet}
                sizes={sizes}
                alt={alt}
                width={width}
                height={height}
                loading={priority ? 'eager' : 'lazy'}
                decoding="async"
                onLoad={handleLoad}
                onError={handleError}
                className={`transition-opacity duration-300 ${
                    isLoaded ? 'opacity-100' : 'opacity-0'
                } w-full h-full object-cover`}
                {...props}
            />

            {/* Error state */}
            {error && (
                <div className="absolute inset-0 flex items-center justify-center bg-gray-800 text-gray-400">
                    <div className="text-center">
                        <svg className="w-8 h-8 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clipRule="evenodd" />
                        </svg>
                        <p className="text-xs">Failed to load</p>
                    </div>
                </div>
            )}
        </div>
    );
}

// Specialized component for comic covers
interface OptimizedComicCoverProps extends Omit<OptimizedImageProps, 'src' | 'alt'> {
    comic: {
        title: string;
        cover_image_url?: string;
        slug?: string;
    };
    size?: 'small' | 'medium' | 'large';
}

export function OptimizedComicCover({
    comic,
    size = 'medium',
    className = '',
    ...props
}: OptimizedComicCoverProps) {
    const sizeConfig = {
        small: { width: 120, height: 180 },
        medium: { width: 200, height: 300 },
        large: { width: 300, height: 450 }
    };

    const { width, height } = sizeConfig[size];

    return (
        <OptimizedImage
            src={comic.cover_image_url || '/images/default-comic-cover.svg'}
            alt={`${comic.title} cover`}
            width={width}
            height={height}
            responsive={true}
            generateBlur={true}
            quality={85}
            className={`rounded-lg ${className}`}
            breakpoints={[width, width * 1.5, width * 2]}
            {...props}
        />
    );
}