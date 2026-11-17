import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { showAlert } from '../lib/dialogs';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeVariant } from '../test/factories';
import type { Product, ProductCardItem } from '../types';
import CategoryDetail from './CategoryDetail';
import Home from './Home';

// Adding a single-variant product straight from the feed or a category page follows the same stock rule as the store
// page and the cart: nothing sold out, nothing beyond what is in stock. Wave M2: the feed answers light cards
// (view=cards); a card without options carries its one variant.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined) }));

const get = vi.mocked(api.get);

const flush = async () => {
  // the feed and the store list are two queries: let both settle
  for (let i = 0; i < 3; i += 1) await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
};

// The card GET /catalog?view=cards sends for a product (CatalogFeed::card)
function cardOf(product: Product, store = makeStore({ id: product.store_id }), accepting = true): ProductCardItem {
  const variants = product.variants ?? [];
  const needsOptions = variants.length > 1 || (product.modifier_groups ?? []).length > 0 || variants.some((v) => v.unit === 'kg' || v.unit === 'l');
  const variant = !needsOptions && variants.length === 1 ? variants[0] : null;
  return {
    id: product.id,
    uuid: product.uuid,
    store_id: product.store_id,
    category_id: product.category_id,
    name: product.name,
    description: product.description,
    image_url: product.image_url,
    batch_tracked: false,
    price: variants[0]?.price ?? null,
    unit: variants[0] ? (variants[0].unit ?? 'piece') : null,
    needs_options: needsOptions,
    variant: variant
      ? { id: variant.id, uuid: variant.uuid, name: variant.name, price: variant.price, unit: variant.unit ?? 'piece', stock: (variant as { stock?: number }).stock ?? null }
      : null,
    store: { id: store.id, uuid: store.uuid, name: store.name, address: store.address, logo_url: store.logo_url, accepting_orders: accepting },
  };
}

function productWithStock(stock: number | undefined): Product {
  const variant = makeVariant({ uuid: 'v-1', price: '25000.00', ...(stock === undefined ? {} : { stock }) });
  if (stock === undefined) Reflect.deleteProperty(variant, 'stock');
  return makeProduct({ id: 1, store_id: 1, variants: [variant] });
}

function serve(products: Product[], { stores = [] as unknown[], menu = products, accepting = true } = {}) {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: stores, meta: { page: 1, per_page: 10, total: stores.length, last_page: 1 } } });
    if (url.startsWith('/catalog?store_id=')) {
      return Promise.resolve({ data: { success: true, data: { store: { ...makeStore({ id: 1 }), branches: [], ratings: [] }, categories: [], products: menu, availability: [] } } });
    }
    return Promise.resolve({ data: { success: true, data: { categories: [], products: products.map((product) => cardOf(product, undefined, accepting)), promos: [], highlight_product: null } } });
  });
}

async function open(page: ReactElement, path: string, product: Product | Product[], options?: Parameters<typeof serve>[1]) {
  serve(Array.isArray(product) ? product : [product], options);
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/" element={page} />
          <Route path="/category/:id" element={page} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flush();
}

const addButton = () => screen.getByRole('button', { name: "Savatga qo'shish" });
const cartQuantity = () => useCartStore.getState().items.reduce((sum, item) => sum + item.quantity, 0);

