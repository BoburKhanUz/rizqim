// The fake of POS va obunalar (P4-24): /billing/* (P4-21: subscriptions, tariff catalog, a store's subscription and commission)
// and /pos/* (overview, invoices, devices, shifts, fiscal receipts, offline sync errors), shaped like AdminBillingPlanController,
// AdminStoreBillingController and AdminPos*Controller answer. The rules that matter to the screens are here: invoices and the
// catalog are read with subscriptions.manage or finance.view, the till side (devices, shifts, fiscal, sync) with devices.view;
// changing a plan needs system.manage, a store's subscription subscriptions.manage, an invoice finance.manage, a device /
// receipt / sync error stores.manage; every change needs the step-up and a reason (3+ characters).
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const iso = (minutesAgo = 0) => new Date(Date.now() - minutesAgo * 60_000).toISOString()
const tashkentDay = (days = 0) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tashkent' }).format(new Date(Date.now() + days * 86_400_000))
const money = (value: number) => value.toFixed(2)

const STATUS_LABEL: Record<string, string> = { trialing: 'Sinov davri', active: 'Faol', past_due: "To'lov muddati o'tgan", locked: 'Cheklangan (qarz)', cancelled: 'Tugagan' }
const INVOICE_STATUS_LABEL: Record<string, string> = { issued: "To'lanmagan", paid: "To'langan", void: 'Bekor qilingan' }
const DUNNING_LABEL: Record<string, string> = { reminder_1: '1-kun eslatmasi', reminder_3: '3-kun eslatmasi', reminder_7: '7-kun eslatmasi', locked: 'Cheklandi', unlocked: 'Cheklov olindi' }
const FEATURES = [
  { key: 'hall', label: 'Zal va stollar', base: true },
  { key: 'room_fee', label: 'Xona haqi', base: true },
  { key: 'guest_items', label: 'Mehmon buyumlari', base: true },
  { key: 'shifts', label: 'Smenalar', base: true },
  { key: 'rizqim_orders', label: 'RIZQIM buyurtmalari', base: true },
  { key: 'bookings', label: 'Bronlar', base: false },
  { key: 'deposits', label: 'Bo‘nak', base: false },
  { key: 'customer_accounts', label: 'Nasiya', base: false },
  { key: 'kds', label: 'Oshxona ekrani', base: false },
  { key: 'service_charge', label: 'Xizmat haqi', base: false },
  { key: 'multi_branch_reports', label: 'Filiallar hisobotlari', base: false },
  { key: 'aggregator_orders', label: 'Agregator buyurtmalari', base: false },
  { key: 'banquet', label: 'Banket', base: false },
]
const BASE = FEATURES.filter((feature) => feature.base).map((feature) => feature.key)

export interface FakePlan {
  id: number
  code: string
  name: string
  version: number
  tier: string | null
  is_active: boolean
  sort_order: number
  description: string | null
  monthly_fee: string
  yearly_fee: string
  included_pos: number
  included_kds: number
  extra_pos_fee: string
  extra_kds_fee: string
  trial_days: number
  features: string[]
}

interface FakeSubscription {
  id: number
  store_id: number
  status: 'trialing' | 'active' | 'past_due' | 'locked' | 'cancelled'
  plan: string
  billing_period: 'monthly' | 'yearly'
  next_plan: string | null
  next_billing_period: 'monthly' | 'yearly' | null
  cancel_at_period_end: boolean
  started_at: string
  trial_ends_at: string | null
  locked_at: string | null
  cancelled_at: string | null
}

export interface FakeInvoice {
  uuid: string
  number: string
  store_id: number
  plan: string
  period: string
  amount: string
  paid_amount: string
  status: 'issued' | 'paid' | 'void'
  due_date: string
  issued_at: string
  paid_at: string | null
  voided_at: string | null
  void_reason: string | null
  lines: Array<{ type: string; label: string; quantity: number; unit_price: string; amount: string }>
  dunning: Array<{ stage: string; notified: boolean; recipients: number; occurred_at: string }>
}

interface FakeStoreBilling {
  id: number
  name: string
  status: string
  debt_balance: number
  subscription_debt: number
  credit_limit: number
  overrides: Record<string, string>
  delivery_courier: 'own' | 'fleet'
}

export interface FakeDevice {
  uuid: string
  name: string
  type: string
  store_id: number
  branch: string | null
  platform: string
  app_version: string | null
  last_seen_minutes_ago: number | null
  revoked: boolean
  pending_ops: number
}

export interface FakeShift {
  uuid: string
  store_id: number
  branch: string
  device: string
  business_date: string
  status: 'open' | 'closed'
  opened_by: string
  opening_cash: string
  expected_cash: string | null
  counted_cash: string | null
  difference: string | null
  z_number: number | null
  force_closed: boolean
  close_note: string | null
  forced_by: string | null
}

export interface FakeFiscal {
  uuid: string
  store_id: number
  branch: string | null
  status: 'pending' | 'issued' | 'failed'
  driver: string
  provider: string | null
  total: string
  error: string | null
  attempts: number
  created_minutes_ago: number
}

export interface FakeSync {
  op_id: string
  store_id: number
  device: string
  staff: string
  type: string
  type_label: string
  status: 'rejected' | 'conflict'
  reason: string
  message: string
  received_minutes_ago: number
  resolution: 'resolved' | 'dismissed' | null
  resolution_note: string | null
  resolved_by: string | null
  payload: Record<string, unknown>
}

const REGIONS = new Map<number, string>([[12, 'Lazzat Oshxona'], [13, 'Lazzat Express'], [14, 'Shirin Qandolat'], [15, 'Qarzdor kafe']])
const CHANNELS = ['rizqim_courier', 'own_delivery', 'pickup', 'pos', 'aggregator'] as const
const DEFAULT_RATE: Record<string, string> = { rizqim_courier: '15.00', own_delivery: '10.00', pickup: '5.00', pos: '0.00', aggregator: '0.00' }

