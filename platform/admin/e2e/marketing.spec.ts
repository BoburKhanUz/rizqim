import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-31 Marketing: the three tabs, a banner added with its picture and scheduled, switched off and ordered, what the Mini App
// shows, a promo code added, switched off and deleted (a used code keeps its discount), a category hidden, ordered, added and
// deleted, each with a reason and, for deleting, the step-up; a member who only reads; phone width.

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64')

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

async function answer(page: Page, dialogName: string, reason: string, button: string) {
  const dialog = page.getByRole('dialog', { name: dialogName })
  await expect(dialog).toBeVisible()
  await dialog.getByLabel('Sabab').fill(reason)
  await dialog.getByRole('button', { name: button, exact: true }).click()
  return dialog
}

test('a superadmin sees the three tabs; the banners show their state and the picture loads through the session', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/marketing')
  await expect(page.getByRole('heading', { name: 'Marketing', level: 1 })).toBeVisible()
  const tabs = page.getByRole('tablist', { name: "Marketing bo'limi" })
  await expect(tabs.getByRole('tab', { name: 'Bannerlar va aksiyalar', selected: true })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Promo-kodlar' })).toBeVisible()
  await expect(tabs.getByRole('tab', { name: 'Kategoriyalar' })).toBeVisible()

  const list = page.getByRole('list', { name: "Bannerlar ro'yxati" })
  const first = list.getByRole('listitem').filter({ hasText: 'Dekabr aksiyasi' })
  await expect(first).toContainText("Ko'rinmoqda")
  await expect(first).toContainText('Ovqatxonani ochadi: Lazzat Oshxona')
  await expect(list.getByRole('listitem').filter({ hasText: 'Yangi yil' })).toContainText('Rejalashtirilgan')
  await expect(list.getByRole('listitem').filter({ hasText: 'Eski banner' })).toContainText("O'chiq")
  // The stored picture comes back as an image from the page's own origin
  await expect.poll(() => first.getByRole('img').evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth)).toBeGreaterThan(0)

  await page.getByRole('button', { name: 'Tugagan' }).click()
  await expect(page.getByRole('heading', { name: "Bu holatda banner yo'q" })).toBeVisible()
})

test('the Mini App preview lists only what shows now, and follows a change', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/marketing')
  await page.getByRole('button', { name: "Mini App'da ko'rinishi" }).click()
  const preview = page.getByRole('list', { name: 'Mini App bannerlari' })
  await expect(preview.getByRole('listitem')).toHaveCount(1)
  await expect(preview).toContainText('Dekabr aksiyasi')

  await page.getByRole('switch', { name: 'Dekabr aksiyasi banneri yoqilgan' }).click()
  await answer(page, "Bannerni o'chirish", 'Mavsum tugadi', "O'chirish")
  await expect(page.getByText("Banner o'chirildi (yashirildi).")).toBeVisible()
  await expect(page.getByText("Hozir bosh sahifada banner ko'rinmayapti.")).toBeVisible()
  expect(api.callsTo('POST', '/marketing/promos/1')[0]?.body).toEqual({ is_active: false, reason: 'Mavsum tugadi' })
})

