import { expect, openSignedIn, test } from './fixtures'

// P6-25: in a kabobxona (or with "weight later" switched on) a dish's editor offers how it is sold at the till: the ×N
// buttons of a skewer dish, the grill time and the weight rule. Only what the section shows is sent; a choyxona never sees it.

test("a kabobxona's dish gets its skewer buttons, grill time and weight rule", async ({ page, api, signIn, reverb }) => {
  void reverb
  api.format.profile = 'kabobxona'
  api.format.overrides = { weight_later: true }
  await openSignedIn(page, signIn, '/menu/501')

  await expect(page.getByRole('heading', { name: "Kassada sotish (six, og'irlik)" })).toBeVisible()
  await page.getByRole('switch', { name: '×2 tugmasi' }).click()
  await page.getByRole('switch', { name: '×5 tugmasi' }).click()
  await page.getByLabel('Mangal vaqti (daqiqa)').fill('15')
  await page.getByLabel("Og'irlik", { exact: true }).selectOption('cooked')
  await page.getByRole('button', { name: 'Saqlash', exact: true }).last().click()

  await expect(page.getByText('Saqlandi.')).toBeVisible()
  expect(api.callsTo('PATCH', '/v1/owner/products/501/pos-service')[0].body).toEqual({ quick_multipliers: [2, 5], wait_minutes: 15, weight_mode: 'cooked' })
})

test('a choyxona does not see the section', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/menu/501')
  await expect(page.getByRole('heading', { name: 'Modifikator guruhlari' })).toBeVisible()
  // The format was read, and it has neither module
  await expect.poll(() => api.callsTo('GET', '/v1/owner/pos/format').length).toBeGreaterThan(0)
  await expect(page.getByRole('heading', { name: "Kassada sotish (six, og'irlik)" })).toHaveCount(0)
})
