import type {
  BillingInvoice,
  Catalog,
  Commission,
  DevicePage,
  DeviceRow,
  Entitlements,
  FiscalCard,
  FiscalRow,
  InvoiceCard,
  InvoiceRow,
  Overview,
  Plan,
  Subscription,
  StoreBilling,
  SyncCard,
  SyncRow,
} from '@/lib/api/pos';

// Builders for the POS va obunalar tests: one realistic row each, overridden per test.

export const plan = (overrides: Partial<Plan> = {}): Plan => ({
  id: 2,
  code: 'asosiy',
  name: 'Asosiy',
  version: 2,
  tier: 'asosiy',
  is_active: true,
  sort_order: 20,
  description: null,
  monthly_fee: '249000.00',
  yearly_fee: '2490000.00',
  included_pos: 2,
  included_kds: 1,
  extra_pos_fee: '59000.00',
  extra_kds_fee: '29000.00',
  trial_days: 14,
  features: ['hall', 'shifts', 'bookings', 'kds'],
  subscriptions: 4,
  ...overrides,
});

export const catalog = (): Catalog => ({
  plans: [
    plan({ id: 1, code: 'start', name: 'Start', monthly_fee: '149000.00', yearly_fee: '1490000.00', included_pos: 1, included_kds: 0, features: ['hall', 'shifts'], subscriptions: 7 }),
    plan(),
    plan({ id: 3, code: 'pro', name: 'Pro', monthly_fee: '449000.00', yearly_fee: '4490000.00', included_pos: 4, included_kds: 3, features: ['hall', 'shifts', 'bookings', 'kds', 'banquet'], subscriptions: 1 }),
    plan({ id: 9, code: 'pos', name: 'POS (eski)', version: 1, is_active: false, subscriptions: 3 }),
  ],
  feature_catalog: [
    { key: 'hall', label: 'Zal', base: true },
    { key: 'shifts', label: 'Smenalar', base: true },
    { key: 'bookings', label: 'Bronlar', base: false },
    { key: 'kds', label: 'KDS', base: false },
    { key: 'banquet', label: 'Banket', base: false },
  ],
});

export const subscription = (overrides: Partial<Subscription> = {}): Subscription => ({
  id: 5,
  store_id: 12,
  status: 'active',
  status_label: 'Faol',
  plan: { code: 'asosiy', name: 'Asosiy', version: 2 },
  billing_period: 'monthly',
  next_billing_period: null,
  next_plan: null,
  cancel_at_period_end: false,
  started_at: '2026-08-01T09:00:00+05:00',
  trial_ends_at: null,
  current_period: { start: '2026-11-01T00:00:00+05:00', end: '2026-12-01T00:00:00+05:00' },
  term_ends_at: null,
  changes_at: '2026-12-01T00:00:00+05:00',
  price_locked_until: null,
  locked_at: null,
  cancelled_at: null,
  ...overrides,
});

export const entitlements = (overrides: Partial<Entitlements> = {}): Entitlements => ({
  plan: { code: 'asosiy', name: 'Asosiy', tier: 'asosiy', version: 2 },
  legacy: false,
  period: 'monthly',
  status: 'active',
  trial_ends_at: null,
  current_period: null,
  term_ends_at: null,
  locked_since: null,
  lock_at: null,
  settings_locked: false,
  features: { hall: true, bookings: true, banquet: false },
  required_plans: { bookings: 'asosiy', banquet: 'pro' },
  devices: {
    pos: { included: 2, active: 3, extra: 1, extra_price: '59000.00', can_add: true },
    kds: { included: 1, active: 1, extra: 0, extra_price: '29000.00', can_add: true },
    waiter: { included: null, unlimited: true, extra_price: '0.00', can_add: true },
  },
  notice: null,
  ...overrides,
});

export const commission = (overrides: Partial<Commission> = {}): Commission => ({
  rates: {
    rizqim_courier: { rate: '15.00', source: 'default', default: '15.00' },
    own_delivery: { rate: '10.00', source: 'default', default: '10.00' },
    pickup: { rate: '3.00', source: 'override', default: '5.00' },
    pos: { rate: '0.00', source: 'default', default: '0.00' },
    aggregator: { rate: '0.00', source: 'default', default: '0.00' },
  },
  overrides: { pickup: '3.00' },
  flat_rate: null,
  delivery_courier: 'own',
  ...overrides,
});

