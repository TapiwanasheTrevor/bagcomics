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

// Add axios request interceptor to ensure fresh CSRF token
window.axios.interceptors.request.use(
    (config) => {
        // Always update CSRF token before requests for POST, PUT, PATCH, DELETE
        if (['post', 'put', 'patch', 'delete'].includes(config.method?.toLowerCase())) {
            const token = getCsrfToken();
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token;
            }
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Add axios response interceptor to handle 419 errors
window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response && error.response.status === 419) {
            console.warn('CSRF token mismatch (419) detected, attempting to refresh...');
            
            try {
                // Always reload the page for 419 errors to ensure fresh token and session
                if (window.location.pathname === '/login' || window.location.pathname === '/register') {
                    console.log('Reloading auth page to get fresh CSRF token and session...');
                    window.location.reload();
                    return Promise.reject(error);
                }
                
                // For other pages, try to refresh the CSRF token
                const response = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to fetch CSRF cookie: ${response.status}`);
                }
                
                // Wait a bit for the token to be updated in the DOM
                await new Promise(resolve => setTimeout(resolve, 200));
                updateCsrfToken();
                console.log('CSRF token refreshed successfully');
                
                // Show user message to try again
                console.info('CSRF token refreshed. Please try your action again');
            } catch (refreshError) {
                console.error('Failed to refresh CSRF token:', refreshError);
                // If we can't refresh token, reload the page
                console.log('Reloading page due to CSRF token refresh failure...');
                window.location.reload();
            }
        }
        
        return Promise.reject(error);
    }
);

// Export utility functions
window.updateCsrfToken = updateCsrfToken;
window.getCsrfToken = getCsrfToken;