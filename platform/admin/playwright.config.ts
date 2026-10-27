import { defineConfig } from '@playwright/test'
import { FAKE_URL, WEB_PORT, WEB_URL } from './e2e/ports'

// Browser end-to-end tests of the control center: the production build (next start) in Chromium, against a fake backend that
// lives in the test worker (e2e/fake) and answers over HTTP the Next server's proxy (app/api/backend). No Laravel or database
// is started. (The cross-app test with the real backend is e2e/stack at the repository root.)
//
// One worker: the fake backend is one process-wide state that every test resets, and the app server is shared.
// Where only another Chromium build is around, point PW_CHROMIUM_PATH at its binary
// (e.g. /opt/pw-browsers/chromium-1194/chrome-linux/chrome). E2E_SKIP_BUILD=1 reuses the last `next build`.
const isCI = Boolean(process.env.CI)

export default defineConfig({
  testDir: 'e2e',
  testMatch: '**/*.spec.ts',
  fullyParallel: false,
  workers: 1,
  forbidOnly: isCI,
  retries: isCI ? 1 : 0,
  reporter: isCI ? [['list'], ['html', { open: 'never' }]] : 'list',
  outputDir: 'test-results',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: WEB_URL,
    trace: 'on-first-retry',
    // The app formats dates with the browser's locale and time zone: pin them so the texts the specs read do not depend on the runner
    locale: 'en-US',
    timezoneId: 'Asia/Tashkent',
    viewport: { width: 1360, height: 900 },
    launchOptions: {
      executablePath: process.env.PW_CHROMIUM_PATH || undefined,
    },
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  webServer: {
    command: `${process.env.E2E_SKIP_BUILD ? '' : 'npm run build && '}npx next start -H 127.0.0.1 -p ${WEB_PORT}`,
    url: `${WEB_URL}/login`,
    reuseExistingServer: !isCI && Boolean(process.env.E2E_REUSE),
    timeout: 300_000,
    env: {
      API_BASE_URL: `${FAKE_URL}/api`,
      SESSION_COOKIE_SECURE: 'false',
    },
  },
})
