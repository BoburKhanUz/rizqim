import { StrictMode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { track } from '../lib/analytics';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeVariant } from '../test/factories';
import type { Category, ModifierGroup } from '../types';
import StoreDetail from './StoreDetail';

// Wave P analytics on the store page: store_view once per store (StrictMode, refetches), category_view per chip tap,
// product_view when a dish's sheet opens, add_to_cart per dish put in the cart.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../lib/analytics', () => ({ track: vi.fn(), setAnalyticsAuth: vi.fn(), trackAppOpen: vi.fn() }));

const get = vi.mocked(api.get);
const tracked = () => vi.mocked(track).mock.calls;

const sauce: ModifierGroup = {
  id: 1, uuid: 'g-1', name: 'Sous', min_select: 0, max_select: 1, is_required: false, sort_order: 0,
  modifiers: [{ id: 5, uuid: 'm-5', modifier_group_id: 1, name: 'Ketchup', price_delta: '0.00', max_quantity: 1, sort_order: 0, is_active: true }],
};
const category = (id: number, name: string): Category => ({ id, uuid: `c-${id}`, name, slug: name, parent_id: null, image: null, image_url: null });

const PRODUCTS = [
  makeProduct({ id: 31, store_id: 7, category_id: 3, name: 'Burger', variants: [makeVariant({ id: 1, uuid: 'v-burger', product_id: 31, price: '50000.00', stock: 20 })], modifier_groups: [sauce] }),
  makeProduct({ id: 32, store_id: 7, category_id: 4, name: 'Osh', variants: [makeVariant({ id: 2, uuid: 'v-osh', product_id: 32, price: '35000.00', stock: 20 })] }),
];

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

async function open() {
  get.mockImplementation((url) => {
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 7, branches: [] } } });
    if (url.startsWith('/catalog')) {
      const store = { ...makeStore({ id: 7, name: 'Evos' }), branches: [], ratings: [] };
      return Promise.resolve({ data: { success: true, data: { store, categories: [category(3, 'Burgerlar'), category(4, 'Milliy')], products: PRODUCTS } } });
    }
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  render(
    <StrictMode>
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter initialEntries={['/store/22222222-2222-4222-8222-222222222222']}>
          <Routes>
            <Route path="/store/:id" element={<StoreDetail />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </StrictMode>,
  );
  await flush();
  await flush();
}

const row = (name: string) => screen.getByRole('heading', { name, level: 3 }).closest('li') as HTMLElement;

beforeEach(() => {
  vi.mocked(track).mockClear();
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe('StoreDetail analytics', () => {
  it('sends store_view once when the store has loaded, StrictMode or not', async () => {
    await open();
    expect(screen.getByRole('heading', { name: 'Evos', level: 1 })).toBeInTheDocument();
    expect(tracked()).toEqual([['store_view', { store_id: 7 }]]);
  });

  it('sends category_view when a section is picked, not when it is unpicked', async () => {
    await open();
    vi.mocked(track).mockClear();
    fireEvent.click(screen.getByRole('button', { name: 'Milliy' }));
    fireEvent.click(screen.getByRole('button', { name: 'Milliy' }));
    expect(tracked()).toEqual([['category_view', { store_id: 7, category_id: 4 }]]);
  });

  it('sends add_to_cart for a plain dish, and product_view then add_to_cart for one with a sheet', async () => {
    await open();
    vi.mocked(track).mockClear();

    fireEvent.click(within(row('Osh')).getByRole('button', { name: "Qo'shish: Osh" }));
    expect(tracked()).toEqual([['add_to_cart', { store_id: 7, product_id: 32, props: { qty: 1 } }]]);

    vi.mocked(track).mockClear();
    fireEvent.click(within(row('Burger')).getByRole('button', { name: "Qo'shish: Burger" }));
    await flush();
    expect(tracked()).toEqual([['product_view', { store_id: 7, product_id: 31 }]]);
    fireEvent.click(screen.getByRole('button', { name: /^Tasdiqlash/ }));
    expect(tracked()).toEqual([
      ['product_view', { store_id: 7, product_id: 31 }],
      ['add_to_cart', { store_id: 7, product_id: 31, props: { qty: 1 } }],
    ]);
  });

  it('does not send add_to_cart when the dish did not go in (another store in the cart)', async () => {
    useCartStore.setState({ items: [{ id: 'x', productId: 1, variantId: 'x', storeId: '99', name: 'Pizza', price: 1000, quantity: 1 }], storeName: 'Other', storeAddress: '' });
    await open();
    vi.mocked(track).mockClear();
    fireEvent.click(within(row('Osh')).getByRole('button', { name: "Qo'shish: Osh" }));
    expect(tracked()).toEqual([]);

    // "Ha, tozalash": now it goes in
    fireEvent.click(screen.getByRole('button', { name: 'Ha, tozalash' }));
    expect(tracked()).toEqual([['add_to_cart', { store_id: 7, product_id: 32, props: { qty: 1 } }]]);
  });
});
