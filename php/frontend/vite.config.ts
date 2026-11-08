import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Builds into ../public next to the PHP api, so the whole php/ folder can be deployed as-is.
// For a sub folder (e.g. https://example.nl/sheet/) build with: WEGREP_BASE=/sheet/ npm run build
export default defineConfig({
  base: process.env.WEGREP_BASE ?? '/',
  plugins: [vue(), tailwindcss()],
  build: { outDir: '../public', emptyOutDir: false },
  server: { proxy: { '/api': 'http://localhost:8080' } },
})
