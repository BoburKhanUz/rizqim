// The plan, the unpaid-invoice notice and the invoices of the fake backend (P4-21; docs/BILLING.md, docs/API.md "Billing v2"):
// GET /owner/billing/entitlements, GET /owner/subscription, GET /owner/invoices, and the 403 plan_feature_missing a gated write
// answers when the plan does not have the feature. A new store starts on the Asosiy plan with everything paid; a test switches to
// Start, to an overdue invoice, to the lock, and pays.
import type { FakeBackend } from './backend'
import { ok, type FakeRequest, type Reply } from './types'

const DAY_MS = 86_400_000

const GATED = ['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge', 'multi_branch_reports', 'aggregator_orders', 'banquet'] as const
type Gated = (typeof GATED)[number]

const REQUIRED: Record<Gated, string> = { bookings: 'asosiy', deposits: 'asosiy', customer_accounts: 'asosiy', kds: 'asosiy', service_charge: 'asosiy', multi_branch_reports: 'pro', aggregator_orders: 'pro', banquet: 'pro' }
const PLAN_NAMES: Record<string, string> = { start: 'Start', asosiy: 'Asosiy', pro: 'Pro' }
const FEATURE_LABELS: Record<Gated, string> = {
  bookings: 'Bron',
  deposits: "Bo'nak (avans)",
  customer_accounts: 'Nasiya',
  kds: 'Oshxona ekrani (KDS)',
  service_charge: 'Xizmat haqi',
  multi_branch_reports: 'Filiallar hisoboti',
  aggregator_orders: 'Yandex va Uzum buyurtmalari',
  banquet: 'Banket',
}
const MONTHLY: Record<string, number> = { start: 149_000, asosiy: 249_000, pro: 449_000 }
const INCLUDED: Record<string, { pos: number; kds: number }> = { start: { pos: 1, kds: 0 }, asosiy: { pos: 1, kds: 1 }, pro: { pos: 2, kds: 1 } }
const PLAN_FEATURES: Record<string, Gated[]> = {
  start: [],
  asosiy: ['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge'],
  pro: [...GATED],
}

export interface FakeInvoice {
  uuid: string
  number: string
  period: string
  period_start: string
  period_end: string
  plan: { code: string; name: string } | null
  terminals_count: number
  included_terminals: number
  base_fee: number
  fee_per_terminal: number
  lines: unknown[]
  amount: number
  kind: string
  paid_amount: string
  status: 'issued' | 'paid' | 'void'
  due_date: string
  issued_at: string
  paid_at: string | null
  voided_at: string | null
}

export class FakeBilling {
  plan = 'asosiy'
  period: 'monthly' | 'yearly' = 'monthly'
  status: 'trialing' | 'active' | 'past_due' | 'locked' = 'active'
  trialEndsAt: string | null = null
  activeDevices = { pos: 1, kds: 1 }
  /** Days past the due date of the unpaid invoice (null: all paid). 7 days or more is locked (the server works it out live). */
  overdueDays: number | null = null
  invoices: FakeInvoice[] = []
  /** Makes a gated write answer 403 whatever the entitlements say (a screen that has not heard of a plan change yet). */
  refuseBookings = false

  constructor(private readonly backend: FakeBackend) {
    void this.backend
    this.invoices = [this.invoice('OB-202609-000041', '2026-09', 'paid', 249_000, '2026-10-10')]
  }

  private invoice(number: string, period: string, status: FakeInvoice['status'], amount: number, due: string, paid = status === 'paid' ? amount : 0): FakeInvoice {
    return {
      uuid: `inv-${number}`,
      number,
      period,
      period_start: `${period}-01T00:00:00+05:00`,
      period_end: `${period}-28T00:00:00+05:00`,
      plan: { code: this.plan, name: PLAN_NAMES[this.plan] ?? this.plan },
      terminals_count: 1,
      included_terminals: 1,
      base_fee: amount,
      fee_per_terminal: 0,
      lines: [],
      amount,
      kind: 'monthly',
      paid_amount: paid.toFixed(2),
      status,
      due_date: due,
      issued_at: `${period}-01T00:15:00+05:00`,
      paid_at: status === 'paid' ? `${due}T09:00:00+05:00` : null,
      voided_at: null,
    }
  }

  // --- What a test sets up ---------------------------------------------------------------------------------------------

  /** Moves the store to a plan (the entitlements follow at once). */
  assignPlan(code: 'start' | 'asosiy' | 'pro'): void {
    this.plan = code
    this.activeDevices = { pos: Math.min(this.activeDevices.pos, INCLUDED[code].pos), kds: Math.min(this.activeDevices.kds, INCLUDED[code].kds) }
  }

  startTrial(daysLeft: number): void {
    this.status = 'trialing'
    this.trialEndsAt = new Date(Date.now() + daysLeft * DAY_MS).toISOString()
  }

  /** An invoice of 249 000 that is `days` days past due. */
  makeOverdue(days: number, amount = 249_000): void {
    this.overdueDays = days
    const due = new Date(Date.now() - days * DAY_MS).toISOString().slice(0, 10)
    this.invoices = [this.invoice('OB-202610-000042', '2026-10', 'issued', amount, due), ...this.invoices.filter((invoice) => invoice.number !== 'OB-202610-000042')]
    this.status = days >= 7 ? 'locked' : 'past_due'
  }

