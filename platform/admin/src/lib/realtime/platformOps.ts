// The team's live channel `platform.ops` (P4-14; backend App\Events\Broadcast\PlatformOpsBroadcast, routes/channels.php:
// admin token, admin.today.view). One socket for the whole app, opened when the first page listens and closed a little
// after the last one stops. Events only say "this changed" (ids and statuses): pages refetch through the API.
//
// Real-time is never relied on. Without a configuration, when the libraries cannot load, when Reverb is down or the
// subscription is refused, the status says so and usePlatformOps polls instead; after a reconnect it asks for a full
// refetch (events may have been missed). laravel-echo and pusher-js load on first use (a separate chunk).

import { AUTH_ENDPOINT, isRealtimeConfig, type RealtimeConfig } from './config';

export const PLATFORM_OPS_CHANNEL = 'platform.ops';

// The events of the stage B sections (docs/API.md): Buyurtmalar (P4-14), Monitoring (P4-15); `ops.ping` is the test event
export const OPS_EVENTS = {
  orderCreated: 'ops.order.created',
  orderUpdated: 'ops.order.updated',
  ping: 'ops.ping',
} as const;

// off: no real-time here (not configured, or the libraries failed to load); connecting; live: socket up and subscribed;
// down: lost or refused (retried by itself)
export type OpsStatus = 'off' | 'connecting' | 'live' | 'down';

// The slice of laravel-echo used here (a fake one in the tests)
export interface EchoChannelLike {
  listen(event: string, callback: (payload: unknown) => void): EchoChannelLike;
  subscribed(callback: () => void): EchoChannelLike;
  error(callback: (error: unknown) => void): EchoChannelLike;
}

export interface EchoLike {
  private(channel: string): EchoChannelLike;
  leave(channel: string): void;
  disconnect(): void;
  connector: { onConnectionChange(callback: (status: string) => void): () => void };
}

export interface PlatformOps {
  // Calls `listener` with each payload of `event`; returns the unsubscribe
  listen(event: string, listener: (payload: unknown) => void): () => void;
  // Called on every status change (and once at once with the current one)
  onStatus(listener: (status: OpsStatus) => void): () => void;
  // Called when the channel is subscribed again after having been lost: refetch everything
  onResync(listener: () => void): () => void;
  status(): OpsStatus;
}

export interface PlatformOpsDeps {
  loadConfig: () => Promise<RealtimeConfig | null>;
  createEcho: (config: RealtimeConfig) => Promise<EchoLike>;
  // How long the socket stays open after the last listener left (page to page navigation)
  idleCloseMs?: number;
}

export const RESUBSCRIBE_BASE_MS = 5_000;
const RESUBSCRIBE_MAX_MS = 60_000;
const IDLE_CLOSE_MS = 10_000;

