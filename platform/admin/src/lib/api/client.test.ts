import { afterEach, describe, expect, it, vi } from 'vitest';
import { ADMIN, json, mockFetch, ok, refusal } from '@/test/helpers';
import { ApiError, call, errorFrom, get, getLastCallAt, markActive, post, setStepUpHandler, subscribeSessionEnded } from './client';

afterEach(() => setStepUpHandler(null));

describe('admin API client', () => {
  it('unwraps the data of the envelope and keeps meta', async () => {
    mockFetch({ 'GET /thing': () => ok([{ id: 1 }], { meta: { next_cursor: 'abc' } }) });
    const result = await call('GET', '/thing');
    expect(result.data).toEqual([{ id: 1 }]);
    expect(result.meta).toEqual({ next_cursor: 'abc' });
  });

  it('builds the query without empty values and talks to the proxy', async () => {
    const { fetchMock } = mockFetch({ 'GET /audit-logs': () => ok([]) });
    await get('/audit-logs', { query: { q: 'abc', action: '', cursor: null, per_page: 30 } });
    const url = String(fetchMock.mock.calls[0]?.[0]);
    expect(url).toBe(`${ADMIN}/audit-logs?q=abc&per_page=30`);
  });

  it('turns a refusal into an ApiError with the backend\'s words, code and field messages', async () => {
    mockFetch({ 'POST /team': () => refusal(422, "Email band.", { reason: 'validation' }, { errors: { email: ['Bu email band.'] } }) });
    const failure = await post('/team', {}).catch((error: unknown) => error);
    expect(failure).toBeInstanceOf(ApiError);
    expect(failure).toMatchObject({ status: 422, message: 'Email band.', code: 'validation', fields: { email: ['Bu email band.'] } });
  });

  it('reads the code from the top level, from data.code or from data.reason, and Retry-After', () => {
    expect(errorFrom(403, { code: 'reauth_required' }, null).code).toBe('reauth_required');
    expect(errorFrom(403, { data: { code: 'reauth_required' } }, null).code).toBe('reauth_required');
    expect(errorFrom(429, { message: 'Kuting' }, '900')).toMatchObject({ retryAfter: 900, message: 'Kuting' });
    expect(errorFrom(500, 'not json', null)).toMatchObject({ status: 500, message: '', code: '' });
  });

  it('says an unreachable server with status 0', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new TypeError('network'))));
    const failure = (await get('/x').catch((error: unknown) => error)) as ApiError;
    expect(failure.unreachable).toBe(true);
  });

  it('asks for a step-up code on 403 reauth_required and repeats the call once', async () => {
    let attempts = 0;
    mockFetch({
      'PATCH /team/4': () => (++attempts === 1 ? json({ success: false, message: 'Qayta tasdiqlang', code: 'reauth_required' }, 403) : ok({ id: 4 })),
    });
    const handler = vi.fn(async () => true);
    setStepUpHandler(handler);
    await expect(call('PATCH', '/team/4', { body: { disabled: true } })).resolves.toMatchObject({ data: { id: 4 } });
    expect(handler).toHaveBeenCalledTimes(1);
    expect(attempts).toBe(2);
  });

  it('does not repeat a call when the step-up is cancelled, nor a second time when it is refused again', async () => {
    const { calls } = mockFetch({ 'POST /team/4/reset-2fa': () => json({ success: false, message: 'Qayta tasdiqlang', code: 'reauth_required' }, 403) });
    setStepUpHandler(async () => false);
    await expect(post('/team/4/reset-2fa')).rejects.toMatchObject({ code: 'reauth_required' });
    expect(calls).toHaveLength(1);

    calls.length = 0;
    setStepUpHandler(async () => true);
    await expect(post('/team/4/reset-2fa')).rejects.toMatchObject({ code: 'reauth_required' });
    expect(calls).toHaveLength(2);
  });

  it('tells the listeners the session is over on a 401, except on the sign-in calls', async () => {
    mockFetch({ 'GET /auth/me': () => refusal(401, 'Unauthenticated.'), 'POST /auth/login': () => refusal(401, "Email yoki parol noto'g'ri.") });
    const ended = vi.fn();
    const off = subscribeSessionEnded(ended);
    await get('/auth/me').catch(() => undefined);
    expect(ended).toHaveBeenCalledTimes(1);
    await post('/auth/login', {}, { anonymous: true }).catch(() => undefined);
    expect(ended).toHaveBeenCalledTimes(1);
    off();
  });

  it('remembers the time of the last call (the idle warning counts from it)', async () => {
    markActive(1_000);
    expect(getLastCallAt()).toBe(1_000);
    mockFetch({ 'GET /auth/me': () => ok({}) });
    await get('/auth/me');
    expect(getLastCallAt()).toBeGreaterThan(1_000);
  });
});
