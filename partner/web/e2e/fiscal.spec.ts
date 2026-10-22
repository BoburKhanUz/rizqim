import { expect, openSignedIn, test } from './fixtures'

// Fiskal tayyorlik: the products without an MXIK are filled in the table and saved in one go. The fake backend refuses a code
// that is not 17 digits, as the real one does, and a saved product leaves the list.

const MXIK_OSH = '10202001001000000'
const MXIK_LAGMON = '10202001002000000'

test('the MXIK codes of the menu are filled in one table and saved together', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.menu.addProduct({ name: 'Somsa', variants: [{ name: 'dona', price: '8000.00' }] })
  await openSignedIn(page, signIn, '/fiscal')

  await expect(page.getByRole('heading', { name: 'Fiskal', level: 1 })).toBeVisible()
  await expect(page.getByText("MXIK'siz taom va xizmat: 3")).toBeVisible()
  await expect(page.getByLabel('Osh: MXIK kodi')).toBeVisible()

  // A short code is refused on the spot and the row cannot be saved
  await page.getByLabel('Osh: MXIK kodi').fill('12345')
  await expect(page.getByText("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")).toBeVisible()
  await expect(page.getByRole('button', { name: 'Osh: saqlash' })).toBeDisabled()

  await page.getByLabel('Osh: MXIK kodi').fill(MXIK_OSH)
  await page.getByLabel('Lagmon: MXIK kodi').fill(MXIK_LAGMON)
  await page.getByLabel('Lagmon: qadoq kodi').fill('1234567')
  await expect(page.getByRole('button', { name: 'Hammasini saqlash (2)' })).toBeEnabled()
  await page.getByRole('button', { name: 'Hammasini saqlash (2)' }).click()

  await expect(page.getByText('2 ta taom saqlandi.')).toBeVisible()
  await expect(page.getByText("MXIK'siz taom va xizmat: 1")).toBeVisible()
  await expect(page.getByLabel('Osh: MXIK kodi')).toHaveCount(0)
  await expect(page.getByLabel('Somsa: MXIK kodi')).toBeVisible()

  // P3-26: only the fiscal fields went out (PATCH /owner/products/{id}/fiscal); the rest of each product is untouched
  expect(api.calls.filter((call) => call.method === 'POST' && /^\/v1\/owner\/products\/\d+$/.test(call.path))).toHaveLength(0)
  const saves = api.calls.filter((call) => call.method === 'PATCH' && /^\/v1\/owner\/products\/\d+\/fiscal$/.test(call.path))
  expect(saves).toHaveLength(2)
  const bodies = saves.map((call) => call.body)
  expect(bodies).toContainEqual({ mxik_code: MXIK_OSH })
  expect(bodies).toContainEqual({ mxik_code: MXIK_LAGMON, package_code: '1234567' })
  expect(api.menu.products.find((product) => product.name === 'Osh')).toMatchObject({ mxik_code: MXIK_OSH })
  expect(api.menu.products.find((product) => product.name === 'Lagmon')?.variants).toHaveLength(2)
  expect(api.menu.products.find((product) => product.name === 'Osh')).toMatchObject({ name: 'Osh', category_id: 2, is_active: true })
})

test("the server's reason for a refused row stays in the row, and the other rows are saved", async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await page.getByLabel('Osh: MXIK kodi').fill('00000000000000000')
  await page.getByLabel('Lagmon: MXIK kodi').fill(MXIK_LAGMON)
  await page.getByRole('button', { name: 'Hammasini saqlash (2)' }).click()

  await expect(page.getByText("1 ta taom saqlandi.")).toBeVisible()
  await expect(page.getByText("1 ta taom saqlanmadi: sababi qatorda ko'rsatilgan.")).toBeVisible()
  await expect(page.getByText("Bu MXIK tasnifda yo'q.")).toBeVisible()
  await expect(page.getByLabel('Osh: MXIK kodi')).toHaveValue('00000000000000000')
  await expect(page.getByLabel('Lagmon: MXIK kodi')).toHaveCount(0)

  // Fix the refused one and save it by itself
  await page.getByLabel('Osh: MXIK kodi').fill(MXIK_OSH)
  await page.getByRole('button', { name: 'Osh: saqlash' }).click()
  await expect(page.getByText('Hamma taomda MXIK bor')).toBeVisible()
  expect(api.menu.products.map((product) => product.mxik_code)).toEqual([MXIK_OSH, MXIK_LAGMON])
})

test('the fiscal settings show the driver as a test one and save a charge code', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/fiscal')
  await page.getByRole('tab', { name: 'Soliq sozlamalari' }).click()
  await expect(page.getByText(/Hozir cheklar haqiqiy soliq tizimiga yuborilmaydi/)).toBeVisible()
  await expect(page.getByText(/buxgalter bilan tasdiqlanmagan/)).toBeVisible()
  await page.getByLabel('Xona puli: MXIK').fill('1234')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")).toBeVisible()
  await page.getByLabel('Xona puli: MXIK').fill('10202002001000000')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Fiskal sozlamalar saqlandi.')).toBeVisible()
  expect(api.fiscal.charges.room_fee.mxik).toBe('10202002001000000')
  expect(api.callsTo('PUT', '/v1/owner/fiscal/settings')[0].body).toEqual({ fiscal_settings: { enabled: true, charges: { room_fee: { mxik: '10202002001000000' } } } })
})

test('a failed receipt is opened and sent again', async ({ page, api, signIn, reverb }) => {
  void reverb
  const receipt = api.fiscal.addReceipt({ status: 'failed', error: 'Provayder javob bermadi', attempts: 3 })
  await openSignedIn(page, signIn, '/fiscal')
  await page.getByRole('tab', { name: 'Fiskal cheklar' }).click()
  await page.getByRole('button', { name: /Chekni ochish/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByText('Provayder javob bermadi')).toBeVisible()
  await dialog.getByRole('button', { name: 'Qayta yuborish' }).click()
  await expect(dialog.getByText('Chek qayta yuborildi.')).toBeVisible()
  expect(receipt.status).toBe('issued')
})
