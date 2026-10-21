import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/authStore';
import { makeOrder, makeOrderItem, makeStore, makeUser } from '../test/factories';
import { ordersResponse } from '../test/ordersApi';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import CourierDashboard from './CourierDashboard';
import ManagerDashboard from './ManagerDashboard';

// The staff dashboards on a fake API and a controlled clock: they poll, and money arrives as decimal strings

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

// The staff screens talk to the API through @rizqim/staff, over fetch: this is the fake backend behind it
let calls: FakeCall[];
let served: ReturnType<typeof makeOrder>[];
let ordersStatus: number | null; // an error status the orders answer with, or null
let ordersTruncated: boolean;
let postAnswer: unknown;
const callsOf = (scope: string) => callsTo(calls, '/orders').filter((call) => call.query.scope === scope);
const ordersCalls = () => callsTo(calls, '/orders').length;
const posts = () => calls.filter((call) => call.method === 'POST').map((call) => [call.path, call.body]);
const failOrders = (status: number) => {
  ordersStatus = status;
};

let client: QueryClient;

function renderWithClient(ui: ReactNode) {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));

function serveOrders(orders: ReturnType<typeof makeOrder>[]) {
  served = orders;
  ordersStatus = null;
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(new Date(2026, 9, 14, 15, 0, 0));
  client = new QueryClient();
  served = [];
  ordersStatus = null;
  ordersTruncated = false;
  postAnswer = success({});
  ({ calls } = stubStaffApi((call) => {
    if (call.method === 'POST') return postAnswer;
    if (call.path !== '/orders') return success([]); // GET /couriers
    if (ordersStatus !== null) return reply(ordersStatus);
    const body = ordersResponse(served, { params: call.query }).data as { meta?: unknown };
    if (call.query.scope === 'active' && ordersTruncated) body.meta = { truncated: true };
    return body;
  }));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
});

