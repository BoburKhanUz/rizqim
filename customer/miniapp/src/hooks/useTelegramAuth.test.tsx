import { renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { makeStore, makeUser } from '../test/factories';
import { useTelegramAuth } from './useTelegramAuth';

// Wave P: on the global bot a customer signs in without a store (`store: null`, a marketplace with no store yet or an
// inactive `store_<uuid>` link). The session is kept and no error is shown.

vi.mock('../lib/telegram', () => ({
  getInitData: vi.fn(() => 'query_id=AAH&user=%7B%7D&hash=abc'),
  getTelegramUser: vi.fn(() => ({ id: 7100001, first_name: 'Aziz' })),
}));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const post = vi.mocked(api.post);

function launchInTelegram(startParam?: string) {
  Object.defineProperty(window, 'Telegram', {
    configurable: true,
    value: { WebApp: { initDataUnsafe: startParam ? { start_param: startParam } : {} } },
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
  useAuthStore.getState().logout();
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
