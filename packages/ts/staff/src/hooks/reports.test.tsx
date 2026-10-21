import { cleanup, renderHook, waitFor, act } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { reportKeys, useDownloadReport, useLedger, usePayments, usePayouts, useSalesReport, useTopProducts, useWalletHistory } from './reports';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

const page = (rows: unknown[], current = 1) => ok({ current_page: current, data: rows, last_page: 2, per_page: 25, total: 30, from: 1, to: 25 });

describe('report hooks', () => {
  it('load a sales report under a key of the query, and wait while there is no period', async () => {
    const { fetchMock } = mockFetch({ 'GET /api/v1/owner/reports/sales': () => ok({ summary: { orders_count: 5, revenue: '1000.00' }, series: [] }) });
    const queryClient = makeQueryClient();
    const wrapper = wrapperFor(queryClient);

    const idle = renderHook(() => useSalesReport(null), { wrapper });
    await act(async () => {});
    expect(idle.result.current.fetchStatus).toBe('idle');
    expect(fetchMock).not.toHaveBeenCalled();

    const query = { from: '2026-10-01', to: '2026-10-07', group_by: 'day' } as const;
    const { result } = renderHook(() => useSalesReport(query), { wrapper });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.summary.revenue).toBe('1000.00');
    expect(queryClient.getQueryData(reportKeys.sales(query))).toBeDefined();
  });

  it('load the best sellers', async () => {
    mockFetch({ 'GET /api/v1/owner/reports/top-products': () => ok({ products: [{ name: 'Osh', quantity: 2, revenue: '60000.00', orders: 2 }] }) });
    const { result } = renderHook(() => useTopProducts({ from: '2026-10-01', to: '2026-10-07', sort: 'revenue' }), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.products[0]?.name).toBe('Osh');
  });

  it('keep the previous page on screen while the next one loads', async () => {
    let release: () => void = () => {};
    mockFetch({
      'GET /api/v1/owner/finance/ledger': async (request) => {
        const requested = new URL(request.url).searchParams.get('page');
        if (requested === '2') await new Promise<void>((resolve) => (release = resolve));
        return page([{ id: requested === '2' ? 2 : 1 }], Number(requested));
      },
    });
    const wrapper = wrapperFor(makeQueryClient());
    const { result, rerender } = renderHook(({ n }) => useLedger({ page: n }), { wrapper, initialProps: { n: 1 } });
    await waitFor(() => expect(result.current.data?.data[0]).toMatchObject({ id: 1 }));
    rerender({ n: 2 });
    await waitFor(() => expect(result.current.isPlaceholderData).toBe(true));
    expect(result.current.data?.data[0]).toMatchObject({ id: 1 });
    await act(async () => release());
    await waitFor(() => expect(result.current.data?.data[0]).toMatchObject({ id: 2 }));
  });

  it('load the wallet, the payouts and the payments', async () => {
    mockFetch({
      'GET /api/v1/owner/finance/wallet': () => page([{ id: 1 }]),
      'GET /api/v1/owner/payouts': () => page([{ id: 2, card: '8600 **** **** 9012' }]),
      'GET /api/v1/owner/payments': () => page([{ uuid: 'p' }]),
    });
    const wrapper = wrapperFor(makeQueryClient());
    const wallet = renderHook(() => useWalletHistory(), { wrapper });
    const payouts = renderHook(() => usePayouts({ status: 'pending' }), { wrapper });
    const payments = renderHook(() => usePayments({ provider: 'click' }), { wrapper });
    await waitFor(() => expect([wallet, payouts, payments].every((hook) => hook.result.current.isSuccess)).toBe(true));
    expect(payouts.result.current.data?.data[0]).toMatchObject({ card: '8600 **** **** 9012' });
  });

  it('download a CSV as a mutation and surface the refusal', async () => {
    let status = 200;
    mockFetch({
      'GET /api/v1/owner/reports/export': () =>
        status === 200
          ? new Response('a,b', { headers: { 'Content-Disposition': 'attachment; filename=sales.csv' } })
          : new Response(JSON.stringify({ success: false, message: 'Ko\'p', data: { reason: 'too_many_rows', limit: 50000 } }), { status, headers: { 'Content-Type': 'application/json' } }),
    });
    const { result } = renderHook(() => useDownloadReport(), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync({ report: 'sales', from: '2026-10-01', to: '2026-10-07' });
    });
    await waitFor(() => expect(result.current.data?.filename).toBe('sales.csv'));
    status = 422;
    await act(async () => {
      await result.current.mutateAsync({ report: 'orders', from: '2026-10-01', to: '2026-10-07' }).catch(() => undefined);
    });
    await waitFor(() => expect(result.current.error).toMatchObject({ tooManyRows: true, limit: 50000 }));
  });
});