export function defaultPlans(): FakePlan[] {
  return [
    { id: 1, code: 'start', name: 'Start', version: 2, tier: 'start', is_active: true, sort_order: 10, description: null, monthly_fee: '149000.00', yearly_fee: '1490000.00', included_pos: 1, included_kds: 0, extra_pos_fee: '59000.00', extra_kds_fee: '29000.00', trial_days: 14, features: [...BASE] },
    { id: 2, code: 'asosiy', name: 'Asosiy', version: 2, tier: 'asosiy', is_active: true, sort_order: 20, description: null, monthly_fee: '249000.00', yearly_fee: '2490000.00', included_pos: 2, included_kds: 1, extra_pos_fee: '59000.00', extra_kds_fee: '29000.00', trial_days: 14, features: [...BASE, 'bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge'] },
    { id: 3, code: 'pro', name: 'Pro', version: 2, tier: 'pro', is_active: true, sort_order: 30, description: null, monthly_fee: '449000.00', yearly_fee: '4490000.00', included_pos: 4, included_kds: 3, extra_pos_fee: '59000.00', extra_kds_fee: '29000.00', trial_days: 14, features: FEATURES.map((feature) => feature.key) },
    { id: 4, code: 'pos', name: 'POS (eski)', version: 1, tier: null, is_active: false, sort_order: 0, description: null, monthly_fee: '0.00', yearly_fee: '0.00', included_pos: 0, included_kds: 0, extra_pos_fee: '0.00', extra_kds_fee: '0.00', trial_days: 0, features: [] },
  ]
}

export class FakePos {
  plans: FakePlan[] = defaultPlans()
  stores: FakeStoreBilling[] = [
    { id: 12, name: 'Lazzat Oshxona', status: 'active', debt_balance: 0, subscription_debt: 0, credit_limit: 100000, overrides: {}, delivery_courier: 'own' },
    { id: 13, name: 'Lazzat Express', status: 'active', debt_balance: 0, subscription_debt: 0, credit_limit: 100000, overrides: { pickup: '3.00' }, delivery_courier: 'fleet' },
    { id: 14, name: 'Shirin Qandolat', status: 'active', debt_balance: 0, subscription_debt: 0, credit_limit: 100000, overrides: {}, delivery_courier: 'own' },
    { id: 15, name: 'Qarzdor kafe', status: 'active', debt_balance: 520000, subscription_debt: 249000, credit_limit: 100000, overrides: {}, delivery_courier: 'own' },
  ]
  subscriptions: FakeSubscription[] = [
    { id: 1, store_id: 12, status: 'active', plan: 'asosiy', billing_period: 'monthly', next_plan: null, next_billing_period: null, cancel_at_period_end: false, started_at: iso(60 * 24 * 60), trial_ends_at: null, locked_at: null, cancelled_at: null },
    { id: 2, store_id: 13, status: 'trialing', plan: 'start', billing_period: 'monthly', next_plan: null, next_billing_period: null, cancel_at_period_end: false, started_at: iso(60 * 24 * 12), trial_ends_at: new Date(Date.now() + 2 * 86_400_000).toISOString(), locked_at: null, cancelled_at: null },
    { id: 3, store_id: 15, status: 'past_due', plan: 'asosiy', billing_period: 'monthly', next_plan: null, next_billing_period: null, cancel_at_period_end: false, started_at: iso(60 * 24 * 90), trial_ends_at: null, locked_at: null, cancelled_at: null },
  ]
  invoices: FakeInvoice[] = [
    {
      uuid: '1a0c0000-0000-4000-8000-000000000001', number: 'OB-202610-000015', store_id: 15, plan: 'asosiy', period: '2026-10', amount: '249000.00', paid_amount: '0.00', status: 'issued', due_date: tashkentDay(-4), issued_at: iso(60 * 24 * 8),
      paid_at: null, voided_at: null, void_reason: null, lines: [{ type: 'base', label: 'Asosiy tarif, oktyabr', quantity: 1, unit_price: '249000.00', amount: '249000.00' }],
      dunning: [{ stage: 'reminder_1', notified: true, recipients: 1, occurred_at: iso(60 * 24 * 3) }, { stage: 'reminder_3', notified: true, recipients: 1, occurred_at: iso(60 * 24) }],
    },
    {
      uuid: '1a0c0000-0000-4000-8000-000000000002', number: 'OB-202610-000012', store_id: 12, plan: 'asosiy', period: '2026-10', amount: '249000.00', paid_amount: '249000.00', status: 'paid', due_date: tashkentDay(-20), issued_at: iso(60 * 24 * 25),
      paid_at: iso(60 * 24 * 22), voided_at: null, void_reason: null, lines: [{ type: 'base', label: 'Asosiy tarif, oktyabr', quantity: 1, unit_price: '249000.00', amount: '249000.00' }], dunning: [],
    },
  ]
  devices: FakeDevice[] = [
    { uuid: '7a1b0000-0000-4000-8000-000000000001', name: 'Kassa 1', type: 'pos', store_id: 13, branch: 'Yunusobod', platform: 'android', app_version: '1.4.0', last_seen_minutes_ago: 25, revoked: false, pending_ops: 3 },
    { uuid: '7a1b0000-0000-4000-8000-000000000002', name: 'Kassa 1', type: 'pos', store_id: 12, branch: 'Chilonzor', platform: 'android', app_version: '1.6.2', last_seen_minutes_ago: 1, revoked: false, pending_ops: 0 },
    { uuid: '7a1b0000-0000-4000-8000-000000000003', name: 'Oshxona ekrani', type: 'kds', store_id: 12, branch: 'Chilonzor', platform: 'android', app_version: '1.6.2', last_seen_minutes_ago: 2, revoked: false, pending_ops: 0 },
    { uuid: '7a1b0000-0000-4000-8000-000000000004', name: 'Kassa 2', type: 'pos', store_id: 15, branch: null, platform: 'android', app_version: null, last_seen_minutes_ago: null, revoked: false, pending_ops: 0 },
  ]
  shifts: FakeShift[] = [
    { uuid: '5b1f0000-0000-4000-8000-000000000001', store_id: 12, branch: 'Chilonzor', device: 'Kassa 1', business_date: tashkentDay(-1), status: 'closed', opened_by: 'Dilnoza', opening_cash: '100000.00', expected_cash: '1250000.00', counted_cash: '1220000.00', difference: '-30000.00', z_number: 41, force_closed: false, close_note: null, forced_by: null },
    { uuid: '5b1f0000-0000-4000-8000-000000000002', store_id: 13, branch: 'Yunusobod', device: 'Kassa 1', business_date: tashkentDay(-2), status: 'closed', opened_by: 'Rustam', opening_cash: '50000.00', expected_cash: '800000.00', counted_cash: '800000.00', difference: '0.00', z_number: 17, force_closed: true, close_note: 'Kassir ketib qoldi', forced_by: 'Menejer Olim' },
    { uuid: '5b1f0000-0000-4000-8000-000000000003', store_id: 12, branch: 'Chilonzor', device: 'Kassa 1', business_date: tashkentDay(0), status: 'open', opened_by: 'Dilnoza', opening_cash: '100000.00', expected_cash: null, counted_cash: null, difference: null, z_number: null, force_closed: false, close_note: null, forced_by: null },
  ]
  fiscal: FakeFiscal[] = [
    { uuid: '5f0c2a10-0000-4000-8000-000000000001', store_id: 12, branch: 'Chilonzor', status: 'failed', driver: 'sandbox', provider: null, total: '86000.00', error: 'Soliq serveri javob bermadi', attempts: 5, created_minutes_ago: 40 },
    { uuid: '5f0c2a10-0000-4000-8000-000000000002', store_id: 14, branch: 'Markaz', status: 'failed', driver: 'didox', provider: null, total: '120000.00', error: "MXIK kodi noto'g'ri", attempts: 5, created_minutes_ago: 95 },
    { uuid: '5f0c2a10-0000-4000-8000-000000000003', store_id: 12, branch: 'Chilonzor', status: 'pending', driver: 'sandbox', provider: null, total: '45000.00', error: null, attempts: 1, created_minutes_ago: 3 },
    { uuid: '5f0c2a10-0000-4000-8000-000000000004', store_id: 13, branch: 'Yunusobod', status: 'pending', driver: 'external', provider: 'payme', total: '70000.00', error: null, attempts: 0, created_minutes_ago: 10 },
  ]
  sync: FakeSync[] = [
    {
      op_id: '0b5f0000-0000-4000-8000-000000000001', store_id: 13, device: 'Kassa 1', staff: 'Rustam', type: 'bill.pay', type_label: "To'lov olindi", status: 'rejected', reason: 'order_cancelled',
      message: 'Buyurtma bekor qilingan', received_minutes_ago: 50, resolution: null, resolution_note: null, resolved_by: null, payload: { order: 'o1', amount: '50000', customer_phone: '***' },
    },
    {
      op_id: '0b5f0000-0000-4000-8000-000000000002', store_id: 12, device: 'Kassa 1', staff: 'Dilnoza', type: 'order.open', type_label: 'Buyurtma ochildi', status: 'conflict', reason: 'oversold',
      message: 'Osh tugagan edi', received_minutes_ago: 120, resolution: null, resolution_note: null, resolved_by: null, payload: { items: [{ name: 'Osh', quantity: 3 }] },
    },
  ]

