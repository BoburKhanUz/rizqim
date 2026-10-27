import { afterEach, describe, expect, it, vi } from 'vitest';
import { FakeEcho } from '@/test/fakeEcho';
import { realtimeConfigFromEnv } from './config';
import { channelAuthorizer, createPlatformOps, RESUBSCRIBE_BASE_MS, type OpsStatus } from './platformOps';

const CONFIG = { key: 'k', host: 'ws.rizqim.test', port: 443, forceTLS: true };
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

function setup(config: typeof CONFIG | null = CONFIG) {
  const echo = new FakeEcho();
  const createEcho = vi.fn(async () => echo);
  const ops = createPlatformOps({ loadConfig: async () => config, createEcho, idleCloseMs: 50 });
  const statuses: OpsStatus[] = [];
  ops.onStatus((status) => statuses.push(status));
  return { echo, ops, createEcho, statuses };
}

describe('realtime config', () => {
  it('is off without a key or a host, and defaults the port by scheme', () => {
    expect(realtimeConfigFromEnv({})).toBeNull();
    expect(realtimeConfigFromEnv({ REVERB_APP_KEY: 'k' })).toBeNull();
    expect(realtimeConfigFromEnv({ REVERB_APP_KEY: 'k', REVERB_HOST: 'ws.rizqim.uz' })).toEqual({ key: 'k', host: 'ws.rizqim.uz', port: 443, forceTLS: true });
    expect(realtimeConfigFromEnv({ REVERB_APP_KEY: 'k', REVERB_HOST: 'localhost', REVERB_PORT: '8080', REVERB_SCHEME: 'http' })).toEqual({ key: 'k', host: 'localhost', port: 8080, forceTLS: false });
  });
});

describe('platform.ops connection', () => {
  it('opens one socket on the first listener, subscribes to private platform.ops and passes the events on', async () => {
    const { echo, ops, createEcho, statuses } = setup();
    const created = vi.fn();
    const updated = vi.fn();
    ops.listen('ops.order.created', created);
    ops.listen('ops.order.updated', updated);
    await flush();
    expect(createEcho).toHaveBeenCalledTimes(1);
    expect(echo.channels.map((channel) => channel.name)).toEqual(['platform.ops']);
    expect([...echo.channel('platform.ops').listeners.keys()]).toEqual(['.ops.order.created', '.ops.order.updated']);
    expect(ops.status()).toBe('connecting');

    echo.connectAll();
    expect(statuses.at(-1)).toBe('live');
    echo.channel('platform.ops').emit('ops.order.created', { order: { uuid: 'u1' } });
    expect(created).toHaveBeenCalledWith({ order: { uuid: 'u1' } });
    expect(updated).not.toHaveBeenCalled();
  });

  it('is off without a configuration and never creates a socket', async () => {
    const { ops, createEcho } = setup(null);
    ops.listen('ops.order.created', vi.fn());
    await flush();
    expect(ops.status()).toBe('off');
    expect(createEcho).not.toHaveBeenCalled();
  });

  it('is off when the libraries cannot load', async () => {
    const ops = createPlatformOps({ loadConfig: async () => CONFIG, createEcho: async () => Promise.reject(new Error('chunk failed')) });
    ops.listen('ops.order.created', vi.fn());
    await flush();
    expect(ops.status()).toBe('off');
  });

  it('says when the socket drops and asks for a full refetch once it is back', async () => {
    const { echo, ops } = setup();
    const resync = vi.fn();
    ops.onResync(resync);
    ops.listen('ops.order.updated', vi.fn());
    await flush();
    echo.connectAll();
    echo.setConnection('unavailable');
    expect(ops.status()).toBe('down');
    echo.connectAll();
    expect(ops.status()).toBe('live');
    expect(resync).toHaveBeenCalledTimes(1);
  });

  it('retries a refused subscription with a growing delay', async () => {
    vi.useFakeTimers();
    const { echo, ops } = setup();
    ops.listen('ops.order.updated', vi.fn());
    await vi.advanceTimersByTimeAsync(0);
    echo.setConnection('connected');
    echo.channel('platform.ops').onError?.({ status: 403 });
    expect(ops.status()).toBe('down');
    expect(echo.left).toEqual(['platform.ops']);
    await vi.advanceTimersByTimeAsync(RESUBSCRIBE_BASE_MS);
    expect(echo.channels).toHaveLength(2);
    echo.channel('platform.ops').onSubscribed?.();
    expect(ops.status()).toBe('live');
  });

  it('closes the socket a little after the last listener left', async () => {
    vi.useFakeTimers();
    const { echo, ops } = setup();
    const stop = ops.listen('ops.order.updated', vi.fn());
    await vi.advanceTimersByTimeAsync(0);
    echo.connectAll();
    stop();
    expect(echo.disconnected).toBe(false);
    await vi.advanceTimersByTimeAsync(60);
    expect(echo.disconnected).toBe(true);
    expect(ops.status()).toBe('off');
  });

  it('signs the subscription through the proxy as JSON', async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ auth: 'key:sig' }), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const callback = vi.fn();
    channelAuthorizer()({ socketId: '1.2', channelName: 'private-platform.ops' }, callback);
    await flush();
    await flush();
    expect(fetchMock).toHaveBeenCalledWith('/api/backend/broadcasting/auth', expect.objectContaining({ method: 'POST', body: JSON.stringify({ socket_id: '1.2', channel_name: 'private-platform.ops' }) }));
    expect(callback).toHaveBeenCalledWith(null, { auth: 'key:sig' });

    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 403 }));
    const refused = vi.fn();
    channelAuthorizer()({ socketId: '1.2', channelName: 'private-platform.ops' }, refused);
    await flush();
    expect(refused.mock.calls[0]?.[0]).toBeInstanceOf(Error);
  });
});
