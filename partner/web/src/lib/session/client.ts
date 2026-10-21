import { createApiClient, type ApiClient } from '@rizqim/api-client';
import { createStaffApi, type StaffApi } from '@rizqim/staff';
import type { AccountChoice, SessionPayload, SessionUser } from './types';

// Browser half of the sign-in. The access token (15 minutes) lives in this module's memory only: not in localStorage,
// not in a cookie. The refresh token is an httpOnly cookie the page cannot read; /api/session/refresh trades it for a
// new access token.

export interface Session {
  accessToken: string;
  // Epoch milliseconds, null when the backend did not say
  expiresAt: number | null;
  user: SessionUser | null;
}

export type RefreshOutcome =
  | { status: 'ok'; session: Session }
  | { status: 'denied' } // no valid refresh token: the person has to sign in again
  | { status: 'unavailable' }; // the server could not be reached or failed: try again later, stay signed in

// Renew this long before the token really expires
const EXPIRY_MARGIN_MS = 30_000;

// --- Sharing the session between tabs --------------------------------------------------------------------------------
// All tabs of the browser share the refresh cookie and the device, and the backend keeps one live access token per user
// and device: a refresh in one tab ends the access token of every other tab. So a tab that gets a new session (sign-in,
// refresh) posts it on this channel and the other tabs adopt it instead of refreshing in turn. The token goes from tab
// to tab in memory only (a BroadcastChannel reaches the same origin's open tabs and keeps nothing).
const CHANNEL_NAME = 'rizqim-session';
// The id (not the token) of the newest session any tab got, and when: a tab that finds a newer one than its own while
// it waits for the refresh lock asks the other tabs for it rather than spending the refresh token again
const STAMP_KEY = 'rizqim-session-stamp';
// A stamp this young means another tab refreshed just now (its session is worth asking for)
const PEER_WINDOW_MS = 60_000;
// How long to wait for another tab's answer (they answer in milliseconds; a tab that closed meanwhile never does)
const PEER_WAIT_MS = 500;

type ChannelMessage =
  // A tab got a new session: everyone adopts it
  | { type: 'session'; stamp: string; session: Session }
  // A tab asks for the current session; `stale` is the token it already knows does not work
  | { type: 'request'; stale: string | null }
  | { type: 'reply'; stamp: string; session: Session }
  // A tab signed out: the backend session of this browser is over for every tab
  | { type: 'signed-out' };

let current: Session | null = null;
// The stamp of `current` (null: a session this tab did not get from the server or another tab, e.g. a test's)
let currentStamp: string | null = null;
let inflight: Promise<RefreshOutcome> | null = null;
const listeners = new Set<(session: Session | null, fromOtherTab: boolean) => void>();
const peerWaiters = new Set<(message: { stamp: string; session: Session }) => void>();
let channel: BroadcastChannel | null | undefined;

export const getSession = (): Session | null => current;

// Sets this tab's session (nothing is told to the other tabs)
export function setSession(session: Session | null): void {
  current = session;
  currentStamp = null;
  listeners.forEach((listener) => listener(session, false));
}

function adopt(session: Session | null, stamp: string | null, fromOtherTab = false): void {
  current = session;
  currentStamp = stamp;
  listeners.forEach((listener) => listener(session, fromOtherTab));
}

