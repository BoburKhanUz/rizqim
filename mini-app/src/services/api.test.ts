import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { makeStore, makeUser } from '../test/factories';

// api.ts keeps its re-authentication state (the in-flight promise, the failure cooldown) at module level, so every
// test loads a fresh copy of it. All HTTP goes through one fake adapter: no network, and every request is recorded.

vi.mock('../lib/telegram', () => ({
  getInitData: vi.fn(),
  tg: { initDataUnsafe: {} },
}));

interface FakeReply {
  status: number;
  data?: unknown;
}
type Handler = (config: InternalAxiosRequestConfig) => FakeReply | Promise<FakeReply>;

interface RecordedRequest {
  method: string;
  url: string;
  authorization: string | undefined;
  body: unknown;
}

const OLD_TOKEN = 'old-token';
const NEW_TOKEN = 'new-token';
const INIT_DATA = 'query_id=AAH&user=%7B%7D&hash=abc';

const loginReply = (token: string = NEW_TOKEN): FakeReply => ({
  status: 200,
  data: {
    success: true,
    message: 'Authenticated successfully.',
    data: { token, user: makeUser({ balance: '12500.00' }), store: makeStore(), sms_verification_required: true },
  },
});

const isLogin = (config: InternalAxiosRequestConfig) => config.url === '/auth/telegram';
const bearer = (config: InternalAxiosRequestConfig) => config.headers.get('Authorization')?.toString();

let requests: RecordedRequest[];
let handler: Handler;
let api: typeof import('./api').default;
let useAuthStore: typeof import('../store/authStore').useAuthStore;
let telegram: typeof import('../lib/telegram');

const loginCount = () => requests.filter((r) => r.url === '/auth/telegram').length;
const callsTo = (url: string) => requests.filter((r) => r.url === url);

beforeEach(async () => {
  vi.resetModules();
  requests = [];
  handler = () => ({ status: 200, data: { success: true, data: [] } });

  const { AxiosError, default: axios } = await import('axios');
  const adapter: AxiosAdapter = async (config) => {
    // A re-authentication loop would never end: fail the test instead of hanging it
    if (requests.length >= 25) throw new Error('Runaway request loop');
    requests.push({
      method: (config.method ?? 'get').toLowerCase(),
      url: config.url ?? '',
      authorization: bearer(config),
      body: typeof config.data === 'string' ? JSON.parse(config.data) : config.data,
    });
    const { status, data } = await handler(config);
    const response: AxiosResponse = { data, status, statusText: String(status), headers: {}, config };
    if (status >= 200 && status < 300) return response;
    throw new AxiosError(
      `Request failed with status code ${status}`,
      status >= 500 ? AxiosError.ERR_BAD_RESPONSE : AxiosError.ERR_BAD_REQUEST,
      config,
      null,
      response,
    );
  };
  // Both axios instances created by api.ts inherit the default adapter
  axios.defaults.adapter = adapter;

  telegram = await import('../lib/telegram');
  vi.mocked(telegram.getInitData).mockReturnValue(INIT_DATA);
  telegram.tg.initDataUnsafe.start_param = undefined;

  api = (await import('./api')).default;
  ({ useAuthStore } = await import('../store/authStore'));
  useAuthStore.getState().setAuth(OLD_TOKEN, makeUser(), makeStore(), false);
});

afterEach(() => {
  vi.useRealTimers();
});

describe('the request interceptor', () => {
  it('sends the stored token as a Bearer header', async () => {
    await api.get('/orders');

    expect(requests).toEqual([expect.objectContaining({ url: '/orders', authorization: `Bearer ${OLD_TOKEN}` })]);
  });

  it('sends no Authorization header when nobody is signed in', async () => {
    useAuthStore.getState().logout();
    await api.get('/stores');

    expect(requests[0].authorization).toBeUndefined();
  });
});

