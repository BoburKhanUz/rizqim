import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { CorporateMember, CorporateStatement } from '@rizqim/staff';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { account, ok, ownerAnswer, signIn } from '../pos/testSupport';
import { AccountsView } from './AccountsView';
import { CorporateAccountView } from './CorporateAccountView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
const saveBlob = vi.hoisted(() => vi.fn());
vi.mock('../reports/download', () => ({ saveBlob }));

beforeEach(signIn);
afterEach(() => setSession(null));

const UUID = 'c0000000-0000-4000-8000-000000000001';
const ROOT = `/api/v1/pos/customer-accounts/${UUID}`;

const company = (over: Record<string, unknown> = {}) =>
  account({
    uuid: UUID,
    name: 'Navoiy zavodi',
    phone: null,
    group_name: null,
    credit_limit: null,
    balance: '196000.00',
    available_credit: null,
    kind: 'corporate',
    kind_label: 'Korxona',
    company_name: '"Navoiy Metall" MChJ',
    inn: '301234567',
    contact_name: 'Dilshod Karimov',
    contact_phone: '+998901234567',
    invoice_email: 'buxgalter@navoiy.uz',
    monthly_limit: '5000000.00',
    charge_approval: false,
    talon_mode: 'daily_limit',
    members_count: 2,
    ...over,
  });

const member = (over: Partial<CorporateMember> = {}): CorporateMember => ({
  uuid: 'm1',
  name: 'Aziz Rahimov',
  phone: '+998901112233',
  code: '5550001111',
  daily_limit: '60000.00',
  meals_per_day: null,
  is_active: true,
  note: null,
  created_at: null,
  updated_at: null,
  ...over,
});

const MEMBERS = [
  member(),
  member({
    uuid: 'm2',
    name: 'Bobur',
    phone: null,
    code: 'NAV-0002',
    daily_limit: null,
    meals_per_day: 1,
  }),
  member({
    uuid: 'm3',
    name: 'Eski xodim',
    code: 'NAV-0003',
    is_active: false,
  }),
];

