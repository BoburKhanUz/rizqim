import { createApiClient, type ApiClient } from '@rizqim/api-client';
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

let current: Session | null = null;
let inflight: Promise<RefreshOutcome> | null = null;
const listeners = new Set<(session: Session | null) => void>();

export const getSession = (): Session | null => current;

export function setSession(session: Session | null): void {
  current = session;
  listeners.forEach((listener) => listener(session));
}

// Called whenever the session starts, is renewed or ends (a refused refresh). Returns the unsubscribe function.
export function subscribeSession(listener: (session: Session | null) => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function toSession(payload: SessionPayload): Session {
  const expires = payload.expires_at ? Date.parse(payload.expires_at) : NaN;
  return { accessToken: payload.access_token, expiresAt: Number.isNaN(expires) ? null : expires, user: payload.user };
}

const isFresh = (session: Session) => session.expiresAt === null || session.expiresAt - Date.now() > EXPIRY_MARGIN_MS;

async function callRefresh(): Promise<RefreshOutcome> {
  try {
    const response = await fetch('/api/session/refresh', { method: 'POST', credentials: 'same-origin', cache: 'no-store' });
    if (response.ok) {
      const session = toSession((await response.json()) as SessionPayload);
      setSession(session);
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

// Other tabs share the cookie, so two tabs refreshing at once would spend the same refresh token twice, which the
// backend takes for theft and ends the whole session. The lock lets one tab at a time through (each reads the cookie
// the previous one wrote).
function withTabLock<T>(run: () => Promise<T>): Promise<T> {
  const locks = typeof navigator === 'undefined' ? undefined : navigator.locks;
  return locks ? locks.request('rizqim-session-refresh', run) : run();
}

// Renews the session. Single-flight: callers that ask while a refresh is running share its result (the refresh
// token is single-use; a second request would look like a replay).
export function refreshSession(): Promise<RefreshOutcome> {
  inflight ??= withTabLock(callRefresh).finally(() => {
    inflight = null;
  });
  return inflight;
}

// A usable access token: the current one while it is fresh, else a renewed one; null when signed out.
export async function getAccessToken(): Promise<string | null> {
  if (current && isFresh(current)) return current.accessToken;
  const outcome = await refreshSession();
  return outcome.status === 'ok' ? outcome.session.accessToken : (current?.accessToken ?? null);
}

// fetch for the API client: adds the token and, on a 401, renews the session once and replays the request.
export async function authFetch(request: Request): Promise<Response> {
  const replay = request.clone();
  const sentWith = await getAccessToken();
  if (sentWith) request.headers.set('Authorization', `Bearer ${sentWith}`);

  const response = await fetch(request);
  if (response.status !== 401) return response;

  // Another request may have renewed the session while this one was in flight: then just replay with the new token
  const latest = current?.accessToken;
  let token = latest && latest !== sentWith ? latest : null;
  if (!token) {
    const outcome = await refreshSession();
    token = outcome.status === 'ok' ? outcome.session.accessToken : null;
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
  setSession(session);
  return session;
}

// The phone holds several staff accounts (login threw account_selection_required): sign in to the chosen one
export async function selectAccount(userId: number): Promise<Session> {
  const session = toSession(await postSession<SessionPayload>('select-account', { user_id: userId }));
  setSession(session);
  return session;
}

// Ends the session on the server (and so on the backend) and forgets the token here
export async function logout(): Promise<void> {
  const token = current?.accessToken;
  setSession(null);
  try {
    await postSession('logout', {}, token ? { Authorization: `Bearer ${token}` } : undefined);
  } catch {
    // Signed out in this browser either way; the cookie expires with the refresh token
  }
}
