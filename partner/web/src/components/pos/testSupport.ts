import type { Booking, CustomerAccount, Hall, Printer, Shift, ShiftReport } from '@rizqim/staff';
import { ok, permissions, signIn } from '../management/testSupport';

// Shared by the tests of the POS screens (P3-23): fixtures shaped like the API's answers (docs/API.md P3-11 ... P3-19).
export { ok, permissions, signIn };

export const POS_OWNER = [
  'orders.view', 'menu.view', 'menu.manage', 'settings.manage', 'tables.manage', 'bookings.manage', 'customer_accounts.manage',
  'pos.close_shift', 'reports.view', 'finance.view', 'devices.view',
];

export const ownerAnswer = (keys: string[] = POS_OWNER, role = 'owner') => permissions(keys, { role });

export const table = (over: Partial<Hall['tables'][number]> = {}): Hall['tables'][number] => ({
  id: 11,
  branch_id: 1,
  hall_id: 1,
  name: 'Xona 1',
  kind: 'room',
  seats: 12,
  layout: { x: '2.00', y: '1.00', w: '5.00', h: '4.00' },
  sort_order: 0,
  is_active: true,
  min_spend: '600000.00',
  min_spend_mode: 'per_table',
  ...over,
});

export const hall = (over: Partial<Hall> = {}): Hall => ({
  id: 1,
  branch_id: 1,
  name: 'Asosiy zal',
  sort_order: 0,
  is_active: true,
  service_fee: null,
  tables: [table(), table({ id: 12, name: 'Stol 5', kind: 'table', seats: 4, layout: { x: '10.00', y: '2.00', w: '2.00', h: '2.00' }, min_spend: null })],
  ...over,
});

export const device = (over: Record<string, unknown> = {}) => ({
  uuid: 'dev-1',
  type: 'pos',
  name: 'Kassa 1',
  platform: 'windows',
  app_version: '1.0.0',
  store_id: 1,
  branch_id: 1,
  user: null,
  has_push_token: false,
  last_seen_at: null,
  revoked_at: null,
  created_at: null,
  ...over,
});

export const printer = (over: Partial<Printer> = {}): Printer => ({
  id: 1,
  branch_id: 1,
  name: 'Kassa printeri',
  purpose: 'receipt',
  connection: 'lan',
  address: '192.168.0.50',
  port: 9100,
  paper_width: 80,
  station_id: null,
  device_id: null,
  is_active: true,
  encoding: null,
  print_as_image: false,
  ...over,
});

export const booking = (over: Partial<Booking> = {}): Booking => ({
  uuid: 'b0000000-0000-4000-8000-000000000001',
  branch_id: 1,
  dining_table_id: 11,
  table_ids: [11],
  // 18:00-22:00 in Tashkent on 22 October 2026
  starts_at: '2026-10-22T13:00:00+00:00',
  ends_at: '2026-10-22T17:00:00+00:00',
  guests_planned: 20,
  event_type: 'tugilgan_kun',
  event_type_label: "Tug'ilgan kun",
  contact_name: 'Aziz Karimov',
  contact_phone: '+998901234567',
  source: 'phone',
  source_label: 'Telefon',
  status: 'confirmed',
  status_label: 'Tasdiqlangan',
  min_spend: null,
  note: null,
  order_uuid: null,
  deposit_total: '200000.00',
  deposit_available: '200000.00',
  deposit_unsettled: false,
  items_count: 0,
  cancel_reason: null,
  version: 3,
  created_by: 7,
  confirmed_at: null,
  arrived_at: null,
  completed_at: null,
  canceled_at: null,
  confirmation_sent_at: null,
  confirmation_channel: null,
  created_at: '2026-10-20T09:00:00+00:00',
  updated_at: null,
  ...over,
});

export const calendar = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  from: '2026-10-22',
  to: '2026-10-22',
  timezone: 'Asia/Tashkent',
  rooms: [
    { id: 11, name: 'Xona 1', kind: 'room', seats: 12, hall_id: 1, hall_name: 'Asosiy zal', is_active: true, min_spend: null, bookings: [booking()] },
    { id: 13, name: 'Xona 2', kind: 'room', seats: 8, hall_id: 1, hall_name: 'Asosiy zal', is_active: true, min_spend: null, bookings: [] },
  ],
  unassigned: [],
  ...over,
});

