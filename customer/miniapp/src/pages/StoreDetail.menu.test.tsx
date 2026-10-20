import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useCartStore } from '../store/cartStore';
import { makeProduct, makeStore, makeVariant } from '../test/factories';
import type { BranchAvailability, ModifierGroup } from '../types';
import StoreDetail from './StoreDetail';

// The store page on the new menu (P1-31): "Tugadi" and no way to add a sold-out dish, a dish sold by weight, a dish with
// required modifiers, and the availability that changes while the page is open.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const sauce: ModifierGroup = {
  id: 1, uuid: 'g-1', name: 'Sous', min_select: 1, max_select: 1, is_required: true, sort_order: 0,
  modifiers: [
    { id: 5, uuid: 'm-5', modifier_group_id: 1, name: 'Ketchup', price_delta: '0.00', max_quantity: 1, sort_order: 0, is_active: true },
    { id: 6, uuid: 'm-6', modifier_group_id: 1, name: 'Pishloqli', price_delta: '3000.00', max_quantity: 1, sort_order: 1, is_active: true },
  ],
};

const PRODUCTS = [
  makeProduct({ id: 1, name: 'Burger', variants: [makeVariant({ id: 1, uuid: 'v-burger', product_id: 1, price: '50000.00', stock: 20 })], modifier_groups: [sauce] }),
  makeProduct({ id: 2, name: 'Qazi', variants: [makeVariant({ id: 2, uuid: 'v-meat', product_id: 2, name: 'kg', price: '120000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.500', stock: 10 })] }),
  makeProduct({ id: 3, name: 'Osh', variants: [makeVariant({ id: 3, uuid: 'v-osh', product_id: 3, price: '35000.00', stock: 20 })] }),
  makeProduct({ id: 4, name: 'Cola', variants: [makeVariant({ id: 4, uuid: 'v-cola', product_id: 4, price: '8000.00', stock: 20 })] }),
];

const branch = (stopped: Partial<BranchAvailability['stopped']> = {}, soldOut: number[] = []): BranchAvailability[] => [
  { branch_id: 1, stopped: { products: [], variants: [], modifiers: [], ...stopped }, sold_out: { variants: soldOut } },
];

let availability: BranchAvailability[];
let client: QueryClient;
const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

async function open(flushPage: () => Promise<void> = flush) {
  get.mockImplementation((url) => {
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 1, branches: availability } } });
    if (url.startsWith('/catalog')) {
      const store = { ...makeStore({ id: 1, name: 'Evos' }), branches: [], ratings: [] };
      return Promise.resolve({ data: { success: true, data: { store, categories: [], products: PRODUCTS } } });
    }
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/store/22222222-2222-4222-8222-222222222222']}>
        <Routes>
          <Route path="/store/:id" element={<StoreDetail />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flushPage();
  await flushPage();
}

const row = (name: string) => screen.getByRole('heading', { name, level: 3 }).closest('li') as HTMLElement;

