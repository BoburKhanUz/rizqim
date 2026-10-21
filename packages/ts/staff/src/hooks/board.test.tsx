import { act, cleanup, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeOrder, makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { makeStaffOrder, makeStaffOrderDetail } from '../test/board';
import { boardKeys, useBoardAction, useBranchList, useStaffOrder, useStaffOrderPages, useStaffOrders } from './board';
import { useOrder } from './orders';
import { staffKeys } from './keys';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('board hooks', () => {
  it('loads a filter under its own key', async () => {
    const order = makeStaffOrder();
    mockFetch({ 'GET /api/v1/staff/orders': () => ok([order], { meta: { next_cursor: null, per_page: 100 } }) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useStaffOrders({ scope: 'active', branch_id: 2 }), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.data).toEqual({ orders: [order], truncated: false }));
    expect(queryClient.getQueryData(boardKeys.list({ scope: 'active', branch_id: 2 }))).toBeDefined();
  });

  it('pages the finished orders with the cursor', async () => {
    const [a, b] = [makeStaffOrder({ status: 'delivered' }), makeStaffOrder({ status: 'delivered' })];
    mockFetch({
      'GET /api/v1/staff/orders': (request) =>
        new URL(request.url).searchParams.get('cursor') ? ok([b], { meta: { next_cursor: null, per_page: 1 } }) : ok([a], { meta: { next_cursor: 'n', per_page: 1 } }),
    });
    const { result } = renderHook(() => useStaffOrderPages({ scope: 'history' }), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.hasNextPage).toBe(true));
    await act(async () => {
      await result.current.fetchNextPage();
    });
    await waitFor(() => expect(result.current.data?.pages.flatMap((page) => page.orders)).toEqual([a, b]));
    expect(result.current.hasNextPage).toBe(false);
  });

  it('loads one order, and stays idle without a uuid', async () => {
    const detail = makeStaffOrderDetail();
    const { fetchMock } = mockFetch({ [`GET /api/v1/staff/orders/${detail.uuid}`]: () => ok(detail) });
    const { result } = renderHook(() => useStaffOrder(detail.uuid), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.data).toEqual(detail));
    renderHook(() => useStaffOrder(undefined), { wrapper: wrapperFor(makeQueryClient()) });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('refetches the board and the older lists after an action', async () => {
    const order = makeStaffOrder();
    mockFetch({ [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => ok({ ...order, status: 'accepted' }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const onSuccess = vi.fn();
    const { result } = renderHook(() => useBoardAction({ onSuccess }), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ uuid: order.uuid, action: { to: 'accepted', via: 'status' } });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: boardKeys.all });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.orders });
    expect(onSuccess).toHaveBeenCalled();
  });

  it('lists the branches', async () => {
    mockFetch({ 'GET /api/v1/owner/branches': () => ok([{ id: 3, name: 'Yunusobod' }]) });
    const { result } = renderHook(() => useBranchList(), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.data).toEqual([{ id: 3, name: 'Yunusobod' }]));
  });
});

describe('useOrder with the detail endpoint', () => {
  it('keeps the list order and adds the detail', async () => {
    const order = makeOrder();
    const detail = makeStaffOrderDetail({ uuid: order.uuid });
    mockFetch({
      'GET /api/v1/orders': () => ok([order], { meta: { truncated: false } }),
      [`GET /api/v1/staff/orders/${order.uuid}`]: () => ok(detail),
    });
    const { result } = renderHook(() => useOrder(order.uuid, 'manager'), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.detail).toEqual(detail));
    expect(result.current.order).toEqual(order);
    expect(result.current.isError).toBe(false);
  });

  it('can leave the detail out (a courier has no /staff/orders)', async () => {
    const order = makeOrder();
    const { fetchMock } = mockFetch({ 'GET /api/v1/orders': () => ok([order], { meta: { truncated: false } }) });
    const { result } = renderHook(() => useOrder(order.uuid, 'courier', { detail: false }), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.order).toEqual(order));
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(result.current.detail).toBeUndefined();
  });

  it('refetches both', async () => {
    const order = makeOrder();
    const { fetchMock } = mockFetch({
      'GET /api/v1/orders': () => ok([order], { meta: { truncated: false } }),
      [`GET /api/v1/staff/orders/${order.uuid}`]: () => ok(makeStaffOrderDetail({ uuid: order.uuid })),
    });
    const { result } = renderHook(() => useOrder(order.uuid, 'manager'), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.detail).toBeDefined());
    await act(async () => {
      await result.current.refetch();
    });
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(4));
  });
});