  private nextId = 100

  // --- Shapes ------------------------------------------------------------------------------------------------------------

  private plan = (code: string) => this.plans.find((plan) => plan.code === code) ?? this.plans[0]!
  private storeName = (id: number) => REGIONS.get(id) ?? `Ovqatxona ${id}`
  private named = (id: number) => ({ id, name: this.storeName(id) })
  private currentOf = (storeId: number) => this.subscriptions.find((row) => row.store_id === storeId && row.status !== 'cancelled') ?? null

  private subscriptionJson(row: FakeSubscription) {
    const plan = this.plan(row.plan)
    const next = row.next_plan ? this.plan(row.next_plan) : null
    return {
      id: row.id, store_id: row.store_id, status: row.status, status_label: STATUS_LABEL[row.status], plan: { code: plan.code, name: plan.name, version: plan.version },
      billing_period: row.billing_period, next_billing_period: row.next_billing_period, next_plan: next ? { code: next.code, name: next.name } : null, cancel_at_period_end: row.cancel_at_period_end,
      started_at: row.started_at, trial_ends_at: row.trial_ends_at, current_period: { start: iso(60 * 24 * 10), end: new Date(Date.now() + 20 * 86_400_000).toISOString() },
      term_ends_at: null, changes_at: new Date(Date.now() + 20 * 86_400_000).toISOString(), price_locked_until: null, locked_at: row.locked_at, cancelled_at: row.cancelled_at,
    }
  }

  private planJson(plan: FakePlan) {
    return {
      ...plan, commission_percent: null, monthly_fee_per_terminal: null, included_terminals: 0,
      subscriptions: this.subscriptions.filter((row) => row.plan === plan.code && row.status !== 'cancelled').length,
    }
  }

  private invoiceBilling(invoice: FakeInvoice) {
    const outstanding = invoice.status === 'void' ? 0 : Math.max(0, Number(invoice.amount) - Number(invoice.paid_amount))
    return {
      uuid: invoice.uuid, number: invoice.number, kind: 'monthly', period: invoice.period, period_start: iso(60 * 24 * 40), period_end: iso(60 * 24 * 10),
      plan: { code: invoice.plan, name: this.plan(invoice.plan).name }, lines: invoice.lines, amount: invoice.amount, paid_amount: invoice.paid_amount, outstanding: money(outstanding),
      status: invoice.status, due_date: invoice.due_date, issued_at: invoice.issued_at, paid_at: invoice.paid_at, voided_at: invoice.voided_at,
      dunning: invoice.dunning.map((step) => ({ ...step, label: DUNNING_LABEL[step.stage], invoice: invoice.number })),
    }
  }

