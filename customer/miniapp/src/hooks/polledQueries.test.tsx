import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { act, render, renderHook, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { ordersResponse } from '../test/ordersApi';
import { useNotifications } from './useNotifications';
import { callsTo, reply, stubStaffApi, type FakeCall } from '../test/staffFetch';
import { useOrders } from './useOrders';
import { useStaffOrders } from './useStaffOrders';

// The hooks that keep a screen fresh by polling, wired to a fake API and a controlled clock

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
const ordersCalls = () => get.mock.calls.filter(([url]) => url === '/orders').length;

let client: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const advance = (ms: number) => act(() => vi.advanceTimersByTimeAsync(ms));

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  client = new QueryClient();
  get.mockImplementation((url, config) => {
    if (url === '/orders') return Promise.resolve(ordersResponse([makeOrder({ id: 1, status: 'new' })], config));
    return Promise.resolve({ data: { success: true, data: { items: [], unread_count: 0, pagination: {} } } });
  });
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
});

afterEach(() => {
  client.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
});

describe('useStaffOrders', () => {
  // The staff screens read GET /orders through @rizqim/staff, over fetch
  let staffCalls: FakeCall[];
  let staffOrders: ReturnType<typeof makeOrder>[];
  let staffFails: boolean;
  const staffOrdersCalls = () => callsTo(staffCalls, '/orders').length;

  beforeEach(() => {
    staffOrders = [makeOrder({ id: 1, status: 'new' })];
    staffFails = false;
    ({ calls: staffCalls } = stubStaffApi((call) => (staffFails ? reply(502) : ordersResponse(staffOrders, { params: call.query }).data)));
  });

  it('loads the orders and refreshes them at the dashboard\'s interval', async () => {
    const { result } = renderHook(() => useStaffOrders('manager', 8000), { wrapper });
    await advance(0);

    expect(result.current.data?.orders.map((o) => o.id)).toEqual([1]);
    expect(staffOrdersCalls()).toBe(1);

    await advance(8000);
    expect(staffOrdersCalls()).toBe(2);
    await advance(16_000);
    expect(staffOrdersCalls()).toBe(4);
  });

  it('stays quiet while the page is hidden and catches up when it is shown', async () => {
    renderHook(() => useStaffOrders('courier', 15_000), { wrapper });
    await advance(0);

    focusManager.setFocused(false);
    await advance(10 * 60_000);
    expect(staffOrdersCalls()).toBe(1);

    focusManager.setFocused(true);
    await advance(0);
    expect(staffOrdersCalls()).toBe(2);
  });

  it('keeps the last orders on screen through failed polls and polls less often meanwhile', async () => {
    // Read through a component, as a screen does: that is what has to keep showing the orders
    function Probe() {
      const { data, isError } = useStaffOrders('manager', 15_000);
      return <p>{`orders:${data?.orders.length ?? 'none'} error:${isError}`}</p>;
    }
    render(<QueryClientProvider client={client}><Probe /></QueryClientProvider>);
    await advance(0);
    expect(screen.getByText('orders:1 error:false')).toBeInTheDocument();

    staffFails = true;
    await advance(15_000 + 5000); // a poll fails (and its one retry)
    expect(screen.getByText('orders:1 error:true')).toBeInTheDocument(); // the last good orders are still there
    const callsAfterFirstFailure = staffOrdersCalls();

    await advance(15_000); // the normal interval has passed, but the next poll is twice as far away
    expect(staffOrdersCalls()).toBe(callsAfterFirstFailure);

    // The server comes back: the next poll succeeds and everything is normal again
    staffFails = false;
    staffOrders = [makeOrder({ id: 1, status: 'new' }), makeOrder({ id: 2, status: 'new' })];
    await advance(60_000);
    expect(screen.getByText('orders:2 error:false')).toBeInTheDocument();

    const callsWhenRecovered = staffOrdersCalls();
    await advance(15_000);
    expect(staffOrdersCalls()).toBe(callsWhenRecovered + 1);
  });
});

describe('useOrders', () => {
  it('polls the customer\'s orders every 10 seconds', async () => {
    renderHook(() => useOrders(), { wrapper });
    await advance(0);
    await advance(30_000);

    expect(ordersCalls()).toBe(4);
  });

  it('waits for the sign-in', async () => {
    useAuthStore.getState().logout();
    renderHook(() => useOrders(), { wrapper });

    await advance(60_000);

    expect(ordersCalls()).toBe(0);
  });
});

describe('useNotifications', () => {
  it('reads the list out of the paginated answer', async () => {
    const item = { id: 5, title: 'Buyurtma qabul qilindi', message: 'm', type: 'accepted', status: 'sent', is_read: false, read_at: null, sent_at: '2026-10-18T09:00:00Z' };
    get.mockResolvedValue({ data: { success: true, data: { items: [item], unread_count: 1, pagination: { current_page: 1, last_page: 1, total: 1, per_page: 20 } } } });

    const { result } = renderHook(() => useNotifications(), { wrapper });
    await advance(0);

    expect(result.current.data).toEqual([item]);
  });

  it('also accepts a bare list', async () => {
    const item = { id: 6, title: 't', message: 'm', type: 'accepted', status: 'sent', is_read: true, read_at: null, sent_at: '2026-10-18T09:00:00Z' };
    get.mockResolvedValue({ data: { success: true, data: [item] } });

    const { result } = renderHook(() => useNotifications(), { wrapper });
    await advance(0);

    expect(result.current.data).toEqual([item]);
  });

  it('polls every 15 seconds', async () => {
    renderHook(() => useNotifications(), { wrapper });
    await advance(0);
    await advance(45_000);

    expect(get.mock.calls.filter(([url]) => url === '/notifications')).toHaveLength(4);
  });
});
