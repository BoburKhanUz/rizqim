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

  describe('review F3: nothing is read before the session and size checks', () => {
    // A body that counts how many chunks the proxy pulled from it
    function streamed(chunks: number, chunkBytes: number, headers: Record<string, string> = {}, path = 'v1/admin/team') {
      let pulled = 0;
      const body = new ReadableStream<Uint8Array>(
        {
          pull(controller) {
            if (pulled >= chunks) return controller.close();
            pulled += 1;
            controller.enqueue(new Uint8Array(chunkBytes).fill(97));
          },
        },
        { highWaterMark: 0 },
      );
      const req = new NextRequest(`http://localhost:3100/api/backend/${path}`, {
        method: 'POST',
        headers: { Host: 'localhost:3100', Origin: 'http://localhost:3100', ...headers },
        body,
        // Node's fetch needs it for a streamed body
        duplex: 'half',
      });
      return { req, pulled: () => pulled };
    }

    it('refuses a write without a session cookie before reading its body', async () => {
      const calls = stubBackend(() => reply({}));
      const { req, pulled } = streamed(50, 64 * 1024, { 'Content-Type': 'application/json' });
      const response = await forward(req, ['v1', 'admin', 'team']);
      expect(response.status).toBe(401);
      expect(pulled()).toBe(0);
      expect(calls).toHaveLength(0);
    });

    it('still lets the public sign-in calls through without a cookie', async () => {
      const calls = stubBackend(() => reply({ success: true, data: {} }));
      for (const path of ['v1/admin/auth/login', 'v1/admin/auth/invite/accept', 'v1/admin/auth/2fa/setup', 'v1/admin/auth/2fa/verify']) {
        expect((await forward(request('POST', path, { body: { a: 1 } }), path.split('/'))).status).toBe(200);
      }
      expect(calls).toHaveLength(4);
    });

    it('stops reading a JSON body at the cap, even without a Content-Length', async () => {
      const calls = stubBackend(() => reply({}));
      const { req, pulled } = streamed(200, 64 * 1024, { 'Content-Type': 'application/json', Cookie: `${SESSION_COOKIE}=tok` });
      const response = await forward(req, ['v1', 'admin', 'team']);
      expect(response.status).toBe(413);
      expect(pulled()).toBeLessThan(20);
      expect(calls).toHaveLength(0);
    });

    it('refuses a declared oversize body without reading it', async () => {
      stubBackend(() => reply({}));
      const { req, pulled } = streamed(10, 1024, { 'Content-Type': 'application/json', 'Content-Length': String(5 * 1024 * 1024), Cookie: `${SESSION_COOKIE}=tok` });
      expect((await forward(req, ['v1', 'admin', 'team'])).status).toBe(413);
      expect(pulled()).toBe(0);
    });

    it('stops reading a multipart upload at the upload cap, even when Content-Length lies or is missing', async () => {
      const calls = stubBackend(() => reply({}));
      const headers = { 'Content-Type': 'multipart/form-data; boundary=xyz', Cookie: `${SESSION_COOKIE}=tok` };
      const { req, pulled } = streamed(400, 256 * 1024, headers, 'v1/admin/finance/adjustments');
      const response = await forward(req, ['v1', 'admin', 'finance', 'adjustments']);
      expect(response.status).toBe(413);
      expect(pulled()).toBeLessThan(60);
      expect(calls).toHaveLength(0);

      const lying = streamed(400, 256 * 1024, { ...headers, 'Content-Length': '100' }, 'v1/admin/finance/adjustments');
      expect((await forward(lying.req, ['v1', 'admin', 'finance', 'adjustments'])).status).toBe(413);
      expect(lying.pulled()).toBeLessThan(60);
    });

    it('refuses a multipart body on a path that takes no file before reading it, and a cookie-less upload too', async () => {
      stubBackend(() => reply({}));
      const headers = { 'Content-Type': 'multipart/form-data; boundary=xyz' };
      const noFile = streamed(10, 1024, { ...headers, Cookie: `${SESSION_COOKIE}=tok` }, 'v1/admin/team');
      expect((await forward(noFile.req, ['v1', 'admin', 'team'])).status).toBe(415);
      expect(noFile.pulled()).toBe(0);
      const noSession = streamed(10, 1024, headers, 'v1/admin/finance/adjustments');
      expect((await forward(noSession.req, ['v1', 'admin', 'finance', 'adjustments'])).status).toBe(401);
      expect(noSession.pulled()).toBe(0);
    });
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

  describe('P4-23 files', () => {
    it('passes a multipart upload of an adjustment on as it came, and refuses it anywhere else', async () => {
      const calls: { contentType: string | null; size: number }[] = [];
      vi.stubGlobal(
        'fetch',
        vi.fn(async (_input: string | URL, init: RequestInit) => {
          calls.push({ contentType: new Headers(init.headers).get('Content-Type'), size: (init.body as ArrayBuffer).byteLength });
          return reply({ success: true, data: { id: 1 } }, 201);
        }),
      );
      const form = new FormData();
      form.set('reason', 'Akt');
      form.set('document', new Blob(['%PDF-1.4'], { type: 'application/pdf' }), 'akt.pdf');
      const upload = (path: string) =>
        new NextRequest(`http://localhost:3100/api/backend/${path}`, { method: 'POST', headers: { Host: 'localhost:3100', Origin: 'http://localhost:3100', Cookie: `${SESSION_COOKIE}=tok` }, body: form });

      const ok = await forward(upload('v1/admin/finance/adjustments'), ['v1', 'admin', 'finance', 'adjustments']);
      expect(ok.status).toBe(201);
      expect(calls[0]?.contentType).toMatch(/^multipart\/form-data; ?boundary=/);
      expect(calls[0]?.size).toBeGreaterThan(8);

      const elsewhere = await forward(upload('v1/admin/team'), ['v1', 'admin', 'team']);
      expect(elsewhere.status).toBe(415);
      expect(calls).toHaveLength(1);
    });

    it('passes the Excel export on as a file, but never a file from another path or of another type', async () => {
      const xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
      stubBackend(() => new Response(new Uint8Array([80, 75, 3, 4]), { status: 200, headers: { 'Content-Type': xlsx, 'Content-Disposition': 'attachment; filename=rizqim-daromad-2026-10.xlsx' } }));
      const file = await forward(request('GET', 'v1/admin/finance/reports/monthly/export?period=2026-10', { cookie: 'tok' }), ['v1', 'admin', 'finance', 'reports', 'monthly', 'export']);
      expect(file.status).toBe(200);
      expect(file.headers.get('Content-Type')).toBe(xlsx);
      expect(file.headers.get('Content-Disposition')).toContain('rizqim-daromad-2026-10.xlsx');
      expect(new Uint8Array(await file.arrayBuffer())).toEqual(new Uint8Array([80, 75, 3, 4]));

      // Not a download path: the bytes never pass (said as a bad gateway answer)
      const other = await forward(request('GET', 'v1/admin/team', { cookie: 'tok' }), ['v1', 'admin', 'team']);
      expect((await other.json()).data.reason).toBe('bad_gateway');

      stubBackend(() => new Response('<html>', { status: 200, headers: { 'Content-Type': 'text/html' } }));
      const html = await forward(request('GET', 'v1/admin/finance/adjustments/3/document', { cookie: 'tok' }), ['v1', 'admin', 'finance', 'adjustments', '3', 'document']);
      expect((await html.json()).data.reason).toBe('bad_gateway');
    });
  });
});