test('adds a scheduled banner with a picture, orders the banners and deletes one with the step-up', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/marketing')
  await page.getByRole('button', { name: 'Yangi banner' }).click()
  const dialog = page.getByRole('dialog', { name: 'Yangi banner' })
  await dialog.getByLabel('Sarlavha').fill('Navro‘z aksiyasi')
  await dialog.getByLabel('Banner rasmi').setInputFiles({ name: 'navruz.png', mimeType: 'image/png', buffer: PNG })
  await dialog.getByRole('button', { name: 'Shirin Qandolat' }).click()
  await dialog.getByRole('button', { name: 'Napoleon' }).click()
  await dialog.getByLabel('Boshlanish vaqti').fill('2027-03-20T09:00')
  await dialog.getByLabel('Tugash vaqti').fill('2027-03-24T21:00')
  await dialog.getByLabel('Sabab').fill('Navro‘z kampaniyasi')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText("Banner qo'shildi.")).toBeVisible()
  const added = page.getByRole('list', { name: "Bannerlar ro'yxati" }).getByRole('listitem').filter({ hasText: 'Navro‘z aksiyasi' })
  await expect(added).toContainText('Rejalashtirilgan')
  await expect(added).toContainText('Taomni ochadi: Napoleon')
  const body = api.callsTo('POST', '/marketing/promos')[0]?.body
  expect(body).toMatchObject({ title: 'Navro‘z aksiyasi', starts_at: '2027-03-20T09:00:00+05:00', ends_at: '2027-03-24T21:00:00+05:00', reason: 'Navro‘z kampaniyasi' })

  // Order: the new one first, saved as the whole row
  await page.getByRole('button', { name: "Yuqoriga ko'chirish: Navro‘z aksiyasi" }).click()
  await page.getByRole('button', { name: "Yuqoriga ko'chirish: Navro‘z aksiyasi" }).click()
  await page.getByRole('button', { name: "Yuqoriga ko'chirish: Navro‘z aksiyasi" }).click()
  await expect(page.getByText("Tartib o'zgartirildi, lekin hali saqlanmadi.")).toBeVisible()
  await page.getByRole('button', { name: 'Tartibni saqlash' }).click()
  await answer(page, 'Bannerlar tartibini saqlash', 'Navro‘z birinchi', 'Saqlash')
  await expect(page.getByText('Tartib saqlandi.')).toBeVisible()
  expect([...api.marketing.banners].sort((a, b) => a.sort_order - b.sort_order).map((banner) => banner.title)[0]).toBe('Navro‘z aksiyasi')
  expect(api.callsTo('PUT', '/marketing/promos/order')[0]?.body).toMatchObject({ ids: [4, 1, 2, 3], reason: 'Navro‘z birinchi' })

  // Deleting asks for the step-up first
  await page.getByRole('button', { name: "Eski banner bannerini o'chirish" }).click()
  const dialogDelete = page.getByRole('dialog', { name: "Bannerni o'chirib tashlash" })
  await dialogDelete.getByLabel('Sabab').fill('Eskirgan')
  await dialogDelete.getByRole('button', { name: "O'chirish", exact: true }).click()
  await confirmStepUp(page)
  await expect(page.getByText("Banner o'chirib tashlandi.")).toBeVisible()
  await expect(page.getByRole('list', { name: "Bannerlar ro'yxati" })).not.toContainText('Eski banner')
})

