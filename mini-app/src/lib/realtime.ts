import { isCustomerRole } from './roles';
import type { OrderStatus, UserRole } from '../types';

// Real-time order updates over Laravel Reverb (laravel-echo + pusher-js). Both libraries are imported when the
// first channel is opened, so they are a separate chunk and never part of the entry bundle.
//
// The server only says "something about this order changed" (no personal data in the payload): the app reacts
// by refetching its order queries (hooks/useRealtimeOrders.ts).

export interface RealtimeConfig {
  key: string;
  host: string;
  port: number;
  forceTLS: boolean;
  authEndpoint: string;
}

type Env = Record<string, string | undefined>;

const DEFAULT_AUTH_PATH = '/api/broadcasting/auth';

// Where the broadcasting auth route lives: on the origin of the API when VITE_API_URL is absolute, else on this origin.
function resolveAuthEndpoint(apiUrl: string | undefined): string {
  if (apiUrl && /^https?:\/\//i.test(apiUrl)) {
    try {
      return new URL(DEFAULT_AUTH_PATH, apiUrl).toString();
    } catch {
      // not a usable URL: the API client would not work either
    }
  }
  return DEFAULT_AUTH_PATH;
}

// null (real-time off, polling only) unless VITE_REVERB_APP_KEY is set.
export function getRealtimeConfig(env: Env = import.meta.env, hostname: string = window.location.hostname): RealtimeConfig | null {
  const key = env.VITE_REVERB_APP_KEY?.trim();
  if (!key) return null;
  const port = Number(env.VITE_REVERB_PORT);
  return {
    key,
    host: env.VITE_REVERB_HOST?.trim() || hostname,
    port: Number.isInteger(port) && port > 0 ? port : 443,
    forceTLS: (env.VITE_REVERB_SCHEME?.trim() || 'https') === 'https',
    authEndpoint: resolveAuthEndpoint(env.VITE_API_URL),
  };
}

// The slice of the signed-in user that decides the channel.
export interface ChannelUser {
  id: number;
  role: UserRole | string;
  store_id: number | null;
  branch_id: number | null;
}

// The private channel carrying the order events this user may see (routes/channels.php), or null when there is none:
// owner -> the store, manager -> their branch (the store when they have no branch), courier -> their own, customer ->
// their own. Super admins have no order screen.
export function getOrderChannelName(user: ChannelUser | null | undefined): string | null {
  if (!user) return null;
  switch (user.role) {
    case 'owner':
      return user.store_id ? `store.${user.store_id}` : null;
    case 'manager':
      if (user.branch_id) return `branch.${user.branch_id}`;
      return user.store_id ? `store.${user.store_id}` : null;
    case 'courier':
      return `courier.${user.id}`;
    default:
      return isCustomerRole(user.role) ? `user.${user.id}` : null;
  }
}

export const ORDER_EVENTS = ['order.created', 'order.status_changed', 'order.assigned'] as const;
export type OrderEventName = (typeof ORDER_EVENTS)[number];

// What an order event carries: ids and state only.
export interface OrderEventOrder {
  uuid: string;
  status: OrderStatus;
  store_id: number;
  branch_id: number | null;
  courier_id: number | null;
  updated_at: string;
}

export interface OrderEvent {
  name: OrderEventName;
  order: OrderEventOrder | null; // null when the payload was not what the contract says (the event still counts)
}

export function parseOrderPayload(payload: unknown): OrderEventOrder | null {
  if (typeof payload !== 'object' || payload === null) return null;
  const order = (payload as { order?: unknown }).order;
  if (typeof order !== 'object' || order === null) return null;
  const { uuid, status } = order as Partial<OrderEventOrder>;
  return typeof uuid === 'string' && typeof status === 'string' ? (order as OrderEventOrder) : null;
}

export interface OrderChannelHandlers {
  onEvent: (event: OrderEvent) => void;
  // true once the socket is connected and the channel subscribed; false whenever either is lost
  onConnectionChange: (connected: boolean) => void;
  // events may have been missed: the channel is subscribed again after having been lost
  onResync: () => void;
}

export interface OrderChannelSession {
  close: () => void;
}

const RESUBSCRIBE_BASE_MS = 5_000;
const RESUBSCRIBE_MAX_MS = 60_000;

// Connects to Reverb and listens for the order events on `channelName`.
//  - `getToken` is read every time the server asks to authorize the channel (the token can change after a re-login);
//  - pusher-js reconnects by itself; every reconnect re-subscribes and reports through onConnectionChange/onResync;
//  - a refused subscription (expired token, 5xx) is retried with a growing delay until the session is closed.
// Rejects when the libraries cannot be loaded or the client cannot be created: the caller keeps polling.
export async function openOrderChannel(
  config: RealtimeConfig,
  channelName: string,
  getToken: () => string | null,
  handlers: OrderChannelHandlers,
): Promise<OrderChannelSession> {
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
    authEndpoint: config.authEndpoint,
    channelAuthorization: {
      transport: 'ajax',
      endpoint: config.authEndpoint,
      headers: {},
      headersProvider: () => {
        const token = getToken();
        return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' };
      },
    },
  });

  let closed = false;
  let socketUp = false;
  let subscribed = false;
  let everSubscribed = false;
  let reported = false;
  let failures = 0;
  let retryTimer: ReturnType<typeof setTimeout> | undefined;

  const report = () => {
    const connected = socketUp && subscribed;
    if (connected === reported) return;
    reported = connected;
    handlers.onConnectionChange(connected);
  };

  const subscribe = () => {
    const channel = echo.private(channelName);
    for (const name of ORDER_EVENTS) {
      channel.listen(`.${name}`, (payload: unknown) => {
        if (!closed) handlers.onEvent({ name, order: parseOrderPayload(payload) });
      });
    }
    channel.subscribed(() => {
      if (closed) return;
      failures = 0;
      subscribed = true;
      report();
      if (everSubscribed) handlers.onResync();
      everSubscribed = true;
    });
    channel.error(() => {
      if (closed) return;
      subscribed = false;
      report();
      // pusher-js does not retry a refused subscription by itself
      echo.leave(channelName);
      const delay = Math.min(RESUBSCRIBE_BASE_MS * 2 ** failures, RESUBSCRIBE_MAX_MS);
      failures += 1;
      clearTimeout(retryTimer);
      retryTimer = setTimeout(() => {
        if (!closed) {
          everSubscribed = true; // whatever happened meanwhile was not heard
          subscribe();
        }
      }, delay);
    });
  };

  const stopWatching = echo.connector.onConnectionChange((status) => {
    socketUp = status === 'connected';
    if (!socketUp) subscribed = false; // pusher-js subscribes again on reconnect and says so
    report();
  });
  subscribe();

  return {
    close: () => {
      if (closed) return;
      closed = true;
      clearTimeout(retryTimer);
      stopWatching();
      echo.leave(channelName);
      echo.disconnect();
      socketUp = false;
      subscribed = false;
      report();
    },
  };
}