export function createPlatformOps(deps: PlatformOpsDeps): PlatformOps {
  const listeners = new Map<string, Set<(payload: unknown) => void>>();
  const statusListeners = new Set<(status: OpsStatus) => void>();
  const resyncListeners = new Set<() => void>();
  const listening = new Set<string>();
  let status: OpsStatus = 'off';
  let echo: EchoLike | null = null;
  let channel: EchoChannelLike | null = null;
  let starting = false;
  let socketUp = false;
  let subscribed = false;
  let everSubscribed = false;
  let failures = 0;
  let retryTimer: ReturnType<typeof setTimeout> | undefined;
  let idleTimer: ReturnType<typeof setTimeout> | undefined;
  let stopWatching: (() => void) | null = null;
  let generation = 0;

  const setStatus = (next: OpsStatus) => {
    if (next === status) return;
    status = next;
    statusListeners.forEach((listener) => listener(next));
  };
  // Until the first definite answer (live, a refused subscription, the socket unavailable) a page shows "connecting"
  let settled = false;
  const report = () => {
    if (!echo) return;
    if (socketUp && subscribed) settled = true;
    setStatus(socketUp && subscribed ? 'live' : settled ? 'down' : 'connecting');
  };

  const attach = (event: string) => {
    if (!channel || listening.has(event)) return;
    listening.add(event);
    // The broadcastAs() names carry no namespace: Echo needs the leading dot
    channel.listen(`.${event}`, (payload: unknown) => listeners.get(event)?.forEach((listener) => listener(payload)));
  };

  const subscribe = () => {
    if (!echo) return;
    listening.clear();
    channel = echo.private(PLATFORM_OPS_CHANNEL);
    for (const event of listeners.keys()) attach(event);
    const mine = generation;
    channel.subscribed(() => {
      if (mine !== generation) return;
      failures = 0;
      subscribed = true;
      report();
      if (everSubscribed) resyncListeners.forEach((listener) => listener());
      everSubscribed = true;
    });
    channel.error(() => {
      if (mine !== generation || !echo) return;
      subscribed = false;
      settled = true;
      report();
      // pusher-js does not retry a refused subscription by itself (an ended session, a 403, a 5xx)
      echo.leave(PLATFORM_OPS_CHANNEL);
      channel = null;
      const delay = Math.min(RESUBSCRIBE_BASE_MS * 2 ** failures, RESUBSCRIBE_MAX_MS);
      failures += 1;
      clearTimeout(retryTimer);
      retryTimer = setTimeout(() => {
        if (mine !== generation) return;
        everSubscribed = true; // whatever happened meanwhile was not heard
        subscribe();
      }, delay);
    });
  };

  const start = async () => {
    if (echo || starting) return;
    starting = true;
    const mine = ++generation;
    setStatus('connecting');
    try {
      const config = await deps.loadConfig();
      if (mine !== generation) return;
      if (!config) return setStatus('off');
      const created = await deps.createEcho(config);
      if (mine !== generation) return created.disconnect();
      echo = created;
      stopWatching = echo.connector.onConnectionChange((state) => {
        if (mine !== generation) return;
        socketUp = state === 'connected';
        if (!socketUp) subscribed = false; // pusher-js subscribes again on reconnect and says so
        if (!['connecting', 'connected', 'initialized'].includes(state)) settled = true;
        report();
      });
      subscribe();
    } catch {
      if (mine === generation) setStatus('off');
    } finally {
      if (mine === generation) starting = false;
    }
  };

  const stop = () => {
    generation++;
    starting = false;
    clearTimeout(retryTimer);
    stopWatching?.();
    stopWatching = null;
    if (echo) {
      echo.leave(PLATFORM_OPS_CHANNEL);
      echo.disconnect();
    }
    echo = null;
    channel = null;
    listening.clear();
    socketUp = false;
    subscribed = false;
    everSubscribed = false;
    settled = false;
    failures = 0;
    setStatus('off');
  };

  const active = () => [...listeners.values()].some((set) => set.size > 0);

  return {
    listen(event, listener) {
      clearTimeout(idleTimer);
      if (!listeners.has(event)) listeners.set(event, new Set());
      listeners.get(event)!.add(listener);
      attach(event);
      void start();
      return () => {
        listeners.get(event)?.delete(listener);
        if (!active()) {
          clearTimeout(idleTimer);
          idleTimer = setTimeout(() => {
            if (!active()) stop();
          }, deps.idleCloseMs ?? IDLE_CLOSE_MS);
        }
      };
    },
    onStatus(listener) {
      statusListeners.add(listener);
      listener(status);
      return () => statusListeners.delete(listener);
    },
    onResync(listener) {
      resyncListeners.add(listener);
      return () => resyncListeners.delete(listener);
    },
    status: () => status,
  };
}

// --- The app's connection --------------------------------------------------------------------------------------------

// Signs the subscription through the proxy, as JSON (the proxy forwards JSON bodies only)
export function channelAuthorizer(endpoint = AUTH_ENDPOINT) {
  return (
    params: { socketId: string; channelName: string },
    callback: (error: Error | null, data: { auth: string; channel_data?: string } | null) => void,
  ) => {
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ socket_id: params.socketId, channel_name: params.channelName }),
    })
      .then(async (response) => {
        if (!response.ok) return callback(new Error(`Channel authorization failed: HTTP ${response.status}`), null);
        callback(null, (await response.json()) as { auth: string; channel_data?: string });
      })
      .catch((error: unknown) => callback(error instanceof Error ? error : new Error(String(error)), null));
  };
}

export async function loadRealtimeConfig(): Promise<RealtimeConfig | null> {
  const response = await fetch('/api/realtime', { cache: 'no-store', credentials: 'same-origin' });
  if (!response.ok) return null;
  const body = (await response.json().catch(() => null)) as { config?: unknown } | null;
  return isRealtimeConfig(body?.config) ? body.config : null;
}

export async function createEcho(config: RealtimeConfig): Promise<EchoLike> {
  const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);
  const echo = new Echo({
    broadcaster: 'reverb',
    key: config.key,
    Pusher,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.forceTLS,
    enabledTransports: ['ws', 'wss'],
    channelAuthorization: { customHandler: channelAuthorizer() },
  });
  return echo as unknown as EchoLike;
}

let shared: PlatformOps | null = null;

// The one connection of the app (tests put their own in with setPlatformOps)
export function getPlatformOps(): PlatformOps {
  shared ??= createPlatformOps({ loadConfig: loadRealtimeConfig, createEcho });
  return shared;
}

export function setPlatformOps(ops: PlatformOps | null): void {
  shared = ops;
}
