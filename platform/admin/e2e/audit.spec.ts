import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// Tizim → Harakatlar jurnali: the list, the filters, the cursor and the detail with before/after. Entries of the actions the
// other specs do also show here: change something, then read it in the log.

test('a change made in the app is in the log with before and after, and the log filters by action', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/team')
  await page.getByRole('heading', { name: /^Sardor Operator/ }).locator('xpath=ancestor::li[1]').getByRole('button', { name: /Rolni o'zgartirish/ }).click()
  await page.getByRole('dialog').getByLabel('Roli').selectOption('support')
  await page.getByRole('dialog').getByRole('button', { name: 'Saqlash' }).click()
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Sardor Operator endi «Yordam».')).toBeVisible()

  await page.getByRole('link', { name: 'Harakatlar jurnali' }).click()
  await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()
  const list = page.getByRole('list', { name: 'Jurnal yozuvlari' })
  await expect(list.getByRole('listitem').first()).toContainText('admin.team.updated')
  await expect(list.getByRole('listitem').first()).toContainText('Aziza Karimova')

  // Filter by the start of the action
  await page.getByLabel('Amal', { exact: true }).fill('admin.team')
  await page.getByRole('button', { name: "Qo'llash" }).click()
  await expect(list.getByRole('listitem')).toHaveCount(1)
  expect(api.callsTo('GET', '/audit-logs').at(-1)?.query.action).toBe('admin.team')

  // The detail: who, from where, and the field that changed
  await list.getByRole('button', { name: 'admin.team.updated: batafsil' }).click()
  const detail = page.getByRole('dialog', { name: 'Yozuv tafsiloti' })
  await expect(detail).toContainText('Aziza Karimova')
  await expect(detail).toContainText('127.0.0.1')
  const row = detail.getByRole('row').filter({ hasText: 'platform_role' })
  await expect(row).toContainText('operator')
  await expect(row).toContainText('support')
  await page.keyboard.press('Escape')

  await page.getByRole('button', { name: 'Tozalash' }).click()
  await expect(list.getByRole('listitem').first()).toBeVisible()
})

test('a long log loads more by cursor, newest first', async ({ page, api, signIn }) => {
  api.seedAudit(45)
  await openSignedIn(page, signIn, '/audit')
  const list = page.getByRole('list', { name: 'Jurnal yozuvlari' })
  await expect(list.getByRole('listitem')).toHaveCount(30)
  await page.getByRole('button', { name: 'Yana yuklash' }).click()
  await expect(list.getByRole('listitem').nth(30)).toBeVisible()
  expect(await list.getByRole('listitem').count()).toBe(45)
  await expect(page.getByRole('button', { name: 'Yana yuklash' })).toHaveCount(0)
  expect(api.callsTo('GET', '/audit-logs').at(-1)?.query.cursor).toBeTruthy()
})

test('filters that match nothing say so, and a member without audit.view cannot open the page', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/audit')
  await page.getByRole('searchbox', { name: 'Qidirish' }).fill('no-such-thing')
  await page.getByRole('button', { name: "Qo'llash" }).click()
  await expect(page.getByText("Bu filtrlar bo'yicha yozuv topilmadi")).toBeVisible()
})

test('an operator has no journal in the menu and is sent away from its address', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/audit', 'sardor@rizqim.uz')
  await expect(page).toHaveURL(/\/today$/)
  await expect(page.getByRole('link', { name: 'Harakatlar jurnali' })).toHaveCount(0)
})