  private overdueDays(invoice: FakeInvoice): number {
    if (invoice.status !== 'issued') return 0
    const today = tashkentDay(0)
    return invoice.due_date < today ? Math.round((Date.parse(today) - Date.parse(invoice.due_date)) / 86_400_000) : 0
  }

  private invoiceRow(invoice: FakeInvoice) {
    const outstanding = invoice.status === 'void' ? 0 : Math.max(0, Number(invoice.amount) - Number(invoice.paid_amount))
    const last = invoice.dunning.at(-1) ?? null
    const days = this.overdueDays(invoice)
    return {
      uuid: invoice.uuid, number: invoice.number, kind: 'monthly', period: invoice.period, store: this.named(invoice.store_id), plan: { code: invoice.plan, name: this.plan(invoice.plan).name },
      amount: invoice.amount, paid_amount: invoice.paid_amount, outstanding: money(outstanding), status: invoice.status, status_label: INVOICE_STATUS_LABEL[invoice.status],
      due_date: invoice.due_date, issued_at: invoice.issued_at, paid_at: invoice.paid_at, voided_at: invoice.voided_at, overdue: days > 0, overdue_days: days,
      dunning_stage: last?.stage ?? null, dunning_label: last ? DUNNING_LABEL[last.stage] : null, dunning_at: last?.occurred_at ?? null,
    }
  }

  private invoiceCard(invoice: FakeInvoice) {
    const store = this.stores.find((row) => row.id === invoice.store_id)
    return {
      invoice: { ...this.invoiceRow(invoice), ...this.invoiceBilling(invoice), void_reason: invoice.void_reason },
      store: store ? { id: store.id, name: store.name, status: store.status, debt_balance: money(store.debt_balance), subscription_debt: money(store.subscription_debt), commission_debt: money(store.debt_balance - store.subscription_debt) } : null,
    }
  }

  private commissionJson(store: FakeStoreBilling) {
    return {
      rates: Object.fromEntries(CHANNELS.map((channel) => [channel, { rate: store.overrides[channel] ?? DEFAULT_RATE[channel]!, source: store.overrides[channel] ? 'override' : 'default', default: DEFAULT_RATE[channel]! }])),
      overrides: store.overrides, flat_rate: null, delivery_courier: store.delivery_courier,
    }
  }

  private entitlements(store: FakeStoreBilling) {
    const subscription = this.currentOf(store.id)
    const plan = subscription ? this.plan(subscription.plan) : null
    const features = Object.fromEntries(FEATURES.map((feature) => [feature.key, plan ? plan.features.includes(feature.key) : false]))
    const required = Object.fromEntries(FEATURES.filter((feature) => !feature.base).map((feature) => [feature.key, this.plans.find((candidate) => candidate.version >= 2 && candidate.features.includes(feature.key))?.code ?? null]))
    return {
      plan: plan ? { code: plan.code, name: plan.name, tier: plan.tier, version: plan.version } : null, legacy: false, period: subscription?.billing_period ?? null,
      status: subscription?.status ?? 'none', trial_ends_at: subscription?.trial_ends_at ?? null, current_period: null, term_ends_at: null, locked_since: null, lock_at: null,
      settings_locked: subscription?.status === 'locked', features, required_plans: required,
      devices: {
        pos: { included: plan?.included_pos ?? null, active: this.devices.filter((device) => device.store_id === store.id && device.type === 'pos' && !device.revoked).length, extra: 0, extra_price: plan?.extra_pos_fee ?? null, can_add: true },
        kds: { included: plan?.included_kds ?? null, active: this.devices.filter((device) => device.store_id === store.id && device.type === 'kds' && !device.revoked).length, extra: 0, extra_price: plan?.extra_kds_fee ?? null, can_add: true },
        waiter: { included: null, unlimited: true, extra_price: '0.00', can_add: true },
      },
      notice: subscription?.status === 'past_due' ? { level: 'reminder', message: "To'lov muddati o'tdi — 3 kundan keyin sozlamalar yopiladi", days_overdue: 4, lock_at: null } : null,
    }
  }

  private billingCard(store: FakeStoreBilling) {
    const current = this.currentOf(store.id)
    return {
      store: { id: store.id, name: store.name, status: store.status },
      subscription: current ? this.subscriptionJson(current) : null,
      entitlements: this.entitlements(store),
      debt: {
        debt_balance: money(store.debt_balance), subscription_debt: money(store.subscription_debt), commission_debt: money(store.debt_balance - store.subscription_debt),
        credit_limit: money(store.credit_limit), over_credit_limit: store.debt_balance - store.subscription_debt > store.credit_limit,
      },
      commission: this.commissionJson(store),
      history: this.subscriptions.filter((row) => row.store_id === store.id).sort((a, b) => b.id - a.id).map((row) => this.subscriptionJson(row)),
      invoices: this.invoices.filter((invoice) => invoice.store_id === store.id).map((invoice) => this.invoiceBilling(invoice)),
    }
  }

