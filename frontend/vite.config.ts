import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  // Dev only: proxy the API through the dev server so the browser sees a single
  // origin. This keeps the SameSite=Lax session cookie first-party in dev (the
  // backend serves https://localhost; without this, http://localhost:5173 is a
  // different "site" under schemeful same-site and the cookie is dropped).
  server: {
    proxy: {
      '/api': {
        target: 'https://localhost',
        changeOrigin: true,
        secure: false, // backend uses a self-signed dev cert
      },
    },
  },
})
