import { isIP } from 'node:net';
import { NextResponse, type NextRequest } from 'next/server';
import { apiBaseUrl, secureCookies } from '../config';

// The server half of the sign-in. The admin token (Sanctum, ability `admin`, 12 h, 60 min idle) never reaches the page's
// JavaScript: it lives in an httpOnly cookie that only this proxy reads. The browser calls /api/backend/v1/admin/... on
// its own origin (no CORS), the proxy adds `Authorization: Bearer <cookie>` and forwards the call to the backend.
// The token is taken off the two answers that carry it (2fa/verify, 2fa/confirm) and put into the cookie.

// The session cookie's name. `__Host-` (review F8): the browser takes it only with Secure, Path=/ and no Domain, so neither
// a sibling subdomain nor a plain-HTTP page can plant or overwrite it. The prefix needs Secure, so a plain-HTTP trial
// (SESSION_COOKIE_SECURE=false) uses the bare name.
export const SESSION_COOKIE_NAME = 'rz_admin';
export const SECURE_SESSION_COOKIE_NAME = `__Host-${SESSION_COOKIE_NAME}`;
export const sessionCookieName = (): string => (secureCookies() ? SECURE_SESSION_COOKIE_NAME : SESSION_COOKIE_NAME);
const BACKEND_TIMEOUT_MS = 20_000;
// Review W3 C#4: a file download (the Excel exports build up to 20 000 rows) gets longer; the abort signal also cuts the body read
const DOWNLOAD_TIMEOUT_MS = 300_000;
const MAX_BODY_BYTES = 256 * 1024;
// P4-23: the only calls that send a file (multipart/form-data, raw bytes passed on) and how big it may be
const UPLOAD_PATHS = /^v1\/admin\/(?:finance\/adjustments|marketing\/(?:promos|categories)(?:\/\d{1,18})?)$/;
const MAX_UPLOAD_BYTES = 11 * 1024 * 1024;
// P4-23: the only answers passed on as a file (the monthly report's Excel, an adjustment's document), and their types
// P4-31: the Excel exports of the lists (/exports/<list>); their X-Export-Rows / X-Export-Truncated headers are passed on too
const DOWNLOAD_PATHS = /^v1\/admin\/(?:finance\/(?:reports\/monthly\/export|adjustments\/\d{1,18}\/document)|exports\/[a-z-]{1,30}(?:\/[a-z-]{1,30})?|marketing\/(?:promos|categories)\/\d{1,18}\/image)$/;
const DOWNLOAD_TYPES = /^(?:application\/vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet|application\/pdf|image\/(?:jpeg|png|webp))(?:;|$)/i;
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

// Host-only (no Domain) and Path=/ always, as __Host- requires
const cookieBase = () => ({ httpOnly: true, secure: secureCookies(), sameSite: 'strict' as const, path: '/' });

export function setSessionCookie(response: NextResponse, token: string, expiresAt: unknown): void {
  const expires = typeof expiresAt === 'string' ? new Date(expiresAt) : null;
  response.cookies.set(sessionCookieName(), token, {
    ...cookieBase(),
    ...(expires && !Number.isNaN(expires.getTime()) ? { expires } : { maxAge: FALLBACK_MAX_AGE_S }),
  });
}

export function clearSessionCookie(response: NextResponse): void {
  response.cookies.set(sessionCookieName(), '', { ...cookieBase(), maxAge: 0 });
}

function jsonError(status: number, message: string, code: string): NextResponse {
  return NextResponse.json({ success: false, message, data: { reason: code } }, { status });
}

// Reads a request body up to `max` bytes; null when it is over (a declared Content-Length over the cap is refused
// without reading anything, and the count goes on while streaming, so a missing or false header changes nothing).
async function readCapped(request: NextRequest, max: number): Promise<Uint8Array | null> {
  const declared = Number(request.headers.get('content-length') ?? '0');
  if (Number.isFinite(declared) && declared > max) return null;
  if (!request.body) return new Uint8Array(0);

  const reader = request.body.getReader();
  const chunks: Uint8Array[] = [];
  let size = 0;
  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    size += value.byteLength;
    if (size > max) {
      await reader.cancel().catch(() => undefined);
      return null;
    }
    chunks.push(value);
  }
  const all = new Uint8Array(size);
  let offset = 0;
  for (const chunk of chunks) {
    all.set(chunk, offset);
    offset += chunk.byteLength;
  }
  return all;
}

