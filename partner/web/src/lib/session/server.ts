import { NextResponse, type NextRequest } from 'next/server';
import { apiBaseUrl, secureCookies } from '../config';
import { createHash, randomUUID } from 'node:crypto';
import { isIP } from 'node:net';
import type { AccountChoice, SessionPayload, SessionUser } from './types';

// Server half of the sign-in (used only by the route handlers in app/api/session). The browser never sees the refresh
// token: it lives in an httpOnly cookie that only these handlers read. The short-lived access token goes to the page
// and stays in memory (lib/session/client.ts), never in localStorage.

export const REFRESH_COOKIE = 'rz_refresh';
export const DEVICE_COOKIE = 'rz_device';
// A phone holding several staff accounts (the same person in two stores): the backend's single-use selection ticket
// waits here, httpOnly, while the page shows the accounts to choose from (app/api/session/select-account)
export const SELECTION_COOKIE = 'rz_select';
// The cookies are sent to the session handlers only, not to pages or other API routes
export const COOKIE_PATH = '/api/session';

const DEVICE_COOKIE_MAX_AGE = 400 * 24 * 60 * 60; // seconds; the longest Chrome keeps a cookie
const SELECTION_COOKIE_MAX_AGE = 5 * 60; // seconds; as long as the backend keeps the ticket
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const BACKEND_TIMEOUT_MS = 15_000;

export type { AccountChoice, SessionPayload, SessionUser };

export interface BackendResult {
  // 0: the backend could not be reached
  status: number;
  body: Record<string, unknown> | null;
  retryAfter: string | null;
}

// The address of the person behind a request to these handlers, for the backend's per-IP rate limits (SMS codes, wrong
// codes, refresh) and its audit log. Without it every sign-in would reach the backend from this server's own address and
// all partners would share one rate-limit bucket.
//
// Only the last X-Forwarded-For entry is taken: the one the hop right in front of this server wrote (the outer TLS proxy
// with `$proxy_add_x_forwarded_for` or `$remote_addr`; without a proxy Next itself fills the header with the socket
// address when it is missing). Entries to its left came from the browser and are ignored. This holds only while nothing
// but the outer proxy can reach this server: compose binds its port to 127.0.0.1 (docs/DEPLOY.md, 4-bo'lim). A CDN in
// front of the proxy must have its real-IP header turned into the proxy's $remote_addr first (nginx real_ip module).
export function clientIp(request: NextRequest): string | null {
  const last = request.headers.get('x-forwarded-for')?.split(',').map((entry) => entry.trim()).filter(Boolean).at(-1);
  return last && isIP(last) !== 0 ? last : null;
}

