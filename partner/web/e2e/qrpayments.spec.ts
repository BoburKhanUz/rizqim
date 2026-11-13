import { expect, openSignedIn, test } from './fixtures'

// P6-41/P6-42: the owner's "QR to'lov" tab of the POS setup page — the providers the till offers (with the store's own
// merchant status), the default one and the QR's validity; it comes with the qr_payments module.

test('a branch without the module has no QR tab', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await expect(page.getByRole('tab', { name: 'Format', selected: true })).toBeVisible()
  await expect(page.getByRole('tab', { name: "QR to'lov" })).toHaveCount(0)
})

test('the owner turns a provider off and sets the validity; only the changes are sent', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { qr_payments: true }
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('tab', { name: "QR to'lov" }).click()
  await expect(page.getByRole('checkbox', { name: /^Click/ })).toBeChecked()
  await expect(page.getByText('Ulangan', { exact: true })).toHaveCount(1)
  await expect(page.getByText('Ulanmagan', { exact: true })).toHaveCount(2)
  await expect(page.getByLabel('Birinchi taklif qilinadigan')).toHaveValue('click')
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()

  // The form checks the range before the server
  await page.getByLabel('QR amal qilish vaqti').fill('45')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('1 dan 30 gacha butun son kiriting.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/qr-payments')).toHaveLength(0)

  await page.getByRole('checkbox', { name: /^UzQR/ }).uncheck()
  await page.getByLabel('Birinchi taklif qilinadigan').selectOption('payme')
  await page.getByLabel('QR amal qilish vaqti').fill('10')
  await page.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText("QR to'lov sozlamalari saqlandi.")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/qr-payments')[0].body).toEqual({ branch_id: 1, providers: ['click', 'payme'], default_provider: 'payme', expiry_minutes: 10 })

  await page.reload()
  await page.getByRole('tab', { name: "QR to'lov" }).click()
  await expect(page.getByRole('checkbox', { name: /^UzQR/ })).not.toBeChecked()
  await expect(page.getByLabel('Birinchi taklif qilinadigan')).toHaveValue('payme')
  await expect(page.getByLabel('QR amal qilish vaqti')).toHaveValue('10')
})
