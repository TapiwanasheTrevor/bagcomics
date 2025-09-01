interface PerformanceMetrics {
    name: string;
    duration: number;
    timestamp: number;
    type: 'navigation' | 'resource' | 'measure' | 'custom';
    metadata?: Record<string, any>;
}

interface VitalMetrics {
    CLS: number; // Cumulative Layout Shift
    FID: number; // First Input Delay
    LCP: number; // Largest Contentful Paint
    FCP: number; // First Contentful Paint
    TTFB: number; // Time to First Byte
}

class PerformanceMonitor {
    private static instance: PerformanceMonitor;
    private metrics: PerformanceMetrics[] = [];
    private observers: PerformanceObserver[] = [];
    private vitals: Partial<VitalMetrics> = {};

    static getInstance(): PerformanceMonitor {
        if (!PerformanceMonitor.instance) {
            PerformanceMonitor.instance = new PerformanceMonitor();
        }
        return PerformanceMonitor.instance;
    }

    constructor() {
        if (typeof window !== 'undefined') {
            this.setupObservers();
            this.measureNavigationTiming();
        }
    }

    private setupObservers() {
        // Largest Contentful Paint
        if ('PerformanceObserver' in window) {
            try {
                const lcpObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lcpEntry = entries[entries.length - 1] as PerformanceEntry & { startTime: number };
                    this.vitals.LCP = lcpEntry.startTime;
                    
                    this.recordMetric({
                        name: 'LCP',
                        duration: lcpEntry.startTime,
                        timestamp: Date.now(),
                        type: 'measure'
                    });
                });
                
                lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
                this.observers.push(lcpObserver);
            } catch (e) {
                console.warn('LCP observer not supported');
            }

