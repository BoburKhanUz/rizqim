import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'
import { PASSWORD } from './fake/backend'

// 360 px: operators check from their phones. No page-wide horizontal scroll on any screen, the menu is a drawer, the matrix
// scrolls inside its own card, dialogs fit.

test.use({ viewport: { width: 360, height: 740 } })

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

test('sign-in with 2FA setup fits a phone', async ({ page }) => {
  await page.goto('/login')
  await noHorizontalScroll(page)
  await page.getByLabel('Email').fill('madina@rizqim.uz')
  await page.getByLabel('Parol', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Kirish' }).click()
  await expect(page.getByRole('img', { name: 'Ilovaga ulash uchun QR kod' })).toBeVisible()
  await noHorizontalScroll(page)
  await page.getByLabel('6 xonali kod').fill(VALID_CODE)
  await page.getByRole('button', { name: 'Yoqish' }).click()
  await expect(page.getByRole('heading', { name: 'Tiklash kodlaringizni saqlang' })).toBeVisible()
  await noHorizontalScroll(page)
})

test('the menu is a drawer, the search is one tap away and the pages do not scroll sideways', async ({ page, api, signIn }) => {
  api.seedAudit(5)
  await openSignedIn(page, signIn, '/today')
  const nav = page.getByRole('navigation', { name: 'Asosiy menyu' })
  await expect(nav).toBeHidden()
  await page.getByRole('button', { name: 'Menyuni ochish' }).click()
  await expect(nav).toBeVisible()
  await expect(nav.getByRole('link')).toHaveCount(13)
  await nav.getByRole('link', { name: 'Jamoa' }).click()
  await expect(page.getByRole('heading', { name: 'Jamoa', level: 1 })).toBeVisible()
  await expect(nav).toBeHidden()
  await noHorizontalScroll(page)

  for (const [link, heading] of [['Harakatlar jurnali', 'Harakatlar jurnali'], ['Rollar', 'Rollar'], ['Sessiyalarim', 'Sessiyalarim'], ['Buyurtmalar', 'Buyurtmalar']] as const) {
    await page.getByRole('button', { name: 'Menyuni ochish' }).click()
    await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: link }).click()
    await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible()
    await noHorizontalScroll(page)
  }

  await page.getByRole('button', { name: 'Qidirish' }).click()
  const palette = page.getByRole('dialog', { name: 'Qidirish' })
  await palette.getByRole('combobox').fill('lazzat')
  await expect(palette.getByRole('option', { name: /^Lazzat Oshxona/ })).toBeVisible()
  const box = await palette.boundingBox()
  expect(box!.x).toBeGreaterThanOrEqual(0)
  expect(box!.x + box!.width).toBeLessThanOrEqual(360)
})

test('the role matrix scrolls inside its own card and a team dialog fits the screen', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/roles')
  const table = page.getByRole('table', { name: 'Rollar va ruxsatlar jadvali' })
  await expect(table).toBeVisible()
  await noHorizontalScroll(page)
  await expect(table.getByRole('switch', { name: "Faqat ko'rish: Jamoani boshqarish" })).toBeAttached()

  await page.goto('/team')
  await page.getByRole('button', { name: 'Xodim taklif qilish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Yangi xodim taklif qilish' })
  await expect(dialog).toBeVisible()
  // The sheet slides up when it opens: measure once it has settled
  await expect
    .poll(async () => {
      const box = await dialog.boundingBox()
      return box ? { right: box.x + box.width, bottom: box.y + box.height } : null
    })
    .toMatchObject({ right: expect.any(Number) })
  await expect.poll(async () => (await dialog.boundingBox())!.y + (await dialog.boundingBox())!.height).toBeLessThanOrEqual(740)
  const box = (await dialog.boundingBox())!
  expect(box.x).toBeGreaterThanOrEqual(0)
  expect(box.x + box.width).toBeLessThanOrEqual(360)
})