export const billingInvoice = (overrides: Partial<BillingInvoice> = {}): BillingInvoice => ({
  uuid: '1a0c0000-0000-4000-8000-000000000001',
  number: 'OB-202610-000015',
  kind: 'monthly',
  period: '2026-10',
  period_start: '2026-10-01T00:00:00+05:00',
  period_end: '2026-11-01T00:00:00+05:00',
  plan: { code: 'asosiy', name: 'Asosiy' },
  lines: [
    { type: 'base', label: 'Asosiy tarif', quantity: 1, unit_price: '249000.00', amount: '249000.00' },
    { type: 'extra_pos', label: 'Ortiqcha kassa', quantity: 1, unit_price: '59000.00', amount: '59000.00' },
    { type: 'discount', label: 'Chegirma 50%', quantity: 1, unit_price: '-124500.00', amount: '-124500.00' },
  ],
  amount: '183500.00',
  paid_amount: '0.00',
  outstanding: '183500.00',
  status: 'issued',
  due_date: '2026-11-05',
  issued_at: '2026-11-01T00:05:00+05:00',
  paid_at: null,
  voided_at: null,
  dunning: [{ stage: 'reminder_1', label: '1-kun eslatmasi', notified: true, recipients: 1, occurred_at: '2026-11-06T10:00:00+05:00', invoice: 'OB-202610-000015' }],
  ...overrides,
});

export const storeBilling = (overrides: Partial<StoreBilling> = {}): StoreBilling => ({
  store: { id: 12, name: 'Lazzat Oshxona', status: 'active' },
  subscription: subscription(),
  entitlements: entitlements(),
  debt: { debt_balance: '300000.00', subscription_debt: '183500.00', commission_debt: '116500.00', credit_limit: '100000.00', over_credit_limit: true },
  commission: commission(),
  history: [subscription()],
  invoices: [billingInvoice()],
  ...overrides,
});

export const invoiceRow = (overrides: Partial<InvoiceRow> = {}): InvoiceRow => ({
  uuid: '1a0c0000-0000-4000-8000-000000000001',
  number: 'OB-202610-000015',
  kind: 'monthly',
  period: '2026-10',
  store: { id: 12, name: 'Lazzat Oshxona' },
  plan: { code: 'asosiy', name: 'Asosiy' },
  amount: '183500.00',
  paid_amount: '0.00',
  outstanding: '183500.00',
  status: 'issued',
  status_label: "To'lanmagan",
  due_date: '2026-11-05',
  issued_at: '2026-11-01T00:05:00+05:00',
  paid_at: null,
  voided_at: null,
  overdue: true,
  overdue_days: 5,
  dunning_stage: 'reminder_1',
  dunning_label: '1-kun eslatmasi',
  dunning_at: '2026-11-06T10:00:00+05:00',
  ...overrides,
});

export const invoiceCard = (overrides: Partial<InvoiceRow> = {}): InvoiceCard => ({
  invoice: { ...invoiceRow(overrides), ...billingInvoice({ status: overrides.status ?? 'issued' }), void_reason: null, ...overrides },
  store: { id: 12, name: 'Lazzat Oshxona', status: 'active', debt_balance: '300000.00', subscription_debt: '183500.00', commission_debt: '116500.00' },
});

export const deviceRow = (overrides: Partial<DeviceRow> = {}): DeviceRow => ({
  uuid: '7a1b0000-0000-4000-8000-000000000001',
  name: 'Kassa 1',
  type: 'pos',
  type_label: 'Kassa (POS)',
  terminal: true,
  store: { id: 13, name: 'Lazzat Express' },
  branch: { id: 1, name: 'Yunusobod' },
  platform: 'android',
  app_version: '1.4.0',
  latest_version: '1.6.2',
  outdated: true,
  state: 'offline',
  last_seen_at: new Date(Date.now() - 25 * 60_000).toISOString(),
  paired_at: '2026-09-01T10:00:00+05:00',
  revoked_at: null,
  sync: { pending_ops: 3, oldest_op_at: '2026-11-10T12:00:00+05:00', reported_at: '2026-11-10T13:00:00+05:00', clock_skew_seconds: 900, clock_warning: true },
  ...overrides,
});

