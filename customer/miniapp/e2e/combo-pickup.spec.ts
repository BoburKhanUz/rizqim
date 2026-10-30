import { mkdirSync } from 'node:fs'
import type { Page } from '@playwright/test'
import { makeProduct, makeVariant } from '../src/test/factories'
import type { ComboOption, ComboSlot, Product } from '../src/types'
import { expect, test } from './fixtures'

// P6-31 / P6-34 on a small phone (Telegram's WebView is 360 px wide): a combo is added in two taps, the deltas are plain,
// the pickup time the customer picked is on the checkout summary, in the order body and on the order card.

test.use({ viewport: { width: 360, height: 740 } })

// Screenshots of the screens for the review (set MINIAPP_SHOTS to a directory to write them)
const shots = process.env.MINIAPP_SHOTS ?? ''
async function shot(page: Page, name: string) {
  if (!shots) return
  mkdirSync(shots, { recursive: true })
  await page.screenshot({ path: `${shots}/${name}.png` })
}

const option = (id: number, name: string, over: Partial<ComboOption> = {}): ComboOption => ({
  id,
  uuid: `c0000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
  variant_id: 100 + id,
  variant_uuid: `c1000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
  product_id: 100 + id,
  name,
  variant_name: null,
  quantity: 1,
  price_delta: '0.00',
  modifiers: [],
  is_default: false,
  label: null,
  receipt_label: name,
  ...over,
})

