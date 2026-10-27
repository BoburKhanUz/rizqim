import { isIP } from 'node:net';
import { NextResponse, type NextRequest } from 'next/server';
import { apiBaseUrl, secureCookies } from '../config';

// The server half of the sign-in. The admin token (Sanctum, ability `admin`, 12 h, 60 min idle) never reaches the page's
// JavaScript: it lives in an httpOnly cookie that only this proxy reads. The browser calls /api/backend/v1/admin/... on
// its own origin (no CORS), the proxy adds `Authorization: Bearer <cookie>` and forwards the call to the backend.
// The token is taken off the two answers that carry it (2fa/verify, 2fa/confirm) and put into the cookie.

export const SESSION_COOKIE = 'rz_admin';
const BACKEND_TIMEOUT_MS = 20_000;
const MAX_BODY_BYTES = 256 * 1024;
// Cookie lifetime when the backend sends no expiry: the absolute session limit
const FALLBACK_MAX_AGE_S = 12 * 60 * 60;

// The only backend paths this proxy reaches: the admin API, and the Reverb channel signing (stage B)
const ALLOWED = /^(?:v1\/admin\/[A-Za-z0-9_\-/.]*|broadcasting\/auth)$/;
// The answers that hand out a token
const TOKEN_PATHS = new Set(['v1/admin/auth/2fa/verify', 'v1/admin/auth/2fa/confirm']);
const LOGOUT_PATH = 'v1/admin/auth/logout';
// Calls made without a session: a 401 on them is a wrong credential, not an ended session
const PUBLIC_PATHS = new Set(['v1/admin/auth/login', 'v1/admin/auth/invite/accept', 'v1/admin/auth/2fa/setup', ...TOKEN_PATHS]);

export const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

// Only the last X-Forwarded-For entry is trusted: the one the hop right in front of this server wrote (the outer TLS proxy).
// Same rule as the partner web (docs/DEPLOY.md, 4-bo'lim): the port is bound to 127.0.0.1 so nothing else can reach it.
export function clientIp(request: NextRequest): string | null {
  const last = request.headers.get('x-forwarded-for')?.split(',').map((entry) => entry.trim()).filter(Boolean).at(-1);
  return last && isIP(last) !== 0 ? last : null;
}

// Browsers send Origin (and Sec-Fetch-Site) on every cross-site write. With SameSite=Strict on the cookie this keeps
// another site from driving the proxy.
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

const cookieBase = () => ({ httpOnly: true, secure: secureCookies(), sameSite: 'strict' as const, path: '/' });

export function setSessionCookie(response: NextResponse, token: string, expiresAt: unknown): void {
  const expires = typeof expiresAt === 'string' ? new Date(expiresAt) : null;
  response.cookies.set(SESSION_COOKIE, token, {
    ...cookieBase(),
    ...(expires && !Number.isNaN(expires.getTime()) ? { expires } : { maxAge: FALLBACK_MAX_AGE_S }),
  });
}

export function clearSessionCookie(response: NextResponse): void {
  response.cookies.set(SESSION_COOKIE, '', { ...cookieBase(), maxAge: 0 });
}

function jsonError(status: number, message: string, code: string): NextResponse {
  return NextResponse.json({ success: false, message, data: { reason: code } }, { status });
}

// Forwards one call. `segments` are the path parts after /api/backend.
export async function forward(request: NextRequest, segments: string[]): Promise<NextResponse> {
  const path = segments.join('/');
  if (!ALLOWED.test(path) || path.includes('..')) return jsonError(404, 'Topilmadi.', 'not_found');

  const write = request.method !== 'GET' && request.method !== 'HEAD';
  if (write && !isSameOrigin(request)) return jsonError(403, 'Ruxsat etilmagan so\'rov.', 'cross_origin');

  let body: string | undefined;
  if (write) {
    body = await request.text();
    if (body.length > MAX_BODY_BYTES) return jsonError(413, 'So\'rov juda katta.', 'too_large');
  }

  const token = request.cookies.get(SESSION_COOKIE)?.value;
  const ip = clientIp(request);
  const userAgent = request.headers.get('user-agent');
  let upstream: Response;
  try {
    upstream = await fetch(`${apiBaseUrl()}/${path}${request.nextUrl.search}`, {
      method: request.method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(ip ? { 'X-Forwarded-For': ip } : {}),
        // The audit log records the browser, not this server
        ...(userAgent ? { 'User-Agent': userAgent } : {}),
      },
      body,
      cache: 'no-store',
      redirect: 'manual',
      signal: AbortSignal.timeout(BACKEND_TIMEOUT_MS),
    });
  } catch {
    return jsonError(502, 'Serverga ulanib bo\'lmadi.', 'unreachable');
  }

  const text = await upstream.text();
  let json: unknown = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }

  // The token goes into the cookie and out of the body
  if (TOKEN_PATHS.has(path) && upstream.ok && isRecord(json) && isRecord(json.data) && typeof json.data.token === 'string') {
    const { token: sessionToken, ...rest } = json.data;
    const response = NextResponse.json({ ...json, data: rest }, { status: upstream.status });
    setSessionCookie(response, sessionToken, json.data.expires_at);
    return response;
  }

  if (json === null && text) {
    // Not JSON (a gateway page): never pass its text on
    return jsonError(upstream.status >= 500 || upstream.status === 0 ? 502 : upstream.status, 'Server kutilmagan javob qaytardi.', 'bad_gateway');
  }

  const response = json === null ? new NextResponse(null, { status: upstream.status }) : NextResponse.json(json, { status: upstream.status });
  const retryAfter = upstream.headers.get('retry-after');
  if (retryAfter) response.headers.set('Retry-After', retryAfter);

  // The browser is signed out either way when it logs out, or when the backend says the session is over
  if (path === LOGOUT_PATH || (upstream.status === 401 && token && !PUBLIC_PATHS.has(path))) clearSessionCookie(response);
  return response;
}
