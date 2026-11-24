import { newIdempotencyKey } from './idempotency';
import { tg } from './telegram';

// First-party Mini App analytics (wave P, step 3: docs/ANALYTICS_PLAN.md 4.1 and A.4; the contract and the event list are
// docs/API.md "Analitika hodisalari"). Events go to our own server only (POST /analytics/events), in small batches.
//
// - track(name, payload): the payload's type is the catalogue entry of the event, so a prop or an id that the
//   catalogue does not list does not compile; at run time anything off the list is cut away as well (nothing but
//   catalogue fields ever leaves the device: no search text, names, phones, addresses or Telegram ids).
// - An in-memory queue (oldest dropped past the cap), flushed about every 10 s and when the page is hidden or closed
//   (fetch keepalive), one request at a time and at most a few a minute (the server's nginx allows ~30 per minute and
//   2 in flight per client). 202 is done, also with `accepted: 0` (the server's switch is off by default); 429, 5xx and
//   a dropped connection are retried with the same event uids and backoff, 3 tries; 413 and 422 drop the batch.
// - Sends only with the signed-in customer's token: events from before sign-in wait in memory and go with their own
//   time and session; a 401 keeps them until a new token comes. Staff (any role but customer, an account tied to a
//   store) send nothing and what was held is forgotten.
// - The session: a random UUID in sessionStorage (`rz_session`), renewed after 30 minutes without events.
//   Nothing is written to localStorage.
// - Off unless the build says VITE_ANALYTICS_ENABLED=true: then nothing is sent, queued or stored at all.
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
  startParam?: string; // only the app_open of a launch: goes in its batch's envelope (sent once)
  tries: number;
}

interface SessionState {
  id: string;
  last: number;
  open: boolean; // app_open sent for it
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
  // true: a plain customer; false: staff (never tracked); null: not known yet (signing in)
  allowed: boolean | null;
}

export interface AnalyticsClient {
  track: TrackFn;
  /** The launch: app_open once per session, with the kind of Telegram's start_param (the raw value goes once). */
  appOpen: (startParam: string | null | undefined) => void;
  setAuth: (auth: AnalyticsAuth) => void;
  flush: (options?: { keepalive?: boolean }) => Promise<void>;
  pending: () => number;
  dispose: () => void;
}

const SESSION_KEY = 'rz_session';
const SESSION_IDLE_MS = 30 * 60_000;
const FLUSH_MS = 10_000;
const MIN_GAP_MS = 2_000; // between two requests
const MAX_PER_MINUTE = 10; // well under the server's 20 and nginx's ~30
const MAX_EVENTS = 50;
const MAX_BYTES = 32 * 1024 - 256; // the server's 32 KB, with room to spare
const MAX_TRIES = 3;
const MAX_BACKOFF_MS = 5 * 60_000;
const NIL_UUID = '00000000-0000-0000-0000-000000000000';
// Sent without waiting for the 10 s tick: the order is the funnel's key step, and a payment page may close the app next
const URGENT: ReadonlySet<AnalyticsEventName> = new Set(['order_placed', 'payment_redirect']);

const byteLength = (text: string) => (typeof TextEncoder !== 'undefined' ? new TextEncoder().encode(text).length : text.length * 3);

function newUuid(): string {
  const id = newIdempotencyKey().toLowerCase();
  return id === NIL_UUID ? newUuid() : id;
}

