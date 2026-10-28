import { act, render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FakeEcho } from '@/test/fakeEcho';
import { createPlatformOps, setPlatformOps } from './platformOps';
import { usePlatformOps } from './usePlatformOps';

afterEach(() => {
  setPlatformOps(null);
  vi.useRealTimers();
});

function Probe({ onEvent, pollMs }: { onEvent: (payload: unknown) => void; pollMs?: number }) {
  const { live, status } = usePlatformOps(['ops.order.created', 'ops.order.updated'], onEvent, { pollMs });
  return <p data-testid="probe">{`${live ? 'live' : 'not-live'} ${status}`}</p>;
}

describe('usePlatformOps', () => {
  it('passes events on while live and polls with null while real-time is off', async () => {
    vi.useFakeTimers();
    const echo = new FakeEcho();
    setPlatformOps(createPlatformOps({ loadConfig: async () => ({ key: 'k', host: 'h', port: 443, forceTLS: true }), createEcho: async () => echo }));
    const onEvent = vi.fn();
    const view = render(<Probe onEvent={onEvent} pollMs={1000} />);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });
    expect(view.getByTestId('probe').textContent).toBe('not-live connecting');

    // Not live yet: the page is refreshed by polling
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });
    expect(onEvent).toHaveBeenLastCalledWith(null);
    const polled = onEvent.mock.calls.length;

    act(() => echo.connectAll());
    expect(view.getByTestId('probe').textContent).toBe('live live');
    act(() => echo.channel('platform.ops').emit('ops.order.updated', { order: { uuid: 'u' }, change: 'sla' }));
    expect(onEvent).toHaveBeenLastCalledWith({ order: { uuid: 'u' }, change: 'sla' });

    // Live: no more polling
    const afterEvent = onEvent.mock.calls.length;
    await act(async () => {
      await vi.advanceTimersByTimeAsync(5000);
    });
    expect(onEvent.mock.calls.length).toBe(afterEvent);
    expect(afterEvent).toBe(polled + 1);

    view.unmount();
  });

  it('keeps polling when Reverb is not configured', async () => {
    vi.useFakeTimers();
    setPlatformOps(createPlatformOps({ loadConfig: async () => null, createEcho: async () => new FakeEcho() }));
    const onEvent = vi.fn();
    const view = render(<Probe onEvent={onEvent} pollMs={1000} />);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(3000);
    });
    expect(view.getByTestId('probe').textContent).toBe('not-live off');
    expect(onEvent).toHaveBeenCalledTimes(3);
    view.unmount();
  });
});
