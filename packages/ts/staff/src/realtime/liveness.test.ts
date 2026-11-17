import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createSilenceWatch, fetchRealtimeStatus, onPageReturn, parseRealtimeStatus, reverbConnectionOf, spreadFirstReconnect, type PusherLike } from './liveness';

const STATUS = {
  success: true,
  message: '',
  data: {
    status: 'ok',
    live: true,
    heartbeat_seconds: 30,
    silence_seconds: 90,
    poll_seconds: { live: 60, fallback: 15 },
    reverb: { key: 'public-app-key', host: null, port: null, scheme: null },
  },
};

describe('GET /realtime', () => {
  it('parses the wrapped answer', () => {
    expect(parseRealtimeStatus(STATUS)).toEqual({
      live: true,
      heartbeatSeconds: 30,
      silenceSeconds: 90,
      pollLiveSeconds: 60,
      pollFallbackSeconds: 15,
      reverb: { key: 'public-app-key', host: null, port: null, scheme: null },
    });
  });

  it('real-time off: no reverb, not live', () => {
    const parsed = parseRealtimeStatus({ data: { ...STATUS.data, status: 'off', live: false, reverb: null } });
    expect(parsed?.live).toBe(false);
    expect(parsed?.reverb).toBeNull();
  });

  it('anything else is not a status', () => {
    expect(parseRealtimeStatus({ data: { status: 'ok' } })).toBeNull();
    expect(parseRealtimeStatus('nope')).toBeNull();
  });

  it('fetches {apiRoot}/v1/realtime without the HTTP cache and never throws', async () => {
    const send = vi.fn(async (request: Request) => {
      expect(request.url).toBe('https://api.test/api/v1/realtime');
      expect(request.cache).toBe('no-store');
      return new Response(JSON.stringify(STATUS), { status: 200 });
    });
    expect((await fetchRealtimeStatus('https://api.test/api/v1', send))?.live).toBe(true);
    expect(await fetchRealtimeStatus('https://api.test/api', async () => new Response('', { status: 404 }))).toBeNull();
    expect(
      await fetchRealtimeStatus('https://api.test/api', async () => {
        throw new TypeError('offline');
      }),
    ).toBeNull();
  });

  it('a null host is the API host, with its scheme and port', () => {
    const reverb = { key: 'k', host: null, port: null, scheme: null };
    expect(reverbConnectionOf(reverb, 'https://api.rizqim.uz/api')).toEqual({ key: 'k', host: 'api.rizqim.uz', port: 443, forceTLS: true });
    expect(reverbConnectionOf(reverb, 'http://127.0.0.1:8000/api')).toEqual({ key: 'k', host: '127.0.0.1', port: 8000, forceTLS: false });
    expect(reverbConnectionOf(reverb, '/api/v1', 'https://app.rizqim.uz/home')).toEqual({ key: 'k', host: 'app.rizqim.uz', port: 443, forceTLS: true });
  });

  it('the server names its own host, port and scheme', () => {
    expect(reverbConnectionOf({ key: 'k', host: 'ws.rizqim.uz', port: 6001, scheme: 'http' }, 'https://api.rizqim.uz/api')).toEqual({
      key: 'k',
      host: 'ws.rizqim.uz',
      port: 6001,
      forceTLS: false,
    });
    expect(reverbConnectionOf({ key: 'k', host: 'ws.rizqim.uz', port: null, scheme: 'https' }, 'http://127.0.0.1:8000/api')?.port).toBe(443);
  });
});

describe('silence watch', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('silent after the limit without anything, live again at the next heartbeat', () => {
    const changes: boolean[] = [];
    const watch = createSilenceWatch(90_000, (silent) => changes.push(silent));
    watch.start();
    vi.advanceTimersByTime(89_000);
    expect(changes).toEqual([]);
    watch.touch();
    vi.advanceTimersByTime(89_000);
    expect(changes).toEqual([]);
    vi.advanceTimersByTime(1_000);
    expect(changes).toEqual([true]);
    expect(watch.silent()).toBe(true);
    watch.touch();
    expect(changes).toEqual([true, false]);
  });

  it('no clock before start or after stop; disabled with 0', () => {
    const changes: boolean[] = [];
    const watch = createSilenceWatch(1_000, (silent) => changes.push(silent));
    watch.touch();
    vi.advanceTimersByTime(5_000);
    watch.start();
    watch.stop();
    vi.advanceTimersByTime(5_000);
    expect(changes).toEqual([]);
    const off = createSilenceWatch(0, (silent) => changes.push(silent));
    off.start();
    vi.advanceTimersByTime(500_000);
    expect(changes).toEqual([]);
  });
});