  private deviceJson(device: FakeDevice) {
    const online = device.last_seen_minutes_ago !== null && device.last_seen_minutes_ago <= 10
    const newest = this.devices.filter((row) => row.type === device.type && !row.revoked && row.app_version).map((row) => row.app_version!).sort((a, b) => b.localeCompare(a, undefined, { numeric: true }))[0] ?? null
    return {
      uuid: device.uuid, name: device.name, type: device.type, type_label: device.type === 'pos' ? 'Kassa (POS)' : 'Oshxona ekrani (KDS)', terminal: true, store: this.named(device.store_id),
      branch: device.branch ? { id: 1, name: device.branch } : null, platform: device.platform, app_version: device.app_version, latest_version: newest,
      outdated: Boolean(device.app_version && newest && device.app_version.localeCompare(newest, undefined, { numeric: true }) < 0),
      state: device.revoked ? 'revoked' : device.last_seen_minutes_ago === null ? 'never' : online ? 'online' : 'offline',
      last_seen_at: device.last_seen_minutes_ago === null ? null : iso(device.last_seen_minutes_ago), paired_at: iso(60 * 24 * 30), revoked_at: device.revoked ? iso(1) : null,
      sync: { pending_ops: device.pending_ops, oldest_op_at: device.pending_ops ? iso(90) : null, reported_at: device.pending_ops ? iso(20) : null, clock_skew_seconds: device.pending_ops ? 900 : null, clock_warning: device.pending_ops > 0 },
    }
  }

  private shiftRow(shift: FakeShift) {
    const has = shift.difference !== null && Number(shift.difference) !== 0
    return {
      uuid: shift.uuid, status: shift.status, store: this.named(shift.store_id), branch: { id: 1, name: shift.branch }, device: { uuid: 'd', name: shift.device }, business_date: shift.business_date,
      opened_at: iso(60 * 10), opened_by: { id: 1, name: shift.opened_by }, opening_cash: shift.opening_cash, closed_at: shift.status === 'closed' ? iso(60) : null,
      closed_by: shift.status === 'closed' ? { id: 2, name: shift.opened_by } : null, expected_cash: shift.expected_cash, counted_cash: shift.counted_cash, difference: shift.difference, has_difference: has,
      z_number: shift.z_number, force_closed: shift.force_closed, close_note: shift.close_note,
    }
  }

  private fiscalRow(receipt: FakeFiscal) {
    return {
      uuid: receipt.uuid, type: 'sale', status: receipt.status, driver: receipt.driver, provider: receipt.provider, non_fiscal: false, total: receipt.total, error: receipt.error,
      attempts: receipt.attempts, next_attempt_at: receipt.status === 'pending' ? iso(-5) : null, created_at: iso(receipt.created_minutes_ago), issued_at: receipt.status === 'issued' ? iso(1) : null,
      store: this.named(receipt.store_id), branch: receipt.branch ? { id: 1, name: receipt.branch } : null, retryable: receipt.status !== 'issued' && receipt.driver !== 'external', subject_type: 'order', subject_id: 1,
    }
  }

  private syncRow(op: FakeSync) {
    return {
      op_id: op.op_id, seq: 1, type: op.type, type_label: op.type_label, status: op.status, reason: op.reason, message: op.message, store: this.named(op.store_id),
      device: { uuid: 'd', name: op.device }, staff: { id: 1, name: op.staff }, order: { uuid: 'o', local_number: null, short_code: 'AB12CD', table_ref: null, status: 'new' },
      occurred_at: iso(op.received_minutes_ago + 5), received_at: iso(op.received_minutes_ago), clock_skew_seconds: 12,
      resolved_at: op.resolution ? iso(1) : null, resolved_by: op.resolved_by ? { id: 1, name: op.resolved_by } : null, resolution: op.resolution, resolution_note: op.resolution_note,
    }
  }

  // --- Lists ---------------------------------------------------------------------------------------------------------------

  private page<T>(items: T[], query: Record<string, string>) {
    const perPage = Math.max(1, Math.min(100, Number(query.per_page) || 30))
    const current = Math.max(1, Number(query.page) || 1)
    return { data: items.slice((current - 1) * perPage, current * perPage), current_page: current, last_page: Math.max(1, Math.ceil(items.length / perPage)), per_page: perPage, total: items.length }
  }

  private invoiceCounts() {
    const issued = this.invoices.filter((invoice) => invoice.status === 'issued')
    return {
      issued: issued.length, overdue: issued.filter((invoice) => this.overdueDays(invoice) > 0).length, paid: this.invoices.filter((invoice) => invoice.status === 'paid').length,
      void: this.invoices.filter((invoice) => invoice.status === 'void').length, outstanding: money(issued.reduce((sum, invoice) => sum + Number(invoice.amount) - Number(invoice.paid_amount), 0)),
    }
  }

  private deviceCounts() {
    const active = this.devices.filter((device) => !device.revoked).map((device) => this.deviceJson(device))
    return {
      total: active.length, online: active.filter((row) => row.state === 'online').length, offline: active.filter((row) => row.state === 'offline').length, never: active.filter((row) => row.state === 'never').length,
      revoked: this.devices.filter((device) => device.revoked).length, outdated: active.filter((row) => row.outdated).length,
      by_type: active.reduce<Record<string, number>>((sum, row) => ({ ...sum, [row.type]: (sum[row.type] ?? 0) + 1 }), {}),
    }
  }

  private fiscalSummary() {
    const open = this.fiscal.filter((receipt) => receipt.status !== 'issued')
    const drivers = [...new Set(open.map((receipt) => receipt.driver))]
    const by_driver = drivers.map((driver) => ({
      driver, failed: open.filter((r) => r.driver === driver && r.status === 'failed').length, pending: open.filter((r) => r.driver === driver && r.status === 'pending').length,
      last_error: open.filter((r) => r.driver === driver && r.status === 'failed' && r.error).at(-1)?.error ?? null,
    }))
    by_driver.sort((a, b) => b.failed - a.failed || b.pending - a.pending || a.driver.localeCompare(b.driver))
    return { failed: open.filter((receipt) => receipt.status === 'failed').length, pending: open.filter((receipt) => receipt.status === 'pending').length, by_driver }
  }

  private shiftCounts() {
    return {
      difference: this.shifts.filter((shift) => shift.status === 'closed' && shift.difference !== null && Number(shift.difference) !== 0).length,
      force_closed: this.shifts.filter((shift) => shift.force_closed).length, open: this.shifts.filter((shift) => shift.status === 'open').length, all: this.shifts.length,
    }
  }

