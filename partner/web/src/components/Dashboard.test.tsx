import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Dashboard } from './Dashboard';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

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
});