const STATEMENT: CorporateStatement = {
  account: {
    uuid: UUID,
    name: 'Navoiy zavodi',
    company_name: '"Navoiy Metall" MChJ',
    inn: '301234567',
    contact_name: 'Dilshod Karimov',
    contact_phone: '+998901234567',
    invoice_email: 'buxgalter@navoiy.uz',
    talon_mode: 'daily_limit',
  },
  month: '2026-11',
  from: '2026-11-01',
  to: '2026-11-30',
  days: [],
  rows: [
    {
      member: {
        uuid: 'm1',
        name: 'Aziz Rahimov',
        code: '5550001111',
        is_active: true,
      },
      cells: [{ date: '2026-11-02', amount: '42000.00', meals: 2 }],
      amount: '42000.00',
      meals: 2,
    },
    {
      member: { uuid: 'm2', name: 'Bobur', code: 'NAV-0002', is_active: true },
      cells: [{ date: '2026-11-03', amount: '28000.00', meals: 1 }],
      amount: '28000.00',
      meals: 1,
    },
    {
      member: null,
      cells: [{ date: '2026-11-03', amount: '15000.00', meals: 1 }],
      amount: '15000.00',
      meals: 1,
    },
  ],
  day_totals: [
    { date: '2026-11-02', amount: '42000.00', meals: 2 },
    { date: '2026-11-03', amount: '43000.00', meals: 2 },
  ],
  total: '85000.00',
  meals: 4,
  repaid_total: '50000.00',
  monthly_limit: '5000000.00',
  balance: '196000.00',
};

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    [`GET ${ROOT}`]: () => ok({ account: company(), entries: [] }),
    [`GET ${ROOT}/members`]: () => ok({ members: MEMBERS, active_count: 2 }),
    [`GET ${ROOT}/corporate-statement`]: () => ok(STATEMENT),
    ...over,
  });

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('Korxona hisobi', () => {
  it('shows the company, its limits and its members with their talon codes', async () => {
    base();
    renderPage(<CorporateAccountView uuid={UUID} />);
    expect(await screen.findByRole('heading', { name: 'Navoiy zavodi' })).toBeInTheDocument();
    expect(screen.getByText('"Navoiy Metall" MChJ · STIR 301234567')).toBeInTheDocument();
    expect(screen.getByText("196 000 so'm")).toBeInTheDocument();
    expect(screen.getByText("5 000 000 so'm")).toBeInTheDocument();
    expect(screen.getByText("Tasdiqsiz (kassir o'zi)")).toBeInTheDocument();
    const table = await screen.findByRole('table');
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]!).getByText('5550001111')).toBeInTheDocument();
    expect(within(rows[1]!).getByText("60 000 so'm")).toBeInTheDocument();
    expect(within(rows[2]!).getByText('Cheksiz')).toBeInTheDocument();
    expect(within(rows[3]!).getByText("O'chirilgan")).toBeInTheDocument();
  });

  it('adds a member (the code is generated when empty) and turns a talon off', async () => {
    const { calls } = base({
      [`POST ${ROOT}/members`]: () =>
        ok(
          member({
            uuid: 'm9',
            name: 'Dilnoza',
            code: '1234567890',
            daily_limit: '50000.00',
          }),
          201,
        ),
      [`PATCH ${ROOT}/members/m1`]: () => ok(member({ is_active: false })),
    });
    const typing = userEvent.setup();
    renderPage(<CorporateAccountView uuid={UUID} />);
    await typing.click(await screen.findByRole('button', { name: "Xodim qo'shish" }));
    const dialog = await screen.findByRole('dialog');
    await typing.type(within(dialog).getByLabelText('Talon kodi', { exact: false }), 'a b');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText('Xodimning ismini kiriting.')).toBeInTheDocument();
    expect(within(dialog).getByText('Talon kodi 4–32 ta lotin harfi, raqam yoki "-" bo\'lsin.')).toBeInTheDocument();
    await typing.clear(within(dialog).getByLabelText('Talon kodi', { exact: false }));
    await typing.type(within(dialog).getByLabelText('Ismi va familiyasi'), 'Dilnoza');
    await typing.type(within(dialog).getByLabelText('Kunlik limit', { exact: false }), '50 000');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Dilnoza qo'shildi. Talon kodi: 1234567890"));
    const post = last(calls, 'POST');
    expect(post.headers.get('Idempotency-Key')).toBeTruthy();
    expect(await post.json()).toEqual({
      name: 'Dilnoza',
      phone: null,
      code: null,
      daily_limit: '50000',
      meals_per_day: null,
      note: null,
    });

    await typing.click(screen.getByRole('button', { name: "Aziz Rahimov: talonni o'chirish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).toEqual({ is_active: false });
  });

  it('pastes a list: the bad lines are shown and nobody is added', async () => {
    const { calls } = base({
      [`POST ${ROOT}/members/bulk`]: () =>
        json(
          {
            success: false,
            message: "Ro'yxatda xato qatorlar bor: hech kim qo'shilmadi.",
            data: {
              reason: 'bulk_invalid',
              lines: [
                {
                  line: 2,
                  reason: 'phone_invalid',
                  message: "Telefon raqami noto'g'ri.",
                },
              ],
            },
          },
          422,
        ),
    });
    const typing = userEvent.setup();
    renderPage(<CorporateAccountView uuid={UUID} />);
    await typing.click(await screen.findByRole('button', { name: "Ro'yxatdan qo'shish" }));
    const dialog = await screen.findByRole('dialog');
    await typing.click(within(dialog).getByRole('button', { name: "Qo'shish" }));
    expect(await within(dialog).findByText('Kamida bitta qator yozing.')).toBeInTheDocument();
    await typing.type(within(dialog).getByLabelText("Ro'yxat", { exact: false }), 'Dilnoza;90 222 33 44{enter}Sardor;12');
    await typing.type(within(dialog).getByLabelText('Kuniga ovqat soni'), '1');
    await typing.click(within(dialog).getByRole('button', { name: "Qo'shish" }));
    expect(await within(dialog).findByText("2-qator: Telefon raqami noto'g'ri.")).toBeInTheDocument();
    expect(await last(calls, 'POST').json()).toEqual({
      lines: 'Dilnoza;90 222 33 44\nSardor;12',
      daily_limit: null,
      meals_per_day: 1,
    });
  });

  it('shows the monthly statement by member and day, and downloads the Excel file', async () => {
    const { calls } = base({
      [`GET ${ROOT}/corporate-statement/export`]: () =>
        new Response('xlsx', {
          status: 200,
          headers: {
            'Content-Disposition': 'attachment; filename="korxona-navoiy-zavodi-2026-11.xlsx"',
          },
        }),
    });
    const typing = userEvent.setup();
    renderPage(<CorporateAccountView uuid={UUID} />);
    await typing.click(await screen.findByRole('tab', { name: 'Oylik hisobot' }));
    const invoice = await screen.findByRole('region', {
      name: /Hisob-faktura/,
    });
    expect(within(invoice).getByText('301234567')).toBeInTheDocument();
    expect(within(invoice).getByText("85 000 so'm")).toBeInTheDocument();
    expect(within(invoice).getByText("50 000 so'm")).toBeInTheDocument();
    const table = screen.getByRole('table');
    const rows = within(table).getAllByRole('row');
    expect(
      within(rows[0]!)
        .getAllByRole('columnheader')
        .map((cell) => cell.textContent?.replace(/\s/g, ' ')),
    ).toEqual(['Xodim', '2', '3', 'Summa', 'Ovqatlar soni']);
    expect(
      within(rows[1]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.replace(/\s/g, ' ')),
    ).toEqual(['Aziz Rahimov', "42 000 so'm", '—', "42 000 so'm", '2']);
    expect(within(rows[3]!).getByText('Xodimsiz (korxona)')).toBeInTheDocument();
    expect(
      within(rows[4]!)
        .getAllByRole('cell')
        .map((cell) => cell.textContent?.replace(/\s/g, ' ')),
    ).toEqual(['Jami', "42 000 so'm", "43 000 so'm", "85 000 so'm", '4']);

    await typing.click(screen.getByRole('button', { name: 'Excel yuklab olish' }));
    expect(await screen.findByText('korxona-navoiy-zavodi-2026-11.xlsx yuklab olindi.')).toBeInTheDocument();
    expect(saveBlob.mock.calls[0]![1]).toBe('korxona-navoiy-zavodi-2026-11.xlsx');
    const exportCall = calls.find((call) => new URL(call.url).pathname.endsWith('/export'))!;
    expect(new URL(exportCall.url).searchParams.get('month')).toMatch(/^\d{4}-\d{2}$/);
  });

  it('a nasiya account has no members page; without reports.view the statement says so', async () => {
    base({
      [`GET ${ROOT}`]: () => ok({ account: company({ kind: 'customer' }), entries: [] }),
    });
    renderPage(<CorporateAccountView uuid={UUID} />);
    expect(await screen.findByText('Bu nasiya hisobi: xodimlar va oylik hisobot faqat korxona hisobida.')).toBeInTheDocument();
  });

  it('without reports.view the statement tab says so', async () => {
    base({}, ['customer_accounts.manage']);
    const typing = userEvent.setup();
    renderPage(<CorporateAccountView uuid={UUID} />);
    await typing.click(await screen.findByRole('tab', { name: 'Oylik hisobot' }));
    expect(await screen.findByText(/"Hisobotlar" ruxsati kerak/)).toBeInTheDocument();
  });
});

