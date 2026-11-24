import type { Page } from '@playwright/test'
import { makeUser } from '../src/test/factories'
import { addLavashToCart } from './cartFlow'
import { TELEGRAM_ID } from './fakeApi'
import { expect, installNetwork, installTelegram, relaunchAs, test, userStorageKey } from './fixtures'

// The app was last used a long time ago: its stored token no longer works. On the first 401 it signs in again with the
// Telegram launch data (one POST /auth/telegram, however many requests failed) and replays what failed.
test('an expired token is renewed once and the failed requests are replayed', async ({ page, api }) => {
  const order = api.addOrder({ status: 'new' })
  await page.addInitScript(({ key, user }) => {
    const stored = { state: { token: 'stale-token', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }
    if (localStorage.getItem(key) === null) localStorage.setItem(key, JSON.stringify(stored))
  }, { key: userStorageKey('auth-storage'), user: api.user })

  await page.goto('/orders')

  // The page works as if nothing happened
  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${order.short_code}`, { exact: true })).toBeVisible()

  // The stored token was refused first ...
  const rejected = api.calls.filter((call) => call.status === 401 && call.path !== '/auth/telegram')
  expect(rejected.length).toBeGreaterThan(0)
  expect(rejected[0].authorization).toBe('Bearer stale-token')
  // ... then exactly one new sign-in happened ...
  expect(api.callsTo('POST', '/auth/telegram')).toHaveLength(1)
  // ... and every refused request was repeated with the new token and went through. An analytics batch (on in the e2e
  // build) is not replayed by the axios interceptor: the analytics client waits for the renewed token and sends it again
  // itself, answered 202
  const analytics = rejected.filter((call) => call.path === '/analytics/events')
  if (analytics.length > 0) {
    await expect.poll(() => api.callsTo('POST', '/analytics/events').some((call) => call.status === 202 && call.authorization === 'Bearer token-1')).toBe(true)
  }
  for (const call of rejected.filter((other) => other.path !== '/analytics/events')) {
    const retry = api.calls.find(
      (other) => other.status === 200 && other.method === call.method && other.path === call.path && JSON.stringify(other.query) === JSON.stringify(call.query),
    )
    expect(retry?.authorization, `${call.method} ${call.path} was not replayed`).toBe('Bearer token-1')
  }
  expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ token: 'token-1' })
})

// The same person, a flaky network: a deep-link re-sign-in that fails keeps their own session (nobody is logged out).
test('the same Telegram user keeps the stored session when a re-sign-in fails', async ({ page, api }) => {
  const order = api.addOrder({ status: 'new' })
  api.addSession('own-token', api.user)
  api.loginFailureStatus = 500
  await page.addInitScript(({ key, user }) => {
    if (localStorage.getItem(key) === null) {
      localStorage.setItem(key, JSON.stringify({ state: { token: 'own-token', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }))
    }
    // Opened from a deep link: the app signs in again (POST /auth/telegram) instead of opening with the stored session
    const launchKey = '__telegram__initParams'
    const params = JSON.parse(sessionStorage.getItem(launchKey) ?? '{}') as { tgWebAppData?: string }
    if (params.tgWebAppData && !params.tgWebAppData.includes('start_param=')) {
      params.tgWebAppData += '&start_param=store_e2e'
      sessionStorage.setItem(launchKey, JSON.stringify(params))
    }
  }, { key: userStorageKey('auth-storage'), user: api.user })

  await page.goto('/orders')

  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${order.short_code}`, { exact: true })).toBeVisible()
  await expect(page.getByText('Kirish amalga oshmadi')).toHaveCount(0)
  // The re-sign-in was tried and failed
  expect(api.callsTo('POST', '/auth/telegram').map((call) => call.status)).toEqual([500])
  expect(api.callsTo('GET', '/orders').every((call) => call.authorization === 'Bearer own-token')).toBe(true)
})

// ---------------------------------------------------------------------------------------------------------------------
// One phone, several Telegram accounts. The Mini App's localStorage belongs to the device, not to the account, so every
// per-user store (session, cart, saved addresses) is kept under the key of the Telegram user who has the app open
// (src/lib/userStorage.ts): one account never reads or overwrites another's, and each finds its own again.

