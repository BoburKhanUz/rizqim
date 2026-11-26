import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-31 Mijozlar va yordam: the users list and the user card with a block (a reason and a fresh 2FA code), the complaints
// inbox (taking one, replying through the bot, resolving), a viewer who reads without acting, the suspicious cases, the
// search palette's user result opening the card, phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

test('the users list opens the card, and blocking asks for a reason and a fresh 2FA code', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/customers', 'aziza@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Mijozlar va yordam', level: 1 })).toBeVisible()
  const list = page.getByRole('list', { name: "Foydalanuvchilar ro'yxati" })
  await expect(list.getByRole('link')).toHaveCount(3)
  await page.getByRole('button', { name: 'Kuryerlar' }).click()
  await expect(list.getByRole('link')).toHaveCount(1)
  expect(api.callsTo('GET', '/users').at(-1)?.query.role).toBe('courier')
  await page.getByRole('button', { name: 'Hammasi' }).click()
  await list.getByRole('link', { name: 'Olim Egamov kartasini ochish' }).click()

  await expect(page).toHaveURL(/\/users\/7$/)
  await expect(page.getByRole('heading', { name: 'Olim Egamov', level: 1 })).toBeVisible()
  await expect(page.getByText('12 ta (yetkazilgan 10, bekor qilingan 2)')).toBeVisible()
  await page.getByRole('button', { name: 'Bloklash' }).click()
  const dialog = page.getByRole('dialog', { name: 'Hisobni bloklash' })
  await expect(dialog).toContainText('buyurtma bera olmaydi')
  const confirm = dialog.getByRole('button', { name: 'Ha, bloklash' })
  await expect(confirm).toBeDisabled()
  await dialog.getByLabel('Sabab', { exact: true }).fill('Soxta buyurtmalar')
  await confirm.click()

  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Foydalanuvchi bloklandi. Uning barcha sessiyalari yopildi.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Blokdan chiqarish' })).toBeVisible()
  await expect(page.getByText(/Aziza Karimova blokladi. Sabab: Soxta buyurtmalar/)).toBeVisible()
  expect(api.customers.users.find((user) => user.id === 7)?.blocked?.reason).toBe('Soxta buyurtmalar')
  expect(api.callsTo('POST', '/users/7/block').at(-1)?.body).toEqual({ reason: 'Soxta buyurtmalar' })
})

test('the inbox: take a complaint, reply with a tayyor javob, resolve it', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/customers?tab=complaints', 'madina@rizqim.uz', { confirmed: true })
  const list = page.getByRole('list', { name: "Shikoyatlar ro'yxati" })
  await expect(list.getByRole('button')).toHaveCount(2)
  await expect(list.getByRole('button').first()).toContainText('Kutmoqda')
  await expect(page.getByRole('button', { name: 'Ochiq (2)' })).toHaveAttribute('aria-pressed', 'true')

  await list.getByRole('button', { name: 'Sifat muammosi: Olim Egamov shikoyatini ochish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Shikoyat: Sifat muammosi' })
  await expect(dialog).toContainText('Uzr, kuryer adashib qoldi.')
  await dialog.getByRole('button', { name: 'Olish' }).click()
  await expect(page.getByText("Shikoyat sizga biriktirildi: «Ko'rib chiqilmoqda».")).toBeVisible()
  await expect(dialog.getByLabel("Mas'ul xodim")).toHaveValue('3')

  await dialog.getByRole('button', { name: 'Javob yozish' }).click()
  const reply = page.getByRole('dialog', { name: 'Mijozga javob' })
  await reply.getByLabel('Tayyor javob').selectOption({ label: 'Kechikish uchun uzr' })
  await expect(reply.getByLabel('Matn')).toHaveValue(/kechikkani uchun uzr/)
  await reply.getByRole('button', { name: 'Yuborish' }).click()
  await expect(page.getByText('Javob mijozga yuborildi.')).toBeVisible()
  await expect(page.getByRole('list', { name: 'RIZQIM javoblari' })).toContainText('kechikkani uchun uzr')
  expect(api.customers.sent).toEqual([{ userId: 7, text: "Buyurtmangiz kechikkani uchun uzr so'raymiz. Pulingiz qaytariladi." }])

  await page.getByRole('dialog').getByRole('button', { name: 'Hal qilish' }).click()
  const resolve = page.getByRole('dialog', { name: 'Shikoyatni hal qilish' })
  await resolve.getByLabel('Qanday hal qilindi').fill("Pul qaytarildi, ovqatxona ogohlantirildi")
  await resolve.getByRole('button', { name: 'Hal qilindi' }).click()
  await expect(page.getByText('Shikoyat hal qilindi.')).toBeVisible()
  await expect(page.getByRole('dialog')).toContainText('Pul qaytarildi, ovqatxona ogohlantirildi')
  await expect(page.getByRole('dialog').getByRole('button', { name: 'Qayta ochish' })).toBeVisible()
  expect(api.customers.complaints[0]?.supportStatus).toBe('resolved')
})

