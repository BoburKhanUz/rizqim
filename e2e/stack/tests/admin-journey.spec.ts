import { readFileSync } from 'node:fs'
import { expect, test, type Browser, type Page } from '@playwright/test'
import { loadSeed, stack } from '../support/env'
import { Authenticator } from '../support/totp'

// P4-12: the RIZQIM control center (platform/admin) against the real backend, no mocks. The first superadmin is made by the
// operators' own command (`php artisan rizqim:admin:create`, scripts/e2e-stack.sh); the test then does what that person would:
//
//   1. opens the one-time invite link, chooses a password, sets up 2FA (the "phone" is this test: it computes the TOTP codes
//      from the key the page shows), saves the recovery codes and is in;
//   2. invites a second member (a sensitive action: the backend asks for a fresh code; the code that just set the 2FA up is
//      refused, so the test waits for the next 30 s time step) and hands the link to a second browser, which accepts it;
//   3. signs out, signs in again with a recovery code, changes the second member's role (step-up once more, one more step);
//   4. reads it in the audit log (with before/after, the browser's address and name) and finds the member and a store by search;
//   5. signs out, and the session is really gone.
//
// The backend takes every TOTP code once, so the waits for the next time step make this one slow test (about two minutes).

const PASSWORD = 'e2e-Superadmin-password-1'
const OPERATOR_PASSWORD = 'e2e-Operator-password-22'
const ADMIN_EMAIL = 'e2e-admin@rizqim.test'
const OPERATOR_EMAIL = 'e2e-operator@rizqim.test'
const OPERATOR_NAME = 'E2E Operator'

const inviteUrl = (): string => readFileSync(stack.adminInviteFile, 'utf8').trim()

// The invite page: password, 2FA key and first code, recovery codes. Returns the "phone" and the recovery codes.
async function acceptInvite(page: Page, url: string, password: string): Promise<{ phone: Authenticator; recoveryCodes: string[] }> {
  await page.goto(url)
  await expect(page.getByRole('heading', { name: 'Taklifni qabul qilish' })).toBeVisible()
  await page.getByLabel('Yangi parol').fill(password)
  await page.getByLabel('Parolni takrorlang').fill(password)
  await page.getByRole('button', { name: 'Parolni saqlash' }).click()

  await expect(page.getByRole('heading', { name: '2 bosqichli tasdiqlashni yoqing' })).toBeVisible()
  await expect(page.getByRole('img', { name: 'Ilovaga ulash uchun QR kod' })).toBeVisible()
  const key = await page.getByLabel("QR ishlamasa, kalitni qo'lda kiriting").inputValue()
  expect(key).toMatch(/^[A-Z2-7]{16,}$/)
  const phone = new Authenticator(key)
  await page.getByLabel('6 xonali kod').fill(await phone.next())
  await page.getByRole('button', { name: 'Yoqish' }).click()

  await expect(page.getByRole('heading', { name: 'Tiklash kodlaringizni saqlang' })).toBeVisible()
  const items = page.getByRole('list', { name: 'Tiklash kodlari' }).getByRole('listitem')
  await expect(items).toHaveCount(10)
  const recoveryCodes = (await items.allTextContents()).map((text) => text.trim())
  const enter = page.getByRole('button', { name: "Boshqaruv markaziga o'tish" })
  await expect(enter).toBeDisabled()
  await page.getByRole('checkbox', { name: "Saqlab qo'ydim" }).check()
  await enter.click()
  return { phone, recoveryCodes }
}

async function newPage(browser: Browser): Promise<Page> {
  const context = await browser.newContext({ baseURL: stack.admin, locale: 'en-US', timezoneId: 'Asia/Tashkent' })
  return context.newPage()
}

