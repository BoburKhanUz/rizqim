import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { serveOrdersApi } from '../test/ordersApi';
import type { Order } from '../types';
import Orders from './Orders';

// Owner decision 4: a refund of money paid into the restaurant's own account is the restaurant's to make. Until the
// payment provider shows the money back the customer is never told "refunded": the badge says the refund is under way
// and a line says who makes it.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const code = (id: number) => String(id).padStart(8, '0');
const orderAt = (id: number, overrides: Partial<Order> = {}) =>
  makeOrder({ id, uuid: `${code(id)}-0000-4000-8000-000000000000`, short_code: code(id), status: 'refunded', payment_status: 'paid', ...overrides });

let client: QueryClient;

async function showOrders(orders: Order[]) {
  get.mockImplementation(serveOrdersApi(orders));
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Orders />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await screen.findByText(`#${code(orders[0].id)}`);
}

beforeEach(() => {
  client = new QueryClient();
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
});

afterEach(() => {
  client.clear();
  useLanguageStore.setState({ language: 'uz' });
});

describe('Orders: a refund the restaurant makes from its own account', () => {
  it('says the restaurant refunds it and never "Qaytarildi" while the money is not back', async () => {
    await showOrders([orderAt(1, { refund: { refund_by: 'store_own_account', returned: false } })]);

    expect(screen.getByTestId('order-refund-by-store')).toHaveTextContent("Pulni ovqatxona o'z hisobidan qaytaradi");
    expect(screen.getByText('Qaytarilmoqda')).toBeInTheDocument();
    expect(screen.queryByText('Qaytarildi')).not.toBeInTheDocument();
  });

  it('shows the plain status once the money is seen back, and for any other refund', async () => {
    await showOrders([
      orderAt(2, { refund: { refund_by: 'store_own_account', returned: true } }),
      orderAt(3, { refund: null }),
    ]);

    expect(screen.queryByTestId('order-refund-by-store')).not.toBeInTheDocument();
    expect(screen.getAllByText('Qaytarildi')).toHaveLength(2);
  });

  it('says it in Russian and English too', async () => {
    useLanguageStore.setState({ language: 'ru' });
    await showOrders([orderAt(4, { status: 'canceled', refund: { refund_by: 'store_own_account', returned: false } })]);

    expect(screen.getByTestId('order-refund-by-store')).toHaveTextContent('Деньги вернёт ресторан со своего счёта');
    useLanguageStore.setState({ language: 'en' });
    expect(await screen.findByText('The restaurant refunds the money from its own account')).toBeInTheDocument();
  });
});
