/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.tsx",
  ],
  theme: {
    extend: {
        colors: {
            'brand-red': {
                50: '#fef2f2',
                100: '#fee2e2',
                200: '#fecaca',
                300: '#fca5a5',
                400: '#f87171',
                500: '#ef4444',
                600: '#dc2626',
                700: '#b91c1c',
                800: '#991b1b',
                900: '#7f1d1d',
                950: '#450a0a',
            },
            'brand-black': '#000000',
            'brand-white': '#FFFFFF',
            'comic-red': '#DC143C',
            'comic-dark': '#1a1a1a',
        },
        screens: {
            'xs': '475px',
            '3xl': '1600px',
        },
        spacing: {
            '18': '4.5rem',
            '88': '22rem',
        },
        minHeight: {
            'screen-safe': 'calc(100vh - env(safe-area-inset-top) - env(safe-area-inset-bottom))',
        },
        maxWidth: {
            '8xl': '88rem',
            '9xl': '96rem',
        },
    },
  },
  plugins: [],
}