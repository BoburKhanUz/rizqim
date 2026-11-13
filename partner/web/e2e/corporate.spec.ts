import { expect, openSignedIn, test } from './fixtures'

// P6-44 "Korxona hisobi": the owner filters the company accounts, opens one, adds a member and pastes a list, turns a talon
// off, reads the monthly statement and downloads its Excel file.

const COMPANY = 'cccccccc-0000-4000-8000-000000000001'

test('a company account: its members, a new one, a pasted list and a talon turned off', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/accounts')
  await page.getByRole('group', { name: 'Hisob turi' }).getByRole('button', { name: 'Korxona' }).click()
  await expect.poll(() => api.callsTo('GET', '/v1/pos/customer-accounts').at(-1)?.query.kind).toBe('corporate')
  await page.getByRole('link', { name: 'Navoiy zavodi: xodimlar va oylik hisobot' }).click()
  await expect(page).toHaveURL(new RegExp(`/accounts/${COMPANY}$`))
  await expect(page.getByRole('heading', { name: 'Navoiy zavodi', level: 1 })).toBeVisible()
  const members = page.getByRole('table', { name: 'Xodimlar' })
  await expect(members).toContainText('5550001111')
  await expect(members).toContainText('NAV-0002')

  await page.getByRole('button', { name: "Xodim qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Ismi va familiyasi').fill('Dilnoza Karimova')
  await dialog.getByLabel('Kunlik limit').fill('50 000')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Dilnoza Karimova qo'shildi. Talon kodi: 1000000003")).toBeVisible()
  expect(api.callsTo('POST', `/v1/pos/customer-accounts/${COMPANY}/members`)[0].body).toEqual({ name: 'Dilnoza Karimova', phone: null, code: null, daily_limit: '50000', meals_per_day: null, note: null })

  await page.getByRole('button', { name: "Ro'yxatdan qo'shish" }).click()
  const bulk = page.getByRole('dialog')
  await bulk.getByLabel("Ro'yxat").fill('Sardor;90 333 44 55\n;901110000')
  await bulk.getByRole('button', { name: "Qo'shish" }).click()
  await expect(bulk.getByText('2-qator: Ism yozilmagan.')).toBeVisible()
  await bulk.getByLabel("Ro'yxat").fill('Sardor;90 333 44 55\nOtabek')
  await bulk.getByRole('button', { name: "Qo'shish" }).click()
  await expect(page.getByText("2 ta xodim qo'shildi.")).toBeVisible()
  await expect(members).toContainText('Otabek')

  await page.getByRole('button', { name: "Aziz Rahimov: talonni o'chirish" }).click()
  await expect(page.getByText('Aziz Rahimov saqlandi.')).toBeVisible()
  expect(api.callsTo('PATCH', `/v1/pos/customer-accounts/${COMPANY}/members/mmmmmmmm-0000-4000-8000-000000000001`)[0].body).toEqual({ is_active: false })
  await expect(page.getByRole('button', { name: 'Aziz Rahimov: talonni yoqish' })).toBeVisible()
})

test('the monthly statement and its Excel file', async ({ page, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, `/accounts/${COMPANY}`)
  await page.getByRole('tab', { name: 'Oylik hisobot' }).click()
  await expect(page.getByRole('region', { name: /Hisob-faktura/ })).toContainText('301234567')
  const table = page.getByRole('table', { name: 'Oylik hisobot' })
  await expect(table).toContainText('Aziz Rahimov')
  await expect(table.getByRole('row').last()).toContainText('Jami')
  const [file] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
  // The server's name (Content-Disposition, exposed by the API's CORS) or, where the header is not readable, the panel's own
  expect(file.suggestedFilename()).toMatch(/^korxona-[a-z-]+-\d{4}-\d{2}\.xlsx$/)
})
