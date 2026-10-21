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

// The private channels carrying the order events this user may see (routes/channels.php), the main one first:
// owner -> the store (every branch's events, and the old branchless orders'), manager -> their branch's staff channel
// pos-branch.{id} (the store when they have no branch), courier -> their own, customer -> their own. An owner or manager can also be handed an order as its courier (assignable staff), and its events go to
// their own courier.{id} channel, which the channel rules let them join too: it comes second. Super admins have no
// order screen.
export function getOrderChannelNames(user: ChannelUser | null | undefined): string[] {
  if (!user) return [];
  const courierChannel = `courier.${user.id}`;
  switch (user.role) {
    case 'owner':
      return user.store_id ? [`store.${user.store_id}`, courierChannel] : [];
    case 'manager':
      if (user.branch_id) return user.store_id ? [`pos-branch.${user.branch_id}`, courierChannel] : [`pos-branch.${user.branch_id}`];
      return user.store_id ? [`store.${user.store_id}`, courierChannel] : [];
    case 'courier':
      return [courierChannel];
    default:
      return isCustomerRole(user.role) ? [`user.${user.id}`] : [];
  }
}

export const ORDER_EVENTS = ['order.created', 'order.status_changed', 'order.assigned'] as const;
export type OrderEventName = (typeof ORDER_EVENTS)[number];

// What the staff channels (store, branch, pos-branch) carry besides the order events: a cook marking a line ready or taking
// it back, and the stop-list / a batch changing what can be sold. Like the order events they only say "ask again".
export const KITCHEN_EVENT = 'kitchen.item_status_changed';
export const STAFF_ONLY_EVENTS = [KITCHEN_EVENT, 'menu.availability_changed'] as const;
export type StaffEventName = OrderEventName | (typeof STAFF_ONLY_EVENTS)[number];

// The channels that carry the staff-only events (the courier's and the customer's do not)
export const isStaffChannel = (name: string): boolean => /^(store|branch|pos-branch)\./.test(name);

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
  name: StaffEventName;
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

