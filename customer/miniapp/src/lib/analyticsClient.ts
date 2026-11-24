import { newIdempotencyKey } from './idempotency';
import { tg } from './telegram';

// First-party Mini App analytics, the client (wave P, step 3: docs/ANALYTICS_PLAN.md 4.1 and A.4; the contract and the
// event list are docs/API.md "Analitika hodisalari"). Events go to our own server only (POST /analytics/events), in
// small batches. The app reaches it through lib/analytics.ts, which downloads this chunk only when the build turns
// analytics on (VITE_ANALYTICS_ENABLED=true).
//
// - track(name, payload): the payload's type is the catalogue entry of the event, so a prop or an id that the
//   catalogue does not list does not compile; at run time anything off the list is cut away as well (nothing but
//   catalogue fields ever leaves the device: no search text, names, phones, addresses, Telegram ids or start_param).
// - An in-memory queue (oldest dropped past the cap), flushed about every 10 s, at once for order_placed and
//   payment_redirect (fetch keepalive), and when the page is hidden or closed (keepalive). One request at a time; a page
//   hide may send a second one next to it with what is not in flight (nginx allows 2 in flight per client; the server
//   de-duplicates by uid); at most 10 a minute. 202 is done, also with `accepted: 0` (the server's switch is off by
//   default); 429, 5xx and a dropped connection are retried with the same event uids and a growing wait (Retry-After
//   first); only HTTP failures count toward 3 tries (a dropped connection and a page hide's last chance do not); 413
//   and 422 drop the batch.
// - Every queued event carries the account it was recorded under and is only ever sent with that account's token.
//   Events from before anyone is known (the launch, a sign-out) belong to whoever signs in next in this page. Another
//   account signing in drops the previous one's events and starts a new session. Staff (any role but customer, an
//   account tied to a store) and a stored account that is not this launch's Telegram user send nothing: what was held
//   is forgotten and no session record is kept.
// - The session: a random UUID, kept in sessionStorage (`rz_session`: id, last activity, a short hash of the launch and
//   of the account, never a raw value) once the user is known to be a customer; renewed after 30 minutes without
//   events, for another account, and for a new launch (Telegram Web keeps sessionStorage across a relaunch in the same
//   tab; a reload of the same launch stays in its session). Nothing is written to localStorage.
// - Never throws into the app and never waits on anything: track() only pushes to an array. It shares nothing with the
//   order and payment calls (its own fetch, not the axios client), so it can never delay or fail them.
//
// Adding an event: docs/API.md catalogue first, then the server allowlist (App\Domain\Analytics\AnalyticsEvent), then
// AnalyticsCatalogue and SPEC below (README "Analitika").

export type StartKind = 'campaign' | 'referral' | 'store' | 'invite' | 'none' | 'unknown';
export type AnalyticsPaymentMethod = 'cash' | 'click' | 'payme' | 'uzum' | 'paynet' | 'paylov' | 'keshbek';
export type AnalyticsOrderType = 'delivery' | 'pickup' | 'dine_in';
export type CheckoutErrorCode = 'out_of_zone' | 'min_order' | 'store_closed' | 'item_unavailable' | 'validation' | 'payment' | 'network';

/** The event catalogue of docs/API.md: the ids (top level) and the props each event may carry. */
export interface AnalyticsCatalogue {
  app_open: { props?: { start_kind?: StartKind; campaign_code?: string } };
  store_view: { store_id: number };
  category_view: { store_id: number; category_id: number };
  product_view: { store_id: number; product_id: number };
  add_to_cart: { store_id: number; product_id: number; props?: { qty?: number } };
  cart_view: { store_id: number; props?: { items?: number; value?: number } };
  checkout_start: { store_id: number; props?: { items?: number; value?: number; payment_method?: AnalyticsPaymentMethod; order_type?: AnalyticsOrderType } };
  checkout_error: { store_id: number; props: { code: CheckoutErrorCode } };
  order_placed: { props: { order_uuid: string } };
  payment_redirect: { store_id: number };
  search: { store_id?: number; props?: { results?: number; length?: number } };
  promo_banner_click: { store_id?: number; props?: { promo_id?: number } };
}