  /** Pays every unpaid invoice: the lock and the notice go at once. */
  pay(): void {
    this.invoices = this.invoices.map((invoice) => (invoice.status === 'issued' ? { ...invoice, status: 'paid', paid_amount: invoice.amount.toFixed(2), paid_at: new Date().toISOString() } : invoice))
    this.overdueDays = null
    this.status = 'active'
  }

  has(feature: Gated): boolean {
    return PLAN_FEATURES[this.plan].includes(feature)
  }

  // --- The answers -------------------------------------------------------------------------------------------------------

  entitlements() {
    const included = INCLUDED[this.plan]
    const locked = this.status === 'locked'
    const device = (type: 'pos' | 'kds') => {
      const active = this.activeDevices[type]
      const feature = type === 'kds' ? this.has('kds') : true
      return { included: included[type], active, extra: Math.max(0, active - included[type]), extra_price: type === 'pos' ? '59000.00' : '29000.00', can_add: feature && !(locked && active >= included[type]) }
    }
    const lockAt = this.overdueDays === null ? null : new Date(Date.now() + (7 - this.overdueDays) * DAY_MS).toISOString()
    const unpaid = this.invoices.find((invoice) => invoice.status === 'issued')
    return {
      plan: { code: this.plan, name: PLAN_NAMES[this.plan], tier: this.plan, version: 2 },
      legacy: false,
      period: this.period,
      status: this.status,
      trial_ends_at: this.trialEndsAt,
      current_period: { start: '2026-10-01T00:00:00+05:00', end: '2026-11-01T00:00:00+05:00' },
      term_ends_at: null,
      locked_since: locked ? lockAt : null,
      lock_at: lockAt,
      settings_locked: locked,
      features: { hall: true, room_fee: true, guest_items: true, shifts: true, rizqim_orders: true, ...Object.fromEntries(GATED.map((feature) => [feature, this.has(feature)])) },
      required_plans: Object.fromEntries(GATED.map((feature) => [feature, REQUIRED[feature]])),
      devices: { pos: device('pos'), kds: device('kds'), waiter: { included: null, unlimited: true, extra_price: '0.00', can_add: true } },
      notice:
        unpaid && this.overdueDays !== null && this.overdueDays >= 1
          ? {
              level: locked ? 'locked' : 'reminder',
              message: `${unpaid.number} hisob-faktura to'lanmagan.`,
              days_overdue: this.overdueDays,
              lock_at: lockAt,
              invoice: { uuid: unpaid.uuid, number: unpaid.number, amount: unpaid.amount.toFixed(2), outstanding: (unpaid.amount - Number(unpaid.paid_amount)).toFixed(2), due_date: unpaid.due_date },
            }
          : null,
    }
  }

  private subscription() {
    return {
      subscription: {
        status: this.status === 'locked' ? 'past_due' : this.status,
        status_label: 'Faol',
        plan: {
          code: this.plan,
          name: PLAN_NAMES[this.plan],
          description: null,
          summary: '',
          commission_percent: null,
          monthly_base_fee: MONTHLY[this.plan],
          monthly_fee_per_terminal: 0,
          included_terminals: INCLUDED[this.plan].pos + INCLUDED[this.plan].kds,
          trial_days: 14,
        },
        started_at: '2026-09-01T00:00:00+05:00',
        trial_ends_at: this.trialEndsAt,
        current_period: { period: '2026-10', start: '2026-10-01T00:00:00+05:00', end: '2026-11-01T00:00:00+05:00' },
        next_plan: null,
        cancel_at_period_end: false,
        billing_period: this.period,
        next_billing_period: null,
        term_ends_at: null,
      },
      commission_rate: 10,
      terminals: { active_now: this.activeDevices.pos + this.activeDevices.kds, counted: this.activeDevices.pos + this.activeDevices.kds },
      next_invoice: { period: '2026-10', issued_on: '2026-11-01', trial: this.status === 'trialing', amount: this.status === 'trialing' ? 0 : MONTHLY[this.plan], lines: [] },
    }
  }

  handle(req: FakeRequest): Reply | null {
    if (req.method === 'GET' && req.path === '/v1/owner/billing/entitlements') return ok(this.entitlements())
    if (req.method === 'GET' && req.path === '/v1/owner/subscription') return ok(this.subscription())
    if (req.method === 'GET' && req.path === '/v1/owner/invoices') {
      const wanted = req.query.status
      const rows = this.invoices.filter((invoice) => !wanted || invoice.status === wanted)
      return ok({ current_page: 1, data: rows, per_page: 15, total: rows.length, last_page: 1 })
    }
    // A new booking is the plan's `bookings` (the middleware `plan.feature:bookings`)
    if (req.method === 'POST' && req.path === '/v1/pos/bookings' && (!this.has('bookings') || this.refuseBookings)) {
      const feature = 'bookings'
      return {
        status: 403,
        body: {
          success: false,
          message: `Bu imkoniyat (${FEATURE_LABELS[feature]}) Asosiy tarifida bor.`,
          code: 'plan_feature_missing',
          feature,
          required_plan: REQUIRED[feature],
          data: { reason: 'plan_feature_missing', feature, feature_label: FEATURE_LABELS[feature], required_plan: REQUIRED[feature], required_plan_name: PLAN_NAMES[REQUIRED[feature]] },
        },
      }
    }
    return null
  }
}