  private syncCounts() {
    const open = this.sync.filter((op) => !op.resolution)
    return { rejected: open.filter((op) => op.status === 'rejected').length, conflict: open.filter((op) => op.status === 'conflict').length, open: open.length }
  }

  // --- Routing ---------------------------------------------------------------------------------------------------------------

  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    if (!(path.startsWith('/billing/') || path.startsWith('/pos/'))) return null
    const forbidden = fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
    const money_read = can('admin.subscriptions.manage') || can('admin.finance.view')
    const till_read = can('admin.devices.view')
    const reasonError = () => (typeof body.reason === 'string' && body.reason.trim().length >= 3 ? null : validation('reason', 'Sababni yozing (kamida 3 belgi).'))
    const write = (permission: string): Reply | null => (can(permission) ? (needsRecent() ?? reasonError()) : forbidden)
    const notFound = (what: string) => fail(404, `${what} topilmadi.`, { reason: 'not_found' })
    const reason = () => String(body.reason ?? '').trim()

    // --- P4-21: the catalog and a store's subscription
    if (path.startsWith('/billing/')) {
      if (req.method === 'GET' && path === '/billing/plans') {
        if (!money_read) return forbidden
        const plans = this.plans.filter((plan) => req.query.all === '1' || plan.version >= 2)
        return ok({ plans: plans.map((plan) => this.planJson(plan)), feature_catalog: FEATURES })
      }
      const plan = /^\/billing\/plans\/([a-z0-9_-]+)$/.exec(path)
      if (req.method === 'PUT' && plan) {
        const refused = write('admin.system.manage')
        if (refused) return refused
        const row = this.plans.find((candidate) => candidate.code === plan[1] && candidate.version >= 2)
        if (!row) return notFound('Tarif')
        for (const key of ['name', 'description', 'is_active', 'monthly_fee', 'yearly_fee', 'included_pos', 'included_kds', 'extra_pos_fee', 'extra_kds_fee', 'trial_days', 'features'] as const) {
          if (key in body) (row as unknown as Record<string, unknown>)[key] = key.endsWith('_fee') ? Number(body[key]).toFixed(2) : body[key]
        }
        if (row.features) row.features = [...new Set([...BASE, ...row.features])]
        return ok(this.planJson(row))
      }
      if (req.method === 'GET' && path === '/billing/subscriptions') {
        if (!money_read) return forbidden
        let rows = this.subscriptions.filter((row) => (req.query.status ? row.status === req.query.status : row.status !== 'cancelled'))
        if (req.query.plan) rows = rows.filter((row) => row.plan === req.query.plan)
        if (req.query.period) rows = rows.filter((row) => row.billing_period === req.query.period)
        if (req.query.store_id) rows = rows.filter((row) => row.store_id === Number(req.query.store_id))
        if (req.query.trial_ends_within) {
          const limit = Date.now() + Number(req.query.trial_ends_within) * 86_400_000
          rows = rows.filter((row) => row.trial_ends_at && Date.parse(row.trial_ends_at) > Date.now() && Date.parse(row.trial_ends_at) <= limit)
        }
        const counts: Record<string, number> = {}
        for (const row of this.subscriptions.filter((candidate) => candidate.status !== 'cancelled')) counts[row.status] = (counts[row.status] ?? 0) + 1
        return ok({ data: rows.sort((a, b) => b.id - a.id).map((row) => ({ subscription: this.subscriptionJson(row), store: this.named(row.store_id) })), next_cursor: null, per_page: 30, counts })
      }
      const one = /^\/billing\/stores\/(\d+)(\/subscription(?:\/(period|cancel|trial))?|\/commission)?$/.exec(path)
      if (one) {
        const store = this.stores.find((row) => row.id === Number(one[1]))
        if (req.method === 'GET' && !one[2]) {
          if (!money_read) return forbidden
          return store ? ok(this.billingCard(store)) : notFound('Ovqatxona')
        }
        const refused = write('admin.subscriptions.manage')
        if (refused) return refused
        if (!store) return notFound('Ovqatxona')
        const current = this.currentOf(store.id)
        if (req.method === 'POST' && one[2] === '/subscription') {
          const plan = this.plans.find((candidate) => candidate.code === body.plan && candidate.is_active)
          if (!plan) return fail(422, 'Tarif topilmadi.', { reason: 'plan_not_found' })
          const period = body.period === 'yearly' ? 'yearly' : 'monthly'
          if (!current) {
            this.subscriptions.push({ id: ++this.nextId, store_id: store.id, status: 'trialing', plan: plan.code, billing_period: period, next_plan: null, next_billing_period: null, cancel_at_period_end: false, started_at: iso(), trial_ends_at: new Date(Date.now() + plan.trial_days * 86_400_000).toISOString(), locked_at: null, cancelled_at: null })
          } else if (body.immediately === true) {
            current.plan = plan.code
            current.billing_period = period
            current.next_plan = null
          } else {
            current.next_plan = plan.code === current.plan ? null : plan.code
            current.next_billing_period = period
          }
          return ok(this.billingCard(store), { message: 'Tarif saqlandi.' })
        }
        if (!current) return fail(422, "Bu ovqatxonada obuna yo'q.", { reason: 'no_subscription' })
        if (req.method === 'POST' && one[2] === '/subscription/period') {
          current.next_billing_period = body.period === 'yearly' ? 'yearly' : 'monthly'
          if (current.status === 'trialing') current.billing_period = current.next_billing_period
          return ok(this.billingCard(store))
        }
        if (req.method === 'POST' && one[2] === '/subscription/cancel') {
          if (body.immediately === true) {
            current.status = 'cancelled'
            current.cancelled_at = iso()
          } else {
            current.cancel_at_period_end = true
          }
          return ok(this.billingCard(store))
        }
        if (req.method === 'POST' && one[2] === '/subscription/trial') {
          if (current.status !== 'trialing') return fail(422, 'Obuna sinov davrida emas.', { reason: 'billing_rule' })
          current.trial_ends_at = new Date(Date.parse(current.trial_ends_at ?? new Date().toISOString()) + Number(body.days) * 86_400_000).toISOString()
          return ok(this.billingCard(store))
        }
        if (req.method === 'PUT' && one[2] === '/commission') {
          const overrides = (body.overrides ?? {}) as Record<string, string | null>
          for (const [channel, value] of Object.entries(overrides)) {
            if (value === null) delete store.overrides[channel]
            else store.overrides[channel] = Number(value).toFixed(2)
          }
          if (body.delivery_courier === 'own' || body.delivery_courier === 'fleet') store.delivery_courier = body.delivery_courier
          return ok(this.commissionJson(store), { message: 'Komissiya saqlandi. U yangi buyurtmalardan amal qiladi.' })
        }
      }
      return null
    }

