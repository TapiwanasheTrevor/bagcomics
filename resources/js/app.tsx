import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializePushNotifications } from './services/push-notifications';
import { offlineStorage } from './services/offline-storage';
import { preloadCriticalResources } from './utils/progressive-loading';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Initialize PWA features
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Initialize offline storage
        await offlineStorage.initialize();
        console.log('Offline storage initialized');
        
        // Initialize push notifications
        await initializePushNotifications();
        console.log('Push notifications initialized');
        
        // Preload critical resources
        await preloadCriticalResources();
        console.log('Critical resources preloaded');
        
        // Clean up old offline data periodically
        const lastCleanup = localStorage.getItem('lastOfflineCleanup');
        const now = Date.now();
        const oneWeek = 7 * 24 * 60 * 60 * 1000;
        
        if (!lastCleanup || now - parseInt(lastCleanup) > oneWeek) {
            await offlineStorage.clearOldData(30);
            localStorage.setItem('lastOfflineCleanup', now.toString());
            console.log('Offline data cleanup completed');
        }
    } catch (error) {
        console.error('Failed to initialize PWA features:', error);
    }
});
