import path from 'path';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, '.', '');
    return {
      base: '/frontend/dist/',
      server: {
        port: 3000,
        host: '0.0.0.0',
      },
      build: {
        outDir: 'dist',
        emptyOutDir: true,
        sourcemap: false,
      },
      plugins: [react()],
      define: {},
      resolve: {
        alias: {
          '@': path.resolve(__dirname, '.'),
        }
      }
    };
});
