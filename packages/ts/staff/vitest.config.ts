import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  resolve: {
    // @rizqim/ui is linked from packages/ts/ui and compiled as source: its imports of react and lucide-react must
    // resolve to this package's copies (one React)
    dedupe: ['react', 'react-dom', 'lucide-react', '@tanstack/react-query'],
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.{ts,tsx}'],
    css: false,
    restoreMocks: true,
    unstubGlobals: true,
  },
});
