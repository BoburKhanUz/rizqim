import { useQuery } from '@tanstack/react-query';
import {
  fetchBranchShiftSummary,
  fetchShift,
  fetchShifts,
  fetchXReport,
  type BranchShiftSummary,
  type Shift,
  type ShiftDetail,
  type ShiftsQuery,
  type XReport,
} from '../api/shifts';
import { useStaffApi } from './context';
import type { QueryExtras } from './orders';

// Shifts with their X and Z reports (P3-18), read-only. A shift changes at the till (`shift.changed` on the branch channel);
// the screens poll or refetch, `options` carries that.
export const shiftKeys = {
  all: ['pos', 'shifts'] as const,
  list: (query: ShiftsQuery) => ['pos', 'shifts', 'list', query] as const,
  detail: (uuid: string) => ['pos', 'shifts', 'detail', uuid] as const,
  xReport: (uuid: string) => ['pos', 'shifts', 'x-report', uuid] as const,
  summary: (branchId?: number) => ['pos', 'shifts', 'summary', branchId ?? null] as const,
};

export function useShifts(query: ShiftsQuery = {}, options: QueryExtras<Shift[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: shiftKeys.list(query), queryFn: () => fetchShifts(api, query) });
}

// The shift with its Z report (once closed)
export function useShift(uuid: string | null, options: QueryExtras<ShiftDetail> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: shiftKeys.detail(uuid ?? ''),
    queryFn: () => fetchShift(api, uuid as string),
  });
}

export function useXReport(uuid: string | null, options: QueryExtras<XReport> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: shiftKeys.xReport(uuid ?? ''),
    queryFn: () => fetchXReport(api, uuid as string),
  });
}

// "Smena xulosasi" of a phone: the branch's open shifts with their X and the last Z, in one query
export function useBranchShiftSummary(branchId?: number, options: QueryExtras<BranchShiftSummary> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: shiftKeys.summary(branchId), queryFn: () => fetchBranchShiftSummary(api, branchId) });
}