// Forwards one call. `segments` are the path parts after /api/backend. A session cookie under the old bare name (from
// before the __Host- prefix) is never read and is cleared on the way back.
export async function forward(request: NextRequest, segments: string[]): Promise<NextResponse> {
  const response = await forwardCall(request, segments);
  if (secureCookies() && request.cookies.has(SESSION_COOKIE_NAME)) response.cookies.set(SESSION_COOKIE_NAME, '', { ...cookieBase(), maxAge: 0 });
  return response;
}

async function forwardCall(request: NextRequest, segments: string[]): Promise<NextResponse> {
  const path = segments.join('/');
  if (!ALLOWED.test(path) || path.includes('..')) return jsonError(404, 'Topilmadi.', 'not_found');

  const write = request.method !== 'GET' && request.method !== 'HEAD';
  if (write && !isSameOrigin(request)) return jsonError(403, 'Ruxsat etilmagan so\'rov.', 'cross_origin');

  // Review F3: nothing of a write's body is read before the cheap checks. A write without a session cookie is refused
  // outright (only the sign-in steps are made without one), and a body is read as a stream that stops at its cap, so a
  // declared, a missing or a false Content-Length cannot make this server buffer a huge body first.
  const token = request.cookies.get(sessionCookieName())?.value;
  if (write && !token && !PUBLIC_PATHS.has(path)) return jsonError(401, 'Sessiya tugagan. Qaytadan kiring.', 'unauthenticated');

  let body: string | ArrayBuffer | undefined;
  let contentType: string | null = null;
  const multipart = /^multipart\/form-data;\s*boundary=/i.test(request.headers.get('content-type') ?? '');
  if (write && multipart) {
    // P4-23: a file goes only to the paths that take one, as it came (the boundary is in its Content-Type)
    if (!UPLOAD_PATHS.test(path)) return jsonError(415, "Bu so'rov fayl qabul qilmaydi.", 'unsupported_media_type');
    const bytes = await readCapped(request, MAX_UPLOAD_BYTES);
    if (bytes === null) return jsonError(413, 'Fayl juda katta.', 'too_large');
    body = bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer;
    contentType = request.headers.get('content-type');
  } else if (write) {
    const bytes = await readCapped(request, MAX_BODY_BYTES);
    if (bytes === null) return jsonError(413, "So'rov juda katta.", 'too_large');
    body = new TextDecoder().decode(bytes);
    if (body) contentType = 'application/json';
  }

  const ip = clientIp(request);
  // P4-14: a money action's Idempotency-Key (1-255 visible characters, docs/API.md section 5) reaches the backend
  const idempotencyKey = request.headers.get('idempotency-key');
  const userAgent = request.headers.get('user-agent');
  let upstream: Response;
  try {
    upstream = await fetch(`${apiBaseUrl()}/${path}${request.nextUrl.search}`, {
      method: request.method,
      headers: {
        Accept: 'application/json',
        ...(contentType ? { 'Content-Type': contentType } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(ip ? { 'X-Forwarded-For': ip } : {}),
        // The audit log records the browser, not this server
        ...(userAgent ? { 'User-Agent': userAgent } : {}),
        ...(idempotencyKey && /^[\x21-\x7E]{1,255}$/.test(idempotencyKey) ? { 'Idempotency-Key': idempotencyKey } : {}),
      },
      body,
      cache: 'no-store',
      redirect: 'manual',
      signal: AbortSignal.timeout(request.method === 'GET' && DOWNLOAD_PATHS.test(path) ? DOWNLOAD_TIMEOUT_MS : BACKEND_TIMEOUT_MS),
    });
  } catch {
    return jsonError(502, 'Serverga ulanib bo\'lmadi.', 'unreachable');
  }

  // P4-23: a file answer of an allowed download path is passed on as bytes, with its name; anything else stays JSON-only
  const upstreamType = upstream.headers.get('content-type') ?? '';
  if (request.method === 'GET' && upstream.ok && DOWNLOAD_PATHS.test(path) && DOWNLOAD_TYPES.test(upstreamType)) {
    const file = new NextResponse(await upstream.arrayBuffer(), { status: 200 });
    file.headers.set('Content-Type', upstreamType);
    const disposition = upstream.headers.get('content-disposition');
    if (disposition) file.headers.set('Content-Disposition', disposition);
    const exportRows = upstream.headers.get('x-export-rows');
    if (exportRows && /^\d{1,9}$/.test(exportRows)) file.headers.set('X-Export-Rows', exportRows);
    const exportTruncated = upstream.headers.get('x-export-truncated');
    if (exportTruncated === '0' || exportTruncated === '1') file.headers.set('X-Export-Truncated', exportTruncated);
    file.headers.set('Cache-Control', 'private, no-store');
    file.headers.set('X-Content-Type-Options', 'nosniff');
    return file;
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
