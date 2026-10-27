import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// Tizim → Rollar: the matrix. A switch is one PUT, confirmed with the 2FA code the first time; a risky permission asks first;
// the superadmin column is locked.

test('the matrix shows the six roles, the superadmin column locked, and a switch changes a role after the step-up', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/roles')
  await expect(page.getByRole('heading', { name: 'Rollar', level: 1 })).toBeVisible()
  const table = page.getByRole('table', { name: 'Rollar va ruxsatlar jadvali' })
  await expect(table.getByRole('columnheader')).toHaveText(['Ruxsat', 'SuperadminQulflangan', 'Operator', 'Yordam', 'Moliya', 'Savdo / ulash', "Faqat ko'rish"])

  const locked = table.getByRole('switch', { name: "Superadmin: Jamoani boshqarish" })
  await expect(locked).toBeChecked()
  await expect(locked).toBeDisabled()
  await expect(table.getByRole('switch', { name: "Operator: Buyurtmaga aralashish" })).toBeChecked()
  await expect(table.getByRole('switch', { name: "Yordam: Buyurtmaga aralashish" })).not.toBeChecked()

  await table.getByRole('switch', { name: "Moliya: Bugun sahifasini ko'rish" }).click()
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText("«Moliya» roliga «Bugun sahifasini ko'rish» ruxsati berildi.")).toBeVisible()
  await expect(table.getByRole('switch', { name: "Moliya: Bugun sahifasini ko'rish" })).toBeChecked()
  expect(api.grants.finance).toContain('admin.today.view')
  expect(api.callsTo('PUT', '/roles')[1]?.body).toEqual({ role: 'finance', permission: 'admin.today.view', allowed: true })

  // And back: confirmed already, so no second question
  await table.getByRole('switch', { name: "Moliya: Bugun sahifasini ko'rish" }).click()
  await expect(page.getByText("«Moliya» rolidan «Bugun sahifasini ko'rish» ruxsati olindi.")).toBeVisible()
  expect(api.callsTo('POST', '/auth/confirm')).toHaveLength(1)
})

test('a risky permission asks first, and backing out sends nothing', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/roles', 'aziza@rizqim.uz', { confirmed: true })
  const table = page.getByRole('table', { name: 'Rollar va ruxsatlar jadvali' })
  await table.getByRole('switch', { name: 'Yordam: Payoutni tasdiqlash' }).click()
  const ask = page.getByRole('alertdialog', { name: '«Payoutni tasdiqlash» ruxsatini berasizmi?' })
  await expect(ask).toContainText('«Yordam» rolidagi barcha xodimlar')
  await ask.getByRole('button', { name: 'Bekor qilish' }).click()
  expect(api.callsTo('PUT', '/roles')).toHaveLength(0)

  await table.getByRole('switch', { name: 'Yordam: Payoutni tasdiqlash' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ruxsat berish' }).click()
  await expect(page.getByText('ruxsati berildi.')).toBeVisible()
  expect(api.grants.support).toContain('admin.payouts.approve')
})

test('a role change shows in the menu of that role\'s members after they reload', async ({ page, browser, api, signIn }) => {
  await openSignedIn(page, signIn, '/roles', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('table').getByRole('switch', { name: 'Savdo / ulash: Moliyani ko\'rish' }).click()
  await expect(page.getByText(/ruxsati berildi/)).toBeVisible()

  // A sales member who is signed in elsewhere now has the Moliya section
  api.addMember({ name: 'Savdo Xodim', email: 'savdo@rizqim.uz', platform_role: 'sales', twoFactor: true })
  const context = await browser.newContext({ baseURL: page.url().split('/').slice(0, 3).join('/') })
  const token = api.seedSession('savdo@rizqim.uz')
  await context.addCookies([{ name: 'rz_admin', value: token, domain: '127.0.0.1', path: '/', httpOnly: true, sameSite: 'Strict', secure: false }])
  const other = await context.newPage()
  await other.route((target) => !target.href.startsWith('http://127.0.0.1'), (route) => route.fulfill({ status: 204 }))
  await other.goto('/stores')
  await expect(other.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Moliya' })).toBeVisible()
  await context.close()
})

test('a member without team.manage does not see the page and is sent elsewhere', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/roles', 'sardor@rizqim.uz')
  await expect(page).toHaveURL(/\/today$/)
  await expect(page.getByRole('link', { name: 'Rollar' })).toHaveCount(0)
})
