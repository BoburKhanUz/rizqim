import { act, renderHook, waitFor } from '@testing-library/react';
import { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { queryClient } from '../lib/queryClient';
import { revisions } from '../lib/revisions';
import { signInAgain, useSessionStatus } from '../lib/session';
import { getInitData, getTelegramUser } from '../lib/telegram';
import { resetSignIn } from '../lib/telegramSignIn';
import { TEST_TELEGRAM_ID } from '../test/telegramId';
import api from '../services/api';
import { authClient } from '../services/authClient';
import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { useAddressStore } from '../store/useAddressStore';
import { makeStore, makeUser } from '../test/factories';
import { useTelegramAuth } from './useTelegramAuth';

// Wave P: on the global bot a customer signs in without a store (`store: null`, a marketplace with no store yet or an
// inactive `store_<uuid>` link). The session is kept and no error is shown.

vi.mock('../lib/telegram', () => ({
  getInitData: vi.fn(() => 'query_id=AAH&user=%7B%7D&hash=abc'),
  getTelegramUser: vi.fn(() => ({ id: 7100001, first_name: 'Aziz' })),
  tg: { initDataUnsafe: {} },
}));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));
// The sign-in goes out on the plain client (no interceptors), shared with the 401 handler (lib/telegramSignIn.ts). The
// test setup already loaded it (through services/staffApi.ts), so its one instance is spied on rather than mocked (in
// beforeEach: the config restores spies before every test).
const post = vi.fn<typeof authClient.post>();
const get = vi.mocked(api.get);

// The Telegram user who has the app open (the same one lib/telegram's getTelegramUser mock reports)
const CURRENT_TELEGRAM_ID = 7100001;

// `initData`: what window.Telegram.WebApp.initData holds (its auth_date tells how old the launch is)
function launchInTelegram(startParam?: string, telegramId: number | null = CURRENT_TELEGRAM_ID, initData?: string) {
  const user = telegramId === null ? undefined : { id: telegramId, first_name: 'Aziz' };
  vi.mocked(getTelegramUser).mockReturnValue(user as ReturnType<typeof getTelegramUser>);
  Object.defineProperty(window, 'Telegram', {
    configurable: true,
    value: { WebApp: { ...(initData ? { initData } : {}), initDataUnsafe: { ...(startParam ? { start_param: startParam } : {}), ...(user ? { user } : {}) } } },
  });
}

const loginAnswer = (store: unknown) => ({
  data: {
    success: true,
    message: 'Authenticated successfully.',
    data: { token: 'fresh-token', user: makeUser({ telegram_id: '7100001', role: 'customer', store_id: null }), store, sms_verification_required: false },
  },
});

function answerLogin(store: unknown) {
  post.mockResolvedValue(loginAnswer(store));
}

beforeEach(() => {
  resetSignIn();
  vi.spyOn(authClient, 'post').mockImplementation((...args) => post(...args));
  post.mockReset();
  get.mockReset();
  vi.mocked(getInitData).mockReturnValue('query_id=AAH&user=%7B%7D&hash=abc');
  useAuthStore.getState().logout();
  useCartStore.getState().clearCart();
  useAddressStore.setState({ addresses: [], selectedAddressId: null });
});

afterEach(() => {
  Reflect.deleteProperty(window, 'Telegram');
});

describe('useTelegramAuth: sign-in without a store', () => {
  it('keeps the session when the backend answers store: null', async () => {
    launchInTelegram();
    answerLogin(null);

    const { result } = renderHook(() => useTelegramAuth());
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.error).toBeNull();
    const state = useAuthStore.getState();
    expect([state.isAuthenticated, state.token, state.store]).toEqual([true, 'fresh-token', null]);
    expect(state.user?.store_id).toBeNull();
  });

  it('an inactive store link signs in the same way, with the link sent as start_param', async () => {
    launchInTelegram('store_0b5c1f8e-0000-4000-8000-000000000000');
    answerLogin(null);

    const { result } = renderHook(() => useTelegramAuth());
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(post.mock.calls[0][1]).toMatchObject({ start_param: 'store_0b5c1f8e-0000-4000-8000-000000000000' });
    expect(result.current.error).toBeNull();
    expect(useAuthStore.getState().store).toBeNull();
  });

  it('a stale store from an earlier session is replaced by null', async () => {
    useAuthStore.getState().setAuth('old-token', makeUser({ telegram_id: '999' }), makeStore({ name: 'Evos' }), false);
    launchInTelegram();
    answerLogin(null);

    const { result } = renderHook(() => useTelegramAuth());
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(useAuthStore.getState().store).toBeNull();
    expect(useAuthStore.getState().token).toBe('fresh-token');
  });
});

