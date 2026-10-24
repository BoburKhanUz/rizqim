import { expect, openSignedIn, test, type Account, OWNER } from './fixtures'

// Huquqlar: the owner reads what each role may do alone, changes it (a risky permission asks first) and sends a row back to
// the default. Only the owner has the page.

test('the owner changes a cashier permission, confirms a risky one and resets it', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/permissions')
  await expect(page.getByRole('heading', { name: 'Huquqlar', level: 1 })).toBeVisible()

  // The roles an owner can change; the cashier's defaults are the owner's decision of 24 October 2026
  await expect(page.getByRole('tab')).toHaveText(['Menejer', 'Kassir', 'Ofitsiant', 'Oshpaz', 'Kuryer'])
  await page.getByRole('tab', { name: 'Kassir' }).click()
  await expect(page.getByRole('switch', { name: 'Chekni qayta chiqarish' })).toBeChecked()
  await expect(page.getByRole('switch', { name: 'Kassaga pul kiritish va chiqarish' })).toBeChecked()
  await expect(page.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked()
  await expect(page.getByRole('switch', { name: 'Buyurtmani bekor qilish' })).not.toBeChecked()

  // A safe permission is saved at once
  await page.getByRole('switch', { name: 'Chekni qayta chiqarish' }).click()
  await expect(page.getByText('Kassir: "Chekni qayta chiqarish" ruxsati olib tashlandi.')).toBeVisible()
  await expect(page.getByRole('switch', { name: 'Chekni qayta chiqarish' })).not.toBeChecked()

  // A risky one asks first; backing out sends nothing
  await page.getByRole('switch', { name: 'Qaytim qilish' }).click()
  const ask = page.getByRole('alertdialog', { name: '"Qaytim qilish" ruxsatini berasizmi?' })
  await expect(ask).toContainText("menejer PIN kodisiz o'zi bajaradi")
  await ask.getByRole('button', { name: 'Bekor qilish' }).click()
  await expect(page.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked()
  expect(api.callsTo('PUT', '/v1/owner/permissions')).toHaveLength(1)

  await page.getByRole('switch', { name: 'Qaytim qilish' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ruxsat berish' }).click()
  await expect(page.getByText('Kassir: "Qaytim qilish" ruxsati berildi.')).toBeVisible()
  const refund = page.getByRole('switch', { name: 'Qaytim qilish' }).locator('xpath=ancestor::li')
  await expect(refund).toContainText("O'zgartirilgan")
  await expect(refund).toContainText("Standart: ruxsat yo'q")

  // Back to the default: null removes the store's override
  await page.getByRole('button', { name: 'Qaytim qilish: standartga qaytarish' }).click()
  await expect(page.getByText('Kassir: "Qaytim qilish" standartga qaytarildi.')).toBeVisible()
  await expect(page.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked()

  expect(api.callsTo('PUT', '/v1/owner/permissions').map((call) => call.body)).toEqual([
    { role: 'cashier', permission: 'pos.reprint_bill', allowed: false },
    { role: 'cashier', permission: 'pos.refund', allowed: true },
    { role: 'cashier', permission: 'pos.refund', allowed: null },
  ])
  // What the server holds: only the reprint is still changed
  expect([...api.permissions.overrides]).toEqual([['cashier:pos.reprint_bill', false]])

  // The change is still there after a reload
  await page.reload()
  await page.getByRole('tab', { name: 'Kassir' }).click()
  await expect(page.getByRole('switch', { name: 'Chekni qayta chiqarish' })).not.toBeChecked()
})

test('a manager has no Huquqlar page', async ({ page, api, signIn, reverb }) => {
  void reverb
  const manager: Account = { ...OWNER, id: 9, name: 'Dilshod', role: 'manager', branchId: 1, branchName: 'Chilonzor', permissions: ['orders.view', 'staff.view'] }
  await openSignedIn(page, signIn, '/orders', manager)
  await expect(page.getByRole('link', { name: 'Xodimlar' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Huquqlar' })).toHaveCount(0)

  // Typing the address does not open it either: the shell sends the person to a section they have
  await page.goto('/permissions')
  await expect(page).toHaveURL(/\/dashboard$/)
  expect(api.callsTo('GET', '/v1/owner/permissions')).toHaveLength(0)
})
