import React, { useState, useEffect, useRef, useMemo } from 'react';
import { useIntersectionObserver } from '@/hooks/useIntersectionObserver';

interface VirtualizedListProps<T> {
    items: T[];
    itemHeight: number;
    containerHeight: number;
    renderItem: (item: T, index: number) => React.ReactNode;
    overscan?: number;
    className?: string;
    loadMore?: () => void;
    hasMore?: boolean;
    loading?: boolean;
    onScroll?: (scrollTop: number) => void;
}

export default function VirtualizedList<T>({
    items,
    itemHeight,
    containerHeight,
    renderItem,
    overscan = 5,
    className = '',
    loadMore,
    hasMore = false,
    loading = false,
    onScroll
}: VirtualizedListProps<T>) {
    const [scrollTop, setScrollTop] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    
    // Intersection observer for infinite loading
    const { elementRef: loadMoreRef, isIntersecting } = useIntersectionObserver({
        threshold: 0.1,
        rootMargin: '100px'
    });

    // Calculate visible range
    const { startIndex, endIndex, visibleItems } = useMemo(() => {
        const visibleStart = Math.floor(scrollTop / itemHeight);
        const visibleEnd = Math.min(
            items.length - 1,
            Math.ceil((scrollTop + containerHeight) / itemHeight)
        );

        const start = Math.max(0, visibleStart - overscan);
        const end = Math.min(items.length - 1, visibleEnd + overscan);

        return {
            startIndex: start,
            endIndex: end,
            visibleItems: items.slice(start, end + 1)
        };
    }, [scrollTop, itemHeight, containerHeight, items.length, overscan]);

    // Handle scroll
    const handleScroll = (e: React.UIEvent<HTMLDivElement>) => {
        const newScrollTop = e.currentTarget.scrollTop;
        setScrollTop(newScrollTop);
        onScroll?.(newScrollTop);
    };

    // Load more when intersection observed
    useEffect(() => {
        if (isIntersecting && hasMore && !loading && loadMore) {
            loadMore();
        }
    }, [isIntersecting, hasMore, loading, loadMore]);

    // Total height of all items
    const totalHeight = items.length * itemHeight;
    const offsetY = startIndex * itemHeight;

    return (
        <div
            ref={containerRef}
            className={`overflow-auto ${className}`}
            style={{ height: containerHeight }}
            onScroll={handleScroll}
        >
            <div style={{ height: totalHeight, position: 'relative' }}>
                <div
                    style={{
                        transform: `translateY(${offsetY}px)`,
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        right: 0,
                    }}
                >
                    {visibleItems.map((item, index) => (
                        <div
                            key={startIndex + index}
                            style={{ height: itemHeight }}
                        >
                            {renderItem(item, startIndex + index)}
                        </div>
                    ))}
                </div>

                {/* Load more trigger */}
                {hasMore && (
                    <div
                        ref={loadMoreRef as React.RefObject<HTMLDivElement>}
                        style={{
                            position: 'absolute',
                            bottom: 0,
                            left: 0,
                            right: 0,
                            height: 1
                        }}
                    />
                )}

                {/* Loading indicator */}
                {loading && (
                    <div className="absolute bottom-0 left-0 right-0 flex justify-center py-4">
                        <div className="flex items-center space-x-2">
                            <div className="w-4 h-4 border-2 border-gray-600 border-t-white rounded-full animate-spin" />
                            <span className="text-gray-400">Loading more...</span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

// Specialized component for comic grids
interface Comic {
    id: number;
    title: string;
    cover_image_url?: string;
    slug: string;
}

interface VirtualizedComicGridProps {
    comics: Comic[];
    itemsPerRow: number;
    itemHeight: number;
    containerHeight: number;
    renderComic: (comic: Comic, index: number) => React.ReactNode;
    loadMore?: () => void;
    hasMore?: boolean;
    loading?: boolean;
    className?: string;
}

export function VirtualizedComicGrid({
    comics,
    itemsPerRow,
    itemHeight,
    containerHeight,
    renderComic,
    loadMore,
    hasMore = false,
    loading = false,
    className = ''
}: VirtualizedComicGridProps) {
    // Group comics into rows
    const rows = useMemo(() => {
        const grouped: Comic[][] = [];
        for (let i = 0; i < comics.length; i += itemsPerRow) {
            grouped.push(comics.slice(i, i + itemsPerRow));
        }
        return grouped;
    }, [comics, itemsPerRow]);

    return (
        <VirtualizedList
            items={rows}
            itemHeight={itemHeight}
            containerHeight={containerHeight}
            renderItem={(row, rowIndex) => (
                <div className="flex space-x-4">
                    {row.map((comic, colIndex) => (
                        <div key={comic.id} className="flex-1">
                            {renderComic(comic, rowIndex * itemsPerRow + colIndex)}
                        </div>
                    ))}
                    {/* Fill empty slots in last row */}
                    {row.length < itemsPerRow && 
                        Array.from({ length: itemsPerRow - row.length }).map((_, i) => (
                            <div key={`empty-${i}`} className="flex-1" />
                        ))
                    }
                </div>
            )}
            loadMore={loadMore}
            hasMore={hasMore}
            loading={loading}
            className={className}
        />
    );
}