test('promo codes: the usage numbers, a used code stays locked, a new one is added, switched off and deleted', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/marketing?tab=codes')
  const list = page.getByRole('list', { name: "Promo-kodlar ro'yxati" })
  const used = list.getByRole('listitem').filter({ hasText: 'YANGI10' })
  await expect(used).toContainText("Chegirma: 10 % (eng ko'pi 8 000 so'm)")
  await expect(used).toContainText('12 / 100')
  await expect(used).toContainText("1 250 000 so'm")
  await expect(list.getByRole('listitem').filter({ hasText: 'ESKI20' })).toContainText('Muddati tugagan')
  // A used code cannot be deleted
  await expect(used.getByRole('button', { name: "YANGI10 promo-kodini o'chirish" })).toHaveCount(0)

  // Its card has the last orders; each opens in Buyurtmalar
  await used.getByRole('button', { name: 'YANGI10 promo-kodi haqida batafsil' }).click()
  const card = page.getByRole('dialog', { name: 'Promo-kod YANGI10' })
  await expect(card.getByRole('link').first()).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666')
  await card.getByRole('button', { name: 'Yopish' }).click()

  // Its discount is locked, the limit is not
  await used.getByRole('button', { name: 'YANGI10 promo-kodini tahrirlash' }).click()
  const edit = page.getByRole('dialog', { name: 'YANGI10 ni tahrirlash' })
  await expect(edit.getByLabel('Chegirma (%)')).toBeDisabled()
  await expect(edit.getByLabel('Promo-kod')).toBeDisabled()
  await edit.getByLabel('Umumiy limit').fill('500')
  await edit.getByLabel('Sabab').fill('Limitni oshirdik')
  await edit.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Promo-kod saqlandi.')).toBeVisible()
  await expect(used).toContainText('12 / 500')

  // A new code
  await page.getByRole('button', { name: 'Yangi promo-kod' }).click()
  const form = page.getByRole('dialog', { name: 'Yangi promo-kod' })
  await form.getByLabel('Promo-kod', { exact: true }).fill('bahor15')
  await form.getByLabel('Chegirma (%)').fill('15')
  await form.getByLabel('Sabab').fill('Bahor aksiyasi')
  await form.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Promo-kod yaratildi.')).toBeVisible()
  const created = list.getByRole('listitem').filter({ hasText: 'BAHOR15' })
  await expect(created).toContainText('Chegirma: 15 %')
  expect(api.callsTo('POST', '/marketing/promo-codes')[0]?.body).toMatchObject({ code: 'BAHOR15', type: 'percentage', value: '15', per_user_limit: 1, reason: 'Bahor aksiyasi' })

  // Switched off, then deleted (it was never used) with the step-up
  await created.getByRole('switch', { name: 'BAHOR15 promo-kodi yoqilgan' }).click()
  await answer(page, "Promo-kodni o'chirish", "Xato kiritilgan", "O'chirish")
  await expect(created).toContainText("O'chirilgan")
  await created.getByRole('button', { name: "BAHOR15 promo-kodini o'chirish" }).click()
  const remove = page.getByRole('dialog', { name: "Promo-kodni o'chirib tashlash" })
  await remove.getByLabel('Sabab').fill('Xato kiritilgan')
  await remove.getByRole('button', { name: "O'chirish", exact: true }).click()
  await confirmStepUp(page)
  await expect(page.getByText("Promo-kod o'chirib tashlandi.")).toBeVisible()
  await expect(list).not.toContainText('BAHOR15')

  await page.getByRole('button', { name: 'Muddati tugagan' }).click()
  await expect(list.getByRole('listitem')).toHaveCount(1)
})

test('categories: hide one, order the list, add one under another, delete an empty one with the step-up', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/marketing?tab=categories')
  const list = page.getByRole('list', { name: "Kategoriyalar ro'yxati" })
  await expect(list.getByRole('listitem')).toHaveCount(3)
  await expect(list.getByRole('listitem').filter({ hasText: 'Burgerlar' })).toContainText('Taomlar ichida')
  await expect(list.getByRole('listitem').filter({ hasText: 'Ichimliklar' })).toContainText('Yashirin')

  await page.getByRole('switch', { name: "Ichimliklar kategoriyasi ko'rinadi" }).click()
  await answer(page, "Kategoriyani ko'rsatish", 'Mavsum boshlandi', "Ko'rsatish")
  await expect(page.getByText("Kategoriya ko'rsatildi.")).toBeVisible()
  await expect(list.getByRole('listitem').filter({ hasText: 'Ichimliklar' })).toContainText("Ko'rinadi")

  await page.getByRole('button', { name: "Yuqoriga ko'chirish: Ichimliklar" }).click()
  await page.getByRole('button', { name: 'Tartibni saqlash' }).click()
  await answer(page, 'Kategoriyalar tartibini saqlash', 'Ichimliklar oldinda', 'Saqlash')
  await expect(page.getByText('Tartib saqlandi.')).toBeVisible()
  expect(api.callsTo('PUT', '/marketing/categories/order')[0]?.body).toMatchObject({ ids: [1, 3, 2] })

  await page.getByRole('button', { name: 'Yangi kategoriya' }).click()
  const form = page.getByRole('dialog', { name: 'Yangi kategoriya' })
  await form.getByLabel('Nomi').fill('Desertlar')
  await form.getByLabel('Asosiy kategoriya').selectOption({ label: 'Taomlar' })
  await form.getByLabel('Ikonka').setInputFiles({ name: 'dessert.png', mimeType: 'image/png', buffer: PNG })
  await form.getByLabel('Sabab').fill("Yangi bo'lim")
  await form.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText("Kategoriya qo'shildi.")).toBeVisible()
  await expect(list.getByRole('listitem').filter({ hasText: 'Desertlar' })).toContainText('Taomlar ichida')

  // Only an empty one can go: Taomlar and Burgerlar hold products or sub-categories
  await expect(page.getByRole('button', { name: "Taomlar kategoriyasini o'chirish" })).toHaveCount(0)
  await page.getByRole('button', { name: "Desertlar kategoriyasini o'chirish" }).click()
  const remove = page.getByRole('dialog', { name: "Kategoriyani o'chirish" })
  await remove.getByLabel('Sabab').fill('Keraksiz')
  await remove.getByRole('button', { name: "O'chirish", exact: true }).click()
  await confirmStepUp(page)
  await expect(page.getByText("Kategoriya o'chirildi.")).toBeVisible()
  await expect(list).not.toContainText('Desertlar')
})

