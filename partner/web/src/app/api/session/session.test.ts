// @vitest-environment node
import { NextRequest } from 'next/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { POST as login } from './login/route';
import { POST as logout } from './logout/route';
import { POST as refresh } from './refresh/route';
import { POST as sendCode } from './send-code/route';

const DEVICE = '3f2b8c1e-5a4d-4e6f-9b7a-1c2d3e4f5a6b';

function post(path: string, body: unknown = {}, headers: Record<string, string> = {}) {
  return new NextRequest(`http://localhost:3000/api/session/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Host: 'localhost:3000', Origin: 'http://localhost:3000', ...headers },
    body: JSON.stringify(body),
  });
}

const reply = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

function stubBackend(handler: (url: URL, init: RequestInit) => Response) {
  const calls: Array<{ path: string; body: Record<string, unknown>; authorization: string | null }> = [];
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string | URL, init: RequestInit) => {
      const url = new URL(String(input));
      calls.push({
        path: url.pathname,
        body: JSON.parse(String(init.body ?? '{}')) as Record<string, unknown>,
        authorization: new Headers(init.headers).get('Authorization'),
      });
      return handler(url, init);
    }),
  );
  return calls;
}

const sessionData = {
  token: 'access-token',
  token_expires_at: '2030-01-01T00:15:00Z',
  refresh_token: 'refresh-secret',
  refresh_token_expires_at: '2030-01-31T00:00:00Z',
  user: { id: 7, name: 'Aziz', role: 'owner', phone: '+998901234567' },
  device: { uuid: DEVICE, type: 'web', user: { id: 7, name: 'Aziz', role: 'owner' } },
};

afterEach(() => vi.unstubAllEnvs());

describe('POST /api/session/login', () => {
  it('registers a web device, keeps the refresh token in an httpOnly cookie and out of the body', async () => {
    const calls = stubBackend(() => reply({ success: true, data: sessionData }));

    const response = await login(post('login', { phone: '+998901234567', code: '123456' }));

    expect(response.status).toBe(200);
    const body = await response.json();
    expect(body).toEqual({ access_token: 'access-token', expires_at: '2030-01-01T00:15:00Z', user: { id: 7, name: 'Aziz', role: 'owner' } });
    expect(JSON.stringify(body)).not.toContain('refresh-secret');

    expect(calls[0]?.path).toBe('/api/v1/auth/phone/verify');
    expect(calls[0]?.body).toMatchObject({ phone: '+998901234567', code: '123456', device: { type: 'web', platform: 'web' } });
    expect((calls[0]?.body.device as { uuid: string }).uuid).toMatch(/^[0-9a-f-]{36}$/);

    const refreshCookie = response.cookies.get('rz_refresh');
    expect(refreshCookie).toMatchObject({ value: 'refresh-secret', httpOnly: true, sameSite: 'strict', path: '/api/session' });
    expect(refreshCookie?.expires).toEqual(new Date('2030-01-31T00:00:00Z'));
    expect(response.cookies.get('rz_device')).toMatchObject({ value: DEVICE, httpOnly: true });
  });

  it('marks the cookies Secure in production and reuses the browser\'s device', async () => {
    vi.stubEnv('NODE_ENV', 'production');
    const calls = stubBackend(() => reply({ success: true, data: sessionData }));
    const response = await login(post('login', { phone: '+998901234567', code: '123456' }, { Cookie: `rz_device=${DEVICE}` }));
    expect(response.cookies.get('rz_refresh')?.secure).toBe(true);
    expect((calls[0]?.body.device as { uuid: string }).uuid).toBe(DEVICE);
  });

  it('passes the backend\'s refusal on without setting a cookie', async () => {
    stubBackend(() => reply({ success: false, message: "Kod noto'g'ri" }, 422));
    const response = await login(post('login', { phone: '+998901234567', code: '000000' }));
    expect(response.status).toBe(422);
    expect((await response.json()).message).toBe("Kod noto'g'ri");
    expect(response.cookies.get('rz_refresh')).toBeUndefined();
  });

  it('refuses a malformed phone or code without calling the backend, and a cross-site request', async () => {
    const calls = stubBackend(() => reply({}));
    expect((await login(post('login', { phone: '901234567', code: '123456' }))).status).toBe(422);
    expect((await login(post('login', { phone: '+998901234567', code: '12' }))).status).toBe(422);
    expect((await login(post('login', { phone: '+998901234567', code: '123456' }, { Origin: 'https://evil.example' }))).status).toBe(403);
    expect(calls).toHaveLength(0);
  });
});

describe('POST /api/session/send-code', () => {
  it('asks the backend for a code and does not pass on the code it may return in development', async () => {
    const calls = stubBackend(() => reply({ success: true, data: { code: '123456' } }));
    const response = await sendCode(post('send-code', { phone: '+998901234567' }));
    expect(await response.json()).toEqual({ ok: true });
    expect(calls[0]).toMatchObject({ path: '/api/v1/auth/phone/send-code', body: { phone: '+998901234567' } });
  });

  it('forwards the rate limit with its Retry-After', async () => {
    stubBackend(() => new Response(JSON.stringify({ message: 'Too many' }), { status: 429, headers: { 'Retry-After': '42' } }));
    const response = await sendCode(post('send-code', { phone: '+998901234567' }));
    expect(response.status).toBe(429);
    expect(response.headers.get('Retry-After')).toBe('42');
  });
});

describe('POST /api/session/refresh', () => {
  const cookies = { Cookie: `rz_refresh=old-refresh; rz_device=${DEVICE}` };

  it('spends the cookie\'s refresh token and stores the rotated one', async () => {
    const calls = stubBackend(() => reply({ success: true, data: { ...sessionData, token: 'access-2', refresh_token: 'refresh-2' } }));
    const response = await refresh(post('refresh', {}, cookies));
    expect(calls[0]).toMatchObject({ path: '/api/v1/auth/refresh', body: { refresh_token: 'old-refresh', device_uuid: DEVICE } });
    expect(await response.json()).toMatchObject({ access_token: 'access-2', user: { id: 7, role: 'owner' } });
    expect(response.cookies.get('rz_refresh')?.value).toBe('refresh-2');
  });

  it('is 401 without a cookie, and clears the cookie when the backend refuses the token', async () => {
    const calls = stubBackend(() => reply({ success: false, message: 'reused', data: { reason: 'refresh_reused' } }, 401));
    const none = await refresh(post('refresh'));
    expect(none.status).toBe(401);
    expect(calls).toHaveLength(0);

    const refused = await refresh(post('refresh', {}, cookies));
    expect(refused.status).toBe(401);
    expect((await refused.json()).reason).toBe('refresh_reused');
    expect(refused.cookies.get('rz_refresh')).toMatchObject({ value: '', maxAge: 0 });
  });

  it('keeps the cookie when the backend is down (nobody is signed out by a hiccup)', async () => {
    stubBackend(() => reply({ message: 'boom' }, 503));
    const response = await refresh(post('refresh', {}, cookies));
    expect(response.status).toBe(502);
    expect(response.cookies.get('rz_refresh')).toBeUndefined();
  });
});

describe('POST /api/session/logout', () => {
  it('ends the session on the backend with the page\'s token and clears the cookie', async () => {
    const calls = stubBackend(() => reply({ success: true }));
    const response = await logout(post('logout', {}, { Authorization: 'Bearer mine', Cookie: `rz_refresh=r; rz_device=${DEVICE}` }));
    expect(calls).toEqual([{ path: '/api/v1/auth/logout', body: {}, authorization: 'Bearer mine' }]);
    expect(response.cookies.get('rz_refresh')).toMatchObject({ value: '', maxAge: 0 });
  });

  it('trades the cookie for a token when the page has none, and clears the cookie even if the backend fails', async () => {
    const calls = stubBackend((url) => (url.pathname.endsWith('/refresh') ? reply({ data: { token: 'fresh' } }) : reply({}, 500)));
    const response = await logout(post('logout', {}, { Cookie: `rz_refresh=r; rz_device=${DEVICE}` }));
    expect(calls.map((call) => call.path)).toEqual(['/api/v1/auth/refresh', '/api/v1/auth/logout']);
    expect(calls[1]?.authorization).toBe('Bearer fresh');
    expect(response.status).toBe(200);
    expect(response.cookies.get('rz_refresh')?.maxAge).toBe(0);
  });
});