const slot = (id: number, name: string, options: ComboOption[], over: Partial<ComboSlot> = {}): ComboSlot => ({
  id,
  uuid: `c2000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
  name,
  min_picks: 1,
  max_picks: 1,
  is_required: true,
  allow_modifiers: false,
  options,
  ...over,
})

function lavashKombo(): Product {
  return makeProduct({
    id: 9,
    uuid: 'a0000000-0000-4000-8000-000000000009',
    store_id: 1,
    name: 'Lavash kombo',
    variants: [makeVariant({ id: 90, uuid: 'b0000000-0000-4000-8000-000000000090', product_id: 9, name: 'Standart', price: '45000.00', stock: 50 })],
    combo: {
      id: 1,
      uuid: 'c3000000-0000-4000-8000-000000000001',
      product_id: 9,
      slots: [
        slot(1, 'Asosiy', [
          option(1, 'Lavash', { variant_name: 'Katta', is_default: true, receipt_label: '1 ta lavash — katta, mol go\'shtli' }),
          option(2, 'Tovuq lavash', { variant_name: 'Katta', price_delta: '5000.00', receipt_label: '1 ta lavash — katta, tovuqli' }),
          option(3, 'Pishloqli lavash', { variant_name: 'Katta', price_delta: '7000.00', receipt_label: '1 ta lavash — katta, pishloqli' }),
        ]),
        slot(2, 'Ichimlik', [
          option(4, 'Pepsi', { variant_name: '0,4 l', is_default: true, receipt_label: 'Ichimlik — 0,4 l Pepsi' }),
          option(5, 'Ayran', { price_delta: '2000.00', receipt_label: 'Ichimlik — Ayran' }),
          option(6, 'Sok', { variant_name: '0,3 l', price_delta: '3000.00', receipt_label: 'Ichimlik — 0,3 l sok' }),
        ]),
        slot(3, 'Sous', [option(7, 'Achchiq sous', { receipt_label: 'Sous — achchiq' }), option(8, 'Sarimsoqli sous', { receipt_label: 'Sous — sarimsoqli' })], {
          min_picks: 0,
          max_picks: 2,
          is_required: false,
        }),
      ],
    },
  })
}

// 18:30 today and 09:00 tomorrow in Tashkent (the fixtures pin the browser's zone to it)
function pickupSlots() {
  const day = (offset: number) => new Date(Date.now() + offset * 86_400_000 + 5 * 3_600_000).toISOString().slice(0, 10)
  return {
    min_lead_minutes: 20,
    slot_minutes: 15,
    slots: [
      { at: `${day(0)}T13:30:00.000000Z`, time: '18:30', business_date: day(0) },
      { at: `${day(0)}T13:45:00.000000Z`, time: '18:45', business_date: day(0) },
      { at: `${day(1)}T04:00:00.000000Z`, time: '09:00', business_date: day(1) },
    ],
  }
}

async function openCombo(page: Page) {
  await page.goto('/')
  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()
  await page.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Lavash kombo', exact: true }) }).getByRole('button', { name: /^Qo'shish/ }).click()
  return page.getByRole('dialog', { name: 'Lavash kombo' })
}

test('the default combo goes into the cart in two taps and the order carries the picks', async ({ page, api }) => {
  api.products.push(lavashKombo())
  const sheet = await openCombo(page) // tap 1: the tile

  await expect(sheet.getByRole('radio', { name: /^Lavash/ })).toHaveAttribute('aria-checked', 'true')
  await expect(sheet.getByRole('radio', { name: /^Pepsi/ })).toHaveAttribute('aria-checked', 'true')
  // What a choice adds is said in words; the included one says it is in the price
  await expect(sheet.getByRole('radio', { name: /^Tovuq lavash/ })).toContainText("+5 000 so'm")
  await expect(sheet.getByRole('radio', { name: /^Lavash/ })).toContainText('Narxda')
  await shot(page, 'combo-sheet-top')

  await sheet.getByRole('button', { name: /^Tasdiqlash · 45.000\sso'm/ }).click() // tap 2
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()

  const line = page.getByRole('listitem').filter({ hasText: 'Lavash kombo' })
  await expect(line.getByRole('list', { name: 'Kombo tarkibi' })).toContainText("1 ta lavash — katta, mol go'shtli")
  await expect(line.getByRole('list', { name: 'Kombo tarkibi' })).toContainText('Ichimlik — 0,4 l Pepsi')
  await shot(page, 'combo-cart-default')

  await page.getByRole('button', { name: /Olib ketish/ }).click()
  await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  const [placed] = api.callsTo('POST', '/orders')
  expect(placed.body).toMatchObject({ items: [{ variant_id: 'b0000000-0000-4000-8000-000000000090', quantity: 1, combo: [{ option_id: 'c0000000-0000-4000-8000-000000000001' }, { option_id: 'c0000000-0000-4000-8000-000000000004' }] }] })
})

test('another pick shows its delta in the sheet, the cart and the order card', async ({ page, api }) => {
  api.products.push(lavashKombo())
  const sheet = await openCombo(page)

  await sheet.getByRole('radio', { name: /^Tovuq lavash/ }).click()
  await sheet.getByRole('radio', { name: /^Ayran/ }).click()
  await sheet.getByRole('checkbox', { name: /^Achchiq sous/ }).click()
  await shot(page, 'combo-sheet-picked')
  await sheet.getByRole('radio', { name: /^Sok/ }).scrollIntoViewIfNeeded()
  await shot(page, 'combo-sheet-bottom')
  await expect(sheet.getByRole('button', { name: /^Tasdiqlash · 52.000\sso'm/ })).toBeVisible()
  await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()

  const line = page.getByRole('listitem').filter({ hasText: 'Lavash kombo' })
  await expect(line.getByRole('list', { name: 'Kombo tarkibi' })).toContainText("1 ta lavash — katta, tovuqli")
  await expect(line.getByText("+5 000 so'm")).toBeVisible()
  await expect(line.getByRole('list', { name: 'Kombo tarkibi' })).toContainText('Ichimlik — Ayran')
  await expect(line.getByText("+2 000 so'm")).toBeVisible()
  await shot(page, 'combo-cart-picked')
})

test('a pickup time is picked at checkout, shown in the summary, sent with the order and shown on the order card', async ({ page, api }) => {
  api.products.push(lavashKombo())
  api.pickupSlots = pickupSlots()
  const sheet = await openCombo(page)
  await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()
  await page.getByRole('button', { name: /Olib ketish/ }).click()

  const picker = page.getByRole('combobox', { name: 'Olib ketish vaqti' })
  await expect(picker).toBeVisible()
  await shot(page, 'pickup-picker')
  // "Hozir" is the default; no time row until a time is picked
  await expect(page.getByTestId('checkout-pickup-time')).toHaveCount(0)

  await picker.selectOption({ label: 'Bugun, 18:30' })
  await expect(page.getByTestId('checkout-pickup-time')).toContainText('Bugun, 18:30')
  await page.getByTestId('checkout-pickup-time').scrollIntoViewIfNeeded()
  await shot(page, 'pickup-summary')

  await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  const [placed] = api.callsTo('POST', '/orders')
  expect(placed.body).toMatchObject({ type: 'pickup', pickup_at: pickupSlots().slots[0].at })

  await page.getByRole('link', { name: 'Buyurtmani kuzatish' }).click()
  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByTestId('order-pickup-at')).toContainText('Bugun, 18:30')
  await shot(page, 'pickup-order-card')
})
