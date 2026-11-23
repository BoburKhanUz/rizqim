import { expect, openSignedIn, test } from './fixtures'

// Sozlamalar → Do'kon, wave P: a store on its own account enters its own Paylov keys. The six secrets are password fields
// that never show a stored value ("Saqlangan" from has_*), an empty one keeps what is stored, a webhook password shorter
// than 16 characters is refused, and the page says which address to give Paylov.

const WEBHOOK = 'https://api.rizqim.uz/api/v1/payments/paylov/webhook'

test("the store's own Paylov keys: secrets never shown, the stored ones kept, the webhook address said", async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/settings')
  const card = page.locator('div', { has: page.getByRole('heading', { name: "Paylov: o'z hisobingiz kalitlari" }) }).last()
  await expect(card).toBeVisible()
  await expect(card.getByLabel('Paylov Merchant ID')).toHaveValue('4f9c2a7e-1b3d-4e8f-9a6b-0c2d4e6f8a1b')
  for (const label of ['Paylov Consumer key', 'Paylov Consumer secret', 'Paylov API login (username)', 'Paylov API paroli (password)', 'Webhook login (callback login)', 'Webhook paroli (callback password)']) {
    await expect(card.getByLabel(label, { exact: true })).toHaveAttribute('type', 'password')
    await expect(card.getByLabel(label, { exact: true })).toHaveValue('')
  }
  await expect(card.getByText('Saqlangan. Almashtirish uchun yangisini yozing.')).toHaveCount(3)
  await expect(card.getByText('Hali kiritilmagan.')).toHaveCount(3)
  await expect(card.getByText(WEBHOOK)).toBeVisible()
  // No secret reached the page
  const html = await page.content()
  for (const secret of ['ck-1', 'cs-1', 'osh-markazi', 'click-secret', 'payme-secret']) expect(html).not.toContain(secret)

  // A short webhook password is refused before anything is sent
  await card.getByLabel('Webhook paroli (callback password)').fill('qisqa')
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(card.getByText("Paylov webhook paroli kamida 16 belgidan iborat bo'lishi kerak.")).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/settings')).toHaveLength(0)

  await card.getByLabel('Paylov API paroli (password)').fill('Api-Parol-1!')
  await card.getByLabel('Webhook login (callback login)').fill('rizqim-callback')
  await card.getByLabel('Webhook paroli (callback password)').fill('uzun-tasodifiy-parol-2026')
  await page.getByRole('button', { name: 'Saqlash', exact: true }).click()
  await expect(page.getByText('Sozlamalar saqlandi.')).toBeVisible()

  const sent = api.callsTo('POST', '/v1/owner/settings')[0]?.body as { payment_settings: Record<string, unknown> }
  expect(sent.payment_settings.paylov).toEqual({ enabled: true, merchant_id: '4f9c2a7e-1b3d-4e8f-9a6b-0c2d4e6f8a1b', password: 'Api-Parol-1!', callback_login: 'rizqim-callback', callback_password: 'uzun-tasodifiy-parol-2026' })
  expect(api.storeSettings.paylov()).toMatchObject({ consumer_key: 'ck-1', consumer_secret: 'cs-1', username: 'osh-markazi', password: 'Api-Parol-1!', callback_password: 'uzun-tasodifiy-parol-2026' })

  // After a reload all seven read "Saqlangan", still without a value
  await page.reload()
  await expect(card.getByText('Saqlangan. Almashtirish uchun yangisini yozing.')).toHaveCount(6)
  await expect(card.getByLabel('Webhook paroli (callback password)')).toHaveValue('')
})

test('no Paylov keys while RIZQIM has Paylov switched off, nor for a store on the platform account', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.storeSettings.unavailable.paylov = 'switched_off'
  await openSignedIn(page, signIn, '/settings')
  await expect(page.getByRole('checkbox', { name: 'Paylov' })).toBeDisabled()
  await expect(page.getByText("RIZQIM vaqtincha o'chirgan")).toBeVisible()
  await expect(page.getByLabel('Paylov Merchant ID')).toHaveCount(0)

  delete api.storeSettings.unavailable.paylov
  api.storeSettings.accountType = 'platform_account'
  await page.reload()
  await expect(page.getByRole('checkbox', { name: 'Paylov' })).toBeEnabled()
  await expect(page.getByLabel('Paylov Merchant ID')).toHaveCount(0)
  await expect(page.getByText(WEBHOOK)).toHaveCount(0)
})
