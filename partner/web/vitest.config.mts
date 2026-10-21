import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    // @rizqim/ui and @rizqim/staff are linked from packages/ts/ and compiled as source: their imports of react, lucide-react,
    // @tanstack/react-query and the sibling packages must resolve to this app's copies (one React, one query context)
    dedupe: ['react', 'react-dom', 'lucide-react', '@tanstack/react-query', '@rizqim/api-client', '@rizqim/ui'],
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    css: false,
    restoreMocks: true,
    unstubGlobals: true,
  },
});
