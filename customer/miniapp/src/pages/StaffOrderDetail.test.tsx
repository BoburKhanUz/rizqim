import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { resetMainButtonForTests } from '../hooks/useMainButton';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeStore, makeUser } from '../test/factories';
import { makeStaffItem, makeStaffOrder } from '../test/staffOrdersApi';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import StaffOrderDetail from './StaffOrderDetail';

// The order screen of GET /staff/orders/{uuid}

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const NOW = new Date('2026-10-14T10:00:00.000Z');
const at = (minutesAgo: number) => new Date(NOW.getTime() - minutesAgo * 60_000).toISOString();

const detail = (overrides: Parameters<typeof makeStaffOrder>[0] = {}) => ({
  ...makeStaffOrder({
    uuid: 'o-1',
    short_code: 'K100',
    status: 'preparing',
    notes: 'Qo‘ng‘iroq qilmang',
    subtotal: '85000.00',
    discount: '5000.00',
    delivery_price: '12000.00',
    total_price: '92000.00',
    payment_method: 'click',
    payment_status: 'paid',
    branch_name: 'Chilonzor',
    delivery: { address: 'Chilonzor 9, 14-uy', latitude: 41.27, longitude: 69.2, zone_name: 'Markaz' },
    courier: { id: 9, name: 'Jasur', phone: null },
    items_total: 2,
    items_ready: 1,
    created_at: at(30),
    accepted_at: at(25),
    preparing_at: at(20),
    items: [
      makeStaffItem({ id: 1, product_name: 'Burger', variant_name: 'Katta', quantity: 2, modifiers: [{ name: 'Pishloqli', group_name: 'Sous', quantity: 1, price_delta: '3000.00' }], kitchen_status: 'ready', total_price: '106000.00' }),
      makeStaffItem({ id: 2, product_name: 'Qazi', variant_name: 'kg', quantity: 1.5, unit: 'kg', total_price: '50000.00' }),
    ],
    ...overrides,
  }),
  status_history: [
    { from_status: null, status: 'new', created_at: at(30) },
    { from_status: 'new', status: 'accepted', created_at: at(25) },
    { from_status: 'accepted', status: 'preparing', created_at: at(15) },
  ],
});

let calls: FakeCall[];
let order: ReturnType<typeof detail>;
let orderAnswer: unknown;
let client: QueryClient;

function renderDetail(uuid = 'o-1') {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[`/manager/orders/${uuid}`]}>
        <Routes>
          <Route path="/manager/orders/:uuid" element={<StaffOrderDetail />} />
        </Routes>
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(NOW);
  client = new QueryClient();
  useLanguageStore.setState({ language: 'uz' });
  useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', branch_id: 1 }), makeStore(), false);
  order = detail();
  orderAnswer = undefined;
  ({ calls } = stubStaffApi((call) => {
    if (call.method === 'POST') return success({ uuid: 'o-1', status: 'ready' });
    if (call.path === '/staff/orders/o-1') return orderAnswer ?? success(order);
    return reply(404, { success: false, message: 'Buyurtma topilmadi.' });
  }));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  resetMainButtonForTests();
  useAuthStore.getState().logout();
  vi.useRealTimers();
});

