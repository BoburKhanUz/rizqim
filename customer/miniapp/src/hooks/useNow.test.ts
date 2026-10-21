import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useNow } from './useNow';

describe('useNow', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    vi.setSystemTime(new Date('2026-10-14T10:00:00.000Z'));
  });
  afterEach(() => vi.useRealTimers());

  it('starts at the current time and moves on every interval', () => {
    const { result } = renderHook(() => useNow(30_000));
    const start = result.current;
    expect(start).toBe(Date.parse('2026-10-14T10:00:00.000Z'));

    act(() => vi.advanceTimersByTime(30_000));
    expect(result.current).toBe(start + 30_000);

    act(() => vi.advanceTimersByTime(60_000));
    expect(result.current).toBe(start + 90_000);
  });

  it('stops when the screen is unmounted', () => {
    const { unmount } = renderHook(() => useNow(1000));
    unmount();
    expect(vi.getTimerCount()).toBe(0);
  });
});
