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
    },
  },
  plugins: [],
}