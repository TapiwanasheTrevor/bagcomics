import React, { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { performanceMonitor, usePerformanceMonitor } from '@/utils/performanceMonitor';

interface PerformanceContextType {
    isSlowConnection: boolean;
    networkSpeed: 'slow' | 'medium' | 'fast';
    enableOptimizations: boolean;
    measureComponent: (name: string) => () => void;
    measureAsync: <T>(name: string, fn: () => Promise<T>) => Promise<T>;
}

const PerformanceContext = createContext<PerformanceContextType | undefined>(undefined);

interface PerformanceProviderProps {
    children: ReactNode;
    enableAutoOptimizations?: boolean;
    slowConnectionThreshold?: number;
}

export function PerformanceProvider({
    children,
    enableAutoOptimizations = true,
    slowConnectionThreshold = 1000
}: PerformanceProviderProps) {
    const [isSlowConnection, setIsSlowConnection] = useState(false);
    const [networkSpeed, setNetworkSpeed] = useState<'slow' | 'medium' | 'fast'>('medium');
    const [enableOptimizations, setEnableOptimizations] = useState(enableAutoOptimizations);
    
    const { measureComponent, measureAsync } = usePerformanceMonitor();

    useEffect(() => {
        // Detect network connection speed
        const detectNetworkSpeed = () => {
            if ('connection' in navigator) {
                const connection = (navigator as any).connection;
                const effectiveType = connection.effectiveType;
                
                switch (effectiveType) {
                    case 'slow-2g':
                    case '2g':
                        setNetworkSpeed('slow');
                        setIsSlowConnection(true);
                        break;
                    case '3g':
                        setNetworkSpeed('medium');
                        setIsSlowConnection(false);
                        break;
                    case '4g':
                    default:
                        setNetworkSpeed('fast');
                        setIsSlowConnection(false);
                        break;
                }
            }
        };

        // Monitor performance metrics
        const checkPerformance = () => {
            const report = performanceMonitor.getReport();
            const avgResourceLoad = report.averageResourceLoad;
            
            if (avgResourceLoad > slowConnectionThreshold) {
                setIsSlowConnection(true);
                setEnableOptimizations(true);
            }
        };

        detectNetworkSpeed();
        
        // Check performance periodically
        const performanceInterval = setInterval(checkPerformance, 10000);
        
        // Listen for network changes
        if ('connection' in navigator) {
            const connection = (navigator as any).connection;
            connection.addEventListener('change', detectNetworkSpeed);
            
            return () => {
                connection.removeEventListener('change', detectNetworkSpeed);
                clearInterval(performanceInterval);
            };
        }

        return () => {
            clearInterval(performanceInterval);
        };
    }, [slowConnectionThreshold]);

    // Auto-optimize based on performance
    useEffect(() => {
        if (!enableAutoOptimizations) return;

        const optimizeForSlow = () => {
            if (isSlowConnection) {
                // Reduce image quality
                document.documentElement.style.setProperty('--image-quality', '60');
                
                // Disable non-essential animations
                document.documentElement.classList.add('reduce-motion');
                
                // Reduce preloading
                document.documentElement.style.setProperty('--preload-distance', '50px');
            } else {
                // Restore normal quality
                document.documentElement.style.setProperty('--image-quality', '80');
                document.documentElement.classList.remove('reduce-motion');
                document.documentElement.style.setProperty('--preload-distance', '200px');
            }
        };

        optimizeForSlow();
    }, [isSlowConnection, enableAutoOptimizations]);

    const contextValue: PerformanceContextType = {
        isSlowConnection,
        networkSpeed,
        enableOptimizations,
        measureComponent,
        measureAsync
    };

    return (
        <PerformanceContext.Provider value={contextValue}>
            {children}
        </PerformanceContext.Provider>
    );
}

export function usePerformanceContext(): PerformanceContextType {
    const context = useContext(PerformanceContext);
    if (context === undefined) {
        throw new Error('usePerformanceContext must be used within a PerformanceProvider');
    }
    return context;
}

// HOC for measuring component performance
export function withPerformanceTracking<P extends object>(
    WrappedComponent: React.ComponentType<P>,
    componentName?: string
) {
    const ComponentWithPerformanceTracking = (props: P) => {
        const { measureComponent } = usePerformanceContext();
        
        useEffect(() => {
            const stopMeasure = measureComponent(
                componentName || WrappedComponent.name || 'Anonymous Component'
            );
            
            return stopMeasure;
        }, [measureComponent]);

        return <WrappedComponent {...props} />;
    };

    ComponentWithPerformanceTracking.displayName = 
        `withPerformanceTracking(${componentName || WrappedComponent.name})`;

    return ComponentWithPerformanceTracking;
}

export default PerformanceProvider;