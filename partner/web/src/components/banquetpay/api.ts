import { StaffApiError, unwrap, type StaffApi } from '@rizqim/staff';

// The banquet payment schedule of P6-53 (docs/API.md "Tadbirlar: to'lov jadvali"): an event's rows, the manager's bank
// transfer, the cancellation with its refund and retained parts, the owner's payment rules and the "Tadbirlar" report.
// Called through the generated client's untyped door and read into the types below, so these screens do not wait for the
// spec to be regenerated; the server does every sum. Payments in cash, card or QR are taken at the till only.

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
  headers?: Record<string, string>;
  parseAs?: 'blob';
}
interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string, init?: Init): Promise<Result>;
  POST(path: string, init?: Init): Promise<Result>;
  PUT(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export type PaymentKind = 'deposit' | 'interim' | 'final';
export const PAYMENT_KINDS: readonly PaymentKind[] = ['deposit', 'interim', 'final'];
export type PaymentStatus = 'due' | 'paid' | 'refunded' | 'retained';
export type EntryType = 'payment' | 'transfer' | 'refund' | 'retain';
export type Tender = 'cash' | 'card' | 'qr' | 'bank_transfer';
export type EventStatus = 'hold' | 'confirmed' | 'in_progress' | 'settled' | 'cancelled';

export interface ScheduleRow {
  uuid: string;
  kind: PaymentKind;
  kind_label: string;
  due_date: string | null;
  amount: string;
  status: PaymentStatus;
  status_label: string;
  overdue: boolean;
  // once paid
  tender: Tender | null;
  card_type: string | null;
  transaction_id: string | null;
  reference: string | null;
  shift_id: number | null;
  paid_at: string | null;
  paid_by: { id: number; name: string | null } | null;
  fiscal: { receipt_uuid: string | null; status: string | null };
  refunded_amount: string;
  retained_amount: string;
  settle_receipt_uuid: string | null;
  note: string | null;
}

export interface ScheduleEntry {
  uuid: string;
  type: EntryType;
  type_label: string;
  payment_uuid: string | null;
  amount: string;
  tender: Tender;
  card_type: string | null;
  reference: string | null;
  business_date: string | null;
  shift_id: number | null;
  user: { id: number; name: string | null } | null;
  approved_by: number | null;
  reason: string | null;
  // A bank transfer refund the store still has to send back by transfer
  transfer_pending: boolean;
  fiscal: { receipt_uuid: string | null; status: string | null };
  original_receipt_uuid: string | null;
  created_at: string | null;
}

export interface ScheduleSummary {
  total: string;
  paid: string;
  outstanding: string;
  overdue: boolean;
  overdue_amount: string;
  overdue_count: number;
  next_due: { date: string; amount: string; kind: PaymentKind } | null;
}

export interface Cancellation {
  days_before: number;
  rule_percent: string;
  paid: string;
  computed_retained: string;
  retained: string;
  refund: string;
  override_reason: string | null;
  reason: string | null;
  business_date: string | null;
  cancelled_by: number | null;
  created_at: string | null;
}

export interface Schedule {
  event: { uuid: string; status: EventStatus; status_label: string; event_date: string; total: string; paid_total: string; version: number };
  summary: ScheduleSummary;
  rows: ScheduleRow[];
  entries: ScheduleEntry[];
  cancellation: Cancellation | null;
}

export type RefundPath = 'cash_drawer' | 'card' | 'qr' | 'bank_transfer';

export interface CancellationQuote {
  days_before: number;
  rule_percent: string;
  paid: string;
  computed_retained: string;
  retained: string;
  refund: string;
  overridden: boolean;
  cash_refund: string;
  transfer_refund: string;
  rows: { payment_uuid: string; kind: PaymentKind; tender: string | null; amount: string; retain: string; refund: string; refund_path: RefundPath | null }[];
}

export interface TemplateRow {
  kind: PaymentKind;
  // null: the rest (the final row)
  percent: string | null;
  // null: at booking (the deposit)
  days_before: number | null;
}

export interface CancellationRule {
  min_days: number;
  retained_percent: string;
}

export interface PaymentRules {
  schedule_template: TemplateRow[];
  cancellation_rules: CancellationRule[];
  reminder_days: number[];
  overdue_reminders: boolean;
  // 0: the defaults (nothing saved yet)
  version: number;
}

export interface ReportEvent {
  uuid: string;
  event_date: string;
  slot: string;
  event_type: string;
  title: string | null;
  branch_id: number;
  customer_name: string;
  // Masked for a report-only reader
  customer_phone: string | null;
  status: EventStatus;
  status_label: string;
  guests_guaranteed: number;
  guests_minimum: number;
  guests_actual: number | null;
  guests: number;
  total: string;
  paid: string;
  refunded: string;
  retained: string;
  outstanding: string;
  overdue: string;
  transfer_pending: string;
}

export interface BanquetReport {
  from: string;
  to: string;
  branch_id: number | null;
  events: number;
  cancelled: number;
  by_status: { status: EventStatus; count: number }[];
  guests: number;
  total: string;
  paid: string;
  outstanding: string;
  overdue: { count: number; amount: string };
  retained: string;
  refunded: string;
  transfer_pending: string;
  rows: ReportEvent[];
}

export interface ReportRange {
  from?: string;
  to?: string;
  branch_id?: number;
}

export interface ScheduleRowInput {
  uuid?: string | null;
  kind: PaymentKind;
  due_date: string;
  amount: string;
}

export const fetchSchedule = (api: StaffApi, uuid: string) => data<Schedule>(door(api).GET(`/v1/pos/banquets/${uuid}/payments`));

export const saveSchedule = (api: StaffApi, uuid: string, rows: ScheduleRowInput[], version: number, key: string) =>
  data<Schedule>(door(api).PUT(`/v1/pos/banquets/${uuid}/payments`, { body: { rows, version }, headers: { 'Idempotency-Key': key } }));

export const recordTransfer = (api: StaffApi, input: { uuid: string; row: string; reference: string; paid_on?: string; amount?: string; key: string }) =>
  data<{ schedule: Schedule; entry: ScheduleEntry }>(
    door(api).POST(`/v1/pos/banquets/${input.uuid}/payments/${input.row}/bank-transfer`, {
      body: { reference: input.reference, ...(input.paid_on ? { paid_on: input.paid_on } : {}), ...(input.amount ? { amount: input.amount } : {}) },
      headers: { 'Idempotency-Key': input.key },
    }),
  );

export const fetchCancellationQuote = (api: StaffApi, uuid: string, retained?: string) =>
  data<CancellationQuote>(door(api).GET(`/v1/pos/banquets/${uuid}/cancellation`, { params: { query: retained !== undefined && retained !== '' ? { retained } : {} } }));

export const cancelEvent = (api: StaffApi, input: { uuid: string; reason: string; retained?: string; override_reason?: string; key: string }) =>
  data<Schedule>(
    door(api).POST(`/v1/pos/banquets/${input.uuid}/cancellation`, {
      body: {
        reason: input.reason,
        ...(input.retained !== undefined && input.retained !== '' ? { retained: input.retained } : {}),
        ...(input.override_reason ? { override_reason: input.override_reason } : {}),
      },
      headers: { 'Idempotency-Key': input.key },
    }),
  );

export const fetchPaymentRules = (api: StaffApi) => data<PaymentRules>(door(api).GET('/v1/owner/pos/banquets/payment-settings'));

export const savePaymentRules = (api: StaffApi, rules: Omit<PaymentRules, 'version'> & { version: number }) =>
  data<PaymentRules>(door(api).PUT('/v1/owner/pos/banquets/payment-settings', { body: rules }));

export const fetchBanquetReport = (api: StaffApi, range: ReportRange) => data<BanquetReport>(door(api).GET('/v1/owner/pos/banquets/report', { params: { query: { ...range } } }));

function filenameOf(header: string | null): string | undefined {
  if (!header) return undefined;
  const encoded = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded.trim());
    } catch {
      // the plain name below
    }
  }
  return /filename\s*=\s*"?([^";]+)"?/i.exec(header)?.[1]?.trim() || undefined;
}

// GET /owner/pos/banquets/export: the .xlsx ("Tadbirlar" and "Jami") with the session's token
export async function downloadBanquetReport(api: StaffApi, range: ReportRange): Promise<{ blob: Blob; filename: string }> {
  const result = await door(api).GET('/v1/owner/pos/banquets/export', { params: { query: { ...range } }, parseAs: 'blob' });
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  return { blob: result.data as Blob, filename: filenameOf(result.response.headers.get('Content-Disposition')) ?? `tadbirlar-${range.from ?? ''}-${range.to ?? ''}.xlsx` };
}