    // --- P4-24: the numbers on the tabs
    if (req.method === 'GET' && path === '/pos/overview') {
      if (!money_read && !till_read) return forbidden
      const counts: Record<string, number> = {}
      for (const row of this.subscriptions.filter((candidate) => candidate.status !== 'cancelled')) counts[row.status] = (counts[row.status] ?? 0) + 1
      const ending = this.subscriptions.filter((row) => row.status !== 'cancelled' && row.trial_ends_at && Date.parse(row.trial_ends_at) > Date.now() && Date.parse(row.trial_ends_at) <= Date.now() + 3 * 86_400_000).length
      return ok({
        subscriptions: money_read ? { counts, trial_ending: ending, trial_ending_days: 3 } : null,
        invoices: money_read ? this.invoiceCounts() : null,
        devices: till_read ? this.deviceCounts() : null,
        shifts: till_read ? this.shiftCounts() : null,
        fiscal: till_read ? this.fiscalSummary() : null,
        sync_errors: till_read ? this.syncCounts() : null,
      })
    }

    // --- Invoices
    if (path === '/pos/invoices' && req.method === 'GET') {
      if (!money_read) return forbidden
      let rows = this.invoices.slice()
      if (req.query.status === 'overdue') rows = rows.filter((invoice) => this.overdueDays(invoice) > 0)
      else if (req.query.status) rows = rows.filter((invoice) => invoice.status === req.query.status)
      if (req.query.dunning) rows = rows.filter((invoice) => invoice.dunning.at(-1)?.stage === req.query.dunning)
      if (req.query.q) rows = rows.filter((invoice) => invoice.number.toLowerCase().includes((req.query.q ?? '').toLowerCase()))
      rows.sort((a, b) => b.issued_at.localeCompare(a.issued_at))
      const page = this.page(rows.map((invoice) => this.invoiceRow(invoice)), req.query)
      return ok({ ...page, counts: this.invoiceCounts() })
    }
    const invoice = /^\/pos\/invoices\/([0-9a-f-]{36})(?:\/(paid|void))?$/.exec(path)
    if (invoice) {
      const row = this.invoices.find((candidate) => candidate.uuid === invoice[1])
      if (req.method === 'GET') {
        if (!money_read) return forbidden
        return row ? ok(this.invoiceCard(row)) : notFound('Hisob-faktura')
      }
      const refused = write('admin.finance.manage')
      if (refused) return refused
      if (!row) return notFound('Hisob-faktura')
      if (invoice[2] === 'paid') {
        if (row.status === 'void') return fail(422, "Bekor qilingan hisob-fakturani to'langan deb belgilab bo'lmaydi.", { reason: 'invoice_void' })
        if (row.status === 'issued') {
          row.status = 'paid'
          row.paid_at = iso()
          const store = this.stores.find((candidate) => candidate.id === row.store_id)
          const sub = this.currentOf(row.store_id)
          if (sub?.status === 'past_due') sub.status = 'active'
          if (store) store.subscription_debt = Math.max(0, store.subscription_debt - Number(row.amount))
        }
        return ok(this.invoiceCard(row), { message: "Hisob-faktura to'langan deb belgilandi." })
      }
      if (row.status !== 'void') {
        row.status = 'void'
        row.voided_at = iso()
        row.void_reason = reason()
      }
      return ok(this.invoiceCard(row), { message: 'Hisob-faktura bekor qilindi.' })
    }

    // --- Devices
    if (path === '/pos/devices' && req.method === 'GET') {
      if (!till_read) return forbidden
      let rows = this.devices.map((device) => this.deviceJson(device))
      const state = req.query.state
      rows = state === 'all' ? rows : state === 'revoked' ? rows.filter((row) => row.state === 'revoked') : rows.filter((row) => row.state !== 'revoked')
      if (state && state !== 'all' && state !== 'revoked') rows = rows.filter((row) => row.state === state)
      if (req.query.type) rows = rows.filter((row) => row.type === req.query.type)
      if (req.query.outdated === '1') rows = rows.filter((row) => row.outdated)
      if (req.query.q) rows = rows.filter((row) => `${row.name} ${row.store?.name}`.toLowerCase().includes((req.query.q ?? '').toLowerCase()))
      rows.sort((a, b) => (a.last_seen_at ?? '9').localeCompare(b.last_seen_at ?? '9'))
      const newest: Record<string, string> = {}
      for (const device of this.devices.filter((row) => !row.revoked && row.app_version)) {
        if (!newest[device.type] || device.app_version!.localeCompare(newest[device.type]!, undefined, { numeric: true }) > 0) newest[device.type] = device.app_version!
      }
      return ok({ ...this.page(rows, req.query), counts: this.deviceCounts(), latest_versions: newest })
    }
    const device = /^\/pos\/devices\/([0-9a-f-]{36})(\/revoke)?$/.exec(path)
    if (device) {
      const row = this.devices.find((candidate) => candidate.uuid === device[1])
      if (req.method === 'GET') {
        if (!till_read) return forbidden
        return row ? ok(this.deviceJson(row)) : notFound('Qurilma')
      }
      const refused = write('admin.stores.manage')
      if (refused) return refused
      if (!row) return notFound('Qurilma')
      row.revoked = true
      return ok(this.deviceJson(row), { message: "Qurilma o'chirildi. Undagi barcha sessiyalar yopildi." })
    }

