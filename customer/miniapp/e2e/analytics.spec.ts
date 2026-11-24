// Wave P, step 3: the Mini App's first-party analytics (docs/API.md "Analitika hodisalari"). A customer opens the app,
// searches, opens a store, adds a dish, opens the cart and orders; the batches the app posts to POST /analytics/events
// carry the funnel's events in order, one session, and nothing but the catalogue's fields.
import { addLavashToCart } from './cartFlow'
import type { Page } from '@playwright/test'
import { ANALYTICS_SPEC, TELEGRAM_ID, type FakeApi } from './fakeApi'
import { expect, test } from './fixtures'

const EVENT_KEYS = ['uid', 'name', 'at', 'store_id', 'product_id', 'category_id', 'props']

type WireEvent = { uid: string; name: string; at: number; props?: Record<string, unknown> } & Record<string, unknown>

const sentNames = (api: FakeApi) => api.analyticsBatches.flatMap((batch) => (batch.events as WireEvent[]).map((e) => e.name))
// The page-hide flush (fetch keepalive), fired by hand: the real one of a reload is sent while the page unloads, and
// Playwright does not route a request of a page that is going away
const hidePage = (page: Page) => page.evaluate(() => window.dispatchEvent(new Event('pagehide')))

test('the funnel of a pickup order is posted in batches with only the allowed keys', async ({ page, api }) => {
  // A plain customer: a global account, tied to no store (the server and the app track nobody else)
  api.signInAs('customer')

  // A search on the home screen: only its length and result count may leave the page
  await page.goto('/')
  await page.getByPlaceholder('Taom yoki oshxona qidirish').fill('evos')
  await expect(page.getByRole('heading', { name: 'Evos', level: 3 })).toBeVisible()
  // Sent on a page hide before the reload below; the search event itself waits for the typing to settle (1 s), hence
  // the poll
  await expect.poll(async () => {
    await hidePage(page)
    return sentNames(api)
  }).toContain('search')

  // addLavashToCart opens "/" again: a reload in the same session
  await addLavashToCart(page)
  await page.getByRole('button', { name: /Olib ketish/ }).click()
  await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  expect(api.orders).toHaveLength(1)

  // order_placed is sent at once, not on the 10 s tick
  await expect.poll(() => sentNames(api), { timeout: 15_000 }).toContain('order_placed')

  const batches = api.analyticsBatches
  const events = batches.flatMap((batch) => batch.events as WireEvent[])
  const names = events.map((event) => event.name)
  const funnel = ['app_open', 'search', 'store_view', 'add_to_cart', 'cart_view', 'checkout_start', 'order_placed']
  expect(names.filter((name) => funnel.includes(name))).toEqual(funnel)
  expect(names.filter((name) => name === 'app_open')).toHaveLength(1) // the reload did not open the session again

  // The envelope: one session, Telegram's platform, the build id, the start param never invented
  expect(new Set(batches.map((batch) => batch.session_id)).size).toBe(1)
  for (const batch of batches) {
    expect(Object.keys(batch).every((key) => ['session_id', 'platform', 'app_version', 'start_param', 'events'].includes(key))).toBe(true)
    expect(batch.platform).toBe('web')
    expect(batch.app_version).toBe('abc1234e2e')
    expect(batch).not.toHaveProperty('start_param')
    expect((batch.events as unknown[]).length).toBeLessThanOrEqual(50)
  }

  // Every event: a uid of its own, integer time, its required ids and props, only its allowed ones; the server would
  // have taken every one of them (the fake drops what the real allowlist drops)
  expect(api.analyticsDropped).toEqual([])
  expect(new Set(events.map((event) => event.uid)).size).toBe(events.length)
  for (const event of events) {
    const spec = ANALYTICS_SPEC[event.name]
    expect(spec, event.name).toBeDefined()
    expect(Number.isInteger(event.at)).toBe(true)
    for (const key of Object.keys(event)) {
      expect(EVENT_KEYS.includes(key), `${event.name}.${key}`).toBe(true)
      if (['store_id', 'product_id', 'category_id'].includes(key)) {
        expect(key in spec.ids, `${event.name}.${key}`).toBe(true)
        expect(Number.isInteger(event[key])).toBe(true)
      }
    }
    for (const [key, required] of Object.entries(spec.ids)) if (required) expect(Number.isInteger(event[key]), `${event.name}.${key} required`).toBe(true)
    for (const key of Object.keys(event.props ?? {})) expect(key in spec.props, `${event.name}.props.${key}`).toBe(true)
    for (const [key, required] of Object.entries(spec.props)) if (required) expect(event.props?.[key], `${event.name}.props.${key} required`).toBeDefined()
  }

  const byName = (name: string) => events.find((event) => event.name === name)!
  expect(byName('app_open').props).toEqual({ start_kind: 'none' })
  expect(byName('search').props).toEqual({ results: expect.any(Number), length: 4 })
  expect(byName('store_view')).toMatchObject({ store_id: 1 })
  expect(byName('add_to_cart')).toMatchObject({ store_id: 1, product_id: expect.any(Number), props: { qty: 1 } })
  expect(byName('checkout_start').props).toMatchObject({ payment_method: 'cash', order_type: 'pickup', items: 1, value: 25000 })
  expect(byName('order_placed').props).toEqual({ order_uuid: api.orders[0].uuid })

  // Nothing about the person or what they typed
  const raw = JSON.stringify(batches)
  for (const secret of ['evos', 'Evos', 'Ali', '998901234567', TELEGRAM_ID, 'Lavash']) expect(raw).not.toContain(secret)

  // Sent with the customer's token, one request at a time, and every batch was taken
  const posts = api.callsTo('POST', '/analytics/events')
  expect(posts.every((call) => call.status === 202)).toBe(true)
  expect(posts.every((call) => /^Bearer /.test(call.authorization ?? ''))).toBe(true)
})