export function createAnalytics(options: AnalyticsOptions): AnalyticsClient {
  const { enabled, endpoint, maxQueue = 200 } = options;
  const doFetch = options.fetch ?? ((...args: Parameters<typeof fetch>) => fetch(...args));
  const appVersion = options.appVersion && VERSION_RE.test(options.appVersion) ? options.appVersion : undefined;

  let queue: QueuedEvent[] = [];
  let session: SessionState | null = null;
  let auth: AnalyticsAuth = { token: null, allowed: null };
  let refusedToken: string | null = null; // the token a 401 answered: nothing more is sent with it
  let inFlight = false;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let timerDue = 0;
  let backoffUntil = 0;
  let backoffStep = 0;
  let sentAt: number[] = [];
  let disposed = false;

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
        return { id: saved.id.toLowerCase(), last: saved.last, open: saved.open === true };
      }
    } catch {
      // blocked or broken storage: a session in memory
    }
    return null;
  };

  const writeSession = (state: SessionState) => {
    try {
      storage()?.setItem(SESSION_KEY, JSON.stringify(state));
    } catch {
      // the session lives on in memory
    }
  };

  // The current session, renewed after 30 idle minutes; `rotated`: an earlier one ran out just now
  const touchSession = (now: number): { state: SessionState; rotated: boolean } => {
    const current = session ?? readSession();
    let rotated = false;
    let state: SessionState;
    if (current && now - current.last <= SESSION_IDLE_MS && current.last <= now + SESSION_IDLE_MS) {
      state = { ...current, last: Math.max(current.last, now) };
    } else {
      rotated = current !== null;
      state = { id: newUuid(), last: now, open: false };
    }
    session = state;
    writeSession(state);
    return { state, rotated };
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

  const push = (item: QueuedEvent) => {
    queue.push(item);
    if (queue.length > maxQueue) queue = queue.slice(queue.length - maxQueue);
    schedule(URGENT.has(item.event.name) ? 0 : FLUSH_MS);
  };

  const enqueueOpen = (state: SessionState, now: number, startParam: string | null | undefined) => {
    const param = typeof startParam === 'string' && startParam !== '' && startParam.length <= 512 ? startParam : undefined;
    const props = startKindOf(startParam);
    push({ event: { uid: newUuid(), name: 'app_open', at: now, props }, session: state.id, startParam: param, tries: 0 });
    session = { ...state, open: true };
    writeSession(session);
  };

  // The event as the server allows it, or null when it is not one (unknown name, a required id or prop missing)
  const build = (name: string, payload: unknown, now: number): WireEvent | null => {
    const spec = (SPEC as Record<string, (typeof SPEC)[AnalyticsEventName] | undefined>)[name];
    if (!spec) return null;
    const input = (payload ?? {}) as Record<string, unknown>;
    const event: WireEvent = { uid: newUuid(), name: name as AnalyticsEventName, at: Math.floor(now) };
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
      const now = Date.now();
      const event = build(name, payload, now);
      if (!event) return;
      const { state, rotated } = touchSession(now);
      // A session that ran out while the app stayed open starts again: the new one has its own app_open
      if (rotated && !state.open) enqueueOpen(state, now, null);
      push({ event, session: state.id, tries: 0 });
    } catch {
      // never into the app
    }
  };

  const appOpen = (startParam: string | null | undefined) => {
    if (!enabled || disposed || auth.allowed === false) return;
    try {
      const now = Date.now();
      const { state } = touchSession(now);
      if (!state.open) enqueueOpen(state, now, startParam);
    } catch {
      // never into the app
    }
  };

  const canSend = () => enabled && !disposed && auth.allowed === true && auth.token !== null && auth.token !== refusedToken;

  // The next batch: events of one session from the front of the queue, at most 50 and within the size cap
  const nextBatch = (): { items: QueuedEvent[]; body: string } | null => {
    while (queue.length > 0) {
      const sessionId = queue[0].session;
      const envelope: Record<string, unknown> = { session_id: sessionId, platform: platformName() };
      if (appVersion) envelope.app_version = appVersion;
      const items: QueuedEvent[] = [];
      const parts: string[] = [];
      let startParam: string | undefined;
      for (const item of queue) {
        if (item.session !== sessionId || items.length >= MAX_EVENTS) break;
        const json = JSON.stringify(item.event);
        const param = startParam ?? item.startParam;
        const head = JSON.stringify(param ? { ...envelope, start_param: param, events: [] } : { ...envelope, events: [] });
        const size = byteLength(head) + parts.reduce((sum, part) => sum + byteLength(part) + 1, 0) + byteLength(json);
        if (size > MAX_BYTES) break;
        items.push(item);
        parts.push(json);
        startParam = param;
      }
      if (items.length === 0) {
        queue.shift(); // one event alone over the cap: never sendable
        continue;
      }
      const head = JSON.stringify(startParam ? { ...envelope, start_param: startParam } : envelope);
      return { items, body: `${head.slice(0, -1)},"events":[${parts.join(',')}]}` };
    }
    return null;
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

  const failed = (items: QueuedEvent[], response: Response | null) => {
    items.forEach((item) => (item.tries += 1));
    remove(items.filter((item) => item.tries >= MAX_TRIES));
    const wait = retryAfterMs(response) ?? Math.min(FLUSH_MS * 2 ** backoffStep, MAX_BACKOFF_MS);
    backoffStep += 1;
    backoffUntil = Date.now() + wait;
    if (queue.length > 0) schedule(wait);
  };

  const flush = async ({ keepalive = false }: { keepalive?: boolean } = {}): Promise<void> => {
    try {
      if (!canSend() || inFlight || queue.length === 0) return;
      const now = Date.now();
      if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        schedule(FLUSH_MS);
        return;
      }
      sentAt = sentAt.filter((time) => now - time < 60_000);
      const last = sentAt[sentAt.length - 1] ?? -Infinity;
      // A page hide is the last chance: it skips the backoff and the gap, never the per-minute cap
      const wait = Math.max(
        keepalive ? 0 : backoffUntil - now,
        keepalive ? 0 : last + MIN_GAP_MS - now,
        sentAt.length >= MAX_PER_MINUTE ? sentAt[0] + 60_000 - now : 0,
      );
      if (wait > 0) {
        schedule(wait);
        return;
      }
      const batch = nextBatch();
      if (!batch) return;
      const token = auth.token as string;
      inFlight = true;
      sentAt.push(now);
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
        inFlight = false;
      }
      const status = response?.status ?? 0;
      if (status >= 200 && status < 300) {
        remove(batch.items);
        backoffStep = 0;
        backoffUntil = 0;
        if (queue.length > 0) schedule(MIN_GAP_MS);
      } else if (status === 401) {
        // Kept for a new token (setAuth); nothing more with this one
        refusedToken = token;
      } else if (status === 0 || status === 408 || status === 429 || status >= 500) {
        failed(batch.items, response);
      } else {
        // 413, 422 and any other refusal: this batch can never be taken
        remove(batch.items);
        if (queue.length > 0) schedule(MIN_GAP_MS);
      }
    } catch {
      inFlight = false;
    }
  };

  const setAuth = (next: AnalyticsAuth) => {
    if (!enabled || disposed) return;
    try {
      const changed = next.token !== auth.token || next.allowed !== auth.allowed;
      auth = { token: next.token, allowed: next.allowed };
      if (next.allowed === false) {
        queue = []; // staff: nothing they did is ever sent
        return;
      }
      if (changed && canSend() && queue.length > 0) schedule(0);
    } catch {
      // never into the app
    }
  };

  const onHide = () => {
    if (document.visibilityState === 'hidden') void flush({ keepalive: true });
  };
  const onPageHide = () => void flush({ keepalive: true });
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

  return { track, appOpen, setAuth, flush, pending: () => queue.length, dispose };
}

// The app's client. VITE_ANALYTICS_ENABLED=true turns it on (off by default: the privacy policy has to say so first,
// docs/ANALYTICS_PLAN.md section 9); the server keeps its own switch (ANALYTICS_ENABLED) as well.
const apiBase = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/+$/, '');
const analytics = createAnalytics({
  enabled: import.meta.env.VITE_ANALYTICS_ENABLED === 'true',
  endpoint: `${apiBase}/analytics/events`,
  platform: () => tg?.platform,
  appVersion: import.meta.env.VITE_SENTRY_RELEASE || null,
});

export const track: TrackFn = analytics.track;
export const trackAppOpen = analytics.appOpen;
export const setAnalyticsAuth = analytics.setAuth;
