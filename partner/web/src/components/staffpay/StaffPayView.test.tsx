import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, ok, permissionsAnswer } from '../menu/menuFixtures';
import { describeComponent, formatPercent, parseAmountInput } from './describe';
import { StaffPayView } from './StaffPayView';
import { createTranslator } from '@/i18n';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const option = (value: string, label: string) => ({ value, label });

const rulesPage = (over: Record<string, unknown> = {}) => ({
  branch: { id: 1, name: 'Chilonzor' },
  period: 'month',
  period_label: 'Oylik',
  periods: [option('day', 'Kunlik'), option('week', 'Haftalik'), option('half_month', 'Yarim oylik'), option('month', 'Oylik')],
  component_types: [],
  guarantee_modes: [option('max', 'Garant yoki foiz (qaysi katta)'), option('plus', 'Garant + foiz')],
  guarantee_per: [option('shift', 'Har smena uchun'), option('period', 'Butun davr uchun')],
  percent_bases: [option('sales', 'Sotuvdan'), option('service_charge', 'Xizmat haqidan')],
  event_types: [option('ziyofat', 'Ziyofat'), option('tugilgan_kun', "Tug'ilgan kun"), option('other', 'Boshqa')],
  roles: [option('manager', 'Menejer'), option('waiter', 'Ofitsiant'), option('cook', 'Oshpaz')],
  staff: [{ id: 7, name: 'Aziz', role: 'waiter', role_label: 'Ofitsiant', branch_id: 1 }],
  rules: [
    {
      uuid: 'r-1',
      branch_id: 1,
      user_id: null,
      user_name: null,
      role: 'waiter',
      role_label: 'Ofitsiant',
      effective_from: '2026-11-01',
      components: [
        { type: 'shift_rate', label: 'Smena stavkasi', amount: '100000.00' },
        { type: 'guarantee', label: 'Garant + foiz', amount: '150000.00', per: 'shift', mode: 'max', percent: '10.00', base: 'sales' },
      ],
      note: null,
      updated_at: null,
    },
  ],
  ...over,
});

const line = (over: Record<string, unknown> = {}) => ({
  uuid: 'l-1',
  user_id: 7,
  user_name: 'Aziz',
  role: 'waiter',
  role_label: 'Ofitsiant',
  metrics: { shifts: 2, minutes: 600, bills: 3, sales: '850000.00', service_charge: '85000.00', events: 0, tips: '30000.00', uncounted_sessions: 0, rules: ['r-1'], own_rule: false },
  components: [{ type: 'shift_rate', label: 'Smena stavkasi', rule_uuid: 'r-1', days: 30, quantity: '2', rate: '100000.00', base: null, amount: '200000.00', detail: "2 smena × 100 000 so'm" }],
  gross: '285000.00',
  advances: '50000.00',
  adjustment: '0.00',
  adjustment_reason: null,
  payable: '235000.00',
  paid: false,
  paid_at: null,
  paid_method: null,
  paid_note: null,
  cash_movement_id: null,
  ...over,
});

const statement = (over: Record<string, unknown> = {}) => ({
  uuid: 's-1',
  branch_id: 1,
  period: 'month',
  period_label: 'Oylik',
  period_from: '2026-11-01',
  period_to: '2026-11-30',
  status: 'closed',
  status_label: 'Yopilgan',
  gross_total: '285000.00',
  payable_total: '235000.00',
  paid_total: '0.00',
  lines_count: 1,
  warnings: [{ type: 'no_rule', user_id: 3, name: 'Kassir Dilnoza' }],
  computed_at: null,
  closed_at: null,
  version: 2,
  created_at: null,
  lines: [line()],
  open_shifts: [{ uuid: 'sh-1', device_name: 'Kassa 1', business_date: '2026-12-02', opened_at: '2026-12-02T04:00:00Z', expected_cash: '1000000.00' }],
  ...over,
});