// ---------------------------------------------------------------------------------------------------------------
// The per-user stores are opened under the key of the Telegram user who has the app open (lib/userStorage.ts), so the
// cart and the addresses in them are that user's own. In memory, a session whose user is not the current Telegram user
// is never used, shown or sent: the current user signs in again, and if that fails the app is signed out on the sign-in
// error screen. The cart and addresses are never wiped for it.

const A_TOKEN = 'token-of-A';
const A_TELEGRAM_ID = '4200042';

// A session of `telegramId` in the store, with a cart and an address (seeded while that user "has the app open", so the
// store accepts it), then the app is opened by the Telegram user `launchAs`
function seedSessionOf(telegramId: string, token = A_TOKEN) {
  launchInTelegram(undefined, Number(telegramId));
  useAuthStore.getState().setAuth(token, makeUser({ id: 42, telegram_id: telegramId, name: 'Aziza A', phone: '+998907777777' }), makeStore(), false);
  useCartStore.getState().addItem({ id: 'v1', productId: 1, variantId: 'v1', storeId: 's1', name: 'Lavash', price: 25000, quantity: 2 }, { name: 'Evos', address: 'Chilonzor' });
  useCartStore.getState().setSpecialInstructions('Domofon 42');
  useAddressStore.getState().addAddress({ id: 9, title: 'Uy', address: 'Yunusobod 4', lat: 41.3, lng: 69.3, is_default: true });
}

const httpError = (status: number, message = 'Server Error') => {
  const config = { headers: {} } as InternalAxiosRequestConfig;
  const response = { status, statusText: String(status), data: { message }, headers: {}, config } as AxiosResponse;
  return new AxiosError(`Request failed with status code ${status}`, status >= 500 ? 'ERR_BAD_RESPONSE' : 'ERR_BAD_REQUEST', config, null, response);
};

const FAILURES: Array<[string, () => unknown]> = [
  ['a 500', () => httpError(500)],
  ['a 429', () => httpError(429, 'Too Many Attempts.')],
  ['a timeout', () => new AxiosError('timeout of 10000ms exceeded', 'ECONNABORTED')],
  ['a network error', () => new AxiosError('Network Error', 'ERR_NETWORK')],
];

type AuthResult = ReturnType<typeof useTelegramAuth>;

// Every value the hook ever returned, so a single render that exposed A is caught too
function renderAuth() {
  const seen: AuthResult[] = [];
  const hook = renderHook(() => {
    const value = useTelegramAuth();
    seen.push(value);
    return value;
  });
  return { ...hook, seen };
}

function expectSessionOfANeverUsed(seen: AuthResult[]) {
  for (const value of seen) {
    expect(value.token).not.toBe(A_TOKEN);
    expect(value.user?.telegram_id).not.toBe(A_TELEGRAM_ID);
    if (value.isAuthenticated) expect(value.user?.telegram_id).toBe(String(CURRENT_TELEGRAM_ID));
  }
  expect(JSON.stringify({ ...localStorage })).not.toContain(A_TOKEN);
  for (const call of get.mock.calls) expect(JSON.stringify(call)).not.toContain(A_TOKEN);
}

// The cart and addresses of this storage are untouched
function expectCartAndAddressesKept() {
  expect(useCartStore.getState().items).toHaveLength(1);
  expect(useCartStore.getState().specialInstructions).toBe('Domofon 42');
  expect(useAddressStore.getState().addresses).toHaveLength(1);
}

