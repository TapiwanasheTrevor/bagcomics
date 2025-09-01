import React, { useState, useEffect } from 'react';
import { Download, X, Smartphone, Monitor, Wifi, WifiOff } from 'lucide-react';

interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    readonly userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
    prompt(): Promise<void>;
}

export default function PWAInstallPrompt() {
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [showInstallPrompt, setShowInstallPrompt] = useState(false);
    const [isInstalled, setIsInstalled] = useState(false);
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [showOfflineNotice, setShowOfflineNotice] = useState(false);

    useEffect(() => {
        // Check if app is already installed
        const checkIfInstalled = () => {
            const isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                               (window.navigator as any).standalone === true ||
                               document.referrer.includes('android-app://');
            setIsInstalled(isInstalled);
        };

        checkIfInstalled();

        // Listen for the beforeinstallprompt event
        const handleBeforeInstallPrompt = (e: Event) => {
            e.preventDefault();
            setDeferredPrompt(e as BeforeInstallPromptEvent);
            
            // Don't show prompt if user has dismissed it before
            const dismissedTime = localStorage.getItem('pwa-install-dismissed');
            if (!dismissedTime || Date.now() - parseInt(dismissedTime) > 7 * 24 * 60 * 60 * 1000) { // 7 days
                setShowInstallPrompt(true);
            }
        };

        // Listen for app installation
        const handleAppInstalled = () => {
            setIsInstalled(true);
            setShowInstallPrompt(false);
            setDeferredPrompt(null);
            console.log('PWA was installed');
        };

        // Listen for online/offline status
        const handleOnline = () => {
            setIsOnline(true);
            setShowOfflineNotice(false);
        };

        const handleOffline = () => {
            setIsOnline(false);
            setShowOfflineNotice(true);
            setTimeout(() => setShowOfflineNotice(false), 5000); // Hide after 5 seconds
        };

        window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
        window.addEventListener('appinstalled', handleAppInstalled);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
            window.removeEventListener('appinstalled', handleAppInstalled);
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const handleInstallClick = async () => {
        if (!deferredPrompt) return;

        try {
            await deferredPrompt.prompt();
            const choiceResult = await deferredPrompt.userChoice;
            
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
                // Store dismissal time
                localStorage.setItem('pwa-install-dismissed', Date.now().toString());
            }
        } catch (error) {
            console.error('Error during PWA installation:', error);
        }

        setDeferredPrompt(null);
        setShowInstallPrompt(false);
    };

    const handleDismiss = () => {
        setShowInstallPrompt(false);
        localStorage.setItem('pwa-install-dismissed', Date.now().toString());
    };

    // Register service worker
    useEffect(() => {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/sw.js');
                    console.log('ServiceWorker registered successfully:', registration.scope);
                    
                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content available, show update notification
                                    console.log('New content available, please refresh.');
                                }
                            });
                        }
                    });
                } catch (error) {
                    console.error('ServiceWorker registration failed:', error);
                }
            });
        }
    }, []);

    // Don't show anything if app is already installed
    if (isInstalled) return null;

    return (
        <>
            {/* Install Prompt */}
            {showInstallPrompt && deferredPrompt && (
                <div className="fixed bottom-4 left-4 right-4 z-50 max-w-sm mx-auto">
                    <div className="bg-gray-900 border border-gray-700 rounded-xl p-4 shadow-2xl backdrop-blur-sm">
                        <div className="flex items-start justify-between mb-3">
                            <div className="flex items-center space-x-2">
                                <div className="p-2 bg-red-500/20 rounded-lg">
                                    <Download className="w-5 h-5 text-red-400" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-white text-sm">Install BAG Comics</h3>
                                    <p className="text-xs text-gray-400">Get the app experience</p>
                                </div>
                            </div>
                            <button
                                onClick={handleDismiss}
                                className="p-1 text-gray-400 hover:text-white transition-colors"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        
                        <div className="space-y-2 mb-4">
                            <div className="flex items-center space-x-2 text-xs text-gray-300">
                                <Smartphone className="w-3 h-3" />
                                <span>Works on mobile & desktop</span>
                            </div>
                            <div className="flex items-center space-x-2 text-xs text-gray-300">
                                <WifiOff className="w-3 h-3" />
                                <span>Read comics offline</span>
                            </div>
                            <div className="flex items-center space-x-2 text-xs text-gray-300">
                                <Monitor className="w-3 h-3" />
                                <span>Faster loading & notifications</span>
                            </div>
                        </div>

                        <div className="flex space-x-2">
                            <button
                                onClick={handleInstallClick}
                                className="flex-1 px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-medium rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200"
                            >
                                Install App
                            </button>
                            <button
                                onClick={handleDismiss}
                                className="px-3 py-2 text-gray-400 text-sm border border-gray-600 rounded-lg hover:text-white hover:border-gray-500 transition-colors"
                            >
                                Later
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Offline Notice */}
            {showOfflineNotice && (
                <div className="fixed top-4 left-4 right-4 z-50 max-w-sm mx-auto">
                    <div className="bg-yellow-900/90 border border-yellow-500/50 rounded-lg p-3 backdrop-blur-sm">
                        <div className="flex items-center space-x-2">
                            <WifiOff className="w-4 h-4 text-yellow-400" />
                            <div>
                                <p className="text-sm font-medium text-yellow-200">You're offline</p>
                                <p className="text-xs text-yellow-300">You can still read downloaded comics</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Online Status Indicator */}
            <div className="fixed top-4 right-4 z-40">
                <div className={`flex items-center space-x-1 px-2 py-1 rounded-full text-xs transition-all duration-300 ${
                    isOnline 
                        ? 'bg-green-900/20 text-green-400 border border-green-500/30' 
                        : 'bg-red-900/20 text-red-400 border border-red-500/30'
                }`}>
                    {isOnline ? <Wifi className="w-3 h-3" /> : <WifiOff className="w-3 h-3" />}
                    <span className="hidden sm:inline">{isOnline ? 'Online' : 'Offline'}</span>
                </div>
            </div>
        </>
    );
}

// Hook to check if PWA is installed
export function usePWAInstalled() {
    const [isInstalled, setIsInstalled] = useState(false);

    useEffect(() => {
        const checkInstalled = () => {
            const installed = window.matchMedia('(display-mode: standalone)').matches ||
                            (window.navigator as any).standalone === true ||
                            document.referrer.includes('android-app://');
            setIsInstalled(installed);
        };

        checkInstalled();
        
        // Listen for installation
        const handleAppInstalled = () => setIsInstalled(true);
        window.addEventListener('appinstalled', handleAppInstalled);
        
        return () => {
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    }, []);

    return isInstalled;
}

// Hook to manage offline functionality
export function useOfflineStorage() {
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [offlineActions, setOfflineActions] = useState<any[]>([]);

    useEffect(() => {
        const handleOnline = () => {
            setIsOnline(true);
            // Sync offline actions when back online
            syncOfflineActions();
        };

        const handleOffline = () => {
            setIsOnline(false);
        };

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const addOfflineAction = (action: any) => {
        if (!isOnline) {
            const newAction = {
                ...action,
                timestamp: Date.now(),
                id: Math.random().toString(36).substr(2, 9)
            };
            
            setOfflineActions(prev => [...prev, newAction]);
            
            // Store in localStorage for persistence
            const storedActions = JSON.parse(localStorage.getItem('offline-actions') || '[]');
            storedActions.push(newAction);
            localStorage.setItem('offline-actions', JSON.stringify(storedActions));
            
            return true; // Action queued for sync
        }
        return false; // Not offline, proceed normally
    };

    const syncOfflineActions = async () => {
        const storedActions = JSON.parse(localStorage.getItem('offline-actions') || '[]');
        
        for (const action of storedActions) {
            try {
                // Attempt to sync each action
                await fetch(action.endpoint, {
                    method: action.method || 'POST',
                    headers: action.headers || { 'Content-Type': 'application/json' },
                    body: JSON.stringify(action.data)
                });
                
                console.log('Synced offline action:', action);
            } catch (error) {
                console.error('Failed to sync action:', action, error);
            }
        }
        
        // Clear synced actions
        localStorage.removeItem('offline-actions');
        setOfflineActions([]);
    };

    return {
        isOnline,
        addOfflineAction,
        offlineActions,
        syncOfflineActions
    };
}