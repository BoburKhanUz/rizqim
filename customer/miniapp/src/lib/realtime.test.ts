import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { echoInstances, type FakeEcho } from '../test/fakeEcho';
import {
  getOrderChannelNames,
  getRealtimeConfig,
  openOrderChannels,
  parseOrderPayload,
  type OrderChannelHandlers,
  type RealtimeConfig,
} from './realtime';

vi.mock('laravel-echo', async () => ({ default: (await import('../test/fakeEcho')).FakeEcho }));
vi.mock('pusher-js', async () => ({ default: (await import('../test/fakeEcho')).FakePusher }));

describe('getRealtimeConfig', () => {
  it('is off without an app key: the screens just poll', () => {
    expect(getRealtimeConfig({}, 'app.example.uz')).toBeNull();
    expect(getRealtimeConfig({ VITE_REVERB_APP_KEY: '  ' }, 'app.example.uz')).toBeNull();
  });

  it('defaults to this host, port 443 and TLS', () => {
    expect(getRealtimeConfig({ VITE_REVERB_APP_KEY: 'key' }, 'app.example.uz')).toEqual({
      key: 'key',
      host: 'app.example.uz',
      port: 443,
      forceTLS: true,
      authEndpoint: '/api/broadcasting/auth',
    });
  });

  it('takes host, port and scheme from the environment', () => {
    const config = getRealtimeConfig(
      { VITE_REVERB_APP_KEY: 'key', VITE_REVERB_HOST: 'ws.example.uz', VITE_REVERB_PORT: '8080', VITE_REVERB_SCHEME: 'http' },
      'app.example.uz',
    );
    expect(config).toMatchObject({ host: 'ws.example.uz', port: 8080, forceTLS: false });
  });

  it('ignores a port that is not a number', () => {
    expect(getRealtimeConfig({ VITE_REVERB_APP_KEY: 'key', VITE_REVERB_PORT: 'abc' }, 'h')?.port).toBe(443);
  });

  it('authorizes channels on the origin of an absolute API url', () => {
    const config = getRealtimeConfig({ VITE_REVERB_APP_KEY: 'key', VITE_API_URL: 'https://api.example.uz/api/v1' }, 'app.example.uz');
    expect(config?.authEndpoint).toBe('https://api.example.uz/api/broadcasting/auth');
  });
});

describe('getOrderChannelNames', () => {
  it.each([
    // Staff who can be handed an order as its courier listen on their own courier channel too (routes/channels.php)
    [{ id: 7, role: 'owner', store_id: 3, branch_id: null }, ['store.3', 'courier.7']],
    [{ id: 7, role: 'owner', store_id: 3, branch_id: 9 }, ['store.3', 'courier.7']],
    [{ id: 7, role: 'manager', store_id: 3, branch_id: 9 }, ['branch.9', 'courier.7']],
    [{ id: 7, role: 'manager', store_id: 3, branch_id: null }, ['store.3', 'courier.7']],
    [{ id: 7, role: 'courier', store_id: 3, branch_id: null }, ['courier.7']],
    [{ id: 7, role: 'customer', store_id: null, branch_id: null }, ['user.7']],
    [{ id: 7, role: 'user', store_id: null, branch_id: null }, ['user.7']],
    [{ id: 7, role: 'super_admin', store_id: null, branch_id: null }, []],
    [{ id: 7, role: 'owner', store_id: null, branch_id: null }, []],
    [{ id: 7, role: 'manager', store_id: null, branch_id: null }, []],
  ])('%j -> %j', (user, expected) => {
    expect(getOrderChannelNames(user)).toEqual(expected);
  });

  it('has no channel for nobody', () => {
    expect(getOrderChannelNames(null)).toEqual([]);
  });
});

describe('parseOrderPayload', () => {
  it('reads the order out of the event', () => {
    const order = { uuid: 'u', status: 'new', store_id: 1, branch_id: null, courier_id: null, updated_at: 't' };
    expect(parseOrderPayload({ order })).toEqual(order);
  });

  it.each([null, undefined, 'x', {}, { order: null }, { order: { status: 'new' } }, { order: { uuid: 1, status: 'new' } }])(
    'gives null for %j',
    (payload) => {
      expect(parseOrderPayload(payload)).toBeNull();
    },
  );
});