export const devicePage = (rows: DeviceRow[] = [deviceRow()]): DevicePage => ({
  data: rows,
  current_page: 1,
  last_page: 1,
  per_page: 30,
  total: rows.length,
  counts: { total: 3, online: 1, offline: 1, never: 1, revoked: 0, outdated: 1, by_type: { pos: 2, kds: 1 } },
  latest_versions: { pos: '1.6.2' },
});

export const fiscalRow = (overrides: Partial<FiscalRow> = {}): FiscalRow => ({
  uuid: '5f0c2a10-0000-4000-8000-000000000001',
  type: 'sale',
  status: 'failed',
  driver: 'sandbox',
  provider: null,
  non_fiscal: false,
  total: '86000.00',
  error: 'Soliq serveri javob bermadi',
  attempts: 5,
  next_attempt_at: null,
  created_at: '2026-11-10T13:05:00+05:00',
  issued_at: null,
  store: { id: 12, name: 'Lazzat Oshxona' },
  branch: { id: 1, name: 'Chilonzor' },
  retryable: true,
  subject_type: 'order',
  subject_id: 1,
  ...overrides,
});

export const fiscalCard = (overrides: Partial<FiscalCard> = {}): FiscalCard => ({
  ...fiscalRow(),
  full_error: 'Soliq serveri javob bermadi',
  payment_form: 'cash',
  tax_regime: 'vat',
  vat_total: '9214.29',
  fiscal_sign: null,
  receipt_number: null,
  terminal_id: null,
  original_receipt_uuid: null,
  sold_at: null,
  lines: [{ name: 'Osh', mxik_code: '10202001001000001', quantity: '2', amount: '86000.00' }],
  payments: [{ form: 'cash', amount: '86000.00' }],
  ...overrides,
});

export const syncRow = (overrides: Partial<SyncRow> = {}): SyncRow => ({
  op_id: '0b5f0000-0000-4000-8000-000000000001',
  seq: 1,
  type: 'bill.pay',
  type_label: "To'lov olindi",
  status: 'rejected',
  reason: 'order_cancelled',
  message: 'Buyurtma bekor qilingan',
  store: { id: 13, name: 'Lazzat Express' },
  device: { uuid: 'd', name: 'Kassa 1' },
  staff: { id: 1, name: 'Rustam' },
  order: { uuid: 'o', local_number: null, short_code: 'AB12CD', table_ref: null, status: 'canceled' },
  occurred_at: '2026-11-10T12:00:00+05:00',
  received_at: '2026-11-10T12:05:00+05:00',
  clock_skew_seconds: 12,
  resolved_at: null,
  resolved_by: null,
  resolution: null,
  resolution_note: null,
  ...overrides,
});

export const syncCard = (overrides: Partial<SyncCard> = {}): SyncCard => ({ ...syncRow(), applied_at: null, payload: { order: 'o1', amount: '50000', customer_phone: '***' }, refs: {}, ...overrides });

export const overview = (overrides: Partial<Overview> = {}): Overview => ({
  subscriptions: { counts: { active: 5, trialing: 2, past_due: 1 }, trial_ending: 1, trial_ending_days: 3 },
  invoices: { issued: 2, overdue: 1, paid: 10, void: 0, outstanding: '432000.00' },
  devices: { total: 3, online: 1, offline: 1, never: 1, revoked: 0, outdated: 1, by_type: { pos: 2, kds: 1 } },
  shifts: { difference: 2, force_closed: 1, open: 1, all: 5 },
  fiscal: { failed: 2, pending: 1, by_driver: [{ driver: 'sandbox', failed: 2, pending: 1, last_error: 'Soliq serveri javob bermadi' }] },
  sync_errors: { rejected: 1, conflict: 1, open: 2 },
  ...overrides,
});

export const page = <T>(rows: T[], extra: Record<string, unknown> = {}) => ({ data: rows, current_page: 1, last_page: 1, per_page: 30, total: rows.length, ...extra });