export type AnalyticsEventName = keyof AnalyticsCatalogue;

// Every key of P must be a key of Shape (and the same one level down, in `props`): an object with a key more is refused,
// not only an object literal
type NoExtra<Shape, P> = { [K in keyof P]: K extends keyof Shape ? P[K] : never };
type PropsOf<T> = T extends { props?: infer Props } ? NonNullable<Props> : never;
type Exact<Shape, P> = NoExtra<Shape, P> & ('props' extends keyof P ? { props?: NoExtra<PropsOf<Shape>, NonNullable<P['props' & keyof P]>> } : unknown);
export type TrackFn = <N extends AnalyticsEventName, P extends AnalyticsCatalogue[N]>(name: N, payload: P & Exact<AnalyticsCatalogue[N], P>) => void;

type IdKey = 'store_id' | 'product_id' | 'category_id';
type Rule = 'start_kind' | 'campaign_code' | 'quantity' | 'amount' | 'count' | 'length' | 'id' | 'payment_method' | 'order_type' | 'error_code' | 'uuid';

// The server's allowlist (AnalyticsEvent::spec): ids (true = required) and props (true = required)
const SPEC: Record<AnalyticsEventName, { ids: Partial<Record<IdKey, boolean>>; props: Record<string, [Rule, boolean]> }> = {
  app_open: { ids: {}, props: { start_kind: ['start_kind', false], campaign_code: ['campaign_code', false] } },
  store_view: { ids: { store_id: true }, props: {} },
  category_view: { ids: { store_id: true, category_id: true }, props: {} },
  product_view: { ids: { store_id: true, product_id: true }, props: {} },
  add_to_cart: { ids: { store_id: true, product_id: true }, props: { qty: ['quantity', false] } },
  cart_view: { ids: { store_id: true }, props: { items: ['count', false], value: ['amount', false] } },
  checkout_start: {
    ids: { store_id: true },
    props: { items: ['count', false], value: ['amount', false], payment_method: ['payment_method', false], order_type: ['order_type', false] },
  },
  checkout_error: { ids: { store_id: true }, props: { code: ['error_code', true] } },
  order_placed: { ids: {}, props: { order_uuid: ['uuid', true] } },
  payment_redirect: { ids: { store_id: true }, props: {} },
  search: { ids: { store_id: false }, props: { results: ['count', false], length: ['length', false] } },
  promo_banner_click: { ids: { store_id: false }, props: { promo_id: ['id', false] } },
};

const ENUMS: Partial<Record<Rule, readonly string[]>> = {
  start_kind: ['campaign', 'referral', 'store', 'invite', 'none', 'unknown'],
  payment_method: ['cash', 'click', 'payme', 'uzum', 'paynet', 'paylov', 'keshbek'],
  order_type: ['delivery', 'pickup', 'dine_in'],
  error_code: ['out_of_zone', 'min_order', 'store_closed', 'item_unavailable', 'validation', 'payment', 'network'],
};

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const CAMPAIGN_CODE_RE = /^[a-z0-9_]{3,32}$/;
// What the server keeps as app_version (EventIngestor::appVersion): a release number or a 7-12 character lowercase hash
const VERSION_RE = /^(\d{1,3}(\.\d{1,4}){1,3}(\+\d{1,8})?|(?=[0-9a-f]*[a-f])[0-9a-f]{7,12})$/;

const round = (value: number, places: number) => Math.round(value * 10 ** places) / 10 ** places;
const isNumber = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);

// A prop value by its rule, or undefined when it breaks it
function propValue(rule: Rule, value: unknown): string | number | undefined {
  const allowed = ENUMS[rule];
  if (allowed) return typeof value === 'string' && allowed.includes(value) ? value : undefined;
  switch (rule) {
    case 'campaign_code':
      return typeof value === 'string' && CAMPAIGN_CODE_RE.test(value) ? value : undefined;
    case 'uuid':
      return typeof value === 'string' && UUID_RE.test(value) ? value : undefined;
    case 'quantity': {
      const qty = isNumber(value) ? round(value, 3) : NaN;
      return qty > 0 && qty <= 10_000 ? qty : undefined;
    }
    case 'amount': {
      const amount = isNumber(value) ? round(value, 2) : NaN;
      return amount >= 0 && amount <= 10_000_000_000 ? amount : undefined;
    }
    case 'count':
      return Number.isInteger(value) && (value as number) >= 0 && (value as number) <= 100_000 ? (value as number) : undefined;
    case 'length':
      return Number.isInteger(value) && (value as number) >= 0 && (value as number) <= 256 ? (value as number) : undefined;
    case 'id':
      return Number.isInteger(value) && (value as number) >= 1 ? (value as number) : undefined;
    default:
      return undefined;
  }
}

