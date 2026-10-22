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

// The X report and the Z report as the server builds them (App\Domain\Pos\Shifts\ShiftReport, typed in the OpenAPI
// spec since P3-26). Amounts are decimal strings ("12000.00").
export type ShiftReport = OkData<'posShift.xReport'>['report'];
export type TenderRow = ShiftReport['by_tender'][number];

// GET /pos/shifts/{uuid}: the shift, and once it is closed its Z report (`summary`, frozen at closing; null while open)
export type ShiftDetail = OkData<'posShift.show'>;

export async function fetchShift(api: StaffApi, uuid: string): Promise<ShiftDetail> {
  return unwrap(await api.client.GET('/v1/pos/shifts/{uuid}', { params: { path: { uuid } } })).data;
}

export type XReport = OkData<'posShift.xReport'>;

// GET /pos/shifts/{uuid}/x-report: the shift's report now (a closed shift: as at its close)
export async function fetchXReport(api: StaffApi, uuid: string): Promise<XReport> {
  return unwrap(await api.client.GET('/v1/pos/shifts/{uuid}/x-report', { params: { path: { uuid } } })).data;
}

// GET /pos/shifts/current (P3-26): a normal sign-in (owner, manager) gets the open shifts of `branch_id` (default: their
// own branch; an owner without one: every branch they see), each with its X report; a terminal gets its own.
export type CurrentShifts = OkData<'posShift.current'>;

export async function fetchCurrentShifts(api: StaffApi, branchId?: number): Promise<CurrentShifts> {
  const query = branchId === undefined ? {} : { branch_id: branchId };
  return unwrap(await api.client.GET('/v1/pos/shifts/current', { params: { query } })).data;
}

// The shift summary a phone shows: the branch's open shifts (one per till) with their X report, and the last closed shift with
// its Z. P3-26: the open shifts and their X reports come in one call (GET /pos/shifts/current with the branch).
export interface BranchShiftSummary {
  open: XReport[];
  lastClosed: ShiftDetail | null;
}

// At most this many open shifts are shown (a branch has a handful of tills)
export const MAX_OPEN_SHIFTS = 5;

export async function fetchBranchShiftSummary(api: StaffApi, branchId?: number): Promise<BranchShiftSummary> {
  const scope = branchId === undefined ? {} : { branch_id: branchId };
  const [current, closedShifts] = await Promise.all([fetchCurrentShifts(api, branchId), fetchShifts(api, { ...scope, status: 'closed', limit: 1 })]);
  const closed = closedShifts[0];
  const lastClosed = closed ? await fetchShift(api, closed.uuid) : null;
  return { open: current.shifts.slice(0, MAX_OPEN_SHIFTS), lastClosed };
}
