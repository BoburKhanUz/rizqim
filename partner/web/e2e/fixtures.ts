// Playwright fixtures shared by the specs: a page whose backend is the fake (e2e/fake), whose real-time socket is the
// FakeReverb, and which can start signed in.
import { test as base, expect, type BrowserContext, type Page } from '@playwright/test'
import { FakeBackend, OWNER, type Account } from './fake/backend'
import { FakeReverb } from './fake/reverb'
import { FakeServer } from './fake/server'
import { FAKE_PORT, FAKE_URL, WEB_URL } from './ports'

export { expect, OWNER }
export type { Account }

interface Fixtures {
  /** The fake backend of this test (a fresh one): seed it before page.goto(), read what the page sent after. */
  api: FakeBackend
  /** The fake Reverb socket of the page. */
  reverb: FakeReverb
  /** Signs the browser in as `account` the way an earlier visit would have (the refresh cookie), before the page loads. */
  signIn: (account?: Account) => Promise<void>
  fake: FakeServer
}

interface WorkerFixtures {
  fakeServer: FakeServer
}

async function setSessionCookies(context: BrowserContext, refreshToken: string, deviceUuid: string): Promise<void> {
  const base = { domain: '127.0.0.1', path: '/api/session', httpOnly: true, sameSite: 'Strict' as const, secure: false }
  await context.addCookies([
    { ...base, name: 'rz_refresh', value: refreshToken },
    { ...base, name: 'rz_device', value: deviceUuid },
  ])
}

export const test = base.extend<Fixtures, WorkerFixtures>({
  fakeServer: [
    async ({}, provide) => {
      const server = new FakeServer()
      await server.start(FAKE_PORT)
      await provide(server)
      await server.stop()
    },
    { scope: 'worker', auto: true },
  ],

  fake: async ({ fakeServer }, provide) => {
    fakeServer.reset()
    await provide(fakeServer)
  },

  api: async ({ fake, page }, provide) => {
    const pageErrors: string[] = []
    page.on('pageerror', (error) => pageErrors.push(error.message))

    // Nothing but the panel and the fake is reachable: anything else the page asks for (fonts, tiles, scripts) is answered empty
    await page.route(
      (url) => url.origin !== new URL(WEB_URL).origin && url.origin !== FAKE_URL,
      (route) => route.fulfill({ status: 204 }),
    )

    await provide(fake.backend)

    // A request the fake has no endpoint for, or an exception nobody caught, is a failure even if the assertions passed
    expect(fake.backend.unhandled, 'API calls the e2e fake does not implement').toEqual([])
    expect(pageErrors, 'uncaught errors in the page').toEqual([])
  },

  reverb: async ({ page, api }, provide) => {
    void api
    const reverb = new FakeReverb()
    await reverb.install(page)
    await provide(reverb)
  },

  signIn: async ({ context, api }, provide) => {
    await provide(async (account = OWNER) => {
      const { refreshToken, deviceUuid } = api.seedSession(account)
      await setSessionCookies(context, refreshToken, deviceUuid)
    })
  },
})

/** The page, signed in and on `path` (the panel is shown once the session is restored). */
export async function openSignedIn(page: Page, signIn: Fixtures['signIn'], path: string, account?: Account): Promise<void> {
  await signIn(account)
  await page.goto(path)
}
