import { expect, openSignedIn, test } from './fixtures'

// P6-23 "Ish haqi": the owner gives the waiters a wage rule, makes the November statement, fines a line, closes it and
// pays the waiter from the open till.

test('a wage rule is added for a role and shown in words', async ({ page, api, signIn, reverb }) => {
  void reverb
  await openSignedIn(page, signIn, '/staff-pay')
  await expect(page.getByRole('heading', { name: 'Ish haqi', level: 1 })).toBeVisible()
  await expect(page.getByText("Hali qoida yo'q")).toBeVisible()

  await page.getByRole('button', { name: "Qoida qo'shish" }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Rol', { exact: true }).selectOption('waiter')
  await dialog.getByLabel('Kuchga kirish sanasi').fill('2026-11-01')
  await dialog.getByLabel("Smena uchun (so'm)").fill('100000')
  await dialog.getByRole('button', { name: "Qism qo'shish" }).click()
  await dialog.getByLabel('Turi').nth(1).selectOption('sales_percent')
  await dialog.getByLabel('Foiz (%)').fill('5')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()

  await expect(dialog).toHaveCount(0)
  const row = page.getByRole('table', { name: 'Qoidalar' }).getByRole('row').nth(1)
  await expect(row).toContainText('Rol: Ofitsiant')
  await expect(row).toContainText('har smena')
  await expect(row).toContainText('Sotuvdan 5%')
  expect(api.callsTo('POST', '/v1/owner/staff-pay/rules')[0].body).toEqual({
    branch_id: 1,
    user_id: null,
    role: 'waiter',
    effective_from: '2026-11-01',
    components: [
      { type: 'shift_rate', amount: '100000' },
      { type: 'sales_percent', percent: '5' },
    ],
    note: null,
  })
})

test('a statement is made, adjusted, closed and paid from the till', async ({ page, api, signIn, reverb }) => {
  void reverb
  api.staffPay.rules.push({ uuid: 'r-1', branch_id: 1, user_id: null, user_name: null, role: 'waiter', role_label: 'Ofitsiant', effective_from: '2026-11-01', components: [{ type: 'shift_rate', label: 'Smena stavkasi', amount: '150000.00' }], note: null, updated_at: null })
  await openSignedIn(page, signIn, '/staff-pay')
  await page.getByRole('tab', { name: 'Hisob-kitob' }).click()
  await page.getByLabel('Davr ichidagi sana').fill('2026-11-15')
  await page.getByRole('button', { name: 'Hisoblash' }).click()

  const lines = page.getByRole('table', { name: "Xodimlar bo'yicha hisob-kitob" })
  const row = lines.getByRole('row').nth(1)
  await expect(row).toContainText('Aziz Ofitsiant')
  await expect(row).toContainText(/300\s000\sso'm/)
  await expect(row).toContainText(/250\s000\sso'm/)
  await row.getByRole('button', { name: 'Tuzatish' }).click()
  const adjust = page.getByRole('dialog')
  await adjust.getByLabel("Summa (so'm)").fill('-20000')
  await adjust.getByLabel('Sabab').fill('Kechikdi')
  await adjust.getByRole('button', { name: 'Saqlash' }).click()
  await expect(row).toContainText('Kechikdi')
  await expect(row).toContainText(/230\s000\sso'm/)

  await page.getByRole('button', { name: 'Yopish' }).click()
  await expect(page.getByRole('button', { name: 'Qayta ochish' })).toBeVisible()
  await row.getByRole('button', { name: "To'lash" }).click()
  const pay = page.getByRole('dialog')
  await expect(pay.getByRole('radio', { name: /Kassadan naqd/ })).toBeChecked()
  await pay.getByRole('button', { name: "To'landi deb belgilash" }).click()
  await expect(row).toContainText("Kassadan to'landi")

  const calls = api.callsTo('POST', /\/lines\/[\w-]+\/pay$/)
  expect(calls).toHaveLength(1)
  expect(calls[0].body).toEqual({ method: 'cash_drawer', shift_uuid: api.staffPay.openShifts[0].uuid, note: null })
  expect(api.callsTo('PUT', /\/lines\/[\w-]+$/)[0].body).toEqual({ adjustment: '-20000', reason: 'Kechikdi' })
})