describe('StaffOrderDetail', () => {
  it('shows the lines with their modifiers, weight and kitchen state', async () => {
    renderDetail();
    await settle();

    expect(screen.getByRole('heading', { name: 'Buyurtma #K100' })).toBeInTheDocument();
    const lines = screen.getAllByRole('listitem').filter((item) => item.hasAttribute('data-kitchen-status'));
    expect(lines).toHaveLength(2);
    expect(within(lines[0]!).getByText('Burger x2')).toBeInTheDocument();
    expect(within(lines[0]!).getByText('Pishloqli')).toBeInTheDocument();
    expect(within(lines[0]!).getByText('Tayyor')).toBeInTheDocument();
    expect(within(lines[0]!).getByText(/^106 000 so'm$/)).toBeInTheDocument();
    expect(within(lines[1]!).getByText(/^Qazi 1,5\skg$/)).toBeInTheDocument();
    expect(within(lines[1]!).queryByText('Tayyor')).not.toBeInTheDocument();
    expect(screen.getByText('Oshxona: 1/2 tayyor')).toBeInTheDocument();
  });

  it('shows the customer, the address, the courier, the notes and the sums the API sent', async () => {
    renderDetail();
    await settle();

    expect(screen.getByText('Mijoz Test')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Qo'ng'iroq qilish: Mijoz Test/ })).toHaveAttribute('href', 'tel:+998909998877');
    expect(screen.getByText('Chilonzor 9, 14-uy')).toBeInTheDocument();
    expect(screen.getByText('Hudud: Markaz')).toBeInTheDocument();
    expect(screen.getByText('Jasur')).toBeInTheDocument();
    expect(screen.getByText('Qo‘ng‘iroq qilmang')).toBeInTheDocument();
    expect(screen.getByText(/^−5 000 so'm$/)).toBeInTheDocument();
    expect(screen.getByText(/^12 000 so'm$/)).toBeInTheDocument();
    expect(screen.getByText(/^92 000 so'm$/)).toBeInTheDocument();
    expect(screen.queryByText('Xizmat haqi')).not.toBeInTheDocument(); // zero: not a line
  });

  it('lists the status history and how long each stage took', async () => {
    renderDetail();
    await settle();

    const history = screen.getByRole('heading', { name: 'Holatlar tarixi' }).closest('section')!;
    expect(within(history).getAllByRole('listitem').map((item) => item.textContent?.split(/\d/)[0])).toEqual(['Yangi', 'Qabul qilindi', 'Tayyorlanmoqda']);
    expect(within(history).getByText('Qabul qilish')).toBeInTheDocument();
    expect(within(history).getByText('5m 0s')).toBeInTheDocument(); // new -> accepted
    expect(within(history).getByText('10m 0s')).toBeInTheDocument(); // accepted -> preparing
  });

  it('hides the customer for a role that may not see them (the cook)', async () => {
    order = detail({ customer: null, delivery: null, courier: null });
    renderDetail();
    await settle();

    expect(screen.getByText('Mijoz ma\'lumoti bu rol uchun yashirilgan.')).toBeInTheDocument();
    expect(screen.queryByText('Mijoz Test')).not.toBeInTheDocument();
  });

  it('puts the next step on the main button, and sends it to the staff status endpoint', async () => {
    order = detail({ status: 'preparing' });
    renderDetail();
    await settle();

    fireEvent.click(screen.getByRole('button', { name: "Tayyor bo'ldi" }));
    await settle();

    const [post] = callsTo(calls, '/staff/orders/o-1/status', 'POST');
    expect(post!.body).toEqual({ status: 'ready' });
  });

  it('has no main button where the next step needs a courier or a reason', async () => {
    order = detail({ status: 'ready', type: 'delivery' });
    renderDetail();
    await settle();

    expect(screen.queryByRole('button', { name: /Kuryerni tayinlash/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Bekor qilish/ })).not.toBeInTheDocument();
  });

  it('has no main button for a finished order', async () => {
    order = detail({ status: 'delivered', closed_at: at(1) });
    renderDetail();
    await settle();

    expect(screen.queryByRole('button', { name: /Qabul qilish|Tayyor/ })).not.toBeInTheDocument();
  });

  it('says so when the order is not there (404)', async () => {
    orderAnswer = reply(404, { success: false, message: 'Buyurtma topilmadi.' });
    renderDetail();
    await settle();

    expect(screen.getByText('Buyurtma topilmadi')).toBeInTheDocument();
  });

  it('offers a retry when the order cannot be loaded', async () => {
    orderAnswer = reply(502);
    renderDetail();
    await settle(5000);

    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  it('keeps itself fresh by polling', async () => {
    renderDetail();
    await settle();
    const first = callsTo(calls, '/staff/orders/o-1').length;

    await settle(8_000 + 100);

    expect(callsTo(calls, '/staff/orders/o-1').length).toBeGreaterThan(first);
  });
});
