import { defineConfig } from '@playwright/test'

// The cross-app test runs against the real stack that scripts/e2e-stack.sh starts (Laravel + Postgres + Reverb, partner web,
// Mini App). One worker, one test file in order: the specs share the seeded database and tell its story step by step.
// Where only another Chromium build is around, point PW_CHROMIUM_PATH at its binary.
const isCI = Boolean(process.env.CI)

export default defineConfig({
  testDir: 'tests',
  fullyParallel: false,
  workers: 1,
  forbidOnly: isCI,
  // A second attempt would meet the first one's data: failures are investigated, not retried
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: isCI ? [['list'], ['html', { open: 'never' }]] : 'list',
  outputDir: 'test-results',
  use: {
    // A click or fill on an element that never shows up fails after this, not after the whole (long) test's timeout
    actionTimeout: 30_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'en-US',
    timezoneId: 'Asia/Tashkent',
    launchOptions: {
      executablePath: process.env.PW_CHROMIUM_PATH || undefined,
    },
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
})
