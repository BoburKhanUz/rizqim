import { readFileSync } from 'node:fs'
import { readFile } from 'node:fs/promises'
import { expect, test, type Browser, type Page } from '@playwright/test'
import { telegramSignIn } from '../support/backend'
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
//
// Wave 3 (P4-32): a second test in the same file (serial: it reuses the first test's "phone" and recovery codes) signs the
// superadmin in again in a fresh browser and walks the new sections against the same backend:
//
//   1. Mijozlar: finds the seeded customer, blocks them behind step-up with a reason (the Mini App's own sign-in is then really
//      refused with 403 account_blocked), unblocks them (sign-in works again); opens the customer's complaint and resolves it;
//   2. Marketing: creates a promo code and switches it off;
//   3. Tizim: switches a feature flag on for the seeded store and off again;
//   4. Ko'rsatkichlar loads; one export (the stores list) downloads a real .xlsx (a zip: it starts with "PK\x03\x04");
//   5. the audit log holds the block, unblock, promo code and flag changes with their reasons.

const PASSWORD = 'e2e-Superadmin-password-1'
const OPERATOR_PASSWORD = 'e2e-Operator-password-22'
const ADMIN_EMAIL = 'e2e-admin@rizqim.test'
const OPERATOR_EMAIL = 'e2e-operator@rizqim.test'
const OPERATOR_NAME = 'E2E Operator'

// The first test creates the superadmin and keeps the "phone" and the recovery codes; the second test signs in again with them
let phone!: Authenticator
let recoveryCodes: string[] = []

test.describe.configure({ mode: 'serial' })

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

