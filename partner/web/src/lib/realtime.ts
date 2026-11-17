// Real-time order events of the board over Laravel Reverb (Pusher protocol; docs/API.md §8). The events only say "this
// order changed" (ids and status, no personal data): the board refetches the order and its lists through the API.
// Real-time is never relied on: the board also polls, and refetches everything after a reconnect.
//
// laravel-echo and pusher-js are imported when the first channel opens (a separate chunk). The subscription is signed
// by POST {api}/broadcasting/auth with the session's access token, through authFetch (which renews a stale token and
// replays the request), so a 15-minute token never breaks the socket.

export interface RealtimeConfig {
  key: string;
  host: string;
  port: number;
  forceTLS: boolean;
  // POST here signs a private channel subscription (Bearer token)
  authEndpoint: string;
}

type Env = Record<string, string | undefined>;

// From the runtime environment of the Next server (REVERB_APP_KEY, REVERB_HOST, REVERB_PORT, REVERB_SCHEME).
// null without a key: real-time is off and the board only polls. The host defaults to the API's host.
export function realtimeConfigFromEnv(env: Env, apiBaseUrl: string): RealtimeConfig | null {
  const key = env.REVERB_APP_KEY?.trim();
  if (!key) return null;
  let apiHost = '';
  try {
    apiHost = new URL(apiBaseUrl).hostname;
  } catch {
    // a relative API address: the host has to be given
  }
  const host = env.REVERB_HOST?.trim() || apiHost;
  if (!host) return null;
  const scheme = (env.REVERB_SCHEME?.trim() || 'https').toLowerCase();
  const port = Number(env.REVERB_PORT);
  return {
    key,
    host,
    port: Number.isInteger(port) && port > 0 ? port : scheme === 'https' ? 443 : 80,
    forceTLS: scheme === 'https',
    authEndpoint: `${apiBaseUrl.replace(/\/+$/, '')}/broadcasting/auth`,
  };
}

// --- Which channels ----------------------------------------------------------------------------------------------

export interface ChannelAccess {
  role: string;
  storeId: number | null;
  // The person's own branch (a branch-bound member of staff), null for store-wide staff
  branchId: number | null;
  branchScoped: boolean;
}

// The private channels the board listens on (routes/channels.php), without the "private-" prefix:
//  - one branch chosen (or a branch-bound person): pos-branch.{id}, open to everyone with orders.view there;
//  - all branches: the store channel for the owner and store-wide managers, else pos-branch.{id} of every branch.
export function boardChannelNames(access: ChannelAccess, selectedBranchId: number | null, branchIds: readonly number[]): string[] {
  const branchId = access.branchScoped ? access.branchId : selectedBranchId;
  if (branchId !== null) return [`pos-branch.${branchId}`];
  const storeWide = access.role === 'owner' || (access.role === 'manager' && access.branchId === null);
  if (storeWide && access.storeId !== null) return [`store.${access.storeId}`];
  return [...new Set(branchIds)].map((id) => `pos-branch.${id}`);
}

// --- The events --------------------------------------------------------------------------------------------------

// Wave M2: every 30 s on the staff channels, with the channel's token
export const HEARTBEAT_EVENT = 'realtime.heartbeat';
export const KITCHEN_ITEM_EVENT = 'kitchen.item_status_changed';

