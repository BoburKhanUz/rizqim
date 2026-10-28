import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-31 Tizim: Sozlamalar (a secret only by its last 4, replaced on its own with the 2FA step-up), To'lov tizimlari, Viloyatlar va
// tumanlar (add, refuse deleting one in use, order), Funksiyalar (per store), who may open it, the phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

async function stepUp(page: Page) {
  const dialog = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(dialog).toBeVisible()
  await dialog.getByLabel('6 xonali kod').fill(VALID_CODE)
  await dialog.getByRole('button', { name: 'Tasdiqlash' }).click()
}

test('the Tizim menu item opens the settings; secrets never show in full and are replaced after the step-up', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/today')
  await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Tizim sozlamalari' }).click()
  await expect(page.getByRole('heading', { name: 'Tizim', level: 1 })).toBeVisible()

  const sms = page.getByRole('region', { name: 'SMS' })
  await expect(sms.getByText('Kiritilgan, oxiri ••••9876')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Telegram' }).getByText('Kiritilgan, oxiri ••••WXYZ')).toBeVisible()
  await expect(page.getByText('eskiz-password-9876')).toHaveCount(0)
  await expect(page.getByText(/AAEexampleToken/)).toHaveCount(0)

  await sms.getByRole('button', { name: 'Eskiz paroli: almashtirish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Eskiz paroli: yangi qiymat' })
  await dialog.getByLabel('Yangi qiymat').fill('brand-new-eskiz-4444')
  await dialog.getByLabel('Sabab').fill('Parol almashdi')
  await dialog.getByRole('button', { name: 'Almashtirish' }).click()
  await stepUp(page)
  await expect(page.getByText('Eskiz paroli almashtirildi.')).toBeVisible()
  await expect(sms.getByText('Kiritilgan, oxiri ••••4444')).toBeVisible()
  expect(api.callsTo('PUT', '/system/settings/secrets/eskiz_password').map((call) => call.status)).toEqual([403, 200])

  // A plain setting: only what changed is sent (the step-up is still fresh)
  await sms.getByLabel("Jo'natuvchi nomi").fill('RIZQIM')
  await sms.getByRole('button', { name: 'Saqlash' }).click()
  const save = page.getByRole('dialog', { name: "Sozlamalarni o'zgartirish" })
  await save.getByLabel('Sabab').fill('Nom tasdiqlandi')
  await save.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Sozlamalar saqlandi.')).toBeVisible()
  expect(api.callsTo('PATCH', '/system/settings')[0]!.body).toEqual({ values: { eskiz_from: 'RIZQIM' }, reason: 'Nom tasdiqlandi' })

  // Links to the pages that stay where they are
  await expect(page.getByRole('link', { name: /Tariflar katalogi/ })).toHaveAttribute('href', '/pos?tab=plans')
  await expect(page.getByRole('link', { name: /SLA chegaralari/ })).toHaveAttribute('href', '/orders')
})

test("payment keys show the last 4 and where they come from; a key is replaced on its own", async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/system?tab=payments', undefined, { confirmed: true })
  const payme = page.getByRole('region', { name: 'Payme' })
  await expect(payme.getByText('Kiritilgan, oxiri ••••4912')).toBeVisible()
  const click = page.getByRole('region', { name: 'Click' })
  await expect(click.getByText('Server sozlamasidan (.env).').first()).toBeVisible()
  await expect(page.getByText('click-env-secret-7777')).toHaveCount(0)

  await payme.getByRole('button', { name: 'Payme: Maxfiy kalit: almashtirish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Payme: Maxfiy kalit: yangi qiymat' })
  await dialog.getByLabel('Yangi qiymat').fill('payme-secret-ABCD')
  await dialog.getByLabel('Sabab').fill('Yangi kalit')
  await dialog.getByRole('button', { name: 'Almashtirish' }).click()
  await expect(payme.getByText('Kiritilgan, oxiri ••••ABCD')).toBeVisible()
  expect(api.system.providers[0]!.credentials[1]!.value).toBe('payme-secret-ABCD')
})

