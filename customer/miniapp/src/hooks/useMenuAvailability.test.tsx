import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { echoInstances, type FakeEcho } from '../test/fakeEcho';
import { useMenuAvailability } from './useMenuAvailability';

// The stop-list / sold-out state of a menu: GET /catalog/availability, kept current by events when real-time is on and by
// polling and refocusing when it is not.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));
vi.mock('laravel-echo', async () => ({ default: (await import('../test/fakeEcho')).FakeEcho }));
vi.mock('pusher-js', async () => ({ default: (await import('../test/fakeEcho')).FakePusher }));

const get = vi.mocked(api.get);
let client: QueryClient;
let stopped: number[];

const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
const advance = (ms: number) => act(() => vi.advanceTimersByTimeAsync(ms));
const availabilityCalls = () => get.mock.calls.filter(([url]) => url === '/catalog/availability');

function serve() {
  get.mockImplementation(() => {
    return Promise.resolve({
      data: { success: true, data: { store_id: 7, branches: [{ branch_id: 1, stopped: { products: [], variants: stopped, modifiers: [] }, sold_out: { variants: [] } }] } },
    });
  });
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  client = new QueryClient();
  stopped = [];
  echoInstances.length = 0;
  get.mockReset();
  serve();
});

afterEach(() => {
  client.clear();
  vi.unstubAllEnvs();
  vi.useRealTimers();
});

describe('useMenuAvailability without real-time (the default)', () => {
  it('loads the availability of the store for the first picture', async () => {
    stopped = [11];
    const { result } = renderHook(() => useMenuAvailability(7), { wrapper });
    await advance(0);

    expect(get).toHaveBeenCalledWith('/catalog/availability', { params: { store_id: 7 } });
    expect(result.current.branches?.[0].stopped.variants).toEqual([11]);
  });

  it('shows the catalog\'s own copy until the endpoint has answered', async () => {
    const placeholder = [{ branch_id: 1, stopped: { products: [], variants: [99], modifiers: [] }, sold_out: { variants: [] } }];
    const { result } = renderHook(() => useMenuAvailability(7, placeholder), { wrapper });

    expect(result.current.branches).toEqual(placeholder);
    await advance(0);
    expect(result.current.branches?.[0].stopped.variants).toEqual([]);
  });

  it('keeps itself correct by polling: a dish stopped meanwhile shows up within 20 s', async () => {
    const { result } = renderHook(() => useMenuAvailability(7), { wrapper });
    await advance(0);
    expect(result.current.branches?.[0].stopped.variants).toEqual([]);

    stopped = [11];
    await advance(20_000);
    await advance(100);

    expect(availabilityCalls().length).toBeGreaterThanOrEqual(2);
    expect(result.current.branches?.[0].stopped.variants).toEqual([11]);
  });

  it('asks nothing without a store', async () => {
    renderHook(() => useMenuAvailability(undefined), { wrapper });
    await advance(0);

    expect(get).not.toHaveBeenCalled();
  });

  it('flags nothing when the endpoint fails: the server decides at checkout', async () => {
    get.mockRejectedValue(new Error('Network Error'));
    const { result } = renderHook(() => useMenuAvailability(7), { wrapper });
    await advance(0);
    await advance(10_000);

    expect(result.current.branches).toBeUndefined();
  });
});

describe('useMenuAvailability with real-time on', () => {
  beforeEach(() => {
    vi.stubEnv('VITE_REVERB_APP_KEY', 'app-key');
  });

  const connect = async () => {
    const view = renderHook(() => useMenuAvailability(7), { wrapper });
    await advance(0);
    const echo = echoInstances[echoInstances.length - 1] as FakeEcho;
    act(() => echo.connectAndSubscribe());
    return { ...view, echo };
  };

  it('listens on the public channel menu.7', async () => {
    const { echo } = await connect();

    expect(echo.firstChannel.name).toBe('menu.7');
  });

  it('refetches at once on menu.availability_changed', async () => {
    const { result, echo } = await connect();
    const before = availabilityCalls().length;

    stopped = [11];
    await act(async () => echo.emit('.menu.availability_changed', { store_id: 7, items: [{ type: 'variant', id: 11, available: false }] }));
    await advance(0);

    expect(availabilityCalls().length).toBe(before + 1);
    expect(result.current.branches?.[0].stopped.variants).toEqual([11]);
  });

  it('only polls as a safety net (every 60 s) while the socket is up, and every 20 s again when it drops', async () => {
    const { echo } = await connect();
    const before = availabilityCalls().length;

    await advance(40_000);
    expect(availabilityCalls().length).toBe(before);

    act(() => echo.setConnection('disconnected'));
    await advance(20_000);
    expect(availabilityCalls().length).toBeGreaterThan(before);
  });

  it('closes the channel on unmount', async () => {
    const { unmount, echo } = await connect();

    unmount();

    expect(echo.left).toContain('menu.7');
    expect(echo.disconnected).toBe(true);
  });
});
