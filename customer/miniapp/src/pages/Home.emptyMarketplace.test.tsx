import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useAddressStore } from '../store/useAddressStore';
import { makeStore, makeUser } from '../test/factories';
import Home from './Home';

// Wave P: a customer signed in on the global bot of a marketplace without stores (POST /auth/telegram answered
// `store: null`) lands on the home screen: it shows the empty marketplace, not a blank page or a crash.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const flush = async () => {
  for (let i = 0; i < 3; i += 1) await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
};

function serve(stores: unknown[]) {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: stores, meta: { page: 1, per_page: 10, total: stores.length, last_page: 1 } } });
    return Promise.resolve({ data: { success: true, data: { categories: [], products: [], promos: [], highlight_product: null } } });
  });
}

async function openHome() {
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/profile" element={<p>Profil sahifasi</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flush();
}

beforeEach(() => {
  get.mockReset();
  useAddressStore.setState({ addresses: [], selectedAddressId: null });
  useAuthStore.getState().logout();
  useAuthStore.getState().setAuth('token', makeUser({ role: 'customer', store_id: null }), null, false);
});

describe('Home: empty marketplace after a sign-in without a store', () => {
  it('shows the empty state when no store and no dish is listed', async () => {
    serve([]);
    await openHome();

    expect(useAuthStore.getState().store).toBeNull();
    // The header shows the RIZQIM logo (an image named "Rizqim") as the page heading
    expect(screen.getByRole('heading', { level: 1, name: 'Rizqim' })).toContainElement(screen.getByRole('img', { name: 'Rizqim' }));
    expect(screen.getByText("Hozircha oshxonalar yo'q")).toBeInTheDocument();
    expect(screen.queryByText('Siz uchun tavsiyalar')).not.toBeInTheDocument();
  });

  it('shows the stores once the marketplace has some, without the empty state', async () => {
    serve([{ ...makeStore({ id: 1, uuid: 's-1', name: 'Evos' }), branches: [] }]);
    await openHome();

    expect(screen.getByText('Evos')).toBeInTheDocument();
    expect(screen.queryByText("Hozircha oshxonalar yo'q")).not.toBeInTheDocument();
  });

  it('says no store serves the selected address (not an empty marketplace) and offers to change it', async () => {
    useAddressStore.setState({
      addresses: [{ id: 5, title: 'Uy', address: 'Chilonzor 9', lat: 41.28, lng: 69.2, is_default: true }],
      selectedAddressId: 5,
    });
    serve([]);
    await openHome();

    expect(screen.getByText("Bu manzilga hozircha yetkazib beradigan oshxona yo'q")).toBeInTheDocument();
    expect(screen.queryByText("Hozircha oshxonalar yo'q")).not.toBeInTheDocument();
    expect(screen.queryByText('Siz uchun tavsiyalar')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: "Manzilni o'zgartirish" }));
    expect(screen.getByText('Profil sahifasi')).toBeInTheDocument();
  });
});
