import type { Page } from '@playwright/test'
import { expect, openSignedIn, test } from './fixtures'

// P4-31 Ko'rsatkichlar and the Excel exports: the cards and charts with the server's figures, the application funnel, the
// period, who may open the page, the export button on the lists (the filters on screen go to the file, the row count and the
// cap are said), phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

test('the page shows MRR, churn, the movement and the funnel from the server, and the period asks again', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/metrics')
  await expect(page.getByRole('heading', { name: "Ko'rsatkichlar", level: 1 })).toBeVisible()

  const cards = page.getByRole('list', { name: "Asosiy ko'rsatkichlar" })
  await expect(cards.getByRole('listitem').filter({ hasText: 'MRR (oylik obuna daromadi)' })).toContainText(/831\s167\sso'm/)
  await expect(cards.getByRole('listitem').filter({ hasText: 'MRR (oylik obuna daromadi)' })).toContainText(/Oy boshidan 100\s000\sso'm ko'p \(13,7%\)/)
  await expect(cards.getByRole('listitem').filter({ hasText: 'Ketish (oxirgi oy)' })).toContainText('50,0%')
  await expect(cards.getByRole('listitem').filter({ hasText: 'Pullik ovqatxonalar' })).toContainText('Sinovda: 1 ta')

  // The chart reads out a bar, the table has the same figures
  const chart = page.getByRole('group', { name: /^MRR oylar bo'yicha: 12 oy$/ })
  await chart.getByRole('button', { name: /^sen 2026: MRR 731\s167\sso'm/ }).hover()
  await expect(page.getByText(/sen 2026: MRR 731\s167/).first()).toBeVisible()

  const movement = page.getByRole('heading', { name: 'MRR harakati' }).locator('xpath=ancestor::div[contains(@class,"rounded-card")][1]')
  await movement.getByRole('button', { name: 'Jadval' }).click()
  await expect(movement.getByRole('row', { name: /sen 2026/ })).toContainText(/249\s000/)

  const funnel = page.getByRole('heading', { name: 'Arizadan faol ovqatxonagacha' }).locator('xpath=ancestor::div[contains(@class,"rounded-card")][1]')
  await expect(funnel.getByText('25,0%', { exact: true })).toBeVisible()
  await expect(funnel.getByRole('list', { name: 'Ariza bosqichlari' }).getByRole('listitem')).toHaveCount(7)

  await page.getByLabel('Davr').selectOption('24')
  await expect.poll(() => api.callsTo('GET', '/metrics').some((call) => call.query.months === '24')).toBe(true)
  await expect.poll(() => api.callsTo('GET', '/metrics/funnel').some((call) => call.query.months === '24')).toBe(true)
  await expect(page.getByRole('group', { name: /^MRR oylar bo'yicha: 24 oy$/ })).toBeVisible()
})

test('a member with stores.view only sees the page; one with neither is sent home and the sidebar link goes', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/metrics', 'sardor@rizqim.uz')
  await expect(page.getByRole('heading', { name: "Ko'rsatkichlar", level: 1 })).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: "Ko'rsatkichlar" })).toBeVisible()

  api.grants.operator = ['admin.today.view', 'admin.orders.view']
  await page.reload()
  // The shell sends a member who may not open the section to their home page
  await expect(page).toHaveURL(/\/today$/)
  await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: "Ko'rsatkichlar" })).toHaveCount(0)
})

test('the numbers failing keeps the funnel and offers a retry', async ({ page, api, signIn }) => {
  api.metrics.broken = true
  await openSignedIn(page, signIn, '/metrics')
  await expect(page.getByText("Ko'rsatkichlar yuklanmadi")).toBeVisible()
  await expect(page.getByText('25,0%', { exact: true })).toBeVisible()
  api.metrics.broken = false
  await page.getByRole('button', { name: 'Qayta urinish' }).first().click()
  await expect(page.getByRole('list', { name: "Asosiy ko'rsatkichlar" })).toBeVisible()
})

test('an empty funnel is said calmly', async ({ page, api, signIn }) => {
  api.metrics.noApplications = true
  await openSignedIn(page, signIn, '/metrics')
  await expect(page.getByText("Bu davrda ariza yo'q")).toBeVisible()
})

