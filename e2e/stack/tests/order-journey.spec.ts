import { expect, test, type BrowserContext, type Locator, type Page } from '@playwright/test'
import { Kitchen, logSize, placeCustomerOrder, signedInitData, smsCodeFor, type PlacedOrder } from '../support/backend'
import { loadSeed, stack, type Seed } from '../support/env'

// P2-31: one order across the whole stack, no mocks: a customer's order (placed through the API, as the Mini App does) shows
// up on the partner web board; the owner accepts it there; the cook, signed in on the paired kitchen terminal with a PIN,
// bumps the lines through the staff API (as the KDS app does); the board shows the kitchen's progress and the order turns
// ready; the manager's Mini App staff screen shows the same, and the owner hands the order over on the board.
//
// Real-time (Reverb) and polling both carry the updates. The board polls every 60 s while its socket is up (15 s without),
// so seeing a change within SOCKET_MS proves the event came over the socket; if it does not arrive in time, the helper
// below falls back to waiting for the poll (and notes it in the report as an annotation) rather than failing the whole
// journey on a timing hiccup of the socket. The socket itself is asserted separately and strictly.

const SOCKET_MS = 8_000
const POLL_MS = 75_000

let seed: Seed
let partner: Page
let miniApp: Page
let partnerContext: BrowserContext
let miniContext: BrowserContext
let order: PlacedOrder
let code: string
const reverbFrames: string[] = []

// The change is expected over the socket; the poll is the safety net (see the header). `check` is any assertion that
// takes a timeout (an attribute, a count...): first with SOCKET_MS, then once more with POLL_MS
async function expectLiveThat(what: string, check: (timeout: number) => Promise<void>): Promise<void> {
  try {
    await check(SOCKET_MS)
  } catch {
    const note = `${what}: not seen within ${SOCKET_MS} ms of the event, waited for the poll`
    test.info().annotations.push({ type: 'realtime-fallback', description: note })
    console.warn(`[realtime-fallback] ${note}`)
    await check(POLL_MS)
  }
}

async function expectLive(locator: Locator, what: string): Promise<void> {
  await expectLiveThat(what, (timeout) => expect(locator).toBeVisible({ timeout }))
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async ({ browser }) => {
  seed = loadSeed()

  partnerContext = await browser.newContext({ baseURL: stack.partnerWeb, viewport: { width: 1360, height: 900 } })
  partner = await partnerContext.newPage()
  partner.on('websocket', (socket) => {
    socket.on('framereceived', (frame) => reverbFrames.push(String(frame.payload)))
  })

  // The manager opens the Mini App from Telegram: Telegram's launch parameters, signed with the bot token
  miniContext = await browser.newContext({
    baseURL: stack.miniApp,
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    deviceScaleFactor: 2,
  })
  const initData = signedInitData({ id: seed.manager.telegram_id, first_name: 'Malika' })
  await miniContext.addInitScript((data) => {
    const key = '__telegram__initParams'
    if (sessionStorage.getItem(key) === null) {
      sessionStorage.setItem(key, JSON.stringify({ tgWebAppData: data, tgWebAppVersion: '8.0', tgWebAppPlatform: 'web' }))
    }
  }, initData)
  miniApp = await miniContext.newPage()
})

// The two pages are made by hand (not the `page` fixture), so a failure's screenshots are taken here
test.afterEach(async ({}, testInfo) => {
  if (testInfo.status === testInfo.expectedStatus) return
  for (const [name, page] of [['partner-web', partner], ['mini-app', miniApp]] as const) {
    if (page && !page.isClosed()) await testInfo.attach(name, { body: await page.screenshot(), contentType: 'image/png' })
  }
})

test.afterAll(async () => {
  await partnerContext?.close()
  await miniContext?.close()
})

