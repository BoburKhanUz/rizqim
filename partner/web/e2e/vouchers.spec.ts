import { expect, openSignedIn, OWNER, test } from './fixtures'

// P6-43 "Sertifikatlar": the owner reads the certificates' report and list, opens one's history, blocks a lost one with a
// reason (and unblocks it), and downloads the Excel file.

test('the report, the list and a certificate history', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/vouchers')
  await expect(page.getByRole('heading', { name: "Sovg'a sertifikatlari", level: 1 })).toBeVisible()
  await expect(page.getByText('Qolgan majburiyat')).toBeVisible()
  const list = page.getByRole('table', { name: 'Sertifikatlar' })
  await expect(list).toContainText('K7QM-4XPA-9HTR')
  await expect(list).toContainText('MNPQ-3469-ACDE')

  await page.getByRole('button', { name: 'K7QM-4XPA-9HTR tarixi' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('table', { name: 'Harakatlar' })).toContainText("To'lovda ishlatildi")
  await dialog.getByRole('button', { name: 'Yopish' }).first().click()
  await expect(dialog).toHaveCount(0)
})

test('a lost certificate is blocked with a reason and unblocked', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/vouchers')
  await page.getByRole('button', { name: 'MNPQ-3469-ACDE ni bloklash' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Sabab').fill("Mijoz yo'qotdi")
  await dialog.getByRole('button', { name: 'Bloklash' }).click()
  await expect(page.getByText('MNPQ-3469-ACDE bloklandi.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/vouchers/dddddddd-0000-4000-8000-000000000002/block')[0].body).toEqual({ reason: "Mijoz yo'qotdi" })

  await page.getByRole('button', { name: 'Bloklangan', exact: true }).click()
  await expect(page.getByRole('table', { name: 'Sertifikatlar' })).not.toContainText('K7QM-4XPA-9HTR')
  await page.getByRole('button', { name: 'MNPQ-3469-ACDE ni blokdan chiqarish' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Blokdan chiqarish' }).click()
  await expect(page.getByText('MNPQ-3469-ACDE blokdan chiqarildi.')).toBeVisible()
})

test('the Excel file is downloaded', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/vouchers')
  await expect(page.getByRole('table', { name: 'Sertifikatlar' })).toBeVisible()
  const [file] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
  expect(file.suggestedFilename()).toMatch(/^sertifikatlar-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.xlsx$/)
})

test('a reports-only reader sees the codes masked and no block button', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/vouchers', { ...OWNER, id: 9, name: 'Dilshod', role: 'manager', phone: '+998901112233', permissions: ['reports.view'] })
  const list = page.getByRole('table', { name: 'Sertifikatlar' })
  await expect(list).toContainText('K7QM-****-9HTR')
  await expect(list).not.toContainText('K7QM-4XPA-9HTR')
  await expect(page.getByText(/Kodlar yashirilgan/)).toBeVisible()
  await expect(page.getByRole('button', { name: /ni bloklash$/ })).toHaveCount(0)
})