describe('useTelegramAuth: a stored session whose user is not the current Telegram user', () => {
  it.each(FAILURES)('the sign-in fails with %s: signed out, the error screen, the session never used', async (_name, makeError) => {
    seedSessionOf(A_TELEGRAM_ID);
    launchInTelegram();
    post.mockRejectedValue(makeError());

    const { result, seen } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.error).toBeTruthy();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.token).toBeNull();
    expect(result.current.user).toBeNull();
    expect(useAuthStore.getState()).toMatchObject({ token: null, user: null, store: null, isAuthenticated: false });
    // Signed in as the current user (with this launch's initData), never anything sent on A's behalf
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/auth/telegram');
    expect(get).not.toHaveBeenCalled();
    expectSessionOfANeverUsed(seen);
    expectCartAndAddressesKept();
  });

  it('the session is dropped before the first render, not only once the sign-in has failed', async () => {
    seedSessionOf(A_TELEGRAM_ID);
    launchInTelegram();
    let answer: (value: unknown) => void = () => {};
    post.mockReturnValue(new Promise((resolve) => { answer = resolve; }) as ReturnType<typeof authClient.post>);

    const { result, seen } = renderAuth();
    expect(seen[0]).toMatchObject({ isAuthenticated: false, token: null, user: null });
    await waitFor(() => expect(post).toHaveBeenCalled());
    // While the sign-in is still on its way the stored session is already gone
    expect(useAuthStore.getState().token).toBeNull();
    expectSessionOfANeverUsed(seen);

    answer({ data: { success: false } });
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));
    expect(result.current.isAuthenticated).toBe(false);
  });

  it('the sign-in succeeds: the current user is signed in', async () => {
    seedSessionOf(A_TELEGRAM_ID);
    launchInTelegram();
    answerLogin(makeStore({ name: 'B store' }));

    const { result, seen } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.error).toBeNull();
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'fresh-token' });
    expect(result.current.user?.telegram_id).toBe(String(CURRENT_TELEGRAM_ID));
    expectSessionOfANeverUsed(seen);
  });

  it('a deep link (start_param) does not keep it either', async () => {
    seedSessionOf(A_TELEGRAM_ID);
    launchInTelegram('store_0b5c1f8e-0000-4000-8000-000000000000');
    post.mockRejectedValue(httpError(500));

    const { result, seen } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.isAuthenticated).toBe(false);
    expectSessionOfANeverUsed(seen);
  });

  it('a backend answer for yet another Telegram account is never stored or used', async () => {
    launchInTelegram();
    post.mockResolvedValue({ data: { success: true, data: { token: 'odd-token', user: makeUser({ telegram_id: '999' }), store: null, sms_verification_required: false } } });

    const { result, seen } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.error).toBeTruthy();
    expect(useAuthStore.getState().token).toBeNull();
    for (const value of seen) expect(value.token).not.toBe('odd-token');
    expect(JSON.stringify({ ...localStorage })).not.toContain('odd-token');
  });
});

describe('useTelegramAuth: the same Telegram user', () => {
  it('a deep-link re-sign-in that fails on a flaky network keeps the session of the same person', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    launchInTelegram('store_0b5c1f8e-0000-4000-8000-000000000000');
    post.mockRejectedValue(new AxiosError('Network Error', 'ERR_NETWORK'));

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.error).toBeTruthy();
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'own-token' });
    expectCartAndAddressesKept();
  });

  it('opens at once with the stored session and refreshes it in the background', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    get.mockResolvedValue({ data: { success: false } });

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(post).not.toHaveBeenCalled();
    expect(get).toHaveBeenCalledWith('/user');
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'own-token', error: null });
  });
});

// Finding 3: the backend unlinked the account's telegram_id (an invite was claimed elsewhere) and a refresh brings that
// in. The session no longer matches the Telegram user: the app signs in again with initData, keeping the cart and the
// addresses (they are kept per Telegram user); if the sign-in fails, the sign-in error screen.
describe('useTelegramAuth: the stored user stops matching the Telegram user mid-session', () => {
  const unlinked = () => ({ data: { success: true, data: { user: makeUser({ telegram_id: null as unknown as string }), store: null, sms_verification_required: false } } });

  it('the background refresh brings telegram_id: null: a new sign-in with initData, the data kept', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    get.mockResolvedValue(unlinked());
    answerLogin(null);

    const { result, seen } = renderAuth();
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(post.mock.calls[0][1]).toMatchObject({ initData: 'query_id=AAH&user=%7B%7D&hash=abc' });
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'fresh-token', error: null });
    for (const value of seen) expect(value.user?.telegram_id ?? String(CURRENT_TELEGRAM_ID)).toBe(String(CURRENT_TELEGRAM_ID));
    expectCartAndAddressesKept();
  });

  it('... and when that sign-in fails: signed out on the error screen, the data kept', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    get.mockResolvedValue(unlinked());
    post.mockRejectedValue(httpError(500));

    const { result } = renderAuth();
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.error).toBeTruthy();
    expect(result.current).toMatchObject({ isAuthenticated: false, token: null, user: null });
    expect(useAuthStore.getState().token).toBeNull();
    expectCartAndAddressesKept();
  });

  it('any other write of such a user (the profile\'s refresh) leads to the same new sign-in', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    get.mockResolvedValue({ data: { success: false } });
    answerLogin(null);

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));
    expect(post).not.toHaveBeenCalled();

    useAuthStore.getState().updateUser({ telegram_id: null as unknown as string });

    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(result.current).toMatchObject({ isAuthenticating: false, isAuthenticated: true, token: 'fresh-token' }));
    expectCartAndAddressesKept();
  });
});

