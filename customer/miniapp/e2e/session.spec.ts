import { makeUser } from '../src/test/factories'
import { TELEGRAM_ID } from './fakeApi'
import { expect, test } from './fixtures'

// The app was last used a long time ago: its stored token no longer works. On the first 401 it signs in again with the
// Telegram launch data (one POST /auth/telegram, however many requests failed) and replays what failed.
test('an expired token is renewed once and the failed requests are replayed', async ({ page, api }) => {
  const order = api.addOrder({ status: 'new' })
  await page.addInitScript((user) => {
    const stored = { state: { token: 'stale-token', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }
    if (localStorage.getItem('auth-storage') === null) localStorage.setItem('auth-storage', JSON.stringify(stored))
  }, api.user)

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
  // ... and every refused request was repeated with the new token and went through
  for (const call of rejected) {
    const retry = api.calls.find(
      (other) => other.status === 200 && other.method === call.method && other.path === call.path && JSON.stringify(other.query) === JSON.stringify(call.query),
    )
    expect(retry?.authorization, `${call.method} ${call.path} was not replayed`).toBe('Bearer token-1')
  }
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('auth-storage') ?? '{}').state.token)).toBe('token-1')
})

// One phone, two Telegram accounts: the Mini App's storage is the device's. User A used the app on this phone; now
// Telegram user B (the fixtures' TELEGRAM_ID) opens it. A's session must never be used, shown or sent for B, even
// while the sign-in fails; once B signs in, only B's data is there.
test('a session stored for another Telegram user is never used, not even when the sign-in fails', async ({ page, api }) => {
  const userA = makeUser({ id: 77, telegram_id: '777000777', name: 'Aziza Begimova', phone: '+998907777777', role: 'customer', store_id: null })
  api.addSession('token-of-A', userA)
  const orderOfA = api.addOrder({ user_id: 77, short_code: 'AZIZA1', status: 'new' })
  const orderOfB = api.addOrder({ short_code: 'BOBUR1', status: 'new' })
  api.loginFailureStatus = 500

  await page.addInitScript((user) => {
    if (localStorage.getItem('auth-storage') !== null) return // seed once: a reload keeps what the app left
    localStorage.setItem('auth-storage', JSON.stringify({ state: { token: 'token-of-A', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }))
    localStorage.setItem('cart-storage', JSON.stringify({
      state: {
        items: [{ id: 'b0000000-0000-4000-8000-000000000001', productId: 1, variantId: 'b0000000-0000-4000-8000-000000000001', storeId: '1', name: 'Lavash', price: 25000, quantity: 3 }],
        storeName: 'Lazzat', storeAddress: 'Chilonzor', specialInstructions: 'Aziza: domofon 42', checkoutAttempt: null,
      },
      version: 0,
    }))
    localStorage.setItem('address-storage', JSON.stringify({
      state: { addresses: [{ id: 9, title: 'Azizaning uyi', address: 'Yunusobod 4-kvartal, 17-uy', lat: 41.36, lng: 69.28, is_default: true }], selectedAddressId: 9 },
      version: 1,
    }))
  }, userA)

  await page.goto('/orders')

  // The sign-in error screen, signed out ...
  const alert = page.getByRole('alert')
  await expect(alert.getByText('Kirish amalga oshmadi')).toBeVisible()
  await expect(alert.getByRole('button', { name: 'Qayta urinish' })).toBeVisible()
  // ... with nothing of A on it ...
  await expect(page.getByText(`#${orderOfA.short_code}`)).toHaveCount(0)
  await expect(page.getByText(/Aziza|Yunusobod|907777777/)).toHaveCount(0)
  // ... nothing sent with A's token (one sign-in as the current user, and nothing else that needs a session) ...
  expect(api.calls.filter((call) => call.authorization === 'Bearer token-of-A')).toEqual([])
  expect(api.calls.filter((call) => call.authorization !== null)).toEqual([])
  expect(api.callsTo('POST', '/auth/telegram')).toHaveLength(1)
  // ... and A's session, cart and addresses are gone from the device
  const stored = await page.evaluate(() => JSON.stringify({ ...localStorage }))
  expect(stored).not.toContain('token-of-A')
  expect(stored).not.toMatch(/Aziza|Yunusobod|907777777/)

  // The backend is back: the retry signs B in, and only B's data is shown
  api.loginFailureStatus = null
  await alert.getByRole('button', { name: 'Qayta urinish' }).click()

  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${orderOfB.short_code}`, { exact: true })).toBeVisible()
  await expect(page.getByText(`#${orderOfA.short_code}`)).toHaveCount(0)
  await expect(page.getByText(/Aziza|Yunusobod|907777777/)).toHaveCount(0)
  expect(api.calls.filter((call) => call.authorization === 'Bearer token-of-A')).toEqual([])
  expect(api.callsTo('POST', '/auth/telegram')).toHaveLength(2)
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('auth-storage') ?? '{}').state.user.telegram_id)).toBe(TELEGRAM_ID)
  const cart = await page.evaluate(() => JSON.parse(localStorage.getItem('cart-storage') ?? '{}').state)
  expect(cart).toMatchObject({ items: [], specialInstructions: '' })
})

// The same person, a flaky network: a deep-link re-sign-in that fails keeps their own session (nobody is logged out).
test('the same Telegram user keeps the stored session when a re-sign-in fails', async ({ page, api }) => {
  const order = api.addOrder({ status: 'new' })
  api.addSession('own-token', api.user)
  api.loginFailureStatus = 500
  await page.addInitScript((user) => {
    if (localStorage.getItem('auth-storage') === null) {
      localStorage.setItem('auth-storage', JSON.stringify({ state: { token: 'own-token', user, store: null, isAuthenticated: true, smsVerificationRequired: false }, version: 0 }))
    }
    // Opened from a deep link: the app signs in again (POST /auth/telegram) instead of opening with the stored session
    const key = '__telegram__initParams'
    const params = JSON.parse(sessionStorage.getItem(key) ?? '{}') as { tgWebAppData?: string }
    if (params.tgWebAppData && !params.tgWebAppData.includes('start_param=')) {
      params.tgWebAppData += '&start_param=store_e2e'
      sessionStorage.setItem(key, JSON.stringify(params))
    }
  }, api.user)

  await page.goto('/orders')

  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${order.short_code}`, { exact: true })).toBeVisible()
  await expect(page.getByText('Kirish amalga oshmadi')).toHaveCount(0)
  // The re-sign-in was tried and failed
  expect(api.callsTo('POST', '/auth/telegram').map((call) => call.status)).toEqual([500])
  expect(api.callsTo('GET', '/orders').every((call) => call.authorization === 'Bearer own-token')).toBe(true)
})