    // --- Shifts
    if (path === '/pos/shifts' && req.method === 'GET') {
      if (!till_read) return forbidden
      let rows = this.shifts.slice()
      if (req.query.kind === 'difference') rows = rows.filter((shift) => shift.status === 'closed' && shift.difference !== null && Number(shift.difference) !== 0)
      if (req.query.kind === 'force_closed') rows = rows.filter((shift) => shift.force_closed)
      if (req.query.kind === 'open') rows = rows.filter((shift) => shift.status === 'open')
      return ok({ ...this.page(rows.map((shift) => this.shiftRow(shift)), req.query), counts: this.shiftCounts() })
    }
    const shift = /^\/pos\/shifts\/([0-9a-f-]{36})$/.exec(path)
    if (shift && req.method === 'GET') {
      if (!till_read) return forbidden
      const row = this.shifts.find((candidate) => candidate.uuid === shift[1])
      if (!row) return notFound('Smena')
      return ok({
        ...this.shiftRow(row), forced_by: row.forced_by ? { id: 3, name: row.forced_by } : null, counted_breakdown: row.counted_cash ? { '200000': 5, '50000': 4, '10000': 2 } : null,
        summary: { sales_total: '1150000.00' }, cash_movements: [{ uuid: 'm1', type: 'cash_out', category: 'supplies', amount: '30000.00', direction: -1, note: 'Non', user: { id: 1, name: row.opened_by }, created_at: iso(300) }],
      })
    }

    // --- Fiscal receipts
    if (path === '/pos/fiscal-receipts' && req.method === 'GET') {
      if (!till_read) return forbidden
      const status = req.query.status ?? 'open'
      let rows = this.fiscal.filter((receipt) => (status === 'all' ? true : status === 'open' ? receipt.status !== 'issued' : receipt.status === status))
      if (req.query.driver) rows = rows.filter((receipt) => receipt.driver === req.query.driver)
      if (req.query.q) rows = rows.filter((receipt) => receipt.uuid.startsWith((req.query.q ?? '').toLowerCase()))
      rows.sort((a, b) => a.created_minutes_ago - b.created_minutes_ago)
      return ok({ ...this.page(rows.map((receipt) => this.fiscalRow(receipt)), req.query), summary: this.fiscalSummary() })
    }
    const receipt = /^\/pos\/fiscal-receipts\/([0-9a-f-]{36})(\/retry)?$/.exec(path)
    if (receipt) {
      const row = this.fiscal.find((candidate) => candidate.uuid === receipt[1])
      const card = (value: FakeFiscal) => ({
        ...this.fiscalRow(value), full_error: value.error, payment_form: 'cash', tax_regime: 'vat', vat_total: '9214.29', fiscal_sign: value.status === 'issued' ? '123456789012' : null, receipt_number: null,
        terminal_id: null, original_receipt_uuid: null, sold_at: null, lines: [{ name: 'Osh', mxik_code: '10202001001000001', quantity: '2', amount: value.total }], payments: [{ form: 'cash', amount: value.total }],
      })
      if (req.method === 'GET') {
        if (!till_read) return forbidden
        return row ? ok(card(row)) : notFound('Chek')
      }
      const refused = write('admin.stores.manage')
      if (refused) return refused
      if (!row) return notFound('Chek')
      if (row.status === 'issued' || row.driver === 'external') return fail(422, "Chek allaqachon ro'yxatdan o'tgan.", { reason: 'already_issued' })
      row.attempts += 1
      // A receipt with a wrong MXIK keeps failing; the others go through
      if (row.error?.includes('MXIK')) {
        row.status = 'failed'
        return ok(card(row), { message: "Chek hali ro'yxatdan o'tmadi: " + row.error })
      }
      row.status = 'issued'
      row.error = null
      return ok(card(row), { message: "Chek ro'yxatdan o'tdi." })
    }

    // --- Offline sync errors
    if (path === '/pos/sync-errors' && req.method === 'GET') {
      if (!till_read) return forbidden
      const state = req.query.state ?? 'open'
      let rows = this.sync.filter((op) => (state === 'all' ? true : state === 'resolved' ? op.resolution !== null : op.resolution === null))
      if (req.query.status) rows = rows.filter((op) => op.status === req.query.status)
      rows.sort((a, b) => a.received_minutes_ago - b.received_minutes_ago)
      return ok({ ...this.page(rows.map((op) => this.syncRow(op)), req.query), counts: this.syncCounts() })
    }
    const op = /^\/pos\/sync-errors\/([0-9a-f-]{36})(\/resolve)?$/.exec(path)
    if (op) {
      const row = this.sync.find((candidate) => candidate.op_id === op[1])
      const card = (value: FakeSync) => ({ ...this.syncRow(value), applied_at: null, payload: value.payload, refs: {} })
      if (req.method === 'GET') {
        if (!till_read) return forbidden
        return row ? ok(card(row)) : notFound('Sinxronlash xatosi')
      }
      const refused = write('admin.stores.manage')
      if (refused) return refused
      if (!row) return notFound('Sinxronlash xatosi')
      if (body.resolution !== 'resolved' && body.resolution !== 'dismissed') return validation('resolution', 'Qaror noto‘g‘ri.')
      if (!row.resolution) {
        row.resolution = body.resolution
        row.resolution_note = reason()
        row.resolved_by = actor.name
      }
      return ok(card(row), { message: body.resolution === 'resolved' ? 'Hal qilindi deb belgilandi.' : 'Yopildi.' })
    }
    return null
  }
}
