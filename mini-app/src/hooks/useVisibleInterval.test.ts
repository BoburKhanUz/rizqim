import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useVisibleInterval } from './useVisibleInterval';

let hidden = false;

function setPageHidden(value: boolean) {
  hidden = value;
  document.dispatchEvent(new Event('visibilitychange'));
}

beforeEach(() => {
  vi.useFakeTimers();
  hidden = false;
  vi.spyOn(document, 'hidden', 'get').mockImplementation(() => hidden);
});

afterEach(() => {
  vi.useRealTimers();
});

describe('useVisibleInterval', () => {
  it('calls back every period while the page is visible', () => {
    const callback = vi.fn();
    renderHook(() => useVisibleInterval(callback, 1000));

    vi.advanceTimersByTime(3500);

    expect(callback).toHaveBeenCalledTimes(3);
  });

  it('stops while the page is hidden and starts again, a full period later, when it is visible', () => {
    const callback = vi.fn();
    renderHook(() => useVisibleInterval(callback, 1000));
    vi.advanceTimersByTime(1000);
    expect(callback).toHaveBeenCalledTimes(1);

    act(() => setPageHidden(true));
    vi.advanceTimersByTime(60_000);
    expect(callback).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(0); // nothing is even scheduled in the background

    act(() => setPageHidden(false));
    vi.advanceTimersByTime(999);
    expect(callback).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(1);
    expect(callback).toHaveBeenCalledTimes(2);
  });

  it('does not start when the page is already hidden on mount', () => {
    hidden = true;
    const callback = vi.fn();
    renderHook(() => useVisibleInterval(callback, 1000));

    vi.advanceTimersByTime(10_000);

    expect(callback).not.toHaveBeenCalled();
    expect(vi.getTimerCount()).toBe(0);
  });

  it('is switched off by a null delay and on again by a number', () => {
    const callback = vi.fn();
    const { rerender } = renderHook(({ delay }) => useVisibleInterval(callback, delay), {
      initialProps: { delay: null as number | null },
    });
    vi.advanceTimersByTime(10_000);
    expect(callback).not.toHaveBeenCalled();

    rerender({ delay: 500 });
    vi.advanceTimersByTime(1000);
    expect(callback).toHaveBeenCalledTimes(2);

    rerender({ delay: null });
    vi.advanceTimersByTime(10_000);
    expect(callback).toHaveBeenCalledTimes(2);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('always runs the latest callback without restarting the timer', () => {
    const first = vi.fn();
    const second = vi.fn();
    const { rerender } = renderHook(({ callback }) => useVisibleInterval(callback, 1000), {
      initialProps: { callback: first },
    });
    vi.advanceTimersByTime(600);

    rerender({ callback: second });
    vi.advanceTimersByTime(400); // the period that began with the first render ends here

    expect(first).not.toHaveBeenCalled();
    expect(second).toHaveBeenCalledTimes(1);
  });

  it('cleans up on unmount: no timer, no listener, no more calls', () => {
    const callback = vi.fn();
    const removeListener = vi.spyOn(document, 'removeEventListener');
    const { unmount } = renderHook(() => useVisibleInterval(callback, 1000));
    vi.advanceTimersByTime(1000);

    unmount();

    expect(vi.getTimerCount()).toBe(0);
    expect(removeListener).toHaveBeenCalledWith('visibilitychange', expect.any(Function));
    vi.advanceTimersByTime(10_000);
    act(() => setPageHidden(false)); // a late event does not bring it back
    vi.advanceTimersByTime(10_000);
    expect(callback).toHaveBeenCalledTimes(1);
  });
});
