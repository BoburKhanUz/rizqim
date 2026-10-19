import { act, fireEvent, render, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeVariant } from '../test/factories';
import type { Product } from '../types';
import CategoryDetail from './CategoryDetail';
import Home from './Home';

// Adding a single-variant product straight from the feed or a category page follows the same stock rule as the store
// page and the cart: nothing sold out, nothing beyond what is in stock.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

function productWithStock(stock: number | undefined): Product {
  const variant = makeVariant({ uuid: 'v-1', price: '25000.00', ...(stock === undefined ? {} : { stock }) });
  if (stock === undefined) Reflect.deleteProperty(variant, 'stock');
  return { ...makeProduct({ id: 1, store_id: 1, variants: [variant] }), store: makeStore({ id: 1 }) } as Product;
}

function serve(product: Product) {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: [] } });
    return Promise.resolve({ data: { success: true, data: { categories: [], products: [product], promos: [] } } });
  });
}

async function open(page: ReactElement, path: string, product: Product) {
  serve(product);
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/" element={page} />
        <Route path="/category/:id" element={page} />
      </Routes>
    </MemoryRouter>,
  );
  await flush();
}

const addButton = () => screen.getByRole('button', { name: "Savatga qo'shish" });
const cartQuantity = () => useCartStore.getState().items.reduce((sum, item) => sum + item.quantity, 0);

beforeEach(() => {
  vi.spyOn(window, 'alert').mockImplementation(() => {});
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe.each([
  ['Home', <Home />, '/'],
  ['CategoryDetail', <CategoryDetail />, '/category/1'],
] as const)('%s: stock check on a single-variant product', (_name, page, path) => {
  it('refuses a sold-out product and says so', async () => {
    await open(page, path, productWithStock(0));

    fireEvent.click(addButton());

    expect(window.alert).toHaveBeenCalledWith('Kechirasiz, bu mahsulot tugagan.');
    expect(cartQuantity()).toBe(0);
  });

  it('adds up to the stock, then refuses one more', async () => {
    await open(page, path, productWithStock(2));

    fireEvent.click(addButton());
    fireEvent.click(addButton());
    expect(cartQuantity()).toBe(2);
    expect(window.alert).not.toHaveBeenCalled();

    fireEvent.click(addButton());
    expect(window.alert).toHaveBeenCalledWith('Kechirasiz, omborda faqat 2 dona qolgan.');
    expect(cartQuantity()).toBe(2);
  });

  it('does not block a product whose stock the API did not send', async () => {
    await open(page, path, productWithStock(undefined));

    fireEvent.click(addButton());
    fireEvent.click(addButton());

    expect(cartQuantity()).toBe(2);
    expect(window.alert).not.toHaveBeenCalled();
  });
});
