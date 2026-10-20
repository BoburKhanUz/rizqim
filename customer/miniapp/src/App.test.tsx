import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { makeStore, makeUser } from './test/factories';
import type { UserRole } from './types';

// The app shell with stub pages: what is under test is which page each role lands on, and that pages are
// loaded lazily (every route page is a dynamic import, only Home is part of the entry bundle).

vi.mock('./lib/telegram', () => ({
  initTelegramApp: vi.fn(),
  hapticFeedback: vi.fn(),
  getInitData: () => '',
  getTelegramUser: () => undefined,
  tg: { initDataUnsafe: {} },
}));

// Signing in is not under test: the screens see whoever the test signed in
vi.mock('./hooks/useTelegramAuth', async () => {
  const { useAuthStore } = await import('./store/authStore');
  return {
    useTelegramAuth: () => {
      const { user, token, isAuthenticated } = useAuthStore();
      return { isAuthenticating: false, error: null, isAuthenticated, token, user };
    },
  };
});

const stubs: Record<string, string> = {
  Home: 'Katalog sahifasi',
  CustomerProfile: 'Mijoz profili',
  Profile: 'Xodim profili',
  AdminAnalytics: 'Analitika sahifasi',
  ManagerDashboard: 'Menejer sahifasi',
  CourierDashboard: 'Kuryer sahifasi',
  SuperadminDashboard: 'Superadmin sahifasi',
  Orders: 'Buyurtmalar sahifasi',
  StoreDetail: 'Restoran sahifasi',
  CategoryDetail: 'Turkum sahifasi',
  Stores: 'Restoranlar sahifasi',
};
vi.mock('./pages/Home', () => ({ default: () => <p>{stubs.Home}</p> }));
vi.mock('./pages/CustomerProfile', () => ({ default: () => <p>{stubs.CustomerProfile}</p> }));
vi.mock('./pages/Profile', () => ({ default: () => <p>{stubs.Profile}</p> }));
vi.mock('./pages/AdminAnalytics', () => ({ default: () => <p>{stubs.AdminAnalytics}</p> }));
vi.mock('./pages/ManagerDashboard', () => ({ default: () => <p>{stubs.ManagerDashboard}</p> }));
vi.mock('./pages/CourierDashboard', () => ({ default: () => <p>{stubs.CourierDashboard}</p> }));
vi.mock('./pages/SuperadminDashboard', () => ({ default: () => <p>{stubs.SuperadminDashboard}</p> }));
vi.mock('./pages/Orders', () => ({ default: () => <p>{stubs.Orders}</p> }));
vi.mock('./pages/StoreDetail', () => ({ default: () => <p>{stubs.StoreDetail}</p> }));
vi.mock('./pages/CategoryDetail', () => ({ default: () => <p>{stubs.CategoryDetail}</p> }));
vi.mock('./pages/Stores', () => ({ default: () => <p>{stubs.Stores}</p> }));

const { default: App } = await import('./App');
const { useAuthStore } = await import('./store/authStore');

function signInAs(role: UserRole) {
  useAuthStore.getState().setAuth('token', makeUser({ role, phone: '+998901234567' }), makeStore(), false);
}

function openApp(path: string) {
  window.history.pushState({}, '', path);
  return render(<App />);
}

beforeEach(() => {
  // Inside Telegram: window.Telegram.WebApp.initData is set (the app refuses to run in a plain browser)
  Object.defineProperty(window, 'Telegram', { configurable: true, value: { WebApp: { initData: 'query_id=AAH&hash=abc' } } });
});

afterEach(() => {
  Reflect.deleteProperty(window, 'Telegram');
  window.history.pushState({}, '', '/');
});

describe('where "/" leads', () => {
  it('shows the catalog to a customer', async () => {
    signInAs('customer');
    openApp('/');

    expect(await screen.findByText(stubs.Home)).toBeInTheDocument();
    expect(window.location.pathname).toBe('/');
  });

  it.each([
    ['owner', '/admin/analytics', stubs.AdminAnalytics],
    ['manager', '/manager', stubs.ManagerDashboard],
    ['courier', '/courier', stubs.CourierDashboard],
    ['super_admin', '/superadmin/dashboard', stubs.SuperadminDashboard],
  ] as const)('sends a %s to %s', async (role, path, pageText) => {
    signInAs(role);
    openApp('/');

    expect(await screen.findByText(pageText)).toBeInTheDocument();
    expect(window.location.pathname).toBe(path);
    expect(screen.queryByText(stubs.Home)).not.toBeInTheDocument();
  });
});

describe('/profile', () => {
  it('is the customer profile for a customer', async () => {
    signInAs('customer');
    openApp('/profile');

    expect(await screen.findByText(stubs.CustomerProfile)).toBeInTheDocument();
  });

  it.each(['owner', 'manager', 'courier', 'super_admin'] as const)('is the staff profile for a %s', async (role) => {
    signInAs(role);
    openApp('/profile');

    expect(await screen.findByText(stubs.Profile)).toBeInTheDocument();
    expect(screen.queryByText(stubs.CustomerProfile)).not.toBeInTheDocument();
  });
});

describe('navigation bar', () => {
  it('offers a customer the catalog, cart and orders', async () => {
    signInAs('customer');
    openApp('/');
    await screen.findByText(stubs.Home);

    expect(screen.getByText('Katalog')).toBeInTheDocument();
    expect(screen.getByText('Savat')).toBeInTheDocument();
    expect(screen.getByText('Buyurtmalarim')).toBeInTheDocument();
  });

  it.each([
    ['owner', ['Analitika', 'Katalog', 'Buyurtmalar', 'Xodimlar', 'Profil']],
    ['manager', ['Buyurtmalar', 'Profil']],
    ['courier', ['Yetkazmalar', 'Profil']],
    ['super_admin', ['Asosiy', 'Restoranlar', "Qo'shish", 'Profil']],
  ] as const)('gives a %s only their own tabs', async (role, labels) => {
    signInAs(role);
    openApp('/profile');
    await screen.findByText(stubs.Profile);

    for (const label of labels) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    expect(screen.queryByText('Savat')).not.toBeInTheDocument(); // the cart tab is a customer thing
  });
});

describe('lazy pages', () => {
  it('loads the page of a route when it is opened', async () => {
    signInAs('customer');
    openApp('/orders');

    expect(await screen.findByText(stubs.Orders)).toBeInTheDocument();
  });

  it('keeps the navigation bar on screen while a page is loading', async () => {
    signInAs('customer');
    openApp('/stores');

    expect(screen.getByText('Savat')).toBeInTheDocument(); // there before the lazy page has arrived
    expect(await screen.findByText(stubs.Stores)).toBeInTheDocument();
  });
});

describe('outside Telegram', () => {
  it('shows the "open in Telegram" screen instead of the app', () => {
    Reflect.deleteProperty(window, 'Telegram');
    signInAs('owner');
    openApp('/admin/analytics');

    expect(screen.getByText('Telegram Mini App')).toBeInTheDocument();
    expect(screen.queryByText(stubs.AdminAnalytics)).not.toBeInTheDocument();
  });
});