test('a viewer reads users and complaints without actions; the suspicious cases explain themselves', async ({ page, api, signIn }) => {
  api.addMember({ name: "Ko'ruvchi", email: 'viewer@rizqim.uz', platform_role: 'viewer', twoFactor: true })
  await openSignedIn(page, signIn, '/users/7', 'viewer@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Olim Egamov', level: 1 })).toBeVisible()
  await expect(page.getByRole('group', { name: 'Amallar' })).toHaveCount(0)

  await page.goto('/customers?tab=complaints&complaint=cc000000-1111-4222-8333-444455556666')
  const dialog = page.getByRole('dialog', { name: 'Shikoyat: Sifat muammosi' })
  await expect(dialog).toContainText('Ovqat sovuq keldi')
  await expect(dialog.getByRole('group', { name: 'Amallar' })).toHaveCount(0)
  await dialog.getByRole('button', { name: 'Yopish' }).click()

  await page.getByRole('tab', { name: 'Shubhali holatlar' }).click()
  const cancellations = page.getByRole('list', { name: "Ko'p bekor qilish" })
  await expect(cancellations).toContainText("3 tasini o'zi bekor qildi")
  await cancellations.getByRole('link', { name: 'Olim Egamov' }).click()
  await expect(page).toHaveURL(/\/users\/7$/)
})

test('the search palette opens the user card', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/today', 'aziza@rizqim.uz')
  await page.getByRole('button', { name: 'Qidirish' }).click()
  const palette = page.getByRole('dialog', { name: 'Qidirish' })
  await palette.getByRole('combobox').fill('Olim')
  await palette.getByRole('option', { name: /Olim Egamov/ }).click()
  await expect(page).toHaveURL(/\/users\/7$/)
  await expect(page.getByRole('heading', { name: 'Olim Egamov', level: 1 })).toBeVisible()
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('the tabs, the card and the complaint dialog fit a phone', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/customers', 'aziza@rizqim.uz')
    await expect(page.getByRole('list', { name: "Foydalanuvchilar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('tab', { name: 'Shikoyatlar' }).click()
    await page.getByRole('list', { name: "Shikoyatlar ro'yxati" }).getByRole('button').first().click()
    await expect(page.getByRole('dialog', { name: /Shikoyat:/ })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('dialog').getByRole('button', { name: 'Yopish' }).click()
    await page.getByRole('tab', { name: 'Shubhali holatlar' }).click()
    await expect(page.getByRole('list', { name: "Ko'p bekor qilish" })).toBeVisible()
    await noHorizontalScroll(page)
    await page.goto('/users/7')
    await expect(page.getByRole('heading', { name: 'Olim Egamov', level: 1 })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('tab', { name: 'Sessiyalar' }).click()
    await noHorizontalScroll(page)
  })
})
