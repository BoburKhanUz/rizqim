import { QueryClient, QueryObserver, focusManager, onlineManager } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  getBackoffInterval,
  getConsecutiveFailures,
  getPollInterval,
  MAX_BACKOFF_MS,
  POLL_INTERVAL_MS,
  pollingQueryOptions,
  REALTIME_POLL_INTERVAL_MS,
} from './polling';

describe('getPollInterval', () => {
  it('is the screen\'s own interval without the socket', () => {
    expect(getPollInterval(8000, false)).toBe(8000);
  });

  it('stretches to a minute while the socket is connected', () => {
    expect(REALTIME_POLL_INTERVAL_MS).toBe(60_000);
    expect(getPollInterval(8000, true)).toBe(60_000);
    expect(getPollInterval(15_000, true)).toBe(60_000);
  });

  it('never makes a slower screen faster', () => {
    expect(getPollInterval(120_000, true)).toBe(120_000);
  });
});

describe('getBackoffInterval', () => {
  it('is the normal interval while nothing fails', () => {
    expect(getBackoffInterval(8000, 0)).toBe(8000);
    expect(getBackoffInterval(8000, -3)).toBe(8000);
  });

  it('doubles with every failure in a row', () => {
    expect(getBackoffInterval(8000, 1)).toBe(16000);
    expect(getBackoffInterval(8000, 2)).toBe(32000);
    expect(getBackoffInterval(8000, 3)).toBe(64000);
  });

  it('stops growing at the cap', () => {
    expect(getBackoffInterval(8000, 4)).toBe(MAX_BACKOFF_MS);
    expect(getBackoffInterval(8000, 50)).toBe(MAX_BACKOFF_MS);
    expect(getBackoffInterval(8000, 100_000)).toBe(MAX_BACKOFF_MS);
    expect(getBackoffInterval(1000, 5, 10_000)).toBe(10_000);
  });

  it('never goes below the normal interval, even with a cap that is lower', () => {
    expect(getBackoffInterval(8000, 3, 1000)).toBe(8000);
  });
});

describe('getConsecutiveFailures', () => {
  const queryState = (dataUpdateCount: number, errorUpdateCount: number) => ({ state: { dataUpdateCount, errorUpdateCount } });

  it('starts counting from the first time it looks at a query', () => {
    expect(getConsecutiveFailures(queryState(0, 0))).toBe(0);
    // A query that already failed before anybody polled it is not punished retroactively
    expect(getConsecutiveFailures(queryState(2, 5))).toBe(0);
  });

  it('counts failures in a row and forgets them after a success', () => {
    const query = queryState(1, 0);
    expect(getConsecutiveFailures(query)).toBe(0);

    query.state.errorUpdateCount = 1;
    expect(getConsecutiveFailures(query)).toBe(1);
    query.state.errorUpdateCount = 2;
    expect(getConsecutiveFailures(query)).toBe(2);
    expect(getConsecutiveFailures(query)).toBe(2); // looking again changes nothing

    query.state.dataUpdateCount = 2; // a poll succeeded
    expect(getConsecutiveFailures(query)).toBe(0);

    query.state.errorUpdateCount = 3; // and then it failed once more: 1 in a row, not 3 in total
    expect(getConsecutiveFailures(query)).toBe(1);
  });

  it('counts every query on its own', () => {
    const a = queryState(0, 0);
    const b = queryState(0, 0);
    getConsecutiveFailures(a);
    getConsecutiveFailures(b);

    a.state.errorUpdateCount = 2;

    expect(getConsecutiveFailures(a)).toBe(2);
    expect(getConsecutiveFailures(b)).toBe(0);
  });
});

describe('pollingQueryOptions', () => {
  it('never polls in the background and refreshes stale data on focus and reconnect', () => {
    const options = pollingQueryOptions(8000);

    expect(options.refetchIntervalInBackground).toBe(false);
    expect(options.refetchOnWindowFocus).toBe(true);
    expect(options.refetchOnReconnect).toBe(true);
    expect(options.staleTime).toBe(8000); // data older than one interval counts as stale
  });

  it('does not multiply every failed poll by react-query\'s default of 3 retries', () => {
    expect(pollingQueryOptions(8000).retry).toBe(1);
  });

  it('asks react-query for the normal interval first, then the backed-off one', () => {
    const { refetchInterval } = pollingQueryOptions(1000, 8000);
    const query = { state: { dataUpdateCount: 1, errorUpdateCount: 0 } };

    expect(refetchInterval(query)).toBe(1000);
    query.state.errorUpdateCount = 1;
    expect(refetchInterval(query)).toBe(2000);
  });

  it('keeps the intervals the screens had before, as the one place to change them', () => {
    expect(POLL_INTERVAL_MS).toEqual({
      customerOrders: 10_000,
      managerOrders: 8_000,
      courierOrders: 15_000,
      ownerAnalytics: 60_000,
      notifications: 15_000,
      ownerFinance: 10_000,
    });
  });
});

