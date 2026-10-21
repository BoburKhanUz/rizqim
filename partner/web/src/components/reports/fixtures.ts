// Server answers the reports and finance tests share (shapes of docs/API.md, 12; money as two-decimal strings)
export const todayAnalytics = {
  period: { key: 'today', from: '2026-10-21', to: '2026-10-21', days: 1, timezone: 'Asia/Tashkent', basis: 'created_at' },
  summary: { orders_count: 0, delivered_count: 0, canceled_count: 0, refunded_count: 0, active_count: 0, revenue: 0, average_check: 0 },
  by_status: {},
  by_payment_method: [],
  by_order_type: [],
  by_channel: [],
  top_products: [],
  couriers: [],
  times: { average_prep_seconds: null, average_delivery_seconds: null },
  daily: [],
  generated_at: '2026-10-21T10:30:00+05:00',
};

export const salesReport = {
  period: { from: '2026-10-15', to: '2026-10-21', days: 7, timezone: 'Asia/Tashkent', basis: 'created_at', group_by: 'day' },
  branch_id: null,
  summary: { orders_count: 42, delivered_count: 30, canceled_count: 3, refunded_count: 1, active_count: 8, revenue: '1250000.00', average_check: '41666.67' },
  by_status: { delivered: 30, canceled: 3 },
  series: [
    { date: '2026-10-15', orders: 5, delivered: 4, revenue: '150000.00' },
    { date: '2026-10-16', orders: 8, delivered: 6, revenue: '300000.00' },
    { date: '2026-10-17', orders: 0, delivered: 0, revenue: '0.00' },
    { date: '2026-10-18', orders: 9, delivered: 7, revenue: '250000.00' },
    { date: '2026-10-19', orders: 6, delivered: 4, revenue: '200000.00' },
    { date: '2026-10-20', orders: 7, delivered: 5, revenue: '230000.00' },
    { date: '2026-10-21', orders: 7, delivered: 4, revenue: '120000.00' },
  ],
  by_channel: [
    { channel: 'telegram', label: 'Telegram', orders: 30, revenue: '900000.00' },
    { channel: 'pos_hall', label: 'Zal (POS)', orders: 12, revenue: '350000.00' },
  ],
  by_branch: [
    { branch_id: 1, name: 'Chilonzor', orders: 25, delivered: 20, revenue: '800000.00' },
    { branch_id: null, name: null, orders: 17, delivered: 10, revenue: '450000.00' },
  ],
  by_payment_method: [
    { method: 'cash', orders: 25, revenue: '700000.00' },
    { method: 'click', orders: 17, revenue: '550000.00' },
  ],
  by_order_type: [
    { type: 'delivery', orders: 30, revenue: '900000.00' },
    { type: 'pickup', orders: 12, revenue: '350000.00' },
  ],
};

export const topProducts = {
  period: { from: '2026-10-15', to: '2026-10-21', days: 7, timezone: 'Asia/Tashkent', basis: 'created_at' },
  branch_id: null,
  sort: 'quantity',
  products: [
    { name: 'Osh', quantity: 18, revenue: '540000.00', orders: 15 },
    { name: "Lag'mon (kg)", quantity: 2.5, revenue: '330000.00', orders: 4 },
  ],
};

export const ok = (data: unknown) => ({ success: true, message: 'ok', data });

// --- Finance ---
export const ownerFinance = {
  debt_balance: 120000,
  wallet_balance: 500000,
  credit_limit: 1000000,
  min_payout_reserve: 100000,
  payment_account_type: 'platform',
  commission_rate: 10,
  status: 'active',
  is_order_locked: false,
  ledger_transactions: [],
  wallet_transactions: [],
  payout_requests: [],
};

export const pageOf = <T,>(rows: T[], over: Record<string, unknown> = {}) => ({
  current_page: 1,
  data: rows,
  last_page: 1,
  per_page: 25,
  total: rows.length,
  from: rows.length ? 1 : null,
  to: rows.length || null,
  ...over,
});

