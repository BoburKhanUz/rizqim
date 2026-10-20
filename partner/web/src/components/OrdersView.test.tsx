import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { OrdersView } from './OrdersView';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

afterEach(() => setSession(null));

const order = (over: Record<string, unknown>) => ({
  uuid: 'aaaaaaaa-0000-0000-0000-000000000001',
  short_code: 'E101',
  status: 'new',
  order_type: 'delivery',
  payment_method: 'cash',
  total_price: '85000.00',
  created_at: '2026-10-20T10:12:00+05:00',
  user: { name: 'Dilnoza' },
  ...over,
});

describe('OrdersView', () => {
  it('lists the active orders, read-only', async () => {
    setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null });
    const { calls } = mockFetch({
      'GET /api/v1/orders': () =>
        json({
          success: true,
          message: 'ok',
          data: [order({}), order({ uuid: 'aaaaaaaa-0000-0000-0000-000000000002', short_code: 'E102', status: 'on_the_way', payment_method: 'click', user: null })],
          meta: { truncated: false },
        }),
    });
    renderPage(<OrdersView />);

    const table = await screen.findByRole('table');
    expect(new URL(calls[0]!.url).searchParams.get('scope')).toBe('active');
    expect(within(table).getByText('#E101')).toBeInTheDocument();
    expect(within(table).getByText('Dilnoza')).toBeInTheDocument();
    expect(within(table).getByText('Yangi')).toBeInTheDocument();
    expect(within(table).getByText("Yo'lda")).toBeInTheDocument();
    expect(within(table).getByText('Click')).toBeInTheDocument();
    expect(within(table).getAllByText(/85\s000\sso'm/)).toHaveLength(2);
    expect(screen.queryByRole('button', { name: /qabul|rad|bekor/i })).not.toBeInTheDocument();
  });

  it('says so when no order is active, and when the list is cut at 200', async () => {
    setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null });
    mockFetch({ 'GET /api/v1/orders': () => json({ success: true, message: 'ok', data: [], meta: { truncated: false } }) });
    renderPage(<OrdersView />);
    expect(await screen.findByText("Faol buyurtma yo'q")).toBeInTheDocument();
  });

  it('warns when the backend truncated the list', async () => {
    setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null });
    mockFetch({ 'GET /api/v1/orders': () => json({ success: true, message: 'ok', data: [order({})], meta: { truncated: true } }) });
    renderPage(<OrdersView />);
    expect(await screen.findByText(/200 ta buyurtma bilan cheklangan/)).toBeInTheDocument();
  });
});
