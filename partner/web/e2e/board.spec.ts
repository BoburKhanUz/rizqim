import type { Page } from '@playwright/test'
import type { FakeBackend } from './fake/backend'
import { expect, openSignedIn, test } from './fixtures'

// The live orders board: how a new order arrives (the Reverb socket, and the poll when there is none), the order's way
// through the kitchen to the customer or the courier, a rejection with its reason, and what the screen says when the
// server refuses a step. Everything is asserted by what the person sees and by the requests the page sent.

// The bodies of the status changes sent for one order, whichever of the two status endpoints carried them (the staff one,
// or the older /orders one that a step like "on the way" used to need)
const statusRequests = (api: FakeBackend, uuid: string) =>
  api.calls.filter((call) => call.method === 'POST' && (call.path === `/v1/staff/orders/${uuid}/status` || call.path === `/v1/orders/${uuid}/status`)).map((call) => call.body)

// The "Jonli" pill of the top bar: data-status is live, connecting or offline
const liveStatus = (page: Page) => page.locator('span[data-status]').first()

test.describe('a new order arrives', () => {
  test('over the real-time socket, without waiting for the poll', async ({ page, api, signIn, reverb }) => {
    await openSignedIn(page, signIn, '/orders')
    // The top bar says the socket is up once the channel is subscribed
    await expect(liveStatus(page)).toHaveAttribute('data-status', 'live')
    await expect(page.getByTestId('board').getByRole('article')).toHaveCount(0)

    // The owner of a one-store account listens on the store channel, signed through the backend
    expect(reverb.subscribed).toContain('private-store.1')
    expect(api.callsTo('POST', '/broadcasting/auth')[0].body).toMatchObject({ channel_name: 'private-store.1' })

    const order = api.orders.add({ short_code: 'R1' })
    reverb.emitOrder('order.created', order, ['private-store.1'])

    const card = page.getByRole('article', { name: '#R1' })
    await expect(card).toBeVisible()
    await expect(card).toHaveAttribute('data-status', 'new')
    await expect(card.getByText('Lavash')).toBeVisible()
    // The sidebar badge counts the orders waiting for acceptance
    await expect(page.getByText('1 ta yangi buyurtma').first()).toBeAttached()
  })

  test('by polling when there is no socket', async ({ page, api, signIn, reverb }) => {
    reverb.breakDown()
    // The poll runs every 15 s: a controlled clock jumps there instead of waiting
    await page.clock.install()
    await openSignedIn(page, signIn, '/orders')
    await expect(page.getByTestId('board')).toBeVisible()
    await expect(liveStatus(page)).not.toHaveAttribute('data-status', 'live')
    await expect(page.getByTestId('board').getByRole('article')).toHaveCount(0)

    api.orders.add({ short_code: 'Q1' })
    await page.clock.fastForward(16_000)

    await expect(page.getByRole('article', { name: '#Q1' })).toBeVisible()
  })
})

test('a pickup order goes from new to handed over', async ({ page, api, signIn, reverb }) => {
  void reverb
  const order = api.orders.add({ short_code: 'P2', type: 'pickup' })
  await openSignedIn(page, signIn, '/orders')
  const card = page.getByRole('article', { name: '#P2' })

  await card.getByRole('button', { name: 'Qabul qilish' }).click()
  await expect(card).toHaveAttribute('data-status', 'accepted')
  await expect(card.getByText('Oshxona: 0/2')).toBeVisible()

  await card.getByRole('button', { name: 'Tayyorlashni boshlash' }).click()
  await expect(card).toHaveAttribute('data-status', 'preparing')

  await card.getByRole('button', { name: "Tayyor bo'ldi" }).click()
  await expect(card).toHaveAttribute('data-status', 'ready')
  // A pickup order is handed over, not sent with a courier
  await expect(card.getByRole('button', { name: 'Kuryerni tayinlash' })).toHaveCount(0)

  await card.getByRole('button', { name: 'Mijozga topshirish' }).click()
  await expect(card).toHaveAttribute('data-status', 'delivered')
  await expect(card.getByText('Yetkazildi')).toBeVisible()

  expect(statusRequests(api, order.uuid)).toEqual([
    { status: 'accepted' },
    { status: 'preparing' },
    { status: 'ready' },
    { status: 'delivered' },
  ])
})

