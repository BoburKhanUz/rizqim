// @vitest-environment node
import { NextRequest } from 'next/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SECURE_SESSION_COOKIE_NAME, SESSION_COOKIE_NAME, forward, sessionCookieName } from './proxy';

// Tests run with NODE_ENV=test: no Secure, so the bare name (the __Host- name is tested on its own below)
const SESSION_COOKIE = SESSION_COOKIE_NAME;

const reply = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

interface Seen {
  url: string;
  method: string;
  authorization: string | null;
  forwardedFor: string | null;
  userAgent: string | null;
  idempotencyKey: string | null;
  body: string;
}

function stubBackend(handler: (seen: Seen) => Response) {
  const calls: Seen[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string | URL, init: RequestInit) => {
      const headers = new Headers(init.headers);
      const seen = {
        url: String(input),
        method: String(init.method),
        authorization: headers.get('Authorization'),
        forwardedFor: headers.get('X-Forwarded-For'),
        userAgent: headers.get('User-Agent'),
        idempotencyKey: headers.get('Idempotency-Key'),
        body: String(init.body ?? ''),
      };
      calls.push(seen);
      return handler(seen);
    }),
  );
  return calls;
}

function request(method: string, path: string, init: { body?: unknown; cookie?: string; headers?: Record<string, string> } = {}) {
  return new NextRequest(`http://localhost:3100/api/backend/${path}`, {
    method,
    headers: { Host: 'localhost:3100', Origin: 'http://localhost:3100', ...(init.cookie ? { Cookie: `${SESSION_COOKIE}=${init.cookie}` } : {}), ...init.headers },
    body: init.body === undefined ? undefined : JSON.stringify(init.body),
  });
}

afterEach(() => {
  vi.unstubAllGlobals();
  vi.unstubAllEnvs();
});

