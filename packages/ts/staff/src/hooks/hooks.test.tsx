import { cleanup, renderHook, waitFor, act } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, makeOrder, makeQueryClient, mockFetch, ok, testApi, wrapperFor } from '../test/helpers';
import { applyFinishedOrderChange, mergeOrders, refetchOrders, someOrderLeft } from '../orders/cache';
import { createStaffApi } from '../api/client';
import { setDefaultStaffApi, StaffApiProvider, useStaffApi } from './context';
import { staffKeys } from './keys';
import { useOwnerAnalytics, useOwnerFinance, useRequestPayout } from './business';
import { useAddStopListEntry, useOwnerProducts, useSaveProduct, useStopList } from './menu';
import { useActiveOrders, useCouriers, useOrder, useOrderAction, useOrderHistory, useUpdateOrderStatus } from './orders';
import { useEmployees, useInviteEmployee } from './team';
import type { Order } from '../types';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  setDefaultStaffApi(null);
});

describe('the API of the hooks', () => {
  it('comes from the provider', () => {
    const api = testApi();
    const { result } = renderHook(() => useStaffApi(), { wrapper: ({ children }) => <StaffApiProvider api={api}>{children}</StaffApiProvider> });
    expect(result.current).toBe(api);
  });

  it('comes from the default when there is no provider, and the provider wins', () => {
    const fallback = testApi();
    setDefaultStaffApi(fallback);
    expect(renderHook(() => useStaffApi()).result.current).toBe(fallback);
    const own = testApi();
    const { result } = renderHook(() => useStaffApi(), { wrapper: ({ children }) => <StaffApiProvider api={own}>{children}</StaffApiProvider> });
    expect(result.current).toBe(own);
  });

  it('is missing loudly', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(() => renderHook(() => useStaffApi())).toThrow('@rizqim/staff');
  });
});

