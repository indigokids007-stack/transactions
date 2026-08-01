/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  // The built app is served by Laravel from `public/app`, on the same origin as the API,
  // so asset URLs must resolve under `/app/` rather than the site root. Same origin also
  // means `VITE_API_BASE_URL` stays empty and requests go to `/api/...` relative.
  base: '/app/',
  build: { outDir: '../public/app', emptyOutDir: true },
  server: { host: '0.0.0.0' },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    globals: true,
  },
})
