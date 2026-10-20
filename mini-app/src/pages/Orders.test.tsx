import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore, type Language } from '../store/languageStore';
import { makeOrder, makeStore, makeUser } from '../test/factories';
import { failedRequest } from '../test/httpErrors';
import { ordersResponse, serveOrdersApi } from '../test/ordersApi';
import type { Order, OrderStatus } from '../types';
import Orders from './Orders';

// The customer's order list shows the right label and colour for every status the backend can send

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

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

// One order per status; the code shown on a card (its short_code) is its id, 8 digits
const codeOf = (id: number) => String(id).padStart(8, '0');
function ordersOfEveryStatus(): Order[] {
  return STATUSES.map((status, index) =>
    makeOrder({ id: index + 1, uuid: `${codeOf(index + 1)}-0000-4000-8000-000000000000`, short_code: codeOf(index + 1), status, payment_status: 'pending' }),
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
  get.mockImplementation(serveOrdersApi(orders));
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

  it('colours each badge by its semantic group (lib/orderStatus.ts), never like a pending order unless it is one', async () => {
    serveOrders(ordersOfEveryStatus());

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    const badge = (status: OrderStatus) => within(cardOf(STATUSES.indexOf(status) + 1)).getByText(LABELS.uz[status]).parentElement;
    expect(badge('new')).toHaveClass('text-warning-soft-fg');
    for (const status of ['accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'] as const) {
      expect(badge(status), status).toHaveClass('text-info-soft-fg');
    }
    expect(badge('delivered')).toHaveClass('text-success-soft-fg');
    expect(badge('canceled')).toHaveClass('text-danger-soft-fg');
    expect(badge('rejected')).toHaveClass('text-danger-soft-fg');
    expect(badge('refunded')).toHaveClass('text-fg-muted');
  });

  it('shows a status the app has never heard of as it came, not as "Pending"', async () => {
    serveOrders([makeOrder({ id: 1, uuid: `${codeOf(1)}-0000-4000-8000-000000000000`, short_code: codeOf(1), status: 'awaiting_pickup' as OrderStatus })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    const card = within(cardOf(1));
    expect(card.getByText('awaiting_pickup')).toBeInTheDocument();
    expect(card.queryByText(LABELS.uz.new)).not.toBeInTheDocument();
    expect(card.getByText('awaiting_pickup').parentElement).toHaveClass('text-fg-muted');
  });
});

const ordersAt = (id: number, overrides: Partial<Order> = {}) =>
  makeOrder({ id, uuid: `${codeOf(id)}-0000-4000-8000-000000000000`, short_code: codeOf(id), payment_status: 'pending', ...overrides });

const expand = (id: number) => fireEvent.click(screen.getByText(`#${codeOf(id)}`));

describe('Orders: open orders and history', () => {
  it('lists the open orders first and the finished ones below, from the two scopes of the API', async () => {
    serveOrders([ordersAt(1, { status: 'delivered' }), ordersAt(2, { status: 'preparing' })]);

    renderOrders();
    await screen.findByText(`#${codeOf(2)}`);
    await screen.findByText(`#${codeOf(1)}`);

    const active = screen.getByRole('region', { name: 'Faol buyurtmalar' });
    const history = screen.getByRole('region', { name: 'Buyurtmalar tarixi' });
    expect(within(active).getByText(`#${codeOf(2)}`)).toBeInTheDocument();
    expect(within(history).getByText(`#${codeOf(1)}`)).toBeInTheDocument();
    expect(get.mock.calls.every(([, config]) => !!(config?.params as { scope?: string })?.scope)).toBe(true); // never the unscoped list
  });

  it('warns that only the newest open orders are shown when the server cut the list', async () => {
    get.mockImplementation((_url, config) => {
      const response = ordersResponse([ordersAt(1, { status: 'new' })], config);
      if ((config?.params as { scope?: string })?.scope === 'active') (response.data as { meta: unknown }).meta = { truncated: true };
      return Promise.resolve(response);
    });

    renderOrders();

    expect(await screen.findByText(/eng yangi 200 tasi/)).toBeInTheDocument();
  });

  it('does not warn when nothing was cut', async () => {
    serveOrders([ordersAt(1, { status: 'new' })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    expect(screen.queryByText(/eng yangi 200 tasi/)).not.toBeInTheDocument();
  });

  it('loads the next page of history with "Ko\'proq yuklash", until there is none', async () => {
    const finished = Array.from({ length: 25 }, (_, i) => ordersAt(i + 1, { status: 'delivered' }));
    serveOrders(finished);

    renderOrders();
    await screen.findByText(`#${codeOf(20)}`);
    expect(screen.queryByText(`#${codeOf(21)}`)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: "Ko'proq yuklash" }));

    expect(await screen.findByText(`#${codeOf(25)}`)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Ko'proq yuklash" })).not.toBeInTheDocument();
    const historyCalls = get.mock.calls.filter(([, config]) => (config?.params as { scope?: string })?.scope === 'history');
    expect(historyCalls[1][1]).toEqual({ params: { scope: 'history', per_page: 20, cursor: '20' } });
  });

  it('loads the next page by itself when the end of the list scrolls into view', async () => {
    const observers: { callback: IntersectionObserverCallback; observe: ReturnType<typeof vi.fn> }[] = [];
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        observe = vi.fn();
        disconnect = vi.fn();
        constructor(callback: IntersectionObserverCallback) {
          observers.push({ callback, observe: this.observe });
        }
      },
    );
    serveOrders(Array.from({ length: 25 }, (_, i) => ordersAt(i + 1, { status: 'delivered' })));

    renderOrders();
    await screen.findByText(`#${codeOf(20)}`);
    const observer = observers[observers.length - 1];
    expect(observer.observe).toHaveBeenCalled();

    observer.callback([{ isIntersecting: true } as IntersectionObserverEntry], {} as IntersectionObserver);

    expect(await screen.findByText(`#${codeOf(25)}`)).toBeInTheDocument();
  });

  it('keeps the page it has and offers a retry when the next page fails', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const finished = Array.from({ length: 25 }, (_, i) => ordersAt(i + 1, { status: 'delivered' }));
    serveOrders(finished);
    renderOrders();
    await screen.findByText(`#${codeOf(20)}`);

    get.mockRejectedValue(failedRequest(422, { message: 'x' }, '/orders'));
    fireEvent.click(screen.getByRole('button', { name: "Ko'proq yuklash" }));

    expect(await screen.findByRole('alert', {}, { timeout: 4000 })).toHaveTextContent("Keyingi buyurtmalarni yuklab bo'lmadi."); // after react-query's one retry
    expect(screen.getByText(`#${codeOf(20)}`)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: "Ko'proq yuklash" })).toBeEnabled();
  });
});

