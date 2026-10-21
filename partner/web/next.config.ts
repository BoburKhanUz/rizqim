import path from 'node:path';
import type { NextConfig } from 'next';

// The repository root: @rizqim/ui, @rizqim/staff and @rizqim/api-client are linked from packages/ts/ (file: dependencies), so the
// bundler and the standalone output tracing must be allowed to read outside this folder.
const repoRoot = path.join(__dirname, '../..');

const config: NextConfig = {
  // A self-contained server for the Docker image (Dockerfile): .next/standalone/partner/web/server.js
  output: 'standalone',
  outputFileTracingRoot: repoRoot,
  // @rizqim/ui and @rizqim/staff ship TypeScript source (no build step): Next compiles them with the app
  transpilePackages: ['@rizqim/ui', '@rizqim/staff'],
  turbopack: {
    root: repoRoot,
    // The linked @rizqim/ui and @rizqim/staff sources import react, react-dom, lucide-react, @tanstack/react-query and the
    // sibling packages from their real location, where there is no node_modules: resolve them to this app's copies (one
    // React and one query cache context for the whole page). @rizqim/ui points at its entry file: the package declares it only
    // in "exports" (no "main"), which a folder alias does not read, and a clean checkout has no packages/ts/*/node_modules
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
        ],
      },
    ];
  },
  reactStrictMode: true,
};

export default config;