describe('an expired token (401)', () => {
  it('logs in again with the Telegram initData and replays the request with the new token', async () => {
    handler = (config) => {
      if (isLogin(config)) return loginReply();
      return bearer(config) === `Bearer ${NEW_TOKEN}`
        ? { status: 200, data: { success: true, data: ['order'] } }
        : { status: 401, data: { message: 'Unauthenticated.' } };
    };

    const response = await api.get('/orders');

    expect(response.status).toBe(200);
    expect(response.data.data).toEqual(['order']);
    // The login itself goes out on the plain client: it must not carry the expired token
    expect(requests.map((r) => [r.method, r.url, r.authorization])).toEqual([
      ['get', '/orders', `Bearer ${OLD_TOKEN}`],
      ['post', '/auth/telegram', undefined],
      ['get', '/orders', `Bearer ${NEW_TOKEN}`],
    ]);
  });

  it('posts the launch initData to the login endpoint, with the bot and the deep-link parameter', async () => {
    telegram.tg.initDataUnsafe.start_param = 'store_abc';
    handler = (config) =>
      isLogin(config)
        ? loginReply()
        : bearer(config) === `Bearer ${NEW_TOKEN}` ? { status: 200, data: {} } : { status: 401 };

    await api.get('/orders');

    expect(callsTo('/auth/telegram')[0].body).toEqual({
      initData: INIT_DATA,
      bot_username: 'rizqimbot',
      start_param: 'store_abc',
    });
  });

  it('stores the renewed session: token, normalised user and store', async () => {
    handler = (config) =>
      isLogin(config)
        ? loginReply()
        : bearer(config) === `Bearer ${NEW_TOKEN}` ? { status: 200, data: {} } : { status: 401 };

    await api.get('/orders');

    const state = useAuthStore.getState();
    expect(state.token).toBe(NEW_TOKEN);
    expect(state.isAuthenticated).toBe(true);
    expect(state.smsVerificationRequired).toBe(true);
    expect(state.user?.balance).toBe(12500); // the API's "12500.00" string became a number
    expect(state.store?.name).toBe('Evos');
  });

  it('shares a single re-authentication between parallel 401s and replays each request once', async () => {
    let releaseLogin: (reply: FakeReply) => void = () => {};
    const loginGate = new Promise<FakeReply>((resolve) => {
      releaseLogin = resolve;
    });
    handler = (config) => {
      if (isLogin(config)) return loginGate;
      return bearer(config) === `Bearer ${NEW_TOKEN}`
        ? { status: 200, data: { path: config.url } }
        : { status: 401 };
    };

    const calls = ['/orders', '/notifications', '/complaints', '/couriers', '/user'].map((url) => api.get(url));
    // Let every first attempt come back as a 401 and queue up behind the pending login
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(loginCount()).toBe(1);
    releaseLogin(loginReply());
    const responses = await Promise.all(calls);

    expect(responses.map((r) => r.status)).toEqual([200, 200, 200, 200, 200]);
    expect(responses.map((r) => r.data.path)).toEqual(['/orders', '/notifications', '/complaints', '/couriers', '/user']);
    expect(loginCount()).toBe(1);
    for (const url of ['/orders', '/notifications', '/complaints', '/couriers', '/user']) {
      expect(callsTo(url).map((r) => r.authorization)).toEqual([`Bearer ${OLD_TOKEN}`, `Bearer ${NEW_TOKEN}`]);
    }
  });

  it('starts a new re-authentication when the renewed token expires later on', async () => {
    let tokenCounter = 0;
    handler = (config) => {
      if (isLogin(config)) return loginReply(`token-${++tokenCounter}`);
      return bearer(config)?.startsWith('Bearer token-') ? { status: 200, data: {} } : { status: 401 };
    };

    await api.get('/orders');
    expect(useAuthStore.getState().token).toBe('token-1');

    // The server forgets that token as well
    handler = (config) => {
      if (isLogin(config)) return loginReply(`token-${++tokenCounter}`);
      return bearer(config) === 'Bearer token-2' ? { status: 200, data: {} } : { status: 401 };
    };
    await api.get('/orders');

    expect(loginCount()).toBe(2);
    expect(useAuthStore.getState().token).toBe('token-2');
  });

  it('replays with the token another request already renewed, without logging in again', async () => {
    let firstAttempt = true;
    handler = (config) => {
      if (firstAttempt) {
        firstAttempt = false;
        // While this request was in flight, a parallel one finished its re-authentication
        useAuthStore.getState().setAuth('renewed-elsewhere', makeUser(), makeStore(), false);
        return { status: 401 };
      }
      return bearer(config) === 'Bearer renewed-elsewhere' ? { status: 200, data: { ok: true } } : { status: 401 };
    };

    const response = await api.get('/orders');

    expect(response.data).toEqual({ ok: true });
    expect(loginCount()).toBe(0);
    expect(requests.map((r) => r.authorization)).toEqual([`Bearer ${OLD_TOKEN}`, 'Bearer renewed-elsewhere']);
  });
});

