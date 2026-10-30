import { expect, openSignedIn, test } from './fixtures'

// P6-22 "Choy puli va xizmat haqi": the owner reads the day's tips, gives a tip to another waiter with a reason,
// switches the branch to a shared pool, splits the day's pool and closes it (or a shift's, when the pool is per shift),
// and sets the service charge variants.

test('the report shows the tips and a tip is given to another waiter with a reason', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/tips')
  await expect(page.getByRole('heading', { name: 'Choy puli va xizmat haqi', level: 1 })).toBeVisible()
  await expect(page.getByRole('table', { name: "Xodimlar bo'yicha" })).toContainText('Aziz Ofitsiant')
  await expect(page.getByRole('table', { name: "Kunlar bo'yicha" })).toContainText("Bo'linmagan")

  const tips = page.getByRole('table', { name: 'Olingan choy pullari' })
  await tips.getByRole('row').nth(1).getByRole('button', { name: 'Tuzatish' }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Kimga').selectOption({ label: 'Bobur Ofitsiant' })
  await dialog.getByLabel('Sabab').fill('Stolga Bobur xizmat qildi')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(dialog).toHaveCount(0)
  await expect(tips.getByRole('row').nth(1)).toContainText('Bobur Ofitsiant')
  expect(api.callsTo('POST', '/v1/owner/pos/tips/cccccccc-0000-4000-8000-000000000001/corrections')[0].body).toEqual({
    amount: '20000',
    mode: 'personal',
    staff_id: 32,
    reason: 'Stolga Bobur xizmat qildi',
  })
})

test('the pool is set up, split equally to the so\'m and closed', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/tips')
  await page.getByRole('tab', { name: 'Choy puli sozlamasi' }).click()
  await page.getByLabel('Choy puli kimga').selectOption('pool')
  await page.getByLabel("Bo'lish qoidasi").selectOption('equal')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Choy puli sozlamasi saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/tips/settings')[0].body).toMatchObject({ branch_id: 1, mode: 'pool', pool_rule: 'equal', roles: ['waiter'] })

  await page.getByRole('tab', { name: "Qozonni bo'lish" }).click()
  await expect(page.getByText("Bo'linish yo'q")).toBeVisible()
  await page.getByLabel('Biznes kun').first().fill('2026-11-16')
  await page.getByRole('button', { name: "Qozonni bo'lish" }).click()
  await expect(page.getByText("Qozon bo'lindi.")).toBeVisible()
  const split = page.getByRole('table', { name: /bo'linishi$/ })
  // 15 001 so'm in two: the so'm left over goes to the first
  await expect(split.getByRole('row').nth(1)).toContainText(/7\s501\sso'm/)
  await expect(split.getByRole('row').nth(2)).toContainText(/7\s500\sso'm/)

  await page.getByRole('button', { name: 'Yopish', exact: true }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Yopish', exact: true }).click()
  await expect(page.getByText("Bo'linish yopildi.")).toBeVisible()
  await expect(page.getByText('Yopilgan').first()).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/tips/distributions')[0].body).toEqual({ branch_id: 1, business_date: '2026-11-16' })
})

test('with a pool per shift the day\'s closed shift is picked and split', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.tips.settings.mode = 'pool'
  api.tips.settings.pool_period = 'shift'
  const shift = { ...api.shifts.shifts[0], uuid: 's0000000-0000-4000-8000-000000000016', business_date: '2026-11-16', opened_at: '2026-11-16T04:00:00+00:00', closed_at: '2026-11-16T12:00:00+00:00', z_number: 16 }
  api.shifts.shifts.push(shift)
  await openSignedIn(page, signIn, '/tips')
  await page.getByRole('tab', { name: "Qozonni bo'lish" }).click()
  await page.getByLabel('Biznes kun').first().fill('2026-11-16')
  const button = page.getByRole('button', { name: "Qozonni bo'lish" })
  await expect(button).toBeDisabled()
  // Only the closed shifts of that day are offered
  const pick = page.getByLabel('Smena', { exact: true })
  await expect(pick.locator('option')).toHaveCount(2)
  await pick.selectOption(shift.uuid)
  await button.click()
  await expect(page.getByText("Qozon bo'lindi.")).toBeVisible()
  await expect(page.getByRole('heading', { name: /Kassa 1/ })).toBeVisible()
  expect(api.callsTo('POST', '/v1/owner/pos/tips/distributions')[0].body).toEqual({ branch_id: 1, business_date: '2026-11-16', shift_uuid: shift.uuid })
})

test('the service charge variants are saved and the bill notice is shown', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/tips')
  await page.getByRole('tab', { name: 'Xizmat haqi qoidalari' }).click()
  await expect(page.getByText('Xizmat haqi 15%.')).toBeVisible()
  await page.getByLabel('Stolga olib kelish').fill('10')
  await page.getByLabel('Katta guruh: necha mehmondan').fill('8')
  await page.getByLabel('Katta guruh foizi').fill('18')
  await page.getByLabel("Kuver (har mehmon uchun, so'm)").fill('5000')
  await page.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Xizmat haqi qoidalari saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/v1/owner/pos/service-charge-rules')[0].body).toEqual({
    branch_id: 1,
    by_order_type: { hall: '15', table_service: '10' },
    large_group_min_guests: 8,
    large_group_percent: '18',
    cover_amount: '5000',
    notice: null,
  })
})
