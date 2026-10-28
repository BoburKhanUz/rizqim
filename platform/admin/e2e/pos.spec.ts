import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-24 POS va obunalar: the tabs a role may open, the links of Bugun and of the store card landing on the right tab and row,
// a store's subscription (plan, trial, commission), an invoice marked paid, a failed receipt retried, a device revoked and an
// offline sync error resolved, each with a reason and the step-up; phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

async function confirmStepUp(page: Page) {
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
}

test('a superadmin sees every tab with the numbers that need a person', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/pos')
  await expect(page.getByRole('heading', { name: 'POS va obunalar', level: 1 })).toBeVisible()
  const tabs = page.getByRole('tablist', { name: "POS va obunalar bo'limi" })
  // The trial ending in 2 days and the past-due store; one overdue invoice; offline + never-seen + outdated devices
  await expect(tabs.getByRole('tab', { name: 'Obunalar (2)' })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Tariflar' })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Hisob-fakturalar (1)' })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: /^Qurilmalar/ })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Fiskal cheklar (4)' })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Sinxron xatolari (2)' })).toBeVisible()

  const list = page.getByRole('list', { name: "Obunalar ro'yxati" })
  await expect(list.getByRole('button', { name: 'Lazzat Express obunasini ochish' })).toContainText('Sinovga 2 kun qoldi')
  await expect(list.getByRole('button', { name: 'Qarzdor kafe obunasini ochish' })).toContainText("To'lov muddati o'tgan")
  await page.getByRole('button', { name: 'Sinovi 3 kunda tugaydi' }).click()
  await expect(list.getByRole('button')).toHaveCount(1)
})

test('an operator sees the tills side only and cannot act', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/pos', 'sardor@rizqim.uz')
  const tabs = page.getByRole('tablist')
  await expect(tabs.getByRole('tab', { name: /^Qurilmalar/ })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: /^Obunalar/ })).toHaveCount(0)
  await expect(tabs.getByRole('tab', { name: /^Hisob-fakturalar/ })).toHaveCount(0)
  await expect(page.getByRole('list', { name: "Qurilmalar ro'yxati" })).toBeVisible()

  await page.getByRole('button', { name: 'Kassa 1 qurilmasini ochish' }).first().click()
  await expect(page.getByRole('heading', { name: 'Kassa 1' })).toBeVisible()
  await expect(page.getByRole('button', { name: "Qurilmani o'chirish" })).toHaveCount(0)
})

