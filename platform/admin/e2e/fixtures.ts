// Playwright fixtures shared by the specs: a page whose backend is the fake (e2e/fake), and a way to start signed in.
import { test as base, expect, type BrowserContext, type Page } from '@playwright/test'
import { FakeBackend, VALID_CODE } from './fake/backend'
import { FakeServer } from './fake/server'
import { FAKE_PORT, FAKE_URL, WEB_URL } from './ports'

export { expect, VALID_CODE }

interface Fixtures {
  /** The fake backend of this test (a fresh one): seed it before page.goto(), read what the page sent after. */
  api: FakeBackend
  /** Signs the browser in as the member the way an earlier visit would have (the session cookie), before the page loads. */
  signIn: (email?: string, options?: { confirmed?: boolean }) => Promise<string>
  fake: FakeServer
}

interface WorkerFixtures {
  fakeServer: FakeServer
}

export const SESSION_COOKIE = 'rz_admin'

export async function setSessionCookie(context: BrowserContext, token: string): Promise<void> {
  await context.addCookies([{ name: SESSION_COOKIE, value: token, domain: '127.0.0.1', path: '/', httpOnly: true, sameSite: 'Strict', secure: false }])
}

// Nothing but the app and the fake is reachable: anything else the page asks for (fonts) is answered empty
export async function isolate(page: Page): Promise<void> {
  await page.route(
    (url) => url.origin !== new URL(WEB_URL).origin && url.origin !== FAKE_URL,
    (route) => route.fulfill({ status: 204 }),
  )
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
    await isolate(page)

    await provide(fake.backend)

    // A request the fake has no endpoint for, or an exception nobody caught, is a failure even if the assertions passed
    expect(fake.backend.unhandled, 'API calls the e2e fake does not implement').toEqual([])
    expect(pageErrors, 'uncaught errors in the page').toEqual([])
  },

  signIn: async ({ context, api }, provide) => {
    await provide(async (email = 'aziza@rizqim.uz', options = {}) => {
      const token = api.seedSession(email, options)
      await setSessionCookie(context, token)
      return token
    })
  },
})

/** The page, signed in and on `path`. */
export async function openSignedIn(page: Page, signIn: Fixtures['signIn'], path: string, email?: string, options?: { confirmed?: boolean }): Promise<void> {
  await signIn(email, options)
  await page.goto(path)
}