// Wave M2 fix round 2: the board hears EVERY event of its channels (laravel-echo listenToAll), so each one feeds the
// change tokens (lib/revisions.ts: an ignored event that moved `rev` unseen made the next relevant one look like a gap
// and every heartbeat like a missed change), but only the events below can change what it shows (GET /staff/orders*:
// status, courier, lines, totals, payment status, table; the presenter is StaffOrderPresenter). Dispatch sites are
// backend/app/Events/Broadcast and backend/app/Domain/Pos/**; all go to the order's staff channels (store, branch,
// pos-branch) unless said.
//
//   event                          board                    dispatched by / why an omission is safe
//   order.created                  read that order          OrderCreatedBroadcast (new order: open list, badge)
//   order.status_changed           read that order          OrderStatusChangedBroadcast (column, badge, finished)
//   order.assigned                 read that order          OrderAssignedBroadcast (courier on the card)
//   order.items_changed            read that order          OrderItemsChangedBroadcast (lines, guests, table, charges
//                                                           and so the totals; a POS cancel)
//   order.bill_changed  payment    read that order          PaymentService (payment_status on the card)
//                       voided/none read that order          a bill voided, or an older backend without `change`
//                       created, rebilled, printed, fiscal   BillService, BillFiscalizer: bills, print and receipt are
//                                                           not on the board; a total changes with items_changed
//   kitchen.item_status_changed    patched from payload     KitchenItemStatusChangedBroadcast (line state, progress;
//                                                           no read: the payload carries all of it)
//   kitchen.order_assembled        nothing                  assembly is the KDS's, not on the board
//   order.course_ready             nothing                  its lines come with kitchen.item_status_changed
//   order.bill_requested           nothing                  bill_requested_at is not on the board
//   order.transferred              nothing                  the waiter is not on the board (also user.{id})
//   fiscal.receipt_updated         nothing                  receipts are not on the board (store, pos-branch)
//   shift.changed, booking.changed, table.state_changed, waitlist.changed, queue.*, qr.*, display.cart,
//   bill.print_requested, pos.lan_changed, menu.availability_changed: nothing (pos-branch / branch only, or the menu:
//                                                           no order field of the board)
// Whatever an omission misses, the 60 s safety poll and every reconnect still read the lists.
const ORDER_READ_EVENTS = new Set(['order.created', 'order.status_changed', 'order.assigned', 'order.items_changed']);
const BILL_READ_CHANGES = new Set(['payment', 'voided']);

export function isBoardRelevant(name: string, payload: unknown): boolean {
  if (ORDER_READ_EVENTS.has(name)) return true;
  if (name !== 'order.bill_changed') return false;
  const change = typeof payload === 'object' && payload !== null ? (payload as { change?: unknown }).change : undefined;
  return typeof change !== 'string' || change === '' || BILL_READ_CHANGES.has(change);
}

export interface BoardEvent {
  // The event name without laravel-echo's leading dot (any event of the channel, see isBoardRelevant)
  name: string;
  // null when the payload is not what the contract says (the event still means "refetch")
  uuid: string | null;
  status: string | null;
  branchId: number | null;
  // Wave M2: the channel it came on (no private- prefix) and the whole payload (its `rev`, read by the board)
  channel: string;
  payload: unknown;
}

export function parseBoardEvent(name: string, payload: unknown, channel = ''): BoardEvent {
  const order = typeof payload === 'object' && payload !== null ? (payload as { order?: unknown }).order : undefined;
  if (typeof order !== 'object' || order === null) return { name, uuid: null, status: null, branchId: null, channel, payload };
  const { uuid, status, branch_id: branchId } = order as { uuid?: unknown; status?: unknown; branch_id?: unknown };
  return {
    name,
    uuid: typeof uuid === 'string' ? uuid : null,
    status: typeof status === 'string' ? status : null,
    branchId: typeof branchId === 'number' ? branchId : null,
    channel,
    payload,
  };
}

// --- The connection ------------------------------------------------------------------------------------------------

// The slice of laravel-echo the board uses (a fake one in the tests)
export interface EchoChannelLike {
  listen(event: string, callback: (payload: unknown) => void): EchoChannelLike;
  // Every event of the channel: laravel-echo names a broadcastAs event with a leading dot (".order.created")
  listenToAll(callback: (event: string, payload: unknown) => void): EchoChannelLike;
  subscribed(callback: () => void): EchoChannelLike;
  error(callback: (error: unknown) => void): EchoChannelLike;
}

export interface EchoLike {
  private(channel: string): EchoChannelLike;
  leave(channel: string): void;
  disconnect(): void;
  // pusher: the pusher-js client of laravel-echo (the first reconnect after a drop is spread, wave M2); fakes have none
  connector: { onConnectionChange(callback: (status: string) => void): () => void; pusher?: unknown };
}

