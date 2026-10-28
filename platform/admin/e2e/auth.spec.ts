import type { Page } from '@playwright/test'
import { expect, openSignedIn, SESSION_COOKIE, test, VALID_CODE } from './fixtures'
import { PASSWORD } from './fake/backend'

// Signing in: email and password, then the 6-digit code (or the 2FA setup the first time, with the recovery codes), the
// session in an httpOnly cookie, signing out. The Next proxy (app/api/backend) runs for real and calls the fake backend.

async function enterCredentials(page: Page, email: string, password = PASSWORD) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Parol', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Kirish' }).click()
}

test('a member signs in with the password and the code from the phone app', async ({ page, context, api }) => {
  await enterCredentials(page, 'aziza@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Tasdiqlash kodi' })).toBeVisible()
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()

  // In: the first section the member has, with the whole menu
  await expect(page).toHaveURL(/\/today$/)
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  // P4-13: Bugun is the home page (e2e/today.spec.ts)
  await expect(page.getByRole('list', { name: "Bugungi ko'rsatkichlar" })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Jamoa' })).toBeVisible()

  // The token is an httpOnly cookie of the app: not in the page's storage, not in what the page can read
  const cookie = (await context.cookies()).find((c) => c.name === SESSION_COOKIE)
  expect(cookie?.httpOnly).toBe(true)
  expect(cookie?.sameSite).toBe('Strict')
  expect(await page.evaluate(() => document.cookie)).not.toContain('rz_admin')
  expect(await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))).not.toContain('admin-')
  // ...and the page never saw it in an answer either: the proxy took it off
  expect(api.callsTo('POST', '/auth/2fa/verify')[0]?.authorization).toBeNull()
})

test('the session survives a reload and signing out ends it on the backend', async ({ page, api }) => {
  await enterCredentials(page, 'aziza@rizqim.uz')
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()

  await page.reload()
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  expect(api.callsTo('GET', '/auth/me').length).toBeGreaterThanOrEqual(2)

  await page.getByRole('button', { name: 'Chiqish' }).first().click()
  await expect(page).toHaveURL(/\/login$/)
  expect(api.callsTo('POST', '/auth/logout')).toHaveLength(1)
  expect(api.liveSessions()).toBe(0)

  // The cookie is gone: opening the app again asks to sign in
  await page.goto('/team')
  await expect(page).toHaveURL(/\/login$/)
})

test('a wrong password and a wrong code are refused in plain words', async ({ page }) => {
  await enterCredentials(page, 'aziza@rizqim.uz', 'not the password')
  await expect(page.getByText("Email yoki parol noto'g'ri.")).toBeVisible()

  await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Kirish' }).click()
  await page.getByLabel('6 xonali kod').fill('000000')
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText(/Ilovada yangi kod chiqquncha \(30 soniya\) kuting/)).toBeVisible()
  await expect(page).toHaveURL(/\/login$/)
})

test('five wrong passwords lock the sign-in and the backend\'s sentence is shown', async ({ page }) => {
  await page.goto('/login')
  for (let attempt = 0; attempt < 5; attempt++) {
    await page.getByLabel('Email').fill('aziza@rizqim.uz')
    await page.getByLabel('Parol', { exact: true }).fill(`wrong ${attempt}`)
    await page.getByRole('button', { name: 'Kirish' }).click()
    await expect(page.getByText("Email yoki parol noto'g'ri.")).toBeVisible()
  }
  await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Kirish' }).click()
  await expect(page.getByText("Juda ko'p xato urinish. 15 daqiqadan so'ng qayta urinib ko'ring.")).toBeVisible()
})

test('a disabled member is told so', async ({ page, api }) => {
  api.member('sardor@rizqim.uz').disabled = true
  await enterCredentials(page, 'sardor@rizqim.uz')
  await expect(page.getByText("Hisobingiz o'chirilgan. Superadministratorga murojaat qiling.")).toBeVisible()
})

test('the first sign-in sets up 2FA: QR code, key, recovery codes that must be saved before going on', async ({ page, api }) => {
  await enterCredentials(page, 'madina@rizqim.uz')
  await expect(page.getByRole('heading', { name: '2 bosqichli tasdiqlashni yoqing' })).toBeVisible()
  await expect(page.getByRole('img', { name: 'Ilovaga ulash uchun QR kod' })).toBeVisible()
  await expect(page.getByLabel("QR ishlamasa, kalitni qo'lda kiriting")).toHaveValue('JBSWY3DPEHPK3PXP')

  await page.getByLabel('6 xonali kod').fill('111111')
  await page.getByRole('button', { name: 'Yoqish' }).click()
  await expect(page.getByText(/Ilovada yangi kod chiqquncha \(30 soniya\) kuting/)).toBeVisible()
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Yoqish' }).click()

  await expect(page.getByRole('heading', { name: 'Tiklash kodlaringizni saqlang' })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Tiklash kodlari' }).getByRole('listitem')).toHaveCount(10)
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Yuklab olish (.txt)' }).click()
  expect((await download).suggestedFilename()).toBe('rizqim-tiklash-kodlari.txt')

  // Not in until the codes are saved
  const enter = page.getByRole('button', { name: "Boshqaruv markaziga o'tish" })
  await expect(enter).toBeDisabled()
  await page.getByRole('checkbox', { name: "Saqlab qo'ydim" }).check()
  await enter.click()
  await expect(page.getByRole('heading', { name: 'Buyurtmalar', level: 1 })).toBeVisible()
  expect(api.member('madina@rizqim.uz').twoFactor).toBe(true)
  expect(api.callsTo('POST', '/auth/2fa/setup')).toHaveLength(1)
})

test('a recovery code signs in once and is burnt', async ({ page, api }) => {
  await enterCredentials(page, 'aziza@rizqim.uz')
  await page.getByRole('button', { name: 'Tiklash kodidan foydalanish' }).click()
  await page.getByLabel('Tiklash kodi').fill('seed-0001')
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  expect(api.member('aziza@rizqim.uz').recoveryCodes).not.toContain('seed-0001')

  await page.getByRole('button', { name: 'Chiqish' }).first().click()
  await enterCredentials(page, 'aziza@rizqim.uz')
  await page.getByRole('button', { name: 'Tiklash kodidan foydalanish' }).click()
  await page.getByLabel('Tiklash kodi').fill('seed-0001')
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText("Tiklash kodi noto'g'ri yoki oldin ishlatilgan.")).toBeVisible()
})

test('a challenge that ran out sends the member back to the start', async ({ page, api }) => {
  api.challengeTtlMs = -1
  await enterCredentials(page, 'aziza@rizqim.uz')
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Kirish muddati tugadi (5 daqiqa). Qaytadan boshlang.')).toBeVisible()
  await expect(page.getByLabel('Email')).toBeVisible()
})

test('a session the backend ended (idle limit, revoked elsewhere) sends the member to the sign-in page with a sentence', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/audit')
  await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()
  api.endSessions('aziza@rizqim.uz')
  // The next call (the page the link opens) is refused with 401: the shell is replaced by the sign-in page at once, so the
  // click is made in the DOM instead of waiting for a link that is about to go
  await page.evaluate(() => document.querySelector<HTMLAnchorElement>('a[href="/sessions"]')?.click())
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByText('Seans tugadi. Qaytadan kiring.')).toBeVisible()
})

test('a signed-out visitor is sent to the sign-in page from any address', async ({ page }) => {
  for (const path of ['/', '/today', '/team', '/audit']) {
    await page.goto(path)
    await expect(page).toHaveURL(/\/login$/)
  }
})
