import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-23 Moliya: the payout card with netting ("Hisoblangan — Ushlangan qarz — O'tkaziladi"), approval with a reason and the
// step-up, two members from 1 000 000 so'm, a manual adjustment with a document and its second approval, the monthly report's
// Excel file, a link from Bugun, phone width.

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

test('a payout nets the debt first and asks for a reason and the step-up', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/finance')
  await expect(page.getByRole('heading', { name: 'Moliya', level: 1 })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Payoutlar (2)' })).toHaveAttribute('aria-selected', 'true')
  await page.getByRole('button', { name: 'Payout #12ni ochish' }).click()

  const card = page.getByRole('dialog', { name: 'Payout #12' })
  const breakdown = card.getByRole('region', { name: 'Hisob' })
  await expect(breakdown).toContainText('Hisoblangan')
  await expect(breakdown).toContainText('OB-202609-3')
  await expect(breakdown).toContainText('OB-202610-3')
  await expect(breakdown.getByText("O'tkaziladi")).toBeVisible()
  await expect(breakdown).toContainText(/102\s000/)
  await card.getByRole('button', { name: 'Tasdiqlash' }).click()

  const form = page.getByRole('dialog', { name: 'Payoutni tasdiqlash' })
  const submit = form.getByRole('button', { name: 'Tasdiqlash' })
  await expect(submit).toBeDisabled()
  await form.getByLabel('Sabab', { exact: true }).fill("Kartaga o'tkazildi")
  await submit.click()
  await confirmStepUp(page)
  await expect(page.getByText(/Tasdiqlandi: 102\s000\sso'm kartaga, 298\s000\sso'm qarzga/)).toBeVisible()
  await expect(page.getByRole('dialog', { name: 'Payout #12' })).toContainText("O'tkazildi")

  expect(api.finance.decisions).toHaveLength(1)
  expect(api.finance.decisions[0]).toMatchObject({ what: 'payouts', id: 12, decision: 'approve', reason: "Kartaga o'tkazildi" })
  expect(api.finance.decisions[0]?.key).toMatch(/.{8,}/)
  expect(api.finance.stores[0]?.wallet).toBe(100_000)
})

test('a payout of a million needs a second member', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Nodira Moliya', email: 'nodira@rizqim.uz', platform_role: 'finance', twoFactor: true })
  await openSignedIn(page, signIn, '/finance?payout=20')
  const card = page.getByRole('dialog', { name: 'Payout #20' })
  await expect(card).toContainText('Bu summani ikki xodim tasdiqlaydi.')
  await card.getByRole('button', { name: 'Birinchi tasdiq' }).click()
  const form = page.getByRole('dialog', { name: 'Payoutni tasdiqlash' })
  await form.getByLabel('Sabab', { exact: true }).fill('Hujjatlar joyida')
  await form.getByRole('button', { name: 'Birinchi tasdiq' }).click()
  await confirmStepUp(page)
  await expect(page.getByText('Birinchi tasdiq yozildi. Endi boshqa xodim tasdiqlashi kerak.')).toBeVisible()
  await expect(page.getByRole('dialog', { name: 'Payout #20' })).toContainText('ikkinchi tasdiqni boshqa xodim beradi')
  expect(api.finance.payouts.find((payout) => payout.id === 20)?.status).toBe('pending')

  // Another member: it waits in the second-approval queue; their approval pays it out
  await signIn('nodira@rizqim.uz', { confirmed: true })
  await page.goto('/finance')
  const queue = page.getByRole('region', { name: 'Ikkinchi tasdiq kutilmoqda' })
  await expect(queue).toContainText('Somsa Markazi')
  await queue.getByRole('button', { name: "Ko'rish" }).click()
  const second = page.getByRole('dialog', { name: 'Payout #20' })
  await second.getByRole('button', { name: 'Tasdiqlash' }).click()
  const secondForm = page.getByRole('dialog', { name: 'Payoutni tasdiqlash' })
  await secondForm.getByLabel('Sabab', { exact: true }).fill("Ikkinchi tasdiq, o'tkazildi")
  await secondForm.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText(/Tasdiqlandi: 1\s380\s000\sso'm kartaga, 120\s000\sso'm qarzga/)).toBeVisible()
  expect(api.finance.payouts.find((payout) => payout.id === 20)?.status).toBe('completed')
})

