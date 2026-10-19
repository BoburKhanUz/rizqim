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
  await expect(active.getByText(`#${order.uuid.slice(0, 8).toUpperCase()}`)).toBeVisible()

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
