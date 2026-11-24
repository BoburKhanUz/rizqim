import type { Page } from '@playwright/test'
import { heldStoreRefundOrder } from './fake/orders'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-14 Buyurtmalar: the live list (SLA in words, chips with counts, polling while Reverb is off), the order card, the
// interventions with a reason and the step-up, the refund request with four-eyes approval, the SLA limits, phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

const list = (page: Page) => page.getByRole('list', { name: "Buyurtmalar ro'yxati" })

test('the live list: SLA in words, chips with counts, a filter, and a row that opens its card', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/orders', 'sardor@rizqim.uz')
  await expect(page.getByRole('heading', { name: 'Buyurtmalar', level: 1 })).toBeVisible()
  const late = list(page).getByRole('link', { name: '#AB12CD buyurtmasini ochish' })
  await expect(late).toContainText('Qabul qilish: 12 daqiqa kechikdi')
  await expect(list(page).getByRole('link', { name: '#AB99EF buyurtmasini ochish' })).toContainText('Tayyorlash: 4 daqiqa qoldi, kechikish xavfi')
  await expect(page.getByRole('button', { name: 'Faol (2)' })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByTestId('live-status')).toContainText('Har 15 soniyada yangilanadi')

  await page.getByRole('button', { name: 'Kechikkan' }).click()
  await expect(list(page).getByRole('link')).toHaveCount(1)
  expect(api.callsTo('GET', '/orders').at(-1)?.query.sla).toBe('late')

  await page.getByRole('button', { name: 'Yakunlangan (1)' }).click()
  await page.getByRole('button', { name: 'Hammasi' }).click()
  await expect(list(page).getByRole('link')).toHaveCount(1)
  await list(page).getByRole('link', { name: '#AB55AA buyurtmasini ochish' }).click()
  await expect(page).toHaveURL(/\/orders\/ab55aa00-1111-4222-8333-444455556668$/)
  await expect(page.getByRole('heading', { name: 'Buyurtma #AB55AA' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Holatlar tarixi' })).toContainText('Kuryer 1')
})

test('an intervention asks for a reason and a fresh 2FA code, then goes through', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/orders/ab12cd34-1111-4222-8333-444455556666', 'sardor@rizqim.uz')
  await page.getByRole('button', { name: 'Ovqatxonaga yozish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Ovqatxonaga xabar' })
  await dialog.getByLabel('Xabar matni').fill('Buyurtmani qabul qiling, iltimos')
  const send = dialog.getByRole('button', { name: 'Yuborish' })
  await expect(send).toBeDisabled()
  await dialog.getByLabel('Sabab', { exact: true }).fill('12 daqiqa kechikdi')
  await send.click()

  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Xabar yuborildi.')).toBeVisible()
  expect(api.orders.messages).toEqual([{ uuid: 'ab12cd34-1111-4222-8333-444455556666', to: 'store', text: 'Buyurtmani qabul qiling, iltimos', reason: '12 daqiqa kechikdi' }])

  // Cancel, now confirmed: no second code
  await page.getByRole('button', { name: 'Bekor qilish', exact: true }).click()
  const cancel = page.getByRole('dialog', { name: 'Buyurtmani bekor qilish' })
  await cancel.getByLabel('Bekor qilish sababi').selectOption('store_closed')
  await cancel.getByLabel('Sabab', { exact: true }).fill('Ovqatxona javob bermadi')
  await cancel.getByRole('button', { name: 'Ha, bekor qilish' }).click()
  await expect(page.getByText('Buyurtma bekor qilindi.', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Buyurtma #AB12CD' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Holatlar tarixi' })).toContainText('Boshqaruv markazi: Ovqatxona javob bermadi')
  await expect(page.getByRole('button', { name: 'Bekor qilish', exact: true })).toHaveCount(0)
})

test('fix status offers only the next statuses of the lifecycle', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/orders/ab99ef00-1111-4222-8333-444455556667', 'sardor@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: 'Holatni tuzatish' }).click()
  const dialog = page.getByRole('dialog', { name: 'Holatni tuzatish' })
  await expect(dialog.getByLabel('Yangi holat').locator('option')).toHaveText(['Tayyor'])
  await dialog.getByLabel('Sabab', { exact: true }).fill('Oshxona tugmani bosmagan')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('Holat tuzatildi.', { exact: true })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Holatlar tarixi' })).toContainText('Tayyor')
})