test('a manual adjustment with a document waits for another member and is applied on approval', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Nodira Moliya', email: 'nodira@rizqim.uz', platform_role: 'finance', twoFactor: true })
  await openSignedIn(page, signIn, '/finance?tab=adjustments', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: 'Yangi tuzatish' }).click()
  const dialog = page.getByRole('dialog', { name: "Qo'lda tuzatish" })
  await dialog.getByRole('combobox', { name: 'Ovqatxona' }).fill('Lazzat')
  await dialog.getByRole('option', { name: /Lazzat Oshxona/ }).click()
  await dialog.getByLabel("Nima o'zgaradi").selectOption('wallet_credit')
  await dialog.getByLabel("Summa, so'm").fill('1 500 000')
  await expect(dialog).toContainText("tasdiqlanguncha balans o'zgarmaydi")
  await dialog.getByLabel('Sabab', { exact: true }).fill("Bank ko'chirmasi bo'yicha")
  await dialog.getByLabel('Hujjat (ixtiyoriy)').setInputFiles({ name: 'kochirma.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 test') })
  await dialog.getByRole('button', { name: 'Yaratish' }).click()
  await expect(page.getByText('Tuzatish yaratildi. Endi boshqa xodim tasdiqlashi kerak.')).toBeVisible()
  const own = page.getByRole('dialog', { name: 'Tuzatish #1' })
  await expect(own).toContainText('Siz yaratgansiz: tasdiqni boshqa xodim beradi.')
  await expect(own).toContainText('kochirma.pdf')
  expect(api.finance.adjustments[0]).toMatchObject({ kind: 'wallet_credit', amount: 1_500_000, status: 'pending', document: 'kochirma.pdf' })
  expect(api.finance.stores[0]?.wallet).toBe(500_000)

  await signIn('nodira@rizqim.uz', { confirmed: true })
  await page.goto('/finance?tab=adjustments&adjustment=1')
  const card = page.getByRole('dialog', { name: 'Tuzatish #1' })
  await card.getByRole('button', { name: 'Tasdiqlash' }).click()
  const form = page.getByRole('dialog', { name: 'Tuzatishni tasdiqlash' })
  await form.getByLabel('Sabab', { exact: true }).fill("Ko'chirma tekshirildi")
  await form.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText("Tasdiqlandi, tuzatish qo'llandi.")).toBeVisible()
  expect(api.finance.stores[0]?.wallet).toBe(2_000_000)
})

test('the monthly report and its Excel file', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/finance?tab=report', 'aziza@rizqim.uz')
  await expect(page.getByTestId('report-total')).toContainText(/177\s500/)
  await expect(page.getByRole('region', { name: "Komissiya kanal bo'yicha" })).toContainText('RIZQIM kuryeri')
  await expect(page.getByRole('region', { name: "Ovqatxonalar bo'yicha" })).toContainText('Somsa Markazi')
  const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Excel yuklab olish' }).click()])
  expect(download.suggestedFilename()).toMatch(/^rizqim-daromad-\d{4}-\d{2}\.xlsx$/)
  await expect(page.getByText('Fayl yuklab olindi.')).toBeVisible()
  expect(api.finance.exports).toHaveLength(1)
})

test.describe('phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('every tab and the payout card fit a phone', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/finance')
    await expect(page.getByRole('heading', { name: 'Moliya', level: 1 })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Payout #12ni ochish' })).toBeVisible()
    await noHorizontalScroll(page)
    for (const tab of [/^To'ldirishlar/, /^Hisob-kitob xatolari/, /^Balans va qarzlar/, /^Qo'lda tuzatish/, /^Oylik hisobot/]) {
      await page.getByRole('tab', { name: tab }).click()
      await expect(page.getByRole('tabpanel')).toBeVisible()
      await noHorizontalScroll(page)
    }
    await expect(page.getByTestId('report-total')).toBeVisible()
    await page.getByRole('tab', { name: /^Payoutlar/ }).click()
    await page.getByRole('button', { name: 'Payout #12ni ochish' }).click()
    const dialog = page.getByRole('dialog', { name: 'Payout #12' })
    await expect(dialog.getByRole('region', { name: 'Hisob' })).toBeVisible()
    await expect.poll(async () => (await dialog.boundingBox())!.y + (await dialog.boundingBox())!.height).toBeLessThanOrEqual(740)
    const box = (await dialog.boundingBox())!
    expect(box.x).toBeGreaterThanOrEqual(0)
    expect(box.x + box.width).toBeLessThanOrEqual(360)
  })
})
