import { afterEach, describe, expect, it, vi } from 'vitest';
import { FakeEcho } from '@/test/fakeEcho';
import { boardChannelNames, channelAuthorizer, openBoardChannels, parseBoardEvent, realtimeConfigFromEnv, RESUBSCRIBE_BASE_MS } from './realtime';

afterEach(() => vi.useRealTimers());

describe('realtime config', () => {
  it('is off without a key, and fills the host, port and auth endpoint from the API address', () => {
    expect(realtimeConfigFromEnv({}, 'https://api.rizqim.uz/api')).toBeNull();
    expect(realtimeConfigFromEnv({ REVERB_APP_KEY: 'k' }, 'https://api.rizqim.uz/api/')).toEqual({
      key: 'k',
      host: 'api.rizqim.uz',
      port: 443,
      forceTLS: true,
      authEndpoint: 'https://api.rizqim.uz/api/broadcasting/auth',
    });
    expect(realtimeConfigFromEnv({ REVERB_APP_KEY: 'k', REVERB_HOST: 'ws.local', REVERB_PORT: '8080', REVERB_SCHEME: 'http' }, 'http://127.0.0.1:8000/api')).toMatchObject({
      host: 'ws.local',
      port: 8080,
      forceTLS: false,
    });
  });
});

describe('board channels', () => {
  const owner = { role: 'owner', storeId: 3, branchId: null, branchScoped: false };
  it('listens on the branch channel for one branch and on the store channel for all of them', () => {
    expect(boardChannelNames(owner, 5, [5, 6])).toEqual(['pos-branch.5']);
    expect(boardChannelNames(owner, null, [5, 6])).toEqual(['store.3']);
    expect(boardChannelNames({ role: 'manager', storeId: 3, branchId: null, branchScoped: false }, null, [5])).toEqual(['store.3']);
  });

  it('keeps branch staff on their branch, and store-wide cashiers on every branch channel', () => {
    expect(boardChannelNames({ role: 'cook', storeId: 3, branchId: 6, branchScoped: true }, null, [6])).toEqual(['pos-branch.6']);
    expect(boardChannelNames({ role: 'cashier', storeId: 3, branchId: null, branchScoped: false }, null, [5, 6, 5])).toEqual(['pos-branch.5', 'pos-branch.6']);
  });

  it('reads an event and tolerates a bad payload', () => {
    expect(parseBoardEvent('order.created', { order: { uuid: 'u', status: 'new', branch_id: 5 } })).toEqual({ name: 'order.created', uuid: 'u', status: 'new', branchId: 5 });
    expect(parseBoardEvent('order.assigned', 'nonsense')).toEqual({ name: 'order.assigned', uuid: null, status: null, branchId: null });
  });
});

describe('openBoardChannels', () => {
  it('subscribes, reports the connection, and passes the four order events on', () => {
    const echo = new FakeEcho();
    const onEvent = vi.fn();
    const onConnectionChange = vi.fn();
    openBoardChannels(echo, ['pos-branch.5'], { onEvent, onConnectionChange, onResync: vi.fn() });
    const channel = echo.channel('pos-branch.5');
    expect([...channel.listeners.keys()]).toEqual(['.order.created', '.order.status_changed', '.order.assigned', '.kitchen.item_status_changed']);

    echo.connectAll();
    expect(onConnectionChange).toHaveBeenLastCalledWith(true);
    channel.emit('kitchen.item_status_changed', { order: { uuid: 'u1', status: 'preparing', branch_id: 5 }, item: { id: 1 } });
    expect(onEvent).toHaveBeenCalledWith({ name: 'kitchen.item_status_changed', uuid: 'u1', status: 'preparing', branchId: 5 });
  });

  it('says when the socket drops and asks for a full refetch after it is back', () => {
    const echo = new FakeEcho();
    const onConnectionChange = vi.fn();
    const onResync = vi.fn();
    openBoardChannels(echo, ['store.3'], { onEvent: vi.fn(), onConnectionChange, onResync });
    echo.connectAll();
    expect(onResync).not.toHaveBeenCalled();
    echo.setConnection('disconnected');
    expect(onConnectionChange).toHaveBeenLastCalledWith(false);
    echo.connectAll(); // pusher-js reconnects and subscribes again
    expect(onConnectionChange).toHaveBeenLastCalledWith(true);
    expect(onResync).toHaveBeenCalledTimes(1);
  });

  it('retries a refused subscription with a growing delay, and stops once closed', () => {
    vi.useFakeTimers();
    const echo = new FakeEcho();
    const session = openBoardChannels(echo, ['pos-branch.5'], { onEvent: vi.fn(), onConnectionChange: vi.fn(), onResync: vi.fn() });
    echo.channel('pos-branch.5').onError?.(new Error('403'));
    expect(echo.left).toEqual(['pos-branch.5']);
    vi.advanceTimersByTime(RESUBSCRIBE_BASE_MS);
    expect(echo.channels).toHaveLength(2);
    echo.channel('pos-branch.5').onError?.(new Error('403'));
    vi.advanceTimersByTime(RESUBSCRIBE_BASE_MS);
    expect(echo.channels).toHaveLength(2); // the second wait is twice as long
    vi.advanceTimersByTime(RESUBSCRIBE_BASE_MS);
    expect(echo.channels).toHaveLength(3);

    session.close();
    expect(echo.disconnected).toBe(true);
    echo.channel('pos-branch.5').onError?.(new Error('late'));
    vi.advanceTimersByTime(10 * RESUBSCRIBE_BASE_MS);
    expect(echo.channels).toHaveLength(3);
  });

  it('ignores events after closing', () => {
    const echo = new FakeEcho();
    const onEvent = vi.fn();
    const session = openBoardChannels(echo, ['pos-branch.5'], { onEvent, onConnectionChange: vi.fn(), onResync: vi.fn() });
    session.close();
    echo.channel('pos-branch.5').emit('order.created', { order: { uuid: 'x', status: 'new' } });
    expect(onEvent).not.toHaveBeenCalled();
  });
});

describe('channelAuthorizer', () => {
  it('signs the subscription through the session fetch', async () => {
    const send = vi.fn(async (request: Request) => {
      expect(request.method).toBe('POST');
      expect(await request.text()).toBe('socket_id=1.2&channel_name=private-pos-branch.5');
      return new Response(JSON.stringify({ auth: 'key:sig' }), { status: 200 });
    });
    const callback = vi.fn();
    channelAuthorizer('https://api.test/api/broadcasting/auth', send)({ socketId: '1.2', channelName: 'private-pos-branch.5' }, callback);
    await vi.waitFor(() => expect(callback).toHaveBeenCalledWith(null, { auth: 'key:sig' }));
  });

  it('reports a refusal as an error', async () => {
    const callback = vi.fn();
    channelAuthorizer('https://api.test/x', async () => new Response('', { status: 403 }))({ socketId: '1', channelName: 'c' }, callback);
    await vi.waitFor(() => expect(callback).toHaveBeenCalledWith(expect.any(Error), null));
  });
});
