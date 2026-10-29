import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, ok, permissionsAnswer } from '../menu/menuFixtures';
import { ShiftWriteOffs, readShiftWriteOffs } from './ShiftWriteOffs';
import { YieldView } from './YieldView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const batchRow = (over: Record<string, unknown> = {}) => ({
  id: 41,
  uuid: 'b-41',
  business_date: '2026-11-14',
  product_id: 7,
  product_name: 'Osh',
  product_variant_id: null,
  variant_name: null,
  unit: 'piece',
  label: '1-qozon',
  started_at: '2026-11-14T06:00:00Z',
  closed_at: null,
  is_open: true,
  produced: 40,
  sold: 30.5,
  written_off: 4,
  left: 3,
  oversold: 0,
  variance: 2.5,
  sold_percent: '76.3',
  written_off_percent: '10.0',
  ...over,
});

const report = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  from: '2026-11-14',
  to: '2026-11-14',
  batches: [batchRow()],
  days: [{ business_date: '2026-11-14', product_id: 7, product_name: 'Osh', unit: 'piece', batches: 1, produced: 40, sold: 30.5, written_off: 4, left: 3, variance: 2.5, sold_percent: '76.3', written_off_percent: '10.0' }],
  write_offs: [{ reason: 'unsold', label: 'Sotilmay qoldi', count: 2, quantity: 4 }],
  in_sets: [{ business_date: '2026-11-14', product_id: 9, name: 'Mastava', unit: 'piece', quantity: 12 }],
  ...over,
});

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(['reports.view', 'menu.view']),
    'GET /api/v1/owner/branches': () => ok([branch()]),
    'GET /api/v1/owner/batch-yield': () => ok(report()),
    'GET /api/v1/owner/batch-writeoffs': () =>
      ok({
        branch_id: 1,
        from: '2026-11-14',
        to: '2026-11-14',
        reasons: [],
        write_offs: [
          { uuid: 'w-1', batch_id: 41, product_id: 7, product_name: 'Osh', variant_name: null, quantity: 3, unit: 'piece', reason: 'spoiled', reason_label: 'Buzildi', note: 'Idish sinib', business_date: '2026-11-14', shift_id: null, written_off_by: { id: 1, name: 'Aziza' }, created_at: '2026-11-14T10:00:00Z' },
        ],
      }),
    ...extra,
  };
}

describe('Hosil hisoboti', () => {
  it('shows what was cooked, sold, written off and left per day and per batch, as the server worked it out', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<YieldView />);

    const days = await screen.findByRole('table', { name: "Kunlar bo'yicha" });
    const row = within(days).getAllByRole('row')[1]!;
    expect(row).toHaveTextContent('Osh');
    expect(row).toHaveTextContent('40');
    expect(row).toHaveTextContent('30,5');
    expect(row).toHaveTextContent('+2,5');
    expect(row).toHaveTextContent('76,3%');

    const batches = screen.getByRole('table', { name: 'Partiyalar' });
    expect(within(batches).getByText('1-qozon')).toBeInTheDocument();
    expect(within(batches).getByText('Ochiq')).toBeInTheDocument();
    expect(screen.getByText('2 marta, 4')).toBeInTheDocument();
    expect(screen.getByText('Kompleks ichida sotilgan')).toBeInTheDocument();

    const query = new URL(calls.find((call) => call.url.includes('/batch-yield'))!.url).searchParams;
    expect(query.get('branch_id')).toBe('1');
    expect(query.get('from')).toBe(query.get('to'));
  });

  it('asks again for the days the person picks', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<YieldView />);
    const user = userEvent.setup();
    await screen.findByRole('table', { name: "Kunlar bo'yicha" });

    const from = screen.getByLabelText('Qaysi kundan');
    await user.clear(from);
    await user.type(from, '2026-11-10');
    await screen.findByRole('table', { name: "Kunlar bo'yicha" });
    const last = new URL(calls.filter((call) => call.url.includes('/batch-yield')).at(-1)!.url).searchParams;
    expect(last.get('from')).toBe('2026-11-10');
  });

  it('says so when there was no batch', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/batch-yield': () => ok(report({ batches: [], days: [], write_offs: [], in_sets: [] })) }));
    renderPage(<YieldView />);
    expect(await screen.findByText("Bu davrda partiya yo'q")).toBeInTheDocument();
  });

  it('lists the write-offs with their reasons and who did them', async () => {
    mockFetch(routes());
    renderPage(<YieldView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('tab', { name: 'Hisobdan chiqarilganlar' }));
    const table = await screen.findByRole('table', { name: 'Hisobdan chiqarilganlar' });
    expect(table).toHaveTextContent('Buzildi');
    expect(table).toHaveTextContent('Idish sinib');
    expect(table).toHaveTextContent('Aziza');
    expect(table).toHaveTextContent('3');
  });

  it("shows the server's message when the module is off", async () => {
    mockFetch(routes({ 'GET /api/v1/owner/batch-yield': () => json({ success: false, message: '"Partiya va qozon" moduli bu filialda o\'chirilgan.', code: 'module_disabled' }, 403) }));
    renderPage(<YieldView />);
    expect(await screen.findByText(/moduli bu filialda o'chirilgan/)).toBeInTheDocument();
  });
});

describe('the write-offs block of a shift report', () => {
  it('is read from the report only when something was written off', () => {
    expect(readShiftWriteOffs({ batch_writeoffs: { count: 0, items: [] } })).toBeNull();
    expect(readShiftWriteOffs({})).toBeNull();
    expect(readShiftWriteOffs(null)).toBeNull();
    expect(readShiftWriteOffs({ batch_writeoffs: { count: 1, items: [] } })?.count).toBe(1);
  });

  it('lists each dish with its reason and amount', () => {
    renderPage(<ShiftWriteOffs writeOffs={{ count: 3, items: [{ product_id: 7, product_name: 'Osh', unit: 'piece', reason: 'unsold', reason_label: 'Sotilmay qoldi', quantity: 2.5 }] }} />);
    const block = screen.getByLabelText('Hisobdan chiqarilgan taomlar');
    expect(block).toHaveTextContent('Osh · Sotilmay qoldi');
    expect(block).toHaveTextContent('2,5');
  });
});
