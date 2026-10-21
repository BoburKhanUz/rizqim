// @vitest-environment node
import { NextRequest } from 'next/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { POST as login } from './login/route';
import { POST as logout } from './logout/route';
import { POST as refresh } from './refresh/route';
import { POST as selectAccount } from './select-account/route';
import { POST as sendCode } from './send-code/route';
import { resetRefreshes } from '@/lib/session/server';

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
  const calls: Array<{ path: string; body: Record<string, unknown>; authorization: string | null; forwardedFor?: string | null }> = [];
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string | URL, init: RequestInit) => {
      const url = new URL(String(input));
      calls.push({
        path: url.pathname,
        body: JSON.parse(String(init.body ?? '{}')) as Record<string, unknown>,
        authorization: new Headers(init.headers).get('Authorization'),
        ...(new Headers(init.headers).has('X-Forwarded-For') ? { forwardedFor: new Headers(init.headers).get('X-Forwarded-For') } : {}),
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

afterEach(() => {
  vi.unstubAllEnvs();
  resetRefreshes();
});

describe('POST /api/session/login', () => {
  it('registers a web device, keeps the refresh token in an httpOnly cookie and out of the body', async () => {
    const calls = stubBackend(() => reply({ success: true, data: sessionData }));

    const response = await login(post('login', { phone: '+998901234567', code: '123456' }));

    expect(response.status).toBe(200);
    const body = await response.json();
    expect(body).toEqual({ access_token: 'access-token', expires_at: '2030-01-01T00:15:00Z', user: { id: 7, name: 'Aziz', role: 'owner' } });
    expect(JSON.stringify(body)).not.toContain('refresh-secret');

    expect(calls[0]?.path).toBe('/api/v1/auth/phone/verify');
    // The panel signs in to the staff account of the phone (owner, manager...), never a customer one
    expect(calls[0]?.body).toMatchObject({ phone: '+998901234567', code: '123456', account: 'staff', device: { type: 'web', platform: 'web' } });
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

describe('signing in when the phone is staff in two stores', () => {
  const accounts = [
    { user_id: 7, name: 'Aziz', role: 'owner', role_label: 'Ega', store: { id: 1, name: 'Osh Markazi' }, branch: null },
    { user_id: 9, name: 'Aziz', role: 'manager', role_label: 'Menejer', store: { id: 2, name: 'Somsa Uyi' }, branch: { id: 5, name: 'Chilonzor' } },
  ];
  const choice = { success: false, message: 'Qaysi biriga kirishni tanlang.', data: { reason: 'account_selection_required', selection_token: 'sel_ticket', selection_expires_in: 300, accounts } };

  it('lists the accounts and keeps the ticket in an httpOnly cookie, out of the body', async () => {
    stubBackend(() => reply(choice, 409));

    const response = await login(post('login', { phone: '+998901234567', code: '123456' }));

    expect(response.status).toBe(409);
    const body = await response.json();
    expect(body).toEqual({
      message: 'Qaysi biriga kirishni tanlang.',
      reason: 'account_selection_required',
      accounts: [
        { user_id: 7, role: 'owner', store: 'Osh Markazi', branch: null },
        { user_id: 9, role: 'manager', store: 'Somsa Uyi', branch: 'Chilonzor' },
      ],
    });
    expect(JSON.stringify(body)).not.toContain('sel_ticket');
    expect(response.cookies.get('rz_select')).toMatchObject({ value: 'sel_ticket', httpOnly: true, sameSite: 'strict', path: '/api/session', maxAge: 300 });
    expect(response.cookies.get('rz_refresh')).toBeUndefined();
  });

  it('signs in to the chosen account with the cookie\'s ticket and spends it', async () => {
    const calls = stubBackend(() => reply({ success: true, data: { ...sessionData, user: { id: 9, name: 'Aziz', role: 'manager' } } }));

    const response = await selectAccount(post('select-account', { user_id: 9 }, { Cookie: `rz_select=sel_ticket; rz_device=${DEVICE}` }));

    expect(response.status).toBe(200);
    expect(await response.json()).toMatchObject({ access_token: 'access-token', user: { id: 9, role: 'manager' } });
    expect(calls[0]?.path).toBe('/api/v1/auth/phone/select-account');
    expect(calls[0]?.body).toEqual({ selection_token: 'sel_ticket', user_id: 9, device: { type: 'web', uuid: DEVICE, name: 'Hamkor veb-panel', platform: 'web' } });
    expect(response.cookies.get('rz_refresh')?.value).toBe('refresh-secret');
    expect(response.cookies.get('rz_select')).toMatchObject({ value: '', maxAge: 0 });
  });

  it('refuses without a ticket, and drops the ticket the backend refused', async () => {
    const calls = stubBackend(() => reply({ success: false, message: 'Tanlov muddati tugagan', data: { reason: 'selection_invalid' } }, 422));

    const none = await selectAccount(post('select-account', { user_id: 9 }));
    expect(none.status).toBe(422);
    expect((await none.json()).reason).toBe('selection_invalid');
    expect((await selectAccount(post('select-account', { user_id: 'x' }, { Cookie: 'rz_select=sel_ticket' }))).status).toBe(422);
    expect((await selectAccount(post('select-account', { user_id: 9 }, { Cookie: 'rz_select=sel_ticket', Origin: 'https://evil.example' }))).status).toBe(403);
    expect(calls).toHaveLength(0);

    const refused = await selectAccount(post('select-account', { user_id: 9 }, { Cookie: 'rz_select=sel_ticket' }));
    expect(refused.status).toBe(422);
    expect((await refused.json()).reason).toBe('selection_invalid');
    expect(refused.cookies.get('rz_select')).toMatchObject({ value: '', maxAge: 0 });
  });

  it('keeps the ticket when the backend could not answer, so the choice can be tried again', async () => {
    const cookie = { Cookie: 'rz_select=sel_ticket' };
    for (const [status, body] of [[503, { message: 'boom' }], [429, { message: 'Too many' }], [403, { message: 'Hisob bloklangan', data: { reason: 'account_blocked' } }]] as const) {
      stubBackend(() => reply(body, status));
      const response = await selectAccount(post('select-account', { user_id: 9 }, cookie));
      expect(response.status).toBe(status === 503 ? 502 : status);
      expect(response.cookies.get('rz_select')).toBeUndefined();
    }
    vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('fetch failed'); }));
    const unreachable = await selectAccount(post('select-account', { user_id: 9 }, cookie));
    expect(unreachable.status).toBe(502);
    expect(unreachable.cookies.get('rz_select')).toBeUndefined();
  });

  it('passes on that the phone belongs to no staff account', async () => {
    stubBackend(() => reply({ success: false, message: "Bu raqam hech qaysi do'kon xodimiga biriktirilmagan.", data: { reason: 'staff_account_not_found' } }, 403));
    const response = await login(post('login', { phone: '+998901234567', code: '123456' }));
    expect(response.status).toBe(403);
    expect(await response.json()).toEqual({ message: "Bu raqam hech qaysi do'kon xodimiga biriktirilmagan.", reason: 'staff_account_not_found' });
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

  it('answers two tabs refreshing the same cookie at once with one backend refresh (no Web Locks on plain HTTP)', async () => {
    let n = 0;
    const calls = stubBackend(() => {
      n++;
      return reply({ success: true, data: { ...sessionData, token: `access-${n}`, refresh_token: `refresh-${n}` } });
    });
    const first = refresh(post('refresh', {}, cookies));
    const second = refresh(post('refresh', {}, cookies));
    const [a, b] = await Promise.all([first, second]);

    expect(calls).toHaveLength(1);
    expect((await a.json()).access_token).toBe('access-1');
    expect((await b.json()).access_token).toBe('access-1');
    expect(a.cookies.get('rz_refresh')?.value).toBe('refresh-1');
    expect(b.cookies.get('rz_refresh')?.value).toBe('refresh-1');

    // A late request with the old cookie still gets that answer; once the rotated token is spent it does not
    const late = await refresh(post('refresh', {}, cookies));
    expect((await late.json()).access_token).toBe('access-1');
    await refresh(post('refresh', {}, { Cookie: `rz_refresh=refresh-1; rz_device=${DEVICE}` }));
    expect(calls).toHaveLength(2);
    await refresh(post('refresh', {}, cookies));
    expect(calls).toHaveLength(3);
    expect(calls.map((call) => call.body.refresh_token)).toEqual(['old-refresh', 'refresh-1', 'old-refresh']);
  });

  it('never shares a refusal or a failure: the next request asks the backend again', async () => {
    const calls = stubBackend(() => reply({ message: 'boom' }, 503));
    await refresh(post('refresh', {}, cookies));
    await refresh(post('refresh', {}, cookies));
    expect(calls).toHaveLength(2);
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

describe('signing out of a tab whose access token expired', () => {
  it('trades the cookie for a fresh token when the backend refuses the page\'s one, so the session still ends', async () => {
    const calls = stubBackend((url, init) => {
      if (url.pathname.endsWith('/refresh')) return reply({ data: { token: 'fresh', refresh_token: 'next' } });
      return new Headers(init.headers).get('Authorization') === 'Bearer fresh' ? reply({ success: true }) : reply({ message: 'Unauthenticated.' }, 401);
    });
    const response = await logout(post('logout', {}, { Authorization: 'Bearer expired', Cookie: `rz_refresh=r; rz_device=${DEVICE}` }));
    expect(calls.map((call) => `${call.path} ${call.authorization ?? ''}`.trim())).toEqual([
      '/api/v1/auth/logout Bearer expired',
      '/api/v1/auth/refresh',
      '/api/v1/auth/logout Bearer fresh',
    ]);
    expect(response.cookies.get('rz_refresh')?.maxAge).toBe(0);
  });
});

describe('the client address the backend sees', () => {
  const routes = [
    ['send-code', () => sendCode(post('send-code', { phone: '+998901234567' }, xff))],
    ['login', () => login(post('login', { phone: '+998901234567', code: '123456' }, xff))],
    ['select-account', () => selectAccount(post('select-account', { user_id: 9 }, { ...xff, Cookie: 'rz_select=sel_ticket' }))],
    ['refresh', () => refresh(post('refresh', {}, { ...xff, Cookie: `rz_refresh=r; rz_device=${DEVICE}` }))],
    ['logout', () => logout(post('logout', {}, { ...xff, Cookie: `rz_refresh=r; rz_device=${DEVICE}` }))],
  ] as const;
  // The browser wrote 6.6.6.6 itself; the outer proxy appended the address it saw the connection from
  const xff = { 'X-Forwarded-For': '6.6.6.6, 203.0.113.9' };

  it.each(routes)('%s forwards the address the outer proxy saw, not the one the browser claimed', async (_name, call) => {
    const calls = stubBackend(() => reply({ success: true, data: { ...sessionData, token: 'fresh' } }));
    await call();
    expect(calls.length).toBeGreaterThan(0);
    for (const backendCall of calls) expect(backendCall.forwardedFor).toBe('203.0.113.9');
  });

  it('two people signing in at once reach the backend as two addresses', async () => {
    const calls = stubBackend(() => reply({ success: true, data: {} }));
    await sendCode(post('send-code', { phone: '+998901111111' }, { 'X-Forwarded-For': '198.51.100.1' }));
    await sendCode(post('send-code', { phone: '+998902222222' }, { 'X-Forwarded-For': '2001:db8::7' }));
    expect(calls.map((call) => call.forwardedFor)).toEqual(['198.51.100.1', '2001:db8::7']);
  });

  it('sends no address when there is none or it is not an IP address', async () => {
    const calls = stubBackend(() => reply({ success: true, data: {} }));
    await sendCode(post('send-code', { phone: '+998901234567' }));
    await sendCode(post('send-code', { phone: '+998901234567' }, { 'X-Forwarded-For': '203.0.113.9, not-an-ip' }));
    await sendCode(post('send-code', { phone: '+998901234567' }, { 'X-Forwarded-For': ' , ' }));
    expect(calls.map((call) => call.forwardedFor)).toEqual([undefined, undefined, undefined]);
  });
});
