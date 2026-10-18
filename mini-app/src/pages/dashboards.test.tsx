import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { act, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { makeOrder, makeOrderItem, makeStore, makeUser } from '../test/factories';
import { failedRequest } from '../test/httpErrors';
import AdminReports from './AdminReports';
import CourierDashboard from './CourierDashboard';
import ManagerDashboard from './ManagerDashboard';

// The staff dashboards on a fake API and a controlled clock: they poll, and money arrives as decimal strings

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

let client: QueryClient;

function renderWithClient(ui: ReactNode) {
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));

function serveOrders(orders: ReturnType<typeof makeOrder>[]) {
  get.mockImplementation((url) =>
    Promise.resolve({ data: { success: true, data: url === '/orders' ? orders : [] } }),
  );
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(new Date(2026, 9, 14, 15, 0, 0));
  client = new QueryClient();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
});

describe('ManagerDashboard', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', name: 'Menejer Bobur' }), makeStore(), false);
  });

  it('lists the open orders with their items and total', async () => {
    serveOrders([
      makeOrder({ id: 1, short_code: 'A100', status: 'new', total_price: '45000.00', items: [makeOrderItem({ product_name: 'Lavash', quantity: 2 })] }),
      makeOrder({ id: 2, short_code: 'A200', status: 'delivered', total_price: '30000.00' }),
    ]);

    renderWithClient(<ManagerDashboard />);
    await settle();

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.getByText('Lavash x2')).toBeInTheDocument();
    expect(screen.getByText(/^45\D?000 UZS$/)).toBeInTheDocument(); // the decimal string, read as a number
    expect(screen.queryByText('#A200')).not.toBeInTheDocument(); // finished orders are on the other tab
  });

  it('keeps showing the orders when a background refresh fails', async () => {
    serveOrders([makeOrder({ id: 1, short_code: 'A100', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();
    expect(screen.getByText('#A100')).toBeInTheDocument();

    get.mockRejectedValue(failedRequest(502, {}, '/orders'));
    await settle(8000 + 2000); // the next poll fails, and so does its retry

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.queryByText('Authentication Failed')).not.toBeInTheDocument();
  });

  it('shows the error screen when the very first load fails: there is nothing else to show', async () => {
    get.mockRejectedValue(failedRequest(502, {}, '/orders'));

    renderWithClient(<ManagerDashboard />);
    await settle(5000);

    expect(screen.getByText('Failed to fetch store orders.')).toBeInTheDocument();
  });

  it('recovers by itself once the server answers again', async () => {
    get.mockRejectedValue(failedRequest(502, {}, '/orders'));
    renderWithClient(<ManagerDashboard />);
    await settle(5000);
    expect(screen.getByText('Failed to fetch store orders.')).toBeInTheDocument();

    serveOrders([makeOrder({ id: 1, short_code: 'A100', status: 'new' })]);
    await settle(60_000); // after the longer wait of the backed-off poll

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.queryByText('Failed to fetch store orders.')).not.toBeInTheDocument();
  });

  it('does not poll while the page is hidden', async () => {
    serveOrders([makeOrder({ id: 1, status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();
    const callsAfterLoad = get.mock.calls.filter(([url]) => url === '/orders').length;

    focusManager.setFocused(false);
    await settle(5 * 60_000);

    expect(get.mock.calls.filter(([url]) => url === '/orders')).toHaveLength(callsAfterLoad);
  });
});

describe('CourierDashboard', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'courier', name: 'Kuryer Ali' }), makeStore(), false);
  });

  it('adds up the delivery fees of delivered orders as numbers, not as text', async () => {
    serveOrders([
      makeOrder({ id: 1, status: 'delivered', delivery_price: '5000.00' }),
      makeOrder({ id: 2, status: 'delivered', delivery_price: '7000.00' }),
      makeOrder({ id: 3, status: 'delivered', delivery_price: '8000.50' }),
      makeOrder({ id: 4, status: 'on_the_way', delivery_price: '9999.00' }), // not delivered yet
    ]);

    renderWithClient(<CourierDashboard />);
    await settle();

    expect(screen.getByText(/^20\D?000\.5 UZS$/)).toBeInTheDocument();
    expect(screen.getByText(/^3 ta$/)).toBeInTheDocument();
  });

  it('is not thrown off by a delivery fee that is not a number', async () => {
    serveOrders([
      makeOrder({ id: 1, status: 'delivered', delivery_price: '5000.00' }),
      makeOrder({ id: 2, status: 'delivered', delivery_price: 'n/a' }),
    ]);

    renderWithClient(<CourierDashboard />);
    await settle();

    expect(screen.getByText(/^5\D?000 UZS$/)).toBeInTheDocument();
    expect(screen.queryByText(/NaN/)).not.toBeInTheDocument();
  });

  it('keeps the deliveries on screen when a background refresh fails', async () => {
    serveOrders([makeOrder({ id: 1, short_code: 'K100', status: 'courier_assigned' })]);
    renderWithClient(<CourierDashboard />);
    await settle();
    expect(screen.getByText('#K100')).toBeInTheDocument();

    get.mockRejectedValue(failedRequest(503, {}, '/orders'));
    await settle(15_000 + 2000);

    expect(screen.getByText('#K100')).toBeInTheDocument();
    expect(screen.queryByText('Failed to fetch courier deliveries.')).not.toBeInTheDocument();
  });
});

describe('AdminReports', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'owner' }), makeStore(), false);
  });

  it('reports the revenue of today\'s delivered orders, the sum of decimal strings', async () => {
    const today = new Date(2026, 9, 14, 12, 0, 0).toISOString();
    serveOrders([
      makeOrder({ id: 1, status: 'delivered', total_price: '30000.00', updated_at: today }),
      makeOrder({ id: 2, status: 'delivered', total_price: '12500.50', updated_at: today }),
      makeOrder({ id: 3, status: 'canceled', total_price: '99999.00', updated_at: today }),
      makeOrder({ id: 4, status: 'delivered', total_price: '77777.00', updated_at: new Date(2026, 9, 1).toISOString() }),
    ]);

    renderWithClient(<AdminReports />);
    await settle();

    expect(screen.getByText(/^42\D?500\.5 UZS$/)).toBeInTheDocument();
  });
});
