import type { Locator, Page } from '@playwright/test'
import { expect, test } from './fixtures'

// The menu model of P1-21 in the Mini App (P1-31): modifiers, items sold by weight, the stop-list and the delivery quote.

const NBSP = ' '

async function openStore(page: Page) {
  await page.goto('/')
  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()
}

// The row of a dish on the store page
const dish = (page: Page, name: string): Locator => page.getByRole('listitem').filter({ has: page.getByRole('heading', { name, level: 3, exact: true }) })

async function openCart(page: Page) {
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()
  await expect(page.getByRole('heading', { name: 'Savat', level: 1 })).toBeVisible()
}

async function chooseSauce(page: Page, name: string) {
  const sheet = page.getByRole('dialog', { name: 'Burger' })
  await sheet.getByRole('radio', { name: new RegExp(name) }).click()
  return sheet
}

test.describe('modifiers', () => {
  test('a required group must be chosen before the dish can be added; the order carries the choice', async ({ page, api }) => {
    await openStore(page)
    await dish(page, 'Burger').getByRole('button', { name: "Qo'shish: Burger" }).click()

    const sheet = page.getByRole('dialog', { name: 'Burger' })
    await expect(sheet.getByText('Majburiy · 1 ta tanlang')).toBeVisible()
    // Nothing can be added yet, and the button says why
    await expect(sheet.getByRole('button', { name: '"Sous" tanlang' })).toBeDisabled()

    await chooseSauce(page, 'Pishloqli')
    await sheet.getByRole('checkbox', { name: /Zaytun/ }).click()
    // 50 000 + 3 000 (sauce) + 2 000 (olives): the running price
    await expect(sheet.getByRole('button', { name: /^Tasdiqlash · 55.000\sso'm/ })).toBeEnabled()
    await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()

    await openCart(page)
    const line = page.getByRole('listitem').filter({ hasText: 'Burger (Standart)' })
    await expect(line.getByText(/Pishloqli · \+3.000\sso'm/)).toBeVisible()
    await expect(line.getByText('Zaytun · +2' + NBSP + '000' + NBSP + "so'm")).toBeVisible()
    await expect(line.getByText('55' + NBSP + '000' + NBSP + "so'm", { exact: true })).toBeVisible()

    await page.getByRole('button', { name: /Olib ketish/ }).click()
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

    const [placed] = api.callsTo('POST', '/orders')
    expect(placed.body).toMatchObject({
      type: 'pickup',
      items: [
        {
          variant_id: 'b0000000-0000-4000-8000-000000000003',
          quantity: 1,
          modifiers: [
            { id: 'e0000000-0000-4000-8000-000000000002', quantity: 1 },
            { id: 'e0000000-0000-4000-8000-000000000003', quantity: 1 },
          ],
        },
      ],
    })
    // An Idempotency-Key per checkout attempt
    expect(placed.idempotencyKey).toMatch(/^[0-9a-f-]{36}$/)
    expect(api.orders[0].total_price).toBe('55000.00')

    // The order history shows the choice
    await page.getByRole('link', { name: 'Buyurtmani kuzatish' }).click()
    await page.getByText(`#${api.orders[0].short_code}`, { exact: true }).click()
    await expect(page.getByText('Pishloqli', { exact: true })).toBeVisible()
  })

  test('the same dish with another choice is another line of the cart', async ({ page, api }) => {
    expect(api.orders).toHaveLength(0)
    await openStore(page)
    for (const sauce of ['Ketchup', 'Pishloqli', 'Ketchup']) {
      await dish(page, 'Burger').getByRole('button', { name: "Qo'shish: Burger" }).click()
      const sheet = await chooseSauce(page, sauce)
      await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()
    }
    await openCart(page)

    const lines = page.getByRole('listitem').filter({ hasText: 'Burger (Standart)' })
    await expect(lines).toHaveCount(2)
    await expect(lines.filter({ hasText: 'Ketchup' }).getByText('100' + NBSP + '000' + NBSP + "so'm", { exact: true })).toBeVisible() // 2 × 50 000
    await expect(lines.filter({ hasText: 'Pishloqli' }).getByText('53' + NBSP + '000' + NBSP + "so'm", { exact: true })).toBeVisible()
  })

  test('a modifier the server refuses is explained, not shown as a raw 422', async ({ page, api }) => {
    await openStore(page)
    await dish(page, 'Burger').getByRole('button', { name: "Qo'shish: Burger" }).click()
    const sheet = await chooseSauce(page, 'Pishloqli')
    await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()
    await openCart(page)
    await page.getByRole('button', { name: /Olib ketish/ }).click()

    api.stoppedModifierIds.push(2) // the cheese sauce ran out after the cart last looked
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()

    const alert = page.getByRole('alertdialog')
    await expect(alert).toContainText("Uzr, 'Pishloqli' hozir ushbu filialda mavjud emas")
    await expect(alert).not.toContainText('given data')
    expect(api.orders).toHaveLength(0)
  })
})

test.describe('sold by weight', () => {
  test('the customer picks 1,5 kg; the line is priced like the server and shows "1,5 kg" in the cart and the history', async ({ page, api }) => {
    await openStore(page)
    await expect(dish(page, 'Qazi').getByText('/ kg')).toBeVisible()
    await dish(page, 'Qazi').getByRole('button', { name: "Qo'shish: Qazi" }).click()

    const sheet = page.getByRole('dialog', { name: 'Qazi' })
    await expect(sheet.getByText('0,5 kg', { exact: true })).toBeVisible() // starts at the least quantity
    await expect(sheet.getByRole('button', { name: /^Tasdiqlash · 16.667\sso'm/ })).toBeVisible() // 33 333 × 0.5 = 16 666.5, rounded half up
    await sheet.getByRole('button', { name: "Qazi: ko'paytirish" }).click()
    await sheet.getByRole('button', { name: "Qazi: ko'paytirish" }).click()
    await expect(sheet.getByText('1,5 kg', { exact: true })).toBeVisible()
    await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()
    await expect(dish(page, 'Qazi').getByText('Savatda: 1,5 kg')).toBeVisible()

    await openCart(page)
    const line = page.getByRole('listitem').filter({ hasText: 'Qazi (kg)' })
    await expect(line.getByText(/1,5.kg/).first()).toBeVisible()
    await expect(line.getByText('50' + NBSP + '000' + NBSP + "so'm", { exact: true })).toBeVisible() // 33 333 × 1.5 = 49 999.5 -> 50 000

    // The step of the item: "-" goes down by 0,5 kg
    await line.getByRole('button', { name: 'Qazi (kg): kamaytirish' }).click()
    await expect(line.getByText('1' + NBSP + 'kg', { exact: true })).toBeVisible()
    await line.getByRole('button', { name: "Qazi (kg): ko'paytirish" }).click()

    await page.getByRole('button', { name: /Olib ketish/ }).click()
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

    expect(api.callsTo('POST', '/orders')[0].body).toMatchObject({ items: [{ variant_id: 'b0000000-0000-4000-8000-000000000004', quantity: 1.5 }] })
    expect(api.orders[0].total_price).toBe('50000.00')

    await page.getByRole('link', { name: 'Buyurtmani kuzatish' }).click()
    await page.getByText(`#${api.orders[0].short_code}`, { exact: true }).click()
    await expect(page.getByText('1,5' + NBSP + 'kg', { exact: true })).toBeVisible()
  })
})

test.describe('sold out', () => {
  test('a dish on the stop-list says "Tugadi" and cannot be added', async ({ page, api }) => {
    await openStore(page)

    const osh = dish(page, 'Osh')
    await expect(osh.getByText('Tugadi')).toBeVisible()
    await expect(osh.getByRole('button')).toHaveCount(0)
    await expect(dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" })).toBeVisible()
    expect(api.callsTo('GET', '/catalog/availability').length).toBeGreaterThan(0)
  })

  test('a dish that sold out while it was in the cart is flagged, blocks the order, and goes with one tap', async ({ page, api }) => {
    api.stoppedVariantIds = []
    await openStore(page)
    await dish(page, 'Cola').getByRole('button', { name: "Qo'shish: Cola" }).click()
    await dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" }).click()

    api.stoppedVariantIds = [2] // the cola is stopped
    await page.goto('/cart')

    const cola = page.getByRole('listitem').filter({ hasText: 'Cola (0.5 l)' })
    await expect(cola.getByText('Tugadi')).toBeVisible()
    await expect(page.getByRole('alert').filter({ hasText: 'hozir buyurtma qilinmaydi' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Tugaganlarni olib tashlang' })).toBeDisabled()

    await page.getByRole('button', { name: 'Tugaganlarni olib tashlash' }).click()
    await expect(cola).toHaveCount(0)
    await page.getByRole('button', { name: /Olib ketish/ }).click()
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  })

  test('a dish that sells out between the cart and the order is explained by the checkout, then the cart shows it', async ({ page, api }) => {
    api.stoppedVariantIds = []
    await openStore(page)
    await dish(page, 'Cola').getByRole('button', { name: "Qo'shish: Cola" }).click()
    await openCart(page)
    await page.getByRole('button', { name: /Olib ketish/ }).click()

    api.stoppedVariantIds = [2]
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()

    const alert = page.getByRole('alertdialog')
    await expect(alert).toContainText("Uzr, 'Cola (0.5 l)' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang.")
    await expect(alert).not.toContainText('given data')
    await alert.getByRole('button', { name: 'OK' }).click()

    // The cart asked for the menu again and now shows what ran out
    await expect(page.getByRole('listitem').filter({ hasText: 'Cola (0.5 l)' }).getByText('Tugadi')).toBeVisible()
    expect(api.orders).toHaveLength(0)
  })
})

test.describe('a price that changed while the dish was in the cart', () => {
  test('the cart totals it at the new price, flags it, and the order goes once the customer accepts it', async ({ page, api }) => {
    api.stoppedVariantIds = []
    await openStore(page)
    await dish(page, 'Cola').getByRole('button', { name: "Qo'shish: Cola" }).click()

    api.products[1].variants![0].price = '12000.00' // the store raised the price meanwhile (was 8 000)
    await page.goto('/cart')

    const cola = page.getByRole('listitem').filter({ hasText: 'Cola (0.5 l)' })
    await expect(cola.getByText(`Narxi o'zgardi: 8${NBSP}000${NBSP}so'm → 12${NBSP}000${NBSP}so'm`)).toBeVisible()
    await expect(cola.getByText(`12${NBSP}000${NBSP}so'm`, { exact: true })).toBeVisible()
    await page.getByRole('button', { name: /Olib ketish/ }).click()
    await expect(page.getByRole('button', { name: 'Yangi narxlarni tasdiqlang' })).toBeDisabled()

    await page.getByRole('button', { name: 'Yangi narxlarni qabul qilish' }).click()
    await page.getByRole('button', { name: `Buyurtma berish · 12${NBSP}000${NBSP}so'm` }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
    expect(api.orders.at(-1)?.items?.[0]?.total_price).toBe('12000.00')
  })
})

test.describe('the delivery quote', () => {
  const HOME = { id: 1, title: 'Uy', address: 'Chilonzor 9, 14-uy', lat: 41.2856, lng: 69.2037, is_default: true }
  const FAR = { id: 2, title: 'Samarqand', address: 'Samarqand, Registon', lat: 39.6542, lng: 66.9597, is_default: false }

  async function seedAddresses(page: Page, selectedAddressId: number) {
    await page.addInitScript(
      ({ addresses, selected }) => {
        localStorage.setItem('address-storage', JSON.stringify({ state: { addresses, selectedAddressId: selected }, version: 1 }))
      },
      { addresses: [HOME, FAR], selected: selectedAddressId },
    )
  }

  test('shows the zone\'s fee and the minimum order from the server, and the total with that fee', async ({ page, api }) => {
    api.zoneMinOrder = 20000
    await seedAddresses(page, 1)
    await openStore(page)
    await dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" }).click()
    await openCart(page)

    await expect(page.getByText(/Yetkazib berish: 12.000\sso'm/)).toBeVisible()
    await expect(page.getByText(/Minimal buyurtma: 20.000\sso'm/)).toBeVisible()
    await expect(page.getByText(/~40 daqiqa/)).toBeVisible()
    await expect(page.getByRole('button', { name: /^Buyurtma berish · 37.000\sso'm/ })).toBeEnabled() // 25 000 + 12 000
  })

  test('below the zone\'s minimum order the order cannot go, and says what the minimum is', async ({ page, api }) => {
    api.zoneMinOrder = 30000
    await seedAddresses(page, 1)
    await openStore(page)
    await dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" }).click()
    await openCart(page)

    await expect(page.getByText('Minimal buyurtma miqdori yetarli emas')).toBeVisible()
    await expect(page.getByText(/kamida 30.000\sso'm/)).toBeVisible()
    await expect(page.getByRole('button', { name: /^Buyurtma berish/ })).toBeDisabled()

    // One more dish and the (re-asked) quote lets it through
    await page.getByRole('button', { name: 'Lavash (Standart): ko\'paytirish' }).click()
    await expect(page.getByText('Minimal buyurtma miqdori yetarli emas')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^Buyurtma berish/ })).toBeEnabled()
  })

  test('an address outside the zone: "Bu manzilga yetkazib berilmaydi", and pickup is the way out', async ({ page, api }) => {
    await seedAddresses(page, 2)
    await openStore(page)
    await dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" }).click()
    await openCart(page)

    await expect(page.getByRole('alert').filter({ hasText: 'Bu manzilga yetkazib berilmaydi' })).toBeVisible()
    await expect(page.getByRole('button', { name: /^Bu manzilga yetkazib berilmaydi/ })).toBeDisabled()
    expect(api.callsTo('POST', '/orders')).toHaveLength(0)

    await page.getByRole('button', { name: 'Olib ketishni tanlash' }).click()
    await expect(page.getByRole('button', { name: /^Buyurtma berish/ })).toBeEnabled()
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  })

  test('the server\'s refusal of the order itself is shown in Uzbek too (zone changed after the quote)', async ({ page, api }) => {
    await seedAddresses(page, 1)
    await openStore(page)
    await dish(page, 'Lavash').getByRole('button', { name: "Qo'shish: Lavash" }).click()
    await openCart(page)
    await expect(page.getByText(/Yetkazib berish: 12.000\sso'm/)).toBeVisible()

    api.zoneMinOrder = 90000 // raised by the owner after the customer's quote
    await page.getByRole('button', { name: /^Buyurtma berish/ }).click()

    const alert = page.getByRole('alertdialog')
    await expect(alert).toContainText("Bu hududda eng kam buyurtma summasi 90" + NBSP + "000" + NBSP + "so'm")
    expect(api.orders).toHaveLength(0)
  })
})
