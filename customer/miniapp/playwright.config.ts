import { defineConfig } from '@playwright/test'

const PORT = Number(process.env.E2E_PORT) || 4173
const BASE_URL = `http://127.0.0.1:${PORT}`
const isCI = Boolean(process.env.CI)

// Browser end-to-end tests of the Mini App against the production build, with the whole API mocked in the browser
// (e2e/fixtures.ts): no backend, database or Telegram is needed.
//
// CI installs Playwright's own Chromium (`npx playwright install --with-deps chromium`). Where only another Chromium
// build is around, point PW_CHROMIUM_PATH at its binary (e.g. /opt/pw-browsers/chromium-1194/chrome-linux/chrome).
export default defineConfig({
  testDir: 'e2e',
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 1 : 0,
  reporter: isCI ? [['list'], ['html', { open: 'never' }]] : 'list',
  outputDir: 'test-results',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    // The app formats amounts and dates with the browser's locale: pin it so the texts the specs read do not depend on the runner
    locale: 'en-US',
    timezoneId: 'Asia/Tashkent',
    // Telegram Mini App sized viewport
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
    launchOptions: {
      executablePath: process.env.PW_CHROMIUM_PATH || undefined,
    },
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  webServer: {
    command: `npm run build && npm run preview -- --host 127.0.0.1 --port ${PORT} --strictPort`,
    url: BASE_URL,
    reuseExistingServer: false,
    timeout: 240_000,
    env: {
      // The page calls /api/v1 on its own origin, where every request is answered by the mock
      VITE_API_URL: '/api/v1',
      // Real-time off: the screens just poll
      VITE_REVERB_APP_KEY: '',
      VITE_SENTRY_DSN: '',
      VITE_APP_DEBUG: '',
    },
  },
})
