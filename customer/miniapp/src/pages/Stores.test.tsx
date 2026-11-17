import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { makeStore } from '../test/factories';
import Stores from './Stores';

// Wave M2: the list of all stores comes 20 at a time; a search loads the rest so it still sees every store

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
const flush = async () => {
  for (let i = 0; i < 3; i += 1) await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
};

const STORES = Array.from({ length: 45 }, (_, i) => ({ ...makeStore({ id: i + 1, uuid: `s-${i + 1}`, name: `Oshxona ${i + 1}` }), branches: [] }));

function serve() {
  get.mockImplementation((_url, config) => {
    const { page = 1, per_page = 20 } = (config?.params ?? {}) as { page?: number; per_page?: number };
    const data = STORES.slice((page - 1) * per_page, page * per_page);
    return Promise.resolve({ data: { success: true, data, meta: { page, per_page, total: STORES.length, last_page: Math.ceil(STORES.length / per_page) } } });
  });
}

function renderStores() {
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter>
        <Stores />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const pagesAsked = () => get.mock.calls.map(([, config]) => (config?.params as { page?: number })?.page);

describe('Stores: pages', () => {
  it('shows the first page and the total, and loads the next page on demand', async () => {
    serve();
    renderStores();
    await flush();

    expect(screen.getByText('45 oshxona')).toBeInTheDocument();
    expect(screen.getByText('Oshxona 20')).toBeInTheDocument();
    expect(screen.queryByText('Oshxona 21')).not.toBeInTheDocument();
    expect(get.mock.calls[0]?.[1]).toMatchObject({ params: { page: 1, per_page: 20 } });

    fireEvent.click(screen.getByRole('button', { name: "Ko'proq ko'rsatish" }));
    await flush();

    expect(screen.getByText('Oshxona 40')).toBeInTheDocument();
    expect(pagesAsked()).toEqual([1, 2]);
  });

  it('a search loads every remaining page and finds a store beyond the first one', async () => {
    serve();
    renderStores();
    await flush();

    fireEvent.change(screen.getByPlaceholderText('Oshxonalarni qidirish'), { target: { value: 'Oshxona 44' } });
    for (let i = 0; i < 10 && pagesAsked().length < 3; i += 1) await flush();
    await flush();

    expect(pagesAsked()).toEqual([1, 2, 3]);
    expect(screen.getByText('Oshxona 44')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Ko'proq ko'rsatish" })).not.toBeInTheDocument();
  });
});
