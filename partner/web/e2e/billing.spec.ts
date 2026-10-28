import { expect, openSignedIn, test } from './fixtures'

// Tarif va to'lov (P4-21): the plan page, the unpaid-invoice banner across the panel, the "Asosiy tarifda bor" locks and the
// server's own refusal in plain words. The backend decides what the plan allows; the panel hides and explains.

const bannerName = "To'lanmagan hisob-faktura haqida"

test('the plan page shows the plan, the trial, the devices, the locked features and the invoices', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.billing.assignPlan('start')
  api.billing.startTrial(5)
  await openSignedIn(page, signIn, '/dashboard')

  // From the menu
  await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: "Tarif va to'lov" }).click()
  await expect(page).toHaveURL(/\/billing$/)

  const main = page.getByRole('main')
  await expect(main.getByRole('heading', { name: "Tarif va to'lov" })).toBeVisible()
  await expect(main.getByText('Start', { exact: true })).toBeVisible()
  await expect(main.getByText('Sinov davri', { exact: true }).first()).toBeVisible()
  await expect(main.getByText(/Sinov davri: [56] kun qoldi/)).toBeVisible()
  await expect(main.getByText("149 000 so'm")).toBeVisible()

  // The till and the kitchen display with the price of an extra one; waiter phones are free
  const devices = main.getByRole('heading', { name: 'Qurilmalar' }).locator('..')
  await expect(devices.getByText('1 / 1 ta')).toBeVisible()
  await expect(devices.getByText("Qo'shimcha qurilma: 59 000 so'm oyiga")).toBeVisible()
  await expect(devices.getByText("Qo'shimcha qurilma: 29 000 so'm oyiga")).toBeVisible()
  await expect(devices.getByText('Cheksiz va bepul')).toBeVisible()

  // What the plan does not have says which plan does
  const features = main.getByRole('heading', { name: 'Funksiyalar' }).locator('xpath=ancestor::section[1]')
  await expect(features.getByText('Asosiy tarifda bor')).toHaveCount(5)
  await expect(features.getByText('Pro tarifda bor')).toHaveCount(3)

  // The invoices, and how to pay
  await expect(main.getByRole('table', { name: 'Hisob-fakturalar' }).getByText('OB-202609-000041')).toBeVisible()
  await expect(main.getByText("Kassa har doim ishlaydi: to'lov qabul qilish, chek chiqarish va smena to'xtamaydi.")).toBeVisible()
  await expect(main.getByRole('link', { name: "Hisobni to'ldirish" })).toHaveAttribute('href', '/finance')
  expect(api.callsTo('GET', '/v1/owner/billing/entitlements').length).toBeGreaterThan(0)
})

test('an overdue invoice shows the banner on every page, a week later the lock, and paying removes it', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.billing.makeOverdue(3)
  await openSignedIn(page, signIn, '/dashboard')

  const banner = page.getByRole('status', { name: bannerName })
  await expect(banner).toContainText("To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi")
  await expect(banner).toContainText("OB-202610-000042 hisob-faktura, qoldiq 249 000 so'm")
  await expect(banner).toContainText('Kassa ishlashda davom etadi')

  // It follows the person to another page, and its link leads to the plan page
  await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Buyurtmalar' }).click()
  await expect(banner).toBeVisible()
  await banner.getByRole('link', { name: "To'lash" }).click()
  await expect(page).toHaveURL(/\/billing$/)
  const row = page.getByRole('table', { name: 'Hisob-fakturalar' }).getByRole('row').nth(1)
  await expect(row).toContainText('OB-202610-000042')
  await expect(row).toContainText("249 000 so'm")
  await expect(row).toContainText("Muddati o'tgan")

  // A week past the due date: settings are locked
  api.billing.makeOverdue(8)
  await page.reload()
  const locked = page.getByRole('alert', { name: bannerName })
  await expect(locked).toContainText("Sozlamalar yopiq — to'lov qiling")
  await expect(locked).toContainText("To'lov qilinishi bilan darhol ochiladi")
  await expect(page.getByRole('main').getByText('Sozlamalar yopiq', { exact: true })).toBeVisible()

  // Paying opens everything at once
  api.billing.pay()
  await page.reload()
  await expect(page.getByRole('main').getByRole('heading', { name: "Tarif va to'lov" })).toBeVisible()
  await expect(page.getByRole('status', { name: bannerName })).toHaveCount(0)
  await expect(page.getByRole('alert', { name: bannerName })).toHaveCount(0)
})

test('on the Start plan a new booking is locked with "Asosiy tarifda bor"; upgrading opens it', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.billing.assignPlan('start')
  await openSignedIn(page, signIn, '/bookings')
  await page.getByLabel('Sana').fill('2026-12-05')

  await expect(page.getByText('Bron — Asosiy tarifda bor')).toBeVisible()
  await expect(page.getByRole('button', { name: "Bron qo'shish", exact: true })).toBeDisabled()
  // The calendar itself is still there
  await expect(page.getByRole('group', { name: 'Xona 1' })).toBeVisible()
  expect(api.callsTo('POST', '/v1/pos/bookings')).toHaveLength(0)

  // The owner moves to Asosiy: the page follows on the next read and the button works
  api.billing.assignPlan('asosiy')
  await page.reload()
  await page.getByLabel('Sana').fill('2026-12-05')
  await expect(page.getByRole('button', { name: "Bron qo'shish", exact: true })).toBeEnabled()
  await expect(page.getByText('Bron — Asosiy tarifda bor')).toHaveCount(0)
})

test('a server refusal on a screen that has not heard of the plan change is shown in the same plain words', async ({ page, api, signIn, reverb }) => {
  void reverb
  // The page believes the plan has bookings; the server says it does not
  api.billing.refuseBookings = true
  await openSignedIn(page, signIn, '/bookings')
  await page.getByLabel('Sana').fill('2026-12-05')
  await page.getByRole('button', { name: "Xona 1: bron qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Ismi (yoki guruh nomi)').fill('Aziz Karimov')
  await dialog.getByLabel('Telefon').fill('90 123 45 67')
  await dialog.getByLabel('Mehmonlar soni').fill('30')
  await dialog.getByLabel('Boshlanishi').fill('19:00')
  await dialog.getByLabel('Tugashi').fill('23:00')
  await dialog.getByRole('button', { name: 'Saqlash', exact: true }).click()

  await expect(dialog.getByText("Bron Asosiy tarifda bor. Hozirgi tarifingizda yo'q.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/pos/bookings')[0].status).toBe(403)
  expect(api.bookings.bookings).toHaveLength(0)
})

test('the plan page works on a phone', async ({ page, api, signIn, reverb }) => {
  void reverb
  await page.setViewportSize({ width: 375, height: 800 })
  api.billing.makeOverdue(2)
  await openSignedIn(page, signIn, '/billing')
  await expect(page.getByRole('status', { name: bannerName })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Joriy tarif' })).toBeVisible()
  // Nothing pushes the page sideways
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
  expect(overflow).toBeLessThanOrEqual(0)
})