test('a delivery order is given to a courier and delivered', async ({ page, api, signIn, reverb }) => {
  void reverb
  const order = api.orders.add({ short_code: 'D3', type: 'delivery', status: 'ready' })
  await openSignedIn(page, signIn, '/orders')
  const card = page.getByRole('article', { name: '#D3' })
  await expect(card.getByText("Yo'lda")).toHaveCount(0)

  await card.getByRole('button', { name: 'Kuryerni tayinlash' }).click()
  const dialog = page.getByRole('dialog', { name: 'Kuryerni tayinlash' })
  await expect(dialog.getByRole('radio')).toHaveCount(1)
  // Nothing is sent until a courier is chosen
  await expect(dialog.getByRole('button', { name: 'Tayinlash' })).toBeDisabled()
  await dialog.getByRole('radio', { name: /Jasur Kuryer/ }).check()
  await dialog.getByRole('button', { name: 'Tayinlash' }).click()

  await expect(card).toHaveAttribute('data-status', 'courier_assigned')
  await expect(card.getByText('Kuryer: Jasur Kuryer')).toBeVisible()
  expect(api.callsTo('POST', `/v1/orders/${order.uuid}/assign-courier`)[0].body).toEqual({ courier_id: 31 })

  await card.getByRole('button', { name: "Yo'lga chiqdi" }).click()
  await expect(card).toHaveAttribute('data-status', 'on_the_way')
  expect(statusRequests(api, order.uuid)).toEqual([{ status: 'on_the_way' }])

  await card.getByRole('button', { name: 'Yetkazilganini tasdiqlash' }).click()
  await expect(card).toHaveAttribute('data-status', 'delivered')
  expect(statusRequests(api, order.uuid)).toEqual([{ status: 'on_the_way' }, { status: 'delivered' }])
})

test.describe('a rejection', () => {
  test('asks for a reason and sends it', async ({ page, api, signIn, reverb }) => {
    void reverb
    const order = api.orders.add({ short_code: 'X4' })
    await openSignedIn(page, signIn, '/orders')

    // The way out of an order is in its detail, away from the next-step button
    await page.getByRole('button', { name: '#X4 buyurtmani ochish' }).click()
    const drawer = page.getByRole('dialog', { name: /Buyurtma #X4/ })
    await expect(drawer.getByText('Lavash')).toBeVisible()
    await drawer.getByRole('button', { name: 'Rad etish' }).click()

    const dialog = page.getByRole('alertdialog', { name: 'Buyurtmani rad etish' })
    await dialog.getByRole('button', { name: 'Rad etish' }).click()
    await expect(dialog.getByText('Sababni tanlang.')).toBeVisible()
    expect(statusRequests(api, order.uuid)).toHaveLength(0)

    await dialog.getByRole('radio', { name: 'Mahsulot qolmagan' }).check()
    await dialog.getByLabel('Izoh (ixtiyoriy)').fill('Lavash tugadi')
    await dialog.getByRole('button', { name: 'Rad etish' }).click()

    await expect(dialog).toHaveCount(0)
    await expect(page.getByRole('status').filter({ hasText: '#X4' })).toBeVisible()
    expect(statusRequests(api, order.uuid)).toEqual([{ status: 'rejected', reason: 'out_of_stock', note: 'Lavash tugadi' }])
    expect(api.orders.find(order.uuid)?.status).toBe('rejected')
  })
})

test('a step the server refuses is explained in Uzbek and the board catches up', async ({ page, api, signIn, reverb }) => {
  void reverb
  const order = api.orders.add({ short_code: 'S5' })
  await openSignedIn(page, signIn, '/orders')
  const card = page.getByRole('article', { name: '#S5' })
  await expect(card).toHaveAttribute('data-status', 'new')

  // Meanwhile somebody else canceled it (the page was not told: its socket stays quiet)
  const stored = api.orders.find(order.uuid)!
  stored.status = 'canceled'
  stored.cancel_reason = 'customer_request'
  stored.history.push({ from_status: 'new', status: 'canceled', created_at: new Date().toISOString() })

  await card.getByRole('button', { name: 'Qabul qilish' }).click()

  await expect(page.getByRole('alert').filter({ hasText: 'holatidan' })).toContainText("Buyurtmani «Bekor qilindi» holatidan «Qabul qilindi» holatiga o'tkazib bo'lmaydi.")
  // ...and the list was refetched: the card shows how the order is now
  await expect(card).toHaveAttribute('data-status', 'canceled')
})

test('the drawer shows the kitchen\'s progress and the history of the order', async ({ page, api, signIn, reverb }) => {
  void reverb
  const order = api.orders.add({ short_code: 'K6', status: 'preparing', accepted_at: new Date().toISOString() })
  order.items[0].kitchen_status = 'ready'
  await openSignedIn(page, signIn, '/orders')

  const card = page.getByRole('article', { name: '#K6' })
  await expect(card.getByText('Oshxona: 1/2')).toBeVisible()
  await page.getByRole('button', { name: '#K6 buyurtmani ochish' }).click()
  const drawer = page.getByRole('dialog', { name: /Buyurtma #K6/ })
  await expect(drawer.getByRole('listitem').filter({ hasText: 'Lavash' })).toContainText('Tayyor')
  await expect(drawer.getByRole('listitem').filter({ hasText: 'Cola' })).toContainText('Kutmoqda')
  await expect(drawer.getByText('Holatlar tarixi')).toBeVisible()
  await expect(drawer.getByText('+998909998877')).toBeVisible()
})
