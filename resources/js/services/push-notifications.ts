// Push notification service for PWA

export interface NotificationOptions {
    title: string;
    body: string;
    icon?: string;
    badge?: string;
    image?: string;
    tag?: string;
    data?: any;
    actions?: Array<{
        action: string;
        title: string;
        icon?: string;
    }>;
    requireInteraction?: boolean;
    silent?: boolean;
    vibrate?: number[];
}

export class PushNotificationService {
    private static instance: PushNotificationService;
    private registration: ServiceWorkerRegistration | null = null;
    private subscription: PushSubscription | null = null;
    
    // VAPID public key - should be generated and stored securely
    private readonly vapidPublicKey = 'YOUR_VAPID_PUBLIC_KEY_HERE';
    
    private constructor() {}
    
    static getInstance(): PushNotificationService {
        if (!this.instance) {
            this.instance = new PushNotificationService();
        }
        return this.instance;
    }
    
    async initialize(): Promise<boolean> {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push notifications not supported');
            return false;
        }
        
        try {
            this.registration = await navigator.serviceWorker.ready;
            console.log('Push notification service initialized');
            return true;
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return false;
        }
    }
    
    async requestPermission(): Promise<NotificationPermission> {
        if (!('Notification' in window)) {
            console.warn('Notifications not supported');
            return 'denied';
        }
        
        let permission = Notification.permission;
        
        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }
        
        return permission;
    }
    
    async subscribe(): Promise<PushSubscription | null> {
        if (!this.registration) {
            console.error('Service worker not registered');
            return null;
        }
        
        const permission = await this.requestPermission();
        if (permission !== 'granted') {
            console.warn('Notification permission denied');
            return null;
        }
        
        try {
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });
            
            // Send subscription to server
            await this.sendSubscriptionToServer(this.subscription);
            
            console.log('Push subscription successful');
            return this.subscription;
        } catch (error) {
            console.error('Failed to subscribe to push notifications:', error);
            return null;
        }
    }
    
    async unsubscribe(): Promise<boolean> {
        if (!this.subscription) {
            return true;
        }
        
        try {
            const successful = await this.subscription.unsubscribe();
            if (successful) {
                // Remove subscription from server
                await this.removeSubscriptionFromServer(this.subscription);
                this.subscription = null;
                console.log('Push unsubscription successful');
            }
            return successful;
        } catch (error) {
            console.error('Failed to unsubscribe from push notifications:', error);
            return false;
        }
    }
    
    async getSubscription(): Promise<PushSubscription | null> {
        if (!this.registration) {
            return null;
        }
        
        try {
            this.subscription = await this.registration.pushManager.getSubscription();
            return this.subscription;
        } catch (error) {
            console.error('Failed to get push subscription:', error);
            return null;
        }
    }
    
    async showLocalNotification(options: NotificationOptions): Promise<void> {
        const permission = await this.requestPermission();
        if (permission !== 'granted') {
            console.warn('Cannot show notification: permission denied');
            return;
        }
        
        if (!this.registration) {
            // Fallback to browser notification
            new Notification(options.title, {
                body: options.body,
                icon: options.icon || '/favicon-192.png',
                badge: options.badge || '/favicon-192.png',
                image: options.image,
                tag: options.tag,
                data: options.data,
                requireInteraction: options.requireInteraction,
                silent: options.silent,
                vibrate: options.vibrate || [200, 100, 200]
            });
            return;
        }
        
        try {
            await this.registration.showNotification(options.title, {
                body: options.body,
                icon: options.icon || '/favicon-192.png',
                badge: options.badge || '/favicon-192.png',
                image: options.image,
                tag: options.tag,
                data: options.data,
                actions: options.actions,
                requireInteraction: options.requireInteraction,
                silent: options.silent,
                vibrate: options.vibrate || [200, 100, 200]
            });
        } catch (error) {
            console.error('Failed to show notification:', error);
        }
    }
    
    private async sendSubscriptionToServer(subscription: PushSubscription): Promise<void> {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            await fetch('/api/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });
        } catch (error) {
            console.error('Failed to send subscription to server:', error);
        }
    }
    
    private async removeSubscriptionFromServer(subscription: PushSubscription): Promise<void> {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            await fetch('/api/push-subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });
        } catch (error) {
            console.error('Failed to remove subscription from server:', error);
        }
    }
    
    private urlBase64ToUint8Array(base64String: string): Uint8Array {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
}

// Notification templates for common use cases
export class NotificationTemplates {
    static newComicRelease(comicTitle: string, author: string): NotificationOptions {
        return {
            title: 'New Comic Release!',
            body: `"${comicTitle}" by ${author} is now available`,
            icon: '/favicon-192.png',
            badge: '/favicon-192.png',
            tag: 'new-release',
            data: {
                type: 'new-release',
                url: '/comics'
            },
            actions: [
                {
                    action: 'view',
                    title: 'View Comic'
                },
                {
                    action: 'dismiss',
                    title: 'Dismiss'
                }
            ],
            vibrate: [200, 100, 200, 100, 200]
        };
    }
    
    static readingReminder(comicTitle: string, currentPage: number): NotificationOptions {
        return {
            title: 'Continue Reading',
            body: `You left off on page ${currentPage} of "${comicTitle}"`,
            icon: '/favicon-192.png',
            badge: '/favicon-192.png',
            tag: 'reading-reminder',
            data: {
                type: 'reading-reminder',
                url: '/library'
            },
            actions: [
                {
                    action: 'continue',
                    title: 'Continue Reading'
                },
                {
                    action: 'dismiss',
                    title: 'Later'
                }
            ]
        };
    }
    
    static libraryUpdate(count: number): NotificationOptions {
        return {
            title: 'Library Updated',
            body: `${count} new comics added to your library`,
            icon: '/favicon-192.png',
            badge: '/favicon-192.png',
            tag: 'library-update',
            data: {
                type: 'library-update',
                url: '/library'
            },
            actions: [
                {
                    action: 'view',
                    title: 'View Library'
                }
            ]
        };
    }
    
    static downloadComplete(comicTitle: string): NotificationOptions {
        return {
            title: 'Download Complete',
            body: `"${comicTitle}" is now available offline`,
            icon: '/favicon-192.png',
            badge: '/favicon-192.png',
            tag: 'download-complete',
            data: {
                type: 'download-complete'
            },
            actions: [
                {
                    action: 'read',
                    title: 'Read Now'
                }
            ]
        };
    }
}

// Initialize push notifications
export async function initializePushNotifications(): Promise<void> {
    const pushService = PushNotificationService.getInstance();
    const initialized = await pushService.initialize();
    
    if (initialized) {
        // Check if user is already subscribed
        const existingSubscription = await pushService.getSubscription();
        if (!existingSubscription) {
            console.log('Push notifications ready for subscription');
        } else {
            console.log('Already subscribed to push notifications');
        }
    }
}

// Export singleton instance
export const pushNotificationService = PushNotificationService.getInstance();