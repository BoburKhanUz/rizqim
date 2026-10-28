import type { Adjustment, BalancePage, FinanceSummary, MonthlyReport, PayoutCard, PayoutPage, PayoutRow } from '@/lib/api/finance';

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
  totals: { orders: 4, gmv: '260000.00', commission: '28500.00', commission_refunded: '5000.00', subscription: '149000.00', total: '177500.00' },
  channels: [
    { channel: 'rizqim_courier', label: 'RIZQIM kuryeri', orders: 2, gmv: '200000.00', commission: '25000.00', commission_refunded: '5000.00' },
    { channel: 'pickup', label: 'Olib ketish', orders: 1, gmv: '50000.00', commission: '2500.00', commission_refunded: '0.00' },
  ],
  subscriptions: { invoices: 1, invoiced: '149000.00', paid: '100000.00', outstanding: '49000.00' },
  stores: [{ store: { id: 4, name: 'Beta' }, orders: 0, gmv: '0.00', commission: '0.00', commission_by_channel: {}, subscription: '149000.00', total: '149000.00' }],
});
