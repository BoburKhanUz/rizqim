import { StrictMode, type ReactNode } from 'react';
import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setAnalyticsAuth, track } from '../lib/analytics';
import { isTrackedUser, trackAddToCart, useAnalyticsAuth, useTrackOnce, useTrackSearch } from './useAnalytics';

// The React side of the Mini App analytics: one event per real action (not per render or StrictMode effect run), the
// search without its text, and staff never tracked.

vi.mock('../lib/analytics', () => ({ track: vi.fn(), setAnalyticsAuth: vi.fn(), trackAppOpen: vi.fn() }));

const strict = ({ children }: { children: ReactNode }) => <StrictMode>{children}</StrictMode>;

beforeEach(() => {
  vi.mocked(track).mockClear();
  vi.mocked(setAnalyticsAuth).mockClear();
});

describe('useTrackOnce', () => {
  it('fires once per key under StrictMode and re-renders, again for a new key', () => {
    const fire = vi.fn();
    const { rerender } = renderHook(({ id }: { id: string | null }) => useTrackOnce(id, () => fire(id)), { wrapper: strict, initialProps: { id: null as string | null } });
    expect(fire).not.toHaveBeenCalled();

    rerender({ id: '12' });
    rerender({ id: '12' });
    rerender({ id: '12' });
    expect(fire.mock.calls).toEqual([['12']]);

    rerender({ id: '13' });
    expect(fire.mock.calls).toEqual([['12'], ['13']]);
  });
});

describe('useTrackSearch', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('sends one search once the typing has settled, with the count and the length only', () => {
    const { rerender } = renderHook(({ q, n, ready }: { q: string; n: number; ready: boolean }) => useTrackSearch(q, n, ready), {
      wrapper: strict,
      initialProps: { q: '', n: 0, ready: true },
    });
    for (const q of ['o', 'os', 'osh', 'osh ', 'osh m', 'osh markazi']) {
      rerender({ q, n: 9, ready: true });
      act(() => void vi.advanceTimersByTime(300));
    }
    expect(track).not.toHaveBeenCalled();

    rerender({ q: 'osh markazi', n: 4, ready: true });
    act(() => void vi.advanceTimersByTime(1_100));
    expect(vi.mocked(track).mock.calls).toEqual([['search', { props: { results: 4, length: 11 } }]]);
    expect(JSON.stringify(vi.mocked(track).mock.calls)).not.toContain('osh');

    // The same query again (a re-render, the results growing) is not another search
    rerender({ q: 'osh markazi', n: 6, ready: true });
    act(() => void vi.advanceTimersByTime(2_000));
    expect(track).toHaveBeenCalledTimes(1);

    // Cleared, nothing; typed again, a new one
    rerender({ q: '', n: 0, ready: true });
    act(() => void vi.advanceTimersByTime(2_000));
    rerender({ q: 'somsa', n: 2, ready: true });
    act(() => void vi.advanceTimersByTime(1_100));
    expect(vi.mocked(track).mock.calls[1]).toEqual(['search', { props: { results: 2, length: 5 } }]);
  });

  it('waits for the results to be there', () => {
    const { rerender } = renderHook(({ q, n, ready }: { q: string; n: number; ready: boolean }) => useTrackSearch(q, n, ready), {
      initialProps: { q: 'lavash', n: 0, ready: false },
    });
    act(() => void vi.advanceTimersByTime(2_000));
    expect(track).not.toHaveBeenCalled();
    rerender({ q: 'lavash', n: 3, ready: true });
    expect(vi.mocked(track).mock.calls).toEqual([['search', { props: { results: 3, length: 6 } }]]);
  });
});

describe('useAnalyticsAuth', () => {
  it('passes the token on once the sign-in has finished, for a customer', () => {
    const { rerender } = renderHook(({ busy }: { busy: boolean }) => useAnalyticsAuth(busy, 'tok', { role: 'customer', store_id: null }), { initialProps: { busy: true } });
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: null, allowed: null });
    rerender({ busy: false });
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: 'tok', allowed: true });
  });

  it.each([
    ['an owner', { role: 'owner' as const, store_id: 1 }],
    ['a manager', { role: 'manager' as const, store_id: 1 }],
    ['a courier', { role: 'courier' as const, store_id: 1 }],
    ['a customer account tied to a store', { role: 'customer' as const, store_id: 3 }],
  ])('marks %s as never tracked', (_label, user) => {
    renderHook(() => useAnalyticsAuth(false, 'tok', user));
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: 'tok', allowed: false });
    expect(isTrackedUser(user)).toBe(false);
  });

  it('holds while nobody is signed in', () => {
    renderHook(() => useAnalyticsAuth(false, null, null));
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: null, allowed: null });
  });
});

describe('trackAddToCart', () => {
  it('sends the store, the dish and the quantity of the line, nothing else of it', () => {
    trackAddToCart({ storeId: '7', productId: 31, quantity: 1.5 });
    expect(vi.mocked(track).mock.calls).toEqual([['add_to_cart', { store_id: 7, product_id: 31, props: { qty: 1.5 } }]]);
  });
});
