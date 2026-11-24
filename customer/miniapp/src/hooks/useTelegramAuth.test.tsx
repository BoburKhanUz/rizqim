import { renderHook, waitFor } from '@testing-library/react';
import { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { queryClient } from '../lib/queryClient';
import { revisions } from '../lib/revisions';
import { useSessionStatus } from '../lib/session';
import { getInitData, getTelegramUser } from '../lib/telegram';
import { TEST_TELEGRAM_ID } from '../test/telegramId';
import api from '../services/api';
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
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const post = vi.mocked(api.post);
const get = vi.mocked(api.get);

// The Telegram user who has the app open (the same one lib/telegram's getTelegramUser mock reports)
const CURRENT_TELEGRAM_ID = 7100001;

function launchInTelegram(startParam?: string, telegramId: number | null = CURRENT_TELEGRAM_ID) {
  const user = telegramId === null ? undefined : { id: telegramId, first_name: 'Aziz' };
  vi.mocked(getTelegramUser).mockReturnValue(user as ReturnType<typeof getTelegramUser>);
  Object.defineProperty(window, 'Telegram', {
    configurable: true,
    value: { WebApp: { initDataUnsafe: { ...(startParam ? { start_param: startParam } : {}), ...(user ? { user } : {}) } } },
  });
}

function answerLogin(store: unknown) {
  post.mockResolvedValue({
    data: {
      success: true,
      message: 'Authenticated successfully.',
      data: { token: 'fresh-token', user: makeUser({ telegram_id: '7100001', role: 'customer', store_id: null }), store, sms_verification_required: false },
    },
  });
}

beforeEach(() => {
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
    post.mockReturnValue(new Promise((resolve) => { answer = resolve; }) as ReturnType<typeof api.post>);

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
