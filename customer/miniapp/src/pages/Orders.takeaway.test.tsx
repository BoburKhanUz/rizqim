import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeOrder, makeOrderItem, makeStore, makeUser } from '../test/factories';
import { serveOrdersApi } from '../test/ordersApi';
import type { Order } from '../types';
import Orders from './Orders';

// P6-34: the order card shows a pickup for later and the packaging lines the branch added (a removed bag is not shown)

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const codeOf = (id: number) => String(id).padStart(8, '0');
const orderAt = (id: number, overrides: Partial<Order> = {}) =>
  makeOrder({ id, uuid: `${codeOf(id)}-0000-4000-8000-000000000000`, short_code: codeOf(id), payment_status: 'pending', ...overrides });

let client: QueryClient;

beforeEach(() => {
  client = new QueryClient();
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
});

afterEach(() => {
  client.clear();
  useLanguageStore.setState({ language: 'uz' });
});

const renderOrders = () =>
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Orders />
      </MemoryRouter>
    </QueryClientProvider>,
  );

describe('Orders: a pickup for later and its packaging (P6-34)', () => {
  it('shows the pickup time on the card and the packaging lines in the details', async () => {
    get.mockImplementation(
      serveOrdersApi([
        orderAt(1, {
          status: 'accepted',
          order_type: 'pickup',
          type: 'pickup',
          pickup_at: '2026-10-22T14:00:00.000000Z',
          packaging_amount: '7000.00',
          packaging_lines: [
            { rule_id: 1, name: 'Paket', quantity: 1, amount: '0.00', removed: true },
            { rule_id: 2, name: "Sho'rva idishi", quantity: 2, amount: '6000.00' },
          ],
          items: [makeOrderItem({ product_name: "Sho'rva", quantity: 2, unit_price: '25000.00', total_price: '50000.00' })],
        }),
        orderAt(2, { status: 'accepted', order_type: 'pickup', type: 'pickup' }),
      ]),
    );

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);
    const card = screen.getByText(`#${codeOf(1)}`).closest<HTMLElement>('.cursor-pointer')!;
    expect(within(card).getByTestId('order-pickup-at')).toHaveTextContent('Olib ketish vaqti:');
    const other = screen.getByText(`#${codeOf(2)}`).closest<HTMLElement>('.cursor-pointer')!;
    expect(within(other).queryByTestId('order-pickup-at')).not.toBeInTheDocument();

    fireEvent.click(screen.getByText(`#${codeOf(1)}`));
    const lines = await screen.findAllByTestId('order-packaging-line');
    expect(lines).toHaveLength(1);
    expect(lines[0]).toHaveTextContent("Sho'rva idishi");
    expect(lines[0]).toHaveTextContent('× 2');
    expect(lines[0]).toHaveTextContent("6 000 so'm");
  });
});
