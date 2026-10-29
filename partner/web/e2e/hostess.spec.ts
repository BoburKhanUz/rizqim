import { expect, openSignedIn, test } from './fixtures'

// P6-24 / P6-26: the restaurant floor's owner screens — the table booking rules and the read-only waitlist, both tabs of the POS
// setup page that appear with their modules, and the hostess as a staff role.

test('a choyxona sees no restaurant tabs', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/pos-setup')
  await expect(page.getByRole('tab', { name: 'Format', selected: true })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Stol broni' })).toHaveCount(0)
  await expect(page.getByRole('tab', { name: "Kutish ro'yxati" })).toHaveCount(0)
})

test('the owner sets the table booking rules and only the changes are sent', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { table_booking: true }
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('tab', { name: 'Stol broni' }).click()
  const tableSlot = page.getByLabel('Stol (daqiqa)')
  await expect(tableSlot).toHaveValue('120')
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()

  await tableSlot.fill('90')
  await page.getByLabel('Xona uchun (daqiqa)').fill('180')
  await page.getByLabel("Eng ko'pi bilan (kun oldin)").fill('30')
  await page.getByLabel("Bo'nak turi").selectOption('per_guest')
  await page.getByLabel("Bo'nak summasi").fill('50 000')
  await page.getByLabel('Necha kishidan boshlab').fill('6')
  await page.getByRole('checkbox', { name: /Bo'nak olinmaguncha tasdiqlanmasin/ }).check()
  await page.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText('Stol broni sozlamalari saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/booking-rules')[0].body).toEqual({
    table_slot_minutes: 90,
    room_min_advance_minutes: 180,
    max_advance_days: 30,
    deposit_mode: 'per_guest',
    deposit_amount: '50000.00',
    deposit_from_guests: 6,
    deposit_required_to_confirm: true,
  })
  await expect(page.getByLabel('Stol (daqiqa)')).toHaveValue('90')
  await expect(page.getByRole('button', { name: 'Saqlash' })).toBeDisabled()
})

test('a slot outside the limits is stopped in the form', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { table_booking: true }
  await openSignedIn(page, signIn, '/pos-setup')
  await page.getByRole('tab', { name: 'Stol broni' }).click()

  await page.getByLabel('Stol (daqiqa)').fill('10')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Daqiqa 30 dan 480 gacha butun son bo'lishi kerak.")).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/branches/1/booking-rules')).toHaveLength(0)
})

test('the owner reads the hostess waitlist with the numbers masked', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.overrides = { hostess: true }
  api.hostess.waitlist = [
    { uuid: 'w1', business_date: '2026-11-05', guest_name: 'Akmal Karimov', guest_phone_masked: '+99890*****67', party_size: 4, quoted_wait_minutes: 20, status: 'seated', waited_minutes: 35, table_name: 'Stol 5', created_at: '2026-11-05T09:00:00Z' },
    { uuid: 'w2', business_date: '2026-11-05', guest_name: 'Dilnoza', guest_phone_masked: '+99891*****10', party_size: 2, quoted_wait_minutes: 40, status: 'no_show', waited_minutes: 12, table_name: null, created_at: '2026-11-05T09:30:00Z' },
  ]
  await openSignedIn(page, signIn, '/pos-setup')

  await page.getByRole('tab', { name: "Kutish ro'yxati" }).click()
  const table = page.getByRole('table', { name: "Kutish ro'yxati yozuvlari" })
  await expect(table.getByRole('rowheader', { name: /Akmal Karimov/ })).toContainText('+99890*****67')
  await expect(table.getByText('aytilganidan uzoq')).toBeVisible()
  await expect(table.getByText('Joylashtirildi')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Xulosa' })).toContainText('35 daq')

  await page.getByLabel('Holat').selectOption('no_show')
  await expect(table.getByRole('rowheader', { name: /Dilnoza/ })).toBeVisible()
  await expect(table.getByRole('rowheader', { name: /Akmal Karimov/ })).toHaveCount(0)
  expect(api.callsTo('GET', '/v1/owner/waitlist').at(-1)!.query).toMatchObject({ branch_id: '1', status: 'no_show' })
})

test('the owner adds a hostess to the staff', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/staff')
  await page.getByRole('button', { name: "Xodim qo'shish" }).first().click()
  const form = page.getByRole('dialog', { name: 'Yangi xodim' })
  await form.getByLabel('Ismi').fill('Dilfuza Hostes')
  await form.getByLabel('Roli').selectOption('hostess')
  await form.getByLabel('Filial').selectOption({ label: 'Chilonzor' })
  await form.getByRole('button', { name: "Xodim qo'shish" }).click()
  await page.getByRole('dialog', { name: 'Dilfuza Hostes uchun taklif havolasi' }).getByRole('button', { name: 'Tayyor' }).click()

  await expect(page.getByRole('row').filter({ hasText: 'Dilfuza Hostes' })).toContainText('Hostes')
  expect(api.callsTo('POST', '/v1/owner/staff')[0].body).toMatchObject({ name: 'Dilfuza Hostes', role: 'hostess', branch_id: 1 })
})
