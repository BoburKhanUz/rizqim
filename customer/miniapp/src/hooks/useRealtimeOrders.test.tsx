import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { echoFailure, echoInstances } from '../test/fakeEcho';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import type { Order } from '../types';
import { ordersResponse, serveOrdersApi } from '../test/ordersApi';
import { ordersKeys, useOrderHistory, useOrders } from './useOrders';
import { useRealtimeOrders } from './useRealtimeOrders';

// The hook that turns the server's order events into refetches, on a fake socket

vi.mock('laravel-echo', async () => ({ default: (await import('../test/fakeEcho')).FakeEcho }));
vi.mock('pusher-js', async () => ({ default: (await import('../test/fakeEcho')).FakePusher }));
vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
const callsWith = (scope: string) => get.mock.calls.filter(([url, config]) => url === '/orders' && (config?.params as { scope?: string })?.scope === scope);
const activeCalls = () => callsWith('active').length;
const historyCalls = () => callsWith('history').length;

let client: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));
const latestEcho = () => echoInstances[echoInstances.length - 1];
const order = { uuid: 'u1', status: 'accepted', store_id: 1, branch_id: null, courier_id: null, updated_at: 't' };

function signIn(overrides: Parameters<typeof makeUser>[0] = {}) {
  useAuthStore.getState().setAuth('token-1', makeUser({ id: 42, role: 'customer', ...overrides }), makeStore(), false);
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.stubEnv('VITE_REVERB_APP_KEY', 'app-key');
  echoInstances.length = 0;
  echoFailure.error = null;
  useRealtimeStore.setState({ connected: false });
  client = new QueryClient();
  get.mockImplementation(serveOrdersApi([makeOrder({ id: 1, status: 'new' })]));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  useAuthStore.getState().logout();
  vi.useRealTimers();
});

