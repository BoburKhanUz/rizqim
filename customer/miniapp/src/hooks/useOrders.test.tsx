import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { failedRequest } from '../test/httpErrors';
import { ordersResponse, serveOrdersApi } from '../test/ordersApi';
import type { Order } from '../types';
import { ORDERS_KEY, fetchActiveOrders, fetchOrdersPage, mergeOrders, refetchOrders, useOrderHistory, useOrders } from './useOrders';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
const callsWith = (scope: string) =>
  get.mock.calls.filter(([url, config]) => url === '/orders' && (config?.params as { scope?: string })?.scope === scope);

let client: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));
// react-query hands results over on timers, which the fake clock only runs when it is moved
const loadMore = (fetchNextPage: () => Promise<unknown>) => act(async () => { void fetchNextPage(); await vi.advanceTimersByTimeAsync(5000); });

// n finished orders, newest first, one per hour back from 2026-10-14 12:00
const finished = (n: number, firstId = 1): Order[] =>
  Array.from({ length: n }, (_, i) => {
    const created = new Date(2026, 9, 14, 12 - i, 0, 0).toISOString();
    return makeOrder({ id: firstId + i, uuid: `00000000-0000-4000-8000-${String(firstId + i).padStart(12, '0')}`, status: 'delivered', created_at: created, updated_at: created });
  });

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(new Date(2026, 9, 14, 15, 0, 0));
  client = new QueryClient();
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
});

afterEach(() => {
  client.clear();
  useRealtimeStore.setState({ connected: false });
  vi.useRealTimers();
});

describe('fetching GET /orders', () => {
  it('asks for the open orders with scope=active and reads the truncated flag', async () => {
    get.mockResolvedValue({ data: { success: true, data: [makeOrder({ id: 1, status: 'new' })], meta: { truncated: true } } });

    const result = await fetchActiveOrders();

    expect(get).toHaveBeenCalledWith('/orders', { params: { scope: 'active' } });
    expect(result.truncated).toBe(true);
    expect(result.orders).toHaveLength(1);
  });

  it('is not truncated unless the server says so', async () => {
    get.mockResolvedValue({ data: { success: true, data: [] } });

    expect((await fetchActiveOrders()).truncated).toBe(false);
  });

  it('asks for a page of history, with the cursor only after the first page', async () => {
    get.mockResolvedValue({ data: { success: true, data: [], meta: { next_cursor: 'abc', per_page: 20 } } });

    const first = await fetchOrdersPage(null);
    await fetchOrdersPage('abc', 50);

    expect(get).toHaveBeenNthCalledWith(1, '/orders', { params: { scope: 'history', per_page: 20 } });
    expect(get).toHaveBeenNthCalledWith(2, '/orders', { params: { scope: 'history', per_page: 50, cursor: 'abc' } });
    expect(first.nextCursor).toBe('abc');
  });

  it('has no next page when next_cursor is null or missing', async () => {
    get.mockResolvedValue({ data: { success: true, data: [], meta: { next_cursor: null, per_page: 20 } } });
    expect((await fetchOrdersPage(null)).nextCursor).toBeNull();
    get.mockResolvedValue({ data: { success: true, data: [] } });
    expect((await fetchOrdersPage(null)).nextCursor).toBeNull();
  });
});

