import type { Page } from '@playwright/test'
import { expect, openSignedIn, test, VALID_CODE } from './fixtures'

// P4-15 Monitoring: Holat (status in words), Ogohlantirishlar (acknowledge, resolve, history), Yiqilgan vazifalar (detail, retry,
// delete with a reason and the 2FA step-up), Qoidalar (thresholds, switches, reason), what a viewer sees, the phone width.

async function noHorizontalScroll(page: Page) {
  const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
  expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
}

const tile = (page: Page, name: string) => page.getByRole('heading', { name, level: 3 }).locator('xpath=ancestor::li[1]')

test('Holat says every part of the system in words and moves to the alerts from its tile', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/monitoring')
  await expect(page.getByRole('heading', { name: 'Monitoring', level: 1 })).toBeVisible()
  await expect(page.getByText("E'tibor kerak", { exact: true }).first()).toBeVisible()

  await expect(tile(page, "Ma'lumotlar bazasi")).toContainText('Ishlayapti')
  await expect(tile(page, 'Redis')).toContainText('Javob vaqti: 0.6 ms')
  await expect(tile(page, 'Navbatlar')).toContainText("To'planib qolgan")
  await expect(tile(page, 'Navbatlar')).toContainText('«default»: 640 ta, eng eskisi 13 daqiqa kutmoqda')
  await expect(tile(page, 'Zaxira nusxa')).toContainText('Eskirgan')
  await expect(tile(page, 'Yiqilgan vazifalar')).toContainText('3 ta, oxirgi 24 soatda 2 ta')
  await expect(tile(page, 'Ilova')).toContainText('1.4.0')
  await expect(page.getByRole('link', { name: /Sentry'da ochish/ })).toHaveAttribute('href', /sentry\.io/)

  // The server comes back changed: a refresh shows it
  api.monitoring.health.database = { status: 'fail', latency_ms: undefined } as never
  api.monitoring.health.status = 'fail'
  await page.getByRole('button', { name: 'Yangilash' }).click()
  await expect(page.getByText('Tizim ishlamayapti', { exact: true })).toBeVisible()
  await expect(tile(page, "Ma'lumotlar bazasi")).toContainText('Baza javob bermayapti')

  await tile(page, 'Ogohlantirishlar').getByRole('button', { name: "Ko'rish" }).click()
  await expect(page).toHaveURL(/\/monitoring\?tab=alerts$/)
  await expect(page.getByRole('list', { name: "Ogohlantirishlar ro'yxati" })).toBeVisible()
})

test('a failing health call is said in place and the other tabs still work', async ({ page, api, signIn }) => {
  api.monitoring.failHealthOnce = 500
  await openSignedIn(page, signIn, '/monitoring')
  await expect(page.getByText('Holat yuklanmadi')).toBeVisible()
  await page.getByRole('tab', { name: 'Yiqilgan vazifalar' }).click()
  await expect(page.getByRole('list', { name: "Yiqilgan vazifalar ro'yxati" })).toBeVisible()
  // Back on Holat the page asks again, and the server has recovered
  await page.getByRole('tab', { name: 'Holat' }).click()
  await expect(tile(page, 'Scheduler')).toContainText('Oxirgi yurak urishi')
})

test('alerts: the link from Bugun opens the alert, acknowledge with a note, resolve, then the history', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/monitoring?tab=alerts&alert=2', 'sardor@rizqim.uz')
  const list = page.getByRole('list', { name: "Ogohlantirishlar ro'yxati" })
  await expect(list.locator(':scope > li')).toHaveCount(3)
  await expect(list.locator(':scope > li').nth(1)).toHaveAttribute('aria-current', 'true')
  await expect(page.getByRole('tab', { name: 'Ogohlantirishlar (3)' })).toHaveAttribute('aria-selected', 'true')

  await page.getByRole('button', { name: /^Qabul qilish: Oxirgi zaxira nusxa/ }).click()
  const ack = page.getByRole('dialog', { name: 'Ogohlantirishni qabul qilasizmi?' })
  await ack.getByLabel('Izoh (ixtiyoriy)').fill('Zaxira xizmatini qayta ishga tushiryapman')
  await ack.getByRole('button', { name: 'Qabul qilish' }).click()
  await expect(page.getByText('Ogohlantirish qabul qilindi.')).toBeVisible()
  expect(api.callsTo('POST', '/monitoring/alerts/1/acknowledge')[0]?.body).toEqual({ note: 'Zaxira xizmatini qayta ishga tushiryapman' })
  await expect(list.locator(':scope > li').filter({ hasText: 'Oxirgi zaxira nusxa' })).toContainText('Qabul qildi: Sardor Operator')

  await page.getByRole('button', { name: /^Hal qilish: Oxirgi zaxira nusxa/ }).click()
  await page.getByRole('dialog', { name: 'Ogohlantirishni hal qildingizmi?' }).getByRole('button', { name: 'Hal qilish' }).click()
  await expect(page.getByText('Ogohlantirish hal qilindi.')).toBeVisible()
  await expect(list.locator(':scope > li')).toHaveCount(2)

  await page.getByRole('button', { name: /^Hal bo'lgan/ }).click()
  await expect(page.getByText('Muammo o\'z-o\'zidan tugadi', { exact: false })).toBeVisible()
  await expect(page.getByText(/Hal qildi: Sardor Operator/)).toBeVisible()
})

test('failed jobs: the detail is redacted, retry works, delete asks for a reason and the 2FA code once', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/monitoring?tab=jobs', 'sardor@rizqim.uz')
  const list = page.getByRole('list', { name: "Yiqilgan vazifalar ro'yxati" })
  await expect(list.locator(':scope > li')).toHaveCount(3)
  await expect(page.getByText('Jami: 3 ta')).toBeVisible()

  await list.getByRole('button', { name: /^Ochish: SendTelegramRequest/ }).click()
  const detail = page.getByRole('dialog', { name: 'Yiqilgan vazifa' })
  await expect(detail.getByText(/#1 \{main\}/)).toBeVisible()
  await expect(detail.getByText('"api_key": "[redacted]"')).toBeVisible()
  await detail.getByRole('button', { name: 'Qayta urinish' }).click()
  await expect(page.getByText('Vazifa navbatga qaytarildi.')).toBeVisible()
  await expect(list.locator(':scope > li')).toHaveCount(2)
  expect(api.monitoring.jobs).toHaveLength(2)

  await list.getByRole('button', { name: /^Ochish: SubmitClickFiscalData/ }).click()
  await page.getByRole('dialog', { name: 'Yiqilgan vazifa' }).getByRole('button', { name: "O'chirish" }).click()
  const reason = page.getByRole('dialog', { name: "Vazifani o'chirasizmi?" })
  await reason.getByLabel('Sabab').fill('Click fiskal sozlamasi tuzatildi')
  await reason.getByRole('button', { name: "O'chirish" }).click()

  const stepUp = page.getByRole('alertdialog', { name: 'Amalni tasdiqlang' })
  await expect(stepUp).toBeVisible()
  await stepUp.getByLabel('6 xonali kod').fill(VALID_CODE)
  await stepUp.getByRole('button', { name: 'Tasdiqlash' }).click()

  await expect(page.getByText("Vazifa o'chirildi.")).toBeVisible()
  await expect(list.locator(':scope > li')).toHaveCount(1)
  const calls = api.callsTo('DELETE', '/monitoring/failed-jobs/00000000-0000-4000-8000-000000000002')
  expect(calls.map((call) => call.status)).toEqual([403, 200])
  expect(calls[1]?.body).toEqual({ reason: 'Click fiskal sozlamasi tuzatildi' })
})

test('rules: change a threshold and a switch, give the reason and the code; the form keeps what the server kept', async ({ page, api, signIn }) => {
  await openSignedIn(page, signIn, '/monitoring?tab=rules', 'aziza@rizqim.uz', { confirmed: true })
  const rules = page.getByRole('list', { name: 'Ogohlantirish qoidalari' })
  const queue = rules.locator(':scope > li').filter({ hasText: "Navbat to'planishi" })
  await expect(queue.getByLabel('Navbatdagi vazifalar soni (ta)')).toHaveValue('500')
  await expect(queue.getByText('Standart: 500 ta')).toBeVisible()

  await queue.getByLabel('Navbatdagi vazifalar soni (ta)').fill('800')
  await queue.getByRole('switch', { name: "Navbat to'planishi: Telegramga yuborish" }).click()
  await queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" }).click()
  const dialog = page.getByRole('dialog', { name: "Qoidani o'zgartirasizmi?" })
  await dialog.getByLabel('Sabab').fill("Kunduzi shovqin ko'p")
  await dialog.getByRole('button', { name: 'Saqlash' }).click()

  await expect(page.getByText("«Navbat to'planishi» qoidasi saqlandi.")).toBeVisible()
  expect(api.callsTo('PUT', '/monitoring/rules/queue_backlog')[0]?.body).toEqual({ threshold: 800, telegram: false, reason: "Kunduzi shovqin ko'p" })
  await expect(queue.getByLabel('Navbatdagi vazifalar soni (ta)')).toHaveValue('800')
  await expect(queue.getByText(/Oxirgi o'zgartirish: Aziza Karimova/)).toBeVisible()
  await expect(queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" })).toBeDisabled()

  // Switching a rule off closes its alerts
  await expect(queue.getByText('1 ta ogohlantirish ochiq')).toBeVisible()
  await rules.locator(':scope > li').filter({ hasText: "Navbat to'planishi" }).getByRole('switch', { name: "Navbat to'planishi: yoqilgan" }).click()
  await queue.getByRole('button', { name: "Saqlash: Navbat to'planishi" }).click()
  await page.getByRole('dialog', { name: "Qoidani o'zgartirasizmi?" }).getByLabel('Sabab').fill('Vaqtincha kerak emas')
  await page.getByRole('dialog', { name: "Qoidani o'zgartirasizmi?" }).getByRole('button', { name: 'Saqlash' }).click()
  await expect(queue.getByText('1 ta ogohlantirish ochiq')).toHaveCount(0)
  expect(api.callsTo('PUT', '/monitoring/rules/queue_backlog')).toHaveLength(2)
  expect(api.monitoring.alerts.find((alert) => alert.id === 2)?.state).toBe('resolved')
})

test('a viewer reads everything and gets no buttons', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Nodira Kuzatuvchi', email: 'nodira@rizqim.uz', platform_role: 'viewer', twoFactor: true })
  await openSignedIn(page, signIn, '/monitoring?tab=alerts', 'nodira@rizqim.uz')
  await expect(page.getByRole('list', { name: "Ogohlantirishlar ro'yxati" })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Qabul qilish:/ })).toHaveCount(0)
  await expect(page.getByText("Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q.")).toBeVisible()

  await page.getByRole('tab', { name: 'Qoidalar' }).click()
  const rule = page.getByRole('list', { name: 'Ogohlantirish qoidalari' }).locator(':scope > li').first()
  await expect(rule.getByRole('switch').first()).toBeDisabled()
  await expect(rule.getByRole('button', { name: /^Saqlash/ })).toHaveCount(0)
})

test('a member without monitoring.view is taken to their own section and the monitoring API is never called', async ({ page, api, signIn }) => {
  api.addMember({ name: 'Nodira Savdo', email: 'nodira2@rizqim.uz', platform_role: 'sales', twoFactor: true })
  await openSignedIn(page, signIn, '/monitoring', 'nodira2@rizqim.uz')
  await expect(page).not.toHaveURL(/\/monitoring/)
  await expect(page.getByRole('navigation', { name: 'Asosiy menyu' }).getByRole('link', { name: 'Monitoring' })).toHaveCount(0)
  expect(api.calls.filter((call) => call.path.startsWith('/monitoring'))).toHaveLength(0)
})

test.describe('at 360 px', () => {
  test.use({ viewport: { width: 360, height: 740 } })

  test('every tab and dialog fits a phone', async ({ page, api, signIn }) => {
    api.monitoring.seedJobs(2)
    await openSignedIn(page, signIn, '/monitoring')
    await expect(tile(page, 'Scheduler')).toBeVisible()
    await noHorizontalScroll(page)

    await page.getByRole('tab', { name: /^Ogohlantirishlar/ }).click()
    await expect(page.getByRole('list', { name: "Ogohlantirishlar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Tafsilot' }).first().click()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: /^Qabul qilish:/ }).first().click()
    await expect(page.getByRole('dialog', { name: 'Ogohlantirishni qabul qilasizmi?' })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Bekor qilish' }).click()

    await page.getByRole('tab', { name: 'Yiqilgan vazifalar' }).click()
    await expect(page.getByRole('list', { name: "Yiqilgan vazifalar ro'yxati" })).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: /^Ochish: SendTelegramRequest/ }).first().click()
    await expect(page.getByRole('dialog', { name: 'Yiqilgan vazifa' }).getByText(/#1 \{main\}/)).toBeVisible()
    await noHorizontalScroll(page)
    await page.getByRole('button', { name: 'Yopish' }).first().click()

    await page.getByRole('tab', { name: 'Qoidalar' }).click()
    await expect(page.getByRole('list', { name: 'Ogohlantirish qoidalari' })).toBeVisible()
    await noHorizontalScroll(page)
  })
})
