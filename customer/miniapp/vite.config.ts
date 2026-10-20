import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// node_modules/<package>/ or node_modules/<scope>/<package>/ (works with / and \ separators)
const fromPackages = (...names: string[]) =>
  new RegExp(`node_modules[\\\\/](?:${names.join('|')})[\\\\/]`)

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
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
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
