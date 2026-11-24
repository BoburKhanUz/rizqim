import { useState } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { track, trackAppOpen } from '../lib/analytics';
import type { AnalyticsClient } from '../lib/analyticsClient';
import { signInAgain, signOut } from '../lib/session';
import { getTelegramUser } from '../lib/telegram';
import { resetSignIn } from '../lib/telegramSignIn';
import { useAnalyticsAuth } from '../hooks/useAnalytics';
import { useTelegramAuth } from '../hooks/useTelegramAuth';
import api from '../services/api';
import { authClient } from '../services/authClient';
import { useAuthStore } from '../store/authStore';
import { makeUser } from './factories';

// The analytics client (lib/analyticsClient.ts) behind the app's real sign-in (useTelegramAuth) and its hand-over to
// analytics (useAnalyticsAuth), across a sign-out and a sign-in again: an event is only ever sent with the token of the
// account it was recorded under (never under another identity), and a launch is opened (app_open) once, not again for
// each sign-in of the same account.

interface SentBatch {
  token: string;
  session: string;
  events: Array<{ uid: string; name: string }>;
}

const harness = vi.hoisted(() => ({
  sent: [] as SentBatch[],
  status: 202,
  client: null as AnalyticsClient | null,
}));

vi.mock('../lib/analytics', async () => {
  const { createAnalytics } = await import('../lib/analyticsClient');
  const memory = new Map<string, string>();
  const client = createAnalytics({
    enabled: true,
    endpoint: '/api/v1/analytics/events',
    storage: () => ({ getItem: (k: string) => memory.get(k) ?? null, setItem: (k: string, v: string) => void memory.set(k, v), removeItem: (k: string) => void memory.delete(k) }) as unknown as Storage,
    fetch: async (_url, init) => {
      const body = JSON.parse(String(init?.body)) as { session_id: string; events: SentBatch['events'] };
      const token = String((init?.headers as Record<string, string>).Authorization).replace(/^Bearer /, '');
      harness.sent.push({ token, session: body.session_id, events: body.events });
      return { status: harness.status, headers: new Headers() } as Response;
    },
  });
  harness.client = client;
  return { track: client.track, trackAppOpen: client.appOpen, setAnalyticsAuth: client.setAuth };
});
vi.mock('../lib/telegram', () => ({
  getInitData: vi.fn(() => 'query_id=AAH&user=%7B%7D&hash=abc'),
  getTelegramUser: vi.fn(() => ({ id: 7100001, first_name: 'Aziz' })),
  tg: { initDataUnsafe: {} },
}));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));
// The sign-in's plain client: already loaded by the test setup, so its one instance is spied on (not mocked), in
// beforeEach (the config restores spies before every test)
const post = vi.fn<typeof authClient.post>();

const TELEGRAM_ID = '7100001';
const client = () => harness.client as AnalyticsClient;
const opensOf = (batches: SentBatch[]) => batches.flatMap((batch) => batch.events.filter((event) => event.name === 'app_open'));
const sentWith = (token: string) => harness.sent.filter((batch) => batch.token === token);

function signedInAs(token: string, id: number) {
  return { data: { success: true, data: { token, user: makeUser({ id, telegram_id: TELEGRAM_ID, role: 'customer', store_id: null }), store: null, sms_verification_required: false } } };
}

// The app's shell as far as analytics goes (App.tsx AppContent): the launch is opened once, then the sign-in is handed over
function useApp() {
  useState(() => trackAppOpen(null, 'query_id=AAH&auth_date=100&hash=abc'));
  const auth = useTelegramAuth();
  useAnalyticsAuth(auth.isAuthenticating, auth.token, auth.user);
  return auth;
}