describe('open orders', () => {
  it('loads the list with the fetch it was given, under the key of its view', async () => {
    const orders = [makeOrder(), makeOrder({ status: 'preparing' })];
    mockFetch({ 'GET /api/v1/orders': () => ok(orders, { meta: { truncated: false } }) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useActiveOrders('manager'), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toEqual({ orders, truncated: false });
    expect(queryClient.getQueryData(staffKeys.active('manager'))).toBeDefined();
  });

  it('passes the caller\'s query options on (polling, enabled)', async () => {
    const { fetchMock } = mockFetch({ 'GET /api/v1/orders': () => ok([]) });
    const { result } = renderHook(() => useActiveOrders('manager', { enabled: false }), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {});
    expect(result.current.fetchStatus).toBe('idle');
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('refetches the history of the screen when an order drops out of the open list', async () => {
    const first = makeOrder();
    const second = makeOrder();
    let calls = 0;
    const history = vi.fn(() => ok([], { meta: { next_cursor: null, per_page: 20 } }));
    mockFetch({
      'GET /api/v1/orders': (request) => {
        if (new URL(request.url).searchParams.get('scope') === 'history') return history();
        calls += 1;
        return ok(calls === 1 ? [first, second] : [second], { meta: { truncated: false } });
      },
    });
    const queryClient = makeQueryClient();
    const wrapper = wrapperFor(queryClient);
    const active = renderHook(() => useActiveOrders('manager'), { wrapper });
    const archive = renderHook(() => useOrderHistory('manager'), { wrapper });
    await waitFor(() => expect(active.result.current.isSuccess && archive.result.current.isLoading === false).toBe(true));
    expect(history).toHaveBeenCalledTimes(1);

    await act(async () => {
      await active.result.current.refetch();
    });

    await waitFor(() => expect(history).toHaveBeenCalledTimes(2));
  });

  it('finds an order of the lists for a detail view, from the open list', async () => {
    const order = makeOrder();
    mockFetch({ 'GET /api/v1/orders': () => ok([order], { meta: { truncated: false } }) });
    const { result } = renderHook(() => useOrder(order.uuid, 'manager'), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.order).toEqual(order));
    expect(renderHook(() => useOrder('nope', 'manager'), { wrapper: wrapperFor(makeQueryClient()) }).result.current.order).toBeUndefined();
  });
});

describe('the finished orders', () => {
  const finished = (n: number) => Array.from({ length: n }, () => makeOrder({ status: 'delivered' }));

  function serveHistory(pages: Order[][]) {
    return mockFetch({
      'GET /api/v1/orders': (request) => {
        const cursor = new URL(request.url).searchParams.get('cursor');
        const index = cursor ? Number(cursor) : 0;
        return ok(pages[index] ?? [], { meta: { next_cursor: index + 1 < pages.length ? String(index + 1) : null, per_page: 20 } });
      },
    });
  }

  it('pages through the history by cursor', async () => {
    const pages = [finished(2), finished(1)];
    const { calls } = serveHistory(pages);
    const { result } = renderHook(() => useOrderHistory('manager'), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.orders).toHaveLength(2));
    expect(result.current.hasNextPage).toBe(true);
    await act(async () => {
      await result.current.fetchNextPage();
    });
    await waitFor(() => expect(result.current.orders).toHaveLength(3));
    expect(result.current.hasNextPage).toBe(false);
    expect(calls.map((c) => new URL(c.url).searchParams.get('cursor'))).toEqual([null, '1']);
  });

  it('waits to be enabled', async () => {
    const { fetchMock } = serveHistory([finished(1)]);
    const { result, rerender } = renderHook(({ enabled }) => useOrderHistory('manager', { enabled }), {
      wrapper: wrapperFor(makeQueryClient()),
      initialProps: { enabled: false },
    });
    await act(async () => {});
    expect(fetchMock).not.toHaveBeenCalled();
    rerender({ enabled: true });
    await waitFor(() => expect(result.current.orders).toHaveLength(1));
  });

  it('keeps loading older pages until the orders are older than `since`, up to the page cap', async () => {
    const day = (iso: string) => makeOrder({ status: 'delivered', created_at: iso });
    const pages = [[day('2026-10-20T10:00:00Z')], [day('2026-10-19T10:00:00Z')], [day('2026-10-10T10:00:00Z')], [day('2026-10-01T10:00:00Z')]];
    const { calls } = serveHistory(pages);
    const since = Date.parse('2026-10-19T00:00:00Z');
    const { result } = renderHook(() => useOrderHistory('courier', { since }), { wrapper: wrapperFor(makeQueryClient()) });
    // page 3 is older than since minus the two-day margin? 2026-10-10 < 2026-10-17: it is, so loading stops there
    await waitFor(() => expect(result.current.orders).toHaveLength(3));
    await act(async () => {});
    expect(calls).toHaveLength(3);
    expect(result.current.hasNextPage).toBe(true);
  });

  it('stops at maxAutoPages', async () => {
    const pages = Array.from({ length: 6 }, () => [makeOrder({ status: 'delivered', created_at: '2026-10-20T10:00:00Z' })]);
    const { calls } = serveHistory(pages);
    const { result } = renderHook(() => useOrderHistory('courier', { since: Date.parse('2026-01-01T00:00:00Z'), maxAutoPages: 2 }), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.orders).toHaveLength(2));
    await act(async () => {});
    expect(calls).toHaveLength(2);
  });

  it('reads pages some other way when asked to', async () => {
    const fetchPage = vi.fn(async () => ({ orders: finished(1), nextCursor: null }));
    const { result } = renderHook(() => useOrderHistory('customer', { fetchPage, pageSize: 7 }), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.orders).toHaveLength(1));
    expect(fetchPage).toHaveBeenCalledWith(null, 7);
  });
});

