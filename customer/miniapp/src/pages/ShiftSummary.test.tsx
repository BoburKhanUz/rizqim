import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeStore, makeUser } from '../test/factories';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import ShiftSummary from './ShiftSummary';

// "Smena xulosasi" on the phone of an owner or manager, on a fake API: the branch's open shifts with their X and the last Z

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const flush = (turns = 10) =>
  act(async () => {
    for (let turn = 0; turn < turns; turn++) await new Promise((resolve) => setTimeout(resolve, 0));
  });

// The first poll that fails is retried once after a second (the polling policy of lib/polling.ts)
const afterRetry = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 1300)); });

const branch = (id: number, name: string) => ({ id, name });
const shift = (over: Record<string, unknown> = {}) => ({
  uuid: 's-open',
  status: 'open',
  branch_id: 1,
  device: { uuid: 'd1', name: 'Kassa 1' },
  business_date: '2026-10-22',
  opened_at: '2026-10-22T04:00:00+00:00',
  opened_by: { id: 5, name: 'Kassir Ali' },
  opening_cash: '500000.00',
  closed_at: null,
  closed_by: null,
  expected_cash: null,
  counted_cash: null,
  counted_breakdown: null,
  difference: null,
  z_number: null,
  force_closed: false,
  close_note: null,
  ...over,
});
const report = (over: Record<string, unknown> = {}) => ({
  sales: { bills: 9, net: '2100000.00' },
  by_tender: [
    { tender: 'cash', count: 5, amount: '900000.00' },
    { tender: 'card', count: 3, amount: '1000000.00' },
    { tender: 'qr', count: 1, amount: '200000.00' },
    { tender: 'prepayment', count: 0, amount: '0.00' },
    { tender: 'account', count: 0, amount: '0.00' },
  ],
  discounts: { count: 1, amount: '50000.00' },
  voids: { count: 0, amount: '0.00' },
  refunds: { count: 0, amount: '0.00' },
  deposits: { taken: { count: 1, amount: '300000.00', cash: '0.00' } },
  credit: { repayments: { count: 0, amount: '0.00', cash: '0.00' } },
  cash: { expected: '1400000.00' },
  ...over,
});

let calls: FakeCall[];
let open: ReturnType<typeof shift>[];
let closed: ReturnType<typeof shift>[];
let client: QueryClient;
let permission: 'ok' | 'forbidden' | 'down';

beforeEach(() => {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  useLanguageStore.setState({ language: 'uz' });
  permission = 'ok';
  open = [shift()];
  closed = [shift({ uuid: 's-closed', status: 'closed', z_number: 12, business_date: '2026-10-21', closed_at: '2026-10-21T19:00:00+00:00', closed_by: { id: 5, name: 'Kassir Ali' }, expected_cash: '1750000.00', counted_cash: '1740000.00', difference: '-10000.00' })];
  ({ calls } = stubStaffApi((call) => {
    if (call.path === '/owner/branches') return success([branch(1, 'Chilonzor'), branch(2, 'Yunusobod')]);
    if (call.path === '/pos/shifts') {
      if (permission === 'forbidden') return reply(403, { success: false, message: 'Ruxsat etilmagan' });
      if (permission === 'down') return reply(502, {});
      return success(call.query.status === 'open' ? open : closed);
    }
    if (call.path.endsWith('/x-report')) return success({ shift: open.find((item) => item.uuid === call.path.split('/')[3]), report: report() });
    if (call.path === '/pos/shifts/s-closed') return success({ ...closed[0], summary: report({ sales: { bills: 14, net: '3150000.00' } }) });
    return reply(404, { success: false, message: "yo'q" });
  }));
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  useAuthStore.getState().logout();
  vi.restoreAllMocks();
});

const renderPage = () =>
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ShiftSummary />
      </MemoryRouter>
    </QueryClientProvider>,
  );