describe('useRealtimeOrders: connecting', () => {
  it('does nothing without VITE_REVERB_APP_KEY', async () => {
    vi.stubEnv('VITE_REVERB_APP_KEY', '');
    signIn();

    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();

    expect(echoInstances).toHaveLength(0);
    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('waits for the sign-in, then connects', async () => {
    useAuthStore.getState().logout();
    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();
    expect(echoInstances).toHaveLength(0);

    act(() => signIn());
    await settle();

    expect(echoInstances).toHaveLength(1);
    expect(latestEcho().channel.name).toBe('user.42');
  });

  it.each([
    [{ role: 'owner', store_id: 5 }, ['store.5', 'courier.42']],
    [{ role: 'manager', store_id: 5, branch_id: 8 }, ['branch.8', 'courier.42']],
    [{ role: 'manager', store_id: 5, branch_id: null }, ['store.5', 'courier.42']],
    [{ role: 'courier', store_id: 5 }, ['courier.42']],
    [{ role: 'customer', store_id: null }, ['user.42']],
  ] as const)('subscribes %j to %j', async (user, channels) => {
    signIn(user);

    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();

    expect(echoInstances).toHaveLength(1); // one socket for all of them
    expect(latestEcho().channels.map((channel) => channel.name)).toEqual(channels);
  });

  it('opens no channel for a super admin', async () => {
    signIn({ role: 'super_admin', store_id: null });

    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();

    expect(echoInstances).toHaveLength(0);
  });

  it('marks the socket connected only when the channel is accepted, and not connected when it drops', async () => {
    signIn();
    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();
    expect(useRealtimeStore.getState().connected).toBe(false);

    act(() => latestEcho().connectAndSubscribe());
    expect(useRealtimeStore.getState().connected).toBe(true);

    act(() => latestEcho().setConnection('disconnected'));
    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('carries on polling when the client cannot be created', async () => {
    echoFailure.error = new Error('boom');
    signIn();

    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();

    expect(echoInstances).toHaveLength(0);
    expect(useRealtimeStore.getState().connected).toBe(false);
  });
});

describe('useRealtimeOrders: leaving', () => {
  it('disconnects on logout', async () => {
    signIn();
    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();
    act(() => latestEcho().connectAndSubscribe());

    act(() => useAuthStore.getState().logout());
    await settle();

    expect(latestEcho().disconnected).toBe(true);
    expect(latestEcho().left).toContain('user.42');
    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('disconnects on unmount', async () => {
    signIn();
    const { unmount } = renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();
    act(() => latestEcho().connectAndSubscribe());

    unmount();

    expect(latestEcho().disconnected).toBe(true);
    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('closes a connection that finished opening after the screen was gone', async () => {
    signIn();
    const { unmount } = renderHook(() => useRealtimeOrders(), { wrapper });
    unmount(); // before the libraries have loaded
    await settle();

    expect(echoInstances.every((echo) => echo.disconnected)).toBe(true);
    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('moves to the new channel when another user signs in', async () => {
    signIn();
    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();
    const first = latestEcho();

    act(() => signIn({ id: 77, role: 'courier' }));
    await settle();

    expect(first.disconnected).toBe(true);
    expect(latestEcho().channel.name).toBe('courier.77');
  });
});

describe('useRealtimeOrders: events', () => {
  async function mountWithOrders() {
    signIn();
    const view = renderHook(
      () => {
        useRealtimeOrders();
        return useOrders();
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();
    return view;
  }

  it('refetches the open orders on an event', async () => {
    await mountWithOrders();
    const before = activeCalls();

    act(() => latestEcho().emit('.order.status_changed', { order }));
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it.each(['.order.created', '.order.status_changed', '.order.assigned'])('reacts to %s', async (event) => {
    await mountWithOrders();
    const before = activeCalls();

    act(() => latestEcho().emit(event, { order }));
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it('refetches once for a burst of events', async () => {
    await mountWithOrders();
    const before = activeCalls();

    act(() => {
      latestEcho().emit('.order.status_changed', { order });
      latestEcho().emit('.order.assigned', { order });
      latestEcho().emit('.order.status_changed', { order });
    });
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it('refetches even when the payload is not what was promised', async () => {
    await mountWithOrders();
    const before = activeCalls();

    act(() => latestEcho().emit('.order.assigned', 'garbage'));
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it('refetches only the open orders: not the history, while no order finished', async () => {
    signIn();
    renderHook(
      () => {
        useRealtimeOrders();
        useOrders();
        return useOrderHistory('customer');
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();
    const [active, history] = [activeCalls(), historyCalls()];

    act(() => latestEcho().emit('.order.status_changed', { order }));
    await settle(400);

    expect(activeCalls()).toBe(active + 1);
    expect(historyCalls()).toBe(history);
  });

  it('refetches the history once an order finished and left the open ones', async () => {
    signIn();
    let served = [makeOrder({ id: 1, status: 'on_the_way' })];
    get.mockImplementation((_url, config) => Promise.resolve(ordersResponse(served, config)));
    renderHook(
      () => {
        useRealtimeOrders();
        useOrders();
        return useOrderHistory('customer');
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();
    const history = historyCalls();

    served = [makeOrder({ id: 1, status: 'delivered' })];
    act(() => latestEcho().emit('.order.status_changed', { order: { ...order, status: 'delivered' } }));
    await settle(400);

    expect(historyCalls()).toBe(history + 1);
  });

  it('does not let a steady stream of events put the refetch off', async () => {
    await mountWithOrders();
    const before = activeCalls();

    for (let i = 0; i < 20; i++) {
      act(() => latestEcho().emit('.order.status_changed', { order }));
      await settle(100);
    }

    expect(activeCalls()).toBeGreaterThanOrEqual(before + 6); // about one per 300 ms
  });

  it('refetches the open orders and the histories after the socket came back, since events may have been missed', async () => {
    await mountWithOrders();
    const invalidate = vi.spyOn(client, 'invalidateQueries');

    act(() => latestEcho().setConnection('disconnected'));
    act(() => latestEcho().connectAndSubscribe());
    await settle(400);

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['orders', 'active'] }, { cancelRefetch: false });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['orders', 'history'] }, { cancelRefetch: false });
    expect(invalidate).not.toHaveBeenCalledWith({ queryKey: ['orders'] });
  });
});

describe('an owner or manager standing in as a courier', () => {
  async function mountAs(user: Parameters<typeof makeUser>[0]) {
    signIn(user);
    const view = renderHook(
      () => {
        useRealtimeOrders();
        return useOrders();
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();
    return view;
  }

  it.each(['.order.assigned', '.order.status_changed'])('refetches the open orders on %s of their own courier channel', async (event) => {
    await mountAs({ role: 'owner', store_id: 5 });
    const before = activeCalls();

    act(() => latestEcho().emit(event, { order: { ...order, courier_id: 42 } }, 'courier.42'));
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it('refetches once when the same change arrives on the store and on the courier channel', async () => {
    await mountAs({ role: 'owner', store_id: 5 });
    const before = activeCalls();

    act(() => {
      latestEcho().emit('.order.assigned', { order: { ...order, courier_id: 42 } }, 'store.5');
      latestEcho().emit('.order.assigned', { order: { ...order, courier_id: 42 } }, 'courier.42');
    });
    await settle(400);

    expect(activeCalls()).toBe(before + 1);
  });

  it('keeps the slow polling while only the courier channel is refused', async () => {
    await mountAs({ role: 'manager', store_id: 5, branch_id: 8 });
    expect(useRealtimeStore.getState().connected).toBe(true);

    act(() => latestEcho().refuseSubscription('courier.42'));

    expect(useRealtimeStore.getState().connected).toBe(true);
  });

  it('polls at its own pace again when the main channel is refused', async () => {
    await mountAs({ role: 'manager', store_id: 5, branch_id: 8 });

    act(() => latestEcho().refuseSubscription('branch.8'));

    expect(useRealtimeStore.getState().connected).toBe(false);
  });

  it('refetches after the courier channel came back, since events may have been missed', async () => {
    await mountAs({ role: 'owner', store_id: 5 });
    const invalidate = vi.spyOn(client, 'invalidateQueries');

    act(() => latestEcho().refuseSubscription('courier.42'));
    await settle(5_000); // subscribed again after the retry delay
    act(() => latestEcho().acceptSubscription('courier.42'));
    await settle(400);

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['orders', 'history'] }, { cancelRefetch: false });
  });
});

describe('an order already in the history that changes', () => {
  const delivered = makeOrder({ id: 7, uuid: '77777777-7777-4777-8777-777777777777', status: 'delivered', payment_status: 'paid' });
  const other = makeOrder({ id: 8, uuid: '88888888-8888-4888-8888-888888888888', status: 'delivered' });
  const historyOrder = (uuid: string) => client.getQueryData<{ pages: Array<{ orders: Order[] }> }>([...ordersKeys.history('manager'), 20])?.pages.flatMap((p) => p.orders).find((o) => o.uuid === uuid);

  async function mountHistory() {
    signIn({ role: 'manager', store_id: 5, branch_id: 8 });
    get.mockImplementation(serveOrdersApi([delivered, other, makeOrder({ id: 9, status: 'preparing' })]));
    renderHook(
      () => {
        useRealtimeOrders();
        return useOrderHistory('manager');
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();
  }

  it('shows delivered -> refunded at once, without a request', async () => {
    await mountHistory();
    expect(historyOrder(delivered.uuid)?.status).toBe('delivered');
    const history = historyCalls();

    act(() => latestEcho().emit('.order.status_changed', { order: { ...order, uuid: delivered.uuid, status: 'refunded', updated_at: '2026-10-18T10:00:00.000000Z' } }, 'branch.8'));

    expect(historyOrder(delivered.uuid)).toMatchObject({ status: 'refunded', updated_at: '2026-10-18T10:00:00.000000Z' });
    expect(historyOrder(other.uuid)?.status).toBe('delivered'); // only that order
    await settle(5_000);
    expect(historyCalls()).toBe(history); // not a refetch of the pages
  });

  it('keeps the age of the list, so the staleness refetch still comes on time', async () => {
    await mountHistory();
    const query = client.getQueryCache().find({ queryKey: [...ordersKeys.history('manager'), 20] });
    const updatedAt = query?.state.dataUpdatedAt;
    await settle(10_000);

    act(() => latestEcho().emit('.order.status_changed', { order: { ...order, uuid: delivered.uuid, status: 'refunded' } }, 'branch.8'));

    expect(query?.state.dataUpdatedAt).toBe(updatedAt);
  });

  it('leaves the history alone for an order it does not hold, a status that is not final or the same status', async () => {
    await mountHistory();
    const before = client.getQueryData([...ordersKeys.history('manager'), 20]);

    act(() => {
      latestEcho().emit('.order.status_changed', { order: { ...order, uuid: 'not-in-history', status: 'refunded' } }, 'branch.8');
      latestEcho().emit('.order.status_changed', { order: { ...order, uuid: delivered.uuid, status: 'preparing' } }, 'branch.8');
      latestEcho().emit('.order.status_changed', { order: { ...order, uuid: delivered.uuid, status: 'delivered' } }, 'branch.8');
      latestEcho().emit('.order.created', { order: { ...order, uuid: delivered.uuid, status: 'refunded' } }, 'branch.8');
    });

    expect(client.getQueryData([...ordersKeys.history('manager'), 20])).toBe(before);
  });

  it('refunds from the customer side too: the customer history shows it', async () => {
    signIn({ role: 'customer', store_id: null });
    get.mockImplementation(serveOrdersApi([delivered]));
    renderHook(
      () => {
        useRealtimeOrders();
        return useOrderHistory('customer');
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    await settle();

    act(() => latestEcho().emit('.order.status_changed', { order: { ...order, uuid: delivered.uuid, status: 'refunded' } }));

    const cached = client.getQueryData<{ pages: Array<{ orders: Order[] }> }>([...ordersKeys.history('customer'), 20]);
    expect(cached?.pages[0].orders[0].status).toBe('refunded');
  });

  it('does not turn a burst of events into a refetch of every loaded page', async () => {
    signIn({ role: 'manager', store_id: 5, branch_id: 8 });
    const many = Array.from({ length: 60 }, (_, i) => makeOrder({ id: i + 1, uuid: `00000000-0000-4000-8000-${String(i + 1).padStart(12, '0')}`, status: 'delivered' }));
    get.mockImplementation(serveOrdersApi(many));
    const { result } = renderHook(
      () => {
        useRealtimeOrders();
        return useOrderHistory('manager', { pageSize: 20 });
      },
      { wrapper },
    );
    await settle();
    act(() => latestEcho().connectAndSubscribe());
    for (let i = 0; i < 2; i++) await act(async () => { await result.current.fetchNextPage(); });
    await settle();
    expect(result.current.orders).toHaveLength(60);
    const history = historyCalls();

    act(() => {
      for (const o of many.slice(0, 40)) latestEcho().emit('.order.status_changed', { order: { ...order, uuid: o.uuid, status: 'refunded' } }, 'branch.8');
    });
    await settle(5_000);

    expect(historyCalls()).toBe(history);
    expect(result.current.orders.filter((o) => o.status === 'refunded')).toHaveLength(40);
  });
});

describe('polling next to the socket', () => {
  it('polls every 60 seconds while connected and at its own pace when not', async () => {
    signIn();
    renderHook(
      () => {
        useRealtimeOrders();
        return useOrders();
      },
      { wrapper },
    );
    await settle();
    expect(activeCalls()).toBe(1);

    // Not connected yet: the customer's 10 s
    await settle(10_000);
    expect(activeCalls()).toBe(2);

    act(() => latestEcho().connectAndSubscribe());
    await settle(0);
    const calls = activeCalls();
    await settle(50_000);
    expect(activeCalls()).toBe(calls); // nothing for a long while...
    await settle(10_000);
    expect(activeCalls()).toBe(calls + 1); // ...then the slow safety-net poll

    // The socket drops: back to 10 s
    act(() => latestEcho().setConnection('disconnected'));
    await settle(0);
    const afterDrop = activeCalls();
    await settle(10_000);
    expect(activeCalls()).toBe(afterDrop + 1);
    await settle(10_000);
    expect(activeCalls()).toBe(afterDrop + 2);
  });
});
