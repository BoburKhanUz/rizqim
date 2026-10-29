import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage, stubFileSaving, xlsx } from '@/test/helpers';
import { storePage, storeRow } from './fixtures';
import { StoresView } from './StoresView';

const PERMS = ['stores.view', 'stores.manage'].map((name) => `admin.${name}`);
const LOOKUP = { stores: [], regions: [{ id: 1, name: 'Toshkent shahri' }, { id: 2, name: 'Samarqand viloyati' }], districts: [] };

function setup(permissions = PERMS, extra: Parameters<typeof mockFetch>[0] = {}) {
  const rows = [
    storeRow(),
    storeRow({
      id: 14,
      name: 'Qarzdor kafe',
      debt_balance: '500000.00',
      over_limit: true,
      plan: null,
      health: { level: 'churn_risk', label: 'Ketish xavfi', score: 40, reasons: 2, top_reason: 'Qarz kredit limitidan oshgan', computed_at: null },
    }),
  ];
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /stores': () => ok(storePage(rows)),
    'GET /stores/lookup': () => ok(LOOKUP),
    ...extra,
  });
  renderPage(<StoresView />);
  return { ...mocks, user: userEvent.setup() };
}

describe('StoresView', () => {
  it('lists stores with health in words, plan and debt, and links to the card', async () => {
    setup();
    const list = await screen.findByRole('list', { name: "Ovqatxonalar ro'yxati" });
    const risky = within(list).getByRole('link', { name: 'Qarzdor kafe kartasini ochish' });
    expect(risky).toHaveAttribute('href', '/stores/14');
    expect(within(risky).getByText('Ketish xavfi · 40 ball')).toBeInTheDocument();
    expect(within(risky).getByText('Qarz kredit limitidan oshgan')).toBeInTheDocument();
    expect(within(risky).getByText('Limitdan oshgan')).toBeInTheDocument();
    expect(within(risky).getByText('Tarifsiz')).toBeInTheDocument();
    expect(within(list).getByText('Asosiy · Faol')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ketish xavfi (1)' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "E'lon yuborish" })).toHaveAttribute('href', '/stores/broadcasts');
  });

  it('sends the filters to the backend', async () => {
    const { user, callsTo } = setup();
    await screen.findByRole('list', { name: "Ovqatxonalar ro'yxati" });
    await user.click(screen.getByRole('button', { name: 'Ketish xavfi (1)' }));
    await vi.waitFor(() => expect(callsTo('GET', '/stores').at(-1)?.query.get('health')).toBe('churn_risk'));
    await user.selectOptions(screen.getByLabelText('Viloyat'), 'Samarqand viloyati');
    await vi.waitFor(() => expect(callsTo('GET', '/stores').at(-1)?.query.get('region_id')).toBe('2'));
    await user.selectOptions(screen.getByLabelText('Qarz'), 'over_limit');
    await vi.waitFor(() => expect(callsTo('GET', '/stores').at(-1)?.query.get('debt')).toBe('over_limit'));
    await user.type(screen.getByLabelText('Qidirish'), 'osh');
    await vi.waitFor(() => expect(callsTo('GET', '/stores').at(-1)?.query.get('q')).toBe('osh'));
  });

  it('says so to a member without stores.view', async () => {
    setup(['admin.orders.view']);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
  });
  it('exports the list to Excel with the filters on screen, for a member with admin.export only', async () => {
    const saved = stubFileSaving();
    const { user, calls } = setup([...PERMS, 'admin.export'], { 'GET /exports/stores': () => xlsx(2) });
    await screen.findByRole('list', { name: "Ovqatxonalar ro'yxati" });
    await user.selectOptions(screen.getByLabelText('Qarz'), 'has_debt');
    await user.click(screen.getByRole('button', { name: 'Excel yuklab olish' }));

    await waitFor(() => expect(saved).toEqual(['rizqim-test.xlsx']));
    expect(Object.fromEntries(calls.filter((call) => call.path === '/exports/stores')[0]!.query)).toEqual({ debt: 'has_debt' });
  });

  it('does not offer the Excel export without admin.export', async () => {
    setup();
    await screen.findByRole('list', { name: "Ovqatxonalar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'Excel yuklab olish' })).not.toBeInTheDocument();
  });
});
