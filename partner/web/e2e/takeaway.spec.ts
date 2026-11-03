import { expect, openSignedIn, test } from './fixtures'

// P6-34: the takeaway owner screens — the packaging rules, the pickup-time rules and the 24/7 business days, tabs of the POS
// setup page that appear with their modules.

test('a choyxona sees no takeaway tabs', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await expect(page.getByRole('tab', { name: 'Format', selected: true })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Qadoq qoidalari' })).toHaveCount(0)
  await expect(page.getByRole('tab', { name: 'Oldindan buyurtma' })).toHaveCount(0)
  await expect(page.getByRole('tab', { name: '24/7' })).toHaveCount(0)
})

test('the owner adds a bag per order and a bowl per soup, edits and deletes one', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { packaging: true }
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('tab', { name: 'Qadoq qoidalari' }).click()
  await expect(page.getByText("Qadoq qoidasi yo'q")).toBeVisible()

  await page.getByRole('button', { name: "Qoida qo'shish" }).click()
  const bag = page.getByRole('form', { name: "Qoida qo'shish" })
  await bag.getByLabel('Qadoq nomi').fill('Paket')
  await bag.getByLabel('Narxi (bir dona)').fill('1000')
  await bag.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Qoida saqlandi.')).toBeVisible()
  const list = page.getByRole('list', { name: 'Qadoq qoidalari' })
  await expect(list.getByText('Paket')).toBeVisible()
  await expect(list.getByText(/1 dona har buyurtmaga/)).toBeVisible()

  await page.getByRole('button', { name: "Qoida qo'shish" }).click()
  const bowl = page.getByRole('form', { name: "Qoida qo'shish" })
  await bowl.getByLabel('Qadoq nomi').fill("Sho'rva idishi")
  await bowl.getByLabel('Qanday hisoblanadi').selectOption('item')
  await bowl.getByLabel('Kategoriya', { exact: true }).selectOption('2')
  await bowl.getByLabel('Narxi (bir dona)').fill('3000')
  await bowl.getByLabel('MXIK').fill('03924000001000001')
  await bowl.getByRole('checkbox', { name: 'Yetkazib berish' }).uncheck()
  await bowl.getByRole('button', { name: 'Saqlash' }).click()
  await expect(list.getByText(/1 dona har porsiyaga: Issiq taomlar/)).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/branches/1/packaging-rules')[1].body).toEqual({
    name: "Sho'rva idishi",
    basis: 'item',
    category_id: 2,
    product_id: null,
    quantity: 1,
    unit_price: '3000.00',
    mxik: '03924000001000001',
    package_code: null,
    unit: 'dona',
    order_types: ['takeaway', 'pickup'],
    is_active: true,
  })

  // The bag goes up to 1 500, then the bag rule is deleted
  await list.getByRole('listitem').filter({ hasText: 'Paket' }).getByRole('button', { name: "O'zgartirish" }).click()
  await page.getByRole('form', { name: 'Paket' }).getByLabel('Narxi (bir dona)').fill('1500')
  await page.getByRole('form', { name: 'Paket' }).getByRole('button', { name: 'Saqlash' }).click()
  await expect(list.getByText(/1\s500/)).toBeVisible()

  page.once('dialog', (dialog) => void dialog.accept())
  await list.getByRole('listitem').filter({ hasText: 'Paket' }).getByRole('button', { name: "O'chirish" }).click()
  await expect(page.getByText("Qoida o'chirildi.")).toBeVisible()
  await expect(list.getByRole('listitem')).toHaveCount(1)
})

test('an item rule without dishes is stopped in the form', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { packaging: true }
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Qadoq qoidalari' }).click()
  await page.getByRole('button', { name: "Qoida qo'shish" }).click()
  const form = page.getByRole('form', { name: "Qoida qo'shish" })
  await form.getByLabel('Qanday hisoblanadi').selectOption('item')
  await form.getByRole('button', { name: 'Saqlash' }).click()
  await expect(form.getByText('Kategoriya yoki taomni tanlang.')).toBeVisible()
  await expect(form.getByText('Nomini yozing.')).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/branches/1/packaging-rules')).toHaveLength(0)
})

test('the owner sets the pickup-time rules and only the changes are sent', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { scheduled_pickup: true }
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Oldindan buyurtma' }).click()
  await expect(page.getByText(/Ish vaqti: 09:00–22:00/)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()

  await page.getByLabel('Eng kamida (daqiqa oldin)').fill('30')
  await page.getByLabel('Tayyorlash vaqti (daqiqa)').fill('25')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Olib ketish vaqti sozlamalari saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/pickup-settings')[0].body).toEqual({ pickup_min_lead_minutes: 30, pickup_prep_minutes: 25 })

  await page.getByLabel('Vaqt qadami (daqiqa)').fill('1')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('5 dan 120 gacha butun son.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/pickup-settings')).toHaveLength(1)
})

test('a 24/7 branch reads its business days with their shifts', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { round_the_clock: true }
  api.takeaway.businessDays = [
    { business_date: '2026-10-22', started_at: '2026-10-22T00:00:00.000000Z', ends_at: '2026-10-23T00:00:00.000000Z', orders: 3, pos_orders: 3, online_orders: 0, revenue: '111000.00', packaging_amount: '3000.00', after_midnight_orders: 2, first_order_at: null, last_order_at: null, shifts: [{ uuid: 's1', business_date: '2026-10-22', z_number: 7, opened_at: null, closed_at: null, status: 'closed' }] },
    { business_date: '2026-10-23', started_at: '2026-10-23T00:00:00.000000Z', ends_at: '2026-10-24T00:00:00.000000Z', orders: 2, pos_orders: 2, online_orders: 0, revenue: '74000.00', packaging_amount: '2000.00', after_midnight_orders: 0, first_order_at: null, last_order_at: null, shifts: [] },
  ]
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: '24/7' }).click()
  await expect(page.getByText(/23:59, 00:00 va 04:59 dagi buyurtmalar oldingi kunniki/)).toBeVisible()

  const table = page.getByRole('table', { name: 'Biznes kunlar' })
  await expect(table.getByRole('row').filter({ hasText: '2026-10-22' })).toContainText('Z-7')
  await expect(table.getByRole('row').filter({ hasText: '2026-10-23' })).toContainText("Smena yo'q")

  await page.getByLabel('Dan').fill('2026-10-01')
  await page.getByLabel('Gacha').fill('2026-10-22')
  await page.getByRole('button', { name: 'Biznes kunlar' }).click()
  await expect.poll(() => api.callsTo('GET', '/v1/owner/branches/1/business-days').at(-1)!.query).toMatchObject({ from: '2026-10-01', to: '2026-10-22' })
})