// The same options on a real react-query observer, with the clock under control
describe('polling a real query', () => {
  const BASE = 1000;
  const MAX = 8000;
  let client: QueryClient;
  let calls: number;
  let failing: boolean;
  let unsubscribe: () => void;

  function startPolling() {
    const observer = new QueryObserver(client, {
      queryKey: ['poll-under-test'],
      queryFn: async () => {
        calls += 1;
        if (failing) throw new Error('server down');
        return calls;
      },
      ...pollingQueryOptions(BASE, MAX),
      retry: false, // one attempt per poll keeps the timeline readable
      gcTime: 500, // so that the cache's own cleanup timer is gone shortly after the last observer
    });
    unsubscribe = observer.subscribe(() => {});
  }

  const advance = (ms: number) => vi.advanceTimersByTimeAsync(ms);

  beforeEach(async () => {
    vi.useFakeTimers();
    client = new QueryClient();
    client.mount(); // what <QueryClientProvider> does: listens to focus and connection changes
    calls = 0;
    failing = false;
    startPolling();
    await advance(0); // the first load
  });

  afterEach(() => {
    unsubscribe();
    client.unmount();
    client.clear();
    focusManager.setFocused(undefined);
    onlineManager.setOnline(true);
    vi.useRealTimers();
  });

  it('polls once per interval while the page is visible', async () => {
    expect(calls).toBe(1);

    await advance(BASE * 3);

    expect(calls).toBe(4);
  });

  it('waits twice, four times, then up to the cap longer after each failed poll', async () => {
    failing = true;
    await advance(BASE); // poll 2 fails
    expect(calls).toBe(2);

    await advance(2 * BASE - 1);
    expect(calls).toBe(2);
    await advance(1); // poll 3, 2s later, fails
    expect(calls).toBe(3);

    await advance(4 * BASE - 1);
    expect(calls).toBe(3);
    await advance(1); // poll 4, 4s later, fails
    expect(calls).toBe(4);

    await advance(MAX - 1); // the next wait would be 8s: exactly the cap
    expect(calls).toBe(4);
    await advance(1); // poll 5
    expect(calls).toBe(5);

    await advance(MAX); // and it stays at the cap
    expect(calls).toBe(6);
  });

  it('is back at the normal interval after the first successful poll', async () => {
    failing = true;
    await advance(BASE + 2 * BASE + 4 * BASE); // polls 2, 3 and 4 fail
    expect(calls).toBe(4);

    failing = false;
    await advance(MAX); // poll 5 succeeds
    expect(calls).toBe(5);

    await advance(BASE * 3);
    expect(calls).toBe(8);
  });

  it('does not poll while the page is hidden, and refreshes at once when it is shown again', async () => {
    focusManager.setFocused(false);
    await advance(BASE * 20);
    expect(calls).toBe(1);

    focusManager.setFocused(true);
    await advance(0);
    expect(calls).toBe(2); // the data was older than one interval

    await advance(BASE);
    expect(calls).toBe(3); // and polling carries on
  });

  it('does not refetch on focus when the data is still fresh', async () => {
    focusManager.setFocused(false);
    await advance(BASE / 2);
    focusManager.setFocused(true);
    await advance(0);

    expect(calls).toBe(1);
  });

  it('refreshes stale data when the connection comes back', async () => {
    onlineManager.setOnline(false);
    await advance(BASE * 5);
    const callsOffline = calls;

    onlineManager.setOnline(true);
    await advance(0);

    expect(calls).toBeGreaterThan(callsOffline);
  });

  it('stops for good when the screen unmounts', async () => {
    unsubscribe();
    const before = calls;

    await advance(BASE * 10);

    expect(calls).toBe(before);
    expect(vi.getTimerCount()).toBe(0);
  });
});
