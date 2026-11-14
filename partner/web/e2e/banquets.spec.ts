import { expect, openSignedIn, test } from './fixtures'
import { BANQUET_EVENT } from './fake/banquetpay'

// P6-51/P6-52: the events calendar (halls × days × slots), holding a hall from a free slot and the 409 of a taken one,
// the event page with its lines, the price freeze, and the owner's packages and hall rent.

test('the calendar shows the held evening and holds another hall for a new family', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets')
  await page.getByLabel('Sana').fill('2027-01-15')
  const chip = page.getByRole('link', { name: /Karimovlar oilasi, 18:00–23:00, Vaqtincha band/ })
  await expect(chip).toBeVisible()
  await expect(chip).toContainText('200 mehmon')
  // The taken evening offers no "+"; the free day does
  await expect(page.getByRole('button', { name: 'Oq zal, 2027-01-20, Kechqurun: zalni band qilish' })).toHaveCount(0)

  await page.getByRole('button', { name: "Ko'k zal, 2027-01-20, Kechqurun: zalni band qilish" }).click()
  const dialog = page.getByRole('dialog', { name: 'Zalni band qilish' })
  await dialog.getByLabel('Mijoz').fill('Aliyevlar')
  await dialog.getByLabel('Kafolatlangan mehmonlar').fill('120')
  await dialog.getByLabel('Paket').selectOption('3')
  await dialog.getByRole('checkbox', { name: /Tort/ }).check()
  await dialog.getByRole('button', { name: 'Band qilish' }).click()

  await expect(page.getByText('Aliyevlar uchun zal band qilindi.')).toBeVisible()
  await expect(page.getByRole('link', { name: /Aliyevlar, 18:00–23:00/ })).toBeVisible()
  const body = api.callsTo('POST', '/v1/pos/banquets')[0].body as Record<string, unknown>
  expect(body).toMatchObject({ branch_id: 1, event_date: '2027-01-20', slot: 'evening', hall_ids: [42], customer_name: 'Aliyevlar', guests_guaranteed: 120, banquet_package_id: 3, extras: [{ extra_id: 7, quantity: '1' }] })
})

test('a taken hall and slot is refused by the server', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets')
  await page.getByLabel('Sana').fill('2027-01-15')
  await page.getByRole('button', { name: 'Oq zal, 2027-01-20, Kunduzi: zalni band qilish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Zalni band qilish' })
  await dialog.getByLabel('Vaqt').selectOption('evening')
  await dialog.getByLabel('Mijoz').fill('Boshqa oila')
  await dialog.getByLabel('Kafolatlangan mehmonlar').fill('100')
  await dialog.getByRole('button', { name: 'Band qilish' }).click()
  await expect(dialog.getByText(/Bu vaqtda zal band: Oq zal/)).toBeVisible()
})

test('the event page shows the bill and freezes the prices', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/banquets/${BANQUET_EVENT}`)
  const bill = page.getByRole('table', { name: 'Hisob' })
  await expect(bill).toContainText('Standart')
  await expect(bill).toContainText('Zal ijarasi: Oq zal')
  // The payment schedule of P6-53 sits under the event
  await expect(page.getByRole('table', { name: "To'lov qatorlari" })).toBeVisible()

  await page.getByRole('button', { name: 'Narxlarni muzlatish' }).click()
  await expect(page.getByText('Narxlar muzlatilgan', { exact: true })).toBeVisible()
  expect(api.callsTo('POST', `/v1/pos/banquets/${BANQUET_EVENT}/freeze`)[0].body).toEqual({ version: 1 })

  await page.getByRole('button', { name: 'Muzlatishni bekor qilish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Muzlatishni bekor qilish' })
  await dialog.getByLabel('Sabab').fill('Shartnoma qayta tuzildi')
  await dialog.getByRole('button', { name: 'Muzlatishni bekor qilish' }).click()
  await expect(page.getByText(/Muzlatish bekor qilindi/)).toBeVisible()
  expect(api.callsTo('POST', `/v1/pos/banquets/${BANQUET_EVENT}/unfreeze`)[0].body).toEqual({ version: 2, reason: 'Shartnoma qayta tuzildi' })
})

test('the owner adds a package and sets a hall rent', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/banquets/setup')
  await expect(page.getByRole('table', { name: 'Paketlar' })).toContainText('Standart')

  await page.getByRole('button', { name: "Paket qo'shish" }).click()
  const dialog = page.getByRole('dialog', { name: "Paket qo'shish" })
  await dialog.getByLabel('Paket nomi').fill('Premium')
  await dialog.getByLabel('Har mehmonga narx').fill('250000')
  await dialog.getByLabel('Minimal mehmonlar').fill('150')
  await dialog.getByLabel('Tarkibi').fill('Osh\nShashlik')
  await dialog.getByRole('button', { name: "Taom qo'shish" }).click()
  await dialog.getByLabel('Taom').fill('Osh')
  await dialog.getByLabel('Har mehmonga', { exact: true }).fill('0.4')
  await dialog.getByLabel("O'lchov").selectOption('kg')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Premium paketi saqlandi.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/banquets/packages')[0].body).toEqual({
    name: 'Premium',
    per_person_price: '250000',
    minimum_guests: 150,
    included: ['Osh', 'Shashlik'],
    is_active: true,
    items: [{ name: 'Osh', quantity_per_person: '0.4', unit: 'kg', product_id: null, product_variant_id: null }],
  })

  await page.getByRole('tab', { name: 'Zal ijarasi' }).click()
  const hall = page.getByRole('form', { name: "Ko'k zal" })
  await hall.getByLabel("Ko'k zal: Kunduzi").fill('2500000')
  await hall.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Ko'k zal narxlari saqlandi.")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/banquets/halls/42/rates')[0].body).toEqual({ rates: { morning: null, day: '2500000', evening: '4000000', custom: null } })
})