test('a refund request waits for another member, who approves it', async ({ page, api, signIn, context }) => {
  await openSignedIn(page, signIn, '/orders/ab55aa00-1111-4222-8333-444455556668', 'sardor@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: "Pulni qaytarishni so'rash" }).click()
  const dialog = page.getByRole('dialog', { name: "Pulni qaytarishni so'rash" })
  await expect(dialog.getByLabel("Summa (so'm)")).toHaveValue('120000')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Taom sovuq keldi')
  await dialog.getByRole('button', { name: "So'rov yuborish" }).click()
  await expect(page.getByText("So'rov yuborildi. Tasdiq kutilmoqda.")).toBeVisible()
  await expect(page.getByText('Tasdiq kutilmoqda: uni boshqa xodim tasdiqlaydi')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Tasdiqlash' })).toHaveCount(0)
  expect(api.orders.refunds).toHaveLength(1)

  // Another member (the superadmin) sees it waiting on the list and approves it on the card
  await context.clearCookies()
  await openSignedIn(page, signIn, '/orders', 'aziza@rizqim.uz', { confirmed: true })
  const queue = page.getByRole('region', { name: 'Tasdiq kutayotgan qaytarishlar' })
  await expect(queue).toContainText("#AB55AA · Lazzat Oshxona · 120 000 so'm · so'radi: Sardor Operator")
  await queue.getByRole('link').click()
  await page.getByRole('button', { name: 'Tasdiqlash' }).click()
  const approve = page.getByRole('dialog', { name: 'Qaytarishni tasdiqlash' })
  await approve.getByLabel('Sabab', { exact: true }).fill('Shikoyat tasdiqlandi')
  await approve.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Tasdiqlandi.', { exact: true })).toBeVisible()
  await expect(page.getByTestId('refund-1')).toContainText('Tasdiqlandi')
  await expect(page.getByTestId('refund-1')).toContainText("120 000 so'm mijozga to'lov tizimi kabinetida yoki naqd qaytariladi")
  expect(api.orders.refunds[0]?.status).toBe('approved')
})

test("a refund from the store's own cashbox: one member proposes, another confirms, the commission goes back", async ({ page, api, signIn, context }) => {
  const held = heldStoreRefundOrder()
  api.orders.orders.push(held)
  api.addMember({ name: 'Feruza Moliya', email: 'feruza@rizqim.uz', platform_role: 'finance', twoFactor: true })
  const block = page.getByRole('region', { name: 'Ovqatxona kassasidan qaytarish' })

  // The finance member proposes it, with a reason and a fresh 2FA code
  await openSignedIn(page, signIn, `/orders/${held.uuid}`, 'feruza@rizqim.uz')
  await expect(block).toContainText("o'zining Payme kabinetidan mijozga qaytardi")
  await expect(block).toContainText('Qaror kutilmoqda')
  await expect(block.getByRole('button', { name: 'Tasdiqlash' })).toHaveCount(0)
  await block.getByRole('button', { name: 'Qaytarishni taklif qilish' }).click()
  const propose = page.getByRole('dialog', { name: 'Qaytarishni taklif qilish' })
  await propose.getByLabel('Sabab', { exact: true }).fill('Payme kabinetida tekshirdim')
  await propose.getByRole('button', { name: 'Taklif qilish' }).click()
  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Qaytarish taklif qilindi: uni boshqa xodim tasdiqlashi kerak.')).toBeVisible()
  await expect(block).toContainText('Siz taklif qilgansiz')
  await expect(block.getByRole('button', { name: 'Tasdiqlash' })).toHaveCount(0)
  expect(held.storeRefunds?.[0]?.status).toBe('pending')
  expect(api.orders.storeRefundKeys.at(-1)).toMatch(/^[\x21-\x7E]{8,}$/)

  // The superadmin sees who proposed it and confirms it
  await context.clearCookies()
  await openSignedIn(page, signIn, `/orders/${held.uuid}`, 'aziza@rizqim.uz', { confirmed: true })
  await expect(block).toContainText('Taklif qildi: Feruza Moliya')
  await expect(block).toContainText('Payme kabinetida tekshirdim')
  await block.getByRole('button', { name: 'Tasdiqlash' }).click()
  const confirm = page.getByRole('dialog', { name: 'Qaytarishni tasdiqlash (ikkinchi xodim)' })
  await confirm.getByLabel('Sabab', { exact: true }).fill('Ikkinchi tasdiq')
  await confirm.getByRole('button', { name: 'Tasdiqlash' }).click()
  await expect(page.getByText('Qaytarish tasdiqlandi: buyurtma qaytarildi, komissiya bekor qilindi.')).toBeVisible()
  await expect(block).toContainText('Tasdiqlandi')
  await expect(block).toContainText("Ko'rib chiqdi: Aziza Karimova")
  await expect(block.getByRole('button')).toHaveCount(0)
  await expect(page.getByRole('region', { name: 'Holatlar tarixi' })).toContainText('Qaytarildi')
  expect(held.status).toBe('refunded')
})

test('a review decided meanwhile answers 409 in words and the card refreshes', async ({ page, api, signIn }) => {
  const held = heldStoreRefundOrder()
  api.orders.orders.push(held)
  await openSignedIn(page, signIn, `/orders/${held.uuid}`, 'aziza@rizqim.uz', { confirmed: true })
  const block = page.getByRole('region', { name: 'Ovqatxona kassasidan qaytarish' })
  await block.getByRole('button', { name: 'Rad etish' }).click()
  // Someone else dismisses it meanwhile
  held.storeRefunds![0]!.status = 'dismissed'
  const dismiss = page.getByRole('dialog', { name: 'Qaytarishni rad etish' })
  await dismiss.getByLabel('Sabab', { exact: true }).fill("Ovqatxona o'zi yuborgan")
  await dismiss.getByRole('button', { name: 'Rad etish' }).click()
  await expect(dismiss).toContainText("Bu qaytarish bo'yicha qaror allaqachon chiqarilgan")
  await dismiss.getByRole('button', { name: 'Bekor qilish' }).click()
  await expect(block).toContainText('Rad etildi')
  await expect(block.getByRole('button')).toHaveCount(0)
})

// Owner decision 2: a refund made in the store's own Paylov cabinet (confirmed by Paylov) is held the same way; the card
// names Paylov, and dismissing it keeps the commission
test("a refund from the store's own Paylov cabinet is named Paylov and dismissing it keeps the commission", async ({ page, api, signIn }) => {
  const held = heldStoreRefundOrder()
  held.payment_method = 'paylov'
  held.storeRefunds![0] = { ...held.storeRefunds![0]!, provider: 'paylov', reason_code: null }
  api.orders.orders.push(held)
  await openSignedIn(page, signIn, `/orders/${held.uuid}`, 'aziza@rizqim.uz', { confirmed: true })
  const block = page.getByRole('region', { name: 'Ovqatxona kassasidan qaytarish' })
  await expect(block).toContainText("o'zining Paylov kabinetidan mijozga qaytardi")
  await expect(block).toContainText('Ovqatxonaning Paylov kabinetida pul haqiqatan')
  await expect(block).toContainText('Paylov · ovqatxona qaytardi')
  await expect(block).not.toContainText('Payme')
  if (process.env.SHOTS_DIR) await page.screenshot({ path: `${process.env.SHOTS_DIR}/admin-store-refund-paylov.png`, fullPage: false })
  await block.getByRole('button', { name: 'Rad etish' }).click()
  const dismiss = page.getByRole('dialog', { name: 'Qaytarishni rad etish' })
  await dismiss.getByLabel('Sabab', { exact: true }).fill("Ovqatxona o'z hisobidan qaytargan")
  await dismiss.getByRole('button', { name: 'Rad etish' }).click()
  await expect(page.getByText('Qaytarish rad etildi: komissiya saqlanadi.')).toBeVisible()
  await expect(block).toContainText('Rad etildi')
  expect(held.storeRefunds?.[0]?.status).toBe('dismissed')
  expect(held.status).toBe('delivered')
})

test('the superadmin changes the SLA limits with a reason', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/orders', 'aziza@rizqim.uz', { confirmed: true })
  await page.getByRole('button', { name: 'SLA chegaralari' }).click()
  const dialog = page.getByRole('dialog', { name: 'SLA chegaralari' })
  await dialog.getByLabel('Qabul qilish (daqiqa)').fill('20')
  await dialog.getByLabel('Sabab', { exact: true }).fill('Bayram kuni')
  await dialog.getByRole('button', { name: 'Saqlash' }).click()
  await expect(page.getByText('SLA chegaralari saqlandi.')).toBeVisible()
  await expect(page.getByText('SLA: qabul qilish 20 daq, tayyorlash 30 daq, yetkazish 45 daq')).toBeVisible()
  expect(api.orders.limits.accept).toBe(20)
  // 17 minutes of 20: no longer late, at risk
  await expect(list(page).getByRole('link', { name: '#AB12CD buyurtmasini ochish' })).toContainText('kechikish xavfi')
})

test('the operator does not get the SLA settings nor the approval queue', async ({ page, signIn }) => {
  await openSignedIn(page, signIn, '/orders', 'sardor@rizqim.uz')
  await expect(list(page)).toBeVisible()
  await expect(page.getByRole('button', { name: 'SLA chegaralari' })).toHaveCount(0)
  await expect(page.getByRole('region', { name: 'Tasdiq kutayotgan qaytarishlar' })).toHaveCount(0)
})

test.describe('at phone width', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('the list and the card fit 360 px', async ({ page, signIn }) => {
    await openSignedIn(page, signIn, '/orders', 'sardor@rizqim.uz')
    await expect(list(page).getByRole('link').first()).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: "Qo'shimcha filtrlar" }).click()
    await noHorizontalScroll(page)
    await list(page).getByRole('link', { name: '#AB12CD buyurtmasini ochish' }).click()
    await expect(page.getByRole('heading', { name: 'Buyurtma #AB12CD' })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Bekor qilish', exact: true }).click()
    await expect(page.getByRole('dialog', { name: 'Buyurtmani bekor qilish' })).toBeVisible()
    await noHorizontalScroll(page)
  })
})