describe('the accounts page: kinds', () => {
  const list = (calls: Request[]) => new URL(calls.filter((call) => new URL(call.url).pathname === '/api/v1/pos/customer-accounts').at(-1)!.url).searchParams;
  const accounts = () =>
    mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(),
      'GET /api/v1/pos/customer-accounts/debtors': () => ok({ total_debt: '0.00', count: 0, accounts: [] }),
      'GET /api/v1/pos/customer-accounts': () =>
        ok({
          accounts: [account(), company()],
          meta: { current_page: 1, last_page: 1, per_page: 30, total: 2 },
        }),
      'POST /api/v1/pos/customer-accounts': () => ok(company({ uuid: 'c9', name: 'Yangi zavod' }), 201),
    });

  it('filters by kind and links a company to its page', async () => {
    const { calls } = accounts();
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    expect(await screen.findByText('2 xodim')).toBeInTheDocument();
    expect(
      screen.getByRole('link', {
        name: 'Navoiy zavodi: xodimlar va oylik hisobot',
      }),
    ).toHaveAttribute('href', `/accounts/${UUID}`);
    await typing.click(within(screen.getByRole('group', { name: 'Hisob turi' })).getByRole('button', { name: 'Korxona' }));
    await waitFor(() => expect(list(calls).get('kind')).toBe('corporate'));
  });

  it('opens a company account with its details', async () => {
    const { calls } = accounts();
    const typing = userEvent.setup();
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisob ochish' }));
    const dialog = await screen.findByRole('dialog');
    await typing.selectOptions(within(dialog).getByLabelText('Hisob turi', { exact: false }), 'corporate');
    await typing.type(within(dialog).getByLabelText('Ismi yoki guruh nomi'), 'Yangi zavod');
    await typing.type(within(dialog).getByLabelText('STIR', { exact: false }), '1234');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText("STIR 9 yoki 14 ta raqam bo'lishi kerak.")).toBeInTheDocument();
    await typing.clear(within(dialog).getByLabelText('STIR', { exact: false }));
    await typing.type(within(dialog).getByLabelText('STIR', { exact: false }), '301234567');
    await typing.type(within(dialog).getByLabelText('Oylik limit', { exact: false }), '3 000 000');
    await typing.selectOptions(within(dialog).getByLabelText('Talon tartibi', { exact: false }), 'subscription');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await last(calls, 'POST').json()).toEqual({
      name: 'Yangi zavod',
      phone: null,
      group_name: null,
      credit_limit: null,
      is_blocked: false,
      note: null,
      kind: 'corporate',
      company_name: null,
      inn: '301234567',
      contact_name: null,
      contact_phone: null,
      invoice_email: null,
      monthly_limit: 3000000,
      talon_mode: 'subscription',
      charge_approval: false,
    });
  });
});
