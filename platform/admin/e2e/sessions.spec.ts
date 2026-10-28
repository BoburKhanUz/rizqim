import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// Sessiyalarim: the devices this account is signed in on, ending another one (step-up), new recovery codes.

test('lists the sessions, ends another one after the 2FA code and makes new recovery codes', async ({ page, api, signIn }) => {
  // A second sign-in of the same member, from a phone
  api.seedSession('aziza@rizqim.uz', { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1' })
  await openSignedIn(page, signIn, '/sessions')
  await expect(page.getByRole('heading', { name: 'Sessiyalarim', level: 1 })).toBeVisible()

  const list = page.getByRole('list', { name: 'Sessiyalarim' })
  await expect(list.getByRole('listitem')).toHaveCount(2)
  await expect(list.getByText('Shu qurilma')).toHaveCount(1)
  const phone = list.getByRole('listitem').filter({ hasText: 'Safari, iOS' })
  await phone.getByRole('button', { name: /Sessiyani tugatish/ }).click()
  await page.getByRole('alertdialog', { name: 'Sessiyani tugatasizmi?' }).getByRole('button', { name: 'Ha, tugatish' }).click()

  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Sessiya tugatildi.')).toBeVisible()
  await expect(list.getByRole('listitem')).toHaveCount(1)
  expect(api.liveSessions()).toBe(1)

  // New recovery codes: once more the step-up is already good
  await page.getByRole('button', { name: 'Yangi tiklash kodlarini yaratish' }).click()
  const codes = page.getByRole('dialog', { name: 'Yangi tiklash kodlari' })
  await expect(codes.getByRole('list', { name: 'Tiklash kodlari' }).getByRole('listitem')).toHaveCount(10)
  await expect(codes.getByRole('button', { name: 'Yuklab olish (.txt)' })).toBeVisible()
  expect(api.member('aziza@rizqim.uz').recoveryCodes).toHaveLength(10)
})

test('a member with no section but Sessiyalarim lands there', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Bo\'sh Rol', email: 'empty@rizqim.uz', platform_role: 'viewer', twoFactor: true })
  api.grants.viewer = []
  await openSignedIn(page, signIn, '/', 'empty@rizqim.uz')
  await expect(page).toHaveURL(/\/sessions$/)
  await expect(page.getByRole('heading', { name: 'Sessiyalarim', level: 1 })).toBeVisible()
  await expect(page.getByText("Sizda hali hech bir bo'limga ruxsat yo'q")).toBeVisible()
})