const A_ID = '777000777'
const makeUserA = () => makeUser({ id: 77, telegram_id: A_ID, name: 'Aziza Begimova', phone: '+998907777777', role: 'customer', store_id: null })
const A_HOME = { id: 9, title: 'Azizaning uyi', address: 'Yunusobod 4-kvartal, 17-uy', lat: 41.36, lng: 69.28, is_default: true, intercom: '42' }
const B_OFFICE = { id: 5, title: 'Ofis', address: 'Chilonzor 9, 14-uy', lat: 41.2856, lng: 69.2037, is_default: true }
const A_TRACES = /Aziza|Yunusobod|907777777|domofon 42/
const LEGACY_KEYS = ['auth-storage', 'cart-storage', 'address-storage']

const addressStore = (address: object) => JSON.stringify({ state: { addresses: [address], selectedAddressId: (address as { id: number }).id }, version: 1 })

async function readStored(page: Page, key: string): Promise<Record<string, unknown> | null> {
  return page.evaluate((k) => {
    const raw = localStorage.getItem(k)
    return raw === null ? null : (JSON.parse(raw) as { state: Record<string, unknown> }).state
  }, key)
}

const allStorage = (page: Page) => page.evaluate(() => ({ ...localStorage }) as Record<string, string>)

// Puts these keys into the device's localStorage once (a reload keeps what the app left there)
async function seedOnce(page: Page, entries: Record<string, string>): Promise<void> {
  await page.addInitScript((seed) => {
    if (localStorage.getItem('e2e-seeded') !== null) return
    localStorage.setItem('e2e-seeded', '1')
    for (const [key, value] of Object.entries(seed)) localStorage.setItem(key, value)
  }, entries)
}

// The tab's next load is a launch by this Telegram account (a same-origin page that is not the app, to set it on)
async function launchAs(page: Page, telegramId: string): Promise<void> {
  if (page.url() === 'about:blank') await page.goto('/favicon.svg')
  await relaunchAs(page, telegramId)
}

// What a customer leaves behind when the cart was filled and a checkout was attempted: the note and the open attempt
async function leaveNoteAndAttempt(page: Page, telegramId: string, note: string): Promise<void> {
  await page.evaluate(({ key, note }) => {
    const stored = JSON.parse(localStorage.getItem(key) ?? '{}') as { state: Record<string, unknown>; version: number }
    stored.state.specialInstructions = note
    stored.state.checkoutAttempt = { key: 'attempt-key', fingerprint: JSON.stringify({ notes: note }) }
    localStorage.setItem(key, JSON.stringify(stored))
  }, { key: userStorageKey('cart-storage', telegramId), note })
}

test('A was signed out with a cart and addresses on the device: B sees none of it, and A finds it all again', async ({ page, api }) => {
  api.addAccount(makeUserA())
  await seedOnce(page, { [userStorageKey('address-storage', A_ID)]: addressStore(A_HOME) })

  // A fills a cart, with a saved home address
  await launchAs(page, A_ID)
  await addLavashToCart(page)
  await expect(page.getByText(A_HOME.address)).toBeVisible()
  await leaveNoteAndAttempt(page, A_ID, 'Aziza: domofon 42')

  // A's session dies and cannot be renewed (initData too old, backend down): A is signed out, on the sign-in error screen
  api.expireTokens()
  api.loginFailureStatus = 500
  await page.reload()
  const alert = page.getByRole('alert')
  await expect(alert.getByText('Kirish amalga oshmadi')).toBeVisible()
  await expect(alert.getByRole('button', { name: 'Qayta urinish' })).toBeVisible()
  expect(await readStored(page, userStorageKey('auth-storage', A_ID))).toMatchObject({ token: null, user: null, isAuthenticated: false })

  // Telegram user B opens the app on the same phone
  api.loginFailureStatus = null
  await launchAs(page, TELEGRAM_ID)
  await page.goto('/cart')
  await expect(page.getByRole('heading', { name: 'Savat', level: 1 })).toBeVisible()
  await expect(page.getByText('Lavash (Standart)')).toHaveCount(0)
  await expect(page.getByText(A_TRACES)).toHaveCount(0)
  await page.goto('/profile')
  await expect(page.getByText("Sizda hali saqlangan manzillar yo'q")).toBeVisible()
  await expect(page.getByText(A_TRACES)).toHaveCount(0)
  const stored = await allStorage(page)
  for (const [key, value] of Object.entries(stored)) {
    if (!key.endsWith(`:${A_ID}`)) expect(value, key).not.toMatch(A_TRACES)
  }
  // B's cart: empty (never even saved, or saved empty)
  expect((await readStored(page, userStorageKey('cart-storage'))) ?? { items: [] }).toMatchObject({ items: [] })
  expect(JSON.stringify(await readStored(page, userStorageKey('cart-storage')))).not.toMatch(/domofon|attempt-key/)

  // A comes back: their cart, note, open checkout and home address are all there
  await launchAs(page, A_ID)
  await page.goto('/cart')
  await expect(page.getByText('Lavash (Standart)')).toBeVisible()
  await expect(page.getByText(A_HOME.address)).toBeVisible()
  expect(await readStored(page, userStorageKey('cart-storage', A_ID))).toMatchObject({
    specialInstructions: 'Aziza: domofon 42',
    checkoutAttempt: { key: 'attempt-key' },
  })
  expect(await readStored(page, userStorageKey('auth-storage', A_ID))).toMatchObject({ user: { telegram_id: A_ID } })
})