// Called whenever the session starts, is renewed or ends (a refused refresh), with whether another tab brought the
// change. Returns the unsubscribe function.
export function subscribeSession(listener: (session: Session | null, fromOtherTab: boolean) => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function toSession(payload: SessionPayload): Session {
  const expires = payload.expires_at ? Date.parse(payload.expires_at) : NaN;
  return { accessToken: payload.access_token, expiresAt: Number.isNaN(expires) ? null : expires, user: payload.user };
}

const isFresh = (session: Session) => session.expiresAt === null || session.expiresAt - Date.now() > EXPIRY_MARGIN_MS;

const isSession = (value: unknown): value is Session =>
  typeof value === 'object' && value !== null && typeof (value as Session).accessToken === 'string';

function onChannelMessage(event: MessageEvent<ChannelMessage>): void {
  const message = event.data;
  if (!message || typeof message !== 'object') return;
  switch (message.type) {
    case 'session':
      if (isSession(message.session) && message.stamp !== currentStamp) adopt(message.session, message.stamp, true);
      return;
    case 'request':
      if (current && currentStamp && isFresh(current) && current.accessToken !== message.stale) {
        post({ type: 'reply', stamp: currentStamp, session: current });
      }
      return;
    case 'reply':
      if (isSession(message.session)) peerWaiters.forEach((waiter) => waiter(message));
      return;
    case 'signed-out':
      if (current) adopt(null, null, true);
      return;
  }
}

function getChannel(): BroadcastChannel | null {
  if (channel !== undefined) return channel;
  try {
    channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(CHANNEL_NAME);
  } catch {
    channel = null;
  }
  if (channel) {
    channel.onmessage = onChannelMessage;
    // Node (the tests) would otherwise stay alive for the channel; browsers have no unref
    (channel as BroadcastChannel & { unref?: () => void }).unref?.();
  }
  return channel;
}

function post(message: ChannelMessage): void {
  try {
    getChannel()?.postMessage(message);
  } catch {
    // Another tab simply refreshes on its own
  }
}

function readStamp(): { id: string; at: number } | null {
  try {
    const value = JSON.parse(localStorage.getItem(STAMP_KEY) ?? 'null') as { id?: unknown; at?: unknown } | null;
    return value && typeof value.id === 'string' && typeof value.at === 'number' ? { id: value.id, at: value.at } : null;
  } catch {
    return null;
  }
}

function writeStamp(id: string): void {
  try {
    localStorage.setItem(STAMP_KEY, JSON.stringify({ id, at: Date.now() }));
  } catch {
    // Private mode or blocked storage: the tabs still share sessions over the channel
  }
}

const newStamp = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);

// A new session from the server: this tab uses it and every other tab adopts it
function publish(session: Session): void {
  const stamp = newStamp();
  adopt(session, stamp);
  writeStamp(stamp);
  post({ type: 'session', stamp, session });
}

// Asks the other tabs for a working session (not `stale`); null when none answers in time
function sessionFromPeers(stale: string | null): Promise<Session | null> {
  if (!getChannel()) return Promise.resolve(null);
  return new Promise((resolve) => {
    const finish = (session: Session | null) => {
      clearTimeout(timer);
      peerWaiters.delete(waiter);
      resolve(session);
    };
    const waiter = (message: { stamp: string; session: Session }) => {
      if (message.session.accessToken === stale || !isFresh(message.session)) return;
      adopt(message.session, message.stamp, true);
      finish(message.session);
    };
    const timer = setTimeout(() => finish(null), PEER_WAIT_MS);
    peerWaiters.add(waiter);
    post({ type: 'request', stale });
  });
}

// Open the channel as soon as the page loads, so this tab hears about sessions other tabs get from now on. A tab that
// goes away (closed, reloaded) and holds the newest session takes its stamp along: a reload then refreshes at once
// instead of first asking tabs that may not exist.
if (typeof window !== 'undefined') {
  getChannel();
  window.addEventListener('pagehide', () => {
    if (currentStamp !== null && readStamp()?.id === currentStamp) {
      try {
        localStorage.removeItem(STAMP_KEY);
      } catch {
        // Nothing to clean up then
      }
    }
  });
}

async function callRefresh(): Promise<RefreshOutcome> {
  try {
    const response = await fetch('/api/session/refresh', { method: 'POST', credentials: 'same-origin', cache: 'no-store' });
    if (response.ok) {
      const session = toSession((await response.json()) as SessionPayload);
      publish(session);
      return { status: 'ok', session };
    }
    if (response.status === 401) {
      setSession(null);
      return { status: 'denied' };
    }
    return { status: 'unavailable' };
  } catch {
    return { status: 'unavailable' };
  }
}

// Inside the lock: the session may have been renewed by another tab while this one waited (it then has the new token, or
// learns it from the tab that just refreshed); only when nobody did is the refresh token spent here.
async function renew(stale: string | null): Promise<RefreshOutcome> {
  const known = current;
  if (known && known.accessToken !== stale && isFresh(known)) return { status: 'ok', session: known };

  const latest = readStamp();
  if (latest && latest.id !== currentStamp && Date.now() - latest.at < PEER_WINDOW_MS) {
    const shared = await sessionFromPeers(stale);
    if (shared) return { status: 'ok', session: shared };
  }
  return callRefresh();
}

// Other tabs share the cookie, so two tabs refreshing at once would spend the same refresh token twice, which the
// backend takes for theft and ends the whole session. The lock lets one tab at a time through (each reads the cookie
// the previous one wrote). Web Locks exist on HTTPS and localhost only: on plain HTTP the session route handler itself
// answers concurrent refreshes of the same cookie with one backend refresh (lib/session/server.ts refreshOnce).
function withTabLock<T>(run: () => Promise<T>): Promise<T> {
  const locks = typeof navigator === 'undefined' ? undefined : navigator.locks;
  return locks ? locks.request('rizqim-session-refresh', run) : run();
}

