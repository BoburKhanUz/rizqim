import { NextResponse, type NextRequest } from 'next/server';
import { apiBaseUrl, secureCookies } from '../config';
import type { SessionPayload, SessionUser } from './types';

// Server half of the sign-in (used only by the route handlers in app/api/session). The browser never sees the refresh
// token: it lives in an httpOnly cookie that only these handlers read. The short-lived access token goes to the page
// and stays in memory (lib/session/client.ts), never in localStorage.

export const REFRESH_COOKIE = 'rz_refresh';
export const DEVICE_COOKIE = 'rz_device';
// The cookies are sent to the session handlers only, not to pages or other API routes
export const COOKIE_PATH = '/api/session';

const DEVICE_COOKIE_MAX_AGE = 400 * 24 * 60 * 60; // seconds; the longest Chrome keeps a cookie
const BACKEND_TIMEOUT_MS = 15_000;

export type { SessionPayload, SessionUser };

export interface BackendResult {
  // 0: the backend could not be reached
  status: number;
  body: Record<string, unknown> | null;
  retryAfter: string | null;
}

// One JSON call to the backend. Never throws: an unreachable backend is status 0.
export async function backendFetch(path: string, init: { body?: unknown; token?: string } = {}): Promise<BackendResult> {
  try {
    const response = await fetch(`${apiBaseUrl()}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(init.token ? { Authorization: `Bearer ${init.token}` } : {}),
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
