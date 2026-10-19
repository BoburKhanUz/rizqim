import type { Locator, Page } from '@playwright/test'
import { expect, test } from './fixtures'

// The card of one product on the store page: the innermost block that holds both its name and its "add" button
const productCard = (page: Page, name: string): Locator =>
  page
    .locator('div')
    .filter({ has: page.getByRole('heading', { name, exact: true }) })
    .filter({ has: page.getByRole('button', { name: "Qo'shish +" }) })
    .last()

// Browse the catalog: Home -> the store -> add a product -> the cart
async function addLavashToCart(page: Page) {
  await page.goto('/')
  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()

  await productCard(page, 'Lavash').getByRole('button', { name: "Qo'shish +" }).click()
  // The floating "view cart" bar appears as soon as something is in the cart (it once did not re-render on add)
  await page.getByRole('link', { name: /Savatchani ko'rish/ }).click()

  await expect(page.getByRole('heading', { name: 'Mening Savatim' })).toBeVisible()
  await expect(page.getByText('Lavash (Standart)')).toBeVisible()
}

test('customer orders for pickup and sees the order among the active ones', async ({ page, api }) => {
  await addLavashToCart(page)

  await page.getByRole('button', { name: /Olib ketish/ }).click()
  await expect(page.getByText('Chilonzor filiali')).toBeVisible()
  await page.getByRole('button', { name: /Buyurtma berish/ }).click()

  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

  const [placed] = api.callsTo('POST', '/orders')
  expect(placed.body).toMatchObject({
    store_id: 1,
    type: 'pickup',
    payment_method: 'cash',
    branch_id: 1,
    items: [{ variant_id: 'b0000000-0000-4000-8000-000000000001', quantity: 1 }],
  })
  expect(api.orders).toHaveLength(1)

  await page.getByRole('button', { name: 'Buyurtmani Kuzatish' }).click()

  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText(`#${api.orders[0].uuid.slice(0, 8).toUpperCase()}`)).toBeVisible()
  await expect(active.getByText('Kutilmoqda')).toBeVisible() // status "new"
  await expect(active.getByText('25,000 UZS')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Buyurtmalar tarixi' })).toHaveCount(0)
})

test('customer orders for delivery to a saved address', async ({ page, api }) => {
  // An address saved earlier, with its pin on the map (picking one on the map itself is not part of this test)
  await page.addInitScript(() => {
    const address = { id: 1, title: 'Uy', address: 'Chilonzor 9, 14-uy', lat: 41.2856, lng: 69.2037, is_default: true }
    localStorage.setItem('address-storage', JSON.stringify({ state: { addresses: [address], selectedAddressId: 1 }, version: 1 }))
  })

  await addLavashToCart(page)

  await expect(page.getByText('Chilonzor 9, 14-uy')).toBeVisible()
  await page.getByRole('button', { name: /Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()

  const [placed] = api.callsTo('POST', '/orders')
  expect(placed.body).toMatchObject({
    type: 'delivery',
    address: 'Chilonzor 9, 14-uy',
    latitude: 41.2856,
    longitude: 69.2037,
    branch_id: null,
  })

  await page.getByRole('button', { name: 'Buyurtmani Kuzatish' }).click()
  const active = page.getByRole('region', { name: 'Faol buyurtmalar' })
  await expect(active.getByText('37,000 UZS')).toBeVisible() // 25 000 + the 12 000 delivery fee
})