/** The kind of a Telegram start_param, as the server reads it (App\Domain\Analytics\StartParam). */
export function startKindOf(param: string | null | undefined): { start_kind: StartKind; campaign_code?: string } {
  if (!param) return { start_kind: 'none' };
  if (param.length > 512) return { start_kind: 'unknown' };
  const lower = param.toLowerCase();
  if (lower.startsWith('c_')) {
    const code = lower.slice(2);
    return CAMPAIGN_CODE_RE.test(code) ? { start_kind: 'campaign', campaign_code: code } : { start_kind: 'unknown' };
  }
  if (['ref_', 'referrer_', 'rizqim_'].some((prefix) => lower.startsWith(prefix))) return { start_kind: 'referral' };
  if (lower.startsWith('invite_')) return { start_kind: 'invite' };
  if (lower.startsWith('store_') || lower.startsWith('s_')) return { start_kind: 'store' };
  return { start_kind: 'unknown' };
}

interface WireEvent {
  uid: string;
  name: AnalyticsEventName;
  at: number;
  store_id?: number;
  product_id?: number;
  category_id?: number;
  props?: Record<string, string | number>;
}

interface QueuedEvent {
  event: WireEvent;
  session: string;
  // The account it was recorded under (null: before anyone was known; the next sign-in of this launch takes it)
  owner: number | null;
  tries: number;
}

interface SessionState {
  id: string;
  last: number;
  open: boolean; // app_open queued for it
  launch?: string; // a short hash of the launch (Telegram's initData): a new launch is a new session
  user?: string; // a short hash of the account the session is bound to
}

export interface AnalyticsOptions {
  enabled: boolean;
  endpoint: string;
  fetch?: typeof fetch;
  storage?: () => Storage | null; // sessionStorage
  platform?: () => string | undefined;
  appVersion?: string | null;
  maxQueue?: number;
}

export interface AnalyticsAuth {
  token: string | null;
  // true: a plain customer; false: staff, or not the Telegram user of this launch (never tracked); null: not known yet
  allowed: boolean | null;
  // The signed-in account (null while nobody is known): events are only ever sent with the token of their own account
  userId: number | null;
}

export interface AnalyticsClient {
  track: TrackFn;
  /**
   * The launch: Telegram's start_param (only its kind is sent) and its launch data (initData; only a short hash is kept,
   * to tell a new launch from a reload). A new launch is a new session with its own app_open; a reload of the same one
   * is not.
   */
  appOpen: (startParam: string | null | undefined, launchData?: string | null) => void;
  setAuth: (auth: AnalyticsAuth) => void;
  flush: (options?: { hide?: boolean }) => Promise<void>;
  pending: () => number;
  /** Runs `fn` with the clock set to `at` (the lazy loader replays calls made before the client was loaded). */
  at: (time: number, fn: () => void) => void;
  dispose: () => void;
}

const SESSION_KEY = 'rz_session';
const SESSION_IDLE_MS = 30 * 60_000;
const FLUSH_MS = 10_000;
const MIN_GAP_MS = 2_000; // between two requests
const MAX_PER_MINUTE = 10; // well under the server's 20 and nginx's ~30
const MAX_IN_FLIGHT = 2; // nginx allows 2 per client: a second one only for a page hide
const MAX_EVENTS = 50;
// The server's 32 KB, with room to spare. Two in flight (the most there ever are) stay within the 64 KB that browsers
// allow for keepalive bodies in flight together
const MAX_BYTES = 32 * 1024 - 256;
const MAX_TRIES = 3; // HTTP failures only: a dropped connection is not a try
const MAX_BACKOFF_MS = 5 * 60_000;
const NIL_UUID = '00000000-0000-0000-0000-000000000000';
// Sent without waiting for the 10 s tick and with keepalive: the order is the funnel's key step, and a payment page may
// close the app next
const URGENT: ReadonlySet<AnalyticsEventName> = new Set(['order_placed', 'payment_redirect']);

