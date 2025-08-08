import { router } from '@inertiajs/react';

export function useLogout() {
    const handleLogout = async () => {
        try {
            // First get CSRF cookie
            await fetch('/sanctum/csrf-cookie', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });

            // Then perform logout with proper CSRF handling
            router.post('/logout', {}, {
                preserveScroll: false,
                preserveState: false,
                replace: true,
                onError: (errors) => {
                    console.error('Logout failed:', errors);
                    // Force reload to clear state on error
                    window.location.href = '/';
                },
                onFinish: () => {
                    // Clear any cached data
                    router.flushAll();
                }
            });
        } catch (error) {
            console.error('CSRF cookie fetch failed:', error);
            // Fallback to direct logout
            router.post('/logout', {}, {
                preserveScroll: false,
                preserveState: false,
                replace: true,
                onError: () => {
                    // Force reload on error
                    window.location.href = '/';
                }
            });
        }
    };

    return handleLogout;
}