test('the Bugun links open the right tab and row', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/pos?fiscal=5f0c2a10-0000-4000-8000-000000000001')
  await expect(page.getByRole('tab', { name: /^Fiskal cheklar/, selected: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: /Lazzat Oshxona · 86/ })).toBeVisible()
  await expect(page.getByText('Soliq serveri javob bermadi').first()).toBeVisible()

  await page.goto('/pos?device=7a1b0000-0000-4000-8000-000000000001')
  await expect(page.getByRole('tab', { name: /^Qurilmalar/, selected: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Kassa 1' })).toBeVisible()
  await expect(page.getByText('Eski versiya').first()).toBeVisible()

  await page.goto('/pos?invoice=1a0c0000-0000-4000-8000-000000000001')
  await expect(page.getByRole('tab', { name: /^Hisob-fakturalar/, selected: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'OB-202610-000015' })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Eslatmalar tarixi' })).toContainText('3-kun eslatmasi')

  // The store card's "Obuna" link
  await page.goto('/pos?store=15')
  await expect(page.getByRole('tab', { name: /^Obunalar/, selected: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Qarzdor kafe' })).toBeVisible()
  await expect(page.getByText('Komissiya qarzi kredit limitidan oshgan: Mini App buyurtmalari yopiq.')).toBeVisible()
})

test('marking an invoice paid asks for a reason and the step-up, and the overdue number goes down', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?invoice=1a0c0000-0000-4000-8000-000000000001')
  await page.getByRole('button', { name: "To'langan deb belgilash" }).click()
  const dialog = page.getByRole('dialog', { name: "To'langan deb belgilash" })
  const confirm = dialog.getByRole('button', { name: 'Belgilash' })
  await expect(confirm).toBeDisabled()
  await dialog.getByLabel('Sabab', { exact: true }).fill("Naqd to'landi, kvitansiya 12")
  await confirm.click()
  await confirmStepUp(page)

  await expect(page.getByText("Hisob-faktura to'langan deb belgilandi.")).toBeVisible()
  expect(api.pos.invoices.find((invoice) => invoice.number === 'OB-202610-000015')?.status).toBe('paid')
  expect(api.callsTo('POST', '/pos/invoices/1a0c0000-0000-4000-8000-000000000001/paid').at(-1)?.body).toEqual({ reason: "Naqd to'landi, kvitansiya 12" })
  await expect(page.getByRole('tab', { name: /^Hisob-fakturalar$/ })).toBeVisible()
  await expect(page.getByRole('button', { name: "To'langan deb belgilash" })).toHaveCount(0)
})

test('a store subscription: the plan changes with a reason, the trial is extended, the commission has an override', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?store=13')
  await expect(page.getByRole('heading', { name: 'Lazzat Express' })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Komissiya' })).toContainText('Olib ketish')
  await expect(page.getByRole('list', { name: 'Komissiya' })).toContainText('3%')

  await page.getByRole('button', { name: 'Sinovni uzaytirish' }).click()
  let dialog = page.getByRole('dialog', { name: 'Sinovni uzaytirish' })
  await dialog.getByLabel("Qo'shiladigan kunlar (1–90)").fill('7')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Kassa kech keldi')
  await dialog.getByRole('button', { name: 'Uzaytirish' }).click()
  await confirmStepUp(page)
  await expect(page.getByText('Sinov davri uzaytirildi.')).toBeVisible()

  await page.getByRole('button', { name: 'Tarif biriktirish' }).click()
  dialog = page.getByRole('dialog', { name: 'Tarif biriktirish' })
  await dialog.getByLabel('Tarif', { exact: true }).selectOption('Pro')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Egasi Pro tarifni tanladi')
  await dialog.getByRole('button', { name: 'Biriktirish' }).click()
  await expect(page.getByText('Tarif saqlandi.')).toBeVisible()
  expect(api.callsTo('POST', '/billing/stores/13/subscription').at(-1)?.body).toMatchObject({ plan: 'pro', reason: 'Egasi Pro tarifni tanladi' })
  await expect(page.getByText('Pro').first()).toBeVisible()

  await page.getByRole('button', { name: "Komissiyani o'zgartirish" }).click()
  dialog = page.getByRole('dialog', { name: 'Komissiyani belgilash' })
  await dialog.getByLabel("O'z yetkazishi").fill('8')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Kelishuv bo‘yicha')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Komissiya saqlandi. U yangi buyurtmalardan amal qiladi.')).toBeVisible()
  await expect(page.getByRole('list', { name: 'Komissiya' })).toContainText('8%')
})

test('the tariff catalog: a price change with a reason reaches the plan card', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?tab=plans')
  const list = page.getByRole('list', { name: "Tariflar ro'yxati" })
  await expect(list).toContainText('249 000')
  await expect(page.getByRole('list', { name: "Eski tariflar (faqat ko'rish)" })).toContainText('POS (eski)')

  await page.getByRole('button', { name: 'Asosiy tarifini tahrirlash' }).click()
  const dialog = page.getByRole('dialog', { name: 'Asosiy tarifini tahrirlash' })
  await dialog.getByLabel('Oylik narx').fill('259000')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Egasi narxni oshirdi')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await confirmStepUp(page)
  await expect(page.getByText('Asosiy tarifi saqlandi.')).toBeVisible()
  await expect(list).toContainText('259 000')
  expect(api.pos.plans.find((plan) => plan.code === 'asosiy')?.monthly_fee).toBe('259000.00')
})

test('a failed receipt is retried; one with a wrong MXIK says why it stays failed', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?tab=fiscal')
  const providers = page.getByRole('list', { name: "Provayder bo'yicha xatolar" })
  await expect(providers).toContainText('didox')
  await expect(providers).toContainText('Yiqilgan: 1, kutilayotgan: 0')
  await page.getByRole('list', { name: "Fiskal cheklar ro'yxati" }).getByRole('button', { name: /Lazzat Oshxona chekini ochish, 45/ }).click()

  await page.getByRole('button', { name: 'Qayta yuborish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Chekni qayta yuborish' })
  await dialog.getByLabel('Sabab', { exact: true }).fill('Provayder tiklandi')
  await dialog.getByRole('button', { name: 'Yuborish' }).click()
  await confirmStepUp(page)
  await expect(page.getByText("Chek ro'yxatdan o'tdi.")).toBeVisible()
  expect(api.pos.fiscal.find((receipt) => receipt.uuid === '5f0c2a10-0000-4000-8000-000000000003')?.status).toBe('issued')

  await page.goto('/pos?fiscal=5f0c2a10-0000-4000-8000-000000000002')
  await page.getByRole('button', { name: 'Qayta yuborish' }).click()
  await page.getByRole('dialog', { name: 'Chekni qayta yuborish' }).getByLabel('Sabab', { exact: true }).fill('MXIK tuzatildimi?')
  await page.getByRole('dialog', { name: 'Chekni qayta yuborish' }).getByRole('button', { name: 'Yuborish' }).click()
  await expect(page.getByText("Chek hali ro'yxatdan o'tmadi: MXIK kodi noto'g'ri")).toBeVisible()
})

test('a device is revoked with a reason and an offline sync error is resolved', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/pos?device=7a1b0000-0000-4000-8000-000000000001', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: "Qurilmani o'chirish" }).click()
  const dialog = page.getByRole('dialog', { name: "Qurilmani o'chirish" })
  await dialog.getByLabel('Sabab', { exact: true }).fill("Kassa o'g'irlangan")
  await dialog.getByRole('button', { name: "Ha, o'chirish" }).click()
  await expect(page.getByText("Qurilma o'chirildi. Undagi barcha sessiyalar yopildi.")).toBeVisible()
  expect(api.pos.devices.find((device) => device.uuid === '7a1b0000-0000-4000-8000-000000000001')?.revoked).toBe(true)

  await page.goto('/pos?sync=0b5f0000-0000-4000-8000-000000000001')
  await expect(page.getByRole('heading', { name: /To'lov olindi · Lazzat Express/ })).toBeVisible()
  await expect(page.getByRole('region', { name: "Kassa yuborgan ma'lumot" })).toContainText('"amount": "50000"')
  await page.getByRole('button', { name: 'Hal qilindi' }).click()
  const resolve = page.getByRole('dialog', { name: 'Hal qilindi deb belgilash' })
  await resolve.getByLabel('Sabab', { exact: true }).fill("Qo'lda kiritildi")
  await resolve.getByRole('button', { name: 'Belgilash' }).click()
  await expect(page.getByText('Hal qilindi deb belgilandi.')).toBeVisible()
  expect(api.pos.sync.find((op) => op.op_id === '0b5f0000-0000-4000-8000-000000000001')?.resolution).toBe('resolved')
})

test('shifts: a cash difference and a forced close are shown with their reasons', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/pos?tab=shifts', 'sardor@rizqim.uz')
  const list = page.getByRole('list', { name: "Smenalar ro'yxati" })
  await expect(list).toContainText('kam: 30 000')
  await expect(list).toContainText('Majburan yopilgan')
  await page.getByRole('button', { name: 'Majburan yopilgan (1)' }).click()
  await expect(list.getByRole('button')).toHaveCount(1)
  await list.getByRole('button').click()
  await expect(page.getByText('Kassir ketib qoldi')).toBeVisible()
  await expect(page.getByRole('list', { name: 'Sanalgan pullar' })).toContainText('× 5')
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 375, height: 800 } })

  test('every tab fits without sideways scrolling', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/pos')
    for (const name of [/^Obunalar/, /^Tariflar/, /^Hisob-fakturalar/, /^Qurilmalar/, /^Smenalar/, /^Fiskal cheklar/, /^Sinxron xatolari/]) {
      await page.getByRole('tab', { name }).click()
      await expect(page.getByRole('tab', { name, selected: true })).toBeVisible()
      await page.waitForLoadState('networkidle')
      await noHorizontalScroll(page)
    }
    await page.goto('/pos?store=12')
    await expect(page.getByRole('heading', { name: 'Lazzat Oshxona' })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: "Komissiyani o'zgartirish" }).click()
    await expect(page.getByRole('dialog', { name: 'Komissiyani belgilash' })).toBeVisible()
    await noHorizontalScroll(page)
  })
})