describe('useOrders', () => {
  it('never asks for the unbounded list', async () => {
    get.mockImplementation(serveOrdersApi([makeOrder({ id: 1, status: 'new' })]));

    renderHook(() => useOrders(), { wrapper });
    await settle(30_000);

    expect(get.mock.calls.length).toBeGreaterThan(0);
    expect(get.mock.calls.every(([, config]) => (config?.params as { scope?: string })?.scope === 'active')).toBe(true);
  });

  it('refetches the history when an order leaves the open ones', async () => {
    const delivering = makeOrder({ id: 1, status: 'on_the_way' });
    let served = [delivering];
    get.mockImplementation((_url, config) => Promise.resolve(ordersResponse(served, config)));

    renderHook(
      () => {
        useOrders();
        return useOrderHistory('customer');
      },
      { wrapper },
    );
    await settle();
    expect(callsWith('history')).toHaveLength(1);

    served = [{ ...delivering, status: 'delivered' }]; // the next poll no longer lists it
    await settle(10_000);

    expect(callsWith('history')).toHaveLength(2);
  });

  it('polls slowly while the socket is up, yet refetches a screen opened again after its own interval', async () => {
    useRealtimeStore.setState({ connected: true });
    get.mockImplementation(serveOrdersApi([makeOrder({ id: 1, status: 'new' })]));

    renderHook(() => useOrders(), { wrapper });
    await settle();
    await settle(11_000);
    expect(callsWith('active')).toHaveLength(1); // no poll yet: the socket brings the changes

    renderHook(() => useOrders(), { wrapper }); // e.g. the orders screen after placing an order
    await settle();

    expect(callsWith('active')).toHaveLength(2);
  });

  it('leaves the history alone while the open orders only change', async () => {
    let served = [makeOrder({ id: 1, status: 'new' })];
    get.mockImplementation((_url, config) => Promise.resolve(ordersResponse(served, config)));

    renderHook(
      () => {
        useOrders();
        return useOrderHistory('customer');
      },
      { wrapper },
    );
    await settle();

    served = [makeOrder({ id: 1, status: 'accepted' }), makeOrder({ id: 2, uuid: '22222222-2222-4222-8222-222222222222', status: 'new' })];
    await settle(10_000);

    expect(callsWith('history')).toHaveLength(1);
  });
});

describe('useOrderHistory', () => {
  it('loads a page at a time, following the cursor', async () => {
    get.mockImplementation(serveOrdersApi(finished(45)));

    const { result } = renderHook(() => useOrderHistory('customer'), { wrapper });
    await settle();
    expect(result.current.orders).toHaveLength(20);
    expect(result.current.hasNextPage).toBe(true);

    await loadMore(result.current.fetchNextPage);
    expect(result.current.orders).toHaveLength(40);
    expect(callsWith('history')[1][1]).toEqual({ params: { scope: 'history', per_page: 20, cursor: '20' } });

    await loadMore(result.current.fetchNextPage);
    expect(result.current.orders).toHaveLength(45);
    expect(result.current.hasNextPage).toBe(false);
  });

  it('does not load anything until it is enabled', async () => {
    get.mockImplementation(serveOrdersApi(finished(3)));

    const { result, rerender } = renderHook(({ enabled }) => useOrderHistory('manager', { enabled }), { wrapper, initialProps: { enabled: false } });
    await settle();
    expect(get).not.toHaveBeenCalled();

    rerender({ enabled: true });
    await settle();
    expect(result.current.orders).toHaveLength(3);
  });

  it('keeps the loaded pages and offers a retry when the next page fails', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    get.mockImplementation(serveOrdersApi(finished(45)));
    const { result } = renderHook(() => useOrderHistory('customer'), { wrapper });
    await settle();

    get.mockRejectedValue(failedRequest(422, { message: "Noto'g'ri yoki eskirgan cursor.", errors: { cursor: ['x'] } }, '/orders'));
    await loadMore(result.current.fetchNextPage);

    expect(result.current.orders).toHaveLength(20);
    expect(result.current.isFetchNextPageError).toBe(true);
    expect(result.current.hasNextPage).toBe(true);
  });

  it('loads older pages by itself back to a date, and not further', async () => {
    // 30 finished orders, one per hour, in pages of 5. Loading goes on until a page's oldest order is older than
    // `since` less the 2 days of margin, which here is 03:00 on the 14th: the order of hour 9
    get.mockImplementation(serveOrdersApi(finished(30)));
    const since = new Date(2026, 9, 14, 12 - 9, 0, 0).getTime() + 2 * 24 * 3600 * 1000;

    const { result } = renderHook(() => useOrderHistory('manager', { pageSize: 5, since }), { wrapper });
    await settle(100);

    // pages hold hours 0-4, 5-9 (its oldest is exactly 03:00, not older) and 10-14 (older): loading stops after the third
    expect(result.current.orders).toHaveLength(15);
    expect(result.current.hasNextPage).toBe(true);
    expect(callsWith('history')).toHaveLength(3);
  });

  it('stops at its page limit', async () => {
    get.mockImplementation(serveOrdersApi(finished(40)));
    const since = new Date(2020, 0, 1).getTime();

    const { result } = renderHook(() => useOrderHistory('manager', { pageSize: 5, since, maxAutoPages: 3 }), { wrapper });
    await settle(100);

    expect(callsWith('history')).toHaveLength(3);
    expect(result.current.orders).toHaveLength(15);
    expect(result.current.hasNextPage).toBe(true);
  });

  it('does not keep loading after a failure', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    get.mockRejectedValue(failedRequest(500, {}, '/orders'));

    renderHook(() => useOrderHistory('manager', { pageSize: 5, since: new Date(2020, 0, 1).getTime() }), { wrapper });
    await settle(60_000);

    expect(callsWith('history').length).toBeLessThanOrEqual(2); // the request and its one retry
  });
});

