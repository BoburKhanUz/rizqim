import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch } from '@/test/helpers';
import { authFetch, getAccessToken, getSession, login, logout, refreshSession, setSession } from './client';

const user = { id: 7, name: 'Aziz', role: 'owner' };
const payload = (token: string, minutes = 15) => ({ access_token: token, expires_at: new Date(Date.now() + minutes * 60_000).toISOString(), user });

afterEach(() => setSession(null));

describe('refreshSession', () => {
  it('is single-flight: callers that ask while a refresh runs share its one request', async () => {
    let release!: () => void;
    const { calls } = mockFetch({
      'POST /api/session/refresh': async () => {
        await new Promise<void>((resolve) => (release = resolve));
        return json(payload('fresh'));
      },
    });

    const results = [refreshSession(), refreshSession(), getAccessToken()];
    await vi.waitFor(() => expect(release).toBeTypeOf('function'));
    release();
    const outcomes = await Promise.all(results);

    expect(calls).toHaveLength(1);
    expect(outcomes[0]).toEqual(outcomes[1]);
    expect(outcomes[2]).toBe('fresh');
    expect(getSession()?.accessToken).toBe('fresh');
  });

  it('signs out when the refresh token is refused, and keeps the session when the server is unreachable', async () => {
    setSession({ accessToken: 'old', expiresAt: Date.now() + 600_000, user });

    mockFetch({ 'POST /api/session/refresh': () => json({ reason: 'refresh_reused' }, 401) });
    await expect(refreshSession()).resolves.toEqual({ status: 'denied' });
    expect(getSession()).toBeNull();

    setSession({ accessToken: 'old', expiresAt: Date.now() + 600_000, user });
    mockFetch({ 'POST /api/session/refresh': () => json({}, 502) });
    await expect(refreshSession()).resolves.toEqual({ status: 'unavailable' });
    expect(getSession()?.accessToken).toBe('old');
  });
});

describe('authFetch', () => {
  it('sends the token, and on a 401 renews the session once and replays the request', async () => {
    setSession({ accessToken: 'expired', expiresAt: Date.now() + 600_000, user });
    const { calls } = mockFetch({
      'POST /api/session/refresh': () => json(payload('renewed')),
      'GET /api/v1/me/permissions': (request) =>
        request.headers.get('Authorization') === 'Bearer renewed' ? json({ data: { ok: true } }) : json({ message: 'Unauthenticated.' }, 401),
    });

    const response = await authFetch(new Request('http://api.test/api/v1/me/permissions'));

    expect(response.status).toBe(200);
    expect(calls.map((call) => `${call.method} ${new URL(call.url).pathname}`)).toEqual([
      'GET /api/v1/me/permissions',
      'POST /api/session/refresh',
      'GET /api/v1/me/permissions',
    ]);
    expect(calls[0]?.headers.get('Authorization')).toBe('Bearer expired');
    expect(calls[2]?.headers.get('Authorization')).toBe('Bearer renewed');
  });

  it('shares one refresh between requests that fail together', async () => {
    setSession({ accessToken: 'expired', expiresAt: Date.now() + 600_000, user });
    const { calls } = mockFetch({
      'POST /api/session/refresh': () => json(payload('renewed')),
      'GET /api/v1/orders': (request) => (request.headers.get('Authorization') === 'Bearer renewed' ? json({ data: [] }) : json({}, 401)),
      'GET /api/v1/owner/analytics': (request) => (request.headers.get('Authorization') === 'Bearer renewed' ? json({ data: {} }) : json({}, 401)),
    });

    const [a, b] = await Promise.all([authFetch(new Request('http://api.test/api/v1/orders')), authFetch(new Request('http://api.test/api/v1/owner/analytics'))]);

    expect([a.status, b.status]).toEqual([200, 200]);
    expect(calls.filter((call) => call.url.endsWith('/api/session/refresh'))).toHaveLength(1);
  });

  it('gives the 401 back when the session cannot be renewed', async () => {
    setSession({ accessToken: 'expired', expiresAt: Date.now() + 600_000, user });
    mockFetch({
      'POST /api/session/refresh': () => json({}, 401),
      'GET /api/v1/orders': () => json({}, 401),
    });
    const response = await authFetch(new Request('http://api.test/api/v1/orders'));
    expect(response.status).toBe(401);
    expect(getSession()).toBeNull();
  });

  it('renews an access token that is about to expire before using it', async () => {
    setSession({ accessToken: 'stale', expiresAt: Date.now() + 5_000, user });
    mockFetch({
      'POST /api/session/refresh': () => json(payload('fresh')),
      'GET /api/v1/orders': (request) => json({ auth: request.headers.get('Authorization') }),
    });
    const response = await authFetch(new Request('http://api.test/api/v1/orders'));
    expect(await response.json()).toEqual({ auth: 'Bearer fresh' });
  });
});

describe('login and logout', () => {
  it('login posts the phone and code, starts the session and never sees a refresh token', async () => {
    const { calls } = mockFetch({ 'POST /api/session/login': () => json(payload('access-1')) });
    const session = await login('+998901234567', '123456');
    expect(session.accessToken).toBe('access-1');
    expect(await calls[0]?.json()).toEqual({ phone: '+998901234567', code: '123456' });
    expect(JSON.stringify(session)).not.toContain('refresh');
  });

  it('logout forgets the token at once and tells the server', async () => {
    setSession({ accessToken: 'mine', expiresAt: Date.now() + 600_000, user });
    const { calls } = mockFetch({ 'POST /api/session/logout': () => json({ ok: true }) });
    await logout();
    expect(getSession()).toBeNull();
    expect(calls[0]?.headers.get('Authorization')).toBe('Bearer mine');
  });
});