function fakePusher() {
  const listeners: Array<(states: { previous: string; current: string }) => void> = [];
  const pusher: PusherLike & { emit: (previous: string, current: string) => void } = {
    connect: vi.fn(),
    disconnect: vi.fn(),
    connection: {
      bind: (_event, callback) => listeners.push(callback),
      unbind: (_event, callback) => listeners.splice(listeners.indexOf(callback), 1),
    },
    emit: (previous, current) => listeners.forEach((listener) => listener({ previous, current })),
  };
  return pusher;
}

describe('reconnect spread', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('takes over the first reconnect after a drop and makes it at a random moment within 10 s', () => {
    const pusher = fakePusher();
    spreadFirstReconnect(pusher, 10_000, () => 0.73);
    pusher.emit('initialized', 'connecting');
    pusher.emit('connecting', 'connected');
    expect(pusher.disconnect).not.toHaveBeenCalled();

    pusher.emit('connected', 'connecting');
    expect(pusher.disconnect).toHaveBeenCalledTimes(1);
    // our own disconnect is not another drop
    pusher.emit('connecting', 'disconnected');
    vi.advanceTimersByTime(7_299);
    expect(pusher.connect).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(pusher.connect).toHaveBeenCalledTimes(1);
    // a failed attempt is pusher-js' own business again
    pusher.emit('connecting', 'unavailable');
    expect(pusher.disconnect).toHaveBeenCalledTimes(1);
  });

  it('spreads over the whole window across devices', () => {
    const delays = [0, 0.25, 0.5, 0.999].map((r) => {
      const pusher = fakePusher();
      spreadFirstReconnect(pusher, 10_000, () => r);
      pusher.emit('connected', 'unavailable');
      let waited = 0;
      while ((pusher.connect as ReturnType<typeof vi.fn>).mock.calls.length === 0 && waited < 20_000) {
        vi.advanceTimersByTime(10);
        waited += 10;
      }
      return waited;
    });
    expect(delays[0]).toBeLessThanOrEqual(10);
    expect(delays[3]).toBeGreaterThan(9_900);
    expect(delays[3]).toBeLessThanOrEqual(10_000);
  });

  it('dispose cancels the pending reconnect', () => {
    const pusher = fakePusher();
    const dispose = spreadFirstReconnect(pusher, 10_000, () => 0.5);
    pusher.emit('connected', 'connecting');
    dispose();
    vi.advanceTimersByTime(20_000);
    expect(pusher.connect).not.toHaveBeenCalled();
  });

  it('does nothing without a pusher client', () => {
    expect(() => spreadFirstReconnect(undefined)()).not.toThrow();
  });
});

describe('onPageReturn', () => {
  it('fires when the page becomes visible after being hidden', () => {
    let state: DocumentVisibilityState = 'visible';
    vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() => state);
    const onReturn = vi.fn();
    const stop = onPageReturn(onReturn);
    document.dispatchEvent(new Event('visibilitychange'));
    expect(onReturn).not.toHaveBeenCalled();
    state = 'hidden';
    document.dispatchEvent(new Event('visibilitychange'));
    state = 'visible';
    document.dispatchEvent(new Event('visibilitychange'));
    expect(onReturn).toHaveBeenCalledTimes(1);
    stop();
    state = 'hidden';
    document.dispatchEvent(new Event('visibilitychange'));
    state = 'visible';
    document.dispatchEvent(new Event('visibilitychange'));
    expect(onReturn).toHaveBeenCalledTimes(1);
  });
});