describe('useOrderHistory: refetches while pages load', () => {
  // `n` finished orders whose short_code tells which version of the server data they come from (the one current when
  // the request was made); every request takes 100 ms
  let version = 1;
  const serveSlowly = (n: number) => {
    version = 1;
    get.mockImplementation((_url, config) => {
      const orders = finished(n).map((order) => ({ ...order, short_code: `v${version}` }));
      return new Promise((resolve) => setTimeout(() => resolve(ordersResponse(orders, config)), 100));
    });
  };

  it('does not cancel a refetch of the loaded pages to load the next one', async () => {
    serveSlowly(30);
    const { result } = renderHook(() => useOrderHistory('manager', { pageSize: 5, since: new Date(2020, 0, 1).getTime() }), { wrapper });
    await settle(350); // three pages in, the fourth on its way

    // The data changes and something refetches the orders (as a mutation does), cancelling the page on its way
    version = 2;
    await act(async () => {
      void client.invalidateQueries({ queryKey: ORDERS_KEY });
      await vi.advanceTimersByTimeAsync(5000);
    });

    expect(result.current.orders).toHaveLength(30);
    expect(new Set(result.current.orders.map((order) => order.short_code))).toEqual(new Set(['v2']));
  });

  it('waits for a refetch under way when asked for the next page', async () => {
    serveSlowly(45);
    const { result } = renderHook(() => useOrderHistory('customer'), { wrapper });
    await settle(100);

    version = 2;
    await act(async () => {
      void client.refetchQueries({ queryKey: ORDERS_KEY });
      await vi.advanceTimersByTimeAsync(50);
      void result.current.fetchNextPage(); // "Ko'proq yuklash" in the middle of it
      await vi.advanceTimersByTimeAsync(5000);
    });

    expect(new Set(result.current.orders.map((order) => order.short_code))).toEqual(new Set(['v2'])); // the refetch went through
  });
});

describe('refetchOrders', () => {
  it('does not restart a refetch under way, and refetches once more after it for all the calls made meanwhile', async () => {
    get.mockImplementation((_url, config) => new Promise((resolve) => setTimeout(() => resolve(ordersResponse([], config)), 100)));
    renderHook(() => useOrders(), { wrapper });
    await settle(100);
    expect(callsWith('active')).toHaveLength(1);

    await act(async () => {
      void client.refetchQueries({ queryKey: ORDERS_KEY }); // a poll, say
      await vi.advanceTimersByTimeAsync(10);
      for (let i = 0; i < 3; i++) {
        void refetchOrders(client, ORDERS_KEY);
        await vi.advanceTimersByTimeAsync(20);
      }
      await vi.advanceTimersByTimeAsync(1000);
    });

    expect(callsWith('active')).toHaveLength(3); // the first load, the poll (not restarted) and one more after it
  });

  it('refetches at once when nothing is under way', async () => {
    get.mockImplementation(serveOrdersApi([]));
    renderHook(() => useOrders(), { wrapper });
    await settle();

    await act(() => refetchOrders(client, ORDERS_KEY));

    expect(callsWith('active')).toHaveLength(2);
  });
});

describe('mergeOrders', () => {
  it('lists an order once, as the history has it', () => {
    const open = makeOrder({ id: 1, status: 'on_the_way' });
    const done = makeOrder({ id: 1, status: 'delivered' });

    expect(mergeOrders([open, makeOrder({ id: 2, status: 'new' })], [done])).toEqual([done, expect.objectContaining({ id: 2 })]);
  });
});
