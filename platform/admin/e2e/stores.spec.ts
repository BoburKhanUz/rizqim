import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-22 Ovqatxonalar: the list with health in words, the 360° card and an action with a reason and the step-up, the
// pipeline (checklist, stage move, creating the store, the owner's link), an announcement with its preview count, the
// store picker in the Buyurtmalar filters, phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

const list = (page: Page) => page.getByRole('list', { name: "Ovqatxonalar ro'yxati" })

test('the list: health in words, a filter, and the card with its reasons', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/stores', 'sardor@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Ovqatxonalar', level: 1 })).toBeVisible()
  const risky = list(page).getByRole('link', { name: 'Qarzdor kafe kartasini ochish' })
  await expect(risky).toContainText('Ketish xavfi · 45 ball')
  await expect(risky).toContainText('Qarz kredit limitidan oshgan')
  await expect(page.getByRole('button', { name: 'Ketish xavfi (1)' })).toBeVisible()

  await page.getByLabel('Viloyat').selectOption('Samarqand viloyati')
  await expect(list(page).getByRole('link')).toHaveCount(1)
  expect(api.callsTo('GET', '/stores').at(-1)?.query.region_id).toBe('2')

  await risky.click()
  await expect(page).toHaveURL(/\/stores\/15$/)
  await expect(page.getByRole('heading', { name: 'Qarzdor kafe', level: 1 })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Sabablar' })).toContainText('Kassa 3 kundan beri aloqa qilmagan')
  // The operator looks, does not act
  await expect(page.getByRole('group', { name: 'Amallar' })).toHaveCount(0)
  await page.getByRole('tab', { name: 'Qurilmalar va fiskal' }).click()
  await expect(page.getByText('Oflayn')).toBeVisible()
})

test('suspending a store asks for a reason and a fresh 2FA code, and lands in the status history', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/stores/12', 'aziza@rizqim.uz')
  await page.getByRole('button', { name: "To'xtatish" }).click()
  const dialog = page.getByRole('dialog', { name: "Ovqatxonani to'xtatish" })
  await expect(dialog).toContainText('Kassa ishlashda davom etadi')
  const confirm = dialog.getByRole('button', { name: "Ha, to'xtatish" })
  await expect(confirm).toBeDisabled()
  await dialog.getByLabel('Sabab', { exact: true }).fill('Gigiyena tekshiruvi')
  await confirm.click()

  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText("Ovqatxona to'xtatildi.")).toBeVisible()
  await expect(page.getByRole('button', { name: 'Faollashtirish' })).toBeVisible()
  expect(api.stores.stores.find((store) => store.id === 12)?.status).toBe('suspended')

  await page.getByRole('tab', { name: 'Tarix' }).click()
  await expect(page.getByRole('list', { name: 'Holat tarixi' })).toContainText("Faol → To'xtatilgan")
  await expect(page.getByRole('list', { name: 'Holat tarixi' })).toContainText('Gigiyena tekshiruvi')
})

test("P4-32: the payment account and reserve, and the owner's login phone, each with a reason", async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/stores/12', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: "To'lov hisobi" }).click()
  const dialog = page.getByRole('dialog', { name: "To'lov hisobi va zaxira" })
  await dialog.getByLabel("To'lov hisobi").selectOption('own_account')
  await dialog.getByLabel("Payoutdan keyin hamyonda qoladigan summa (so'm)").fill('50000')
  await dialog.getByLabel('Sabab', { exact: true }).fill("O'z Payme kassasi")
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("To'lov hisobi sozlamalari saqlandi.")).toBeVisible()
  expect(api.callsTo('PUT', '/stores/12/payment-account').at(-1)?.body).toEqual({ payment_account_type: 'own_account', min_payout_reserve: '50000', reason: "O'z Payme kassasi" })
  await page.getByRole('tab', { name: 'Moliya' }).click()
  await expect(page.getByText("Ovqatxonaning o'z hisobi")).toBeVisible()

  await page.getByRole('button', { name: 'Kirish raqami' }).click()
  const phone = page.getByRole('dialog', { name: 'Egasining kirish raqami' })
  await phone.getByLabel('Yangi raqam').fill('+998 90 765 43 21')
  await phone.getByLabel('Sabab', { exact: true }).fill("Egasi raqamini yo'qotdi")
  await phone.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Egasining kirish raqami saqlandi.')).toBeVisible()
  expect(api.stores.stores.find((store) => store.id === 12)?.owner?.phone).toBe('+998907654321')
})