// Finding 5: every sign-out ends on the sign-in error screen, with the session, the query cache and the change tokens gone
describe('useTelegramAuth: the session is refused (401) and cannot be renewed', () => {
  it('signs out to the error screen and clears the caches; the cart and addresses stay', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    queryClient.setQueryData(['orders'], [{ id: 1 }]);
    const clearRevisions = vi.spyOn(revisions, 'clear');
    get.mockRejectedValue(httpError(401, 'Unauthenticated.'));

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.error).toBeTruthy());

    expect(result.current).toMatchObject({ isAuthenticating: false, isAuthenticated: false, token: null });
    expect(useSessionStatus.getState().error).toBeTruthy();
    expect(queryClient.getQueryCache().getAll()).toEqual([]);
    expect(clearRevisions).toHaveBeenCalled();
    expectCartAndAddressesKept();
  });
});

describe('useTelegramAuth: no Telegram user (opened outside Telegram)', () => {
  it('a production build never uses a stored session it cannot tie to a Telegram user', async () => {
    seedSessionOf(A_TELEGRAM_ID);
    vi.stubEnv('DEV', false);
    launchInTelegram(undefined, null);
    vi.mocked(getInitData).mockReturnValue('');

    const { result, seen } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.error).toBeTruthy();
    expect(post).not.toHaveBeenCalled();
    expect(get).not.toHaveBeenCalled();
    for (const value of seen) expect(value.token).toBeNull();
    expect(useAuthStore.getState().token).toBeNull();
  });

  it('the dev server signs in as the mock account its storage was chosen for', async () => {
    launchInTelegram(undefined, null);
    vi.mocked(getInitData).mockReturnValue('');
    post.mockResolvedValue({ data: { success: true, data: { token: 'mock-token', user: makeUser(), store: null, sms_verification_required: false } } });

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    const initData = (post.mock.calls[0][1] as { initData: string }).initData;
    expect(initData).toContain('mock_data=true');
    expect(JSON.parse(new URLSearchParams(initData).get('user') ?? '{}')).toMatchObject({ id: Number(TEST_TELEGRAM_ID) });
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'mock-token' });
  });
});

// ---------------------------------------------------------------------------------------------------------------
// The backend accepts a launch's initData for 10 minutes only, and a reload keeps the same initData. A sign-in that
// cannot succeed because the launch is too old asks to close the app and open it again (WebApp.close()), never to reload.
const DEEP_LINK = 'store_0b5c1f8e-0000-4000-8000-000000000000';
const initDataAged = (seconds: number) => `query_id=AAH&user=%7B%7D&auth_date=${Math.floor(Date.now() / 1000) - seconds}&hash=abc`;

describe('useTelegramAuth: launch data too old to sign in', () => {
  it('the server refuses the launch data (401): the "close and reopen" screen', async () => {
    launchInTelegram();
    post.mockRejectedValue(httpError(401, 'Invalid Telegram authentication data.'));

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current).toMatchObject({ isAuthenticated: false, reopen: true });
    expect(result.current.error).toBeTruthy();
  });

  it('the launch is older than 9 minutes by its auth_date: a failure asks to reopen as well', async () => {
    launchInTelegram(undefined, CURRENT_TELEGRAM_ID, initDataAged(9 * 60 + 30));
    post.mockRejectedValue(httpError(500));

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current).toMatchObject({ isAuthenticated: false, reopen: true });
  });

  it.each(FAILURES)('a fresh launch that fails with %s keeps the reload retry', async (_name, makeError) => {
    launchInTelegram(undefined, CURRENT_TELEGRAM_ID, initDataAged(60));
    post.mockRejectedValue(makeError());

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current).toMatchObject({ isAuthenticated: false, reopen: false });
    expect(result.current.error).toBeTruthy();
  });

  it('once refused, the same launch data is never posted again (a later sign-in asks to reopen at once)', async () => {
    launchInTelegram();
    post.mockRejectedValue(httpError(401, 'Invalid Telegram authentication data.'));
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    act(() => signInAgain());
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(post).toHaveBeenCalledTimes(1);
    expect(result.current).toMatchObject({ isAuthenticated: false, reopen: true });
  });

  it.each([
    ['a 401 (the launch data is too old)', () => httpError(401, 'Invalid Telegram authentication data.')],
    ...FAILURES,
  ])('a deep link with a valid stored session of the same person: %s falls back to that session', async (_name, makeError) => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    launchInTelegram(DEEP_LINK);
    post.mockRejectedValue(makeError());

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));

    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'own-token' });
    expect(useAuthStore.getState().token).toBe('own-token');
    expect(JSON.stringify({ ...localStorage })).toContain('own-token');
    expectCartAndAddressesKept();
  });
});

