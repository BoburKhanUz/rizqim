import { OPS_EVENT } from './fake/banquetops'
import { expect, openSignedIn, test } from './fixtures'

// P6-54: the owner reads an event's production sheet (dishes by station, the osh kazans) and downloads it, writes the
// guests who came on the final settlement, gives the contract its number and prints it, reads the shopping list and
// saves the production settings.

test('the production sheet and its Excel file', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/banquets/production?event=${OPS_EVENT}`)
  await expect(page.getByRole('heading', { name: "Ishlab chiqarish varag'i", level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Osh sexi' })
  await expect(table).toContainText("To'y oshi")
  await expect(table).toContainText('220 porsiya')
  await expect(page.getByText('1 × 50 kg')).toBeVisible()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Excel yuklab olish' }).click()
  expect((await download).suggestedFilename()).toBe('ishlab-chiqarish-2026-11-20.xlsx')
})

test('the guests who came change the final settlement', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/banquets/settlement?event=${OPS_EVENT}`)
  const table = page.getByRole('table', { name: 'Yakuniy hisob' })
  await expect(table).toContainText('200 kishi')
  await page.getByLabel('Kelgan mehmonlar').fill('260')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Kelgan mehmonlar soni saqlandi.')).toBeVisible()
  await expect(table).toContainText('260 kishi')
  expect(api.callsTo('PATCH', `/v1/pos/banquets/${OPS_EVENT}/guests`)[0].body).toEqual({ guests_actual: 260 })

  // The event's other pages from here
  await page.getByRole('link', { name: 'Shartnoma' }).click()
  await expect(page.getByTestId('contract-text')).toContainText('Karimovlar oilasi')
  await page.getByRole('button', { name: 'Raqam berish' }).click()
  await expect(page.getByText('Shartnoma raqami berildi: 2026-0001.')).toBeVisible()
  await expect(page.getByTestId('contract-text')).toContainText('2026-0001')
})

test('the shopping list and the production settings', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets/shopping')
  await expect(page.getByRole('table', { name: 'Osh masalliqlari' })).toContainText('Guruch')
  await expect(page.getByRole('table', { name: 'Taomlar' })).toContainText('220 dona')
  await expect(page.getByRole('link', { name: "Aziz va Malika to'yi" })).toBeVisible()

  await page.getByRole('link', { name: 'Ishlab chiqarish sozlamalari' }).click()
  await expect(page.getByRole('heading', { name: 'Ishlab chiqarish va shartnoma sozlamalari', level: 1 })).toBeVisible()
  await page.getByLabel('Zaxira, %').fill('15')
  await page.getByLabel('1 kg oshga kishi').fill('6')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Sozlamalar saqlandi.')).toBeVisible()
  const put = api.callsTo('PUT', '/v1/owner/pos/banquets/ops-settings')[0].body as { version: number; reserve_percent: string; osh: { persons_per_kg: string; kazans: number[] } }
  expect(put.version).toBe(1)
  expect(put.reserve_percent).toBe('15')
  expect(put.osh.persons_per_kg).toBe('6')
  expect(put.osh.kazans).toEqual([10, 20, 50])
})