test('the pipeline: checklist, the next stage, creating the store and its owner link', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/stores/applications', 'aziza@rizqim.uz', { confirmed: true })
  await expect(page.getByRole('heading', { name: 'Ulash jarayoni', level: 1 })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Tekshiruv' })).toContainText('Burger Joy')
  await expect(page.getByRole('region', { name: 'Tekshiruv' })).toContainText("Muddati o'tgan")
  await page.getByRole('link', { name: "Choyxona Navro'z arizasini ochish" }).click()
  await expect(page.getByRole('heading', { name: "Ariza: Choyxona Navro'z" })).toBeVisible()

  await page.getByRole('button', { name: 'Keyingi bosqich: Tekshiruv' }).click()
  await expect(page.getByText(/ro'yxati tugallanmagan: Egasi bilan bog'lanildi/)).toBeVisible()
  const contacted = page.getByRole('list', { name: 'Ariza' }).getByRole('checkbox', { name: /Egasi bilan bog'lanildi/ })
  // The box follows the server's answer
  await contacted.click()
  await expect(contacted).toBeChecked()
  await page.getByRole('button', { name: 'Keyingi bosqich: Tekshiruv' }).click()
  await expect(page.getByText("Ariza «Tekshiruv» bosqichiga o'tdi.")).toBeVisible()

  await page.getByRole('button', { name: 'Ovqatxonani yaratish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Arizadan ovqatxona yaratish' })
  await dialog.getByLabel('Tuman').selectOption('Chilonzor tumani')
  await dialog.getByLabel('Filial manzili').fill('Chilonzor 9-kvartal')
  await dialog.getByLabel('Kenglik (lat)').fill('41.285')
  await dialog.getByLabel('Uzunlik (lng)').fill('69.204')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Hujjatlar tekshirildi')
  await dialog.getByRole('button', { name: 'Ovqatxonani yaratish' }).click()
  const link = page.getByRole('dialog', { name: 'Havola tayyor' })
  await expect(link.getByRole('textbox')).toHaveValue(/start=invite_new-/)
  expect(api.callsTo('POST', '/store-applications/7a7a7a7a-0000-4000-8000-000000000007/convert').at(-1)?.body).toMatchObject({ branch_district_id: 11, branch_region_id: 1, reason: 'Hujjatlar tekshirildi' })
  await link.getByRole('link', { name: 'Ovqatxona kartasi' }).click()
  await expect(page.getByRole('heading', { name: "Choyxona Navro'z", level: 1 })).toBeVisible()
  await expect(page.getByText("Marketpleysda ko'rinmaydi")).toBeVisible()
})

test('an announcement goes to the count its preview showed', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/stores/broadcasts', 'aziza@rizqim.uz', { confirmed: true })
  await expect(page.getByRole('heading', { name: "Ommaviy e'lonlar", level: 1 })).toBeVisible()
  await page.getByLabel('Matn').fill('Bugun 23:00 da texnik ishlar')
  await page.getByLabel('Sabab', { exact: true }).fill('Server yangilanishi')
  await page.getByRole('button', { name: "Ko'rib chiqish" }).click()
  await expect(page.getByText("3 ta ovqatxona, 2 ta egaga boradi. Telegramsiz: 1.")).toBeVisible()
  await page.getByRole('button', { name: '2 ta egaga yuborish' }).click()
  await expect(page.getByText("E'lon navbatga qo'yildi: 2 ta egaga yuboriladi.")).toBeVisible()
  await expect(page.getByRole('list', { name: "Ommaviy e'lonlar" })).toContainText('Bugun 23:00 da texnik ishlar')
  expect(api.stores.broadcasts).toHaveLength(1)
  expect(api.callsTo('POST', '/store-broadcasts').at(-1)?.body).toMatchObject({ audience: 'all', statuses: ['active'], recipients: 2 })
})

test('Buyurtmalar filters by a store picked by name, not by id', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/orders', 'sardor@rizqim.uz')
  await page.getByRole('button', { name: "Qo'shimcha filtrlar" }).click()
  await page.getByRole('combobox', { name: 'Ovqatxona' }).fill('express')
  await page.getByRole('option', { name: /Lazzat Express/ }).click()
  await expect(page.getByRole('textbox', { name: 'Ovqatxona' })).toHaveValue('Lazzat Express')
  await expect.poll(() => api.callsTo('GET', '/orders').at(-1)?.query.store_id).toBe('13')
})

test('phone width: the list, the card and the pipeline do not scroll sideways', async ({ page, signIn }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await openSignedIn(page, signIn, '/stores', 'aziza@rizqim.uz')
  await expect(list(page).getByRole('link').first()).toBeVisible()
  await noHorizontalScroll(page)
  await page.goto('/stores/15')
  await expect(page.getByRole('heading', { name: 'Qarzdor kafe', level: 1 })).toBeVisible()
  await noHorizontalScroll(page)
  await page.goto('/stores/applications')
  await expect(page.getByRole('heading', { name: 'Ulash jarayoni', level: 1 })).toBeVisible()
  await noHorizontalScroll(page)
})
