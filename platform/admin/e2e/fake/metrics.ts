// The fake of Ko'rsatkichlar and the Excel exports (P4-31): /admin/metrics, /admin/metrics/funnel and /admin/exports/*, shaped
// like AdminMetricsController and AdminExportController answer. Reads need finance.view or stores.view; an export needs
// admin.export and the list's own view permission (403 otherwise), is throttled when a test says so (429) and is recorded
// with its filters. The numbers are the store set of the backend's AdminMetricsTest (today is 2026-10-28): four months of
// history (Jul..Oct, the last one in progress), older months empty.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

const EXPORTS: Record<string, { name: string; views: string[] }> = {
  orders: { name: 'orders', views: ['admin.orders.view'] },
  stores: { name: 'stores', views: ['admin.stores.view'] },
  'store-applications': { name: 'store_applications', views: ['admin.stores.view'] },
  'finance/payouts': { name: 'finance_payouts', views: ['admin.finance.view'] },
  'finance/topups': { name: 'finance_topups', views: ['admin.finance.view'] },
  'finance/balances': { name: 'finance_balances', views: ['admin.finance.view'] },
  'pos/invoices': { name: 'pos_invoices', views: ['admin.subscriptions.manage', 'admin.finance.view'] },
  'pos/devices': { name: 'pos_devices', views: ['admin.devices.view'] },
  'audit-logs': { name: 'audit_logs', views: ['admin.audit.view'] },
}

export interface RecordedExport {
  list: string
  query: Record<string, string>
}

// The `count` months up to 2026-10 (today is 2026-10-28), oldest first
function monthKeys(count: number): string[] {
  return Array.from({ length: count }, (_, index) => {
    const at = new Date(Date.UTC(2026, 9 - (count - 1 - index), 1))
    return `${at.getUTCFullYear()}-${String(at.getUTCMonth() + 1).padStart(2, '0')}`
  })
}

const part = (mrr: string, stores: number) => ({ mrr, stores })
const ZERO = part('0.00', 0)

// Jul..Oct of the backend's test data; every earlier month is empty
const HISTORY: Record<string, { mrr: string; paying: number; trialing: number; active: number; movement: [typeof ZERO, typeof ZERO, typeof ZERO, typeof ZERO]; opening: string; net: string; churn: { start: number; end: number; lost: number; inactive: number; ended: number; rate: string | null; revenue: string | null }; revenue: { orders: number; gmv: string; commission: string; sub: string; missing: number; complete: boolean } }> = {
  '2026-07': { mrr: '772166.67', paying: 3, trialing: 0, active: 3, opening: '0.00', net: '772166.67', movement: [part('772166.67', 3), ZERO, ZERO, ZERO], churn: { start: 3, end: 3, lost: 0, inactive: 0, ended: 0, rate: '0.0', revenue: null }, revenue: { orders: 0, gmv: '0.00', commission: '0.00', sub: '0.00', missing: 31, complete: false } },
  '2026-08': { mrr: '921166.67', paying: 4, trialing: 0, active: 4, opening: '772166.67', net: '149000.00', movement: [part('149000.00', 1), ZERO, ZERO, ZERO], churn: { start: 3, end: 4, lost: 0, inactive: 0, ended: 0, rate: '0.0', revenue: '0.0' }, revenue: { orders: 0, gmv: '0.00', commission: '0.00', sub: '0.00', missing: 31, complete: false } },
  '2026-09': { mrr: '731166.67', paying: 3, trialing: 0, active: 3, opening: '921166.67', net: '-190000.00', movement: [ZERO, part('59000.00', 1), ZERO, part('249000.00', 1)], churn: { start: 4, end: 3, lost: 2, inactive: 1, ended: 1, rate: '50.0', revenue: '27.0' }, revenue: { orders: 2, gmv: '200000.00', commission: '20000.00', sub: '0.00', missing: 0, complete: true } },
  '2026-10': { mrr: '831166.67', paying: 3, trialing: 1, active: 4, opening: '731166.67', net: '100000.00', movement: [ZERO, part('100000.00', 1), ZERO, ZERO], churn: { start: 3, end: 4, lost: 0, inactive: 0, ended: 0, rate: '0.0', revenue: '0.0' }, revenue: { orders: 15, gmv: '1500000.00', commission: '150000.00', sub: '149000.00', missing: 27, complete: false } },
}

export class FakeMetrics {
  /** Every export asked for, with the filters it carried */
  exports: RecordedExport[] = []
  /** Set to answer the next exports with "too many" */
  throttled = false
  /** Set to make an export say it was cut at the cap */
  truncated = false
  /** Set to make /metrics answer 500 */
  broken = false
  /** Set to an empty funnel */
  noApplications = false

