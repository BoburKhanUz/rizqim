import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { account, ok, ownerAnswer, signIn } from '../pos/testSupport';
import { AccountsView } from './AccountsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const meta = (total: number, page = 1, last = 1) => ({ current_page: page, last_page: last, per_page: 30, total });

const ACCOUNTS = [
  account(),
  account({ uuid: 'a2', name: 'Guruh B', phone: null, group_name: null, credit_limit: null, balance: '0.00', available_credit: null }),
  account({ uuid: 'a3', name: 'Bloklangan mijoz', credit_limit: '0.00', balance: '120000.00', available_credit: '0.00', is_blocked: true }),
];

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/pos/customer-accounts/debtors': () => ok({ total_debt: '470000.00', count: 2, accounts: [ACCOUNTS[0], ACCOUNTS[2]] }),
    'GET /api/v1/pos/customer-accounts': () => ok({ accounts: ACCOUNTS, meta: meta(3) }),
    ...over,
  });

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;
const listQuery = (calls: Request[]) => new URL(calls.filter((call) => new URL(call.url).pathname === '/api/v1/pos/customer-accounts').at(-1)!.url).searchParams;

describe('Nasiya hisoblari', () => {
  it('shows the total debt and the debtors count, and each account with its balance, limit and state', async () => {
    base();
    renderPage(<AccountsView />);
    expect(await screen.findByText("470 000 so'm")).toBeInTheDocument();
    const table = await screen.findByRole('table');
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]!).getByText('Karim Rahimov')).toBeInTheDocument();
    expect(within(rows[1]!).getByText("350 000 so'm")).toBeInTheDocument();
    expect(within(rows[1]!).getByText("1 000 000 so'm")).toBeInTheDocument();
    expect(within(rows[1]!).getByText('Qarzi bor')).toBeInTheDocument();
    expect(within(rows[2]!).getByText('Cheksiz')).toBeInTheDocument();
    expect(within(rows[2]!).getByText("Qarzi yo'q")).toBeInTheDocument();
    expect(within(rows[3]!).getByText("Nasiya yo'q")).toBeInTheDocument();
    expect(within(rows[3]!).getByText('Bloklangan')).toBeInTheDocument();
  });

  it('asks only for the debtors, or a search, from the server', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Qarzdorlar' }));
    await waitFor(() => expect(listQuery(calls).get('debtors')).toBe('1'));
    await typing.type(screen.getByLabelText('Ism, guruh yoki telefon'), 'Kar');
    await waitFor(() => expect(listQuery(calls).get('q')).toBe('Kar'));
    expect(listQuery(calls).get('page')).toBe('1');
  });

  it('says so when nothing matches and when there are no accounts at all', async () => {
    base({ 'GET /api/v1/pos/customer-accounts': () => ok({ accounts: [], meta: meta(0) }) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    expect(await screen.findByText("Hisob yo'q")).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Ism, guruh yoki telefon'), 'zzz');
    expect(await screen.findByText('Hech narsa topilmadi')).toBeInTheDocument();
  });

  it('pages through the accounts', async () => {
    const { calls } = base({ 'GET /api/v1/pos/customer-accounts': () => ok({ accounts: ACCOUNTS, meta: meta(70, 1, 3) }) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: /Keyingi/ }));
    await waitFor(() => expect(listQuery(calls).get('page')).toBe('2'));
  });

  it('changes the credit limit to a sum, and nothing else is sent', async () => {
    const { calls } = base({ 'PATCH /api/v1/pos/customer-accounts/a2': () => ok(account({ uuid: 'a2', name: 'Guruh B' })) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Guruh B: tahrirlash' }));
    await typing.selectOptions(screen.getByLabelText('Nasiya limiti'), 'amount');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Limit noldan katta son bo'lishi kerak.")).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Limit summasi'), '2 000 000');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Guruh B saqlandi.'));
    const request = last(calls, 'PATCH');
    expect(request.headers.get('Idempotency-Key')).toBeTruthy();
    expect(await request.json()).toEqual({ credit_limit: 2000000 });
  });

  it('blocks an account and sets "no credit" (0), not "no limit"', async () => {
    const { calls } = base({ 'PATCH /api/v1/pos/customer-accounts/a2': () => ok(account({ uuid: 'a2', name: 'Guruh B' })) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Guruh B: tahrirlash' }));
    await typing.selectOptions(screen.getByLabelText('Nasiya limiti'), 'zero');
    await typing.click(screen.getByRole('checkbox', { name: /Hisob bloklangan/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).toEqual({ credit_limit: 0, is_blocked: true });
  });

  it('closes the dialog without a request when nothing changed', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Karim Rahimov: tahrirlash' }));
    expect(screen.getByLabelText('Nomi', { exact: false })).toBeDefined();
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(calls.some((call) => call.method === 'PATCH')).toBe(false);
  });

  it('opens an account: a name is needed, the phone is made whole, "no limit" is null', async () => {
    const { calls } = base({ 'POST /api/v1/pos/customer-accounts': () => ok(account({ uuid: 'a9', name: 'Yangi' }), 201) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisob ochish' }));
    await typing.type(screen.getByLabelText('Telefon'), '90 1');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Ismini kiriting.')).toBeInTheDocument();
    expect(screen.getByText("Raqamni to'liq kiriting: 90 123 45 67")).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Ismi yoki guruh nomi'), 'Yangi');
    await typing.clear(screen.getByLabelText('Telefon'));
    await typing.type(screen.getByLabelText('Telefon'), '90 765 43 21');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Yangi hisobi ochildi.'));
    expect(await last(calls, 'POST').json()).toEqual({ name: 'Yangi', phone: '+998907654321', group_name: null, credit_limit: null, is_blocked: false, note: null });
  });

  it('shows the field message of a phone that is already in the store', async () => {
    base({ 'POST /api/v1/pos/customer-accounts': () => json({ success: false, message: 'Xato', errors: { phone: ["Bu telefon raqami bilan hisob bor."] } }, 422) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisob ochish' }));
    await typing.type(screen.getByLabelText('Ismi yoki guruh nomi'), 'Yangi');
    await typing.type(screen.getByLabelText('Telefon'), '90 765 43 21');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Bu telefon raqami bilan hisob bor.')).toBeInTheDocument();
  });

  it('is for managers of accounts: another role is told so', async () => {
    base({}, ['orders.view']);
    renderPage(<AccountsView />);
    expect(await screen.findByText("Nasiya hisoblarini ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Hisob ochish' })).not.toBeInTheDocument();
  });
});

describe('the statement', () => {
  const statement = {
    account: ACCOUNTS[0],
    from: '2026-09-22',
    to: '2026-10-22',
    opening_balance: '100000.00',
    closing_balance: '350000.00',
    charges_total: '400000.00',
    repayments_total: '150000.00',
    adjustments_total: '0.00',
    entries: [
      { uuid: 'e1', type: 'charge', type_label: 'Nasiya', amount: '400000.00', balance_after: '500000.00', bill_uuid: 'b', tender: null, card_type: null, transaction_id: null, fiscal_receipt_uuid: null, fiscal_status: null, branch_id: 1, user: { id: 5, name: 'Kassir Ali' }, approved_by: null, note: null, created_at: '2026-10-10T10:00:00+05:00' },
      { uuid: 'e2', type: 'repayment', type_label: "Qarz to'lovi", amount: '-150000.00', balance_after: '350000.00', bill_uuid: null, tender: 'cash', card_type: null, transaction_id: null, fiscal_receipt_uuid: null, fiscal_status: null, branch_id: 1, user: { id: 5, name: 'Kassir Ali' }, approved_by: null, note: 'Qisman', created_at: '2026-10-15T10:00:00+05:00' },
    ],
  };

  it('opens the statement of a period with the debt at both ends, the totals and the entries', async () => {
    const { calls } = base({ 'GET /api/v1/pos/customer-accounts/a0000000-0000-4000-8000-000000000001/statement': () => ok(statement) });
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: "Karim Rahimov: ko'chirma" }));
    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findByText("Nasiya")).toBeInTheDocument();
    expect(within(dialog).getByText("100 000 so'm")).toBeInTheDocument();
    expect(within(dialog).getByText('Yakuniy qarz')).toBeInTheDocument();
    expect(within(dialog).getByText("+400 000 so'm")).toBeInTheDocument();
    expect(within(dialog).getByText("-150 000 so'm")).toBeInTheDocument();
    expect(within(dialog).getByText('Kassir Ali · Qisman')).toBeInTheDocument();
    const request = new URL(calls.find((call) => new URL(call.url).pathname.endsWith('/statement'))!.url).searchParams;
    expect(request.get('from')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(request.get('to')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('does not ask for a period that ends before it starts', async () => {
    const { calls } = base({ 'GET /api/v1/pos/customer-accounts/a0000000-0000-4000-8000-000000000001/statement': () => ok(statement) });
    renderPage(<AccountsView />);
    const typing = userEvent.setup();
    await typing.click(await screen.findByRole('button', { name: "Karim Rahimov: ko'chirma" }));
    const dialog = await screen.findByRole('dialog');
    await within(dialog).findByText('Yakuniy qarz');
    const before = calls.length;
    fireEvent.change(within(dialog).getByLabelText('Boshlanish sanasi'), { target: { value: '2027-01-01' } });
    expect(await within(dialog).findByRole('alert')).toBeInTheDocument();
    expect(calls.length).toBe(before);
  });
});
