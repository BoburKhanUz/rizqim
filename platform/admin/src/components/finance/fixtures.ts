import type { Adjustment, BalancePage, FinanceSummary, KeshbekReport, MonthlyReport, PayoutCard, PayoutPage, PayoutRow } from '@/lib/api/finance';

// Answers of the Moliya endpoints for the component tests (shapes of AdminFinance*Controller)

export const summary = (overrides: Partial<FinanceSummary> = {}): FinanceSummary => ({
  payouts: { pending: 1, awaiting_second: 1, open_amount: '1400000.00' },
  adjustments: { pending: 1 },
  settlement_failures: { count: 0, gave_up: 0 },
  topups: { stuck: 0 },
  balances: { over_limit: 1, wallet_total: '500000.00', debt_total: '298000.00', subscription_debt_total: '298000.00' },
  threshold: '1000000.00',
  ...overrides,
});

export const payoutRow = (overrides: Partial<PayoutRow> = {}): PayoutRow => ({
  id: 12,
  store: { id: 3, name: 'Lazzat Oshxona' },
  amount: '400000.00',
  card: '8600 **** **** 9012',
  status: 'pending',
  status_label: 'Tasdiq kutilmoqda',
  needs_second_approval: false,
  first_approval: null,
  netted_amount: null,
  transferred_amount: null,
  created_at: '2026-10-28T09:00:00+05:00',
  processed_at: null,
  ...overrides,
});

export const payoutCard = (overrides: Partial<PayoutCard> = {}): PayoutCard => ({
  ...payoutRow(),
  threshold: '1000000.00',
  notes: null,
  resolution_notes: null,
  processed_by: null,
  breakdown: {
    accrued: '400000.00',
    netted: '298000.00',
    transfer: '102000.00',
    preview: true,
    lines: [
      { kind: 'invoice', label: 'Obuna hisob-fakturasi OB-202609-3 (2026-09)', amount: '149000.00', invoice: { id: 1, uuid: 'i-1', number: 'OB-202609-3', period: '2026-09' }, transaction_id: null },
      { kind: 'invoice', label: 'Obuna hisob-fakturasi OB-202610-3 (2026-10)', amount: '149000.00', invoice: { id: 2, uuid: 'i-2', number: 'OB-202610-3', period: '2026-10' }, transaction_id: null },
    ],
  },
  balances: { wallet: '500000.00', debt: '298000.00', subscription_debt: '298000.00', commission_debt: '0.00', credit_limit: '-100000.00' },
  actions: { can_approve: true, can_reject: true, approve_completes: true },
  ...overrides,
});

export const payoutPage = (data: PayoutRow[]): PayoutPage => ({ data, next_cursor: null, per_page: 30, counts: { pending: 1, awaiting_second: 1, open_amount: '1400000.00' }, threshold: '1000000.00' });

export const adjustment = (overrides: Partial<Adjustment> = {}): Adjustment => ({
  id: 5,
  uuid: 'a-5',
  store: { id: 3, name: 'Lazzat Oshxona' },
  kind: 'wallet_credit',
  kind_label: "Hamyonga qo'shish",
  ledger: 'wallet',
  amount: '1500000.00',
  signed_amount: '1500000.00',
  reason: "Bank ko'chirmasi",
  status: 'pending',
  status_label: 'Ikkinchi tasdiq kutilmoqda',
  needs_second_approval: true,
  requested_by: { id: 9, name: 'Moliya 1' },
  requested_at: '2026-10-28T10:00:00+05:00',
  approved_by: null,
  approved_at: null,
  rejected_by: null,
  rejected_at: null,
  decision_note: null,
  applied_at: null,
  document: null,
  transactions: [],
  mine: false,
  can_approve: true,
  can_reject: true,
  ...overrides,
});

export const adjustmentPage = (data: Adjustment[]) => ({
  data,
  next_cursor: null,
  per_page: 30,
  counts: { pending: data.filter((row) => row.status === 'pending').length },
  kinds: [],
  threshold: '1000000.00',
});

export const balances = (): BalancePage => ({
  data: [
    {
      store: { id: 3, name: 'Lazzat Oshxona', status: 'payment_required', status_label: "To'lov kutilmoqda" },
      wallet_balance: '0.00',
      debt_balance: '120000.00',
      subscription_debt: '0.00',
      commission_debt: '120000.00',
      credit_limit: '-100000.00',
      over_limit: true,
      is_order_locked: true,
      pending_payouts: '0.00',
    },
  ],
  page: 1,
  per_page: 30,
  total: 1,
  totals: { over_limit: 1, wallet_total: '0.00', debt_total: '120000.00', subscription_debt_total: '0.00', commission_debt_total: '120000.00' },
});

export const report = (): MonthlyReport => ({
  period: '2026-10',
  timezone: 'Asia/Tashkent',
  totals: { orders: 4, gmv: '260000.00', commission: '28500.00', commission_refunded: '5000.00', subscription: '149000.00', total: '177500.00', referral_count: 1, referral_bonuses: '5000.00', net: '172500.00' },
  channels: [
    { channel: 'rizqim_courier', label: 'RIZQIM kuryeri', orders: 2, gmv: '200000.00', commission: '25000.00', commission_refunded: '5000.00' },
    { channel: 'pickup', label: 'Olib ketish', orders: 1, gmv: '50000.00', commission: '2500.00', commission_refunded: '0.00' },
  ],
  subscriptions: { invoices: 1, invoiced: '149000.00', paid: '100000.00', outstanding: '49000.00' },
  stores: [{ store: { id: 4, name: 'Beta' }, orders: 0, gmv: '0.00', commission: '0.00', commission_by_channel: {}, subscription: '149000.00', total: '149000.00' }],
});

// The backend test's October: 3 bonuses, 1 taken back, a 50 000 keshbek order, 20 000 of it returned
export const keshbekReport = (overrides: Partial<KeshbekReport> = {}): KeshbekReport => ({
  period: '2026-10',
  timezone: 'Asia/Tashkent',
  as_of: '2026-11-10T12:00:00+05:00',
  issued: { count: 3, amount: '15000.00' },
  clawed_back: { count: 1, amount: '5000.00' },
  legacy_store_paid: { count: 0, amount: '0.00' },
  spent: { count: 1, amount: '50000.00' },
  returned: { count: 1, amount: '20000.00' },
  transferred: { count: 0, amount: '0.00' },
  outstanding: { positive: '30000.00', negative: '0.00', net: '30000.00', holders: 1, debtors: 0 },
  reconciliation: { opening: '50000.00', movement: '-20000.00', closing: '30000.00', after_period: '0.00', expected_now: '30000.00', live: '30000.00', difference: '0.00' },
  unrecorded: { keshbek_orders_without_payment: 0, invalid_payload_values: 0 },
  ...overrides,
});
