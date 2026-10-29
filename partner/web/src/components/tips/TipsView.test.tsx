import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { TipsView } from './TipsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const OWNER = ['reports.view', 'tips.manage', 'settings.manage', 'orders.view'];

const summary = {
  count: 2,
  amount: '15000.00',
  by_method: [
    { method: 'cash', count: 1, amount: '10000.00' },
    { method: 'card', count: 1, amount: '5000.00' },
    { method: 'qr', count: 0, amount: '0.00' },
  ],
  personal: '10000.00',
  pool: '5000.00',
  unassigned: '0.00',
  by_staff: [{ staff_id: 7, name: 'Aziz', amount: '10000.00' }],
  corrections: { count: 0, amount: '0.00' },
};

const report = {
  branch_id: 1,
  from: '2026-11-10',
  to: '2026-11-16',
  totals: summary,
  by_day: [{ business_date: '2026-11-16', amount: '15000.00', personal: '10000.00', pool: '5000.00', cash: '10000.00', card: '5000.00', qr: '0.00', distributions: [], undistributed: '5000.00' }],
  by_staff: [{ staff_id: 7, name: 'Aziz', role: 'waiter', personal: '10000.00', pool_share: '0.00', pool_share_closed: '0.00', total: '10000.00' }],
  undistributed: '5000.00',
  staff: [
    { id: 7, name: 'Aziz', role: 'waiter' },
    { id: 8, name: 'Bobur', role: 'waiter' },
  ],
};

const tip = {
  uuid: '11111111-1111-4111-8111-111111111111',
  method: 'cash',
  source: 'tender',
  amount: '10000.00',
  original_amount: '10000.00',
  mode: 'personal',
  staff: { id: 7, name: 'Aziz' },
  holders: [{ mode: 'personal', staff: { id: 7, name: 'Aziz' }, amount: '10000.00' }],
  business_date: '2026-11-16',
  order_uuid: null,
  bill_uuid: null,
  payment_uuid: null,
  shift_uuid: null,
  recorded_by: { id: 3, name: 'Kassir' },
  closed: false,
  corrections: [],
  created_at: '2026-11-16T12:00:00+05:00',
};

const settings = {
  settings: { branch_id: 1, enabled: true, mode: 'personal', pool_rule: 'equal', pool_period: 'day', roles: ['waiter'], role_weights: { manager: '1.00', cashier: '1.00', waiter: '1.00', cook: '1.00' }, updated_at: null },
  options: {
    modes: [
      { key: 'personal', label: 'Shaxsiy (ofitsiantga)' },
      { key: 'pool', label: 'Umumiy qozon' },
    ],
    pool_rules: [
      { key: 'equal', label: 'Teng' },
      { key: 'hours', label: 'Ishlagan soatiga qarab' },
      { key: 'sales', label: 'Savdosiga qarab' },
      { key: 'role_weights', label: 'Rol ulushiga qarab' },
    ],
    pool_periods: [
      { key: 'day', label: 'Biznes kun' },
      { key: 'shift', label: 'Smena' },
    ],
    roles: [
      { key: 'manager', label: 'Menejer' },
      { key: 'cashier', label: 'Kassir' },
      { key: 'waiter', label: 'Ofitsiant' },
      { key: 'cook', label: 'Oshpaz' },
    ],
  },
};

const distribution = {
  uuid: '22222222-2222-4222-8222-222222222222',
  branch_id: 1,
  business_date: '2026-11-16',
  shift_uuid: null,
  rule: 'equal',
  roles: ['waiter'],
  role_weights: {},
  pool_amount: '5000.00',
  tips_count: 1,
  distributed: '5000.00',
  status: 'open',
  computed_at: '2026-11-16T23:00:00+05:00',
  closed_at: null,
  closed_by: null,
  manual_participants: false,
  lines: [
    { staff_id: 7, name: 'Aziz', role: 'waiter', basis: '1.00', amount: '2500.00' },
    { staff_id: 8, name: 'Bobur', role: 'waiter', basis: '1.00', amount: '2500.00' },
  ],
};

const rules = {
  branch_id: 1,
  enabled: true,
  locked: false,
  order_types: [
    { key: 'hall', label: 'Zalda (ofitsiant)' },
    { key: 'table_service', label: 'Stolga olib kelish' },
    { key: 'takeaway', label: 'Olib ketish / kassa' },
  ],
  rows: [
    {
      branch_id: 1,
      hall_id: null,
      hall_name: null,
      service_charge_percent: '10.00',
      service_charge_channels: null,
      place_fee_amount: '0.00',
      place_fee_mode: 'per_person',
      rules: { by_order_type: { hall: '15.00' }, large_group_min_guests: null, large_group_percent: null, cover_amount: null },
      notice: null,
      notice_preview: 'Xizmat haqi 15%.',
    },
  ],
};