// One JSON call to the backend. Never throws: an unreachable backend is status 0. `request` is the incoming request whose
// client address the backend should see (sent as X-Forwarded-For; the backend trusts it from the compose network only).
export async function backendFetch(path: string, init: { body?: unknown; token?: string; request?: NextRequest } = {}): Promise<BackendResult> {
  const ip = init.request ? clientIp(init.request) : null;
  try {
    const response = await fetch(`${apiBaseUrl()}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(init.token ? { Authorization: `Bearer ${init.token}` } : {}),
        ...(ip ? { 'X-Forwarded-For': ip } : {}),
      },
      body: JSON.stringify(init.body ?? {}),
      cache: 'no-store',
      signal: AbortSignal.timeout(BACKEND_TIMEOUT_MS),
    });
    const body = (await response.json().catch(() => null)) as Record<string, unknown> | null;
    return { status: response.status, body: body && typeof body === 'object' ? body : null, retryAfter: response.headers.get('Retry-After') };
  } catch {
    return { status: 0, body: null, retryAfter: null };
  }
}

// Concurrent refreshes of the same cookie (two tabs on plain HTTP, where browsers give pages no Web Locks to take turns
// with; see lib/session/client.ts) get one backend refresh and share its answer: spending the single-use refresh token
// twice would look like theft to the backend and end the session. A successful answer is also given to a request with
// the same old cookie that arrives just after (its tab sent the cookie before the rotated one reached the browser),
// for as long as the backend itself would accept that retry (its refresh grace). The map lives in this server process.
const REFRESH_SHARE_MS = 10_000;
// nextKey: the key of the refresh token the answer carried; once that one is spent the old answer is stale and goes
interface SharedRefresh {
  result: Promise<BackendResult>;
  settledAt: number | null;
  nextKey: string | null;
}
const refreshes = new Map<string, SharedRefresh>();

const refreshKey = (refreshToken: string, deviceUuid: string) => createHash('sha256').update(`${deviceUuid}\n${refreshToken}`).digest('hex');

export function refreshOnce(refreshToken: string, deviceUuid: string, request?: NextRequest): Promise<BackendResult> {
  const key = refreshKey(refreshToken, deviceUuid);
  const known = refreshes.get(key);
  if (known) return known.result;

  const now = Date.now();
  for (const [other, entry] of refreshes) {
    if ((entry.settledAt !== null && now - entry.settledAt > REFRESH_SHARE_MS) || entry.nextKey === key) refreshes.delete(other);
  }

  const entry: SharedRefresh = {
    result: backendFetch('/v1/auth/refresh', { body: { refresh_token: refreshToken, device_uuid: deviceUuid }, request }),
    settledAt: null,
    nextKey: null,
  };
  refreshes.set(key, entry);
  void entry.result.then((result) => {
    const data = isRecord(result.body?.data) ? result.body.data : null;
    // Only a new pair is worth sharing afterwards; a refusal or a failure is answered afresh next time
    if (result.status >= 200 && result.status < 300 && data && typeof data.refresh_token === 'string') {
      entry.settledAt = Date.now();
      entry.nextKey = refreshKey(data.refresh_token, deviceUuid);
    } else if (refreshes.get(key) === entry) {
      refreshes.delete(key);
    }
  });
  return entry.result;
}

// Signing out: the answer of a refresh of this cookie is not handed out any more
export function forgetRefresh(refreshToken: string, deviceUuid: string): void {
  refreshes.delete(refreshKey(refreshToken, deviceUuid));
}

// Tests: every test starts without shared refreshes
export function resetRefreshes(): void {
  refreshes.clear();
}

export const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

export function messageOf(result: BackendResult): string | undefined {
  const message = result.body?.message;
  return typeof message === 'string' && message !== '' ? message : undefined;
}

export function reasonOf(result: BackendResult): string | undefined {
  const data = result.body?.data;
  return isRecord(data) && typeof data.reason === 'string' ? data.reason : undefined;
}

// The answer of a handler when the backend refused or failed: the status and message are the backend's, nothing else of
// its body is passed on.
export function failure(result: BackendResult, fallbackMessage: string): NextResponse {
  const status = result.status === 0 ? 502 : result.status >= 500 ? 502 : result.status;
  const response = NextResponse.json(
    { message: result.status === 0 ? fallbackMessage : (messageOf(result) ?? fallbackMessage), reason: reasonOf(result) ?? (result.status === 0 ? 'unreachable' : undefined) },
    { status },
  );
  if (result.retryAfter) response.headers.set('Retry-After', result.retryAfter);
  return response;
}

// Browsers send Origin (and Sec-Fetch-Site) on every cross-site POST. Together with SameSite=Strict on the cookies this
// keeps another site from driving these handlers.
export function isSameOrigin(request: NextRequest): boolean {
  if (request.headers.get('sec-fetch-site') === 'cross-site') return false;
  const origin = request.headers.get('origin');
  if (!origin) return true;
  const host = request.headers.get('x-forwarded-host') ?? request.headers.get('host');
  try {
    return host !== null && new URL(origin).host === host;
  } catch {
    return false;
  }
}

export const forbidden = () => NextResponse.json({ message: 'Forbidden', reason: 'cross_origin' }, { status: 403 });

function cookieBase() {
  return { httpOnly: true, secure: secureCookies(), sameSite: 'strict' as const, path: COOKIE_PATH };
}

// Writes the rotated refresh token and the device uuid. The refresh cookie lives as long as the token does.
export function setSessionCookies(response: NextResponse, refreshToken: string, refreshExpiresAt: unknown, deviceUuid: string): void {
  const expires = typeof refreshExpiresAt === 'string' ? new Date(refreshExpiresAt) : null;
  response.cookies.set(REFRESH_COOKIE, refreshToken, {
    ...cookieBase(),
    ...(expires && !Number.isNaN(expires.getTime()) ? { expires } : { maxAge: 30 * 24 * 60 * 60 }),
  });
  response.cookies.set(DEVICE_COOKIE, deviceUuid, { ...cookieBase(), maxAge: DEVICE_COOKIE_MAX_AGE });
}

// Signs the browser out of this server: the refresh token goes, the device uuid stays (the next sign-in reuses the device)
export function clearRefreshCookie(response: NextResponse): void {
  response.cookies.set(REFRESH_COOKIE, '', { ...cookieBase(), maxAge: 0 });
}

// The pair /auth/phone/verify and /auth/refresh answer with, as the payload for the page
export function sessionPayload(data: Record<string, unknown>, user: unknown): SessionPayload | null {
  if (typeof data.token !== 'string') return null;
  return {
    access_token: data.token,
    expires_at: typeof data.token_expires_at === 'string' ? data.token_expires_at : null,
    user: toSessionUser(user),
  };
}

export function toSessionUser(value: unknown): SessionUser | null {
  if (!isRecord(value) || typeof value.id !== 'number') return null;
  return {
    id: value.id,
    name: typeof value.name === 'string' ? value.name : '',
    role: typeof value.role === 'string' ? value.role : '',
  };
}

// The `web` device this browser signs in as: the same browser keeps the same device across sign-ins (the backend
// updates it instead of creating a new one)
export function webDevice(request: NextRequest): Record<string, string> {
  const known = request.cookies.get(DEVICE_COOKIE)?.value;
  return {
    type: 'web',
    uuid: known && UUID.test(known) ? known : randomUUID(),
    name: 'Hamkor veb-panel',
    platform: 'web',
  };
}

// The answer to the page after the backend signed the person in: the access token in the body, the refresh token and
// the device in httpOnly cookies. Any selection ticket is spent by now.
export function signedIn(result: BackendResult): NextResponse {
  const data = isRecord(result.body?.data) ? result.body.data : null;
  const device = data && isRecord(data.device) ? data.device : null;
  const payload = data ? sessionPayload(data, data.user) : null;
  if (!data || !payload || typeof data.refresh_token !== 'string' || !device || typeof device.uuid !== 'string') {
    return NextResponse.json({ message: 'Server kutilmagan javob qaytardi.', reason: 'bad_gateway' }, { status: 502 });
  }

  const response = NextResponse.json(payload);
  setSessionCookies(response, data.refresh_token, data.refresh_token_expires_at, device.uuid);
  clearSelectionCookie(response);
  return response;
}

// The accounts of a 409 account_selection_required, as the page lists them; null when the answer is not one
export function accountChoices(result: BackendResult): { ticket: string; accounts: AccountChoice[] } | null {
  const data = isRecord(result.body?.data) ? result.body.data : null;
  if (result.status !== 409 || !data || data.reason !== 'account_selection_required' || typeof data.selection_token !== 'string' || !Array.isArray(data.accounts)) {
    return null;
  }
  const accounts = data.accounts.flatMap((value): AccountChoice[] => {
    if (!isRecord(value) || typeof value.user_id !== 'number' || !isRecord(value.store) || typeof value.store.name !== 'string') return [];
    const branch = isRecord(value.branch) && typeof value.branch.name === 'string' ? value.branch.name : null;
    return [{ user_id: value.user_id, role: typeof value.role === 'string' ? value.role : '', store: value.store.name, branch }];
  });
  return accounts.length > 0 ? { ticket: data.selection_token, accounts } : null;
}

export function setSelectionCookie(response: NextResponse, ticket: string): void {
  response.cookies.set(SELECTION_COOKIE, ticket, { ...cookieBase(), maxAge: SELECTION_COOKIE_MAX_AGE });
}

export function clearSelectionCookie(response: NextResponse): void {
  response.cookies.set(SELECTION_COOKIE, '', { ...cookieBase(), maxAge: 0 });
}
