// The fake of Bugun (P4-13): GET /admin/today and GET /admin/today/attention, shaped like AdminTodayController's answers.
// A test changes `summary` or `groups` before (or while) the page is open; the page asks again every 30 seconds.
import { fail, ok, type FakeRequest, type Reply } from './types'

type Direction = 'up' | 'down' | 'same'

const change = (direction: Direction, percent: string | null) => ({ direction, percent })

const kpi = <V extends string | number>(value: V, before: V, yesterday: V, lastWeek: V, vsYesterday: ReturnType<typeof change>, vsLastWeek: ReturnType<typeof change>) => ({
  value,
  yesterday_same_time: before,
  yesterday,
  last_week_same_time: lastWeek,
  last_week: lastWeek,
  vs_yesterday: vsYesterday,
  vs_last_week: vsLastWeek,
})

export function defaultSummary(now = new Date()) {
  const iso = now.toISOString()
  return {
    date: new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tashkent' }).format(now),
    timezone: 'Asia/Tashkent',
    generated_at: iso,
    stats_computed_at: iso,
    live_from: iso,
    kpis: {
      gmv: kpi<string>('12450000.00', '9960000.00', '18000000.00', '12450000.00', change('up', '25.0'), change('same', '0.0')),
      orders: kpi<number>(186, 200, 310, 150, change('down', '7.0'), change('up', '24.0')),
      commission: kpi<string>('1245000.00', '996000.00', '1800000.00', '1100000.00', change('up', '25.0'), change('up', '13.2')),
      subscription_revenue: kpi<string>('747000.00', '0.00', '249000.00', '149000.00', change('up', null), change('up', '401.3')),
      canceled_orders: kpi<number>(4, 3, 9, 5, change('up', '33.3'), change('down', '20.0')),
    },
    channels: [
      { channel: 'telegram', label: 'Telegram', marketplace: true, orders: 170, gmv: '11300000.00' },
      { channel: 'mobile', label: 'Mobil ilova', marketplace: true, orders: 16, gmv: '1150000.00' },
      { channel: 'pos_hall', label: 'Zal (POS)', marketplace: false, orders: 64, gmv: '8400000.00' },
      { channel: 'pos_takeaway', label: 'Olib ketish (POS)', marketplace: false, orders: 0, gmv: '0.00' },
      { channel: 'aggregator', label: 'Agregator', marketplace: false, orders: 0, gmv: '0.00' },
    ],
    regions: [
      { region_id: 1, name: 'Toshkent shahri', orders: 140, gmv: '9800000.00', active_stores: 38 },
      { region_id: 2, name: 'Samarqand viloyati', orders: 40, gmv: '2400000.00', active_stores: 11 },
      { region_id: null, name: "Viloyat ko'rsatilmagan", orders: 6, gmv: '250000.00', active_stores: 2 },
    ],
  }
}

export type FakeSummary = ReturnType<typeof defaultSummary>

export interface FakeAttentionItem {
  kind: string
  severity: 'critical' | 'warning' | 'info'
  title: string
  subtitle: string | null
  href: string
  since: string | null
  store_id: number | null
}

export interface FakeAttentionGroup {
  kind: string
  label: string
  count: number
  severity: 'critical' | 'warning' | 'info'
  items: FakeAttentionItem[]
  /** The permission the real source needs (admin.*): a member without it does not get the group */
  permission: string
}

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

export function defaultGroups(): FakeAttentionGroup[] {
  return [
    {
      kind: 'fiscal_failed',
      label: 'Yiqilgan fiskal cheklar',
      count: 2,
      severity: 'critical',
      permission: 'admin.devices.view',
      items: [
        { kind: 'fiscal_failed', severity: 'critical', title: 'Lazzat Oshxona · Chilonzor', subtitle: "86 000 so'm · Soliq serveri javob bermadi", href: '/pos?fiscal=5f0c2a10-0000-4000-8000-000000000001', since: minutesAgo(40), store_id: 12 },
        { kind: 'fiscal_failed', severity: 'critical', title: 'Shirin Qandolat · Markaz', subtitle: "120 000 so'm · MXIK kodi noto'g'ri", href: '/pos?fiscal=5f0c2a10-0000-4000-8000-000000000002', since: minutesAgo(95), store_id: 14 },
      ],
    },
    {
      kind: 'device_offline',
      label: 'Ish vaqtida oflayn kassalar',
      count: 1,
      severity: 'warning',
      permission: 'admin.devices.view',
      items: [{ kind: 'device_offline', severity: 'warning', title: 'Kassa 1 · Lazzat Express', subtitle: 'Kassa (POS) · Yunusobod · oxirgi aloqa', href: '/pos?device=7a1b0000-0000-4000-8000-000000000001', since: minutesAgo(25), store_id: 13 }],
    },
    {
      kind: 'payout_pending',
      label: "Pul yechish so'rovlari",
      count: 3,
      severity: 'warning',
      permission: 'admin.finance.view',
      items: [{ kind: 'payout_pending', severity: 'warning', title: "Lazzat Oshxona · 1 500 000 so'm", subtitle: "Pul yechish so'rovi tasdiq kutmoqda", href: '/finance?payout=31', since: minutesAgo(300), store_id: 12 }],
    },
  ]
}

export class FakeToday {
  summary: FakeSummary = defaultSummary()
  groups: FakeAttentionGroup[] = defaultGroups()
  /** Set to make GET /today answer 500 (the page shows its error and a retry) */
  failSummary = false

  route(req: FakeRequest, path: string, can: (permission: string) => boolean): Reply | null {
    if (req.method !== 'GET' || (path !== '/today' && path !== '/today/attention')) return null
    if (!can('admin.today.view')) return fail(403, "Ruxsat yo'q.")

    if (path === '/today') {
      if (this.failSummary) return fail(500, 'Server xatosi.')
      return ok({ ...this.summary, generated_at: new Date().toISOString() })
    }

    const limit = Number(req.query.limit ?? 5)
    if (!Number.isInteger(limit) || limit < 1 || limit > 20) return fail(422, "limit 1 dan 20 gacha bo'lishi kerak.")
    const order = { critical: 0, warning: 1, info: 2 }
    const groups = this.groups
      .filter((group) => can(group.permission) && group.count > 0)
      .sort((a, b) => order[a.severity] - order[b.severity] || b.count - a.count)
      .map((group) => ({ kind: group.kind, label: group.label, count: group.count, severity: group.severity, items: group.items.slice(0, limit) }))
    return ok({ groups, generated_at: new Date().toISOString() })
  }
}
