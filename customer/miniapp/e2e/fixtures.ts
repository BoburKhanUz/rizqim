// Playwright fixtures shared by the specs: a page whose API is a FakeApi (e2e/fakeApi.ts) and which opens inside a
// (fake) Telegram launch, so the app signs in through its normal /auth/telegram flow.
import { test as base, expect, type Page, type Request } from '@playwright/test'
import { FakeApi, TELEGRAM_ID } from './fakeApi'

export { expect }

// What Telegram hands the Mini App on launch (the tgWebAppData launch parameter, an initData query string).
// The backend is the only one that verifies the hash, and here the backend is the fake.
function buildInitData(): string {
  const user = { id: Number(TELEGRAM_ID), first_name: 'Ali', username: 'ali', language_code: 'uz' }
  return `query_id=E2E&user=${encodeURIComponent(JSON.stringify(user))}&auth_date=1790000000&hash=e2e`
}

async function installTelegram(page: Page): Promise<void> {
  // The app bundles Telegram's own web-app script (@twa-dev/sdk), which builds window.Telegram.WebApp from the launch
  // parameters it finds in sessionStorage. Seeding them there is what opening the Mini App from Telegram amounts to.
  await page.addInitScript((initData) => {
    const key = '__telegram__initParams'
    if (sessionStorage.getItem(key) === null) {
      sessionStorage.setItem(key, JSON.stringify({ tgWebAppData: initData, tgWebAppVersion: '8.0', tgWebAppPlatform: 'web' }))
    }
  }, buildInitData())
}

function jsonBody(request: Request): Record<string, unknown> | null {
  try {
    return request.postDataJSON() as Record<string, unknown> | null
  } catch {
    return null // no body, or not JSON
  }
}

async function installNetwork(page: Page, api: FakeApi, origin: string): Promise<void> {
  // Nothing but the app itself and the mocked API is reachable: external images, tiles and scripts are answered empty
  // (they are not what these tests are about, and the CI runner may be offline from them anyway)
  await page.route((url) => url.origin !== origin, (route) => {
    const isScript = route.request().resourceType() === 'script'
    return route.fulfill(isScript ? { status: 200, contentType: 'application/javascript', body: '' } : { status: 204 })
  })

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const result = api.handle(
      request.method(),
      url.pathname.replace(/^\/api\/v1/, ''),
      Object.fromEntries(url.searchParams),
      jsonBody(request),
      await request.headerValue('authorization'),
      await request.headerValue('idempotency-key'),
    )
    await route.fulfill({ status: result.status, contentType: 'application/json', body: JSON.stringify(result.body) })
  })
}

interface Fixtures {
  /** The fake backend of this test: seed it (api.addOrder(...), api.signInAs(...)) before page.goto(). */
  api: FakeApi
}

export const test = base.extend<Fixtures>({
  api: async ({ page, baseURL }, use) => {
    const api = new FakeApi()
    const pageErrors: string[] = []
    page.on('pageerror', (error) => pageErrors.push(error.message))

    await installTelegram(page)
    await installNetwork(page, api, new URL(baseURL ?? 'http://127.0.0.1').origin)

    await use(api)

    // A request the fake has no endpoint for, or an exception nobody caught, is a failure even if the assertions passed
    expect(api.unhandled, 'API calls the e2e fake does not implement').toEqual([])
    expect(pageErrors, 'uncaught errors in the page').toEqual([])
  },
})
