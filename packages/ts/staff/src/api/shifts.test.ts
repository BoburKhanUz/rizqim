import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { fetchBranchShiftSummary, fetchShift, fetchShifts, fetchXReport } from './shifts';

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
      'GET /api/v1/pos/shifts/s1': () => ok(shift()),
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

  it('builds the summary of a branch: its open shifts with their X and the last Z', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/pos/shifts': (request) => {
        const status = new URL(request.url).searchParams.get('status');
        return ok(status === 'open' ? [shift()] : [shift({ uuid: 's0', status: 'closed', z_number: 6 })]);
      },
      'GET /api/v1/pos/shifts/s1/x-report': () => ok({ shift: shift(), report: { cash: { expected: '750000.00' } } }),
      'GET /api/v1/pos/shifts/s0': () => ok(shift({ uuid: 's0', status: 'closed', z_number: 6, summary: { cash: { expected: '900000.00' } } })),
    });
    const summary = await fetchBranchShiftSummary(testApi(), 2);
    expect(summary.open).toHaveLength(1);
    expect(summary.open[0]!.report.cash.expected).toBe('750000.00');
    expect(summary.lastClosed?.z_number).toBe(6);
    expect(calls.filter((call) => new URL(call.url).pathname === '/api/v1/pos/shifts').map((call) => new URL(call.url).searchParams.get('branch_id'))).toEqual(['2', '2']);
  });

  it('has nothing when no shift was ever opened', async () => {
    mockFetch({ 'GET /api/v1/pos/shifts': () => ok([]) });
    expect(await fetchBranchShiftSummary(testApi())).toEqual({ open: [], lastClosed: null });
  });

  it('keeps the 403 of a role without reports', async () => {
    mockFetch({ 'GET /api/v1/pos/shifts': () => json({ success: false, message: 'Ruxsat etilmagan' }, 403) });
    const error = (await fetchShifts(testApi()).catch((e: unknown) => e)) as { status: number };
    expect(error.status).toBe(403);
  });
});
