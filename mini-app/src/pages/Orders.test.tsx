import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore, type Language } from '../store/languageStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import type { Order, OrderStatus } from '../types';
import Orders from './Orders';

// The customer's order list shows the right label and colour for every status the backend can send

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const STATUSES: OrderStatus[] = [
  'new',
  'accepted',
  'preparing',
  'ready',
  'courier_assigned',
  'on_the_way',
  'delivery',
  'delivered',
  'canceled',
  'rejected',
  'refunded',
];

const LABELS: Record<Language, Record<OrderStatus, string>> = {
  uz: {
    new: 'Kutilmoqda',
    accepted: 'Qabul qilindi',
    preparing: 'Tayyorlanmoqda',
    ready: 'Tayyor',
    courier_assigned: 'Kuryer biriktirildi',
    on_the_way: "Yo'lda",
    delivery: "Yo'lda",
    delivered: 'Yetkazildi',
    canceled: 'Bekor qilindi',
    rejected: 'Rad etildi',
    refunded: 'Qaytarildi',
  },
  ru: {
    new: 'Ожидание',
    accepted: 'Принят',
    preparing: 'Готовится',
    ready: 'Готов',
    courier_assigned: 'Курьер назначен',
    on_the_way: 'В пути',
    delivery: 'В пути',
    delivered: 'Доставлен',
    canceled: 'Отменен',
    rejected: 'Отклонен',
    refunded: 'Возвращен',
  },
  en: {
    new: 'Pending',
    accepted: 'Accepted',
    preparing: 'Preparing',
    ready: 'Ready',
    courier_assigned: 'Courier assigned',
    on_the_way: 'On the way',
    delivery: 'On the way',
    delivered: 'Delivered',
    canceled: 'Cancelled',
    rejected: 'Rejected',
    refunded: 'Refunded',
  },
};

// One order per status; the 8-letter code shown on a card is its id
const codeOf = (id: number) => String(id).padStart(8, '0');
function ordersOfEveryStatus(): Order[] {
  return STATUSES.map((status, index) =>
    makeOrder({ id: index + 1, uuid: `${codeOf(index + 1)}-0000-4000-8000-000000000000`, status, payment_status: 'pending' }),
  );
}

let client: QueryClient;

function renderOrders() {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Orders />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function serveOrders(orders: Order[]) {
  get.mockResolvedValue({ data: { success: true, data: orders } });
}

// The header of an order card: its code, date, status badge and total
const cardOf = (id: number) => {
  const header = screen.getByText(`#${codeOf(id)}`).closest<HTMLElement>('.cursor-pointer');
  if (!header) throw new Error(`no card for order ${id}`);
  return header;
};

beforeEach(() => {
  client = new QueryClient();
  useAuthStore.getState().setAuth('token', makeUser(), makeStore(), false);
});

afterEach(() => {
  client.clear();
  useLanguageStore.setState({ language: 'uz' });
});

describe('Orders: status of each order', () => {
  it.each(['uz', 'ru', 'en'] as const)('names every status in %s', async (language) => {
    useLanguageStore.setState({ language });
    serveOrders(ordersOfEveryStatus());

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    STATUSES.forEach((status, index) => {
      expect(within(cardOf(index + 1)).getByText(LABELS[language][status]), `${status} in ${language}`).toBeInTheDocument();
    });
  });

  it('shows "Pending" for a new order only: ready, courier_assigned, on_the_way, delivery, rejected, refunded and canceled are not new', async () => {
    serveOrders(ordersOfEveryStatus());

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    expect(screen.getAllByText(LABELS.uz.new)).toHaveLength(1);
    expect(within(cardOf(1)).getByText(LABELS.uz.new)).toBeInTheDocument();
    for (const status of ['ready', 'courier_assigned', 'on_the_way', 'delivery', 'rejected', 'refunded', 'canceled'] as const) {
      const card = cardOf(STATUSES.indexOf(status) + 1);
      expect(within(card).queryByText(LABELS.uz.new), status).not.toBeInTheDocument();
    }
  });

  it('colours the badges that used to look like a pending order by their own meaning', async () => {
    serveOrders(ordersOfEveryStatus());

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    const badge = (status: OrderStatus) => within(cardOf(STATUSES.indexOf(status) + 1)).getByText(LABELS.uz[status]).parentElement;
    expect(badge('new')).toHaveClass('text-amber-500');
    expect(badge('ready')).toHaveClass('text-teal-500');
    expect(badge('courier_assigned')).toHaveClass('text-indigo-500');
    expect(badge('on_the_way')).toHaveClass('text-orange-500');
    expect(badge('delivery')).toHaveClass('text-orange-500');
    expect(badge('rejected')).toHaveClass('text-rose-500');
    expect(badge('refunded')).toHaveClass('text-sky-500');
    expect(badge('canceled')).toHaveClass('text-red-500');
    expect(badge('delivered')).toHaveClass('text-emerald-500');
  });

  it('shows a status the app has never heard of as it came, not as "Pending"', async () => {
    serveOrders([makeOrder({ id: 1, uuid: `${codeOf(1)}-0000-4000-8000-000000000000`, status: 'awaiting_pickup' as OrderStatus })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    const card = within(cardOf(1));
    expect(card.getByText('awaiting_pickup')).toBeInTheDocument();
    expect(card.queryByText(LABELS.uz.new)).not.toBeInTheDocument();
    expect(card.getByText('awaiting_pickup').parentElement).toHaveClass('text-slate-500');
  });
});
