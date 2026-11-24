import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAddressStore } from '../store/useAddressStore';
import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeUser, makeVariant } from '../test/factories';
import type { StoreListItem, StoreSeller } from '../types';
import Cart from './Cart';

// Wave P (receipts): before paying, the checkout says who sells the food (consumer law art. 28-1: before the contract),
// from the store of GET /catalog?store_id=.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const LAVASH = makeProduct({ id: 1, variants: [makeVariant({ id: 1, uuid: 'lavash', product_id: 1, price: '25000.00', stock: 50 })] });
const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

async function renderCart(seller: StoreSeller | null | undefined) {
  const store: StoreListItem = { ...makeStore({ id: 1, payment_settings: { cash: { enabled: true } } }), branches: [] };
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: [store] } });
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 1, branches: [] } } });
    if (url.startsWith('/catalog')) {
      return Promise.resolve({ data: { success: true, data: { products: [LAVASH], store: { ...makeStore({ id: 1 }), ...(seller !== undefined ? { seller } : {}) } } } });
    }
    if (url === '/delivery/quote') return Promise.resolve({ data: { success: false } });
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter>
        <Cart />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flush();
  await flush();
}

beforeEach(() => {
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  useCartStore.setState({
    items: [{ id: '1-lavash', productId: 1, variantId: 'lavash', storeId: '1', name: 'Lavash (Standart)', price: 25000, quantity: 1 }],
    storeName: 'Evos',
    storeAddress: 'Asosiy filial',
  });
  useAddressStore.setState({ addresses: [], selectedAddressId: null });
});

describe('Cart: the seller before payment', () => {
  it('shows the seller block above the payment methods', async () => {
    await renderCart({ legal_name: '"Evos" MChJ', tin: '305123456', legal_address: 'Toshkent, Yunusobod 5', phone: null, complaint_contact: '+998901112233' });

    const seller = await screen.findByTestId('seller-info');
    expect(within(seller).getByText('"Evos" MChJ')).toBeInTheDocument();
    expect(within(seller).getByText('305123456')).toBeInTheDocument();
    expect(within(seller).getByText('Toshkent, Yunusobod 5')).toBeInTheDocument();
    expect(within(seller).getByText(/\+998901112233/)).toBeInTheDocument();
    // Before the payment choice in the page
    const payment = screen.getByRole('radiogroup', { name: "To'lov usuli" });
    expect(seller.compareDocumentPosition(payment) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('shows no seller block when the store has none', async () => {
    await renderCart(undefined);
    expect(screen.queryByTestId('seller-info')).not.toBeInTheDocument();
  });
});
