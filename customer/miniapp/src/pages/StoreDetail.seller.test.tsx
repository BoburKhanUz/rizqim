import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useCartStore } from '../store/cartStore';
import { makeStore } from '../test/factories';
import type { StoreSeller } from '../types';
import StoreDetail from './StoreDetail';

// Wave P (receipts): the restaurant page says who sells the food (consumer law art. 28-1) from the store's `seller`.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
let client: QueryClient;
const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

async function open(seller: StoreSeller | null | undefined) {
  get.mockImplementation((url) => {
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 1, branches: [] } } });
    if (url.startsWith('/catalog')) {
      const store = { ...makeStore({ id: 1, name: 'Evos' }), branches: [], ratings: [], ...(seller !== undefined ? { seller } : {}) };
      return Promise.resolve({ data: { success: true, data: { store, categories: [], products: [] } } });
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
  await flush();
  await flush();
}

beforeEach(() => {
  client = new QueryClient();
  useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
});

describe('StoreDetail: the seller', () => {
  it('shows the legal name, STIR, address, phone and the way to complain, behind "Sotuvchi"', async () => {
    await open({ legal_name: '"Evos" MChJ', tin: '305123456', legal_address: 'Toshkent, Yunusobod 5', phone: '+998712001020', complaint_contact: null });

    const box = screen.getByTestId('store-seller');
    const summary = box.querySelector('summary') as HTMLElement;
    expect(summary).toHaveTextContent('Sotuvchi: "Evos" MChJ');
    fireEvent.click(summary);
    const seller = within(box).getByTestId('seller-info');
    expect(within(seller).getByText('305123456')).toBeInTheDocument();
    expect(within(seller).getByText('Toshkent, Yunusobod 5')).toBeInTheDocument();
    expect(within(seller).getByText('+998712001020')).toBeInTheDocument();
    expect(within(seller).getByRole('link', { name: /Shikoyatlar/ })).toHaveAttribute('href', '/complaints');
    expect(within(seller).getByText(/savdo maydonchasi operatori/)).toBeInTheDocument();
  });

  it('shows nothing without seller details (an older backend, or none filled)', async () => {
    await open(undefined);
    expect(screen.queryByText(/Sotuvchi/)).not.toBeInTheDocument();
  });

  it('shows nothing when every field is empty', async () => {
    await open({ legal_name: null, tin: null, legal_address: null, phone: null, complaint_contact: null });
    expect(screen.queryByText(/Sotuvchi/)).not.toBeInTheDocument();
  });
});
