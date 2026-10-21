import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Chime } from '@rizqim/staff';
import { ORDER_SOUND_KEY, useNewOrderChime } from './useNewOrderChime';

function fakeChime(ready = true): Chime & { play: ReturnType<typeof vi.fn>; close: ReturnType<typeof vi.fn>; unlock: ReturnType<typeof vi.fn> } {
  const chime = {
    ready,
    play: vi.fn(() => true),
    close: vi.fn(),
    unlock: vi.fn(async () => {
      chime.ready = true;
      return true;
    }),
  };
  return chime;
}

const order = (uuid: string, status = 'new') => ({ uuid, status });

beforeEach(() => localStorage.clear());
afterEach(() => vi.unstubAllGlobals());

describe('useNewOrderChime', () => {
  it('chimes for a new order that was not on the list, never for what was there at first', () => {
    const chime = fakeChime();
    const { rerender } = renderHook(({ orders }) => useNewOrderChime(orders, 'all', chime), { initialProps: { orders: [order('a')] } });
    expect(chime.play).not.toHaveBeenCalled();

    rerender({ orders: [order('a'), order('b')] });
    expect(chime.play).toHaveBeenCalledTimes(1);

    // An order moving on (accepted) or one that is not new does not chime
    rerender({ orders: [order('a', 'accepted'), order('b'), order('c', 'preparing')] });
    expect(chime.play).toHaveBeenCalledTimes(1);
  });

  it('announces nothing when the list becomes another branch\'s', () => {
    const chime = fakeChime();
    const { rerender } = renderHook(({ orders, scope }) => useNewOrderChime(orders, scope, chime), { initialProps: { orders: [order('a')], scope: '1' } });
    rerender({ orders: [order('x'), order('y')], scope: '2' });
    expect(chime.play).not.toHaveBeenCalled();
  });

  it('stays silent while muted, and remembers the switch on this phone', () => {
    const chime = fakeChime();
    const { result, rerender } = renderHook(({ orders }) => useNewOrderChime(orders, 'all', chime), { initialProps: { orders: [order('a')] } });
    act(() => result.current.toggleMuted());
    expect(result.current.muted).toBe(true);
    expect(localStorage.getItem(ORDER_SOUND_KEY)).toBe('off');

    rerender({ orders: [order('a'), order('b')] });
    expect(chime.play).not.toHaveBeenCalled();
  });

  it('asks for a tap until Telegram allows sound, unlocks on it, and gives the audio back on leaving', async () => {
    vi.stubGlobal('AudioContext', class {});
    const chime = fakeChime(false);
    const { result, unmount } = renderHook(() => useNewOrderChime([], 'all', chime));
    expect(result.current.needsGesture).toBe(true);

    await act(async () => {
      window.dispatchEvent(new Event('click'));
    });
    expect(chime.unlock).toHaveBeenCalled();
    expect(result.current.needsGesture).toBe(false);

    unmount();
    expect(chime.close).toHaveBeenCalled();
  });
});