// The Reverb client of the app. Both libraries are imported here, on first use. The token is read for every private
// channel authorization (it can change after a re-login); public channels need none.
async function createEcho(config: RealtimeConfig, getToken: () => string | null) {
  const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);

  return new Echo({
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
}

const RESUBSCRIBE_BASE_MS = 5_000;
const RESUBSCRIBE_MAX_MS = 60_000;

interface ChannelState {
  name: string;
  subscribed: boolean;
  everSubscribed: boolean;
  failures: number;
  retryTimer: ReturnType<typeof setTimeout> | undefined;
}

// Connects to Reverb and listens for the order events on `channelNames` (one socket, a subscription per channel).
//  - `getToken` is read every time the server asks to authorize a channel (the token can change after a re-login);
//  - pusher-js reconnects by itself; every reconnect re-subscribes and reports through onConnectionChange/onResync;
//  - a refused subscription (expired token, 5xx) is retried with a growing delay until the session is closed;
//  - "connected" (which slows the polling down) means the socket is up and the FIRST channel is subscribed: the others
//    are extra (an owner's own courier channel), so one of them being refused costs nothing but its own events.
// Rejects when the libraries cannot be loaded or the client cannot be created: the caller keeps polling.
export async function openOrderChannels(
  config: RealtimeConfig,
  channelNames: readonly string[],
  getToken: () => string | null,
  handlers: OrderChannelHandlers,
): Promise<OrderChannelSession> {
  const echo = await createEcho(config, getToken);

  const channels: ChannelState[] = [...new Set(channelNames)].map((name) => ({
    name,
    subscribed: false,
    everSubscribed: false,
    failures: 0,
    retryTimer: undefined,
  }));
  const main = channels[0];

  let closed = false;
  let socketUp = false;
  let reported = false;

  const report = () => {
    const connected = socketUp && main !== undefined && main.subscribed;
    if (connected === reported) return;
    reported = connected;
    handlers.onConnectionChange(connected);
  };

  const subscribe = (state: ChannelState) => {
    const channel = echo.private(state.name);
    const events: readonly StaffEventName[] = isStaffChannel(state.name) ? [...ORDER_EVENTS, ...STAFF_ONLY_EVENTS] : ORDER_EVENTS;
    for (const name of events) {
      channel.listen(`.${name}`, (payload: unknown) => {
        if (!closed) handlers.onEvent({ name, order: parseOrderPayload(payload) });
      });
    }
    channel.subscribed(() => {
      if (closed) return;
      state.failures = 0;
      state.subscribed = true;
      report();
      if (state.everSubscribed) handlers.onResync();
      state.everSubscribed = true;
    });
    channel.error(() => {
      if (closed) return;
      state.subscribed = false;
      report();
      // pusher-js does not retry a refused subscription by itself
      echo.leave(state.name);
      const delay = Math.min(RESUBSCRIBE_BASE_MS * 2 ** state.failures, RESUBSCRIBE_MAX_MS);
      state.failures += 1;
      clearTimeout(state.retryTimer);
      state.retryTimer = setTimeout(() => {
        if (!closed) {
          state.everSubscribed = true; // whatever happened meanwhile was not heard
          subscribe(state);
        }
      }, delay);
    });
  };

  const stopWatching = echo.connector.onConnectionChange((status) => {
    socketUp = status === 'connected';
    if (!socketUp) for (const state of channels) state.subscribed = false; // pusher-js subscribes again on reconnect and says so
    report();
  });
  channels.forEach(subscribe);

  return {
    close: () => {
      if (closed) return;
      closed = true;
      stopWatching();
      for (const state of channels) {
        clearTimeout(state.retryTimer);
        echo.leave(state.name);
        state.subscribed = false;
      }
      echo.disconnect();
      socketUp = false;
      report();
    },
  };
}

// --- The menu's availability (public channel menu.{store_id}) ---

export const MENU_EVENT = 'menu.availability_changed';

export function getMenuChannelName(storeId: number | string): string {
  return `menu.${storeId}`;
}

export interface MenuChannelHandlers {
  // something on the menu became (un)available: ask GET /catalog/availability again
  onChange: () => void;
  // true once the socket is connected and the channel subscribed; false whenever either is lost
  onConnectionChange: (connected: boolean) => void;
  // the channel is subscribed again after having been lost: events may have been missed
  onResync: () => void;
}

// Listens for `menu.availability_changed` of a store on its public channel (availability is public data: no token). The
// event only says "something changed"; the app asks the availability endpoint again. Like the order channels it
// reports the connection state (the caller polls slowly while it is up), and re-subscribes by itself.
// Rejects when the libraries cannot be loaded or the client cannot be created: the caller keeps polling.
export async function openMenuChannel(
  config: RealtimeConfig,
  storeId: number | string,
  handlers: MenuChannelHandlers,
): Promise<OrderChannelSession> {
  const echo = await createEcho(config, () => null);
  const name = getMenuChannelName(storeId);

  let closed = false;
  let socketUp = false;
  let subscribed = false;
  let everSubscribed = false;
  let failures = 0;
  let retryTimer: ReturnType<typeof setTimeout> | undefined;
  let reported = false;

  const report = () => {
    const connected = socketUp && subscribed;
    if (connected === reported) return;
    reported = connected;
    handlers.onConnectionChange(connected);
  };

  const subscribe = () => {
    const channel = echo.channel(name);
    channel.listen(`.${MENU_EVENT}`, () => {
      if (!closed) handlers.onChange();
    });
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
      echo.leave(name);
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
      stopWatching();
      clearTimeout(retryTimer);
      echo.leave(name);
      echo.disconnect();
      socketUp = false;
      subscribed = false;
      report();
    },
  };
}