// Wave 3 (P4-32): the sections added after the first journey, on the same real backend. A new browser, a new session: it is
// not step-up confirmed, so the first sensitive action asks for a fresh code again.
test('the control center: customers, complaints, promo codes, feature flags, metrics, export - on the real backend', async ({ page, context }) => {
  test.setTimeout(420_000)
  const seed = loadSeed()
  const customer = seed.complainer
  const menu = page.getByRole('navigation', { name: 'Asosiy menyu' })
  // The text a dialog asks for before the backend does the work
  const REASON = 'E2E: tekshiruv uchun sabab'
  const PROMO = 'E2EPROMO10'
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  const confirmWithCode = async () => {
    await expect(stepUp).toBeVisible()
    await stepUp.getByLabel('6 xonali kod').fill(await phone.next())
    await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
    await expect(stepUp).toBeHidden()
  }

  await test.step('the superadmin signs in again (recovery code) and sees the new sections in the menu', async () => {
    expect(recoveryCodes.length, 'the first test must have run before this one').toBeGreaterThan(2)
    await page.goto(`${stack.admin}/login`)
    await page.getByLabel('Email').fill(ADMIN_EMAIL)
    await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
    await page.getByRole('button', { name: 'Kirish' }).click()
    await page.getByRole('button', { name: 'Tiklash kodidan foydalanish' }).click()
    await page.getByLabel('Tiklash kodi').fill(recoveryCodes[2]!)
    await page.getByRole('button', { name: 'Tasdiqlash' }).click()
    await expect(page.getByRole('heading', { name: 'Bugun', level: 1 })).toBeVisible()
    for (const name of ["Ko'rsatkichlar", 'Mijozlar va yordam', 'Marketing', 'Tizim sozlamalari']) {
      await expect(menu.getByRole('link', { name })).toBeVisible()
    }
  })

  await test.step('Mijozlar: the seeded customer is found by name and the card opens', async () => {
    await menu.getByRole('link', { name: 'Mijozlar va yordam' }).click()
    await expect(page.getByRole('heading', { name: 'Mijozlar va yordam', level: 1 })).toBeVisible()
    const panel = page.getByRole('tabpanel')
    await panel.getByLabel('Qidirish', { exact: true }).fill('Komila')
    await page.getByRole('link', { name: `${customer.name} kartasini ochish` }).click()
    await expect(page).toHaveURL(new RegExp(`/users/${customer.id}$`))
    await expect(page.getByRole('heading', { name: customer.name, level: 1 })).toBeVisible()
    await expect(page.getByText('Faol', { exact: true }).first()).toBeVisible()
    // The phone is masked even on the superadmin's card unless it is revealed on purpose (it is in the audit log then)
    await expect(page.getByText(customer.phone)).toHaveCount(0)
    expect((await telegramSignIn({ id: customer.telegram_id, first_name: customer.name })).status).toBe(200)
  })

  await test.step('blocking asks for a reason and a fresh code; the Mini App sign-in is then really refused', async () => {
    await page.getByRole('group', { name: 'Amallar' }).getByRole('button', { name: 'Bloklash' }).click()
    const dialog = page.getByRole('dialog', { name: 'Hisobni bloklash' })
    const confirm = dialog.getByRole('button', { name: 'Ha, bloklash' })
    // No reason, no block
    await expect(confirm).toBeDisabled()
    await dialog.getByLabel('Sabab').fill(REASON)
    await confirm.click()
    // The backend: reauth_required (this session was never confirmed). The page: the step-up dialog.
    await confirmWithCode()
    await expect(page.getByText('Foydalanuvchi bloklandi. Uning barcha sessiyalari yopildi.')).toBeVisible()
    await expect(page.getByText(`Sabab: ${REASON}`)).toBeVisible()
    await expect(page.getByText('Bloklangan', { exact: true }).first()).toBeVisible()

    const refused = await telegramSignIn({ id: customer.telegram_id, first_name: customer.name })
    expect(refused.status).toBe(403)
    expect(refused.code).toBe('account_blocked')
  })

  await test.step('unblocking (the confirmation still holds) lets the customer sign in again', async () => {
    await page.getByRole('group', { name: 'Amallar' }).getByRole('button', { name: 'Blokdan chiqarish' }).click()
    const dialog = page.getByRole('dialog', { name: 'Blokdan chiqarish' })
    await dialog.getByLabel('Sabab').fill('E2E: blok xato edi')
    await dialog.getByRole('button', { name: 'Blokdan chiqarish' }).click()
    await expect(page.getByText('Blok olib tashlandi.')).toBeVisible()
    await expect(page.getByText('Faol', { exact: true }).first()).toBeVisible()
    expect((await telegramSignIn({ id: customer.telegram_id, first_name: customer.name })).status).toBe(200)
  })

  await test.step('Shikoyatlar: the complaint is opened, taken and resolved with a note', async () => {
    await menu.getByRole('link', { name: 'Mijozlar va yordam' }).click()
    await page.getByRole('tab', { name: 'Shikoyatlar' }).click()
    const row = page.getByRole('button', { name: new RegExp(`${customer.name} shikoyatini ochish$`) })
    await row.click()
    const dialog = page.getByRole('dialog', { name: /^Shikoyat:/ })
    await expect(dialog).toContainText('Lavash sovuq keldi')
    await dialog.getByRole('button', { name: 'Olish' }).click()
    await expect(page.getByText("Shikoyat sizga biriktirildi: «Ko'rib chiqilmoqda».")).toBeVisible()

    await dialog.getByRole('button', { name: 'Hal qilish' }).click()
    const resolve = page.getByRole('dialog', { name: 'Shikoyatni hal qilish' })
    await resolve.getByLabel('Qanday hal qilindi').fill("E2E: ovqat almashtirildi, uzr so'raldi")
    await resolve.getByRole('button', { name: 'Hal qilindi', exact: true }).click()
    await expect(page.getByText('Shikoyat hal qilindi.')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('dialog')).toHaveCount(0)

    // It is no longer among the open ones; it is under "Hal qilindi"
    await expect(row).toHaveCount(0)
    await page.getByRole('button', { name: /^Hal qilindi/ }).click()
    await expect(row).toBeVisible()
  })

  await test.step('Marketing: a promo code is created and then switched off', async () => {
    await menu.getByRole('link', { name: 'Marketing' }).click()
    await expect(page.getByRole('heading', { name: 'Marketing', level: 1 })).toBeVisible()
    await page.getByRole('tab', { name: 'Promo-kodlar' }).click()
    await page.getByRole('button', { name: 'Yangi promo-kod' }).click()
    const form = page.getByRole('dialog', { name: 'Yangi promo-kod' })
    await form.getByLabel('Promo-kod', { exact: true }).fill(PROMO)
    await form.getByLabel('Chegirma (%)').fill('10')
    await form.getByLabel('Sabab').fill(REASON)
    await form.getByRole('button', { name: 'Saqlash' }).click()
    await expect(page.getByText('Promo-kod yaratildi.')).toBeVisible()

    const toggle = page.getByRole('switch', { name: `${PROMO} promo-kodi yoqilgan` })
    await expect(toggle).toHaveAttribute('aria-checked', 'true')
    await toggle.click()
    const off = page.getByRole('dialog', { name: "Promo-kodni o'chirish" })
    await off.getByLabel('Sabab').fill('E2E: aksiya tugadi')
    await off.getByRole('button', { name: "O'chirish" }).click()
    await expect(page.getByText("Promo-kod o'chirib qo'yildi.")).toBeVisible()
    await expect(page.getByRole('switch', { name: `${PROMO} promo-kodi yoqilgan` })).toHaveAttribute('aria-checked', 'false')
  })

  await test.step('Tizim: a feature flag is switched on for the seeded store and off again', async () => {
    await menu.getByRole('link', { name: 'Tizim sozlamalari' }).click()
    await expect(page.getByRole('heading', { name: 'Tizim', level: 1 })).toBeVisible()
    await page.getByRole('tab', { name: 'Funksiyalar' }).click()
    await page.getByLabel('Ovqatxona', { exact: true }).fill(seed.store.name.slice(0, 6))
    const flag = page.getByRole('switch', { name: `Yangi buyurtma matni: ${seed.store.name}` })
    await expect(flag).toHaveAttribute('aria-checked', 'false')

    await flag.click()
    const on = page.getByRole('dialog', { name: new RegExp(`yoqilsinmi\\?$`) })
    await on.getByLabel('Sabab').fill(REASON)
    await on.getByRole('button', { name: 'Yoqish' }).click()
    await expect(page.getByText(`${seed.store.name}: «Yangi buyurtma matni» yoqildi.`)).toBeVisible()
    await expect(page.getByRole('switch', { name: `Yangi buyurtma matni: ${seed.store.name}` })).toHaveAttribute('aria-checked', 'true')

    await page.getByRole('switch', { name: `Yangi buyurtma matni: ${seed.store.name}` }).click()
    const offDialog = page.getByRole('dialog', { name: new RegExp(`o'chirilsinmi\\?$`) })
    await offDialog.getByLabel('Sabab').fill('E2E: tajriba tugadi')
    await offDialog.getByRole('button', { name: "O'chirish" }).click()
    await expect(page.getByText(`${seed.store.name}: «Yangi buyurtma matni» o'chirildi.`)).toBeVisible()
    await expect(page.getByRole('switch', { name: `Yangi buyurtma matni: ${seed.store.name}` })).toHaveAttribute('aria-checked', 'false')
  })

  await test.step("Ko'rsatkichlar loads (an empty platform: zeros, no error)", async () => {
    await menu.getByRole('link', { name: "Ko'rsatkichlar" }).click()
    await expect(page.getByRole('heading', { name: "Ko'rsatkichlar", level: 1 })).toBeVisible()
    await expect(page.getByRole('list', { name: "Asosiy ko'rsatkichlar" })).toBeVisible()
    await expect(page.getByText('MRR (oylik obuna daromadi)')).toBeVisible()
    await expect(page.getByText("Ko'rsatkichlar yuklanmadi")).toHaveCount(0)
  })

  await test.step('an export downloads a real .xlsx file (a zip: it starts with PK 03 04)', async () => {
    await menu.getByRole('link', { name: 'Ovqatxonalar' }).click()
    await expect(page.getByRole('heading', { name: 'Ovqatxonalar', level: 1 })).toBeVisible()
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
    expect(download.suggestedFilename()).toMatch(/\.xlsx$/)
    const file = await readFile(await download.path())
    expect(file.length).toBeGreaterThan(200)
    expect([...file.subarray(0, 4)]).toEqual([0x50, 0x4b, 0x03, 0x04])
    await expect(page.getByText(/^Fayl tayyor/)).toBeVisible()
  })

  await test.step('the audit log holds the block, the unblock, the promo code and the flag with their reasons', async () => {
    await menu.getByRole('link', { name: 'Harakatlar jurnali' }).click()
    const list = page.getByRole('list', { name: 'Jurnal yozuvlari' })
    for (const [action, reason] of [
      ['admin.users.blocked', REASON],
      ['admin.users.unblocked', 'E2E: blok xato edi'],
      ['admin.marketing.promo_codes.created', REASON],
      // Newest first: the switch-off came after the switch-on, and both are in the log
      ['admin.system.feature_flag_set', 'E2E: tajriba tugadi'],
      ['admin.exports.stores', null],
    ] as const) {
      await page.getByLabel('Amal', { exact: true }).fill(action)
      await page.getByRole('button', { name: "Qo'llash" }).click()
      await list.getByRole('button', { name: `${action}: batafsil` }).first().click()
      const detail = page.getByRole('dialog', { name: 'Yozuv tafsiloti' })
      await expect(detail).toContainText('E2E Superadmin')
      if (reason) await expect(detail).toContainText(reason)
      await page.keyboard.press('Escape')
    }
  })

  await test.step('signing out leaves nothing behind', async () => {
    await page.getByRole('button', { name: 'Chiqish' }).first().click()
    await expect(page).toHaveURL(/\/login$/)
    expect((await context.cookies()).find((c) => c.name === 'rz_admin')?.value ?? '').toBe('')
  })
})