test('the control center: invite, 2FA, step-up, team, audit, search, sign-out - on the real backend', async ({ page, browser, context }) => {
  test.setTimeout(420_000)
  const seed = loadSeed()
  let phone: Authenticator
  let recoveryCodes: string[]

  await test.step('the invited superadmin chooses a password, sets up 2FA and saves the recovery codes', async () => {
    ;({ phone, recoveryCodes } = await acceptInvite(page, inviteUrl(), PASSWORD))
    await expect(page).toHaveURL(/\/today$/)
    await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Jamoa' })).toBeVisible()

    // The session is a cookie the page's scripts cannot read, and no admin token is in the page's storage
    const cookie = (await context.cookies()).find((c) => c.name === 'rz_admin')
    expect(cookie?.httpOnly).toBe(true)
    expect(cookie?.sameSite).toBe('Strict')
    expect(await page.evaluate(() => document.cookie)).not.toContain('rz_admin')
    expect(await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))).not.toMatch(/\d+\|[A-Za-z0-9]{20,}/)
  })

  let operatorInvite = ''
  await test.step('inviting a member asks for a fresh code; the code that just signed the member in is refused', async () => {
    await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Jamoa' }).click()
    await expect(page.getByRole('heading', { name: 'Jamoa', level: 1 })).toBeVisible()
    await page.getByRole('button', { name: 'Xodim taklif qilish' }).click()
    const form = page.getByRole('dialog', { name: 'Yangi xodim taklif qilish' })
    await form.getByLabel('Ism').fill(OPERATOR_NAME)
    await form.getByLabel('Email').fill(OPERATOR_EMAIL)
    await form.getByLabel('Roli').selectOption('operator')
    await form.getByRole('button', { name: 'Taklif havolasini yaratish' }).click()

    // The backend: reauth_required. The page: the step-up dialog. The code of the 2FA set-up was used a moment ago.
    const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
    await expect(stepUp).toBeVisible()
    await stepUp.getByLabel('6 xonali kod').fill(phone.usedCode())
    await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
    await expect(stepUp.getByText('Bu kod hozirgina ishlatilgan — keyingi kodni kiriting')).toBeVisible()

    // The next time step's code goes through, and the invitation is made once
    await stepUp.getByLabel('6 xonali kod').fill(await phone.next())
    await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
    const link = page.getByRole('dialog', { name: 'Taklif havolasi' })
    await expect(link).toContainText(`Havolani ${OPERATOR_NAME} ga yuboring`)
    operatorInvite = await link.getByRole('textbox', { name: 'Taklif havolasi' }).inputValue()
    expect(operatorInvite).toMatch(new RegExp(`^${stack.admin}/invite/`))
    await link.getByRole('button', { name: 'Tayyor' }).click()
    await expect(page.getByRole('heading', { name: new RegExp(`^${OPERATOR_NAME}`) }).locator('xpath=ancestor::li[1]').getByText('Taklif kutilmoqda')).toBeVisible()
  })

  await test.step('the second member accepts the link in their own browser and gets the operator menu', async () => {
    const other = await newPage(browser)
    await acceptInvite(other, operatorInvite, OPERATOR_PASSWORD)
    await expect(other).toHaveURL(/\/today$/)
    const menu = other.getByRole('navigation', { name: 'Asosiy menyu' })
    await expect(menu.getByRole('link', { name: 'Buyurtmalar' })).toBeVisible()
    await expect(menu.getByRole('link', { name: 'Sessiyalarim' })).toBeVisible()
    await expect(menu.getByRole('link', { name: 'Jamoa' })).toHaveCount(0)
    await expect(menu.getByRole('link', { name: 'Harakatlar jurnali' })).toHaveCount(0)
    // The operator is not allowed on the team page even by address
    await other.goto('/team')
    await expect(other).toHaveURL(/\/today$/)
    await other.context().close()
  })

  await test.step('signing out ends the session; a recovery code signs the superadmin in again, once', async () => {
    await page.getByRole('button', { name: 'Chiqish' }).first().click()
    await expect(page).toHaveURL(/\/login$/)
    expect((await context.cookies()).find((c) => c.name === 'rz_admin')?.value ?? '').toBe('')

    const signInWith = async (recoveryCode: string) => {
      await page.goto(`${stack.admin}/login`)
      await page.getByLabel('Email').fill(ADMIN_EMAIL)
      await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
      await page.getByRole('button', { name: 'Kirish' }).click()
      await page.getByRole('button', { name: 'Tiklash kodidan foydalanish' }).click()
      await page.getByLabel('Tiklash kodi').fill(recoveryCode)
      await page.getByRole('button', { name: 'Tasdiqlash' }).click()
    }
    await signInWith(recoveryCodes[0]!)
    await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()

    // The same recovery code does not work a second time
    await page.getByRole('button', { name: 'Chiqish' }).first().click()
    await signInWith(recoveryCodes[0]!)
    await expect(page.getByText("Tiklash kodi noto'g'ri yoki oldin ishlatilgan.")).toBeVisible()
    await signInWith(recoveryCodes[1]!)
    await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
  })

  await test.step('changing the role asks for a fresh code again (a new session is not confirmed)', async () => {
    await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Jamoa' }).click()
    const row = page.getByRole('heading', { name: new RegExp(`^${OPERATOR_NAME}`) }).locator('xpath=ancestor::li[1]')
    await expect(row.getByText('2FA yoqilgan')).toBeVisible()
    await expect(row.getByText('Faol', { exact: true })).toBeVisible()
    await row.getByRole('button', { name: `Rolni o'zgartirish: ${OPERATOR_NAME}` }).click()
    const dialog = page.getByRole('dialog', { name: "Rolni o'zgartirish" })
    await dialog.getByLabel('Roli').selectOption('support')
    await dialog.getByRole('button', { name: 'Saqlash' }).click()

    const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
    await expect(stepUp).toBeVisible()
    await stepUp.getByLabel('6 xonali kod').fill(await phone.next())
    await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
    await expect(page.getByText(`${OPERATOR_NAME} endi «Yordam».`)).toBeVisible()
    await expect(row.getByText('Yordam', { exact: true }).first()).toBeVisible()
  })

  await test.step('the audit log shows the invitation and the role change, with before/after and where it came from', async () => {
    await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Harakatlar jurnali' }).click()
    await expect(page.getByRole('heading', { name: 'Harakatlar jurnali', level: 1 })).toBeVisible()
    await page.getByLabel('Amal', { exact: true }).fill('admin.team')
    await page.getByRole('button', { name: "Qo'llash" }).click()

    const list = page.getByRole('list', { name: 'Jurnal yozuvlari' })
    await expect(list.getByRole('button', { name: 'admin.team.invited: batafsil' })).toBeVisible()
    await list.getByRole('button', { name: 'admin.team.updated: batafsil' }).first().click()
    const detail = page.getByRole('dialog', { name: 'Yozuv tafsiloti' })
    await expect(detail).toContainText('E2E Superadmin')
    // The address and the browser come through the app's server to the backend's audit log
    await expect(detail).toContainText('127.0.0.1')
    await expect(detail).toContainText(/Chrome/)
    const change = detail.getByRole('row').filter({ hasText: 'platform_role' })
    await expect(change).toContainText('operator')
    await expect(change).toContainText('support')
    await page.keyboard.press('Escape')
  })

  await test.step('Ctrl+K finds the member and a store', async () => {
    await page.keyboard.press('Control+k')
    const palette = page.getByRole('dialog', { name: 'Qidirish' })
    const box = palette.getByRole('combobox')
    await box.fill('E2E Oper')
    await expect(palette.getByRole('group', { name: 'Jamoa' }).getByRole('option', { name: new RegExp(OPERATOR_NAME) })).toBeVisible()
    await box.fill(seed.store.name.slice(0, 6))
    const store = palette.getByRole('group', { name: 'Ovqatxonalar' }).getByRole('option').first()
    await expect(store).toContainText(seed.store.name)
    await store.click()
    await expect(page).toHaveURL(new RegExp(`/stores/${seed.store.id}$`))
    // Wave 2 (P4-22): the search opens the store's 360-degree card
    await expect(page.getByRole('heading', { name: seed.store.name, level: 1 })).toBeVisible()
  })

  await test.step('signing out leaves nothing behind', async () => {
    await page.getByRole('button', { name: 'Chiqish' }).first().click()
    await expect(page).toHaveURL(/\/login$/)
    const me = await page.request.get(`${stack.admin}/api/backend/v1/admin/auth/me`)
    expect(me.status()).toBe(401)
    await page.goto(`${stack.admin}/audit`)
    await expect(page).toHaveURL(/\/login$/)
  })
})
