import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Enhanced CSRF token handling with automatic refresh
 */
function getCsrfToken() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    return csrfToken ? csrfToken.getAttribute('content') : null;
}

function updateCsrfToken() {
    const token = getCsrfToken();
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    } else {
        console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-token');
    }
}

// Set initial CSRF token
updateCsrfToken();

// Add axios response interceptor to handle 419 errors
window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response && error.response.status === 419) {
            console.warn('CSRF token mismatch detected, attempting to refresh...');
            
            try {
                // Try to get a fresh CSRF token
                await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                
                // Wait a bit for the token to be updated in the DOM
                setTimeout(() => {
                    updateCsrfToken();
                    console.log('CSRF token refreshed');
                }, 100);
                
                // Don't retry automatically to avoid infinite loops
                // Let the user know they should try again
                if (window.location.pathname !== '/login') {
                    console.info('Please try your action again');
                }
            } catch (refreshError) {
                console.error('Failed to refresh CSRF token:', refreshError);
            }
        }
        
        return Promise.reject(error);
    }
);

// Export utility functions
window.updateCsrfToken = updateCsrfToken;
window.getCsrfToken = getCsrfToken;