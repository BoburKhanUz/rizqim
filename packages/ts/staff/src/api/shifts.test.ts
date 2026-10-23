import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { fetchBranchShiftSummary, fetchCurrentShifts, fetchShift, fetchShifts, fetchXReport } from './shifts';

afterEach(() => vi.unstubAllGlobals());

const shift = (over: Record<string, unknown> = {}) => ({ uuid: 's1', status: 'open', branch_id: 2, business_date: '2026-10-22', opening_cash: '500000.00', z_number: null, ...over });

describe('shifts', () => {
  it('lists the shifts of a branch with the filters', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/shifts': () => ok([shift()]) });
    expect(await fetchShifts(testApi(), { branch_id: 2, status: 'closed', from: '2026-10-01', to: '2026-10-22', limit: 20 })).toHaveLength(1);
    expect(Object.fromEntries(new URL(calls[0]!.url).searchParams)).toEqual({ branch_id: '2', status: 'closed', from: '2026-10-01', to: '2026-10-22', limit: '20' });
  });

  it('reads a shift with its Z summary, null while it is open', async () => {
    mockFetch({
      'GET /api/v1/pos/shifts/s1': () => ok(shift({ summary: null })),
      'GET /api/v1/pos/shifts/s2': () => ok(shift({ uuid: 's2', status: 'closed', z_number: 7, summary: { sales: { net: '1000000.00' } } })),
    });
    const api = testApi();
    expect((await fetchShift(api, 's1')).summary).toBeNull();
    const closed = await fetchShift(api, 's2');
    expect(closed.z_number).toBe(7);
    expect(closed.summary?.sales.net).toBe('1000000.00');
  });

  it('reads the X report', async () => {
    mockFetch({ 'GET /api/v1/pos/shifts/s1/x-report': () => ok({ shift: shift(), report: { cash: { expected: '750000.00' } } }) });
    expect((await fetchXReport(testApi(), 's1')).report.cash.expected).toBe('750000.00');
  });

  it('reads the open shifts of a branch with their X reports in one call', async () => {
    const current = { shift: shift(), report: { cash: { expected: '750000.00' } }, shifts: [{ shift: shift(), report: { cash: { expected: '750000.00' } } }] };
    const { calls } = mockFetch({ 'GET /api/v1/pos/shifts/current': () => ok(current) });
    const api = testApi();
    expect((await fetchCurrentShifts(api, 2)).shifts).toHaveLength(1);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('2');
    await fetchCurrentShifts(api);
    expect(new URL(calls[1]!.url).searchParams.has('branch_id')).toBe(false);
  });

  it('builds the summary of a branch: its open shifts with their X and the last Z', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/pos/shifts/current': () => ok({ shift: shift(), report: { cash: { expected: '750000.00' } }, shifts: [{ shift: shift(), report: { cash: { expected: '750000.00' } } }] }),
      'GET /api/v1/pos/shifts': () => ok([shift({ uuid: 's0', status: 'closed', z_number: 6 })]),
      'GET /api/v1/pos/shifts/s0': () => ok(shift({ uuid: 's0', status: 'closed', z_number: 6, summary: { cash: { expected: '900000.00' } } })),
    });
    const summary = await fetchBranchShiftSummary(testApi(), 2);
    expect(summary.open).toHaveLength(1);
    expect(summary.open[0]!.report.cash.expected).toBe('750000.00');
    expect(summary.lastClosed?.z_number).toBe(6);
    const listed = calls.filter((call) => ['/api/v1/pos/shifts', '/api/v1/pos/shifts/current'].includes(new URL(call.url).pathname));
    expect(listed.map((call) => new URL(call.url).searchParams.get('branch_id'))).toEqual(['2', '2']);
    expect(new URL(listed.find((call) => new URL(call.url).pathname === '/api/v1/pos/shifts')!.url).searchParams.get('status')).toBe('closed');
  });

  it('has nothing when no shift was ever opened', async () => {
    mockFetch({ 'GET /api/v1/pos/shifts': () => ok([]), 'GET /api/v1/pos/shifts/current': () => ok({ shift: null, report: null, shifts: [] }) });
    expect(await fetchBranchShiftSummary(testApi())).toEqual({ open: [], lastClosed: null });
  });

  it('keeps the 403 of a role without reports', async () => {
    mockFetch({ 'GET /api/v1/pos/shifts': () => json({ success: false, message: 'Ruxsat etilmagan' }, 403) });
    const error = (await fetchShifts(testApi()).catch((e: unknown) => e)) as { status: number };
    expect(error.status).toBe(403);
  });
});
