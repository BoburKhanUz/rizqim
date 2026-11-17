import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createRefreshThrottle, spreadDelay } from './throttle';

beforeEach(() => {
  vi.useFakeTimers();
});
afterEach(() => {
  vi.useRealTimers();
});

describe('createRefreshThrottle', () => {
  it('a first request after quiet reads after the spread only', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 5_000, spreadMs: 1_000, random: () => 0.4 });
    throttle.request();
    expect(throttle.pending).toBe(true);
    vi.advanceTimersByTime(399);
    expect(run).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(run).toHaveBeenCalledTimes(1);
    expect(throttle.pending).toBe(false);
  });

  it('requests during the spread delay add nothing', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 5_000, spreadMs: 1_000, random: () => 0.5 });
    throttle.request();
    throttle.request();
    throttle.request();
    vi.advanceTimersByTime(500);
    expect(run).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(20_000);
    expect(run).toHaveBeenCalledTimes(1);
  });

  it('a burst during the window becomes one read at its end plus a new spread', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 5_000, spreadMs: 1_000, random: () => 0.25 });
    throttle.request();
    vi.advanceTimersByTime(250);
    expect(run).toHaveBeenCalledTimes(1);
    // the window runs from the read: 5 s
    for (let i = 0; i < 10; i++) {
      vi.advanceTimersByTime(400);
      throttle.request();
    }
    expect(run).toHaveBeenCalledTimes(1);
    expect(throttle.pending).toBe(true);
    vi.advanceTimersByTime(5_000 - 4_000 - 1); // the window ends at 5 s after the read
    expect(run).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(1 + 249);
    expect(run).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(1);
    expect(run).toHaveBeenCalledTimes(2);
    // and a new window starts from that read
    throttle.request();
    vi.advanceTimersByTime(4_999);
    expect(run).toHaveBeenCalledTimes(2);
    vi.advanceTimersByTime(1 + 250);
    expect(run).toHaveBeenCalledTimes(3);
  });

  it('a window without requests ends quietly, and the next request is prompt again', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 2_000, spreadMs: 500, random: () => 0 });
    throttle.request();
    vi.advanceTimersByTime(0);
    expect(run).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(2_000);
    expect(run).toHaveBeenCalledTimes(1);
    throttle.request();
    vi.advanceTimersByTime(0);
    expect(run).toHaveBeenCalledTimes(2);
  });

  it('flush reads at once, takes over what was scheduled and opens no window', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 5_000, spreadMs: 1_000, random: () => 0.9 });
    throttle.request();
    throttle.flush();
    expect(run).toHaveBeenCalledTimes(1);
    expect(throttle.pending).toBe(false);
    vi.advanceTimersByTime(10_000);
    expect(run).toHaveBeenCalledTimes(1);
    // during a window too: the trailing read is dropped, the flush covers it
    throttle.request();
    vi.advanceTimersByTime(900);
    expect(run).toHaveBeenCalledTimes(2);
    throttle.request();
    throttle.flush();
    expect(run).toHaveBeenCalledTimes(3);
    vi.advanceTimersByTime(20_000);
    expect(run).toHaveBeenCalledTimes(3);
    // no window after a flush: the next request waits for the spread only
    throttle.request();
    vi.advanceTimersByTime(900);
    expect(run).toHaveBeenCalledTimes(4);
  });

  it('cancel drops whatever is scheduled', () => {
    const run = vi.fn();
    const throttle = createRefreshThrottle(run, { windowMs: 5_000, spreadMs: 1_000, random: () => 0.5 });
    throttle.request();
    throttle.cancel();
    vi.advanceTimersByTime(10_000);
    expect(run).not.toHaveBeenCalled();
  });

  it('the spread stays within its bounds, whatever random() says', () => {
    expect(spreadDelay(1_500, () => 0)).toBe(0);
    expect(spreadDelay(1_500, () => 0.999999)).toBe(1_500);
    expect(spreadDelay(1_500, () => 2)).toBe(1_500);
    expect(spreadDelay(1_500, () => -1)).toBe(0);
    expect(spreadDelay(1_500, () => Number.NaN)).toBe(0);
    expect(spreadDelay(0, () => 0.7)).toBe(0);
    for (let i = 0; i < 200; i++) {
      const delay = spreadDelay(5_000);
      expect(delay).toBeGreaterThanOrEqual(0);
      expect(delay).toBeLessThanOrEqual(5_000);
    }
  });
});
