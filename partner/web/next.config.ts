import path from 'node:path';
import type { NextConfig } from 'next';

// The repository root: @rizqim/ui and @rizqim/api-client are linked from packages/ts/ (file: dependencies), so the
// bundler and the standalone output tracing must be allowed to read outside this folder.
const repoRoot = path.join(__dirname, '../..');

const config: NextConfig = {
  // A self-contained server for the Docker image (Dockerfile): .next/standalone/partner/web/server.js
  output: 'standalone',
  outputFileTracingRoot: repoRoot,
  // @rizqim/ui ships TypeScript source (no build step): Next compiles it with the app
  transpilePackages: ['@rizqim/ui'],
  turbopack: {
    root: repoRoot,
    // The linked @rizqim/ui sources import react, react-dom and lucide-react from their real location, where there is
    // no node_modules: resolve them to this app's copies (one React for the whole page)
    resolveAlias: {
      react: './node_modules/react',
      'react-dom': './node_modules/react-dom',
      'lucide-react': './node_modules/lucide-react',
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
