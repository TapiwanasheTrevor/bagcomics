import { useState, useEffect } from 'react';

export function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
}

export function useThrottle<T>(value: T, limit: number): T {
    const [throttledValue, setThrottledValue] = useState<T>(value);
    const [lastRun, setLastRun] = useState(Date.now());

    useEffect(() => {
        if (Date.now() - lastRun >= limit) {
            setThrottledValue(value);
            setLastRun(Date.now());
        } else {
            const timeout = setTimeout(() => {
                setThrottledValue(value);
                setLastRun(Date.now());
            }, limit - (Date.now() - lastRun));

            return () => clearTimeout(timeout);
        }
    }, [value, limit, lastRun]);

    return throttledValue;
}

export default useDebounce;