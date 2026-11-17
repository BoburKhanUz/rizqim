import type { Page } from '@playwright/test'
import { expect, test } from './fixtures'

// Wave M2: the home feed is light cards (GET /catalog?view=cards) and the store lists come a page at a time. For the
// customer, adding from home, the product sheet, search and checkout work as before; a store that does not take orders
// now says so and cannot be ordered from.

// A dish card of the home feed: the article with its name
const feedCard = (page: Page, name: string) => page.getByRole('article').filter({ has: page.getByRole('heading', { name, exact: true }) })

test('home: a plain dish goes into the cart from its card at once, without loading the store menu', async ({ page, api }) => {
  await page.goto('/')
  await feedCard(page, 'Lavash').getByRole('button', { name: "Savatga qo'shish" }).click()
  await expect(feedCard(page, 'Lavash').getByText('1', { exact: true })).toBeVisible()

  const feed = api.callsTo('GET', '/catalog').filter((call) => !call.query.store_id)
  expect(feed).toHaveLength(1)
  expect(feed[0].query).toMatchObject({ view: 'cards', limit: '50' })
  expect(api.callsTo('GET', '/catalog').filter((call) => call.query.store_id)).toHaveLength(0)
  expect(api.callsTo('GET', '/stores')[0].query).toMatchObject({ page: '1', per_page: '10' })

  // The cart and the order are the ones a full product made
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()
  await expect(page.getByText('Lavash (Standart)')).toBeVisible()
  await page.getByRole('button', { name: /Olib ketish/ }).click()
  await page.getByRole('button', { name: /^Buyurtma berish/ }).click()
  await expect(page.getByRole('heading', { name: 'Buyurtma qabul qilindi!' })).toBeVisible()
  expect(api.callsTo('POST', '/orders')[0].body).toMatchObject({ items: [{ variant_id: 'b0000000-0000-4000-8000-000000000001', quantity: 1 }] })
  // The cart still reads the live store list for checkout
  expect(api.callsTo('GET', '/stores').some((call) => call.query.for === 'checkout')).toBe(true)
})

test('home: a dish with options loads its store menu and opens the same sheet as the store page', async ({ page, api }) => {
  await page.goto('/')
  await feedCard(page, 'Burger').getByRole('button', { name: "Savatga qo'shish" }).click()

  const sheet = page.getByRole('dialog', { name: 'Burger' })
  await expect(sheet.getByText('Majburiy · 1 ta tanlang')).toBeVisible()
  expect(api.callsTo('GET', '/catalog').some((call) => call.query.store_id === api.store.uuid)).toBe(true)
  await sheet.getByRole('radio', { name: /Pishloqli/ }).click()
  await sheet.getByRole('button', { name: /^Tasdiqlash/ }).click()

  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()
  await expect(page.getByRole('listitem').filter({ hasText: 'Burger (Standart)' }).getByText(/Pishloqli/)).toBeVisible()
})

test('home: search finds a dish by its restaurant among the cards', async ({ page, api }) => {
  void api
  await page.goto('/')
  await page.getByPlaceholder('Taom yoki oshxona qidirish').fill('evos')
  await expect(feedCard(page, 'Lavash')).toBeVisible()
  await page.getByPlaceholder('Taom yoki oshxona qidirish').fill('qazi')
  await expect(feedCard(page, 'Qazi')).toBeVisible()
  await expect(feedCard(page, 'Lavash')).toHaveCount(0)
})

test('a store that does not take orders now says so and cannot be ordered from', async ({ page, api }) => {
  api.acceptingOrders = false
  await page.goto('/')
  await expect(page.getByText('Hozir buyurtma qabul qilmayapti').first()).toBeVisible()
  await expect(feedCard(page, 'Lavash').getByRole('button', { name: "Savatga qo'shish" })).toHaveCount(0)

  await page.getByRole('heading', { name: 'Evos', level: 3 }).click()
  await expect(page.getByRole('heading', { name: 'Evos', level: 1 })).toBeVisible()
  await expect(page.getByRole('status').filter({ hasText: 'Hozir buyurtma qabul qilmayapti' })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Qo'shish/ })).toHaveCount(0)
})

test('a cart of a store that stopped taking orders cannot be checked out', async ({ page, api }) => {
  await page.goto('/')
  await feedCard(page, 'Lavash').getByRole('button', { name: "Savatga qo'shish" }).click()
  api.acceptingOrders = false
  await page.getByRole('link', { name: /Savatni ko'rish/ }).click()

  await expect(page.getByRole('alert').filter({ hasText: 'Hozir buyurtma qabul qilmayapti' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Hozir buyurtma qabul qilmayapti' })).toBeDisabled()
  expect(api.callsTo('POST', '/orders')).toHaveLength(0)
})
