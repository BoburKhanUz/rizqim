import type { BrowserContext, Page } from '@playwright/test'
import { OWNER, type Account } from './fake/backend'
import { expect, openSignedIn, test } from './fixtures'

// Wave M2 on the wire: the board's polls are conditional GETs and its real-time events carry change tokens.
//
// 1. The panel keeps no ETag store of its own: the browser's HTTP cache does it. The fake answers the polled reads
//    with an ETag and `Cache-Control: private, no-cache`, as the backend does (docs/API.md 7.1); the test reads what
//    the browser really sent (If-None-Match) and what came back (304) at the server. Playwright turns the HTTP cache
//    off for a page with request routing, so this test signs in without the `api` fixture (no page.route) and without
//    the Reverb mock: the socket fails and the board polls every 15 s, which a controlled clock fast-forwards.
// 2. A change token the board already read fetches nothing; a gap or a heartbeat ahead reads the board again.

async function signInWithoutRouting(context: BrowserContext, fake: { backend: { seedSession: (account: Account) => { refreshToken: string; deviceUuid: string } } }, account: Account = OWNER) {
  const { refreshToken, deviceUuid } = fake.backend.seedSession(account)
  const base = { domain: '127.0.0.1', path: '/api/session', httpOnly: true, sameSite: 'Strict' as const, secure: false }
  await context.addCookies([
    { ...base, name: 'rz_refresh', value: refreshToken },
    { ...base, name: 'rz_device', value: deviceUuid },
  ])
}

const boardCard = (page: Page, code: string) => page.getByRole('article', { name: `#${code}` })

test('a repeated poll of the unchanged board is a 304 on the wire and the board keeps its orders', async ({ page, context, fake }) => {
  fake.backend.orders.add({ short_code: 'E1' })
  await signInWithoutRouting(context, fake)
  await page.clock.install()
  await page.goto('/orders')
  await expect(boardCard(page, 'E1')).toBeVisible()

  const lists = () => fake.conditional.filter((exchange) => exchange.path === '/v1/staff/orders')
  const firstReads = lists().length
  expect(firstReads).toBeGreaterThan(0)
  expect(lists().every((exchange) => exchange.status === 200)).toBe(true)

  // The next poll (15 s while the socket is down) of the same lists: the browser revalidates, the server says 304
  await page.clock.fastForward(16_000)
  await expect.poll(() => lists().filter((exchange) => exchange.status === 304).length).toBeGreaterThan(0)
  const revalidated = lists().slice(firstReads)
  expect(revalidated.length).toBeGreaterThan(0)
  for (const exchange of revalidated) {
    expect(exchange.ifNoneMatch, `${exchange.path}${exchange.search} sent If-None-Match`).toMatch(/^(W\/)?"e2e\./)
    expect(exchange.status, `${exchange.path}${exchange.search}`).toBe(304)
  }
  // The screen still shows what it had (the browser answered the 304 with the stored body)
  await expect(boardCard(page, 'E1')).toBeVisible()

  // Something changed: the next poll is a 200 with the new body, never the stored one
  fake.backend.orders.add({ short_code: 'E2' })
  const before = lists().length
  await page.clock.fastForward(16_000)
  await expect(boardCard(page, 'E2')).toBeVisible()
  expect(lists().slice(before).some((exchange) => exchange.status === 200 && exchange.ifNoneMatch !== null)).toBe(true)
})

test.describe('change tokens of the real-time events', () => {
  const manager: Account = { ...OWNER, id: 9, name: 'Dilshod', role: 'manager', branchId: 1, branchName: 'Chilonzor', permissions: ['orders.view', 'orders.manage'] }
  const liveStatus = (page: Page) => page.locator('span[data-status]').first()

  // Fix round 2: the reads a gap or a heartbeat ahead ask for are paced (one open-list read, at most every 30 s, after
  // a 0-5 s spread), so a heartbeat right after a gap's read would wait for the window: two tests
  const orderEvent = (rev: string) => ({ order: { uuid: 'x', status: 'accepted', store_id: 1, branch_id: 1, courier_id: null, updated_at: 't' }, rev: { 'pos-branch.1': rev } })

  test('a token the board read fetches nothing; a gap reads the open list again', async ({ page, api, signIn, reverb }) => {
    api.orders.add({ short_code: 'T1', branch_id: 1 })
    await openSignedIn(page, signIn, '/orders', manager)
    await expect(liveStatus(page)).toHaveAttribute('data-status', 'live')
    await expect(boardCard(page, 'T1')).toBeVisible()
    expect(reverb.subscribed).toContain('private-pos-branch.1')
    const reads = () => api.callsTo('GET', /^\/v1\/staff\/orders/).length
    const listReads = () => api.callsTo('GET', '/v1/staff/orders').length
    const held = api.revisionOf(1)

    // An event of a change the board already read (its token is the one the read named): no request
    await page.waitForTimeout(500)
    const settled = reads()
    reverb.emit('private-pos-branch.1', 'order.status_changed', orderEvent(held))
    await page.waitForTimeout(1_000)
    expect(reads()).toBe(settled)

    // An event two changes ahead: one was lost, the board reads its open list again
    const listsBefore = listReads()
    api.branchRevisions.set(1, Number(held) + 2)
    reverb.emit('private-pos-branch.1', 'order.status_changed', orderEvent(String(Number(held) + 2)))
    await expect.poll(listReads).toBeGreaterThan(listsBefore)
  })

  test('a heartbeat ahead of the last read reads the open list again', async ({ page, api, signIn, reverb }) => {
    api.orders.add({ short_code: 'T2', branch_id: 1 })
    await openSignedIn(page, signIn, '/orders', manager)
    await expect(liveStatus(page)).toHaveAttribute('data-status', 'live')
    await expect(boardCard(page, 'T2')).toBeVisible()
    const listReads = () => api.callsTo('GET', '/v1/staff/orders').length
    const held = api.revisionOf(1)

    // A heartbeat naming the token the board read: nothing
    await page.waitForTimeout(500)
    const settled = listReads()
    reverb.emit('private-pos-branch.1', 'realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.1': held } })
    await page.waitForTimeout(1_000)
    expect(listReads()).toBe(settled)

    // A change without an event: read again
    api.branchRevisions.set(1, Number(held) + 1)
    reverb.emit('private-pos-branch.1', 'realtime.heartbeat', { at: 't', interval: 30, rev: { 'pos-branch.1': String(Number(held) + 1) } })
    await expect.poll(listReads).toBeGreaterThan(settled)
  })
})
