import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { echoFailure, echoInstances } from '../test/fakeEcho';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { serveOrdersApi } from '../test/ordersApi';
import { useOrders } from './useOrders';
import { useRealtimeOrders } from './useRealtimeOrders';

// The hook that turns the server's order events into refetches, on a fake socket

vi.mock('laravel-echo', async () => ({ default: (await import('../test/fakeEcho')).FakeEcho }));
vi.mock('pusher-js', async () => ({ default: (await import('../test/fakeEcho')).FakePusher }));
vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
const activeCalls = () => get.mock.calls.filter(([url, config]) => url === '/orders' && (config?.params as { scope?: string })?.scope === 'active').length;

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
    [{ role: 'owner', store_id: 5 }, 'store.5'],
    [{ role: 'manager', store_id: 5, branch_id: 8 }, 'branch.8'],
    [{ role: 'manager', store_id: 5, branch_id: null }, 'store.5'],
    [{ role: 'courier', store_id: 5 }, 'courier.42'],
  ] as const)('subscribes %j to %s', async (user, channel) => {
    signIn(user);

    renderHook(() => useRealtimeOrders(), { wrapper });
    await settle();

    expect(latestEcho().channel.name).toBe(channel);
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

  it('invalidates just the open orders for a new order, everything for a change', async () => {
    await mountWithOrders();
    const invalidate = vi.spyOn(client, 'invalidateQueries');

    act(() => latestEcho().emit('.order.created', { order }));
    await settle(400);
    expect(invalidate).toHaveBeenLastCalledWith({ queryKey: ['orders', 'active'] });

    act(() => latestEcho().emit('.order.status_changed', { order }));
    await settle(400);
    expect(invalidate).toHaveBeenLastCalledWith({ queryKey: ['orders'] });
  });

  it('refetches everything after the socket came back, since events may have been missed', async () => {
    await mountWithOrders();
    const invalidate = vi.spyOn(client, 'invalidateQueries');

    act(() => latestEcho().setConnection('disconnected'));
    act(() => latestEcho().connectAndSubscribe());
    await settle(400);

    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['orders'] });
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
