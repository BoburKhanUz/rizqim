// Wave M2 on the wire: the Mini App's polls are conditional GETs, made by the browser's HTTP cache (docs/API.md 7.1).
//
// The app keeps no ETag store of its own; this proves it does not need one. Playwright switches the HTTP cache off for a
// page with request routing, so here the fake API is not answered inside the page (fixtures.ts) but by a real HTTP
// server behind the preview server's /api proxy (playwright.config.ts API_PROXY_TARGET), which answers the polled reads
// as ConditionalGet does: an ETag and `Cache-Control: private, no-cache` on the 200, a 304 without a body when
// If-None-Match holds the current tag. The test reads what the browser sent and what it got at that server. Both HTTP
// layers of the app are covered: axios (the customer's GET /orders, GET /notifications) and the fetch of the staff
// screens (@rizqim/staff, GET /staff/orders).
import { createHash } from 'node:crypto'
import { createServer, type Server } from 'node:http'
import { expect, test } from '@playwright/test'
import { FakeApi } from './fakeApi'
import { installTelegram } from './fixtures'
import { E2E_API_PORT } from '../playwright.config'

const CONDITIONAL = [/^\/orders$/, /^\/notifications$/, /^\/staff\/orders(\/[^/]+)?$/, /^\/catalog(\/availability)?$/, /^\/stores$/]

interface Exchange {
  path: string
  search: string
  ifNoneMatch: string | null
  status: number
}

let server: Server
let api: FakeApi
let exchanges: Exchange[] = []

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
  server = createServer((req, res) => {
    const chunks: Buffer[] = []
    req.on('data', (chunk: Buffer) => chunks.push(chunk))
    req.on('end', () => {
      const url = new URL(req.url ?? '/', 'http://fake.test')
      const path = url.pathname.replace(/^\/api\/v1/, '')
      let body: Record<string, unknown> | null
      try {
        body = chunks.length > 0 ? (JSON.parse(Buffer.concat(chunks).toString('utf8')) as Record<string, unknown>) : null
      } catch {
        body = null
      }
      const header = (name: string) => {
        const value = req.headers[name]
        return typeof value === 'string' ? value : null
      }
      const result = api.handle(req.method ?? 'GET', path, Object.fromEntries(url.searchParams), body, header('authorization'), header('idempotency-key'))
      const raw = JSON.stringify(result.body)
      const headers: Record<string, string> = { 'Content-Type': 'application/json' }
      if (req.method === 'GET' && result.status === 200 && CONDITIONAL.some((pattern) => pattern.test(path))) {
        const tag = `"e2e.${createHash('sha256').update(`${header('authorization') ?? ''}\n${url.pathname}${url.search}\n${raw}`).digest('base64url').slice(0, 22)}"`
        const sent = header('if-none-match')
        const matched = sent !== null && sent.split(',').some((candidate) => candidate.trim().replace(/^W\//, '') === tag)
        exchanges.push({ path, search: url.search, ifNoneMatch: sent, status: matched ? 304 : 200 })
        if (matched) {
          res.writeHead(304, { ETag: tag, 'Cache-Control': 'private, no-cache' }).end()
          return
        }
        headers.ETag = tag
        headers['Cache-Control'] = 'private, no-cache'
      }
      res.writeHead(result.status, headers).end(raw)
    })
  })
  await new Promise<void>((resolve, reject) => {
    server.once('error', reject)
    server.listen(E2E_API_PORT, '127.0.0.1', resolve)
  })
})

test.afterAll(async () => {
  server.closeAllConnections()
  await new Promise<void>((resolve) => server.close(() => resolve()))
})

test.beforeEach(async ({ page }) => {
  api = new FakeApi()
  exchanges = []
  await installTelegram(page)
})

test.afterEach(() => {
  expect(api.unhandled, 'API calls the e2e fake does not implement').toEqual([])
})

const of = (path: string, scope?: string) =>
  exchanges.filter((exchange) => exchange.path === path && (scope === undefined || new URLSearchParams(exchange.search).get('scope') === scope))

test('customer (axios): the repeated poll of unchanged orders is a 304 and the order stays on screen', async ({ page }) => {
  api.addOrder({ status: 'preparing', short_code: 'C304' })
  await page.clock.install()
  await page.goto('/orders')
  await expect(page.getByText('#C304')).toBeVisible()
  const first = of('/orders', 'active').length
  expect(first).toBeGreaterThan(0)
  expect(of('/orders', 'active')[0]?.status).toBe(200)

  // The customer's open orders poll every 10 s (real-time is off here)
  await page.clock.fastForward(11_000)
  await expect.poll(() => of('/orders', 'active').length).toBeGreaterThan(first)
  for (const exchange of of('/orders', 'active').slice(first)) {
    expect(exchange.ifNoneMatch).toMatch(/^(W\/)?"e2e\./)
    expect(exchange.status).toBe(304)
  }
  await expect(page.getByText('#C304')).toBeVisible()

  // A change is a 200 with the new body, never the stored one
  api.orders[0]!.status = 'ready'
  const before = of('/orders', 'active').length
  await page.clock.fastForward(11_000)
  await expect.poll(() => of('/orders', 'active').slice(before).some((exchange) => exchange.status === 200 && exchange.ifNoneMatch !== null)).toBe(true)
  await expect(page.getByText('Tayyor').first()).toBeVisible()
})

test('staff (fetch of @rizqim/staff): the repeated poll of an unchanged board is a 304 and the card stays', async ({ page }) => {
  api.signInAs('manager')
  api.addOrder({ status: 'new', short_code: 'M304' })
  await page.clock.install()
  await page.goto('/manager')
  const card = page.getByRole('article', { name: 'Buyurtma #M304' })
  await expect(card).toBeVisible()
  const first = of('/staff/orders').length
  expect(first).toBeGreaterThan(0)

  await page.clock.fastForward(9_000) // the manager's board polls every 8 s
  await expect.poll(() => of('/staff/orders').slice(first).filter((exchange) => exchange.status === 304).length).toBeGreaterThan(0)
  for (const exchange of of('/staff/orders').slice(first)) {
    expect(exchange.ifNoneMatch, `${exchange.path}${exchange.search}`).toMatch(/^(W\/)?"e2e\./)
    expect(exchange.status, `${exchange.path}${exchange.search}`).toBe(304)
  }
  await expect(card).toBeVisible()
})

test("home: the bell's poll and the marketplace reads revalidate", async ({ page }) => {
  await page.clock.install()
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Lavash' })).toBeVisible()
  // The bell's notifications poll (15 s) is conditional too
  await page.clock.fastForward(16_000)
  await expect.poll(() => of('/notifications').filter((exchange) => exchange.status === 304).length).toBeGreaterThan(0)

  const feed = of('/catalog').filter((exchange) => new URLSearchParams(exchange.search).get('view') === 'cards')
  expect(feed.length).toBe(1)
  expect(feed[0]?.status).toBe(200)
  // The app is opened again (a new launch from Telegram): the browser asks with the stored tag and gets a 304
  await page.reload()
  await expect(page.getByRole('heading', { name: 'Lavash' })).toBeVisible()
  const again = of('/catalog').filter((exchange) => new URLSearchParams(exchange.search).get('view') === 'cards')
  expect(again.length).toBe(2)
  expect(again[1]?.ifNoneMatch).toMatch(/^(W\/)?"e2e\./)
  expect(again[1]?.status).toBe(304)
})