test('the owner signs in to the partner web with an SMS code and the manager opens the Mini App', async () => {
  const sinceLog = logSize()
  await partner.goto('/login')
  await partner.getByLabel('Telefon raqami').fill('901000001')
  await partner.getByRole('button', { name: 'Kod yuborish' }).click()

  await partner.getByLabel('SMS kod').fill(await smsCodeFor(seed.owner.phone, sinceLog))
  await partner.getByRole('button', { name: 'Kirish' }).click()

  // The access token lives in the page's memory: move on through the menu, not by loading a new page
  await expect(partner).toHaveURL(/\/dashboard/)
  await partner.getByRole('link', { name: 'Buyurtmalar' }).first().click()
  await expect(partner.getByRole('heading', { name: 'Buyurtmalar', level: 1 })).toBeVisible()
  await expect(partner.getByTestId('board')).toBeVisible()

  await miniApp.goto('/manager')
  await expect(miniApp.getByText('Malika Menejer').or(miniApp.getByText(/Hozircha faol buyurtmalar yo'q/))).toBeVisible()
})

test('the partner web board listens on its Reverb channel', async () => {
  // The socket carries the pusher protocol: connection established, then the signed subscription of the store channel
  await expect.poll(() => reverbFrames.some((frame) => frame.includes('pusher:connection_established')), { timeout: 15_000 }).toBe(true)
  await expect
    .poll(() => reverbFrames.some((frame) => frame.includes('pusher_internal:subscription_succeeded') && frame.includes(`private-store.${seed.store.id}`)), {
      timeout: 15_000,
    })
    .toBe(true)
})

test('a customer order appears on the board and in the manager\'s Mini App', async () => {
  order = await placeCustomerOrder(seed, [
    { product: 'Lavash', quantity: 2 },
    { product: 'Cola', quantity: 1 },
  ])
  expect(order.status).toBe('new')
  code = `#${order.number}`
  expect(order.number).toMatch(/^[A-Z0-9-]{4,}$/)

  const card = partner.getByRole('article', { name: code })
  await expectLive(card, 'new order on the board')
  await expect(card).toHaveAttribute('data-status', 'new')
  await expect(card.getByText('Lavash')).toBeVisible()
  await expect(card.getByRole('button', { name: 'Qabul qilish' })).toBeVisible()

  const miniCard = miniApp.getByRole('article', { name: `Buyurtma ${code}` })
  await expectLive(miniCard, 'new order in the Mini App')
})

test('the owner accepts the order on the board and the cook sees the ticket', async () => {
  const card = partner.getByRole('article', { name: code })
  await card.getByRole('button', { name: 'Qabul qilish' }).click()
  await expect(card).toHaveAttribute('data-status', 'accepted', { timeout: 15_000 })

  const kitchen = await Kitchen.signIn(seed)
  const ticket = await kitchen.ticket(order.uuid)
  expect(ticket.items).toHaveLength(2)
  expect(ticket.items_ready).toBe(0)
})

test('the first line the cook bumps starts the order and the board shows the kitchen\'s progress', async () => {
  const kitchen = await Kitchen.signIn(seed)
  const [first] = (await kitchen.ticket(order.uuid)).items
  await kitchen.bump(order.uuid, first.id)

  const card = partner.getByRole('article', { name: code })
  await expectLive(card.getByText('Oshxona: 1/2'), 'kitchen progress on the board')
  await expect(card).toHaveAttribute('data-status', 'preparing')

  const miniCard = miniApp.getByRole('article', { name: `Buyurtma ${code}` })
  await expectLive(miniCard.getByText('Oshxona: 1/2 tayyor'), 'kitchen progress in the Mini App')
})

test('the last line makes the order ready on the board and in the Mini App', async () => {
  const kitchen = await Kitchen.signIn(seed)
  const pending = (await kitchen.ticket(order.uuid)).items.filter((item) => item.kitchen_status !== 'ready')
  expect(pending).toHaveLength(1)
  await kitchen.bump(order.uuid, pending[0].id)

  const card = partner.getByRole('article', { name: code })
  await expectLive(card, 'ready order on the board')
  // P2-33: the card was already there; its status is what the event changes, so it gets the poll fallback too
  await expectLiveThat('ready status on the board', (timeout) => expect(card).toHaveAttribute('data-status', 'ready', { timeout }))
  await expect(card.getByRole('button', { name: 'Mijozga topshirish' })).toBeVisible()

  const miniCard = miniApp.getByRole('article', { name: `Buyurtma ${code}` })
  await expectLive(miniCard.getByText('Tayyor', { exact: true }).first(), 'ready status in the Mini App')
  await expect(miniCard.getByRole('button', { name: 'Mijozga topshirish' })).toBeVisible()
})

test('handing the order over on the board closes it in the Mini App too', async () => {
  const card = partner.getByRole('article', { name: code })
  await card.getByRole('button', { name: 'Mijozga topshirish' }).click()

  // It leaves the open columns and is among today's finished orders
  await expect(card).toHaveAttribute('data-status', 'delivered', { timeout: 15_000 })
  await expectLiveThat('handed-over order leaving the Mini App', (timeout) =>
    expect(miniApp.getByRole('article', { name: `Buyurtma ${code}` })).toHaveCount(0, { timeout }),
  )
})
