import type { route as routeFn } from 'ziggy-js';
import type { AxiosStatic } from 'axios';

declare global {
    const route: typeof routeFn;
    
    interface Window {
        axios: AxiosStatic;
        updateCsrfToken: () => void;
        getCsrfToken: () => string | null;
    }
}
