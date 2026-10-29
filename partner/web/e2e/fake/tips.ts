// Tips (choy puli) and service charge rules of the fake backend (docs/API.md P6-22 "Choy puli", "Xizmat haqi
// qoidalari"): branch 1's tip settings, two tips of the day (one personal, one in the pool), the pool split (equal
// between the two waiters) and the service charge rules row. Enough for the panel's flows; the real arithmetic (the
// remainder rule, the work time, the notice text) is the backend's and its tests.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const AZIZ = { id: 31, name: 'Aziz Ofitsiant' }
const BOBUR = { id: 32, name: 'Bobur Ofitsiant' }
const DAY = '2026-11-16'
const money = (n: number) => n.toFixed(2)
const option = (key: string, label: string) => ({ key, label })

interface Tip {
  uuid: string
  method: 'cash' | 'card' | 'qr'
  amount: string
  mode: 'personal' | 'pool'
  staff: { id: number; name: string } | null
  corrections: Array<Record<string, unknown>>
}

export class FakeTips {
  settings = { mode: 'personal', pool_rule: 'equal', pool_period: 'day', roles: ['waiter'], role_weights: { manager: '1.00', cashier: '1.00', waiter: '1.00', cook: '1.00', hostess: '1.00' } as Record<string, string>, updated_at: null as string | null }
  tips: Tip[] = [
    { uuid: 'cccccccc-0000-4000-8000-000000000001', method: 'cash', amount: '20000.00', mode: 'personal', staff: AZIZ, corrections: [] },
    { uuid: 'cccccccc-0000-4000-8000-000000000002', method: 'card', amount: '15001.00', mode: 'pool', staff: null, corrections: [] },
  ]
  split: { uuid: string; status: 'open' | 'closed' } | null = null
  rules = { by_order_type: { hall: '15.00' } as Record<string, string>, large_group_min_guests: null as number | null, large_group_percent: null as string | null, cover_amount: null as string | null, notice: null as string | null }

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private pool(): number {
    return this.tips.filter((tip) => tip.mode === 'pool').reduce((sum, tip) => sum + Number(tip.amount), 0)
  }

  private view(tip: Tip) {
    return {
      ...tip,
      source: 'tender',
      original_amount: tip.corrections.length > 0 ? (tip.corrections[0].was as string) : tip.amount,
      holders: [{ mode: tip.mode, staff: tip.staff, amount: tip.amount }],
      business_date: DAY,
      order_uuid: null,
      bill_uuid: null,
      payment_uuid: null,
      shift_uuid: null,
      recorded_by: { id: 1, name: 'Kassir' },
      closed: this.split?.status === 'closed',
      created_at: `${DAY}T13:00:00+05:00`,
    }
  }

  private distribution() {
    const pool = this.pool()
    const half = Math.floor(pool / 2)
    return {
      uuid: this.split!.uuid,
      branch_id: 1,
      business_date: DAY,
      shift_uuid: null,
      rule: 'equal',
      roles: ['waiter'],
      role_weights: {},
      pool_amount: money(pool),
      tips_count: this.tips.filter((tip) => tip.mode === 'pool').length,
      distributed: money(pool),
      status: this.split!.status,
      computed_at: `${DAY}T23:00:00+05:00`,
      closed_at: this.split!.status === 'closed' ? `${DAY}T23:05:00+05:00` : null,
      closed_by: this.split!.status === 'closed' ? { id: 1, name: 'Ega' } : null,
      manual_participants: false,
      // The so'm left over goes to the larger remainder, the lower id on a tie
      lines: [
        { staff_id: AZIZ.id, name: AZIZ.name, role: 'waiter', basis: '1.00', amount: money(pool - half) },
        { staff_id: BOBUR.id, name: BOBUR.name, role: 'waiter', basis: '1.00', amount: money(half) },
      ],
    }
  }