function routes(permissions: string[], extra: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(permissions),
    'GET /api/v1/owner/branches': () => ok([branch()]),
    'GET /api/v1/owner/staff-pay/rules': () => ok(rulesPage()),
    'GET /api/v1/owner/staff-pay/statements': () => ok([statement({ lines: undefined, open_shifts: undefined })]),
    'GET /api/v1/owner/staff-pay/statements/s-1': () => ok(statement()),
    ...extra,
  };
}

describe('Ish haqi qoidalari', () => {
  it('lists the rules in words and adds a rule for one person with the parts that add up', async () => {
    const { calls } = mockFetch(routes(['staff_pay.view', 'staff_pay.manage'], { 'POST /api/v1/owner/staff-pay/rules': () => json({ success: true, message: 'ok', data: rulesPage().rules[0] }, 201) }));
    renderPage(<StaffPayView />);

    const table = await screen.findByRole('table', { name: 'Qoidalar' });
    const row = within(table).getAllByRole('row')[1]!;
    expect(row).toHaveTextContent('Rol: Ofitsiant');
    expect(row).toHaveTextContent("100 000 so'm har smena");
    expect(row).toHaveTextContent("Garant 150 000 so'm har smena yoki 10% sotuvdan (qaysi katta)");

    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: "Qoida qo'shish" }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('radio', { name: 'Bitta xodim uchun' }));
    const date = within(dialog).getByLabelText('Kuchga kirish sanasi');
    await user.clear(date);
    await user.type(date, '2026-12-01');
    await user.type(within(dialog).getByLabelText("Smena uchun (so'm)"), '120 000');
    await user.click(within(dialog).getByRole('button', { name: "Qism qo'shish" }));
    const parts = within(dialog).getAllByTestId('sp-part');
    await user.selectOptions(within(parts[1]!).getByLabelText('Turi'), 'sales_percent');
    await user.type(within(parts[1]!).getByLabelText('Foiz (%)'), '2,5');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const body = await calls.find((call) => call.method === 'POST')!.json();
    expect(body).toEqual({
      branch_id: 1,
      user_id: 7,
      role: null,
      effective_from: '2026-12-01',
      components: [
        { type: 'shift_rate', amount: '120000' },
        { type: 'sales_percent', percent: '2.5' },
      ],
      note: null,
    });
  });

  it('shows the rules read-only without staff_pay.manage', async () => {
    mockFetch(routes(['staff_pay.view']));
    renderPage(<StaffPayView />);

    await screen.findByRole('table', { name: 'Qoidalar' });
    expect(screen.queryByRole('button', { name: "Qoida qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tahrirlash' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('Davr')).toBeDisabled();
    expect(screen.getByLabelText("Stol boshqa ofitsiantga o'tsa, sotuv")).toBeDisabled();
  });

  it('saves whose sales a handed-over table is together with the period', async () => {
    const { calls } = mockFetch(
      routes(['staff_pay.view', 'staff_pay.manage'], {
        'PUT /api/v1/owner/staff-pay/settings': () => ok({ period: 'month', period_label: 'Oylik', sales_attribution: 'current_waiter', sales_attribution_label: 'Stolning oxirgi ofitsianti' }),
      }),
    );
    renderPage(<StaffPayView />);

    const select = await screen.findByLabelText("Stol boshqa ofitsiantga o'tsa, sotuv");
    expect(select).toHaveValue('at_payment');
    expect(screen.getByText("O'tkazishdan oldin to'langan schyotlar birinchi ofitsiantda qoladi (choy puli kabi).")).toBeInTheDocument();
    const save = screen.getByRole('button', { name: 'Saqlash' });
    expect(save).toBeDisabled();

    const user = userEvent.setup();
    await user.selectOptions(select, 'current_waiter');
    expect(screen.getByText("Buyurtmaning hamma schyoti stolni oxirgi olgan ofitsiantga yoziladi.")).toBeInTheDocument();
    await user.click(save);

    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ branch_id: 1, period: 'month', sales_attribution: 'current_waiter' });
  });
});

