import type { FakeApi } from './fakeApi'
import { expect, test } from './fixtures'

const HISTORY_SIZE = 25 // two pages: 20 + 5

function seedHistory(api: FakeApi) {
  for (let i = 0; i < HISTORY_SIZE; i++) api.addOrder({ status: 'delivered', payment_status: 'paid' })
}

// The cursor of every history request the page made, in order (null for the first page)
const historyCursors = (api: FakeApi) =>
  api.callsTo('GET', '/orders').filter((call) => call.query.scope === 'history').map((call) => call.query.cursor ?? null)

test('"Ko\'proq yuklash" loads the next page of the history and goes away on the last one', async ({ page, api }) => {
  seedHistory(api)

  await page.goto('/orders')
  const history = page.getByRole('region', { name: 'Buyurtmalar tarixi' })
  const orders = history.getByText(/^#E\d+$/)
  await expect(orders).toHaveCount(20)

  await page.getByRole('button', { name: "Ko'proq yuklash" }).click()

  await expect(orders).toHaveCount(HISTORY_SIZE)
  await expect(page.getByRole('button', { name: "Ko'proq yuklash" })).toHaveCount(0)
  // Two pages were asked for and, with no next_cursor left, nothing more
  expect(new Set(historyCursors(api))).toEqual(new Set([null, '20']))
})

test('scrolling to the end of the history loads the next page by itself', async ({ page, api }) => {
  seedHistory(api)

  await page.goto('/orders')
  const orders = page.getByRole('region', { name: 'Buyurtmalar tarixi' }).getByText(/^#E\d+$/)
  await expect(orders).toHaveCount(20)

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))

  await expect(orders).toHaveCount(HISTORY_SIZE)
  await expect(page.getByRole('button', { name: "Ko'proq yuklash" })).toHaveCount(0)
  expect(new Set(historyCursors(api))).toEqual(new Set([null, '20']))
})