test('two accounts open at once (two tabs) keep separate carts and addresses', async ({ page, api, baseURL }) => {
  api.addAccount(makeUserA())
  await seedOnce(page, {
    [userStorageKey('address-storage', A_ID)]: addressStore(A_HOME),
    [userStorageKey('address-storage')]: addressStore(B_OFFICE),
  })

  // Tab 1: A; tab 2 (the same device storage): B
  await launchAs(page, A_ID)
  await addLavashToCart(page)
  await leaveNoteAndAttempt(page, A_ID, 'Aziza: domofon 42')

  const tabB = await page.context().newPage()
  await installTelegram(tabB, TELEGRAM_ID)
  await installNetwork(tabB, api, new URL(baseURL ?? 'http://127.0.0.1').origin)
  await tabB.goto('/cart')
  await expect(tabB.getByRole('heading', { name: 'Savat', level: 1 })).toBeVisible()
  await expect(tabB.getByText('Lavash (Standart)')).toHaveCount(0)
  await addLavashToCart(tabB)
  await expect(tabB.getByText(B_OFFICE.address)).toBeVisible()
  await expect(tabB.getByText(A_TRACES)).toHaveCount(0)
  await leaveNoteAndAttempt(tabB, TELEGRAM_ID, 'Bobur: ofis')

  // A works on in tab 1 (a write of A's stores), then both tabs reload: each sees only its own
  await page.goto('/cart')
  await expect(page.getByText(A_HOME.address)).toBeVisible()
  await tabB.reload()
  await expect(tabB.getByText(B_OFFICE.address)).toBeVisible()
  await expect(tabB.getByText(A_TRACES)).toHaveCount(0)
  await page.reload()
  await expect(page.getByText(A_HOME.address)).toBeVisible()
  await expect(page.getByText(B_OFFICE.address)).toHaveCount(0)

  expect(await readStored(page, userStorageKey('cart-storage', A_ID))).toMatchObject({ specialInstructions: 'Aziza: domofon 42' })
  expect(await readStored(page, userStorageKey('cart-storage'))).toMatchObject({ specialInstructions: 'Bobur: ofis' })
  expect(await readStored(page, userStorageKey('auth-storage', A_ID))).toMatchObject({ user: { telegram_id: A_ID } })
  expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ user: { telegram_id: TELEGRAM_ID } })
  await tabB.close()
})