test('a member who only reads sees every tab without a single button to change anything', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Faqat Ko‘ruvchi', email: 'viewer@rizqim.uz', platform_role: 'viewer', twoFactor: true })
  await openSignedIn(page, signIn, '/marketing', 'viewer@rizqim.uz')
  await expect(page.getByRole('list', { name: "Bannerlar ro'yxati" })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Yangi banner' })).toHaveCount(0)
  await expect(page.getByRole('switch')).toHaveCount(0)
  await page.getByRole('tab', { name: 'Promo-kodlar' }).click()
  await expect(page.getByRole('list', { name: "Promo-kodlar ro'yxati" })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Yangi promo-kod' })).toHaveCount(0)
  await page.getByRole('tab', { name: 'Kategoriyalar' }).click()
  await expect(page.getByRole('list', { name: "Kategoriyalar ro'yxati" })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Yangi kategoriya' })).toHaveCount(0)
})

test('a role without marketing.view has no Marketing in the menu and is sent home from its address', async ({ page, signIn }) => {
  // The operator lands on Bugun; Marketing is not in its menu and its address leads back
  await openSignedIn(page, signIn, '/marketing', 'sardor@rizqim.uz')
  await expect(page).toHaveURL(/\/today$/)
  await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Marketing' })).toHaveCount(0)
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('every tab and the dialogs fit 360 px without sideways scrolling', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/marketing')
    await expect(page.getByRole('list', { name: "Bannerlar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)

    await page.getByRole('button', { name: "Mini App'da ko'rinishi" }).click()
    await expect(page.getByRole('list', { name: 'Mini App bannerlari' })).toBeVisible()
    await noHorizontalScroll(page)

    await page.getByRole('button', { name: 'Yangi banner' }).click()
    const dialog = page.getByRole('dialog', { name: 'Yangi banner' })
    await expect(dialog).toBeVisible()
    await expect.poll(async () => (await dialog.boundingBox())!.y + (await dialog.boundingBox())!.height).toBeLessThanOrEqual(740)
    const box = (await dialog.boundingBox())!
    expect(box.x).toBeGreaterThanOrEqual(0)
    expect(box.x + box.width).toBeLessThanOrEqual(360)
    await noHorizontalScroll(page)
    await dialog.getByRole('button', { name: 'Bekor qilish' }).click()

    await page.getByRole('tab', { name: 'Promo-kodlar' }).click()
    await expect(page.getByRole('list', { name: "Promo-kodlar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Yangi promo-kod' }).click()
    await expect(page.getByRole('dialog', { name: 'Yangi promo-kod' })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Bekor qilish' }).click()

    await page.getByRole('tab', { name: 'Kategoriyalar' }).click()
    await expect(page.getByRole('list', { name: "Kategoriyalar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)
  })
})