  route(req: FakeRequest, path: string, can: (permission: string) => boolean): Reply | null {
    if (req.method !== 'GET') return null

    if (path === '/metrics' || path === '/metrics/funnel') {
      if (!can('admin.finance.view') && !can('admin.stores.view')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
      const months = Number(req.query.months ?? 12)
      if (!Number.isInteger(months) || months < 3 || months > 24) return validation('months', 'Oylar soni 3 dan 24 gacha bo\'lsin.')
      if (path === '/metrics/funnel') return ok(this.funnel(months))
      if (this.broken) return fail(500, 'Server xatosi.')
      return ok(this.overview(months))
    }

    const exported = /^\/exports\/(.+)$/.exec(path)
    if (exported) {
      const list = EXPORTS[exported[1]!]
      if (!list) return null
      if (!can('admin.export') || !list.views.some((view) => can(view))) return fail(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' })
      if (this.throttled) return { status: 429, headers: { 'Retry-After': '30', 'Content-Type': 'application/json' }, body: { success: false, message: 'Too Many Attempts.', data: { reason: 'throttled' } } }
      this.exports.push({ list: list.name, query: { ...req.query } })
      return {
        status: 200,
        // A tiny zip header is enough: the proxy passes the bytes on by their type
        raw: Buffer.from([80, 75, 3, 4, 20, 0]),
        headers: {
          'Content-Type': XLSX,
          'Content-Disposition': `attachment; filename=rizqim-${list.name}-20261028-1200.xlsx`,
          'X-Export-Rows': this.truncated ? '50000' : '2',
          'X-Export-Truncated': this.truncated ? '1' : '0',
        },
      }
    }
    return null
  }

  private overview(months: number) {
    const keys = monthKeys(months)
    const row = (key: string) => HISTORY[key]
    const empty = { mrr: '0.00', paying: 0, trialing: 0, active: 0, opening: '0.00', net: '0.00', movement: [ZERO, ZERO, ZERO, ZERO] as [typeof ZERO, typeof ZERO, typeof ZERO, typeof ZERO], churn: { start: 0, end: 0, lost: 0, inactive: 0, ended: 0, rate: null, revenue: null }, revenue: { orders: 0, gmv: '0.00', commission: '0.00', sub: '0.00', missing: 30, complete: false } }
    const rows = keys.map((key) => ({ key, partial: key === '2026-10', ...(row(key) ?? empty) }))

    return {
      timezone: 'Asia/Tashkent',
      generated_at: '2026-10-28T12:00:00+05:00',
      months,
      cards: {
        mrr: '831166.67',
        mrr_change: { direction: 'up', amount: '100000.00', percent: '13.7' },
        arr: '9974000.04',
        paying_stores: 3,
        trialing_stores: 1,
        arpa: '277055.56',
        active_stores: 4,
        churn_rate: '50.0',
        churn_month: '2026-09',
        gmv_month: '1500000.00',
        commission_month: '150000.00',
        revenue_complete: false,
      },
      series: rows.map((r) => ({ month: r.key, partial: r.partial, mrr: r.mrr, paying_stores: r.paying, trialing_stores: r.trialing, active_stores: r.active })),
      movement: rows.map((r) => ({
        month: r.key,
        partial: r.partial,
        opening: r.opening,
        closing: r.mrr,
        new: r.movement[0],
        expansion: r.movement[1],
        contraction: r.movement[2],
        churned: r.movement[3],
        net_new: r.net,
        paying_stores: r.paying,
        trialing_stores: r.trialing,
      })),
      churn: rows.map((r) => ({ month: r.key, partial: r.partial, active_at_start: r.churn.start, active_at_end: r.churn.end, churned: r.churn.lost, went_inactive: r.churn.inactive, subscription_ended: r.churn.ended, rate: r.churn.rate, revenue_rate: r.churn.revenue })),
      revenue: rows.map((r) => ({ month: r.key, partial: r.partial, orders: r.revenue.orders, gmv: r.revenue.gmv, commission: r.revenue.commission, subscription_revenue: r.revenue.sub, missing_days: r.revenue.missing, complete: r.revenue.complete })),
    }
  }

  private funnel(months: number) {
    if (this.noApplications) {
      return { months, from: '2025-11', applications: 0, active: 0, rejected: 0, open: 0, conversion: null, median_days_to_active: null, stages: [] }
    }
    const stage = (key: string, label: string, reached: number, share: string, median: number | null, samples: number) => ({ stage: key, label, reached, share, median_days: median, samples })

    return {
      months,
      from: '2025-11',
      applications: 4,
      active: 1,
      rejected: 1,
      open: 2,
      conversion: '25.0',
      median_days_to_active: 14,
      stages: [
        stage('application', 'Ariza', 4, '100.0', 2, 3),
        stage('review', 'Tekshiruv', 3, '75.0', 2, 3),
        stage('menu', 'Menyu', 2, '50.0', 1, 1),
        stage('devices', 'Qurilmalar', 1, '25.0', 2, 1),
        stage('training', "O'qitish", 1, '25.0', null, 0),
        stage('trial', 'Sinov', 1, '25.0', 5, 1),
        stage('active', 'Faol', 1, '25.0', null, 0),
      ],
    }
  }
}