describe('changing orders', () => {
  it('sends the change and refetches every order list, then runs the caller\'s onSuccess', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/orders/o-1/status': () => ok(makeOrder({ status: 'accepted' })) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const onSuccess = vi.fn();
    const { result } = renderHook(() => useUpdateOrderStatus({ onSuccess }), { wrapper: wrapperFor(queryClient) });

    await act(async () => {
      await result.current.mutateAsync({ uuid: 'o-1', status: 'accepted' });
    });

    expect(await calls[0]!.json()).toEqual({ status: 'accepted' });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.orders });
    expect(onSuccess).toHaveBeenCalledTimes(1);
  });

  it('reports the server\'s refusal and does not refetch', async () => {
    mockFetch({ 'POST /api/v1/orders/o-1/status': () => json({ success: false, message: 'Mumkin emas' }, 422) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const onError = vi.fn();
    const { result } = renderHook(() => useUpdateOrderStatus({ onError }), { wrapper: wrapperFor(queryClient) });

    await act(async () => {
      await result.current.mutateAsync({ uuid: 'o-1', status: 'ready' }).catch(() => {});
    });

    expect(onError).toHaveBeenCalledTimes(1);
    expect((onError.mock.calls[0]![0] as Error).message).toBe('Mumkin emas');
    expect(invalidate).not.toHaveBeenCalled();
  });

  it('performs an action on its own endpoint', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/orders/o-1/complete': () => ok(makeOrder({ status: 'delivered' })) });
    const { result } = renderHook(() => useOrderAction(), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync({ uuid: 'o-1', action: { to: 'delivered', via: 'complete' } });
    });
    expect(calls).toHaveLength(1);
  });

  it('lists the couriers', async () => {
    mockFetch({ 'GET /api/v1/couriers': () => ok([{ id: 1, name: 'Ali (Kuryer)', phone: null, telegram_username: null, branch_id: 1, courier_type: 'own' }]) });
    const { result } = renderHook(() => useCouriers(), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.data).toHaveLength(1));
  });
});

describe('the order cache helpers', () => {
  it('sees an order leave the list', () => {
    const a = makeOrder();
    const b = makeOrder();
    expect(someOrderLeft([a, b], [b])).toBe(true);
    expect(someOrderLeft([a], [a, b])).toBe(false);
  });

  it('merges the lists, the finished version winning', () => {
    const open = { ...makeOrder({ status: 'ready' }), id: 1 };
    const done = { ...open, status: 'delivered' as const };
    expect(mergeOrders([open], [done])).toEqual([done]);
  });

  it('patches a finished order in the cached history without a request', () => {
    const queryClient = makeQueryClient();
    const order = makeOrder({ status: 'delivered', updated_at: '2026-10-20T10:00:00Z' });
    const key = [...staffKeys.history('manager'), 20];
    queryClient.setQueryData(key, { pages: [{ orders: [order], nextCursor: null }], pageParams: [null] });

    expect(applyFinishedOrderChange(queryClient, { uuid: order.uuid, status: 'refunded', updated_at: '2026-10-20T11:00:00Z' })).toBe(true);

    const data = queryClient.getQueryData<{ pages: Array<{ orders: Order[] }> }>(key)!;
    expect(data.pages[0]!.orders[0]).toMatchObject({ status: 'refunded', updated_at: '2026-10-20T11:00:00Z' });
    // an order that has only just finished is the open list's business; an unfinished status is ignored
    expect(applyFinishedOrderChange(queryClient, { uuid: order.uuid, status: 'preparing' })).toBe(false);
    expect(applyFinishedOrderChange(queryClient, { uuid: 'other', status: 'refunded' })).toBe(false);
  });

  it('refetches once more when a refetch was already running', async () => {
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries').mockResolvedValue();
    vi.spyOn(queryClient, 'isFetching').mockReturnValue(1);
    await refetchOrders(queryClient, staffKeys.allActive);
    expect(invalidate).toHaveBeenCalledTimes(2);
  });
});

