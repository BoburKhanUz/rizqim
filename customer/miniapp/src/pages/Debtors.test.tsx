import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeStore, makeUser } from '../test/factories';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import Debtors from './Debtors';

// "Qarzdorlar" on the phone, on a fake API: the debtors list with balances and the total

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const flush = (turns = 10) =>
  act(async () => {
    for (let turn = 0; turn < turns; turn++) await new Promise((resolve) => setTimeout(resolve, 0));
  });

// The first poll that fails is retried once after a second (the polling policy of lib/polling.ts)
const afterRetry = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 1300)); });

const account = (over: Record<string, unknown> = {}) => ({
  uuid: 'a1',
  name: 'Karim Rahimov',
  phone: '+998909998877',
  group_name: 'Kompaniya A',
  branch_id: null,
  credit_limit: '1000000.00',
  balance: '350000.00',
  available_credit: '650000.00',
  is_blocked: false,
  note: null,
  created_at: null,
  updated_at: null,
  ...over,
});

let calls: FakeCall[];
let answer: unknown;
let client: QueryClient;

beforeEach(() => {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  useLanguageStore.setState({ language: 'uz' });
  answer = success({
    total_debt: '470000.00',
    count: 2,
    accounts: [account(), account({ uuid: 'a2', name: 'Bloklangan mijoz', phone: null, group_name: null, credit_limit: '0.00', balance: '120000.00', is_blocked: true })],
  });
  ({ calls } = stubStaffApi(() => answer));
  vi.spyOn(console, 'error').mockImplementation(() => {});
  useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
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
        <Debtors />
      </MemoryRouter>
    </QueryClientProvider>,
  );

describe('Debtors', () => {
  it('shows the total debt and each debtor with the balance, the limit and a number to call', async () => {
    renderPage();
    await flush();
    expect(screen.getByText("470 000 so'm")).toBeInTheDocument();
    expect(screen.getByText('Qarzdorlar: 2')).toBeInTheDocument();
    expect(callsTo(calls, '/pos/customer-accounts/debtors')).toHaveLength(1);

    const [first, second] = screen.getAllByRole('listitem');
    expect(within(first!).getByText('Karim Rahimov')).toBeInTheDocument();
    expect(within(first!).getByText('Kompaniya A')).toBeInTheDocument();
    expect(within(first!).getByText("350 000 so'm")).toBeInTheDocument();
    expect(within(first!).getByText("Limit: 1 000 000 so'm")).toBeInTheDocument();
    expect(within(first!).getByRole('link', { name: "Karim Rahimov: qo'ng'iroq qilish" })).toHaveAttribute('href', 'tel:+998909998877');
    expect(within(second!).getByText('Bloklangan')).toBeInTheDocument();
    expect(within(second!).getByText("Nasiya yo'q")).toBeInTheDocument();
    expect(within(second!).queryByRole('link')).not.toBeInTheDocument();
  });

  it('says there are no debtors', async () => {
    answer = success({ total_debt: '0.00', count: 0, accounts: [] });
    renderPage();
    await flush();
    expect(screen.getByText("Qarzdor yo'q")).toBeInTheDocument();
    expect(screen.getByText("0 so'm")).toBeInTheDocument();
  });

  it('shows no limit for an account without one', async () => {
    answer = success({ total_debt: '10.00', count: 1, accounts: [account({ credit_limit: null })] });
    renderPage();
    await flush();
    expect(screen.getByText('Limit: cheksiz')).toBeInTheDocument();
  });

  it("shows the server's words for a 403", async () => {
    answer = reply(403, { success: false, message: 'Ruxsat etilmagan' });
    renderPage();
    await flush();
    await afterRetry();
    expect(screen.getByText('Ruxsat etilmagan')).toBeInTheDocument();
  });

  it('offers a retry when the server is down', async () => {
    answer = reply(502, {});
    renderPage();
    await flush();
    await afterRetry();
    expect(screen.getByRole('button', { name: /Qayta urinish/i })).toBeInTheDocument();
  });

  it('is in Russian for a Russian app', async () => {
    useLanguageStore.setState({ language: 'ru' });
    renderPage();
    await flush();
    expect(screen.getByText('Общий долг')).toBeInTheDocument();
    expect(screen.getByText('Должников: 2')).toBeInTheDocument();
    expect(screen.getByText('Лимит: 1 000 000 сум')).toBeInTheDocument();
  });
});