beforeEach(() => {
  vi.mocked(showAlert).mockClear();
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe.each([
  ['Home', <Home />, '/'],
  ['CategoryDetail', <CategoryDetail />, '/category/1'],
] as const)('%s: stock check on a single-variant product', (_name, page, path) => {
  it('marks a sold-out product "Tugadi" and offers no way to add it', async () => {
    await open(page, path, productWithStock(0));

    expect(screen.getByText('Tugadi')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Savatga qo'shish" })).not.toBeInTheDocument();
    expect(cartQuantity()).toBe(0);
  });

  it('adds up to the stock, then refuses one more', async () => {
    await open(page, path, productWithStock(2));

    fireEvent.click(addButton());
    fireEvent.click(addButton());
    expect(cartQuantity()).toBe(2);
    expect(showAlert).not.toHaveBeenCalled();

    fireEvent.click(addButton());
    expect(showAlert).toHaveBeenCalledWith('Kechirasiz, omborda faqat 2 dona qolgan.');
    expect(cartQuantity()).toBe(2);
  });

  it('does not block a product whose stock the API did not send', async () => {
    await open(page, path, productWithStock(undefined));

    fireEvent.click(addButton());
    fireEvent.click(addButton());

    expect(cartQuantity()).toBe(2);
    expect(showAlert).not.toHaveBeenCalled();
  });
});

describe.each([
  ['Home', <Home />, '/'],
  ['CategoryDetail', <CategoryDetail />, '/category/1'],
] as const)('%s: wave M2 cards', (_name, page, path) => {
  it('asks for light cards', async () => {
    await open(page, path, productWithStock(5));
    const feedCall = get.mock.calls.find(([url]) => url === '/catalog');
    expect(feedCall?.[1]).toMatchObject({ params: { view: 'cards', limit: 50 } });
  });

  it('a dish with options loads its store menu and opens the sheet of the full product', async () => {
    const burger = makeProduct({
      id: 7,
      store_id: 1,
      name: 'Burger',
      variants: [makeVariant({ id: 70, uuid: 'v-70', name: 'Kichik', price: '30000.00' }), makeVariant({ id: 71, uuid: 'v-71', name: 'Katta', price: '45000.00' })],
    });
    await open(page, path, burger);

    fireEvent.click(addButton());
    await flush();

    expect(get.mock.calls.some(([url]) => url === `/catalog?store_id=${makeStore({ id: 1 }).uuid}`)).toBe(true);
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(screen.getAllByText(/Katta/).length).toBeGreaterThan(0);
    expect(cartQuantity()).toBe(0); // nothing goes in before the choice
  });
});

describe('Home: wave M2 store that does not take orders', () => {
  it('shows it on the store card and on its dishes (the card says so itself), and refuses the add', async () => {
    const store = { ...makeStore({ id: 1, name: 'Somsa markazi' }), branches: [], is_open: true, accepting_orders: false };
    await open(<Home />, '/', productWithStock(5), { stores: [store], accepting: false });

    expect(screen.getAllByText('Hozir buyurtma qabul qilmayapti').length).toBeGreaterThanOrEqual(2);
    expect(screen.queryByRole('button', { name: "Savatga qo'shish" })).not.toBeInTheDocument();
    expect(cartQuantity()).toBe(0);
  });
});

describe('Home: wave M2 search', () => {
  it('finds a dish by its restaurant, as before', async () => {
    const products = [
      { ...productWithStock(5), id: 1, name: 'Lavash' },
      { ...productWithStock(5), id: 2, name: 'Cola', store_id: 2 },
    ];
    get.mockImplementation((url) => {
      if (url === '/stores') return Promise.resolve({ data: { success: true, data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 } } });
      return Promise.resolve({
        data: {
          success: true,
          data: {
            categories: [],
            products: [cardOf(products[0], makeStore({ id: 1, name: 'Somsa markazi' })), cardOf(products[1], makeStore({ id: 2, name: 'Ichimliklar' }))],
            promos: [],
            highlight_product: null,
          },
        },
      });
    });
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter initialEntries={['/']}>
          <Routes>
            <Route path="/" element={<Home />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>,
    );
    await flush();

    fireEvent.change(screen.getByPlaceholderText('Taom yoki oshxona qidirish'), { target: { value: 'ichimlik' } });
    await flush();

    expect(screen.getByText('Cola')).toBeInTheDocument();
    expect(screen.queryByText('Lavash')).not.toBeInTheDocument();
  });
});

describe('Home: wave M2 card fields', () => {
  it('a card of a store not in the loaded store pages still says it does not take orders', async () => {
    await open(<Home />, '/', productWithStock(5), { stores: [], accepting: false });
    expect(screen.getByText('Hozir buyurtma qabul qilmayapti')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Savatga qo'shish" })).not.toBeInTheDocument();
  });

  it('a weighed dish with options shows its price per kg from the card unit', async () => {
    const qazi = makeProduct({
      id: 9,
      store_id: 1,
      name: 'Qazi',
      variants: [makeVariant({ id: 90, uuid: 'v-90', name: 'kg', price: '33333.00', unit: 'kg' }), makeVariant({ id: 91, uuid: 'v-91', name: 'l', price: '40000.00', unit: 'l' })],
    });
    await open(<Home />, '/', qazi);
    expect(screen.getByText(/\/ kg/)).toBeInTheDocument();
  });
});