test.describe('data saved before per-user storage (legacy keys)', () => {
  const legacyAuth = (user: object | null, token: string | null) =>
    JSON.stringify({ state: { token, user, store: null, isAuthenticated: user !== null, smsVerificationRequired: false }, version: 0 })
  const legacyCart = (note: string) => JSON.stringify({
    state: {
      items: [{ id: 'b0000000-0000-4000-8000-000000000001', productId: 1, variantId: 'b0000000-0000-4000-8000-000000000001', storeId: '1', name: 'Lavash', price: 25000, quantity: 3 }],
      storeName: 'Lazzat', storeAddress: 'Chilonzor', specialInstructions: note, checkoutAttempt: { key: 'old-key', fingerprint: '{}' },
    },
    version: 0,
  })

  // The old app never cleared the cart and the addresses when the account changed: whose they are is unknown, so they
  // are removed, never shown. The saved session names its user: it moves to that user's own storage.
  test("the stored session moves to its owner's storage; the cart and the addresses are removed", async ({ page, api }) => {
    api.addSession('own-token', api.user)
    const seed = { 'auth-storage': legacyAuth(api.user, 'own-token'), 'cart-storage': legacyCart('Ali: ofis'), 'address-storage': addressStore(B_OFFICE) }
    await seedOnce(page, seed)

    await page.goto('/profile')

    await expect(page.getByText("Sizda hali saqlangan manzillar yo'q")).toBeVisible()
    await expect(page.getByText(B_OFFICE.address)).toHaveCount(0)
    // The session was used as it was (no new sign-in), and nothing is left under the old keys
    expect(api.callsTo('POST', '/auth/telegram')).toHaveLength(0)
    expect(api.callsTo('GET', '/user').length).toBeGreaterThan(0)
    expect(api.callsTo('GET', '/user').every((call) => call.authorization === 'Bearer own-token')).toBe(true)
    const stored = await allStorage(page)
    for (const key of LEGACY_KEYS) expect(stored[key], key).toBeUndefined()
    expect(JSON.stringify(stored)).not.toMatch(/Ali: ofis|old-key|Chilonzor 9/)
  })

  test('a stored session of another Telegram user moves to their storage, never shown; the cart and the addresses are removed', async ({ page, api }) => {
    api.addSession('token-of-A', makeUserA())
    await seedOnce(page, {
      'auth-storage': legacyAuth(makeUserA(), 'token-of-A'),
      'cart-storage': legacyCart('Aziza: domofon 42'),
      'address-storage': addressStore(A_HOME),
    })

    await page.goto('/profile')

    await expect(page.getByText("Sizda hali saqlangan manzillar yo'q")).toBeVisible()
    await expect(page.getByText(A_TRACES)).toHaveCount(0)
    expect(api.calls.filter((call) => call.authorization === 'Bearer token-of-A')).toEqual([])
    const stored = await allStorage(page)
    for (const key of LEGACY_KEYS) expect(stored[key], key).toBeUndefined()
    // A's session waits in A's own storage, and only there
    expect(await readStored(page, userStorageKey('auth-storage', A_ID))).toMatchObject({ token: 'token-of-A', user: { telegram_id: A_ID } })
    for (const [key, value] of Object.entries(stored)) {
      if (key !== userStorageKey('auth-storage', A_ID)) expect(value, key).not.toMatch(A_TRACES)
    }
    expect(JSON.stringify(stored)).not.toMatch(/domofon|Yunusobod|old-key/)
    expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ user: { telegram_id: TELEGRAM_ID } })
  })

  test('a stored session of nobody (already signed out) is removed with the cart and the addresses', async ({ page, api }) => {
    expect(api.user.telegram_id).toBe(TELEGRAM_ID) // the fake backend (and the Telegram launch) of this test
    await seedOnce(page, {
      'auth-storage': legacyAuth(null, null),
      'cart-storage': legacyCart('Aziza: domofon 42'),
      'address-storage': addressStore(A_HOME),
    })

    await page.goto('/profile')

    await expect(page.getByText("Sizda hali saqlangan manzillar yo'q")).toBeVisible()
    const stored = await allStorage(page)
    for (const key of LEGACY_KEYS) expect(stored[key], key).toBeUndefined()
    expect(JSON.stringify(stored)).not.toMatch(A_TRACES)
    expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ user: { telegram_id: TELEGRAM_ID } })
  })
})

