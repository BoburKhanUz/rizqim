import type { BanquetReport, CancellationQuote, PaymentRules, Schedule } from './api';

// Shared answers of the banquet payment screens' tests (vitest and the Playwright fake backend)
export const EVENT_UUID = '44444444-4444-4444-8444-444444444444';
export const DEPOSIT_UUID = '55555555-5555-4555-8555-555555555551';
export const INTERIM_UUID = '55555555-5555-4555-8555-555555555552';
export const FINAL_UUID = '55555555-5555-4555-8555-555555555553';

const row = (uuid: string, kind: 'deposit' | 'interim' | 'final', due: string, amount: string, over: Partial<Schedule['rows'][number]> = {}): Schedule['rows'][number] => ({
  uuid,
  kind,
  kind_label: kind,
  due_date: due,
  amount,
  status: 'due',
  status_label: "To'lanmagan",
  overdue: false,
  tender: null,
  card_type: null,
  transaction_id: null,
  reference: null,
  shift_id: null,
  paid_at: null,
  paid_by: null,
  fiscal: { receipt_uuid: null, status: null },
  refunded_amount: '0.00',
  retained_amount: '0.00',
  settle_receipt_uuid: null,
  note: null,
  ...over,
});

export function scheduleFixture(over: Partial<Schedule> = {}): Schedule {
  return {
    event: { uuid: EVENT_UUID, status: 'confirmed', status_label: 'Tasdiqlangan', event_date: '2027-01-20', total: '10000000.00', paid_total: '3000000.00', version: 3 },
    summary: { total: '10000000.00', paid: '3000000.00', outstanding: '7000000.00', overdue: true, overdue_amount: '4000000.00', overdue_count: 1, next_due: { date: '2026-11-10', amount: '4000000.00', kind: 'interim' } },
    rows: [
      row(DEPOSIT_UUID, 'deposit', '2026-11-01', '3000000.00', { status: 'paid', status_label: "To'langan", tender: 'cash', fiscal: { receipt_uuid: 'r1', status: 'issued' } }),
      row(INTERIM_UUID, 'interim', '2026-11-10', '4000000.00', { overdue: true }),
      row(FINAL_UUID, 'final', '2027-01-20', '3000000.00'),
    ],
    entries: [
      {
        uuid: 'e1',
        type: 'payment',
        type_label: "To'lov (avans)",
        payment_uuid: DEPOSIT_UUID,
        amount: '3000000.00',
        tender: 'cash',
        card_type: null,
        reference: null,
        business_date: '2026-11-01',
        shift_id: 4,
        user: { id: 3, name: 'Kassir' },
        approved_by: null,
        reason: null,
        transfer_pending: false,
        fiscal: { receipt_uuid: 'r1', status: 'issued' },
        original_receipt_uuid: null,
        created_at: '2026-11-01T10:00:00+05:00',
      },
    ],
    cancellation: null,
    ...over,
  };
}

export const quoteFixture = (over: Partial<CancellationQuote> = {}): CancellationQuote => ({
  days_before: 20,
  rule_percent: '30',
  paid: '3000000.00',
  computed_retained: '900000.00',
  retained: '900000.00',
  refund: '2100000.00',
  overridden: false,
  cash_refund: '2100000.00',
  transfer_refund: '0.00',
  rows: [{ payment_uuid: DEPOSIT_UUID, kind: 'deposit', tender: 'cash', amount: '3000000.00', retain: '900000.00', refund: '2100000.00', refund_path: 'cash_drawer' }],
  ...over,
});

export const rulesFixture: PaymentRules = {
  schedule_template: [
    { kind: 'deposit', percent: '30', days_before: null },
    { kind: 'interim', percent: '40', days_before: 14 },
    { kind: 'final', percent: null, days_before: 0 },
  ],
  cancellation_rules: [
    { min_days: 30, retained_percent: '0' },
    { min_days: 14, retained_percent: '30' },
    { min_days: 0, retained_percent: '100' },
  ],
  reminder_days: [3, 1],
  overdue_reminders: true,
  version: 0,
};

export const reportFixture: BanquetReport = {
  from: '2026-11-01',
  to: '2026-11-30',
  branch_id: null,
  events: 2,
  cancelled: 1,
  by_status: [],
  guests: 350,
  total: '16000000.00',
  paid: '4000000.00',
  outstanding: '13000000.00',
  overdue: { count: 1, amount: '1800000.00' },
  retained: '1000000.00',
  refunded: '0.00',
  transfer_pending: '0.00',
  rows: [
    {
      uuid: EVENT_UUID,
      event_date: '2026-11-20',
      slot: 'evening',
      event_type: 'toy',
      title: "Karimovlar to'yi",
      branch_id: 1,
      customer_name: 'Karimovlar oilasi',
      customer_phone: '+99890*****67',
      status: 'confirmed',
      status_label: 'Tasdiqlangan',
      guests_guaranteed: 200,
      guests_minimum: 200,
      guests_actual: null,
      guests: 200,
      total: '10000000.00',
      paid: '3000000.00',
      refunded: '0.00',
      retained: '0.00',
      outstanding: '7000000.00',
      overdue: '1800000.00',
      transfer_pending: '0.00',
    },
  ],
};
