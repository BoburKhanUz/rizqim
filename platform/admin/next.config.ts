import path from 'node:path';
import type { NextConfig } from 'next';

// The repository root: @rizqim/ui and @rizqim/api-client are linked from packages/ts/ (file: dependencies), so the bundler
// and the standalone output tracing must be allowed to read outside this folder.
const repoRoot = path.join(__dirname, '../..');

const config: NextConfig = {
  // A self-contained server for the Docker image (Dockerfile): .next/standalone/platform/admin/server.js
  output: 'standalone',
  outputFileTracingRoot: repoRoot,
  // @rizqim/ui ships TypeScript source (no build step): Next compiles it with the app
  transpilePackages: ['@rizqim/ui'],
  turbopack: {
    root: repoRoot,
    // The linked @rizqim/ui source imports react, react-dom, lucide-react and @tanstack/react-query from its real location,
    // where there is no node_modules: resolve them to this app's copies (one React for the whole page). @rizqim/ui points at
    // its entry file: the package declares it only in "exports" (no "main"), which a folder alias does not read
    resolveAlias: {
      react: './node_modules/react',
      'react-dom': './node_modules/react-dom',
      'lucide-react': './node_modules/lucide-react',
      '@tanstack/react-query': './node_modules/@tanstack/react-query',
      '@rizqim/api-client': './node_modules/@rizqim/api-client',
      '@rizqim/ui': './node_modules/@rizqim/ui/src/index.ts',
    },
  },
  poweredByHeader: false,
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'X-Frame-Options', value: 'DENY' },
          { key: 'Referrer-Policy', value: 'same-origin' },
          // The control center is never indexed
          { key: 'X-Robots-Tag', value: 'noindex, nofollow' },
        ],
      },
      {
        // Pages and the proxy are never kept by a shared cache; the hashed files under /_next/ keep Next's own long cache
        source: '/((?!_next/).*)',
        headers: [{ key: 'Cache-Control', value: 'no-store' }],
      },
    ];
  },
  reactStrictMode: true,
};

export default config;