const owner = () => useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
const manager = () => useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', store_id: 1, branch_id: 1 }), makeStore(), false);
const shiftCalls = () => callsTo(calls, '/pos/shifts');

describe('ShiftSummary', () => {
  it('shows the open shift with what should be in the till and its figures, and the last Z with the difference in words', async () => {
    owner();
    renderPage();
    await flush();

    const card = screen.getByRole('group', { name: 'Ochiq smena: Kassa 1' });
    expect(within(card).getByText("1 400 000 so'm")).toBeInTheDocument();
    expect(within(card).getByText(/^Ochdi: Kassir Ali, 22-okt/)).toBeInTheDocument();
    expect(within(card).getByText("2 100 000 so'm")).toBeInTheDocument();
    expect(within(card).getByText("900 000 so'm")).toBeInTheDocument();
    expect(within(card).getByText("300 000 so'm")).toBeInTheDocument();

    const z = screen.getByRole('group', { name: 'Oxirgi Z hisobot' });
    expect(within(z).getByText('Z № 12')).toBeInTheDocument();
    expect(within(z).getByText("3 150 000 so'm")).toBeInTheDocument();
    expect(within(z).getByText("1 740 000 so'm")).toBeInTheDocument();
    expect(within(z).getByText("Kam: 10 000 so'm")).toBeInTheDocument();
  });

  it("reads the shifts of the first branch for an owner, and of another one they pick", async () => {
    owner();
    renderPage();
    await flush();
    expect(shiftCalls().every((call) => call.query.branch_id === '1')).toBe(true);
    expect(shiftCalls().map((call) => call.query.status).sort()).toEqual(['closed', 'open']);

    fireEvent.change(screen.getByRole('combobox', { name: 'Filial' }), { target: { value: '2' } });
    await flush();
    expect(shiftCalls().at(-1)!.query.branch_id).toBe('2');
  });

  it("asks a manager for no branch: the backend binds them to their own", async () => {
    manager();
    renderPage();
    await flush();
    expect(shiftCalls().length).toBeGreaterThan(0);
    expect(shiftCalls().every((call) => call.query.branch_id === undefined)).toBe(true);
    expect(screen.queryByRole('combobox', { name: 'Filial' })).not.toBeInTheDocument();
    expect(callsTo(calls, '/owner/branches')).toHaveLength(0);
  });

  it('says there is no open shift and no Z yet', async () => {
    open = [];
    closed = [];
    owner();
    renderPage();
    await flush();
    expect(screen.getByText("Hozir ochiq smena yo'q")).toBeInTheDocument();
    expect(screen.getByText("Yopilgan smena hali yo'q")).toBeInTheDocument();
  });

  it('shows several open tills, each with its own card', async () => {
    open = [shift(), shift({ uuid: 's-open-2', device: { uuid: 'd2', name: 'Kassa 2' } })];
    owner();
    renderPage();
    await flush();
    expect(screen.getByRole('group', { name: 'Ochiq smena: Kassa 1' })).toBeInTheDocument();
    expect(screen.getByRole('group', { name: 'Ochiq smena: Kassa 2' })).toBeInTheDocument();
  });

  it("shows the server's words for a 403 and a retry for a server that is down", async () => {
    permission = 'forbidden';
    owner();
    const { unmount } = renderPage();
    await flush();
    await afterRetry();
    expect(screen.getByText('Ruxsat etilmagan')).toBeInTheDocument();
    unmount();
    client.clear();
    permission = 'down';
    renderPage();
    await flush();
    await afterRetry();
    expect(screen.getByRole('button', { name: /Qayta urinish/i })).toBeInTheDocument();
  });

  it('speaks Russian when the app does', async () => {
    useLanguageStore.setState({ language: 'ru' });
    owner();
    renderPage();
    await flush();
    expect(screen.getByRole('group', { name: 'Открытая смена: Kassa 1' })).toBeInTheDocument();
    expect(screen.getByText('Недостача: 10 000 сум')).toBeInTheDocument();
  });
});
