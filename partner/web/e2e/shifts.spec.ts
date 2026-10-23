import { expect, openSignedIn, test } from './fixtures'

// Smenalar: the Z report of a closed shift, with the cash count and the difference in words, and a print button.

test('the Z report of a closed shift is read and printed', async ({ page, api, signIn, reverb }) => {
  void reverb
  await page.addInitScript(() => {
    ;(window as unknown as { printed: number }).printed = 0
    window.print = () => {
      ;(window as unknown as { printed: number }).printed += 1
    }
  })
  await openSignedIn(page, signIn, '/shifts')

  const list = page.getByRole('table', { name: 'Smenalar' })
  await expect(list.getByText('Kassa 1')).toBeVisible()
  await expect(list.getByText(/Kam:\s10\s000\sso'm/)).toBeVisible()
  await page.getByRole('button', { name: 'Hisobotni ochish: 2026-10-21, Z 12' }).click()

  const report = page.getByRole('article', { name: 'Z-hisobot № 12' })
  await expect(report).toBeVisible()
  await expect(report.getByText(/Chilonzor/)).toBeVisible()
  // sales and cash, tenders, waiters, deposits, credit, the cash difference
  await expect(report.getByText('Sof tushum')).toBeVisible()
  await expect(report.getByText(/3\s150\s000\sso'm/).first()).toBeVisible()
  await expect(report.getByRole('table', { name: "To'lov turlari" }).getByText('Avans hisobidan')).toBeVisible()
  await expect(report.getByRole('table', { name: 'Ofitsiantlar' }).getByText('Ofitsiant Vali')).toBeVisible()
  await expect(report.getByText(/400\s000\sso'm\s\(2\)/)).toBeVisible()
  await expect(report.getByText(/80\s000\sso'm\s\(1\)/)).toBeVisible()
  await expect(report.getByText(/1\s730\s000\sso'm/).first()).toBeVisible()
  await expect(report.getByText(/1\s720\s000\sso'm/).first()).toBeVisible()
  await expect(report.getByText(/Kam:\s10\s000\sso'm/)).toBeVisible()
  await expect(report.getByText(/Kupyuralar:\s100\s000\s×\s17,\s10\s000\s×\s2/)).toBeVisible()

  // A closed shift's Z is one read: no X report is asked for
  expect(api.calls.some((call) => call.path.endsWith('/x-report'))).toBe(false)

  await page.getByRole('button', { name: 'Chop etish' }).click()
  expect(await page.evaluate(() => (window as unknown as { printed: number }).printed)).toBe(1)

  // And the way back
  await page.getByRole('button', { name: "Ro'yxatga" }).click()
  await expect(page.getByRole('table', { name: 'Smenalar' })).toBeVisible()
})

test('a person who may not read shifts is told so', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/orders', { id: 9, name: 'Dilshod', role: 'manager', phone: '+998901112233', storeId: 1, storeName: 'Lazzat Oshxona', branchId: 1, branchName: 'Chilonzor', permissions: ['orders.view', 'kitchen.manage'] })
  await expect(page.getByRole('link', { name: 'Smenalar' })).toHaveCount(0)
  await page.goto('/shifts')
  // the shell sends a section the person may not open to the first one they may
  await expect(page).toHaveURL(/\/(orders|dashboard)/)
})