describe('admin proxy', () => {
  it('puts the token of 2fa/verify into an httpOnly cookie and takes it out of the body', async () => {
    stubBackend(() => reply({ success: true, data: { token: 'secret-token', expires_at: '2026-10-27T20:00:00Z', member: { id: 1, name: 'Aziza' } } }));
    const response = await forward(request('POST', 'v1/admin/auth/2fa/verify', { body: { challenge_token: 'c', code: '123456' } }), ['v1', 'admin', 'auth', '2fa', 'verify']);
    const body = await response.json();
    expect(JSON.stringify(body)).not.toContain('secret-token');
    expect(body.data.member.name).toBe('Aziza');
    const cookie = response.cookies.get(SESSION_COOKIE);
    expect(cookie?.value).toBe('secret-token');
    expect(cookie?.httpOnly).toBe(true);
    expect(cookie?.sameSite).toBe('strict');
    expect(cookie?.path).toBe('/');
    expect(new Date(cookie?.expires ?? 0).toISOString()).toBe('2026-10-27T20:00:00.000Z');
  });

  it('does the same for 2fa/confirm and keeps the recovery codes for the page', async () => {
    stubBackend(() => reply({ success: true, data: { token: 't-2', expires_at: null, recovery_codes: ['aaaa-bbbb'] } }));
    const response = await forward(request('POST', 'v1/admin/auth/2fa/confirm', { body: {} }), ['v1', 'admin', 'auth', '2fa', 'confirm']);
    expect((await response.json()).data.recovery_codes).toEqual(['aaaa-bbbb']);
    expect(response.cookies.get(SESSION_COOKIE)?.value).toBe('t-2');
  });

  it('adds the cookie as a bearer token, the client address and the browser, and forwards the path and query', async () => {
    const calls = stubBackend(() => reply({ success: true, data: [] }));
    await forward(request('GET', 'v1/admin/audit-logs?q=abc', { cookie: 'tok', headers: { 'X-Forwarded-For': '1.2.3.4, 10.0.0.9', 'User-Agent': 'Mozilla/5.0 Test' } }), ['v1', 'admin', 'audit-logs']);
    expect(calls[0]).toMatchObject({ url: 'http://127.0.0.1:8000/api/v1/admin/audit-logs?q=abc', method: 'GET', authorization: 'Bearer tok', forwardedFor: '10.0.0.9', userAgent: 'Mozilla/5.0 Test' });
  });

  it('forwards no authorization without a cookie', async () => {
    const calls = stubBackend(() => reply({ success: true, data: {} }));
    await forward(request('POST', 'v1/admin/auth/login', { body: { email: 'a@b.uz', password: 'x' } }), ['v1', 'admin', 'auth', 'login']);
    expect(calls[0]?.authorization).toBeNull();
    expect(JSON.parse(calls[0]?.body ?? '{}')).toEqual({ email: 'a@b.uz', password: 'x' });
  });

  it('reaches only the admin API and the broadcast signing', async () => {
    const calls = stubBackend(() => reply({}));
    for (const path of [['v1', 'owner', 'permissions'], ['v1', 'admin', '..', 'owner'], ['v1', 'auth', 'refresh'], ['superadmin']]) {
      expect((await forward(request('GET', path.join('/')), path)).status).toBe(404);
    }
    expect(calls).toHaveLength(0);
    await forward(request('POST', 'broadcasting/auth', { cookie: 'tok', body: {} }), ['broadcasting', 'auth']);
    expect(calls).toHaveLength(1);
  });

  it("passes a money action's Idempotency-Key on, and drops a malformed one", async () => {
    const calls = stubBackend(() => reply({ success: true, data: {} }));
    await forward(request('POST', 'v1/admin/refund-requests/4/approve', { cookie: 'tok', body: { reason: 'Tasdiq' }, headers: { 'Idempotency-Key': 'approve-4-abc' } }), ['v1', 'admin', 'refund-requests', '4', 'approve']);
    await forward(request('POST', 'v1/admin/refund-requests/4/approve', { cookie: 'tok', body: {}, headers: { 'Idempotency-Key': 'has space' } }), ['v1', 'admin', 'refund-requests', '4', 'approve']);
    expect(calls.map((call) => call.idempotencyKey)).toEqual(['approve-4-abc', null]);
  });

  it('refuses a write that comes from another site', async () => {
    const calls = stubBackend(() => reply({}));
    const crossSite = request('POST', 'v1/admin/team', { body: {}, headers: { Origin: 'https://evil.example', 'Sec-Fetch-Site': 'cross-site' } });
    expect((await forward(crossSite, ['v1', 'admin', 'team'])).status).toBe(403);
    expect(calls).toHaveLength(0);
  });

  it('clears the cookie when the backend says the session is over, and when the member logs out', async () => {
    stubBackend(() => reply({ message: 'Unauthenticated.' }, 401));
    const ended = await forward(request('GET', 'v1/admin/auth/me', { cookie: 'old' }), ['v1', 'admin', 'auth', 'me']);
    expect(ended.status).toBe(401);
    expect(ended.cookies.get(SESSION_COOKIE)?.value).toBe('');

    stubBackend(() => reply({ success: true, data: [] }));
    const out = await forward(request('POST', 'v1/admin/auth/logout', { cookie: 'tok', body: {} }), ['v1', 'admin', 'auth', 'logout']);
    expect(out.status).toBe(200);
    expect(out.cookies.get(SESSION_COOKIE)?.value).toBe('');
  });

  it('keeps the cookie when a wrong password is a 401, and passes Retry-After on', async () => {
    stubBackend(() => reply({ success: false, message: "Email yoki parol noto'g'ri." }, 401));
    const wrong = await forward(request('POST', 'v1/admin/auth/login', { cookie: 'tok', body: {} }), ['v1', 'admin', 'auth', 'login']);
    expect(wrong.cookies.get(SESSION_COOKIE)).toBeUndefined();

    stubBackend(() => reply({ success: false, message: 'Kuting' }, 429, { 'Retry-After': '900' }));
    const locked = await forward(request('POST', 'v1/admin/auth/login', { body: {} }), ['v1', 'admin', 'auth', 'login']);
    expect(locked.status).toBe(429);
    expect(locked.headers.get('Retry-After')).toBe('900');
  });

  it('over HTTPS names the cookie __Host-rz_admin: Secure, Path=/, no Domain; the bare name is neither read nor kept', async () => {
    vi.stubEnv('SESSION_COOKIE_SECURE', 'true');
    expect(sessionCookieName()).toBe('__Host-rz_admin');

    stubBackend(() => reply({ success: true, data: { token: 'secret-token', expires_at: null } }));
    const signedIn = await forward(request('POST', 'v1/admin/auth/2fa/verify', { body: {} }), ['v1', 'admin', 'auth', '2fa', 'verify']);
    const cookie = signedIn.cookies.get(SECURE_SESSION_COOKIE_NAME);
    expect(cookie).toMatchObject({ value: 'secret-token', secure: true, path: '/', httpOnly: true, sameSite: 'strict' });
    expect(cookie?.domain).toBeUndefined();
    expect(signedIn.cookies.get(SESSION_COOKIE_NAME)).toBeUndefined();
    expect(signedIn.headers.get('set-cookie')).toMatch(/^__Host-rz_admin=secret-token; Path=\/;.*Secure/);

    const calls = stubBackend(() => reply({ success: true, data: {} }));
    await forward(request('GET', 'v1/admin/me', { headers: { Cookie: `${SECURE_SESSION_COOKIE_NAME}=host-tok` } }), ['v1', 'admin', 'me']);
    // A cookie planted under the bare name (a sibling subdomain, plain HTTP) is ignored, and cleared
    const planted = await forward(request('GET', 'v1/admin/me', { headers: { Cookie: `${SESSION_COOKIE_NAME}=planted` } }), ['v1', 'admin', 'me']);
    expect(calls.map((call) => call.authorization)).toEqual(['Bearer host-tok', null]);
    expect(planted.cookies.get(SESSION_COOKIE_NAME)?.value).toBe('');
  });

  it('answers 502 for an unreachable backend and never passes a gateway page on', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new Error('down'))));
    expect((await forward(request('GET', 'v1/admin/auth/me'), ['v1', 'admin', 'auth', 'me'])).status).toBe(502);

    vi.stubGlobal('fetch', vi.fn(async () => new Response('<html>Bad gateway</html>', { status: 502 })));
    const page = await forward(request('GET', 'v1/admin/auth/me'), ['v1', 'admin', 'auth', 'me']);
    expect(page.status).toBe(502);
    expect(JSON.stringify(await page.json())).not.toContain('html');
  });
});