describe('Hisob-kitob', () => {
  it('opens a statement, shows each line and its warnings, and pays a line from the till with an Idempotency-Key', async () => {
    const { calls } = mockFetch(
      routes(['staff_pay.view', 'staff_pay.manage'], {
        'POST /api/v1/owner/staff-pay/statements/s-1/lines/l-1/pay': () => ok(statement({ status: 'paid', paid_total: '235000.00', lines: [line({ paid: true, paid_method: 'cash_drawer' })] })),
      }),
    );
    renderPage(<StaffPayView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('tab', { name: 'Hisob-kitob' }));
    await user.click(await screen.findByRole('button', { name: 'Ochish' }));

    const lines = await screen.findByRole('table', { name: "Xodimlar bo'yicha hisob-kitob" });
    const row = within(lines).getAllByRole('row')[1]!;
    expect(row).toHaveTextContent('Aziz');
    expect(row).toHaveTextContent('10 soat 00 daq');
    expect(row).toHaveTextContent("−50 000 so'm");
    expect(row).toHaveTextContent("235 000 so'm");
    expect(screen.getByRole('status')).toHaveTextContent("Kassir Dilnoza ishlagan, lekin qoidasi yo'q");

    await user.click(within(row).getByRole('button', { name: 'Aziz' }));
    expect(screen.getByText(/2 smena × 100 000 so'm/)).toBeInTheDocument();

    await user.click(within(row).getByRole('button', { name: "To'lash" }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByRole('radio', { name: /Kassadan naqd/ })).toBeChecked();
    expect(within(dialog).getByLabelText('Smena')).toHaveDisplayValue(/Kassa 1 · kassada 1\s000\s000\sso'm/);
    await user.click(within(dialog).getByRole('button', { name: "To'landi deb belgilash" }));

    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    const request = calls.find((call) => call.method === 'POST')!;
    expect(request.headers.get('Idempotency-Key')).toMatch(/[0-9a-f-]{36}/);
    expect(await request.json()).toEqual({ method: 'cash_drawer', shift_uuid: 'sh-1', note: null });
    expect(await screen.findByText('Kassadan to\'landi')).toBeInTheDocument();
  });

  it('adjusts a draft line with a reason', async () => {
    const draft = statement({ status: 'draft', status_label: 'Qoralama' });
    const { calls } = mockFetch(
      routes(['staff_pay.manage'], {
        'GET /api/v1/owner/staff-pay/statements': () => ok([draft]),
        'GET /api/v1/owner/staff-pay/statements/s-1': () => ok(draft),
        'PUT /api/v1/owner/staff-pay/statements/s-1/lines/l-1': () => ok(statement({ status: 'draft', lines: [line({ adjustment: '-35000.00', adjustment_reason: 'Idish', payable: '200000.00' })] })),
      }),
    );
    renderPage(<StaffPayView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('tab', { name: 'Hisob-kitob' }));
    await user.click(await screen.findByRole('button', { name: 'Ochish' }));
    expect(await screen.findByRole('button', { name: 'Qayta hisoblash' })).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Tuzatish' }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText("Summa (so'm)"), '-35 000');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Idish');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ adjustment: '-35000', reason: 'Idish' });
    expect(await screen.findByText('Idish')).toBeInTheDocument();
  });
});

describe('staff pay words', () => {
  const t = createTranslator('uz');
  const plain = (text: string) => text.replace(/\u00a0/g, ' ');
  it('says each part in one line and reads typed amounts', () => {
    expect(plain(describeComponent({ type: 'hourly_rate', amount: '20000.00' }, t, 'uz'))).toBe("20 000 so'm soatiga");
    expect(plain(describeComponent({ type: 'guarantee', amount: '3000000', per: 'period', mode: 'plus', percent: '5.50', base: 'service_charge' }, t, 'uz'))).toBe("Garant 3 000 000 so'm davr uchun + 5,5% xizmat haqidan");
    expect(describeComponent({ type: 'tips' }, t, 'uz')).toBe("Choy puli qo'shiladi");
    expect(formatPercent('10.00', 'en')).toBe('10%');
    expect(parseAmountInput('1 234 567,5')).toBe('1234567.5');
    expect(parseAmountInput('abc')).toBeNull();
  });
});
