import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeStore, makeUser } from '../test/factories';
import { makeStaffItem, makeStaffOrder, staffOrdersResponse } from '../test/staffOrdersApi';
import { callsTo, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import ManagerDashboard from './ManagerDashboard';

// The manager's orders on the staff board (GET /staff/orders): what a card says about the food, the kitchen and the wait,
// and the owner's branch filter

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const NOW = new Date('2026-10-14T10:00:00.000Z');
const minutesAgo = (minutes: number) => new Date(NOW.getTime() - minutes * 60_000).toISOString();

let calls: FakeCall[];
let orders: ReturnType<typeof makeStaffOrder>[];
let client: QueryClient;

const BRANCHES = [
  { id: 1, name: 'Chilonzor', phone: null, address: null, region_id: null, district_id: null, latitude: null, longitude: null, radius_km: 10, status: 'active', is_active: true, working_hours: { open: '09:00', close: '22:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' }, is_open_now: true, created_at: null, updated_at: null },
  { id: 2, name: 'Yunusobod', phone: null, address: null, region_id: null, district_id: null, latitude: null, longitude: null, radius_km: 10, status: 'active', is_active: true, working_hours: { open: '09:00', close: '22:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' }, is_open_now: true, created_at: null, updated_at: null },
];

function Where() {
  return <p data-testid="where">{useLocation().pathname}</p>;
}

function renderManager() {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Routes>
          <Route path="/" element={<ManagerDashboard />} />
          <Route path="/manager/orders/:uuid" element={<Where />} />
        </Routes>
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
  orders = [];
  ({ calls } = stubStaffApi((call) => {
    if (call.path === '/staff/orders') return staffOrdersResponse(orders, call.query);
    if (call.path === '/owner/branches') return success(BRANCHES);
    return success([]); // GET /couriers
  }));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  useAuthStore.getState().logout();
  vi.useRealTimers();
});

describe('ManagerDashboard: an order card', () => {
  beforeEach(() => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', name: 'Menejer Bobur' }), makeStore(), false);
  });

  it('names the modifiers of a line, its weight, the notes and the kitchen\'s progress', async () => {
    orders = [
      makeStaffOrder({
        uuid: 'o1',
        short_code: 'K100',
        status: 'preparing',
        notes: 'Piyozsiz, iltimos',
        items_total: 3,
        items_ready: 1,
        accepted_at: minutesAgo(7),
        items: [
          makeStaffItem({ id: 1, product_name: 'Burger', variant_name: 'Katta', quantity: 2, modifiers: [{ name: 'Pishloqli', group_name: 'Sous', quantity: 1 }, { name: 'Zaytun', group_name: "Qo'shimcha", quantity: 2 }], kitchen_status: 'ready' }),
          makeStaffItem({ id: 2, product_name: 'Qazi', variant_name: 'kg', quantity: 1.5, unit: 'kg', quantity_label: '1.5 kg' }),
          makeStaffItem({ id: 3, product_name: 'Cola', variant_name: '0.5 l', quantity: 1 }),
        ],
      }),
    ];

    renderManager();
    await settle();

    const card = screen.getByRole('article', { name: 'Buyurtma #K100' });
    expect(within(card).getByText('Burger x2')).toBeInTheDocument();
    expect(within(card).getByText('Pishloqli, Zaytun × 2')).toBeInTheDocument();
    expect(within(card).getByText(/^Qazi 1,5\skg$/)).toBeInTheDocument(); // the weight, with a decimal comma
    expect(within(card).getByText('Piyozsiz, iltimos')).toBeInTheDocument();
    expect(within(card).getByText('Oshxona: 1/3 tayyor')).toBeInTheDocument();
    expect(within(card).getAllByText('Tayyor')).toHaveLength(1); // only the finished line carries the mark (the status badge is "Tayyorlanmoqda")
  });

  it('says how long the order has been waiting, from its acceptance, and turns the colour as it gets late', async () => {
    orders = [
      makeStaffOrder({ uuid: 'fresh', short_code: 'W1', status: 'accepted', accepted_at: minutesAgo(5) }),
      makeStaffOrder({ uuid: 'late', short_code: 'W2', status: 'accepted', accepted_at: minutesAgo(75) }),
      makeStaffOrder({ uuid: 'new', short_code: 'W3', status: 'new', created_at: minutesAgo(2), accepted_at: null }),
    ];

    renderManager();
    await settle();

    expect(within(screen.getByRole('article', { name: 'Buyurtma #W1' })).getByText('Kutmoqda: 5 daq')).toBeInTheDocument();
    expect(within(screen.getByRole('article', { name: 'Buyurtma #W2' })).getByText('Kutmoqda: 1 soat 15 daq')).toHaveClass('bg-danger-soft');
    expect(within(screen.getByRole('article', { name: 'Buyurtma #W3' })).getByText('Kutmoqda: 2 daq')).toBeInTheDocument(); // not accepted yet: since it came
  });

  it('keeps the waiting time true on a screen left open', async () => {
    orders = [makeStaffOrder({ uuid: 'o1', short_code: 'W1', status: 'accepted', accepted_at: minutesAgo(5) })];
    renderManager();
    await settle();
    expect(screen.getByText('Kutmoqda: 5 daq')).toBeInTheDocument();

    await settle(10 * 60_000);

    expect(screen.getByText('Kutmoqda: 15 daq')).toBeInTheDocument();
  });

  it('does not say "waiting" for an order that is ready', async () => {
    orders = [makeStaffOrder({ uuid: 'o1', short_code: 'R1', status: 'ready', type: 'pickup', accepted_at: minutesAgo(30) })];
    renderManager();
    await settle();

    expect(screen.queryByText(/Kutmoqda:/)).not.toBeInTheDocument();
  });

  it('opens the order\'s own screen from the card', async () => {
    orders = [makeStaffOrder({ uuid: 'abc-123', short_code: 'D1', status: 'new' })];
    renderManager();
    await settle();

    fireEvent.click(screen.getByRole('button', { name: '#D1 buyurtmasi: batafsil' }));

    expect(screen.getByTestId('where')).toHaveTextContent('/manager/orders/abc-123');
  });

  it('shows the table of a hall order and no address', async () => {
    orders = [makeStaffOrder({ uuid: 'o1', short_code: 'H1', status: 'preparing', type: 'dine_in', hall_ref: 'A', table_ref: '12', customer: null, accepted_at: minutesAgo(1) })];
    renderManager();
    await settle();

    expect(screen.getByText(/Zalda · Zal A · Stol 12/)).toBeInTheDocument();
  });
});