describe('when the session cannot be renewed', () => {
  it('replays only once: a second 401 logs out instead of looping', async () => {
    handler = (config) => (isLogin(config) ? loginReply() : { status: 401, data: { message: 'Unauthenticated.' } });

    await expect(api.get('/orders')).rejects.toMatchObject({ response: { status: 401 } });

    expect(callsTo('/orders')).toHaveLength(2); // the original and exactly one replay
    expect(loginCount()).toBe(1);
    expect(useAuthStore.getState().token).toBeNull();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('logs out and rejects with the original error when the login is refused', async () => {
    handler = (config) => (isLogin(config) ? { status: 401, data: { message: 'Invalid Telegram authentication data.' } } : { status: 401 });

    const error = await api.get('/orders').catch((e: unknown) => e);

    expect(error).toMatchObject({ config: { url: '/orders' }, response: { status: 401 } });
    expect(callsTo('/orders')).toHaveLength(1); // nothing to replay with
    expect(loginCount()).toBe(1);
    expect(useAuthStore.getState().token).toBeNull();
    expect(useAuthStore.getState().user).toBeNull();
  });

  it('logs out when the login endpoint is down', async () => {
    handler = (config) => (isLogin(config) ? { status: 503 } : { status: 401 });

    await expect(api.get('/orders')).rejects.toMatchObject({ response: { status: 401 } });

    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('logs out without calling the login endpoint when the app has no initData', async () => {
    vi.mocked(telegram.getInitData).mockReturnValue('');
    handler = () => ({ status: 401 });

    await expect(api.get('/orders')).rejects.toMatchObject({ response: { status: 401 } });

    expect(loginCount()).toBe(0);
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('never tries to recover a failed login itself', async () => {
    handler = () => ({ status: 401, data: { message: 'Invalid Telegram authentication data.' } });

    await expect(api.post('/auth/telegram', { initData: 'x' })).rejects.toMatchObject({ response: { status: 401 } });

    expect(requests).toHaveLength(1);
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  describe('cooldown', () => {
    beforeEach(() => {
      // Only the clock: promises and the fake adapter keep running normally
      vi.useFakeTimers({ toFake: ['Date'] });
      vi.setSystemTime(new Date('2026-10-18T10:00:00Z'));
    });

    const signInAgain = () => useAuthStore.getState().setAuth(OLD_TOKEN, makeUser(), makeStore(), false);

    it('does not hit the login endpoint again for 30 seconds after a failed re-authentication', async () => {
      handler = (config) => (isLogin(config) ? { status: 401 } : { status: 401 });
      await expect(api.get('/orders')).rejects.toBeDefined();
      expect(loginCount()).toBe(1);

      vi.setSystemTime(new Date('2026-10-18T10:00:29Z'));
      signInAgain();
      await expect(api.get('/orders')).rejects.toBeDefined();
      expect(loginCount()).toBe(1); // still cooling down: the rate-limited login is left alone
      expect(useAuthStore.getState().isAuthenticated).toBe(false);
    });

    it('tries again once the cooldown has passed', async () => {
      handler = (config) => (isLogin(config) ? { status: 401 } : { status: 401 });
      await expect(api.get('/orders')).rejects.toBeDefined();

      vi.setSystemTime(new Date('2026-10-18T10:00:31Z'));
      signInAgain();
      handler = (config) => {
        if (isLogin(config)) return loginReply();
        return bearer(config) === `Bearer ${NEW_TOKEN}` ? { status: 200, data: { ok: true } } : { status: 401 };
      };
      const response = await api.get('/orders');

      expect(response.data).toEqual({ ok: true });
      expect(loginCount()).toBe(2);
    });
  });
});

describe('errors that are not an expired token', () => {
  it.each([400, 403, 404, 422, 429, 500])('passes a %i through without logging in or out', async (status) => {
    handler = () => ({ status, data: { message: 'nope' } });

    await expect(api.get('/orders')).rejects.toMatchObject({ response: { status } });

    expect(requests).toHaveLength(1);
    expect(useAuthStore.getState().token).toBe(OLD_TOKEN);
    expect(useAuthStore.getState().isAuthenticated).toBe(true);
  });
});
