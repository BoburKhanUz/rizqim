import { readFileSync } from 'node:fs'
import { daysBetween, ordersOn, REVENUE_PER_ORDER, storeToday } from './fake/reports'
import { expect, openSignedIn, test } from './fixtures'

// Reports: the figures are the server's (the fake's are a function of the day, so the spec can say what each period must
// show); changing the period asks again; the CSV is a real download.

const addDays = (day: string, count: number) => new Date(Date.parse(`${day}T00:00:00Z`) + count * 86_400_000).toISOString().slice(0, 10)
const som = (amount: number) => `${amount.toLocaleString('en-US').replace(/,/g, ' ')} so'm`

function expected(from: string, to: string) {
  const days = daysBetween(from, to)
  const orders = days.reduce((sum, day) => sum + ordersOn(day), 0)
  return { days: days.length, orders, revenue: orders * REVENUE_PER_ORDER }
}

test('the last seven days are shown, and another period asks the server again', async ({ page, api, signIn, reverb }) => {
  void reverb
  const today = storeToday()
  await openSignedIn(page, signIn, '/reports')

  const week = expected(addDays(today, -6), today)
  const main = page.getByRole('main')
  await expect(main).toContainText(som(week.revenue))
  await expect(main.getByText(String(week.orders), { exact: true }).first()).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/reports/sales').at(-1)?.query).toMatchObject({ from: addDays(today, -6), to: today, group_by: 'day' })

  // 30 days
  await page.getByRole('button', { name: 'Oxirgi 30 kun' }).click()
  const month = expected(addDays(today, -29), today)
  await expect(main).toContainText(som(month.revenue))
  expect(api.callsTo('GET', '/v1/owner/reports/sales').at(-1)?.query).toMatchObject({ from: addDays(today, -29), to: today })
  expect(api.callsTo('GET', '/v1/owner/reports/top-products').at(-1)?.query).toMatchObject({ from: addDays(today, -29), to: today })

  // Yesterday only
  await page.getByRole('button', { name: 'Kecha' }).click()
  const yesterday = addDays(today, -1)
  await expect(main).toContainText(som(expected(yesterday, yesterday).revenue))

  // A range of the person's own
  await page.getByRole('button', { name: 'Boshqa davr' }).click()
  await page.getByLabel('Boshlanish sanasi').fill(addDays(today, -3))
  await page.getByLabel('Tugash sanasi').fill(addDays(today, -1))
  const custom = expected(addDays(today, -3), addDays(today, -1))
  await expect(main).toContainText(som(custom.revenue))
  expect(api.callsTo('GET', '/v1/owner/reports/sales').at(-1)?.query).toMatchObject({ from: addDays(today, -3), to: addDays(today, -1) })

  // A backwards range is not even sent
  const sent = api.callsTo('GET', '/v1/owner/reports/sales').length
  await page.getByLabel('Boshlanish sanasi').fill(addDays(today, 0))
  await page.getByLabel('Tugash sanasi').fill(addDays(today, -5))
  await expect(page.getByText("Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.")).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/reports/sales')).toHaveLength(sent)
})

test('the CSV is downloaded', async ({ page, api, signIn, reverb }) => {
  void reverb
  const today = storeToday()
  await openSignedIn(page, signIn, '/reports')
  await expect(page.getByRole('main')).toContainText(som(expected(addDays(today, -6), today).revenue))

  const [sales] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'CSV yuklab olish' }).click()])
  expect(sales.suggestedFilename()).toMatch(/\.csv$/)
  const text = readFileSync((await sales.path())!, 'utf8')
  const lines = text.replace(/^﻿/, '').trim().split('\n')
  expect(lines[0]).toBe('date,orders,revenue')
  expect(lines).toHaveLength(1 + 7)
  expect(lines[1]).toBe(`${addDays(today, -6)},${ordersOn(addDays(today, -6))},${(ordersOn(addDays(today, -6)) * REVENUE_PER_ORDER).toFixed(2)}`)
  await expect(page.getByRole('status').filter({ hasText: 'Fayl yuklab olindi' })).toBeVisible()
  expect(api.callsTo('GET', '/v1/owner/reports/export')[0].query).toMatchObject({ report: 'sales', from: addDays(today, -6), to: today, group_by: 'day' })

  // The other kind of file, for the same period
  await page.getByLabel('Fayl turi').selectOption('orders')
  const [orders] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'CSV yuklab olish' }).click()])
  expect(readFileSync((await orders.path())!, 'utf8')).toContain('order,status,total')
  expect(api.callsTo('GET', '/v1/owner/reports/export').at(-1)?.query).toMatchObject({ report: 'orders' })
})