export interface BoardChannelHandlers {
  onEvent: (event: BoardEvent) => void;
  // realtime.heartbeat (wave M2): the channel and the payload (its `rev`)
  onHeartbeat?: (channel: string, payload: unknown) => void;
  // true once the socket is up and every channel subscribed; false whenever one is lost
  onConnectionChange: (connected: boolean) => void;
  // a channel is subscribed again after having been lost: events may have been missed, refetch everything
  onResync: () => void;
}

export interface ChannelSession {
  close: () => void;
}

export const RESUBSCRIBE_BASE_MS = 5_000;
const RESUBSCRIBE_MAX_MS = 60_000;

interface ChannelState {
  name: string;
  subscribed: boolean;
  everSubscribed: boolean;
  failures: number;
  retryTimer: ReturnType<typeof setTimeout> | undefined;
}

// Listens for every event of `channelNames` over one socket (the board decides which it reads for). pusher-js reconnects by itself and re-subscribes
// (reported through onConnectionChange and onResync); a refused subscription (expired token, 403, 5xx) is retried with a
// growing delay until the session is closed.
export function openBoardChannels(echo: EchoLike, channelNames: readonly string[], handlers: BoardChannelHandlers): ChannelSession {
  const channels: ChannelState[] = [...new Set(channelNames)].map((name) => ({
    name,
    subscribed: false,
    everSubscribed: false,
    failures: 0,
    retryTimer: undefined,
  }));
  let closed = false;
  let socketUp = false;
  // null until the first answer: a socket that never opens (Reverb down, blocked) must still be reported as down, so the
  // page leaves "connecting" for its polling text. While the first attempt is merely under way nothing is reported.
  let reported: boolean | null = null;

  const report = (settled = true) => {
    const connected = socketUp && channels.length > 0 && channels.every((state) => state.subscribed);
    if (connected === reported || (!connected && !settled && reported === null)) return;
    reported = connected;
    handlers.onConnectionChange(connected);
  };

  const subscribe = (state: ChannelState) => {
    const channel = echo.private(state.name);
    channel.listenToAll((event: string, payload: unknown) => {
      if (closed) return;
      const name = event.replace(/^\./, '');
      // pusher's own (subscription) and internal events are not the server's
      if (name === '' || name.startsWith('pusher:') || name.startsWith('pusher_internal:')) return;
      if (name === HEARTBEAT_EVENT) handlers.onHeartbeat?.(state.name, payload);
      else handlers.onEvent(parseBoardEvent(name, payload, state.name));
    });
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
        if (closed) return;
        state.everSubscribed = true; // whatever happened meanwhile was not heard
        subscribe(state);
      }, delay);
    });
  };

  const stopWatching = echo.connector.onConnectionChange((status) => {
    socketUp = status === 'connected';
    if (!socketUp) for (const state of channels) state.subscribed = false; // pusher-js subscribes again on reconnect and says so
    // 'connecting' is not an answer yet; 'failed' (unavailable) and 'disconnected' are
    report(status !== 'connecting');
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

// Signs a channel subscription: POST {authEndpoint} with the socket and channel, sent through `send` (the session's
// authFetch: Bearer token, renewed on a 401).
export type AuthorizeSend = (request: Request) => Promise<Response>;

export function channelAuthorizer(endpoint: string, send: AuthorizeSend) {
  return (
    params: { socketId: string; channelName: string },
    callback: (error: Error | null, data: { auth: string; channel_data?: string } | null) => void,
  ) => {
    const request = new Request(endpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ socket_id: params.socketId, channel_name: params.channelName }).toString(),
    });
    send(request)
      .then(async (response) => {
        if (!response.ok) return callback(new Error(`Channel authorization failed: HTTP ${response.status}`), null);
        callback(null, (await response.json()) as { auth: string; channel_data?: string });
      })
      .catch((error: unknown) => callback(error instanceof Error ? error : new Error(String(error)), null));
  };
}

// The Reverb client: both libraries are loaded here, on first use. Rejects when they cannot be loaded (the board keeps
// polling).
export async function createEcho(config: RealtimeConfig, send: AuthorizeSend): Promise<EchoLike> {
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
    channelAuthorization: { customHandler: channelAuthorizer(config.authEndpoint, send) },
  });
  return echo as unknown as EchoLike;
}
