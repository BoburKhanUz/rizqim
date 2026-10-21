// The numbers of the fake backend: today's analytics (dashboard and the store's calendar day), the sales and top-products
// reports of a period and the CSV export. The figures are a function of the day, so a test can tell one period from
// another and compute what the screen should say.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const DAY_MS = 86_400_000

/** The orders the fake "sold" on a day (YYYY-MM-DD): 2 to 6, by the day of the month. */
export const ordersOn = (day: string): number => 2 + (Number(day.slice(8, 10)) % 5)
export const REVENUE_PER_ORDER = 40_000

export function daysBetween(from: string, to: string): string[] {
  const days: string[] = []
  for (let time = Date.parse(`${from}T00:00:00Z`); time <= Date.parse(`${to}T00:00:00Z`); time += DAY_MS) days.push(new Date(time).toISOString().slice(0, 10))
  return days
}

// "Today" of the store: the real date in Tashkent (the fake's store timezone)
export const storeToday = (): string => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Tashkent' })

export class FakeReports {
  constructor(private readonly backend: FakeBackend) {}

  private analytics(query: Record<string, string>): Reply {
    const today = storeToday()
    return ok({
      period: { key: query.period ?? 'today', from: today, to: today, days: 1, timezone: 'Asia/Tashkent', basis: 'created_at' },
      summary: {
        orders_count: this.backend.orders.orders.length,
        delivered_count: 0,
        canceled_count: 0,
        refunded_count: 0,
        active_count: this.backend.orders.orders.length,
        revenue: 0,
        average_check: 0,
      },
      by_status: {},
      by_payment_method: [],
      by_order_type: [],
      by_channel: [],
      top_products: [],
      couriers: [],
      times: { average_prep_seconds: null, average_delivery_seconds: null },
      daily: [],
      generated_at: new Date().toISOString(),
    })
  }

  private sales(query: Record<string, string>): Reply {
    const { from, to } = query
    if (!from || !to || from > to) return fail(422, 'The given data was invalid.', { to: ["Tugash sanasi boshlanishidan oldin bo'lmasin."] })
    const days = daysBetween(from, to)
    const series = days.map((date) => ({ date, orders: ordersOn(date), delivered: ordersOn(date) - 1, revenue: (ordersOn(date) * REVENUE_PER_ORDER).toFixed(2) }))
    const orders = series.reduce((sum, row) => sum + row.orders, 0)
    const delivered = series.reduce((sum, row) => sum + row.delivered, 0)
    const revenue = orders * REVENUE_PER_ORDER
    return ok({
      period: { from, to, days: days.length, timezone: 'Asia/Tashkent', basis: 'created_at', group_by: query.group_by ?? 'day' },
      branch_id: query.branch_id ? Number(query.branch_id) : null,
      summary: {
        orders_count: orders,
        delivered_count: delivered,
        canceled_count: orders - delivered,
        refunded_count: 0,
        active_count: 0,
        revenue: revenue.toFixed(2),
        average_check: orders === 0 ? '0.00' : (revenue / orders).toFixed(2),
      },
      by_status: { delivered, canceled: orders - delivered },
      series,
      by_channel: [{ channel: 'telegram', label: 'Telegram', orders, revenue: revenue.toFixed(2) }],
      by_branch: [{ branch_id: 1, name: 'Chilonzor', orders, delivered, revenue: revenue.toFixed(2) }],
      by_payment_method: [{ method: 'cash', orders, revenue: revenue.toFixed(2) }],
      by_order_type: [{ type: 'pickup', orders, revenue: revenue.toFixed(2) }],
    })
  }

  private topProducts(query: Record<string, string>): Reply {
    const days = daysBetween(query.from, query.to).length
    return ok({
      period: { from: query.from, to: query.to, days, timezone: 'Asia/Tashkent', basis: 'created_at' },
      branch_id: null,
      sort: query.sort ?? 'quantity',
      products: [
        { name: 'Lavash', quantity: days * 3, revenue: (days * 75_000).toFixed(2), orders: days * 2 },
        { name: 'Osh', quantity: days, revenue: (days * 35_000).toFixed(2), orders: days },
      ],
    })
  }

  private export(query: Record<string, string>): Reply {
    const days = daysBetween(query.from, query.to)
    const header = query.report === 'orders' ? 'order,status,total' : query.report === 'products' ? 'product,quantity,revenue' : 'date,orders,revenue'
    const rows = days.map((day) => `${day},${ordersOn(day)},${(ordersOn(day) * REVENUE_PER_ORDER).toFixed(2)}`)
    // UTF-8 with a BOM, like the backend's files. No Content-Disposition is exposed to the page: the backend's CORS
    // config exposes no headers, so the browser falls back to a file name of its own (see the finding in the e2e report).
    return { status: 200, body: null, raw: `﻿${[header, ...rows].join('\n')}\n`, headers: { 'Content-Type': 'text/csv; charset=UTF-8', 'Content-Disposition': 'attachment; filename="hisobot.csv"' } }
  }

  handle(req: FakeRequest): Reply | null {
    if (req.method !== 'GET') return null
    switch (req.path) {
      case '/v1/owner/analytics':
        return this.analytics(req.query)
      case '/v1/owner/reports/sales':
        return this.sales(req.query)
      case '/v1/owner/reports/top-products':
        return this.topProducts(req.query)
      case '/v1/owner/reports/export':
        return this.export(req.query)
    }
    return null
  }
}