describe('Orders: why an order was canceled', () => {
  it.each([
    ['customer_request', "Sizning so'rovingizga ko'ra"],
    ['no_show', 'Buyurtma olib ketilmadi'],
    ['store_closed', "Do'kon hozir ishlamayapti"],
    ['courier_unavailable', "Bo'sh kuryer topilmadi"],
    ['payment_timeout', "To'lov belgilangan vaqtda amalga oshirilmadi"],
    ['out_of_stock', 'Mahsulot qolmagan'],
    ['other', 'Boshqa sabab'],
  ] as const)('tells the customer "%s" as "%s"', async (reason, label) => {
    serveOrders([ordersAt(1, { status: 'canceled', cancel_reason: reason })]);

    renderOrders();

    expect(await screen.findByText(label)).toBeInTheDocument();
  });

  it('shows the note too, and for a rejected order', async () => {
    serveOrders([ordersAt(1, { status: 'rejected', cancel_reason: 'out_of_stock', cancel_note: 'Lavash tugadi' })]);

    renderOrders();

    expect(await screen.findByText('Mahsulot qolmagan')).toBeInTheDocument();
    expect(screen.getByText('"Lavash tugadi"')).toBeInTheDocument();
  });

  it('says nothing for a canceled order from before reasons were recorded', async () => {
    serveOrders([ordersAt(1, { status: 'canceled', cancel_reason: null, cancel_note: null })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    expect(screen.queryByText('Bekor qilish sababi')).not.toBeInTheDocument();
  });

  it('says nothing about a reason on an order that is not canceled', async () => {
    serveOrders([ordersAt(1, { status: 'delivered', cancel_reason: 'other' })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);

    expect(screen.queryByText('Boshqa sabab')).not.toBeInTheDocument();
  });

  it('translates the reason', async () => {
    useLanguageStore.setState({ language: 'ru' });
    serveOrders([ordersAt(1, { status: 'canceled', cancel_reason: 'no_show' })]);

    renderOrders();

    expect(await screen.findByText('Заказ не был забран')).toBeInTheDocument();
  });
});

describe('Orders: cancelling an order', () => {
  const cancelButton = () => screen.queryByRole('button', { name: /Bekor qilish$/ });

  it.each([
    ['new', 'pending', true],
    ['new', 'failed', true],
    ['new', 'paid', false],
    ['new', 'refunded', false],
    ['accepted', 'pending', false],
    ['preparing', 'pending', false],
    ['on_the_way', 'pending', false],
    ['delivered', 'paid', false],
    ['canceled', 'pending', false],
  ] as const)('a %s order with %s payment: button %s', async (status, payment_status, shown) => {
    serveOrders([ordersAt(1, { status, payment_status })]);

    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);
    expand(1);

    expect(!!cancelButton()).toBe(shown);
  });

  async function openDialog() {
    serveOrders([ordersAt(1, { status: 'new', payment_status: 'pending' })]);
    renderOrders();
    await screen.findByText(`#${codeOf(1)}`);
    expand(1);
    fireEvent.click(cancelButton()!);
    return screen.getByRole('dialog');
  }

  it('asks before cancelling and does nothing when the dialog is closed', async () => {
    const dialog = await openDialog();

    expect(dialog).toHaveTextContent('Buyurtmani bekor qilmoqchimisiz?');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Yopish' }));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });

  it('cancels with the customer_request reason and an optional note, then reloads the orders', async () => {
    post.mockResolvedValue({ data: { success: true, data: ordersAt(1, { status: 'canceled', cancel_reason: 'customer_request' }) } });
    const dialog = await openDialog();
    const activeCallsBefore = get.mock.calls.filter(([, config]) => (config?.params as { scope?: string })?.scope === 'active').length;

    fireEvent.change(within(dialog).getByLabelText(/Izoh/), { target: { value: '  Adashib buyurtma berdim  ' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(post).toHaveBeenCalledExactlyOnceWith(`/orders/${codeOf(1)}-0000-4000-8000-000000000000/cancel`, {
      reason: 'customer_request',
      note: 'Adashib buyurtma berdim',
    });
    await waitFor(() =>
      expect(get.mock.calls.filter(([, config]) => (config?.params as { scope?: string })?.scope === 'active').length).toBeGreaterThan(activeCallsBefore),
    );
  });

  it('sends no note when none was written', async () => {
    post.mockResolvedValue({ data: { success: true, data: {} } });
    const dialog = await openDialog();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));

    await waitFor(() => expect(post).toHaveBeenCalled());
    expect(post.mock.calls[0][1]).toEqual({ reason: 'customer_request' });
  });

  it('limits the note to 500 characters', async () => {
    const dialog = await openDialog();

    expect(within(dialog).getByLabelText(/Izoh/)).toHaveAttribute('maxlength', '500');
  });

  it('shows the server\'s message when the order can no longer be cancelled, and keeps the dialog open', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    post.mockRejectedValue(failedRequest(422, { message: 'Buyurtma allaqachon qabul qilingan, uni bekor qilib bo\'lmaydi.' }, '/orders/x/cancel'));
    const dialog = await openDialog();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent("Buyurtma allaqachon qabul qilingan, uni bekor qilib bo'lmaydi.");
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Bekor qilish' })).toBeEnabled(); // it can be tried again
  });

  it('asks to wait when the throttle (5 a minute) says so', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    post.mockRejectedValue(failedRequest(429, { message: 'Too Many Attempts.' }, '/orders/x/cancel'));
    const dialog = await openDialog();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent("Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.");
  });

  it('forgets an old error when the dialog is opened again', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    post.mockRejectedValue(failedRequest(429, {}, '/orders/x/cancel'));
    const dialog = await openDialog();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));
    await within(dialog).findByRole('alert');

    fireEvent.click(within(dialog).getByRole('button', { name: 'Yopish' }));
    fireEvent.click(cancelButton()!);

    expect(within(screen.getByRole('dialog')).queryByRole('alert')).not.toBeInTheDocument();
  });
});