export const account = (over: Partial<CustomerAccount> = {}): CustomerAccount => ({
  uuid: 'a0000000-0000-4000-8000-000000000001',
  name: 'Karim Rahimov',
  phone: '+998909998877',
  group_name: 'Kompaniya A',
  branch_id: null,
  credit_limit: '1000000.00',
  balance: '350000.00',
  available_credit: '650000.00',
  is_blocked: false,
  note: null,
  created_at: null,
  updated_at: null,
  ...over,
});

export const shift = (over: Partial<Shift> = {}): Shift => ({
  uuid: 's0000000-0000-4000-8000-000000000001',
  status: 'closed',
  branch_id: 1,
  device: { uuid: 'dev-1', name: 'Kassa 1' },
  business_date: '2026-10-21',
  opened_at: '2026-10-21T04:00:00+00:00',
  opened_by: { id: 5, name: 'Kassir Ali' },
  opening_cash: '500000.00',
  closed_at: '2026-10-21T19:00:00+00:00',
  closed_by: { id: 5, name: 'Kassir Ali' },
  expected_cash: '1750000.00',
  counted_cash: '1740000.00',
  counted_breakdown: { '100000': 17, '10000': 4 },
  difference: '-10000.00',
  z_number: 12,
  force_closed: false,
  close_note: null,
  ...over,
});

export const report = (over: Partial<ShiftReport> = {}): ShiftReport => ({
  shift_uuid: 's0000000-0000-4000-8000-000000000001',
  generated_at: '2026-10-21T19:00:00+00:00',
  period: { from: '2026-10-21T04:00:00+00:00', to: '2026-10-21T19:00:00+00:00' },
  sales: {
    bills: 14,
    received: '3200000.00',
    refunded: '50000.00',
    net: '3150000.00',
    bills_total: '3200000.00',
    subtotal: '2900000.00',
    discount: '100000.00',
    room_fee: '300000.00',
    min_spend_topup: '0.00',
    service_charge: '290000.00',
    place_fee: '0.00',
    average_bill: '228571.00',
  },
  by_tender: [
    { tender: 'cash', count: 8, amount: '1500000.00' },
    { tender: 'card', count: 5, amount: '1200000.00' },
    { tender: 'qr', count: 0, amount: '0.00' },
    { tender: 'prepayment', count: 1, amount: '200000.00' },
    { tender: 'account', count: 1, amount: '300000.00' },
  ],
  cards: [{ card_type: 'uzcard', count: 3, amount: '700000.00' }, { card_type: 'humo', count: 2, amount: '500000.00' }],
  by_channel: [{ channel: 'pos_hall', orders: 12, amount: '3000000.00' }, { channel: 'pos_takeaway', orders: 2, amount: '200000.00' }],
  by_waiter: [{ waiter_id: 9, name: 'Ofitsiant Vali', orders: 12, bills: 12, amount: '3000000.00', service_charge: '270000.00' }],
  discounts: { count: 2, amount: '100000.00' },
  voids: { count: 1, amount: '35000.00' },
  batch_writeoffs: { count: 0, items: [] },
  refunds: { count: 1, amount: '50000.00', by_method: [{ method: 'cash', count: 1, amount: '50000.00' }] },
  deposits: {
    taken: { count: 2, amount: '400000.00', cash: '150000.00' },
    applied: { count: 1, amount: '200000.00' },
    refunded: { count: 1, amount: '100000.00', cash: '100000.00' },
  },
  credit: {
    sales: { count: 1, amount: '300000.00' },
    repayments: { count: 1, amount: '80000.00', cash: '80000.00' },
  },
  cash: {
    opening: '500000.00',
    sales: '1500000.00',
    refunds: '50000.00',
    cash_in: '100000.00',
    cash_out: '200000.00',
    wages: '150000.00',
    purchases: '300000.00',
    deposits: '150000.00',
    deposit_refunds: '100000.00',
    repayments: '80000.00',
    expected: '1730000.00',
  },
  movements: [
    { uuid: 'm1', type: 'purchase', category: 'bazaar', amount: '300000.00', note: 'Go\'sht', employee: null, user: { id: 5, name: 'Kassir Ali' }, approved_by: null, created_at: '2026-10-21T08:00:00+00:00' },
  ],
  ...over,
});