describe('ManagerDashboard', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', name: 'Menejer Bobur' }), makeStore(), false);
  });

  it('lists the open orders with their items and total', async () => {
    serveOrders([
      makeOrder({ id: 1, short_code: 'A100', status: 'new', total_price: '45000.00', items: [makeOrderItem({ product_name: 'Lavash', quantity: 2 })] }),
      makeOrder({ id: 2, short_code: 'A200', status: 'delivered', total_price: '30000.00' }),
    ]);

    renderWithClient(<ManagerDashboard />);
    await settle();

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.getByText('Lavash x2')).toBeInTheDocument();
    expect(screen.getByText(/^45 000 so'm$/)).toBeInTheDocument(); // the decimal string, read as a number
    expect(screen.queryByText('#A200')).not.toBeInTheDocument(); // finished orders are on the other tab
  });

  it('keeps showing the orders when a background refresh fails', async () => {
    serveOrders([makeOrder({ id: 1, short_code: 'A100', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();
    expect(screen.getByText('#A100')).toBeInTheDocument();

    failOrders(502);
    await settle(8000 + 2000); // the next poll fails, and so does its retry

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.queryByText("Ma'lumotlarni yuklab bo'lmadi")).not.toBeInTheDocument();
  });

  it('shows the error screen when the very first load fails: there is nothing else to show', async () => {
    failOrders(502);

    renderWithClient(<ManagerDashboard />);
    await settle(5000);

    expect(screen.getByRole('alert')).toHaveTextContent("Ma'lumotlarni yuklab bo'lmadi");
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  it('recovers by itself once the server answers again', async () => {
    failOrders(502);
    renderWithClient(<ManagerDashboard />);
    await settle(5000);
    expect(screen.getByRole('alert')).toHaveTextContent("Ma'lumotlarni yuklab bo'lmadi");
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();

    serveOrders([makeOrder({ id: 1, short_code: 'A100', status: 'new' })]);
    await settle(60_000); // after the longer wait of the backed-off poll

    expect(screen.getByText('#A100')).toBeInTheDocument();
    expect(screen.queryByText("Ma'lumotlarni yuklab bo'lmadi")).not.toBeInTheDocument();
  });

  it('does not poll while the page is hidden', async () => {
    serveOrders([makeOrder({ id: 1, status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();
    const callsAfterLoad = ordersCalls();

    focusManager.setFocused(false);
    await settle(5 * 60_000);

    expect(ordersCalls()).toBe(callsAfterLoad);
  });
});

describe('CourierDashboard', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'courier', name: 'Kuryer Ali' }), makeStore(), false);
  });

  it('adds up the delivery fees of delivered orders as numbers, not as text', async () => {
    serveOrders([
      makeOrder({ id: 1, status: 'delivered', delivery_price: '5000.00' }),
      makeOrder({ id: 2, status: 'delivered', delivery_price: '7000.00' }),
      makeOrder({ id: 3, status: 'delivered', delivery_price: '8000.50' }),
      makeOrder({ id: 4, status: 'on_the_way', delivery_price: '9999.00' }), // not delivered yet
    ]);

    renderWithClient(<CourierDashboard />);
    await settle();

    expect(screen.getByText(/^20 001 so'm$/)).toBeInTheDocument();
    expect(screen.getByText(/^3 ta$/)).toBeInTheDocument();
  });

  it('is not thrown off by a delivery fee that is not a number', async () => {
    serveOrders([
      makeOrder({ id: 1, status: 'delivered', delivery_price: '5000.00' }),
      makeOrder({ id: 2, status: 'delivered', delivery_price: 'n/a' }),
    ]);

    renderWithClient(<CourierDashboard />);
    await settle();

    expect(screen.getByText(/^5 000 so'm$/)).toBeInTheDocument();
    expect(screen.queryByText(/NaN/)).not.toBeInTheDocument();
  });

  it('keeps the deliveries on screen when a background refresh fails', async () => {
    serveOrders([makeOrder({ id: 1, short_code: 'K100', status: 'courier_assigned' })]);
    renderWithClient(<CourierDashboard />);
    await settle();
    expect(screen.getByText('#K100')).toBeInTheDocument();

    failOrders(503);
    await settle(15_000 + 2000);

    expect(screen.getByText('#K100')).toBeInTheDocument();
    expect(screen.queryByText("Ma'lumotlarni yuklab bo'lmadi")).not.toBeInTheDocument();
  });
});

describe('ManagerDashboard: open orders and the archive', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', name: 'Menejer Bobur' }), makeStore(), false);
  });

  const archiveTab = () => screen.getByRole('button', { name: /Yakunlanganlar/ });

  it('reads only the open orders at first and the archive when its tab is opened', async () => {
    serveOrders([
      makeOrder({ id: 1, short_code: 'A100', status: 'new' }),
      makeOrder({ id: 2, short_code: 'A200', status: 'delivered' }),
    ]);

    renderWithClient(<ManagerDashboard />);
    await settle();
    expect(callsOf('active')).toHaveLength(1);
    expect(callsOf('history')).toHaveLength(0);
    expect(callsTo(calls, '/orders').every((call) => !!call.query.scope)).toBe(true);

    fireEvent.click(archiveTab());
    await settle();

    expect(callsOf('history')).toHaveLength(1);
    expect(screen.getByText('#A200')).toBeInTheDocument();
    expect(screen.queryByText('#A100')).not.toBeInTheDocument();
  });

  it('loads the archive a page at a time', async () => {
    serveOrders(Array.from({ length: 25 }, (_, i) => makeOrder({ id: i + 1, short_code: `D${i + 1}`, status: 'delivered' })));
    renderWithClient(<ManagerDashboard />);
    await settle();
    fireEvent.click(archiveTab());
    await settle();
    expect(screen.getByText('#D20')).toBeInTheDocument();
    expect(screen.queryByText('#D21')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: "Ko'proq yuklash" }));
    await settle();

    expect(screen.getByText('#D25')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Ko'proq yuklash" })).not.toBeInTheDocument();
  });

  it('shows why an order in the archive was canceled or rejected', async () => {
    serveOrders([
      makeOrder({ id: 1, short_code: 'C1', status: 'canceled', cancel_reason: 'no_show', cancel_note: null }),
      makeOrder({ id: 2, short_code: 'C2', status: 'rejected', cancel_reason: 'out_of_stock', cancel_note: 'Lavash tugadi' }),
      makeOrder({ id: 3, short_code: 'C3', status: 'canceled', cancel_reason: null }),
    ]);
    renderWithClient(<ManagerDashboard />);
    await settle();
    fireEvent.click(archiveTab());
    await settle();

    expect(screen.getByText('Mijoz kelmadi')).toBeInTheDocument();
    expect(screen.getByText('Mahsulot qolmagan')).toBeInTheDocument();
    expect(screen.getByText('"Lavash tugadi"')).toBeInTheDocument();
    expect(screen.getByText('#C3')).toBeInTheDocument();
  });

  it('warns when there are more open orders than the server sent', async () => {
    serveOrders([makeOrder({ id: 1, short_code: 'A100', status: 'new' })]);
    ordersTruncated = true;

    renderWithClient(<ManagerDashboard />);
    await settle();

    expect(screen.getByText(/eng yangi 200 tasi/)).toBeInTheDocument();
  });

  it('sends a plain status change without a reason', async () => {
    serveOrders([makeOrder({ id: 1, uuid: 'uuid-1', short_code: 'A100', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: /Qabul qilish/ }));
    await settle();

    expect(posts()).toEqual([['/orders/uuid-1/status', { status: 'accepted' }]]);
  });
});

describe('ManagerDashboard: cancelling and rejecting', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', name: 'Menejer Bobur' }), makeStore(), false);
  });

  const reasonLabels = () => within(screen.getByRole('dialog')).getAllByRole('radio').map((radio) => radio.closest('label')?.textContent);

  it('asks for a reason before rejecting a new order, and does not reject until one is chosen', async () => {
    serveOrders([makeOrder({ id: 1, uuid: 'uuid-1', short_code: 'A100', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Rad etish' }));
    const dialog = screen.getByRole('dialog');

    expect(posts()).toEqual([]);
    expect(within(dialog).getByRole('button', { name: 'Rad etish' })).toBeDisabled();
    expect(reasonLabels()).toEqual(["Mijoz so'rovi", 'Mahsulot qolmagan', "Do'kon yopiq", "Kuryer yo'q", 'Boshqa sabab']);
  });

  it('rejects with the chosen reason and note, then closes', async () => {
    serveOrders([makeOrder({ id: 1, uuid: 'uuid-1', short_code: 'A100', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Rad etish' }));
    const dialog = screen.getByRole('dialog');
    fireEvent.click(within(dialog).getByLabelText('Mahsulot qolmagan'));
    fireEvent.change(within(dialog).getByLabelText(/Izoh/), { target: { value: ' Lavash tugadi ' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    await settle();

    expect(posts()).toEqual([['/orders/uuid-1/status', { status: 'rejected', reason: 'out_of_stock', note: 'Lavash tugadi' }]]);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('leaves the note out when there is none', async () => {
    serveOrders([makeOrder({ id: 1, uuid: 'uuid-1', status: 'new' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Rad etish' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByLabelText("Do'kon yopiq"));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Rad etish' }));
    await settle();

    expect(posts()).toContainEqual(['/orders/uuid-1/status', { status: 'rejected', reason: 'store_closed' }]);
  });

  it('offers "Mijoz kelmadi" only for a pickup order and "Kuryer yo\'q" only for a delivery order', async () => {
    serveOrders([
      makeOrder({ id: 1, uuid: 'p-acc', short_code: 'P1', status: 'accepted', order_type: 'pickup' }),
      makeOrder({ id: 2, uuid: 'p-rdy', short_code: 'P2', status: 'ready', order_type: 'pickup' }),
      makeOrder({ id: 3, uuid: 'd-acc', short_code: 'D1', status: 'accepted', order_type: 'delivery' }),
    ]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    expect(screen.getAllByRole('button', { name: 'Bekor qilish' })).toHaveLength(3);
    const reasonsOfNth = (n: number) => {
      fireEvent.click(screen.getAllByRole('button', { name: 'Bekor qilish' })[n]); // newest first, as listed
      const labels = reasonLabels();
      fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Yopish' }));
      return labels;
    };

    for (const pickup of [reasonsOfNth(0), reasonsOfNth(1)]) {
      expect(pickup).toContain('Mijoz kelmadi');
      expect(pickup).not.toContain("Kuryer yo'q");
    }
    const delivery = reasonsOfNth(2);
    expect(delivery).not.toContain('Mijoz kelmadi');
    expect(delivery).toContain("Kuryer yo'q");
  });

  it('cancels an accepted order with status canceled and a reason', async () => {
    serveOrders([makeOrder({ id: 1, uuid: 'p-acc', status: 'preparing', order_type: 'pickup' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Bekor qilish' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByLabelText('Mijoz kelmadi'));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Bekor qilish' }));
    await settle();

    expect(posts()).toContainEqual(['/orders/p-acc/status', { status: 'canceled', reason: 'no_show' }]);
  });

  it('shows the reason error of a 422 in the dialog and keeps it open', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    serveOrders([makeOrder({ id: 1, uuid: 'uuid-1', status: 'new' })]);
    postAnswer = reply(422, { message: 'The given data was invalid.', errors: { reason: ['Bekor qilish sababini tanlang.'] } });
    renderWithClient(<ManagerDashboard />);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Rad etish' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByLabelText('Boshqa sabab'));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Rad etish' }));
    await settle();

    expect(within(screen.getByRole('dialog')).getByRole('alert')).toHaveTextContent('Bekor qilish sababini tanlang.');
  });

  it('does not offer to cancel what is already with the courier or finished', async () => {
    serveOrders([makeOrder({ id: 1, status: 'courier_assigned' }), makeOrder({ id: 2, status: 'on_the_way' })]);
    renderWithClient(<ManagerDashboard />);
    await settle();

    expect(screen.queryByRole('button', { name: 'Bekor qilish' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Rad etish' })).not.toBeInTheDocument();
  });
});

describe('CourierDashboard: today\'s deliveries', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'courier', name: 'Kuryer Ali' }), makeStore(), false);
  });

  it('counts what was delivered today from the history, and not what was delivered before', async () => {
    const today = new Date(2026, 9, 14, 11, 0, 0).toISOString();
    const lastWeek = new Date(2026, 9, 7, 11, 0, 0).toISOString();
    serveOrders([
      makeOrder({ id: 1, status: 'on_the_way', delivery_price: '1000.00' }),
      makeOrder({ id: 2, status: 'delivered', delivery_price: '5000.00', created_at: today, updated_at: today }),
      makeOrder({ id: 3, status: 'delivered', delivery_price: '7000.00', created_at: today, updated_at: today }),
      makeOrder({ id: 4, status: 'delivered', delivery_price: '9000.00', created_at: lastWeek, updated_at: lastWeek }),
      makeOrder({ id: 5, status: 'canceled', delivery_price: '3000.00', created_at: today, updated_at: today }),
    ]);

    renderWithClient(<CourierDashboard />);
    await settle();

    expect(screen.getByText(/^12 000 so'm$/)).toBeInTheDocument();
    expect(screen.getByText(/^2 ta$/)).toBeInTheDocument();
  });

  it('starts a new day at midnight on a screen left open', async () => {
    vi.setSystemTime(new Date(2026, 9, 14, 23, 50, 0));
    const lateLastNight = new Date(2026, 9, 14, 23, 0, 0).toISOString();
    serveOrders([makeOrder({ id: 1, status: 'delivered', delivery_price: '5000.00', created_at: lateLastNight, updated_at: lateLastNight })]);

    renderWithClient(<CourierDashboard />);
    await settle();
    expect(screen.getByText(/^1 ta$/)).toBeInTheDocument();

    await settle(20 * 60_000); // polls carry on past midnight

    expect(screen.getByText(/^0 ta$/)).toBeInTheDocument();
  });

  it('keeps loading older pages until it is past the start of today', async () => {
    const at = (day: number, hour: number) => new Date(2026, 9, day, hour, 0, 0).toISOString();
    // 120 finished orders, the first 60 delivered today
    const finished = Array.from({ length: 120 }, (_, i) => {
      const when = i < 60 ? at(14, 14 - (i % 14)) : at(6, 12);
      return makeOrder({ id: i + 1, status: 'delivered', delivery_price: '1000.00', created_at: i < 60 ? at(14, 1 + (i % 12)) : when, updated_at: when });
    }).sort((a, b) => b.created_at.localeCompare(a.created_at));
    serveOrders(finished);

    renderWithClient(<CourierDashboard />);
    await settle(100);

    expect(callsOf('history').length).toBeGreaterThan(1);
    expect(callsOf('history').length).toBeLessThan(5); // not the whole history
    expect(screen.getByText(/^60 ta$/)).toBeInTheDocument();
  });
});