            // First Input Delay
            try {
                const fidObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        const fidEntry = entry as PerformanceEntry & { processingStart: number; startTime: number };
                        const fid = fidEntry.processingStart - fidEntry.startTime;
                        this.vitals.FID = fid;
                        
                        this.recordMetric({
                            name: 'FID',
                            duration: fid,
                            timestamp: Date.now(),
                            type: 'measure'
                        });
                    });
                });
                
                fidObserver.observe({ entryTypes: ['first-input'] });
                this.observers.push(fidObserver);
            } catch (e) {
                console.warn('FID observer not supported');
            }

            // Cumulative Layout Shift
            try {
                let clsValue = 0;
                const clsObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        const clsEntry = entry as PerformanceEntry & { value: number; hadRecentInput: boolean };
                        if (!clsEntry.hadRecentInput) {
                            clsValue += clsEntry.value;
                        }
                    });
                    
                    this.vitals.CLS = clsValue;
                    
                    this.recordMetric({
                        name: 'CLS',
                        duration: clsValue,
                        timestamp: Date.now(),
                        type: 'measure'
                    });
                });
                
                clsObserver.observe({ entryTypes: ['layout-shift'] });
                this.observers.push(clsObserver);
            } catch (e) {
                console.warn('CLS observer not supported');
            }

            // Resource loading
            try {
                const resourceObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        const resourceEntry = entry as PerformanceResourceTiming;
                        this.recordMetric({
                            name: entry.name,
                            duration: resourceEntry.duration,
                            timestamp: Date.now(),
                            type: 'resource',
                            metadata: {
                                transferSize: resourceEntry.transferSize,
                                decodedBodySize: resourceEntry.decodedBodySize,
                                initiatorType: resourceEntry.initiatorType
                            }
                        });
                    });
                });
                
                resourceObserver.observe({ entryTypes: ['resource'] });
                this.observers.push(resourceObserver);
            } catch (e) {
                console.warn('Resource observer not supported');
            }
        }
    }

    private measureNavigationTiming() {
        if (typeof window !== 'undefined' && 'performance' in window) {
            window.addEventListener('load', () => {
                const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
                
                if (navigation) {
                    // First Contentful Paint
                    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
                    if (fcp) {
                        this.vitals.FCP = fcp.startTime;
                        this.recordMetric({
                            name: 'FCP',
                            duration: fcp.startTime,
                            timestamp: Date.now(),
                            type: 'measure'
                        });
                    }

                    // Time to First Byte
                    const ttfb = navigation.responseStart - navigation.requestStart;
                    this.vitals.TTFB = ttfb;
                    this.recordMetric({
                        name: 'TTFB',
                        duration: ttfb,
                        timestamp: Date.now(),
                        type: 'measure'
                    });

                    // Other navigation metrics
                    this.recordMetric({
                        name: 'DOM Content Loaded',
                        duration: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                        timestamp: Date.now(),
                        type: 'navigation'
                    });

                    this.recordMetric({
                        name: 'Load Complete',
                        duration: navigation.loadEventEnd - navigation.loadEventStart,
                        timestamp: Date.now(),
                        type: 'navigation'
                    });
                }
            });
        }
    }

    recordMetric(metric: PerformanceMetrics) {
        this.metrics.push(metric);
        
        // Keep only last 100 metrics to prevent memory leaks
        if (this.metrics.length > 100) {
            this.metrics = this.metrics.slice(-100);
        }
    }

    startMeasure(name: string): () => void {
        const startTime = performance.now();
        
        return () => {
            const duration = performance.now() - startTime;
            this.recordMetric({
                name,
                duration,
                timestamp: Date.now(),
                type: 'custom'
            });
        };
    }

    measureAsync<T>(name: string, fn: () => Promise<T>): Promise<T> {
        const startTime = performance.now();
        
        return fn().finally(() => {
            const duration = performance.now() - startTime;
            this.recordMetric({
                name,
                duration,
                timestamp: Date.now(),
                type: 'custom'
            });
        });
    }

    getMetrics(): PerformanceMetrics[] {
        return [...this.metrics];
    }

    getVitals(): Partial<VitalMetrics> {
        return { ...this.vitals };
    }

    getAverageLoadTime(type?: string): number {
        const filteredMetrics = type 
            ? this.metrics.filter(m => m.name.includes(type))
            : this.metrics.filter(m => m.type === 'resource');
            
        if (filteredMetrics.length === 0) return 0;
        
        const totalTime = filteredMetrics.reduce((sum, m) => sum + m.duration, 0);
        return totalTime / filteredMetrics.length;
    }

    getSlowestResources(count: number = 5): PerformanceMetrics[] {
        return this.metrics
            .filter(m => m.type === 'resource')
            .sort((a, b) => b.duration - a.duration)
            .slice(0, count);
    }

    getReport(): {
        vitals: Partial<VitalMetrics>;
        averageResourceLoad: number;
        slowestResources: PerformanceMetrics[];
        totalMetrics: number;
        timeRange: { start: number; end: number };
    } {
        const sortedByTime = this.metrics.sort((a, b) => a.timestamp - b.timestamp);
        
        return {
            vitals: this.getVitals(),
            averageResourceLoad: this.getAverageLoadTime(),
            slowestResources: this.getSlowestResources(),
            totalMetrics: this.metrics.length,
            timeRange: {
                start: sortedByTime[0]?.timestamp || 0,
                end: sortedByTime[sortedByTime.length - 1]?.timestamp || 0
            }
        };
    }

    clear() {
        this.metrics = [];
        this.vitals = {};
    }

    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers = [];
        this.clear();
    }
}

export const performanceMonitor = PerformanceMonitor.getInstance();

// React hook for performance monitoring
export function usePerformanceMonitor() {
    const measureComponent = (componentName: string) => {
        return performanceMonitor.startMeasure(`Component: ${componentName}`);
    };

    const measureAsync = <T>(name: string, fn: () => Promise<T>) => {
        return performanceMonitor.measureAsync(name, fn);
    };

    const getReport = () => {
        return performanceMonitor.getReport();
    };

    return {
        measureComponent,
        measureAsync,
        getReport,
        recordMetric: (metric: Omit<PerformanceMetrics, 'timestamp'>) => {
            performanceMonitor.recordMetric({
                ...metric,
                timestamp: Date.now()
            });
        }
    };
}

export default performanceMonitor;