// A production build opened with no Telegram user (a link opened in Telegram's in-app browser, which may share the
// storage): the "open in Telegram" screen, and nothing on the device is read into the page or deleted.
for (const path of ['/', '/?dev=true']) {
  test(`no Telegram user (${path}): nothing is deleted from the device`, async ({ page }) => {
    const seed = {
      'auth-storage': JSON.stringify({ state: { token: 'legacy-token', user: makeUserA(), store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }),
      'address-storage': addressStore(A_HOME),
      [userStorageKey('cart-storage', A_ID)]: JSON.stringify({ state: { items: [], storeName: '', storeAddress: '', specialInstructions: 'Aziza: domofon 42', checkoutAttempt: null }, version: 0 }),
      [userStorageKey('address-storage', A_ID)]: addressStore(A_HOME),
    }
    await seedOnce(page, seed)
    // No launch parameters: Telegram did not open this page as a Mini App
    await page.addInitScript(() => sessionStorage.removeItem('__telegram__initParams'))

    await page.goto(path)

    if (path === '/') await expect(page.getByRole('link', { name: 'Bot orqali ochish' })).toBeVisible()
    else await expect(page.getByText('Iltimos, ilovani Telegram orqali oching')).toBeVisible()
    await expect(page.getByText(A_TRACES)).toHaveCount(0)
    const stored = await allStorage(page)
    for (const [key, value] of Object.entries(seed)) expect(stored[key], key).toBe(value)
  })
}

// The backend unlinked the account's telegram_id (an invite was claimed elsewhere) and the background GET /user brought
// that into the store: the app signs in again with initData. The cart and the addresses are this Telegram user's own.
test.describe('the stored user loses its telegram_id', () => {
  async function openWithUnlinkedRefresh(page: Page, api: import('./fakeApi').FakeApi) {
    api.addSession('own-token', { ...api.user, telegram_id: null })
    await seedOnce(page, {
      [userStorageKey('auth-storage')]: JSON.stringify({ state: { token: 'own-token', user: api.user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }),
      [userStorageKey('address-storage')]: addressStore(B_OFFICE),
      [userStorageKey('cart-storage')]: JSON.stringify({ state: { items: [], storeName: '', storeAddress: '', specialInstructions: 'Ali: ofis', checkoutAttempt: null }, version: 0 }),
    })
    await page.goto('/profile')
  }

  test('a re-sign-in signs the same person in again, and nothing of theirs is lost', async ({ page, api }) => {
    await openWithUnlinkedRefresh(page, api)

    await expect.poll(() => api.callsTo('POST', '/auth/telegram').map((call) => call.status)).toContain(200)
    await expect(page.getByText(B_OFFICE.address)).toBeVisible()
    await expect(page.getByText('Kirish amalga oshmadi')).toHaveCount(0)
    await expect.poll(() => readStored(page, userStorageKey('auth-storage'))).toMatchObject({ user: { telegram_id: TELEGRAM_ID }, isAuthenticated: true })
    expect(await readStored(page, userStorageKey('cart-storage'))).toMatchObject({ specialInstructions: 'Ali: ofis' })
    expect(await readStored(page, userStorageKey('address-storage'))).toMatchObject({ addresses: [B_OFFICE] })
  })

  test('when the re-sign-in fails: the sign-in error screen with retry, and the cart and addresses are kept', async ({ page, api }) => {
    api.loginFailureStatus = 500
    await openWithUnlinkedRefresh(page, api)

    const alert = page.getByRole('alert')
    await expect(alert.getByText('Kirish amalga oshmadi')).toBeVisible()
    await expect(alert.getByRole('button', { name: 'Qayta urinish' })).toBeVisible()
    expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ token: null, isAuthenticated: false })
    expect(await readStored(page, userStorageKey('cart-storage'))).toMatchObject({ specialInstructions: 'Ali: ofis' })
    expect(await readStored(page, userStorageKey('address-storage'))).toMatchObject({ addresses: [B_OFFICE] })
  })
})