test('staff send nothing and leave no session record', async ({ page, api }) => {
  api.signInAs('manager')
  await page.goto('/')
  await expect(page).toHaveURL(/\/manager$/)
  // A page hide sends at once whatever is queued for a customer; for staff nothing is queued at all
  await hidePage(page)
  await page.waitForTimeout(1_000)
  expect(api.callsTo('POST', '/analytics/events')).toEqual([])
  expect(await page.evaluate(() => sessionStorage.getItem('rz_session'))).toBeNull()
})

test('a relaunch from a start link in the same tab is a new session; the raw start_param never leaves the page', async ({ page, api }) => {
  api.signInAs('customer')
  await page.goto('/')
  await expect(page.getByPlaceholder('Taom yoki oshxona qidirish')).toBeVisible()
  await expect.poll(async () => {
    await hidePage(page)
    return sentNames(api)
  }).toContain('app_open')

  // Telegram Web opens the app again in the same tab (sessionStorage survives): new launch data with a referral link
  await page.evaluate((telegramId) => {
    const user = { id: Number(telegramId), first_name: 'Ali', username: 'ali', language_code: 'uz' }
    // A new launch: launched a little later than the first one (and within the 10 minutes the server accepts)
    const authDate = Math.floor(Date.now() / 1000) + 1
    const initData = `query_id=E2E2&user=${encodeURIComponent(JSON.stringify(user))}&start_param=ref_424242&auth_date=${authDate}&hash=e2e2`
    sessionStorage.setItem('__telegram__initParams', JSON.stringify({ tgWebAppData: initData, tgWebAppVersion: '8.0', tgWebAppPlatform: 'web', tgWebAppStartParam: 'ref_424242' }))
  }, TELEGRAM_ID)
  await page.reload()
  await expect(page.getByPlaceholder('Taom yoki oshxona qidirish')).toBeVisible()
  await expect.poll(async () => {
    await hidePage(page)
    return sentNames(api).filter((name) => name === 'app_open').length
  }).toBe(2)

  const batches = api.analyticsBatches
  const opens = batches.flatMap((batch) => (batch.events as WireEvent[]).filter((e) => e.name === 'app_open').map((e) => ({ session: batch.session_id, props: e.props })))
  expect(opens[0].props).toEqual({ start_kind: 'none' })
  expect(opens[1].props).toEqual({ start_kind: 'referral' })
  expect(opens[1].session).not.toBe(opens[0].session)
  for (const batch of batches) expect(batch).not.toHaveProperty('start_param')
  expect(JSON.stringify(batches)).not.toContain('424242')
  expect(await page.evaluate(() => sessionStorage.getItem('rz_session'))).not.toContain('424242')
  expect(api.analyticsDropped).toEqual([])

  // A plain reload of that launch stays in its session
  await page.reload()
  await expect(page.getByPlaceholder('Taom yoki oshxona qidirish')).toBeVisible()
  await hidePage(page)
  await page.waitForTimeout(500)
  expect(sentNames(api).filter((name) => name === 'app_open')).toHaveLength(2)
})
