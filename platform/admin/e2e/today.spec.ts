import type { Page } from '@playwright/test'
import { expect, openSignedIn, test } from './fixtures'

// P4-13 Bugun: the home page. Numbers with comparisons in words, "Diqqat talab qiladi" (critical first, rows open their
// card), channels and regions, what each role sees, the 30 s refresh, a failing part, the phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

test('the numbers, the attention list and a row that opens its card', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/today')

  const kpis = page.getByRole('list', { name: "Bugungi ko'rsatkichlar" })
  const gmv = kpis.getByRole('listitem').filter({ hasText: 'GMV (marketpleys)' })
  await expect(gmv).toContainText("12 450 000 so'm")
  await expect(gmv).toContainText("Kechagi shu vaqtga:25% ko'p (9 960 000 so'm)")
  await expect(gmv).toContainText("O'tgan haftaning shu kuniga:o'zgarmadi")
  await expect(kpis.getByRole('listitem').filter({ hasText: 'Buyurtmalar' })).toContainText('7% kam (200)')
  await expect(kpis.getByRole('listitem').filter({ hasText: 'Obuna daromadi' })).toContainText("ko'p (u paytda 0 so'm edi)")

  const attention = page.getByRole('list', { name: 'Diqqat talab qiladi' })
  const groups = attention.locator(':scope > li')
  await expect(groups).toHaveCount(3)
  await expect(groups.nth(0)).toContainText('Yiqilgan fiskal cheklar')
  await expect(groups.nth(0)).toContainText('Shoshilinch')
  await expect(groups.nth(1)).toContainText("Pul yechish so'rovlari")
  await expect(groups.nth(1)).toContainText('Yana 2 ta bor')

  await expect(page.getByRole('list', { name: "Kanallar bo'yicha" })).toContainText('GMV ga kirmaydi')
  await expect(page.getByRole('list', { name: "Viloyatlar bo'yicha" }).getByRole('listitem').first()).toContainText('38 faol ovqatxona')
  expect(api.callsTo('GET', '/today/attention')[0]?.query.limit).toBe('5')

  await groups.nth(0).getByRole('link', { name: /Lazzat Oshxona · Chilonzor/ }).click()
  await expect(page).toHaveURL(/\/pos\?fiscal=5f0c2a10-0000-4000-8000-000000000001$/)
  await expect(page.getByRole('heading', { name: 'POS va obunalar', level: 1 })).toBeVisible()
})

test('an operator sees only the problems of their permissions; nothing left is "Hammasi joyida"', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/today', 'sardor@rizqim.uz')
  const attention = page.getByRole('list', { name: 'Diqqat talab qiladi' })
  await expect(attention.locator(':scope > li')).toHaveCount(2)
  await expect(attention).not.toContainText("Pul yechish so'rovlari")

  api.today.groups = []
  await page.getByRole('button', { name: 'Yangilash' }).click()
  await expect(page.getByText('Hammasi joyida')).toBeVisible()
  await expect(attention).toBeHidden()
})

test('the page asks again every 30 seconds', async ({ page, api, signIn }) => {
  await page.clock.install()
  await openSignedIn(page, signIn, '/today')
  const orders = page.getByRole('list', { name: "Bugungi ko'rsatkichlar" }).getByRole('listitem').filter({ hasText: 'Buyurtmalar' })
  await expect(orders).toContainText('186')

  api.today.summary = { ...api.today.summary, kpis: { ...api.today.summary.kpis, orders: { ...api.today.summary.kpis.orders, value: 187 } } }
  const before = api.callsTo('GET', '/today').length
  await page.clock.fastForward('00:31')
  await expect(orders).toContainText('187')
  expect(api.callsTo('GET', '/today').length).toBeGreaterThan(before)
})

test('a failing part keeps the other and comes back on retry', async ({ page, api, signIn }) => {
  api.today.failSummary = true
  await openSignedIn(page, signIn, '/today')
  await expect(page.getByText('Bugungi raqamlar yuklanmadi')).toBeVisible()
  await expect(page.getByRole('list', { name: 'Diqqat talab qiladi' })).toBeVisible()

  api.today.failSummary = false
  await page.getByRole('button', { name: 'Qayta urinish' }).click()
  await expect(page.getByRole('list', { name: "Bugungi ko'rsatkichlar" })).toBeVisible()
})

test('a member without today.view is not sent to Bugun', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/', 'madina@rizqim.uz')
  await expect(page).not.toHaveURL(/\/today$/)
  await page.goto('/today')
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeHidden()
})

test.describe('on a phone', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('Bugun fits 360 px', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/today')
    await expect(page.getByRole('list', { name: 'Diqqat talab qiladi' })).toBeVisible()
    await expect(page.getByRole('list', { name: "Viloyatlar bo'yicha" })).toBeVisible()
    await noHorizontalScroll(page)
    const card = page.getByRole('list', { name: "Bugungi ko'rsatkichlar" }).getByRole('listitem').first()
    const box = await card.boundingBox()
    expect(box!.width).toBeLessThanOrEqual(360)
  })
})