describe('menu, team, payments and analytics hooks', () => {
  it('loads the products', async () => {
    mockFetch({ 'GET /api/v1/owner/products': () => ok([{ id: 1, name: 'Plov' }]) });
    const { result } = renderHook(() => useOwnerProducts(), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.data).toHaveLength(1));
  });

  it('saves a product and refetches the list', async () => {
    mockFetch({ 'POST /api/v1/owner/products': () => ok({ id: 2 }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useSaveProduct(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ form: new FormData() });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.products });
  });

  it('reads the stop-list and refetches it with the availability after an entry is added', async () => {
    mockFetch({
      'GET /api/v1/owner/stop-list': () => ok({ branch_id: 1, entries: [] }),
      'POST /api/v1/owner/stop-list': () => ok({ id: 1 }),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const wrapper = wrapperFor(queryClient);
    const list = renderHook(() => useStopList(1), { wrapper });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const add = renderHook(() => useAddStopListEntry(), { wrapper });
    await act(async () => {
      await add.result.current.mutateAsync({ branch_id: 1, type: 'product', id: 3 });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.allStopList });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['menu-availability'] });
  });

  it('lists the employees and refetches them after an invite', async () => {
    mockFetch({
      'GET /api/v1/owner/employees': () => ok([]),
      'POST /api/v1/owner/employees/invite': () => ok({ employee: { id: 1 }, invite_link: 'l' }),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const wrapper = wrapperFor(queryClient);
    const list = renderHook(() => useEmployees(), { wrapper });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const invite = renderHook(() => useInviteEmployee(), { wrapper });
    await act(async () => {
      await invite.result.current.mutateAsync({ role: 'courier', name: 'Ali', branchId: 1 });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.employees });
  });

  it('reads the finance (with the screen\'s polling options) and refetches it after a payout', async () => {
    mockFetch({
      'GET /api/v1/owner/finance': () => ok({ debt_balance: 0, wallet_balance: 1 }),
      'POST /api/v1/owner/payouts': () => ok({ id: 1 }),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const wrapper = wrapperFor(queryClient);
    const finance = renderHook(() => useOwnerFinance({ staleTime: 10_000 }), { wrapper });
    await waitFor(() => expect(finance.result.current.data?.wallet_balance).toBe(1));
    const payout = renderHook(() => useRequestPayout(), { wrapper });
    await act(async () => {
      await payout.result.current.mutateAsync({ amount: 1000, card_number: '8600' });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.finance });
  });

  it('does not ask for a custom range the server would refuse, and says why', async () => {
    const { fetchMock } = mockFetch({ 'GET /api/v1/owner/analytics': () => ok({}) });
    const { result, rerender } = renderHook(({ end }) => useOwnerAnalytics('custom', { start: '2026-10-08', end }), {
      wrapper: wrapperFor(makeQueryClient()),
      initialProps: { end: '2026-10-07' },
    });
    await act(async () => {});
    expect(result.current.rangeProblem).toBe('reversed');
    expect(fetchMock).not.toHaveBeenCalled();
    rerender({ end: '2026-10-10' });
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    expect(result.current.rangeProblem).toBeNull();
  });

  it('keeps one cache entry per period', async () => {
    mockFetch({ 'GET /api/v1/owner/analytics': () => ok({ summary: {} }) });
    const queryClient = makeQueryClient();
    const wrapper = wrapperFor(queryClient);
    const week = renderHook(() => useOwnerAnalytics('weekly'), { wrapper });
    const month = renderHook(() => useOwnerAnalytics('monthly'), { wrapper });
    await waitFor(() => expect(week.result.current.isSuccess && month.result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(staffKeys.analyticsPeriod('week'))).toBeDefined();
    expect(queryClient.getQueryData(staffKeys.analyticsPeriod('month'))).toBeDefined();
  });

  it('works with an API made by createStaffApi and a signing fetch', async () => {
    const sent: string[] = [];
    const api = createStaffApi({
      baseUrl: 'http://api.test/api',
      fetch: async (request) => {
        sent.push(request.headers.get('X-Marker') ?? '');
        return ok([]);
      },
      headers: { 'X-Marker': 'staff' },
    });
    const { result } = renderHook(() => useOwnerProducts(), { wrapper: wrapperFor(makeQueryClient(), api) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(sent).toEqual(['staff']);
  });
});