beforeEach(() => {
  client = new QueryClient();
  availability = branch();
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe('StoreDetail: sold out', () => {
  it('marks a dish stopped on the branch "Tugadi" and offers no way to add it', async () => {
    availability = branch({ variants: [4] });
    await open();

    expect(within(row('Cola')).getByText('Tugadi')).toBeInTheDocument();
    expect(within(row('Cola')).queryByRole('button')).not.toBeInTheDocument();
    expect(within(row('Osh')).getByRole('button', { name: "Qo'shish: Osh" })).toBeInTheDocument();
  });

  it('marks a dish whose batch ran out ("tugadi") the same way', async () => {
    availability = branch({}, [3]);
    await open();

    expect(within(row('Osh')).getByText('Tugadi')).toBeInTheDocument();
    expect(within(row('Osh')).queryByRole('button')).not.toBeInTheDocument();
  });

  it('marks a dish stopped as a whole', async () => {
    availability = branch({ products: [3] });
    await open();

    expect(within(row('Osh')).getByText('Tugadi')).toBeInTheDocument();
  });

  it('marks a dish with no stock left', async () => {
    PRODUCTS[3].variants[0].stock = 0;
    try {
      await open();
      expect(within(row('Cola')).getByText('Tugadi')).toBeInTheDocument();
    } finally {
      PRODUCTS[3].variants[0].stock = 20;
    }
  });

  it('shows no badge while only some other branch of the store is out of it', async () => {
    availability = [...branch({ variants: [4] }), { branch_id: 2, stopped: { products: [], variants: [], modifiers: [] }, sold_out: { variants: [] } }];
    await open();

    expect(within(row('Cola')).queryByText('Tugadi')).not.toBeInTheDocument();
  });

  it('asks the availability endpoint of the store for the first picture', async () => {
    await open();

    expect(get).toHaveBeenCalledWith('/catalog/availability', { params: { store_id: 1 } });
  });
});

describe('StoreDetail: modifiers and weight', () => {
  it('opens the sheet of a dish with a required group, and adds a line with the chosen modifier', async () => {
    await open();

    fireEvent.click(within(row('Burger')).getByRole('button', { name: "Qo'shish: Burger" }));
    const sheet = screen.getByRole('dialog', { name: 'Burger' });
    expect(within(sheet).getByRole('button', { name: /"Sous" tanlang/ })).toBeDisabled();

    fireEvent.click(within(sheet).getByRole('radio', { name: /Pishloqli/ }));
    fireEvent.click(within(sheet).getByRole('button', { name: /^Tasdiqlash · 53\s000\sso'm/ }));

    const [item] = useCartStore.getState().items;
    expect(item).toMatchObject({ productId: 1, variantId: 'v-burger', quantity: 1, price: 50000 });
    expect(item.modifiers).toEqual([{ id: 6, uuid: 'm-6', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 1 }]);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('keeps lines with different modifier choices apart in the cart', async () => {
    await open();

    for (const choice of [/Ketchup/, /Pishloqli/, /Ketchup/]) {
      fireEvent.click(within(row('Burger')).getByRole('button', { name: "Qo'shish: Burger" }));
      const sheet = screen.getByRole('dialog', { name: 'Burger' });
      fireEvent.click(within(sheet).getByRole('radio', { name: choice }));
      fireEvent.click(within(sheet).getByRole('button', { name: /^Tasdiqlash/ }));
    }

    const items = useCartStore.getState().items;
    expect(items).toHaveLength(2);
    expect(items.map((item) => item.quantity).sort()).toEqual([1, 2]);
    expect(within(row('Burger')).getByText('Savatda: 3')).toBeInTheDocument();
  });

  it('shows the price per kg and adds a weight in the step of the item', async () => {
    await open();
    expect(within(row('Qazi')).getByText(/120\s000\sso'm/)).toHaveTextContent('/ kg');

    fireEvent.click(within(row('Qazi')).getByRole('button', { name: "Qo'shish: Qazi" }));
    const sheet = screen.getByRole('dialog', { name: 'Qazi' });
    fireEvent.click(within(sheet).getByRole('button', { name: "Qazi: ko'paytirish" }));
    fireEvent.click(within(sheet).getByRole('button', { name: /^Tasdiqlash · 120\s000\sso'm/ }));

    expect(useCartStore.getState().items[0]).toMatchObject({ productId: 2, quantity: 1, unit: 'kg', quantityStep: 0.5, minQuantity: 0.5 });
    expect(within(row('Qazi')).getByText('Savatda: 1 kg')).toBeInTheDocument();
  });

  it('keeps the plain stepper for a dish without choices', async () => {
    await open();

    fireEvent.click(within(row('Osh')).getByRole('button', { name: "Qo'shish: Osh" }));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(useCartStore.getState().items[0]).toMatchObject({ productId: 3, quantity: 1 });
    expect(within(row('Osh')).getByRole('button', { name: "Osh: ko'paytirish" })).toBeInTheDocument();
  });
});

describe('StoreDetail: availability that changes while the page is open', () => {
  it('shows a dish that sold out after the next poll', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    try {
      await open(async () => { await act(() => vi.advanceTimersByTimeAsync(0)); });
      expect(within(row('Osh')).queryByText('Tugadi')).not.toBeInTheDocument();

      availability = branch({ variants: [3] });
      await act(() => vi.advanceTimersByTimeAsync(20_000));
      await act(() => vi.advanceTimersByTimeAsync(100));

      expect(within(row('Osh')).getByText('Tugadi')).toBeInTheDocument();
    } finally {
      vi.useRealTimers();
    }
  });
});
