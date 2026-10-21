import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { makeAnalytics } from '../test/factories';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import { useOwnerAnalytics } from './useOwnerAnalytics';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

let calls: FakeCall[];
let serve: () => unknown;
const analyticsCalls = () => callsTo(calls, '/owner/analytics');

let client: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  client = new QueryClient();
  serve = () => success(makeAnalytics());
  ({ calls } = stubStaffApi(() => serve()));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  vi.useRealTimers();
});

describe('useOwnerAnalytics', () => {
  it('reads a period of a busy store with a single request', async () => {
    const { result } = renderHook(() => useOwnerAnalytics('yearly'), { wrapper });
    await settle();

    expect(analyticsCalls().map((call) => call.query)).toEqual([{ period: 'year' }]);
    expect(calls).toHaveLength(1);
    expect(result.current.data?.summary.revenue).toBe(130000);
  });

  it('asks for the period each timeframe means', async () => {
    for (const [timeframe, period] of [['today', 'today'], ['weekly', 'week'], ['monthly', 'month']] as const) {
      renderHook(() => useOwnerAnalytics(timeframe), { wrapper });
      await settle();
      expect(analyticsCalls().at(-1)?.query).toEqual({ period });
    }
  });

  it('sends the dates of a custom period', async () => {
    renderHook(() => useOwnerAnalytics('custom', { start: '2026-10-01', end: '2026-10-10' }), { wrapper });
    await settle();

    expect(analyticsCalls()[0]!.query).toEqual({ period: 'custom', from: '2026-10-01', to: '2026-10-10' });
  });

  it('does not ask for a custom period the server would refuse, and says why', async () => {
    const { result, rerender } = renderHook(({ end }) => useOwnerAnalytics('custom', { start: '2026-01-01', end }), {
      wrapper,
      initialProps: { end: '2026-12-31' },
    });
    await settle();

    expect(calls).toHaveLength(0);
    expect(result.current.rangeProblem).toBe('too_long');

    rerender({ end: '2025-12-31' });
    await settle();
    expect(result.current.rangeProblem).toBe('reversed');

    rerender({ end: '2026-01-20' });
    await settle();
    expect(result.current.rangeProblem).toBeNull();
    expect(analyticsCalls()).toHaveLength(1);
  });

  it('refreshes every minute, which is as long as the server keeps the figures', async () => {
    renderHook(() => useOwnerAnalytics('today'), { wrapper });
    await settle();
    expect(analyticsCalls()).toHaveLength(1);

    await settle(59_000);
    expect(analyticsCalls()).toHaveLength(1);
    await settle(1_000);
    expect(analyticsCalls()).toHaveLength(2);
  });

  it('keeps each timeframe in its own cache entry', async () => {
    const { rerender } = renderHook(({ timeframe }) => useOwnerAnalytics(timeframe), { wrapper, initialProps: { timeframe: 'today' as 'today' | 'weekly' } });
    await settle();
    rerender({ timeframe: 'weekly' });
    await settle();
    rerender({ timeframe: 'today' });
    await settle();

    expect(analyticsCalls()).toHaveLength(2); // back on "today" within the minute: from the cache
  });

  it('reports a failed request and keeps the last figures through a failed refresh', async () => {
    const { result } = renderHook(() => useOwnerAnalytics('today'), { wrapper });
    await settle();

    serve = () => reply(500);
    await settle(60_000 + 5_000);

    expect(result.current.isError).toBe(true);
    expect(result.current.data?.summary.revenue).toBe(130000);
  });
});
