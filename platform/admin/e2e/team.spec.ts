import { expect, NEXT_CODE, openSignedIn, test, VALID_CODE } from './fixtures'
import { PASSWORD } from './fake/backend'

// Tizim → Jamoa: invite, change role, disable / enable, reset 2FA, re-issue an invite. Each change is a sensitive action: the
// backend asks for a fresh 2FA code (step-up) once, and the app opens the dialog by itself and repeats the call.

const card = (page: import('@playwright/test').Page, name: string) => page.getByRole('heading', { name: new RegExp(`^${name}`) }).locator('xpath=ancestor::li[1]')

test('a role change asks for the 2FA code once, then goes through', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/team')
  await expect(page.getByRole('heading', { name: 'Jamoa', level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: /^Sardor Operator/ })).toBeVisible()

  await card(page, 'Sardor Operator').getByRole('button', { name: "Rolni o'zgartirish: Sardor Operator" }).click()
  await page.getByRole('dialog', { name: "Rolni o'zgartirish" }).getByLabel('Roli').selectOption('finance')
  await page.getByRole('dialog', { name: "Rolni o'zgartirish" }).getByRole('button', { name: 'Saqlash' }).click()

  // The backend answered 403 reauth_required: the step-up dialog opens
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill('000000')
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(stepUp.getByText(/Ilovada yangi kod chiqquncha \(30 soniya\) kuting/)).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()

  await expect(page.getByText('Sardor Operator endi «Moliya».')).toBeVisible()
  expect(api.member('sardor@rizqim.uz').platform_role).toBe('finance')
  // The change was sent twice (refused, then repeated after the confirmation) and the confirmation once good
  expect(api.callsTo('PATCH', '/team/2').map((call) => call.status)).toEqual([403, 200])

  // Confirmed for ten minutes: the next change does not ask again
  await card(page, 'Sardor Operator').getByRole('button', { name: /Hisobni o'chirish/ }).click()
  await page.getByRole('alertdialog', { name: "Sardor Operator hisobini o'chirasizmi?" }).getByRole('button', { name: "Ha, o'chirish" }).click()
  await expect(page.getByText("Sardor Operator hisobi o'chirildi.")).toBeVisible()
  expect(api.callsTo('POST', '/auth/confirm')).toHaveLength(2)
  expect(api.callsTo('PATCH', '/team/2').map((call) => call.status)).toEqual([403, 200, 200])
  await expect(card(page, 'Sardor Operator').getByText("O'chirilgan")).toBeVisible()
})

test('cancelling the step-up leaves the change undone', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/team')
  await card(page, 'Sardor Operator').getByRole('button', { name: /2FA ni tiklash/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ha, tiklash' }).click()
  await page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' }).getByRole('button', { name: 'Bekor qilish' }).click()
  await expect(page.getByRole('alertdialog', { name: 'Sardor Operator uchun 2FA ni tiklaysizmi?' })).toBeVisible()
  expect(api.member('sardor@rizqim.uz').twoFactor).toBe(true)
})

test('invite: the link is shown once, a new browser accepts it, sets a password and 2FA, and gets the role\'s menu', async ({ page, browser, api, signIn, context }) => {
  await openSignedIn(page, signIn, '/team', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: 'Xodim taklif qilish' }).click()
  const form = page.getByRole('dialog', { name: 'Yangi xodim taklif qilish' })
  await form.getByLabel('Ism').fill('Nodira Savdo')
  await form.getByLabel('Email').fill('nodira@rizqim.uz')
  await form.getByLabel('Roli').selectOption('sales')
  await form.getByRole('button', { name: 'Taklif havolasini yaratish' }).click()

  const link = page.getByRole('dialog', { name: 'Taklif havolasi' })
  await expect(link).toContainText('Havolani Nodira Savdo ga yuboring')
  const url = await link.getByRole('textbox', { name: 'Taklif havolasi' }).inputValue()
  expect(url).toMatch(/\/invite\/invite-/)
  await link.getByRole('button', { name: 'Tayyor' }).click()
  await expect(card(page, 'Nodira Savdo').getByText('Taklif kutilmoqda')).toBeVisible()
  // Gone for good: no input on the page holds it any more
  await expect(page.locator('input[value*="/invite/"]')).toHaveCount(0)

  // The invited person opens it in their own browser
  const other = await browser.newContext({ baseURL: page.url().split('/').slice(0, 3).join('/'), locale: 'en-US', timezoneId: 'Asia/Tashkent' })
  const fresh = await other.newPage()
  await fresh.route((target) => !target.href.startsWith('http://127.0.0.1'), (route) => route.fulfill({ status: 204 }))
  await fresh.goto(new URL(url).pathname)
  await expect(fresh.getByRole('heading', { name: 'Taklifni qabul qilish' })).toBeVisible()
  await fresh.getByLabel('Yangi parol').fill('short')
  await fresh.getByLabel('Parolni takrorlang').fill('short')
  await fresh.getByRole('button', { name: 'Parolni saqlash' }).click()
  await expect(fresh.getByText("Parol kamida 12 ta belgidan iborat bo'lsin.")).toBeVisible()
  await fresh.getByLabel('Yangi parol').fill('nodira-new-password')
  await fresh.getByLabel('Parolni takrorlang').fill('nodira-new-password')
  await fresh.getByRole('button', { name: 'Parolni saqlash' }).click()

  await expect(fresh.getByRole('heading', { name: '2 bosqichli tasdiqlashni yoqing' })).toBeVisible()
  await fresh.getByLabel('6 xonali kod').fill(VALID_CODE)
  await fresh.getByRole('button', { name: 'Yoqish' }).click()
  await fresh.getByRole('checkbox', { name: "Saqlab qo'ydim" }).check()
  await fresh.getByRole('button', { name: "Boshqaruv markaziga o'tish" }).click()

  // A sales member: the stores and nothing of the team's pages (Sessiyalarim is for everyone)
  await expect(fresh.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Ovqatxonalar' })).toBeVisible()
  await expect(fresh.getByRole('link', { name: 'Jamoa' })).toHaveCount(0)
  await expect(fresh.getByRole('link', { name: 'Sessiyalarim' })).toBeVisible()
  expect(api.member('nodira@rizqim.uz').disabled).toBe(true)
  void context
  await other.close()
})

test('the backend refuses to demote the last superadmin and says why inside the dialog', async ({ page, api, signIn }) => {
  // An operator who was given team.manage tries to demote Aziza, the only superadmin
  api.grants.operator = [...(api.grants.operator ?? []), 'admin.team.manage']
  await openSignedIn(page, signIn, '/team', 'sardor@rizqim.uz', { confirmed: true })
  await card(page, 'Aziza Karimova').getByRole('button', { name: "Rolni o'zgartirish: Aziza Karimova" }).click()
  const dialog = page.getByRole('dialog', { name: "Rolni o'zgartirish" })
  await dialog.getByLabel('Roli').selectOption('operator')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(dialog.getByText("Oxirgi superadministratorni o'zgartirib yoki o'chirib bo'lmaydi. Avval boshqa superadministrator tayinlang.")).toBeVisible()
  expect(api.member('aziza@rizqim.uz').platform_role).toBe('superadmin')
})

test('re-issuing an invite shows a new link, and a member cannot change themselves', async ({ page, api, signIn }) => {
  const pending = api.addMember({ name: 'Kutayotgan', email: 'kutayotgan@rizqim.uz', platform_role: 'viewer', invite: true, disabled: true })
  pending.inviteToken = 'old-token'
  pending.inviteExpiresAt = Date.now() + 1_000_000
  await openSignedIn(page, signIn, '/team', 'aziza@rizqim.uz', { confirmed: true })

  const own = card(page, 'Aziza Karimova')
  await expect(own.getByText('Siz', { exact: true })).toBeVisible()
  await expect(own.getByRole('button')).toHaveCount(0)

  await card(page, 'Kutayotgan').getByRole('button', { name: /Taklifni qayta yuborish/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ha, yangi havola' }).click()
  const url = await page.getByRole('textbox', { name: 'Taklif havolasi' }).inputValue()
  expect(url).toMatch(/\/invite\/invite-/)
  expect(pending.inviteToken).not.toBe('old-token')
})

test('right after signing in, the same code is not accepted again: the member is told to wait for the next one', async ({ page, api }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('aziza@rizqim.uz')
  await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Kirish' }).click()
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()

  await page.getByRole('link', { name: 'Jamoa' }).click()
  await card(page, 'Sardor Operator').getByRole('button', { name: /2FA ni tiklash/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ha, tiklash' }).click()
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(stepUp.getByText('Bu kod hozirgina ishlatilgan — keyingi kodni kiriting')).toBeVisible()
  expect(api.callsTo('POST', '/auth/confirm')).toHaveLength(0)

  // The next code (30 seconds later in real life)
  await stepUp.getByLabel('6 xonali kod').fill(NEXT_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText(/Sardor Operator uchun 2FA tiklandi/)).toBeVisible()
  expect(api.callsTo('POST', '/auth/confirm').map((call) => call.status)).toEqual([200])
})

test('a code the backend refuses says to wait for the next one', async ({ page, api, signIn }) => {
  // The code was taken a moment ago (by another device): the page cannot know, the backend answers invalid_code
  api.member('aziza@rizqim.uz').lastCode = { code: VALID_CODE, at: Date.now() }
  await openSignedIn(page, signIn, '/team')
  await card(page, 'Sardor Operator').getByRole('button', { name: /2FA ni tiklash/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ha, tiklash' }).click()
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(stepUp.getByText(/Ilovada yangi kod chiqquncha \(30 soniya\) kuting/)).toBeVisible()
})