beforeEach(() => {
  resetSignIn();
  vi.spyOn(authClient, 'post').mockImplementation((...args) => post(...args));
  harness.sent.length = 0;
  harness.status = 202;
  post.mockReset();
  vi.mocked(api.get).mockReset().mockResolvedValue({ data: { success: false } });
  vi.mocked(getTelegramUser).mockReturnValue({ id: Number(TELEGRAM_ID), first_name: 'Aziz' } as ReturnType<typeof getTelegramUser>);
  Object.defineProperty(window, 'Telegram', { configurable: true, value: { WebApp: { initDataUnsafe: { user: { id: Number(TELEGRAM_ID) } } } } });
  // A stored session of this Telegram user's account 42
  useAuthStore.getState().setAuth('token-42', makeUser({ id: 42, telegram_id: TELEGRAM_ID, role: 'customer', store_id: null }), null, false);
});

afterEach(() => {
  useAuthStore.getState().logout();
  Reflect.deleteProperty(window, 'Telegram');
});

describe('analytics across a sign-out and a sign-in again', () => {
  it('signed out, then the same account signs in again: its events go with its new token, one app_open in all', async () => {
    const { result } = renderHook(() => useApp());
    await waitFor(() => expect(result.current.token).toBe('token-42'));
    track('store_view', { store_id: 1 });
    await act(() => client().flush({ hide: true }));
    expect(sentWith('token-42').flatMap((b) => b.events.map((e) => e.name))).toEqual(['app_open', 'store_view']);

    // The token is refused and cannot be renewed: signed out. What happens meanwhile is nobody's yet, and nothing is sent
    const sentBefore = harness.sent.length;
    act(() => signOut());
    track('search', { props: { results: 2, length: 4 } });
    await act(() => client().flush({ hide: true }));
    expect(harness.sent).toHaveLength(sentBefore);

    // The same account signs in again (a new token)
    post.mockResolvedValue(signedInAs('token-42b', 42));
    act(() => signInAgain());
    await waitFor(() => expect(result.current.token).toBe('token-42b'));
    await act(() => client().flush({ hide: true }));

    expect(sentWith('token-42b').flatMap((b) => b.events.map((e) => e.name))).toEqual(['search']);
    expect(harness.sent.every((batch) => batch.token === 'token-42' || batch.token === 'token-42b')).toBe(true);
    expect(opensOf(harness.sent)).toHaveLength(1);
    expect(new Set(harness.sent.map((batch) => batch.session)).size).toBe(1);
  });

  it('signInAgain lands on another account (account linking): what 42 queued is never sent with its token', async () => {
    harness.status = 500; // 42's batch does not get through: its events stay queued
    const { result } = renderHook(() => useApp());
    await waitFor(() => expect(result.current.token).toBe('token-42'));
    track('store_view', { store_id: 1 });
    track('add_to_cart', { store_id: 1, product_id: 3 });
    await act(() => client().flush({ hide: true }));
    const queuedBy42 = new Set(sentWith('token-42').flatMap((b) => b.events.map((e) => e.uid)));
    expect(queuedBy42.size).toBeGreaterThan(0); // tried (and refused) with 42's token, so still queued

    harness.status = 202;
    post.mockResolvedValue(signedInAs('token-77', 77));
    act(() => signInAgain());
    await waitFor(() => expect(result.current.token).toBe('token-77'));
    track('search', { props: { results: 1, length: 3 } });
    await act(() => client().flush({ hide: true }));

    const of77 = sentWith('token-77');
    expect(of77.length).toBeGreaterThan(0);
    expect(of77.flatMap((b) => b.events).filter((event) => queuedBy42.has(event.uid))).toEqual([]);
    // A session is one account's: 77's has its own app_open, once
    expect(opensOf(of77)).toHaveLength(1);
    expect(of77.flatMap((b) => b.events.map((e) => e.name))).toEqual(['app_open', 'search']);
    expect(new Set(of77.map((b) => b.session)).size).toBe(1);
    expect(of77[0].session).not.toBe(sentWith('token-42')[0].session);
    // ... and nothing more ever goes with 42's token
    const before = sentWith('token-42').length;
    await act(() => client().flush({ hide: true }));
    expect(sentWith('token-42').length).toBe(before);
  });
});
