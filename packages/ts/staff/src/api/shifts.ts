import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { OkData } from './types';

// Shifts, the X report (a shift now) and the Z report (a shift as it was closed), P3-18 (docs/API.md "Smena, kassa, X va Z").
// The routes are the POS ones (/pos/shifts) and need `pos.close_shift` or `reports.view`: the web panel and the Mini App read
// them with a normal owner or manager sign-in. A shift is opened and closed at the till, never here. Every figure is computed
// by the server (App\Domain\Pos\Shifts\ShiftReport): nothing is added up on the screen.

export type Shift = OkData<'posShift.index'>[number];
export type ShiftStatus = 'open' | 'closed';

export interface ShiftsQuery {
  branch_id?: number;
  status?: ShiftStatus;
  // Business days (YYYY-MM-DD); a business day starts at the branch's `business_day_starts_at`, not at midnight
  from?: string;
  to?: string;
  // 1-200, 50 by default
  limit?: number;
}

// GET /pos/shifts: newest first
export async function fetchShifts(api: StaffApi, query: ShiftsQuery = {}): Promise<Shift[]> {
  const body = unwrap(await api.client.GET('/v1/pos/shifts', { params: { query } }));
  return body.data ?? [];
}

// The report the server builds is typed `array<string, mixed>` in the OpenAPI spec (and `summary` of the shift detail is
// missing from it), so its shape is written out here from ShiftReport::build(). Amounts are decimal strings ("12000.00").
export interface TenderRow {
  tender: string;
  count: number;
  amount: string;
}

export interface ShiftReport {
  shift_uuid: string;
  generated_at: string;
  period: { from: string; to: string | null };
  sales: {
    bills: number;
    received: string;
    refunded: string;
    net: string;
    bills_total: string;
    subtotal: string;
    discount: string;
    room_fee: string;
    min_spend_topup: string;
    service_charge: string;
    place_fee: string;
    average_bill: string;
  };
  by_tender: TenderRow[];
  cards: Array<{ card_type: string; count: number; amount: string }>;
  by_channel: Array<{ channel: string; orders: number; amount: string }>;
  by_waiter: Array<{ waiter_id: number | null; name: string | null; orders: number; bills: number; amount: string; service_charge: string }>;
  discounts: { count: number; amount: string };
  voids: { count: number; amount: string };
  refunds: { count: number; amount: string; by_method: Array<{ method: string; count: number; amount: string }> };
  deposits: {
    taken: { count: number; amount: string; cash: string };
    applied: { count: number; amount: string };
    refunded: { count: number; amount: string; cash: string };
  };
  credit: {
    sales: { count: number; amount: string };
    repayments: { count: number; amount: string; cash: string };
  };
  cash: {
    opening: string;
    sales: string;
    refunds: string;
    cash_in: string;
    cash_out: string;
    wages: string;
    purchases: string;
    deposits: string;
    deposit_refunds: string;
    repayments: string;
    expected: string;
  };
  movements: Array<{
    uuid: string;
    type: string;
    category: string | null;
    amount: string;
    note: string | null;
    employee: { id: number; name: string | null } | null;
    user: { id: number; name: string | null } | null;
    approved_by: number | null;
    created_at: string | null;
  }>;
}

// GET /pos/shifts/{uuid}: the shift, and once it is closed its Z report (`summary`, frozen at closing; null while open)
export type ShiftDetail = Shift & { summary: ShiftReport | null };

export async function fetchShift(api: StaffApi, uuid: string): Promise<ShiftDetail> {
  const body = unwrap(await api.client.GET('/v1/pos/shifts/{uuid}', { params: { path: { uuid } } }));
  const data = body.data as Shift & { summary?: ShiftReport | null };
  return { ...data, summary: data.summary ?? null };
}

export interface XReport {
  shift: Shift;
  report: ShiftReport;
}

// GET /pos/shifts/{uuid}/x-report: the shift's report now (a closed shift: as at its close)
export async function fetchXReport(api: StaffApi, uuid: string): Promise<XReport> {
  const body = unwrap(await api.client.GET('/v1/pos/shifts/{uuid}/x-report', { params: { path: { uuid } } }));
  return body.data as unknown as XReport;
}

// The shift summary a phone shows: the branch's open shifts (one per till) with their X report, and the last closed shift with
// its Z. A normal sign-in has no terminal, so GET /pos/shifts/current always answers null for it; the shifts are found
// through the list instead.
export interface BranchShiftSummary {
  open: XReport[];
  lastClosed: ShiftDetail | null;
}

// At most this many open shifts get their X report (a branch has a handful of tills)
export const MAX_OPEN_SHIFTS = 5;

export async function fetchBranchShiftSummary(api: StaffApi, branchId?: number): Promise<BranchShiftSummary> {
  const scope = branchId === undefined ? {} : { branch_id: branchId };
  const [openShifts, closedShifts] = await Promise.all([
    fetchShifts(api, { ...scope, status: 'open', limit: MAX_OPEN_SHIFTS }),
    fetchShifts(api, { ...scope, status: 'closed', limit: 1 }),
  ]);
  const closed = closedShifts[0];
  const [open, lastClosed] = await Promise.all([Promise.all(openShifts.map((shift) => fetchXReport(api, shift.uuid))), closed ? fetchShift(api, closed.uuid) : null]);
  return { open, lastClosed };
}