const byteLength = (text: string) => (typeof TextEncoder !== 'undefined' ? new TextEncoder().encode(text).length : text.length * 3);

function newUuid(): string {
  const id = newIdempotencyKey().toLowerCase();
  return id === NIL_UUID ? newUuid() : id;
}

/** A short one-way marker (FNV-1a, 32 bits): tells two values apart, never gives either back. */
function marker(value: string): string {
  let hash = 0x811c9dc5;
  for (let i = 0; i < value.length; i++) {
    hash ^= value.charCodeAt(i);
    hash = Math.imul(hash, 0x01000193) >>> 0;
  }
  return hash.toString(16).padStart(8, '0');
}

export function createAnalytics(options: AnalyticsOptions): AnalyticsClient {
  const { enabled, endpoint, maxQueue = 200 } = options;
  const doFetch = options.fetch ?? ((...args: Parameters<typeof fetch>) => fetch(...args));
  const appVersion = options.appVersion && VERSION_RE.test(options.appVersion) ? options.appVersion : undefined;

  let queue: QueuedEvent[] = [];
  let session: SessionState | null = null;
  let launched = false; // appOpen ran in this page: a session started later by track opens with an app_open too
  let auth: AnalyticsAuth = { token: null, allowed: null, userId: null };
  let refusedToken: string | null = null; // the token a 401 answered: nothing more is sent with it
  const sending = new Set<QueuedEvent>();
  let inFlight = 0;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let timerDue = 0;
  let backoffUntil = 0; // after a failure: a page hide skips it
  let serverWaitUntil = 0; // what a 429 or a 503 asked for (Retry-After): a page hide waits for it too
  let backoffStep = 0;
  let sentAt: number[] = [];
  let clock: number | null = null;
  let disposed = false;

  const now = () => clock ?? Date.now();

  const storage = (): Storage | null => {
    try {
      return options.storage ? options.storage() : typeof sessionStorage !== 'undefined' ? sessionStorage : null;
    } catch {
      return null;
    }
  };

  const readSession = (): SessionState | null => {
    try {
      const raw = storage()?.getItem(SESSION_KEY);
      const saved = raw ? (JSON.parse(raw) as Partial<SessionState>) : null;
      if (saved && typeof saved.id === 'string' && UUID_RE.test(saved.id) && saved.id !== NIL_UUID && isNumber(saved.last)) {
        return {
          id: saved.id.toLowerCase(),
          last: saved.last,
          open: saved.open === true,
          ...(typeof saved.launch === 'string' ? { launch: saved.launch } : {}),
          ...(typeof saved.user === 'string' ? { user: saved.user } : {}),
        };
      }
    } catch {
      // blocked or broken storage: a session in memory
    }
    return null;
  };

  // Kept in sessionStorage only for a customer: nothing is written before the role is known, and staff leave nothing
  const saveSession = () => {
    if (auth.allowed !== true || session === null) return;
    try {
      storage()?.setItem(SESSION_KEY, JSON.stringify(session));
    } catch {
      // the session lives on in memory
    }
  };

  const forgetSession = () => {
    session = null;
    try {
      storage()?.removeItem(SESSION_KEY);
    } catch {
      // nothing to remove
    }
  };

  const fresh = (state: SessionState | null, time: number): state is SessionState =>
    state !== null && time - state.last <= SESSION_IDLE_MS && state.last <= time + SESSION_IDLE_MS;

  // A new session keeps the launch it belongs to; it is bound to the signed-in account (when one is known)
  const newSession = (time: number, from: SessionState | null): SessionState => {
    const user = owner() !== null ? marker(String(owner())) : from?.user;
    return { id: newUuid(), last: time, open: false, ...(from?.launch ? { launch: from.launch } : {}), ...(user ? { user } : {}) };
  };

  // The current session, renewed after 30 idle minutes; `opened`: a new one that needs its app_open
  const touchSession = (time: number): { state: SessionState; opened: boolean } => {
    const current = session ?? readSession();
    let state: SessionState;
    let opened = false;
    if (fresh(current, time)) {
      state = { ...current, last: Math.max(current.last, time) };
    } else {
      state = newSession(time, current);
      opened = current !== null || launched;
    }
    session = state;
    saveSession();
    return { state, opened };
  };

  const schedule = (delay: number) => {
    if (disposed) return;
    const due = Date.now() + Math.max(0, delay);
    if (timer !== null && timerDue <= due) return;
    if (timer !== null) clearTimeout(timer);
    timerDue = due;
    timer = setTimeout(() => {
      timer = null;
      void flush();
    }, Math.max(0, delay));
  };

  const owner = (): number | null => (auth.allowed === true ? auth.userId : null);

  const push = (item: QueuedEvent) => {
    queue.push(item);
    if (queue.length > maxQueue) queue = queue.slice(queue.length - maxQueue);
    schedule(URGENT.has(item.event.name) ? 0 : FLUSH_MS);
  };

  const openEvent = (state: SessionState, time: number, startParam: string | null | undefined): QueuedEvent => {
    // Only the kind of the start_param (and a campaign's code) is sent, never the value: it can carry a staff invite
    // token or another user's referral id
    session = { ...state, open: true };
    return { event: { uid: newUuid(), name: 'app_open', at: Math.floor(time), props: startKindOf(startParam) }, session: state.id, owner: owner(), tries: 0 };
  };

  // The event as the server allows it, or null when it is not one (unknown name, a required id or prop missing)
  const build = (name: string, payload: unknown, time: number): WireEvent | null => {
    const spec = (SPEC as Record<string, (typeof SPEC)[AnalyticsEventName] | undefined>)[name];
    if (!spec) return null;
    const input = (payload ?? {}) as Record<string, unknown>;
    const event: WireEvent = { uid: newUuid(), name: name as AnalyticsEventName, at: Math.floor(time) };
    for (const key of ['store_id', 'product_id', 'category_id'] as const) {
      const required = spec.ids[key];
      if (required === undefined) continue;
      const value = input[key];
      if (Number.isInteger(value) && (value as number) >= 1) event[key] = value as number;
      else if (required) return null;
    }
    const given = (input.props ?? {}) as Record<string, unknown>;
    const props: Record<string, string | number> = {};
    for (const [key, [rule, required]] of Object.entries(spec.props)) {
      const value = Object.prototype.hasOwnProperty.call(given, key) ? propValue(rule, given[key]) : undefined;
      if (value !== undefined) props[key] = value;
      else if (required) return null;
    }
    if (Object.keys(props).length > 0) event.props = props;
    return event;
  };

  const track: TrackFn = (name, payload) => {
    if (!enabled || disposed || auth.allowed === false) return;
    try {
      const time = now();
      const event = build(name, payload, time);
      if (!event) return;
      const { state, opened } = touchSession(time);
      // A session that ran out while the app stayed open starts again: the new one has its own app_open
      if (opened && !state.open) {
        push(openEvent(state, time, null));
        saveSession();
      }
      push({ event, session: state.id, owner: owner(), tries: 0 });
    } catch {
      // never into the app
    }
  };

  const appOpen = (startParam: string | null | undefined, launchData?: string | null) => {
    if (!enabled || disposed || auth.allowed === false) return;
    try {
      const time = now();
      launched = true;
      const launch = marker(launchData || startParam || '');
      const current = session ?? readSession();
      // The same launch again (a reload of the page): the same session, opened once. Anything else is a new launch
      if (fresh(current, time) && current.launch === launch) {
        session = { ...current, last: Math.max(current.last, time) };
        if (!session.open) push(openEvent(session, time, startParam));
      } else {
        push(openEvent({ ...newSession(time, null), launch }, time, startParam));
      }
      saveSession();
    } catch {
      // never into the app
    }
  };

  const canSend = () =>
    enabled && !disposed && auth.allowed === true && auth.userId !== null && auth.token !== null && auth.token !== refusedToken;

  // The next batch: events of one session from the front of what can go now (the signed-in account's, not in flight),
  // at most 50 and within the size cap
  const nextBatch = (): { items: QueuedEvent[]; body: string } | null => {
    for (;;) {
      const ready = queue.filter((item) => item.owner === auth.userId && !sending.has(item));
      if (ready.length === 0) return null;
      const sessionId = ready[0].session;
      const envelope: Record<string, unknown> = { session_id: sessionId, platform: platformName() };
      if (appVersion) envelope.app_version = appVersion;
      const head = JSON.stringify(envelope);
      let size = byteLength(head) + byteLength(',"events":[]');
      const items: QueuedEvent[] = [];
      const parts: string[] = [];
      for (const item of ready) {
        if (item.session !== sessionId || items.length >= MAX_EVENTS) break;
        const json = JSON.stringify(item.event);
        const bytes = byteLength(json) + (parts.length > 0 ? 1 : 0);
        if (size + bytes > MAX_BYTES) break;
        size += bytes;
        items.push(item);
        parts.push(json);
      }
      if (items.length === 0) {
        remove([ready[0]]); // one event alone over the cap: never sendable
        continue;
      }
      return { items, body: `${head.slice(0, -1)},"events":[${parts.join(',')}]}` };
    }
  };

  const platformName = (): string => {
    try {
      const name = options.platform ? options.platform() : undefined;
      return typeof name === 'string' && name !== '' ? name.slice(0, 32) : 'unknown';
    } catch {
      return 'unknown';
    }
  };

  const remove = (items: QueuedEvent[]) => {
    const gone = new Set(items);
    queue = queue.filter((item) => !gone.has(item));
  };

  const retryAfterMs = (response: Response | null): number | null => {
    const header = response?.headers.get('Retry-After');
    const seconds = header ? Number(header) : NaN;
    return Number.isFinite(seconds) && seconds >= 0 ? Math.min(seconds * 1000, MAX_BACKOFF_MS) : null;
  };

  // A failed try: HTTP failures count toward MAX_TRIES (not a dropped connection, nor a page hide's last chance, whose
  // answer is often lost); the wait grows (capped) either way. The queue cap and the server's 24 h rule bound the rest
  const failed = (items: QueuedEvent[], response: Response | null, counted: boolean) => {
    if (counted) {
      items.forEach((item) => (item.tries += 1));
      remove(items.filter((item) => item.tries >= MAX_TRIES));
    }
    const wait = retryAfterMs(response) ?? Math.min(FLUSH_MS * 2 ** Math.min(backoffStep, 10), MAX_BACKOFF_MS);
    backoffStep += 1;
    backoffUntil = Date.now() + wait;
    if (response?.status === 429 || response?.status === 503) serverWaitUntil = backoffUntil;
    if (queue.length > 0) schedule(wait);
  };

  const flush = async ({ hide = false }: { hide?: boolean } = {}): Promise<void> => {
    try {
      // One request at a time; a page hide may send one more next to it (what is not in flight already)
      if (!canSend() || queue.length === 0 || inFlight >= (hide ? MAX_IN_FLIGHT : 1)) return;
      const time = Date.now();
      if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        schedule(FLUSH_MS);
        return;
      }
      sentAt = sentAt.filter((sent) => time - sent < 60_000);
      const last = sentAt[sentAt.length - 1] ?? -Infinity;
      // A page hide is the last chance: it skips the gap and the backoff of a failure, never what the server asked
      // for (Retry-After of a 429 or a 503) and never the per-minute cap
      const wait = Math.max(
        hide ? 0 : backoffUntil - time,
        serverWaitUntil - time,
        hide ? 0 : last + MIN_GAP_MS - time,
        sentAt.length >= MAX_PER_MINUTE ? sentAt[0] + 60_000 - time : 0,
      );
      if (wait > 0) {
        if (!hide || inFlight === 0) schedule(wait);
        return;
      }
      const batch = nextBatch();
      if (!batch) return;
      const keepalive = hide || batch.items.some((item) => URGENT.has(item.event.name));
      const token = auth.token as string;
      inFlight += 1;
      batch.items.forEach((item) => sending.add(item));
      sentAt.push(time);
      let response: Response | null = null;
      try {
        response = await doFetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` },
          body: batch.body,
          keepalive,
          credentials: 'omit',
        });
      } catch {
        response = null;
      } finally {
        inFlight -= 1;
        batch.items.forEach((item) => sending.delete(item));
      }
      const status = response?.status ?? 0;
      if (status >= 200 && status < 300) {
        remove(batch.items);
        backoffStep = 0;
        backoffUntil = 0;
        serverWaitUntil = 0;
        if (queue.length > 0) schedule(MIN_GAP_MS);
      } else if (status === 401) {
        // Kept for a new token of the same account (setAuth); nothing more with this one. Renewed already while this
        // was on its way: again with the new one
        refusedToken = token;
        if (canSend() && queue.length > 0) schedule(MIN_GAP_MS);
      } else if (status === 0 || status === 408 || status === 429 || status >= 500) {
        failed(batch.items, response, status !== 0 && !hide);
      } else {
        // 413, 422 and any other refusal: this batch can never be taken
        remove(batch.items);
        if (queue.length > 0) schedule(MIN_GAP_MS);
      }
    } catch {
      // never into the app
    }
  };

  const setAuth = (next: AnalyticsAuth) => {
    if (!enabled || disposed) return;
    try {
      const changed = next.token !== auth.token || next.allowed !== auth.allowed || next.userId !== auth.userId;
      auth = { token: next.token, allowed: next.allowed, userId: next.allowed === true ? next.userId : null };
      if (next.allowed === false) {
        queue = []; // staff, or not this launch's Telegram user: nothing they did is ever sent, no session is kept
        forgetSession();
        return;
      }
      const userId = auth.userId;
      if (auth.allowed === true && userId !== null) {
        // Another account's events never go with this token; what nobody was known for (since the launch or a sign-out)
        // is this account's
        const claimed = queue.filter((item) => item.owner === null);
        queue = queue.filter((item) => item.owner === null || item.owner === userId);
        claimed.forEach((item) => (item.owner = userId));
        const user = marker(String(userId));
        const current = session ?? readSession();
        if (current?.user !== undefined && current.user !== user) {
          // A session is one account's: another account signed in starts a new one, with its own app_open, and takes
          // over what was recorded before it was known
          const time = now();
          const state: SessionState = { ...newSession(time, current), user };
          const moved = claimed.filter((item) => item.session === current.id);
          const open = openEvent(state, moved[0]?.event.at ?? time, null);
          moved.forEach((item) => (item.session = state.id));
          const at = moved.length > 0 ? queue.indexOf(moved[0]) : queue.length;
          queue.splice(at, 0, open);
          schedule(FLUSH_MS);
        } else if (current !== null) {
          session = { ...current, user };
        }
        saveSession();
      }
      if (changed && canSend() && queue.length > 0) schedule(0);
    } catch {
      // never into the app
    }
  };

  const onHide = () => {
    if (document.visibilityState === 'hidden') void flush({ hide: true });
  };
  const onPageHide = () => void flush({ hide: true });
  const onOnline = () => schedule(0);
  if (enabled && typeof window !== 'undefined') {
    document.addEventListener('visibilitychange', onHide);
    window.addEventListener('pagehide', onPageHide);
    window.addEventListener('online', onOnline);
  }

  const dispose = () => {
    disposed = true;
    if (timer !== null) clearTimeout(timer);
    timer = null;
    if (enabled && typeof window !== 'undefined') {
      document.removeEventListener('visibilitychange', onHide);
      window.removeEventListener('pagehide', onPageHide);
      window.removeEventListener('online', onOnline);
    }
  };

  const at = (time: number, fn: () => void) => {
    clock = time;
    try {
      fn();
    } finally {
      clock = null;
    }
  };

  return { track, appOpen, setAuth, flush, pending: () => queue.length, at, dispose };
}

/** The app's client (loaded by lib/analytics.ts only when the build has VITE_ANALYTICS_ENABLED=true). */
export function createAppAnalytics(): AnalyticsClient {
  const apiBase = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/+$/, '');
  return createAnalytics({
    enabled: true,
    endpoint: `${apiBase}/analytics/events`,
    platform: () => tg?.platform,
    appVersion: import.meta.env.VITE_SENTRY_RELEASE || null,
  });
}