  private report() {
    const sum = (tips: Tip[]) => money(tips.reduce((total, tip) => total + Number(tip.amount), 0))
    const personal = this.tips.filter((tip) => tip.mode === 'personal')
    const shares = this.split ? this.distribution().lines : []
    const share = (id: number) => Number(shares.find((line) => line.staff_id === id)?.amount ?? 0)
    const own = (id: number) => personal.filter((tip) => tip.staff?.id === id).reduce((total, tip) => total + Number(tip.amount), 0)
    const totals = {
      count: this.tips.length,
      amount: sum(this.tips),
      by_method: (['cash', 'card', 'qr'] as const).map((method) => ({ method, count: this.tips.filter((tip) => tip.method === method).length, amount: sum(this.tips.filter((tip) => tip.method === method)) })),
      personal: sum(personal),
      pool: money(this.pool()),
      unassigned: sum(personal.filter((tip) => tip.staff === null)),
      by_staff: [],
      corrections: { count: 0, amount: '0.00' },
    }
    return {
      branch_id: 1,
      from: DAY,
      to: DAY,
      totals,
      by_day: [
        {
          business_date: DAY,
          amount: totals.amount,
          personal: totals.personal,
          pool: totals.pool,
          cash: totals.by_method[0].amount,
          card: totals.by_method[1].amount,
          qr: totals.by_method[2].amount,
          distributions: this.split ? [{ uuid: this.split.uuid, shift_uuid: null, status: this.split.status, pool_amount: totals.pool, distributed: totals.pool }] : [],
          undistributed: this.split ? '0.00' : totals.pool,
        },
      ],
      by_staff: [AZIZ, BOBUR]
        .map((person) => ({ staff_id: person.id, name: person.name, role: 'waiter', personal: money(own(person.id)), pool_share: money(share(person.id)), pool_share_closed: this.split?.status === 'closed' ? money(share(person.id)) : '0.00', total: money(own(person.id) + share(person.id)) }))
        .filter((row) => row.total !== '0.00'),
      undistributed: this.split ? '0.00' : totals.pool,
      staff: [
        { ...AZIZ, role: 'waiter' },
        { ...BOBUR, role: 'waiter' },
      ],
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path === '/v1/owner/pos/service-charge-rules') {
      if (req.method === 'PUT') {
        this.rules = {
          by_order_type: (body?.by_order_type as Record<string, string>) ?? {},
          large_group_min_guests: (body?.large_group_min_guests as number | null) ?? null,
          large_group_percent: (body?.large_group_percent as string | null) ?? null,
          cover_amount: (body?.cover_amount as string | null) ?? null,
          notice: (body?.notice as string | null) ?? null,
        }
      }
      return ok(this.rulesPage())
    }
    if (!req.path.startsWith('/v1/owner/pos/tips')) return null
    const path = req.path.slice('/v1/owner/pos/tips'.length)

    if (path === '/settings') {
      if (req.method === 'PUT') {
        for (const key of ['mode', 'pool_rule', 'pool_period', 'roles', 'role_weights'] as const) {
          if (body && key in body) (this.settings as Record<string, unknown>)[key] = body[key]
        }
        this.settings.updated_at = `${DAY}T10:00:00+05:00`
      }
      return ok({
        settings: { branch_id: 1, enabled: true, ...this.settings },
        options: {
          modes: [option('personal', 'Shaxsiy (ofitsiantga)'), option('pool', 'Umumiy qozon')],
          pool_rules: [option('equal', 'Teng'), option('hours', 'Ishlagan soatiga qarab'), option('sales', 'Savdosiga qarab'), option('role_weights', 'Rol ulushiga qarab')],
          pool_periods: [option('day', 'Biznes kun'), option('shift', 'Smena')],
          roles: [option('manager', 'Menejer'), option('cashier', 'Kassir'), option('waiter', 'Ofitsiant'), option('cook', 'Oshpaz'), option('hostess', 'Hostes')],
        },
      })
    }
    if (req.method === 'GET' && path === '') return ok(this.report())
    if (req.method === 'GET' && path === '/entries') return ok({ tips: this.tips.map((tip) => this.view(tip)), truncated: false })
    if (req.method === 'GET' && path === '/distributions') return ok({ distributions: this.split ? [this.distribution()] : [] })
    if (req.method === 'POST' && path === '/distributions') {
      if (this.split?.status === 'closed') return fail(409, "Bu choy puli bo'linishi yopilgan: qayta hisoblab bo'lmaydi.", undefined, { reason: 'tips_distribution_closed' })
      this.split ??= { uuid: 'dddddddd-0000-4000-8000-000000000001', status: 'open' }
      return ok(this.distribution())
    }
    const close = path.match(/^\/distributions\/([0-9a-f-]+)\/close$/)
    if (req.method === 'POST' && close && this.split?.uuid === close[1]) {
      this.split.status = 'closed'
      return ok(this.distribution())
    }
    const correct = path.match(/^\/([0-9a-f-]+)\/corrections$/)
    if (req.method === 'POST' && correct) {
      const tip = this.tips.find((candidate) => candidate.uuid === correct[1])
      if (!tip) return fail(404, 'Choy puli topilmadi.')
      const staffId = body?.staff_id as number | null
      tip.corrections.push({ was: tip.amount, reason: body?.reason ?? null })
      tip.mode = body?.mode === 'pool' ? 'pool' : 'personal'
      tip.staff = tip.mode === 'pool' ? null : ([AZIZ, BOBUR].find((person) => person.id === staffId) ?? null)
      if (typeof body?.amount === 'string') tip.amount = money(Number(body.amount))
      return ok(this.view(tip))
    }
    return null
  }

  private rulesPage() {
    const preview = this.rules.notice ?? (this.rules.by_order_type.hall ? `Xizmat haqi ${Number(this.rules.by_order_type.hall)}%.` : null)
    return {
      branch_id: 1,
      enabled: true,
      locked: false,
      order_types: [option('hall', 'Zalda (ofitsiant)'), option('table_service', 'Stolga olib kelish'), option('takeaway', 'Olib ketish / kassa')],
      rows: [
        {
          branch_id: 1,
          hall_id: null,
          hall_name: null,
          service_charge_percent: '10.00',
          service_charge_channels: null,
          place_fee_amount: '0.00',
          place_fee_mode: 'per_person',
          rules: { by_order_type: this.rules.by_order_type, large_group_min_guests: this.rules.large_group_min_guests, large_group_percent: this.rules.large_group_percent, cover_amount: this.rules.cover_amount },
          notice: this.rules.notice,
          notice_preview: preview,
        },
      ],
    }
  }
}
