import { screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StaffApiProvider } from '@rizqim/staff';
import { AuthProvider } from './AuthProvider';
import { Dashboard } from './Dashboard';
import { I18nProvider } from './I18nProvider';
import { ApiConfigContext } from '@/lib/apiConfig';
import { getStaffApi } from '@/lib/session/client';
import { setSession } from '@/lib/session/client';
import { API, json, mockFetch, renderPage } from '@/test/helpers';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), usePathname: () => '/dashboard' }));

afterEach(() => setSession(null));

const signedIn = () => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: { id: 7, name: 'Aziz', role: 'owner' } });

const analytics = {
  period: { key: 'today', from: '2026-10-20', to: '2026-10-20', days: 1, timezone: 'Asia/Tashkent', basis: 'created_at' },
  summary: { orders_count: 42, delivered_count: 30, canceled_count: 3, refunded_count: 0, active_count: 9, revenue: 1_250_000, average_check: 41_667 },
  by_status: { new: 4, preparing: 5, delivered: 30, canceled: 3 },
  by_payment_method: [{ method: 'cash', orders: 25, revenue: 700_000 }, { method: 'click', orders: 17, revenue: 550_000 }],
  by_order_type: [{ type: 'delivery', orders: 30, revenue: 900_000 }, { type: 'pickup', orders: 12, revenue: 350_000 }],
  top_products: [{ name: 'Osh', quantity: 18, revenue: 540_000 }, { name: 'Lag\'mon', quantity: 11, revenue: 330_000 }],
  couriers: [],
  times: { average_prep_seconds: 600, average_delivery_seconds: null },
  daily: [],
  generated_at: '2026-10-20T10:30:00+05:00',
};

describe('Dashboard', () => {
  it('shows today\'s figures from GET /v1/owner/analytics, as the backend computed them', async () => {
    signedIn();
    const { calls } = mockFetch({ 'GET /api/v1/owner/analytics': () => json({ success: true, message: 'ok', data: analytics }) });
    renderPage(<Dashboard />);

    // Amounts use non-breaking spaces; Testing Library matches them as plain ones
    expect(await screen.findByText("1 250 000 so'm")).toBeInTheDocument(); // revenue
    expect(screen.getByText("41 667 so'm")).toBeInTheDocument(); // average check
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('Osh')).toBeInTheDocument();
    expect(screen.getByText('Naqd pul')).toBeInTheDocument();
    expect(screen.getByText('10 daq')).toBeInTheDocument();
    expect(screen.getByText('Tayyorlanmoqda')).toBeInTheDocument(); // a status badge in the staff's words

    const url = new URL(calls[0]!.url);
    expect(url.pathname).toBe('/api/v1/owner/analytics');
    expect(url.searchParams.get('period')).toBe('today');
    expect(calls[0]?.headers.get('Authorization')).toBe('Bearer token');
  });

  it('says so when there are no orders today', async () => {
    signedIn();
    const empty = { ...analytics, summary: { ...analytics.summary, orders_count: 0, active_count: 0, delivered_count: 0, canceled_count: 0, revenue: 0, average_check: 0 }, by_status: {} };
    mockFetch({ 'GET /api/v1/owner/analytics': () => json({ success: true, message: 'ok', data: empty }) });
    renderPage(<Dashboard />);
    expect(await screen.findByText("Bugun hali buyurtma yo'q")).toBeInTheDocument();
  });

  it('shows an inline error with a retry when the backend fails', async () => {
    signedIn();
    mockFetch({ 'GET /api/v1/owner/analytics': () => json({ message: 'boom' }, 500) });
    renderPage(<Dashboard />);
    expect(await screen.findByRole('alert')).toHaveTextContent("Ma'lumot yuklanmadi");
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  describe('for people who see orders', () => {
    const signIn = (permissions: string[]) => ({
      'POST /api/session/refresh': () => json({ access_token: 'a', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id: 1, name: 'A', role: 'cashier' } }),
      'GET /api/v1/me/permissions': () => json({ success: true, message: 'ok', data: { role: 'cashier', store_id: 1, branch_id: 2, branch_scoped: true, permissions, device_uuid: null } }),
    });
    const order = (status: string, minutesAgo = 1) => ({ uuid: `${status}-${minutesAgo}`, status, created_at: new Date(Date.now() - minutesAgo * 60_000).toISOString(), updated_at: null, accepted_at: null, waiting_seconds: minutesAgo * 60 });

    function renderSignedIn() {
      render(
        <I18nProvider language="uz">
          <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
            <StaffApiProvider api={getStaffApi(API)}>
              <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
                <AuthProvider apiBaseUrl={API}>
                  <Dashboard />
                </AuthProvider>
              </QueryClientProvider>
            </StaffApiProvider>
          </ApiConfigContext.Provider>
        </I18nProvider>,
      );
    }

    it('shows the board summary and leaves the analytics out without reports.view', async () => {
      const { calls } = mockFetch({
        ...signIn(['orders.view', 'orders.manage']),
        'GET /api/v1/staff/orders': () =>
          json({ success: true, message: 'ok', data: [order('new', 12), order('new'), order('preparing'), order('ready')], meta: { next_cursor: null, per_page: 100 } }),
      });
      renderSignedIn();
      const card = async (name: string) => (await screen.findByText(name, { selector: 'p' })).closest('[data-column]') as HTMLElement;
      await waitFor(async () => expect(within(await card('Yangi')).getByText('2')).toBeInTheDocument());
      expect(within(await card('Tayyor')).getByText('1')).toBeInTheDocument();
      expect(within(await card("Yo'lda")).getByText('0')).toBeInTheDocument();
      expect(screen.getByText('1 ta buyurtma 10 daqiqadan ortiq qabul qilinmagan')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Doskani ochish' })).toHaveAttribute('href', '/orders');
      expect(calls.some((call) => call.url.includes('/owner/analytics'))).toBe(false);
    });
  });
});
