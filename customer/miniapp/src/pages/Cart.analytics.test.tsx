import { StrictMode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AxiosError, type AxiosResponse } from 'axios';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { track } from '../lib/analytics';
import { tg } from '../lib/telegram';
import { useAddressStore } from '../store/useAddressStore';
import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeUser, makeVariant } from '../test/factories';
import type { BranchSummary, StoreListItem } from '../types';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import Cart from './Cart';

// Wave P analytics in the cart: cart_view once, checkout_start per "Buyurtma berish", checkout_error with a code (never
// the server's text), order_placed with the order's uuid after the 2xx, payment_redirect before the payment page opens.
// None of it changes what POST /orders sends or when.

const log: string[] = [];
vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {}, openLink: vi.fn(() => log.push('openLink')) } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../lib/analytics', () => ({
  track: vi.fn((name: string) => log.push(name)),
  setAnalyticsAuth: vi.fn(),
  trackAppOpen: vi.fn(),
}));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);
const tracked = () => vi.mocked(track).mock.calls;
const ORDER_UUID = '9b2f7c1e-3d4a-4b5c-8d6e-7f8091a2b3c4';

const BRANCH: BranchSummary = { id: 5, store_id: 7, name: 'Asosiy filial', latitude: 41.31, longitude: 69.28, radius_km: 10, address: 'Amir Temur 1', phone: null };
const STORE: StoreListItem = { ...makeStore({ id: 7, payment_settings: null, delivery_fee: 10000, min_order: 0 }), branches: [BRANCH] };
const LAVASH = makeProduct({ id: 31, store_id: 7, variants: [makeVariant({ id: 1, uuid: 'lavash', product_id: 31, price: '25000.00', stock: 50 })] });
const QUOTE = {
  zone: { id: 1, name: 'Markaz', delivery_price: '10000.00', min_order_amount: '0.00', free_delivery_from: null, eta_minutes: 40 },
  branch: { id: 5, name: 'Asosiy filial', distance_km: 1.2 },
  delivery_price: '10000.00',
  min_order_amount: '0.00',
  free_delivery_applies: false,
  meets_min_order: true,
  eta: { minutes: 40 },
};

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
const orderButton = () => screen.getByRole('button', { name: /^Buyurtma berish/ });

async function renderCart() {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: [STORE] } });
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 7, branches: [] } } });
    if (url.startsWith('/catalog')) return Promise.resolve({ data: { success: true, data: { products: [LAVASH] } } });
    if (url === '/delivery/quote') return Promise.resolve({ data: { success: true, data: QUOTE } });
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  render(
    <StrictMode>
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <Cart />
          <MainButtonHost />
        </MemoryRouter>
      </QueryClientProvider>
    </StrictMode>,
  );
  await flush();
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 600)); });
}

function refusal(status: number, body: unknown): AxiosError {
  const response = { status, data: body, statusText: '', headers: {}, config: {} } as AxiosResponse;
  return new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, response);
}

beforeEach(() => {
  log.length = 0;
  vi.mocked(track).mockClear();
  vi.mocked(tg.openLink).mockClear();
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
  vi.spyOn(console, 'error').mockImplementation(() => {});
  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  useCartStore.setState({
    items: [{ id: '31-lavash', productId: 31, variantId: 'lavash', storeId: '7', name: 'Lavash (Standart)', price: 25000, quantity: 2 }],
    storeName: 'Evos',
    storeAddress: 'Asosiy filial',
  });
  useAddressStore.setState({ addresses: [{ id: 1, title: 'Uy', address: 'Chilonzor 12', lat: 41.27, lng: 69.21, is_default: true }], selectedAddressId: 1 });
});

describe('Cart analytics', () => {
  it('sends cart_view once when the cart opens, with the store, the pieces and the amount', async () => {
    post.mockResolvedValue({ data: { success: true, data: {} } });
    await renderCart();
    expect(tracked().filter(([name]) => name === 'cart_view')).toEqual([['cart_view', { store_id: 7, props: { items: 2, value: 50000 } }]]);
  });

  it('sends checkout_start on the press and order_placed with the uuid of the order the server made', async () => {
    post.mockResolvedValue({ data: { success: true, data: { order_number: 'A100', order_uuid: ORDER_UUID } } });
    await renderCart();
    vi.mocked(track).mockClear();

    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(1);
    expect(tracked()).toEqual([
      ['checkout_start', { store_id: 7, props: { items: 2, value: 50000, payment_method: 'cash', order_type: 'delivery' } }],
      ['order_placed', { props: { order_uuid: ORDER_UUID } }],
    ]);
  });

  it('sends payment_redirect before the payment page opens', async () => {
    post.mockResolvedValue({ data: { success: true, data: { order_uuid: ORDER_UUID, payment_url: 'https://pay.example/1' } } });
    await renderCart();
    log.length = 0;
    vi.mocked(track).mockClear();
    fireEvent.click(orderButton());
    await flush();
    expect(log).toEqual(['checkout_start', 'order_placed', 'payment_redirect', 'openLink']);
    expect(tracked()[2]).toEqual(['payment_redirect', { store_id: 7 }]);
  });

  it('sends checkout_error with the kind of the refusal and no order_placed', async () => {
    post.mockRejectedValueOnce(refusal(422, { message: 'The given data was invalid.', errors: { items: ["Uzr, 'Lavash' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang."] } }));
    await renderCart();
    vi.mocked(track).mockClear();
    fireEvent.click(orderButton());
    await flush();
    expect(tracked().map(([name]) => name)).toEqual(['checkout_start', 'checkout_error']);
    expect(tracked()[1]).toEqual(['checkout_error', { store_id: 7, props: { code: 'item_unavailable' } }]);
    expect(JSON.stringify(tracked())).not.toContain('Lavash');
  });

  it('sends checkout_error network when the connection dropped', async () => {
    post.mockRejectedValueOnce(new AxiosError('Network Error'));
    await renderCart();
    vi.mocked(track).mockClear();
    fireEvent.click(orderButton());
    await flush();
    expect(tracked()[1]).toEqual(['checkout_error', { store_id: 7, props: { code: 'network' } }]);
  });
});