describe('openOrderChannels', () => {
  const CONFIG: RealtimeConfig = { key: 'app-key', host: 'ws.example.uz', port: 443, forceTLS: true, authEndpoint: '/api/broadcasting/auth' };
  let token: string | null;
  let channels: string[];
  let handlers: { [K in keyof OrderChannelHandlers]: ReturnType<typeof vi.fn> };

  const open = async () => {
    const session = await openOrderChannels(CONFIG, channels, () => token, handlers as unknown as OrderChannelHandlers);
    return { session, echo: echoInstances[echoInstances.length - 1] as FakeEcho };
  };

  beforeEach(() => {
    echoInstances.length = 0;
    token = 'token-1';
    channels = ['store.3'];
    handlers = { onEvent: vi.fn(), onConnectionChange: vi.fn(), onResync: vi.fn() };
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('connects a reverb client with the configured host, port and transports', async () => {
    const { echo } = await open();

    expect(echo.options).toMatchObject({
      broadcaster: 'reverb',
      key: 'app-key',
      wsHost: 'ws.example.uz',
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/broadcasting/auth',
    });
    expect(echo.channel.name).toBe('store.3');
  });

  it('authorizes with the token the app has at that moment, not the one it had at start', async () => {
    const { echo } = await open();
    const { headersProvider } = echo.options.channelAuthorization as { headersProvider: () => Record<string, string> };

    expect(headersProvider()).toMatchObject({ Authorization: 'Bearer token-1' });
    token = 'token-2'; // re-authenticated after a 401
    expect(headersProvider()).toMatchObject({ Authorization: 'Bearer token-2' });
    token = null;
    expect(headersProvider()).not.toHaveProperty('Authorization');
  });

  it('listens for the three order events, with the leading dot that skips the namespace', async () => {
    const { echo } = await open();

    expect([...echo.channel.listeners.keys()].sort()).toEqual(['.order.assigned', '.order.created', '.order.status_changed']);
  });

  it('passes events on, with the order when the payload has one', async () => {
    const { echo } = await open();
    const order = { uuid: 'u1', status: 'accepted', store_id: 3, branch_id: null, courier_id: null, updated_at: 't' };

    echo.emit('.order.status_changed', { order });
    echo.emit('.order.created', { unexpected: true });

    expect(handlers.onEvent).toHaveBeenNthCalledWith(1, { name: 'order.status_changed', order });
    expect(handlers.onEvent).toHaveBeenNthCalledWith(2, { name: 'order.created', order: null });
  });

  it('is connected only once the socket is up and the channel accepted', async () => {
    const { echo } = await open();
    expect(handlers.onConnectionChange).not.toHaveBeenCalled();

    echo.setConnection('connected');
    expect(handlers.onConnectionChange).not.toHaveBeenCalled(); // not subscribed yet

    echo.acceptSubscription();
    expect(handlers.onConnectionChange).toHaveBeenCalledExactlyOnceWith(true);
    expect(handlers.onResync).not.toHaveBeenCalled(); // the first subscription is not a re-subscription
  });

  it('reports a lost connection and asks for a refetch once the channel is back', async () => {
    const { echo } = await open();
    echo.connectAndSubscribe();

    echo.setConnection('disconnected');
    expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(false);

    echo.setConnection('connecting'); // pusher-js retries by itself
    echo.setConnection('connected');
    expect(handlers.onConnectionChange).toHaveBeenCalledTimes(2); // still not subscribed: no flapping

    echo.acceptSubscription();
    expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(true);
    expect(handlers.onResync).toHaveBeenCalledOnce();
  });

  it('treats a failed connection like a lost one', async () => {
    const { echo } = await open();
    echo.connectAndSubscribe();

    echo.setConnection('failed');

    expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(false);
  });

  it('retries a refused subscription with a growing delay', async () => {
    vi.useFakeTimers();
    const { echo } = await open();
    echo.setConnection('connected');

    echo.refuseSubscription();
    expect(echo.left).toEqual(['store.3']);
    expect(echo.channels).toHaveLength(1);

    vi.advanceTimersByTime(4_999);
    expect(echo.channels).toHaveLength(1);
    vi.advanceTimersByTime(1);
    expect(echo.channels).toHaveLength(2); // subscribed again, with the listeners

    echo.refuseSubscription();
    vi.advanceTimersByTime(9_999);
    expect(echo.channels).toHaveLength(2);
    vi.advanceTimersByTime(1);
    expect(echo.channels).toHaveLength(3);

    echo.acceptSubscription();
    expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(true);
    expect(handlers.onResync).toHaveBeenCalledOnce(); // events were missed meanwhile
    expect([...echo.channel.listeners.keys()]).toHaveLength(3);
  });

  it('leaves, disconnects and goes quiet when closed', async () => {
    vi.useFakeTimers();
    const { session, echo } = await open();
    echo.connectAndSubscribe();

    session.close();

    expect(echo.left).toContain('store.3');
    expect(echo.disconnected).toBe(true);
    expect(echo.watchers).toBe(0);
    expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(false);

    const callsBefore = handlers.onConnectionChange.mock.calls.length;
    echo.emit('.order.created', { order: { uuid: 'u', status: 'new' } });
    echo.refuseSubscription();
    vi.advanceTimersByTime(120_000);
    expect(handlers.onEvent).not.toHaveBeenCalled();
    expect(echo.channels).toHaveLength(1); // no retry after close
    expect(handlers.onConnectionChange).toHaveBeenCalledTimes(callsBefore);
  });

  it('can be closed twice', async () => {
    const { session, echo } = await open();

    session.close();
    session.close();

    expect(echo.left).toEqual(['store.3']);
  });
  describe('with more than one channel', () => {
    const order = { uuid: 'u1', status: 'accepted', store_id: 3, branch_id: null, courier_id: 7, updated_at: 't' };

    beforeEach(() => {
      channels = ['store.3', 'courier.7'];
    });

    it('subscribes each channel on the one socket, with the three order events', async () => {
      const { echo } = await open();

      expect(echoInstances).toHaveLength(1);
      expect(echo.channels.map((channel) => channel.name)).toEqual(['store.3', 'courier.7']);
      expect([...echo.channelNamed('courier.7').listeners.keys()].sort()).toEqual(['.order.assigned', '.order.created', '.order.status_changed']);
    });

    it('passes on the events of every channel', async () => {
      const { echo } = await open();

      echo.emit('.order.assigned', { order }, 'courier.7');
      echo.emit('.order.status_changed', { order }, 'store.3');

      expect(handlers.onEvent).toHaveBeenNthCalledWith(1, { name: 'order.assigned', order });
      expect(handlers.onEvent).toHaveBeenNthCalledWith(2, { name: 'order.status_changed', order });
    });

    it('is connected once the first channel is accepted, whether or not the others are', async () => {
      const { echo } = await open();
      echo.setConnection('connected');

      echo.acceptSubscription('courier.7');
      expect(handlers.onConnectionChange).not.toHaveBeenCalled();

      echo.acceptSubscription('store.3');
      expect(handlers.onConnectionChange).toHaveBeenCalledExactlyOnceWith(true);
      expect(handlers.onResync).not.toHaveBeenCalled();
    });

    it('stays connected when only the extra channel is refused, and retries just that one', async () => {
      vi.useFakeTimers();
      const { echo } = await open();
      echo.connectAndSubscribe();
      handlers.onConnectionChange.mockClear();

      echo.refuseSubscription('courier.7');

      expect(echo.left).toEqual(['courier.7']);
      expect(handlers.onConnectionChange).not.toHaveBeenCalled();
      vi.advanceTimersByTime(5_000);
      expect(echo.channels.filter((channel) => channel.name === 'courier.7')).toHaveLength(2);
      expect(echo.channels.filter((channel) => channel.name === 'store.3')).toHaveLength(1);

      echo.acceptSubscription('courier.7');
      expect(handlers.onResync).toHaveBeenCalledOnce(); // events were missed meanwhile
    });

    it('is not connected while the first channel is refused', async () => {
      vi.useFakeTimers();
      const { echo } = await open();
      echo.connectAndSubscribe();

      echo.refuseSubscription('store.3');

      expect(handlers.onConnectionChange).toHaveBeenLastCalledWith(false);
    });

    it('leaves every channel and stops every retry when closed', async () => {
      vi.useFakeTimers();
      const { session, echo } = await open();
      echo.connectAndSubscribe();
      echo.refuseSubscription('courier.7');

      session.close();
      vi.advanceTimersByTime(120_000);

      expect(echo.left).toEqual(expect.arrayContaining(['store.3', 'courier.7']));
      expect(echo.disconnected).toBe(true);
      expect(echo.channels.filter((channel) => channel.name === 'courier.7')).toHaveLength(1);
    });

    it('asks to refetch when the socket came back and re-subscribed both channels', async () => {
      const { echo } = await open();
      echo.connectAndSubscribe();

      echo.setConnection('disconnected');
      echo.setConnection('connected');
      echo.acceptSubscription('store.3');
      echo.acceptSubscription('courier.7');

      expect(handlers.onResync).toHaveBeenCalledTimes(2);
    });
  });
});