describe('ManagerDashboard: the branch filter', () => {
  const branchCalls = () => callsTo(calls, '/staff/orders').filter((call) => call.query.scope === 'active');

  beforeEach(() => {
    orders = [
      makeStaffOrder({ uuid: 'c1', short_code: 'C1', branch_id: 1, branch_name: 'Chilonzor' }),
      makeStaffOrder({ uuid: 'y1', short_code: 'Y1', branch_id: 2, branch_name: 'Yunusobod' }),
    ];
  });

  it('lets an owner with several branches narrow the board to one of them', async () => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
    renderManager();
    await settle();

    expect(branchCalls().at(-1)!.query.branch_id).toBeUndefined();
    expect(screen.getByText('#C1')).toBeInTheDocument();
    expect(screen.getByText('#Y1')).toBeInTheDocument();
    // with several branches each card says which one it is
    expect(within(screen.getByRole('article', { name: 'Buyurtma #Y1' })).getByText('Yunusobod')).toBeInTheDocument();

    fireEvent.change(screen.getByRole('combobox', { name: "Filial bo'yicha" }), { target: { value: '2' } });
    await settle();

    expect(branchCalls().at(-1)!.query.branch_id).toBe('2');
    expect(screen.queryByText('#C1')).not.toBeInTheDocument();
    expect(screen.getByText('#Y1')).toBeInTheDocument();

    fireEvent.change(screen.getByRole('combobox', { name: "Filial bo'yicha" }), { target: { value: '' } });
    await settle();

    expect(screen.getByText('#C1')).toBeInTheDocument();
  });

  it('asks the archive for the same branch', async () => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
    orders = [makeStaffOrder({ uuid: 'y9', short_code: 'Y9', status: 'delivered', branch_id: 2 })];
    renderManager();
    await settle();
    fireEvent.change(screen.getByRole('combobox', { name: "Filial bo'yicha" }), { target: { value: '2' } });
    await settle();

    fireEvent.click(screen.getByRole('button', { name: /Yakunlangan/ }));
    await settle();

    const history = callsTo(calls, '/staff/orders').filter((call) => call.query.scope === 'history');
    expect(history[0]!.query.branch_id).toBe('2');
    expect(screen.getByText('#Y9')).toBeInTheDocument();
  });

  it('offers no filter to a manager, who has one branch, and does not even ask for the branches', async () => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', store_id: 1, branch_id: 1 }), makeStore(), false);
    renderManager();
    await settle();

    expect(screen.queryByRole('combobox', { name: "Filial bo'yicha" })).not.toBeInTheDocument();
    expect(callsTo(calls, '/owner/branches')).toHaveLength(0);
    expect(branchCalls().at(-1)!.query.branch_id).toBeUndefined(); // the backend binds a manager to their branch
  });

  it('offers no filter to an owner with a single branch', async () => {
    useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
    stubStaffApi((call) => {
      if (call.path === '/staff/orders') return staffOrdersResponse(orders, call.query);
      if (call.path === '/owner/branches') return success(BRANCHES.slice(0, 1));
      return success([]);
    });
    renderManager();
    await settle();

    expect(screen.queryByRole('combobox', { name: "Filial bo'yicha" })).not.toBeInTheDocument();
  });
});