// A session that dies and cannot be renewed ends on the sign-in error screen (with retry), never on screens that are
// silently signed out. The retry signs in again once the backend is back.
for (const [name, path, role] of [['a customer screen (axios)', '/orders', 'customer'], ['a staff screen (the staff API)', '/manager', 'manager']] as const) {
  test(`signed out on ${name}: the sign-in error screen, then the retry signs in`, async ({ page, api }) => {
    api.signInAs(role)
    const order = api.addOrder({ status: 'new' })
    api.loginFailureStatus = 500
    await seedOnce(page, {
      [userStorageKey('auth-storage')]: JSON.stringify({ state: { token: 'dead-token', user: api.user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }),
    })

    await page.goto(path)

    const alert = page.getByRole('alert')
    await expect(alert.getByText('Kirish amalga oshmadi')).toBeVisible()
    await expect(page.getByText(`#${order.short_code}`)).toHaveCount(0)
    expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ token: null, isAuthenticated: false })

    api.loginFailureStatus = null
    await alert.getByRole('button', { name: 'Qayta urinish' }).click()
    await expect(page.getByText('Kirish amalga oshmadi')).toHaveCount(0)
    await expect.poll(() => readStored(page, userStorageKey('auth-storage'))).toMatchObject({ isAuthenticated: true })
  })
}

// ---------------------------------------------------------------------------------------------------------------------
// The backend accepts a launch's initData for 10 minutes only, and a reload keeps the same initData: once a launch is
// older than that, no sign-in of this page can succeed. The fake's clock (api.now) is moved ahead to age the launch.
const ELEVEN_MINUTES = 11 * 60_000

test('a stale launch and a dead token: signed out to "close and reopen", with no request loop', async ({ page, api }) => {
  api.addOrder({ status: 'new' })
  api.now = () => Date.now() + ELEVEN_MINUTES
  await seedOnce(page, {
    [userStorageKey('auth-storage')]: JSON.stringify({ state: { token: 'dead-token', user: api.user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }),
  })

  await page.goto('/orders')

  const alert = page.getByRole('alert')
  await expect(alert.getByText('Kirish muddati tugadi')).toBeVisible()
  await expect(alert.getByRole('button', { name: 'Ilovani yopish' })).toBeVisible()
  // No reload that could not work
  await expect(alert.getByRole('button', { name: 'Qayta urinish' })).toHaveCount(0)
  expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ token: null, isAuthenticated: false })

  // The launch data was tried once at most, and nothing keeps asking
  const settled = api.calls.length
  await page.waitForTimeout(3_000)
  expect(api.calls.length).toBe(settled)
  expect(api.callsTo('POST', '/auth/telegram').length).toBeLessThanOrEqual(1)

  // "Close" closes the Mini App (Telegram's WebApp.close), to be opened again with fresh launch data
  await page.evaluate(() => {
    const app = (window as unknown as { Telegram: { WebApp: { close: () => void } } }).Telegram.WebApp
    app.close = () => {
      ;(window as unknown as { __closed?: boolean }).__closed = true
    }
  })
  await alert.getByRole('button', { name: 'Ilovani yopish' }).click()
  expect(await page.evaluate(() => (window as unknown as { __closed?: boolean }).__closed)).toBe(true)
})

test('a deep link whose re-sign-in is refused (401) keeps the valid stored session of the same person', async ({ page, api }) => {
  const order = api.addOrder({ status: 'new' })
  api.addSession('own-token', api.user)
  api.now = () => Date.now() + ELEVEN_MINUTES
  await page.addInitScript(({ key, user }) => {
    if (localStorage.getItem(key) === null) {
      localStorage.setItem(key, JSON.stringify({ state: { token: 'own-token', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }))
    }
    const launchKey = '__telegram__initParams'
    const params = JSON.parse(sessionStorage.getItem(launchKey) ?? '{}') as { tgWebAppData?: string }
    if (params.tgWebAppData && !params.tgWebAppData.includes('start_param=')) {
      params.tgWebAppData += '&start_param=store_e2e'
      sessionStorage.setItem(launchKey, JSON.stringify(params))
    }
  }, { key: userStorageKey('auth-storage'), user: api.user })

  await page.goto('/orders')

  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${order.short_code}`, { exact: true })).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)
  expect(api.callsTo('POST', '/auth/telegram').map((call) => call.status)).toEqual([401])
  expect(api.callsTo('GET', '/orders').every((call) => call.authorization === 'Bearer own-token')).toBe(true)
  expect(await readStored(page, userStorageKey('auth-storage'))).toMatchObject({ token: 'own-token', isAuthenticated: true })
})