// Renews the session. Single-flight: callers that ask while a refresh is running share its result (the refresh
// token is single-use; a second request would look like a replay). `stale` is the access token the caller found
// expired or refused: a different, fresh one that arrived meanwhile (from another tab) is used instead of refreshing.
export function refreshSession(stale: string | null = current?.accessToken ?? null): Promise<RefreshOutcome> {
  inflight ??= withTabLock(() => renew(stale)).finally(() => {
    inflight = null;
  });
  return inflight;
}

// A usable access token: the current one while it is fresh, else a renewed one; null when signed out.
export async function getAccessToken(): Promise<string | null> {
  if (current && isFresh(current)) return current.accessToken;
  const outcome = await refreshSession(current?.accessToken ?? null);
  return outcome.status === 'ok' ? outcome.session.accessToken : (current?.accessToken ?? null);
}

// fetch for the API client: adds the token and, on a 401, renews the session once and replays the request.
export async function authFetch(request: Request): Promise<Response> {
  const replay = request.clone();
  const sentWith = await getAccessToken();
  if (sentWith) request.headers.set('Authorization', `Bearer ${sentWith}`);

  const response = await fetch(request);
  if (response.status !== 401) return response;

  // Another request (or another tab) may have renewed the session while this one was in flight: then just replay with
  // the new token
  const latest = current?.accessToken;
  let token = latest && latest !== sentWith ? latest : null;
  if (!token) {
    const outcome = await refreshSession(sentWith);
    token = outcome.status === 'ok' && outcome.session.accessToken !== sentWith ? outcome.session.accessToken : null;
  }
  if (!token) return response;
  replay.headers.set('Authorization', `Bearer ${token}`);
  return fetch(replay);
}

const clients = new Map<string, ApiClient>();

// The typed API client (generated from the OpenAPI spec) that signs its requests with the session
export function getApi(baseUrl: string): ApiClient {
  let client = clients.get(baseUrl);
  if (!client) {
    client = createApiClient({ baseUrl, fetch: authFetch });
    clients.set(baseUrl, client);
  }
  return client;
}

const staffApis = new Map<string, StaffApi>();

// The same, as the API layer of @rizqim/staff (the hooks and functions the staff screens share with the Mini App)
export function getStaffApi(baseUrl: string): StaffApi {
  let api = staffApis.get(baseUrl);
  if (!api) {
    api = createStaffApi({ baseUrl, fetch: authFetch });
    staffApis.set(baseUrl, api);
  }
  return api;
}

export class SessionError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly reason?: string,
    // The staff accounts of a phone that holds several (reason account_selection_required)
    readonly accounts: AccountChoice[] = [],
  ) {
    super(message);
  }
}

async function postSession<T>(path: string, body?: unknown, headers?: Record<string, string>): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`/api/session/${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: JSON.stringify(body ?? {}),
    });
  } catch {
    throw new SessionError('', 0, 'unreachable');
  }
  const json = (await response.json().catch(() => null)) as { message?: string; reason?: string; accounts?: AccountChoice[] } | null;
  if (!response.ok) throw new SessionError(json?.message ?? '', response.status, json?.reason, Array.isArray(json?.accounts) ? json.accounts : []);
  return json as T;
}

export const sendCode = (phone: string) => postSession<{ ok: true }>('send-code', { phone });

// Verifies the code; on success the session starts (the refresh cookie is set by the server)
export async function login(phone: string, code: string): Promise<Session> {
  const session = toSession(await postSession<SessionPayload>('login', { phone, code }));
  publish(session);
  return session;
}

// The phone holds several staff accounts (login threw account_selection_required): sign in to the chosen one
export async function selectAccount(userId: number): Promise<Session> {
  const session = toSession(await postSession<SessionPayload>('select-account', { user_id: userId }));
  publish(session);
  return session;
}

// Ends the session on the server (and so on the backend) and forgets the token here and in the other tabs (they share
// the device, whose session this ends). An expired token is not sent: the server then trades the refresh cookie for a
// fresh one, so the backend session ends even when this tab sat idle past the token's life.
export async function logout(): Promise<void> {
  const token = current && isFresh(current) ? current.accessToken : undefined;
  setSession(null);
  post({ type: 'signed-out' });
  writeStamp('signed-out');
  try {
    await postSession('logout', {}, token ? { Authorization: `Bearer ${token}` } : undefined);
  } catch {
    // Signed out in this browser either way; the cookie expires with the refresh token
  }
}