const open = (over: Parameters<typeof mockFetch>[0] = {}, keys: string[] = OWNER) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/tips': () => ok(report),
    'GET /api/v1/owner/pos/tips/entries': () => ok({ tips: [tip], truncated: false }),
    'GET /api/v1/owner/pos/tips/settings': () => ok(settings),
    'GET /api/v1/owner/pos/tips/distributions': () => ok({ distributions: [distribution] }),
    'GET /api/v1/owner/pos/service-charge-rules': () => ok(rules),
    ...over,
  });
  renderPage(<TipsView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('Choy puli', () => {
  it('shows the report: totals, people, days and the tips', async () => {
    open();
    expect(await screen.findByRole('tab', { name: 'Hisobot', selected: true })).toBeInTheDocument();
    expect(await screen.findByRole('table', { name: "Xodimlar bo'yicha" })).toHaveTextContent('Aziz');
    expect(screen.getByRole('table', { name: "Kunlar bo'yicha" })).toHaveTextContent("Bo'linmagan");
    expect(await screen.findByRole('table', { name: 'Olingan choy pullari' })).toHaveTextContent('Naqd');
  });

  it('corrects a tip to another waiter with a reason', async () => {
    const corrected = { ...tip, staff: { id: 8, name: 'Bobur' }, holders: [{ mode: 'personal', staff: { id: 8, name: 'Bobur' }, amount: '10000.00' }] };
    const { calls, typing } = open({ 'POST /api/v1/owner/pos/tips/11111111-1111-4111-8111-111111111111/corrections': () => ok(corrected) });
    await typing.click(await screen.findByRole('button', { name: 'Tuzatish' }));
    const dialog = await screen.findByRole('dialog');
    await typing.selectOptions(within(dialog).getByLabelText('Kimga'), 'Bobur');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText("Maydonni to'ldiring.")).toBeInTheDocument();
    await typing.type(within(dialog).getByLabelText('Sabab'), 'Boshqa ofitsiant');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(await last(calls, 'POST').json()).toEqual({ amount: '10000', mode: 'personal', staff_id: 8, reason: 'Boshqa ofitsiant' });
  });

  it('computes and closes the pool split', async () => {
    const { calls, typing } = open({
      'POST /api/v1/owner/pos/tips/distributions': () => ok(distribution),
      'POST /api/v1/owner/pos/tips/distributions/22222222-2222-4222-8222-222222222222/close': () => ok({ ...distribution, status: 'closed' }),
    });
    await typing.click(await screen.findByRole('tab', { name: "Qozonni bo'lish" }));
    expect(await screen.findByRole('table', { name: /bo'linishi$/ })).toHaveTextContent('Bobur');
    await typing.click(screen.getByRole('button', { name: "Qozonni bo'lish" }));
    expect(await screen.findByText("Qozon bo'lindi.")).toBeInTheDocument();
    expect(JSON.parse(await last(calls, 'POST').text())).toMatchObject({ branch_id: 1 });

    await typing.click(screen.getByRole('button', { name: 'Yopish' }));
    const confirm = await screen.findByRole('alertdialog');
    await typing.click(within(confirm).getByRole('button', { name: 'Yopish' }));
    expect(await screen.findByText("Bo'linish yopildi.")).toBeInTheDocument();
  });

  it('saves the pool settings with the roles taking part', async () => {
    const { calls, typing } = open({ 'PUT /api/v1/owner/pos/tips/settings': () => ok(settings) });
    await typing.click(await screen.findByRole('tab', { name: 'Choy puli sozlamasi' }));
    await typing.selectOptions(await screen.findByLabelText('Choy puli kimga'), 'pool');
    await typing.selectOptions(screen.getByLabelText("Bo'lish qoidasi"), 'role_weights');
    await typing.click(screen.getByRole('checkbox', { name: 'Oshpaz' }));
    const weight = screen.getByLabelText('Oshpaz ulushi');
    await typing.clear(weight);
    await typing.type(weight, '0,5');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Choy puli sozlamasi saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, mode: 'pool', pool_rule: 'role_weights', pool_period: 'day', roles: ['waiter', 'cook'], role_weights: { waiter: '1', cook: '0.5' } });
  });

  it('saves the service charge rules and shows the module refusal', async () => {
    const { calls, typing } = open({
      'PUT /api/v1/owner/pos/service-charge-rules': () => json({ success: false, message: "Modul o'chirilgan.", code: 'module_disabled', data: { reason: 'module_disabled', module: 'service_charge_rules' } }, 403),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Xizmat haqi qoidalari' }));
    expect(await screen.findByText('Xizmat haqi 15%.')).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Katta guruh: necha mehmondan'), '8');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Mehmonlar soni va foizni birga kiriting.")).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Katta guruh foizi'), '18');
    await typing.type(screen.getByLabelText("Kuver (har mehmon uchun, so'm)"), '5000');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Modul o'chirilgan.")).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, by_order_type: { hall: '15' }, large_group_min_guests: 8, large_group_percent: '18', cover_amount: '5000', notice: null });
  });

  it('shows only the report to someone with reports.view', async () => {
    open({}, ['reports.view']);
    expect(await screen.findByRole('tab', { name: 'Hisobot' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Choy puli sozlamasi' })).toBeNull();
    expect(await screen.findByRole('table', { name: 'Olingan choy pullari' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Tuzatish' })).toBeNull();
  });
});
