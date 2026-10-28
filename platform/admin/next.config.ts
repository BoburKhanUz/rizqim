import path from 'node:path';
import type { NextConfig } from 'next';

// The repository root: @rizqim/ui and @rizqim/api-client are linked from packages/ts/ (file: dependencies), so the bundler
// and the standalone output tracing must be allowed to read outside this folder.
const repoRoot = path.join(__dirname, '../..');

// Content-Security-Policy (review hardening). Everything the page loads comes from its own origin: the backend only
// through the proxy (/api/backend), the Reverb socket from REVERB_HOST, known only at runtime (ws:/wss:). Scripts and
// styles allow 'unsafe-inline': the App Router writes its bootstrap and RSC payload as inline <script> tags, and a nonce
// would need per-request rendering of every page; the rest of the policy still shuts out other origins, plugins,
// <base> rewrites, form posts elsewhere and framing. `next dev` also needs eval (React's dev tooling).
const isDev = process.env.NODE_ENV === 'development';
const contentSecurityPolicy = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ''}`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob:",
  "font-src 'self' data:",
  "connect-src 'self' ws: wss:",
  "worker-src 'self' blob:",
  "manifest-src 'self'",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
].join('; ');

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
          { key: 'Content-Security-Policy', value: contentSecurityPolicy },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'X-Frame-Options', value: 'DENY' },
          { key: 'Referrer-Policy', value: 'same-origin' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=(), payment=(), usb=()' },
          { key: 'Cross-Origin-Opener-Policy', value: 'same-origin' },
          { key: 'Cross-Origin-Resource-Policy', value: 'same-origin' },
          // Ignored over plain HTTP (trials); over HTTPS the browser keeps to HTTPS for a year
          { key: 'Strict-Transport-Security', value: 'max-age=31536000' },
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