test('the page and its charts fit a phone', async ({ page, signIn }) => {
  await page.setViewportSize({ width: 360, height: 740 })
  await openSignedIn(page, signIn, '/metrics')
  await expect(page.getByRole('list', { name: "Asosiy ko'rsatkichlar" })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Ariza bosqichlari' })).toBeVisible()
  await noHorizontalScroll(page)
  // A wide table scrolls inside its own card
  const movement = page.getByRole('heading', { name: 'MRR harakati' }).locator('xpath=ancestor::div[contains(@class,"rounded-card")][1]')
  await movement.getByRole('button', { name: 'Jadval' }).click()
  await expect(movement.getByRole('table')).toBeVisible()
  await noHorizontalScroll(page)
})

// --- The export button on the lists --------------------------------------------------------------------------------------

const LISTS: Array<{ path: string; heading: string; list: string; ready: (page: Page) => Promise<void> }> = [
  { path: '/orders', heading: 'Buyurtmalar', list: 'orders', ready: async (page) => void (await expect(page.getByRole('list', { name: "Buyurtmalar ro'yxati" })).toBeVisible()) },
  { path: '/stores', heading: 'Ovqatxonalar', list: 'stores', ready: async (page) => void (await expect(page.getByRole('list', { name: "Ovqatxonalar ro'yxati" })).toBeVisible()) },
  { path: '/stores/applications', heading: 'Ulash jarayoni', list: 'store_applications', ready: async (page) => void (await expect(page.getByRole('region', { name: 'Ariza' }).first()).toBeVisible()) },
  { path: '/finance', heading: 'Moliya', list: 'finance_payouts', ready: async (page) => void (await expect(page.getByRole('list', { name: "Payout so'rovlari" })).toBeVisible()) },
  { path: '/finance?tab=topups', heading: 'Moliya', list: 'finance_topups', ready: async (page) => void (await expect(page.getByRole('tab', { name: /^To'ldirishlar/ })).toHaveAttribute('aria-selected', 'true')) },
  { path: '/finance?tab=balances', heading: 'Moliya', list: 'finance_balances', ready: async (page) => void (await expect(page.getByRole('tab', { name: /^Balans/ })).toHaveAttribute('aria-selected', 'true')) },
  { path: '/audit', heading: 'Harakatlar jurnali', list: 'audit_logs', ready: async (page) => void (await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()) },
]

for (const entry of LISTS) {
  test(`${entry.path}: the Excel button asks for the file and says how many rows it holds`, async ({ page, api, signIn }) => {
    await openSignedIn(page, signIn, entry.path)
    await entry.ready(page)

    const download = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
    expect((await download).suggestedFilename()).toBe(`rizqim-${entry.list}-20261028-1200.xlsx`)
    await expect(page.getByRole('status').filter({ hasText: 'Fayl tayyor: 2 qator.' })).toBeVisible()
    expect(api.metrics.exports.map((item) => item.list)).toEqual([entry.list])
  })
}

test('POS lists export too: invoices and devices', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?tab=invoices')
  await expect(page.getByRole('button', { name: 'Excel yuklab olish' })).toBeVisible()
  const first = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  await first
  await page.goto('/pos?tab=devices')
  const second = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  await second
  expect(api.metrics.exports.map((item) => item.list)).toEqual(['pos_invoices', 'pos_devices'])
})

test('the filters on screen go to the file', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/orders')
  await expect(page.getByRole('list', { name: "Buyurtmalar ro'yxati" })).toBeVisible()
  await page.getByRole('button', { name: 'Kechikkan' }).click()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  await download
  expect(api.metrics.exports[0]).toMatchObject({ list: 'orders', query: { status: 'active', sla: 'late' } })
})

test('a list cut at the cap, too many exports and a member without the permission', async ({ page, api, signIn }) => {
  api.metrics.truncated = true
  await openSignedIn(page, signIn, '/stores')
  await expect(page.getByRole('list', { name: "Ovqatxonalar ro'yxati" })).toBeVisible()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  await download
  await expect(page.getByRole('status').filter({ hasText: "ro'yxat 50 000 qatordan keyin kesildi" })).toBeVisible()

  api.metrics.throttled = true
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  await expect(page.getByRole('alert').filter({ hasText: "Juda ko'p eksport" })).toBeVisible()

  // The operator has no export: no button
  await page.context().clearCookies()
  await openSignedIn(page, signIn, '/stores', 'sardor@rizqim.uz')
  await expect(page.getByRole('list', { name: "Ovqatxonalar ro'yxati" })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Excel yuklab olish' })).toHaveCount(0)
})
