import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { serveOrdersApi } from '../test/ordersApi';
import { openExternalLink } from '../lib/openMap';
import type { Order, OrderReceipt } from '../types';
import Orders from './Orders';

// Wave P (receipts): the order screen shows the seller of the order and its fiscal receipts: a button that opens an
// ofd.soliq.uz link through Telegram for a ready one, the state as text for a pending or unavailable one.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../lib/openMap', async (importOriginal) => ({ ...(await importOriginal<typeof import('../lib/openMap')>()), openExternalLink: vi.fn() }));

const get = vi.mocked(api.get);
const SALE_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=121';
const REFUND_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=123';

const receipt = (overrides: Partial<OrderReceipt>): OrderReceipt => ({ uuid: 'r', type: 'sale', status: 'ready', provider: 'payme', url: SALE_URL, issued_at: '2026-11-24T12:00:00+05:00', ...overrides });
const code = (id: number) => String(id).padStart(8, '0');
const orderAt = (id: number, overrides: Partial<Order> = {}) =>
  makeOrder({ id, uuid: `${code(id)}-0000-4000-8000-000000000000`, short_code: code(id), status: 'delivered', payment_status: 'paid', ...overrides });

let client: QueryClient;

async function openOrder(orders: Order[], id: number) {
  get.mockImplementation(serveOrdersApi(orders));
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Orders />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  fireEvent.click(await screen.findByText(`#${code(id)}`));
}

beforeEach(() => {
  client = new QueryClient();
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
  vi.mocked(openExternalLink).mockClear();
});

afterEach(() => {
  client.clear();
  useLanguageStore.setState({ language: 'uz' });
});

describe('Orders: fiscal receipts', () => {
  it('opens the sale and the refund receipt through Telegram', async () => {
    await openOrder([orderAt(1, { status: 'refunded', receipts: [receipt({ uuid: 's' }), receipt({ uuid: 'f', type: 'refund', url: REFUND_URL })] })], 1);

    fireEvent.click(screen.getByRole('button', { name: /Fiskal chek/ }));
    expect(openExternalLink).toHaveBeenLastCalledWith(SALE_URL);
    fireEvent.click(screen.getByRole('button', { name: /Qaytarish cheki/ }));
    expect(openExternalLink).toHaveBeenLastCalledWith(REFUND_URL);
  });

  it('says where a pending or unavailable receipt stands, without a button', async () => {
    await openOrder(
      [orderAt(2, { receipts: [receipt({ uuid: null, status: 'pending', url: null }), receipt({ type: 'refund', status: 'unavailable', url: null })] })],
      2,
    );

    const box = screen.getByTestId('order-receipts');
    expect(within(box).getByText('Chek tayyorlanmoqda')).toBeInTheDocument();
    expect(within(box).getByText("Qaytarish chekini restorandan so'rang")).toBeInTheDocument();
    expect(within(box).queryByRole('button')).not.toBeInTheDocument();
  });

  it('never opens a link that is not an https ofd.soliq.uz one', async () => {
    await openOrder([orderAt(3, { receipts: [receipt({ url: 'https://evil.example/receipt' })] })], 3);

    const box = screen.getByTestId('order-receipts');
    expect(within(box).queryByRole('button')).not.toBeInTheDocument();
    expect(within(box).getByText("Chekni restorandan so'rang")).toBeInTheDocument();
    expect(openExternalLink).not.toHaveBeenCalled();
  });

  it('shows nothing about receipts on an order without any', async () => {
    await openOrder([orderAt(4, { receipts: [] }), orderAt(5)], 4);
    expect(screen.queryByTestId('order-receipts')).not.toBeInTheDocument();
  });

  it('names the buttons in Russian', async () => {
    useLanguageStore.setState({ language: 'ru' });
    await openOrder([orderAt(6, { receipts: [receipt({}), receipt({ type: 'refund', url: REFUND_URL })] })], 6);

    expect(screen.getByRole('button', { name: /Фискальный чек/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Чек возврата/ })).toBeInTheDocument();
  });
});

describe('Orders: the seller of the order', () => {
  it('shows the filled seller fields and points complaints to the complaint section', async () => {
    await openOrder(
      [orderAt(7, { seller: { legal_name: '"Lazzat Burger" MChJ', tin: '305123456', legal_address: 'Toshkent, Chilonzor 1', phone: null, complaint_contact: '@lazzat_support' } })],
      7,
    );

    const seller = screen.getByTestId('seller-info');
    expect(within(seller).getByText('"Lazzat Burger" MChJ')).toBeInTheDocument();
    expect(within(seller).getByText('305123456')).toBeInTheDocument();
    expect(within(seller).getByText('Toshkent, Chilonzor 1')).toBeInTheDocument();
    expect(within(seller).queryByText('Telefon')).not.toBeInTheDocument();
    expect(within(seller).getByText(/@lazzat_support/)).toBeInTheDocument();
    expect(within(seller).getByRole('link', { name: /Shikoyatlar/ })).toHaveAttribute('href', '/complaints');
  });

  it('shows no seller block when the order has no seller details', async () => {
    await openOrder([orderAt(8, { seller: null })], 8);
    expect(screen.queryByTestId('seller-info')).not.toBeInTheDocument();
  });
});