export const ledgerRows = [
  { id: 12, type: 'commission', amount: '4500.00', balance_before: '115500.00', balance_after: '120000.00', order: { uuid: 'aaaaaaaa-1111', short_code: 'E101' }, reference_id: null, notes: null, created_at: '2026-10-20T10:00:00+05:00' },
  { id: 11, type: 'subscription', amount: '100000.00', balance_before: '15500.00', balance_after: '115500.00', order: null, reference_id: 'inv-1', notes: 'OB-202609-000042', created_at: '2026-10-01T00:15:00+05:00' },
];

export const walletRows = [
  { id: 7, type: 'settlement', amount: '45000.00', balance_before: '455000.00', balance_after: '500000.00', order: { uuid: 'bbbbbbbb-2222', short_code: 'E102' }, reference_id: null, notes: null, created_at: '2026-10-20T11:00:00+05:00' },
];

export const paymentRows = [
  { uuid: 'p-1', provider: 'click', provider_transaction_id: 'CLK-998877', status: 'paid', amount: '45000.00', paid_at: '2026-10-20T11:05:00+05:00', cancelled_at: null, created_at: '2026-10-20T11:00:00+05:00', order: { uuid: 'bbbbbbbb-2222', short_code: 'E102', branch_id: 1, total_price: '45000.00' } },
  { uuid: 'p-2', provider: 'payme', provider_transaction_id: null, status: 'failed', amount: '30000.00', paid_at: null, cancelled_at: null, created_at: '2026-10-20T12:00:00+05:00', order: { uuid: 'cccccccc-3333', short_code: null, branch_id: null, total_price: '30000.00' } },
];

export const payoutRows = [
  { id: 3, amount: '500000.00', card: '8600 **** **** 9012', status: 'pending', notes: 'Oy oxiri', resolution_notes: null, processed_at: null, created_at: '2026-10-19T09:00:00+05:00' },
  { id: 2, amount: '250000.00', card: '8600 **** **** 9012', status: 'completed', notes: null, resolution_notes: 'Yuborildi', processed_at: '2026-10-10T12:00:00+05:00', created_at: '2026-10-09T09:00:00+05:00' },
];

const plan = { code: 'mixed', name: 'Aralash', description: null, summary: "5% komissiya + 100 000 so'm", commission_percent: 5, monthly_base_fee: 100000, monthly_fee_per_terminal: 100000, included_terminals: 1, trial_days: 14 };

export const subscription = {
  subscription: {
    status: 'active',
    status_label: 'Faol',
    plan,
    started_at: '2026-09-01T00:00:00+05:00',
    trial_ends_at: null,
    current_period: { period: '2026-10', start: '2026-10-01', end: '2026-10-31' },
    next_plan: null,
    cancel_at_period_end: false,
  },
  commission_rate: 5,
  terminals: { active_now: 2, counted: 2 },
  next_invoice: {
    period: '2026-10',
    issued_on: '2026-11-01',
    trial: false,
    amount: 200000,
    lines: [
      { type: 'base', label: 'Asosiy to\'lov', quantity: 1, unit_price: 100000, amount: 100000 },
      { type: 'terminal', label: 'Qo\'shimcha terminal', quantity: 1, unit_price: 100000, amount: 100000 },
    ],
  },
};

export const invoiceRows = [
  { uuid: 'i-2', number: 'OB-202609-000042', period: '2026-09', period_start: '2026-09-01', period_end: '2026-09-30', plan: { code: 'mixed', name: 'Aralash' }, terminals_count: 1, included_terminals: 1, base_fee: 100000, fee_per_terminal: 100000, lines: [], amount: 100000, status: 'issued', due_date: '2026-10-10', issued_at: '2026-10-01T00:15:00+05:00', paid_at: null, voided_at: null },
  { uuid: 'i-1', number: 'OB-202608-000042', period: '2026-08', period_start: '2026-08-01', period_end: '2026-08-31', plan: { code: 'mixed', name: 'Aralash' }, terminals_count: 1, included_terminals: 1, base_fee: 100000, fee_per_terminal: 100000, lines: [], amount: 100000, status: 'paid', due_date: '2026-09-10', issued_at: '2026-09-01T00:15:00+05:00', paid_at: '2026-09-05T10:00:00+05:00', voided_at: null },
];
