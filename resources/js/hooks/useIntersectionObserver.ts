import { useEffect, useRef, useState } from 'react';

interface UseIntersectionObserverOptions {
    threshold?: number | number[];
    rootMargin?: string;
    freezeOnceVisible?: boolean;
    initialIsIntersecting?: boolean;
}

export function useIntersectionObserver({
    threshold = 0.1,
    rootMargin = '0px',
    freezeOnceVisible = false,
    initialIsIntersecting = false
}: UseIntersectionObserverOptions = {}) {
    const [entry, setEntry] = useState<IntersectionObserverEntry>();
    const [isIntersecting, setIsIntersecting] = useState(initialIsIntersecting);
    const elementRef = useRef<HTMLElement>();

    const frozen = entry?.isIntersecting && freezeOnceVisible;

    const updateEntry = ([entry]: IntersectionObserverEntry[]): void => {
        setEntry(entry);
        setIsIntersecting(entry.isIntersecting);
    };

    useEffect(() => {
        const node = elementRef?.current;
        const hasIOSupport = !!window.IntersectionObserver;

        if (!hasIOSupport || frozen || !node) return;

        const observerParams = { threshold, rootMargin };
        const observer = new IntersectionObserver(updateEntry, observerParams);

        observer.observe(node);

        return () => observer.disconnect();
    }, [elementRef, JSON.stringify(threshold), rootMargin, frozen]);

    return { elementRef, entry, isIntersecting };
}

export default useIntersectionObserver;