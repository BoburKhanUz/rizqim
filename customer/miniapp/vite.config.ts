import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// node_modules/<package>/ or node_modules/<scope>/<package>/ (works with / and \ separators)
const fromPackages = (...names: string[]) =>
  new RegExp(`node_modules[\\\\/](?:${names.join('|')})[\\\\/]`)

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    // @rizqim/ui and @rizqim/staff (packages/ts/) are linked and compiled as source from outside this folder: their imports
    // of react, react-dom, lucide-react, @tanstack/react-query and the sibling packages must resolve to this app's copies,
    // not to a second React or a second query cache context
    dedupe: ['react', 'react-dom', 'lucide-react', '@tanstack/react-query', '@rizqim/api-client', '@rizqim/ui'],
  },
  build: {
    rolldownOptions: {
      output: {
        // Vendor code in a few stable chunks: they change far less often than the app, so a returning
        // user keeps them cached across releases. Pages are split by route in App.tsx; the map (leaflet)
        // is only imported by the lazily loaded map picker, so its chunk is only fetched together with it.
        codeSplitting: {
          groups: [
            { name: 'leaflet', test: fromPackages('leaflet', 'react-leaflet', '@react-leaflet'), priority: 40 },
            { name: 'react-vendor', test: fromPackages('react', 'react-dom', 'scheduler', 'react-router', 'react-router-dom'), priority: 50 },
            { name: 'query-vendor', test: fromPackages('@tanstack'), priority: 30 },
            { name: 'telegram-vendor', test: fromPackages('@twa-dev'), priority: 30 },
            { name: 'http-vendor', test: fromPackages('axios', 'zustand'), priority: 30 },
            { name: 'icons', test: fromPackages('lucide-react'), priority: 30 },
          ],
        },
      },
    },
  },
  server: {
    allowedHosts: true,
    // The linked @rizqim/ui and @rizqim/staff sources live in packages/ts/, outside this folder
    fs: { allow: ['../..'] },
    proxy: {
      '/api': {
        // P2-31: scripts/e2e-stack.sh runs the backend on another port and points `vite preview` (which uses this proxy) at it
        target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