test('regions: add one, a region in use cannot be deleted, the order is saved as the full list', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/system?tab=locations', undefined, { confirmed: true })
  await expect(page.getByRole('list', { name: 'Viloyatlar' }).getByText('Toshkent shahri')).toBeVisible()

  await page.getByRole('button', { name: 'Toshkent shahri: tumanlarni ochish' }).click()
  await expect(page.getByText(/Tumanlari bor viloyat o'chirilmaydi/)).toBeVisible()
  await expect(page.getByRole('button', { name: "Chilonzor tumani: o'chirish" })).toHaveCount(0)

  await page.getByRole('button', { name: "Viloyat qo'shish" }).click()
  const add = page.getByRole('dialog', { name: "Viloyat qo'shish" })
  await add.getByLabel('Nomi').fill('Samarqand viloyati')
  await add.getByLabel('Sabab').fill('Yangi hudud')
  await add.getByRole('button', { name: "Qo'shish" }).click()
  await expect(page.getByText("Viloyat qo'shildi.")).toBeVisible()
  await expect(page.getByRole('list', { name: 'Viloyatlar' }).getByText('Samarqand viloyati')).toBeVisible()

  await page.getByRole('button', { name: "Tartibni o'zgartirish" }).click()
  await page.getByRole('button', { name: 'Toshkent shahri: yuqoriga' }).click()
  await page.getByRole('button', { name: 'Tartibni saqlash' }).click()
  const order = page.getByRole('dialog', { name: 'Yangi tartib' })
  await order.getByLabel('Sabab').fill('Poytaxt birinchi')
  await order.getByRole('button', { name: 'Tartibni saqlash' }).click()
  await expect(page.getByText('Tartib saqlandi.')).toBeVisible()
  const ids = api.callsTo('PUT', '/system/regions/order')[0]!.body!.ids as number[]
  expect(ids.slice(0, 2)).toEqual([2, 1])
  expect(ids).toHaveLength(4)
  await expect(page.getByRole('list', { name: 'Viloyatlar' }).locator(':scope > li').first()).toContainText('Toshkent shahri')
})

test('a feature flag is switched for one store', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/system?tab=flags', undefined, { confirmed: true })
  await expect(page.getByText("1 ta ovqatxonada yoqilgan, 2 tasida o'chiq (jami 3)")).toBeVisible()
  await page.getByRole('switch', { name: 'Yangi buyurtma matni: Shirin Qandolat' }).click()
  const dialog = page.getByRole('dialog', { name: 'Shirin Qandolat: «Yangi buyurtma matni» yoqilsinmi?' })
  await dialog.getByLabel('Sabab').fill('Pilot kengaydi')
  await dialog.getByRole('button', { name: 'Yoqish' }).click()
  await expect(page.getByText('Shirin Qandolat: «Yangi buyurtma matni» yoqildi.')).toBeVisible()
  expect(api.system.flagValues.get(14)).toBe(true)
  await expect(page.getByText("2 ta ovqatxonada yoqilgan, 1 tasida o'chiq (jami 3)")).toBeVisible()
})

test('a member without system.manage sees neither the menu item nor the page', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/system', 'sardor@rizqim.uz')
  await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Tizim sozlamalari' })).toHaveCount(0)
  await expect(page).not.toHaveURL(/\/system/)
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('every Tizim tab fits 360 px', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/system')
    await expect(page.getByRole('region', { name: 'SMS' })).toBeVisible()
    await noHorizontalScroll(page)
    for (const tab of ["To'lov tizimlari", 'Viloyatlar va tumanlar', 'Funksiyalar']) {
      await page.getByRole('tab', { name: tab }).click()
      await expect(page.getByRole('tab', { name: tab })).toHaveAttribute('aria-selected', 'true')
      await page.waitForLoadState('networkidle')
      await noHorizontalScroll(page)
    }
    await page.getByRole('tab', { name: 'Viloyatlar va tumanlar' }).click()
    await page.getByRole('button', { name: 'Toshkent shahri: tumanlarni ochish' }).click()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: "Viloyat qo'shish" }).click()
    const box = await page.getByRole('dialog', { name: "Viloyat qo'shish" }).boundingBox()
    expect(box!.x).toBeGreaterThanOrEqual(0)
    expect(box!.x + box!.width).toBeLessThanOrEqual(360)
  })
})
