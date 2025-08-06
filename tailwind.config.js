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
            'brand-red': '#FF0000',
            'brand-black': '#000000',
            'brand-white': '#FFFFFF',
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