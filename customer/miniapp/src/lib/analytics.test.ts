import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAnalyticsLoader } from './analytics';
import type { AnalyticsClient } from './analyticsClient';

// The small stub of the entry bundle: the analytics client (lib/analyticsClient.ts) is only downloaded when the build
// turns analytics on, and what the app tracks before it has loaded is buffered and handed over in order, with its time.

const T0 = Date.UTC(2026, 10, 24, 9, 0, 0);

function fakeClient(log: Array<[string, number, unknown]>): AnalyticsClient {
  let clock: number | null = null;
  const time = () => clock ?? Date.now();
  return {
    track: ((name: string, payload: unknown) => log.push([`track:${name}`, time(), payload])) as AnalyticsClient['track'],
    appOpen: (startParam, launch) => void log.push(['appOpen', time(), [startParam, launch]]),
    setAuth: (auth) => void log.push(['setAuth', time(), auth]),
    flush: async () => {},
    pending: () => 0,
    at: (at, fn) => {
      clock = at;
      try {
        fn();
      } finally {
        clock = null;
      }
    },
    dispose: () => {},
  };
}

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(T0);
});
afterEach(() => vi.useRealTimers());

describe('createAnalyticsLoader', () => {
  it('when off never loads the client and drops every call', async () => {
    const load = vi.fn();
    const analytics = createAnalyticsLoader(false, load);
    analytics.trackAppOpen('c_abc', 'launch');
    analytics.setAnalyticsAuth({ token: 't', allowed: true, userId: 1 });
    analytics.track('store_view', { store_id: 1 });
    await vi.advanceTimersByTimeAsync(1_000);
    expect(load).not.toHaveBeenCalled();
  });

  it('buffers the calls made before the client has loaded and replays them in order, each with its own time', async () => {
    const log: Array<[string, number, unknown]> = [];
    let resolve: (client: AnalyticsClient) => void = () => {};
    const load = vi.fn(() => new Promise<AnalyticsClient>((done) => (resolve = done)));
    const analytics = createAnalyticsLoader(true, load);

    analytics.trackAppOpen(null, 'launch-1');
    vi.setSystemTime(T0 + 100);
    analytics.track('store_view', { store_id: 1 });
    vi.setSystemTime(T0 + 200);
    analytics.setAnalyticsAuth({ token: 't', allowed: true, userId: 1 });
    expect(load).toHaveBeenCalledTimes(1);
    expect(log).toEqual([]);

    vi.setSystemTime(T0 + 900);
    resolve(fakeClient(log));
    await vi.advanceTimersByTimeAsync(0);
    analytics.track('product_view', { store_id: 1, product_id: 2 });

    expect(log).toEqual([
      ['appOpen', T0, [null, 'launch-1']],
      ['track:store_view', T0 + 100, { store_id: 1 }],
      ['setAuth', T0 + 200, { token: 't', allowed: true, userId: 1 }],
      ['track:product_view', T0 + 900, { store_id: 1, product_id: 2 }],
    ]);
    expect(load).toHaveBeenCalledTimes(1);
  });

  it('keeps at most a bounded number of events while loading (the launch and the sign-in are never dropped)', async () => {
    const log: Array<[string, number, unknown]> = [];
    let resolve: (client: AnalyticsClient) => void = () => {};
    const analytics = createAnalyticsLoader(true, () => new Promise<AnalyticsClient>((done) => (resolve = done)), 5);
    analytics.trackAppOpen(null, 'launch-1');
    for (let i = 1; i <= 20; i++) analytics.track('store_view', { store_id: i });
    analytics.setAnalyticsAuth({ token: 't', allowed: true, userId: 1 });
    resolve(fakeClient(log));
    await vi.advanceTimersByTimeAsync(0);
    expect(log.map(([name]) => name)).toEqual(['appOpen', 'track:store_view', 'track:store_view', 'track:store_view', 'track:store_view', 'setAuth']);
  });

  it('never throws into the app when the client cannot be loaded, and stops buffering', async () => {
    const analytics = createAnalyticsLoader(true, () => Promise.reject(new Error('chunk gone')));
    expect(() => analytics.trackAppOpen(null, null)).not.toThrow();
    await vi.advanceTimersByTimeAsync(0);
    expect(() => analytics.track('store_view', { store_id: 1 })).not.toThrow();
    expect(() => analytics.setAnalyticsAuth({ token: null, allowed: null, userId: null })).not.toThrow();
  });
});