describe('useTelegramAuth: the background GET /user', () => {
  it('an answer that comes back after the token changed is not applied', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    let answer: (value: unknown) => void = () => {};
    get.mockReturnValue(new Promise((resolve) => { answer = resolve; }) as ReturnType<typeof api.get>);

    const { result } = renderAuth();
    await waitFor(() => expect(get).toHaveBeenCalledWith('/user'));
    // Meanwhile the session was renewed (the 401 handler signed in again)
    act(() => useAuthStore.getState().setAuth('renewed-token', makeUser({ telegram_id: String(CURRENT_TELEGRAM_ID), name: 'Renewed' }), null, false));
    answer({ config: { _authToken: 'own-token' }, data: { success: true, data: { user: makeUser({ telegram_id: String(CURRENT_TELEGRAM_ID), name: 'Stale answer' }), store: makeStore({ name: 'Stale' }), sms_verification_required: true } } });
    await act(async () => {});

    expect(useAuthStore.getState()).toMatchObject({ token: 'renewed-token', user: { name: 'Renewed' }, store: null, smsVerificationRequired: false });
    expect(result.current.token).toBe('renewed-token');
  });

  it('an answer for the token it was sent with is applied', async () => {
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    get.mockResolvedValue({ config: { _authToken: 'own-token' }, data: { success: true, data: { user: makeUser({ telegram_id: String(CURRENT_TELEGRAM_ID), name: 'Fresh' }), store: null, sms_verification_required: false } } });

    renderAuth();
    await waitFor(() => expect(useAuthStore.getState().user?.name).toBe('Fresh'));
    expect(useAuthStore.getState().token).toBe('own-token');
  });
});

describe('useTelegramAuth: one sign-in at a time', () => {
  it('signInAgain while a sign-in is running does not start a second one', async () => {
    launchInTelegram();
    let answer: (value: unknown) => void = () => {};
    post.mockReturnValue(new Promise((resolve) => { answer = resolve; }) as ReturnType<typeof authClient.post>);

    const { result } = renderAuth();
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    act(() => signInAgain());
    act(() => signInAgain());
    await act(async () => {});
    expect(post).toHaveBeenCalledTimes(1);

    answer(loginAnswer(null));
    await waitFor(() => expect(result.current.isAuthenticating).toBe(false));
    expect(post).toHaveBeenCalledTimes(1);
    expect(result.current).toMatchObject({ isAuthenticated: true, token: 'fresh-token', error: null });
  });

  it('a sign-in that ends after the hook is gone (unmount: the effect is stale) changes nothing', async () => {
    launchInTelegram();
    let fail: (error: unknown) => void = () => {};
    post.mockReturnValue(new Promise((_resolve, reject) => { fail = reject; }) as ReturnType<typeof authClient.post>);

    const { unmount } = renderAuth();
    await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
    unmount();
    fail(httpError(500));
    await act(async () => {});

    expect(useSessionStatus.getState()).toMatchObject({ signingIn: true, error: null });
  });

  it('a successful sign-in clears an error an earlier attempt left', async () => {
    useSessionStatus.setState({ signingIn: false, error: 'Connection timeout.', reopen: false });
    seedSessionOf(String(CURRENT_TELEGRAM_ID), 'own-token');
    launchInTelegram(DEEP_LINK);
    answerLogin(null);

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.token).toBe('fresh-token'));
    expect(useSessionStatus.getState().error).toBeNull();
  });
});
