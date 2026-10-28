import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'
import { CONNECTION_UUID } from './fake/integrations'

// P4-31 Integratsiyalar: calm empty screens before phase 5; with a connection: the card (keys by name and last 4 only), pausing
// with a reason and the step-up, the journal with redacted bodies, the stuck orders; the phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

test('before phase 5 every tab is empty and says what will be there', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/today')
  await page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Integratsiyalar' }).click()
  await expect(page.getByRole('heading', { name: 'Integratsiyalar', level: 1 })).toBeVisible()
  await expect(page.getByText("Hali hech qanday ulanish yo'q")).toBeVisible()
  await page.getByRole('tab', { name: 'Hodisalar jurnali' }).click()
  await expect(page.getByText("Jurnal bo'sh")).toBeVisible()
  await page.getByRole('tab', { name: 'Menyu xaritasi' }).click()
  await expect(page.getByText('Menyu xaritasi 5-bosqichda')).toBeVisible()
  await page.getByRole('tab', { name: 'Qotib qolganlar' }).click()
  await expect(page.getByText("Qotib qolgan buyurtma yo'q")).toBeVisible()
  await expect(page).toHaveURL(/\/integrations\?tab=stuck$/)
})

test('a connection card, pausing it with a reason and the step-up, the journal and the stuck orders', async ({ page, api, signIn }) => {
  api.integrations.seed()
  await openSignedIn(page, signIn, '/integrations')
  await expect(page.getByText("E'tibor kerak · 62")).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Qotib qolganlar (1)' })).toBeVisible()

  await page.getByRole('button', { name: 'iiko · Lazzat Oshxona: ochish' }).click()
  await expect(page).toHaveURL(new RegExp(`connection=${CONNECTION_UUID}`))
  await expect(page.getByText('kiritilgan, oxiri ••••4321')).toBeVisible()
  await expect(page.getByText('[redacted]')).toBeVisible()

  await page.getByRole('button', { name: "To'xtatish" }).click()
  const dialog = page.getByRole('dialog', { name: "iiko · Lazzat Oshxona: to'xtatilsinmi?" })
  await dialog.getByLabel('Sabab').fill("Kassa o'chiq")
  await dialog.getByRole('button', { name: "To'xtatish" }).click()
  const step = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await step.getByLabel('6 xonali kod').fill(VALID_CODE)
  await step.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText("Ulanish to'xtatildi.")).toBeVisible()
  await expect(page.getByRole('button', { name: 'Qayta yoqish' })).toBeVisible()
  expect(api.callsTo('POST', `/integrations/connections/${CONNECTION_UUID}/pause`).map((call) => call.status)).toEqual([403, 200])

  // From the card's last events to the event itself
  await page.getByRole('button', { name: /^order\.push, .*ochish$/ }).click()
  await expect(page.getByRole('heading', { name: 'order.push' })).toBeVisible()
  await expect(page.getByText(/"access_token": "\[redacted\]"/)).toBeVisible()
  await expect(page.getByText(/\+99890\*\*\*\*\*33/).first()).toBeVisible()

  await page.getByRole('tab', { name: 'Qotib qolganlar (1)' }).click()
  await expect(page.getByRole('link', { name: 'Buyurtma AB12CD' })).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666')
})

test('a viewer reads the card but cannot pause; support has no Integratsiyalar', async ({ page, api, signIn }) => {
  api.integrations.seed()
  api.addMember({ name: 'Vali Viewer', email: 'vali@rizqim.uz', platform_role: 'viewer', twoFactor: true })
  await openSignedIn(page, signIn, `/integrations?connection=${CONNECTION_UUID}`, 'vali@rizqim.uz')
  await expect(page.getByText('kiritilgan, oxiri ••••4321')).toBeVisible()
  await expect(page.getByRole('button', { name: "To'xtatish" })).toHaveCount(0)
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('the tabs, the card and the event fit 360 px', async ({ page, api, signIn }) => {
    api.integrations.seed()
    await openSignedIn(page, signIn, '/integrations')
    await expect(page.getByText("E'tibor kerak · 62")).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'iiko · Lazzat Oshxona: ochish' }).click()
    await expect(page.getByText('kiritilgan, oxiri ••••4321')).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('tab', { name: 'Hodisalar jurnali' }).click()
    await page.getByRole('button', { name: /^order\.push, .*ochish$/ }).click()
    await expect(page.getByText(/"access_token"/)).toBeVisible()
    await noHorizontalScroll(page)
    for (const tab of ['Menyu xaritasi', 'Qotib qolganlar (1)']) {
      await page.getByRole('tab', { name: tab }).click()
      await noHorizontalScroll(page)
    }
  })
})
