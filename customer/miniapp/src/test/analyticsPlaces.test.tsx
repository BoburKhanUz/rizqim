import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { setAnalyticsAuth, track, trackAppOpen } from '../lib/analytics';
import { makeStore, makeUser } from './factories';
import type { UserRole } from '../types';

// Wave P analytics at the places that are not a store page or the cart: the launch (app_open with Telegram's start
// param, the sign-in handed over), the home banner, and the store search (count and length only).

vi.mock('../lib/telegram', () => ({
  initTelegramApp: vi.fn(),
  hapticFeedback: vi.fn(),
  getInitData: () => '',
  getTelegramUser: () => undefined,
  tg: { initData: 'query_id=AAH&auth_date=1&hash=abc', initDataUnsafe: { start_param: 'c_tg_ads_1' }, platform: 'ios' },
}));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../lib/analytics', () => ({ track: vi.fn(), setAnalyticsAuth: vi.fn(), trackAppOpen: vi.fn() }));
vi.mock('../hooks/useTelegramAuth', async () => {
  const { useAuthStore } = await import('../store/authStore');
  return {
    useTelegramAuth: () => {
      const { user, token, isAuthenticated } = useAuthStore();
      return { isAuthenticating: false, error: null, isAuthenticated, token, user };
    },
  };
});
vi.mock('../pages/Home', () => ({ default: () => <p>Katalog sahifasi</p> }));
vi.mock('../pages/AdminAnalytics', () => ({ default: () => <p>Analitika sahifasi</p> }));

const { default: App } = await import('../App');
const { default: HeroPromoSlider } = await import('../components/HeroPromoSlider');
const { default: Stores } = await import('../pages/Stores');
const { useAuthStore } = await import('../store/authStore');

const get = vi.mocked(api.get);

beforeEach(() => {
  vi.mocked(track).mockClear();
  vi.mocked(trackAppOpen).mockClear();
  vi.mocked(setAnalyticsAuth).mockClear();
});

describe('the launch', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'Telegram', { configurable: true, value: { WebApp: { initData: 'query_id=AAH&hash=abc' } } });
  });
  afterEach(() => {
    Reflect.deleteProperty(window, 'Telegram');
    window.history.pushState({}, '', '/');
  });

  const signInAs = (role: UserRole) =>
    useAuthStore.getState().setAuth('token', makeUser({ role, phone: '+998901234567', store_id: role === 'customer' ? null : 1 }), makeStore(), false);

  it('opens the session with the start param of the launch and hands the customer sign-in over', async () => {
    signInAs('customer');
    render(<App />);
    expect(await screen.findByText('Katalog sahifasi')).toBeInTheDocument();
    // The launch data goes along only to tell a relaunch from a reload (the client keeps a short hash of it)
    expect(trackAppOpen).toHaveBeenCalledWith('c_tg_ads_1', 'query_id=AAH&auth_date=1&hash=abc');
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: 'token', allowed: true, userId: useAuthStore.getState().user?.id });
  });

  it('marks staff as never tracked', async () => {
    signInAs('owner');
    render(<App />);
    expect(await screen.findByText('Analitika sahifasi')).toBeInTheDocument();
    expect(setAnalyticsAuth).toHaveBeenLastCalledWith({ token: 'token', allowed: false, userId: useAuthStore.getState().user?.id });
  });
});

describe('the home banner', () => {
  it('sends promo_banner_click with the banner id', () => {
    render(
      <MemoryRouter>
        <HeroPromoSlider promos={[{ id: 9, title: 'Chegirma', image_url: null, store_uuid: 's-1', product_id: null }]} />
      </MemoryRouter>,
    );
    fireEvent.click(screen.getByRole('group', { name: '1 / 1' }));
    expect(vi.mocked(track).mock.calls).toEqual([['promo_banner_click', { props: { promo_id: 9 } }]]);
  });
});

describe('the store search', () => {
  beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
  afterEach(() => vi.useRealTimers());

  it('sends search with the number of stores found and the length of the query, never the query', async () => {
    const stores = [1, 2, 3].map((id) => ({ ...makeStore({ id, uuid: `s-${id}`, name: id === 3 ? 'Somsa markazi' : `Oshxona ${id}` }), branches: [] }));
    get.mockResolvedValue({ data: { success: true, data: stores, meta: { page: 1, per_page: 20, total: 3, last_page: 1 } } });
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter>
          <Stores />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    await act(async () => { await vi.advanceTimersByTimeAsync(10); });
    fireEvent.change(screen.getByPlaceholderText('Oshxonalarni qidirish'), { target: { value: 'oshx' } });
    await act(async () => { await vi.advanceTimersByTimeAsync(1_500); });

    expect(vi.mocked(track).mock.calls).toEqual([['search', { props: { results: 2, length: 4 } }]]);
    expect(JSON.stringify(vi.mocked(track).mock.calls)).not.toContain('oshx');
  });
});
