import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { product, variant } from '../menu/menuFixtures';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { StationsPanel } from '../possetup/StationsPanel';
import { formatDuration, parseTarget } from './format';
import { KitchenTimesView } from './KitchenTimesView';
import { createTranslator } from '@/i18n';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const KINDS = ['osh', 'mangal', 'salad_bar', 'tea', 'kitchen', 'other', 'grill', 'fryer', 'drinks', 'barista', 'expo'].map((value) => ({ value, label: value }));
const station = (over: Record<string, unknown> = {}) => ({ id: 1, branch_id: 1, name: 'Grill', kind: 'grill', kind_label: 'Grill', sort_order: 1, is_active: true, show_on_kds: true, target_seconds: 300, ...over });
const format = (modules: string[]) => ({ branch_id: 1, profile: 'fast_food', profile_label: 'Fast food', mode: 'counter', first_screen: 'quick_counter', modules, locks: { service_charge_zero: false }, module_rows: [], profiles: [], template: { profile: 'fast_food', stations: [], dishes: [] } });
const targetsPage = (products: Array<Record<string, unknown>> = []) => ({
  branch_id: 1,
  enabled: true,
  limits: { min: 10, max: 7200 },
  stations: [{ id: 1, name: 'Grill', kind: 'grill', kind_label: 'Grill', is_active: true, target_seconds: 300, default_target_seconds: 300 }],
  products,
});

const stats = (over: Record<string, unknown> = {}) => ({ count: 0, median_seconds: null, p90_seconds: null, with_target: 0, over_target: 0, over_target_share: null, ...over });
const report = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  from: '2026-10-22',
  to: '2026-10-22',
  timezone: 'Asia/Tashkent',
  day_starts_at: '05:00',
  station_id: null,
  summary: stats({ count: 6, median_seconds: 110, p90_seconds: 300, with_target: 6, over_target: 2, over_target_share: '33.3' }),
  orders: stats({ count: 4, median_seconds: 210, p90_seconds: 440 }),
  stations: [
    { station_id: 1, name: 'Grill', kind: 'grill', target_seconds: 300, ...stats({ count: 3, median_seconds: 240, p90_seconds: 336, with_target: 3, over_target: 1, over_target_share: '33.3' }) },
    { station_id: null, name: 'Sexsiz', kind: null, target_seconds: null, ...stats({ count: 1, median_seconds: 45, p90_seconds: 45 }) },
  ],
  products: [{ product_id: 8, name: 'Shashlik', ...stats({ count: 3, median_seconds: 240, p90_seconds: 336, with_target: 3, over_target: 0, over_target_share: '0.0' }) }],
  hours: Array.from({ length: 24 }, (_, hour) => ({ hour, ...(hour === 18 ? stats({ count: 5, median_seconds: 120, p90_seconds: 312, with_target: 5, over_target: 2, over_target_share: '40.0' }) : stats()) })),
  expo: { station_id: 3, name: "Yig'ish", target_seconds: 120, ...stats({ count: 2, median_seconds: 100, p90_seconds: 132, with_target: 2, over_target: 1, over_target_share: '50.0' }) },
  ...over,
});

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('formatDuration and parseTarget', () => {
  it('shows seconds as minutes:seconds and reads a typed target within the limits', () => {
    const t = createTranslator('uz');
    expect(formatDuration(300, t)).toBe('5:00');
    expect(formatDuration(75, t)).toBe('1:15');
    expect(formatDuration(null, t)).toBe('—');
    expect(parseTarget('', { min: 10, max: 7200 })).toBeNull();
    expect(parseTarget(' 90 ', { min: 10, max: 7200 })).toBe(90);
    expect(parseTarget('5', { min: 10, max: 7200 })).toBeUndefined();
    expect(parseTarget('1.5', { min: 10, max: 7200 })).toBeUndefined();
  });
});

describe('Sexlar: KDS v2 time targets', () => {
  const open = (over: Parameters<typeof mockFetch>[0] = {}, modules = ['kds', 'kds_expo']) => {
    const mocked = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(),
      'GET /api/v1/owner/branches': () => ok([makeBranch()]),
      'GET /api/v1/owner/pos/format': () => ok(format(modules)),
      'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: KINDS, stations: [station(), station({ id: 3, name: "Yig'ish", kind: 'expo', target_seconds: null })] }),
      'GET /api/v1/owner/kds/targets': () => ok(targetsPage([{ product_id: 8, name: 'Katta burger', category_id: 2, target_seconds: 420 }])),
      'GET /api/v1/owner/products': () => json({ success: true, data: [product({ id: 8, name: 'Katta burger', variants: [variant()] }), product({ id: 9, name: 'Kartoshka fri', variants: [variant({ id: 13, product_id: 9 })] })] }),
      ...over,
    });
    renderPage(<StationsPanel branchId={1} />);
    return { ...mocked, typing: userEvent.setup() };
  };

  it("lists each station's target and the dishes' own targets", async () => {
    open();
    const table = await screen.findByRole('table', { name: 'Oshxona sexlari' });
    const rows = within(table).getAllByRole('row');
    expect(rows[1]).toHaveTextContent('Grill');
    expect(rows[1]).toHaveTextContent('5:00');
    expect(rows[2]).toHaveTextContent("Yig'ish");
    expect(rows[2]).toHaveTextContent('—');
    const dishes = await screen.findByRole('table', { name: "Taomning o'z vaqti" });
    expect(within(dishes).getByText('Katta burger')).toBeInTheDocument();
    expect(within(dishes).getByText('7:00')).toBeInTheDocument();
  });

  it('adds an expo station with its target, checking the seconds first', async () => {
    const { calls, typing } = open({ 'POST /api/v1/owner/kitchen-stations': () => ok(station({ id: 4, name: 'Yig\'ish 2', kind: 'expo', target_seconds: 150 })) });
    await typing.click(await screen.findByRole('button', { name: "Sex qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), "Yig'ish 2");
    await typing.selectOptions(screen.getByLabelText('Turi'), 'expo');
    expect(screen.getByText(/Yig'ish sexiga taom biriktirilmaydi/)).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Vaqt maqsadi'), '5');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Vaqt maqsadi 10 dan 7200 soniyagacha butun son bo'lishi kerak.")).toBeInTheDocument();
    await typing.clear(screen.getByLabelText('Vaqt maqsadi'));
    await typing.type(screen.getByLabelText('Vaqt maqsadi'), '150');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await last(calls, 'POST').json()).toEqual({ branch_id: 1, name: "Yig'ish 2", kind: 'expo', is_active: true, show_on_kds: true, target_seconds: 150 });
  });

  it('sends an unchanged target never, and a dish gets its own target or loses it', async () => {
    const { calls, typing } = open({
      'PATCH /api/v1/owner/kitchen-stations/1': () => ok(station({ name: 'Grill 1' })),
      'PUT /api/v1/owner/kds/targets/products': () =>
        ok(targetsPage([{ product_id: 8, name: 'Katta burger', category_id: 2, target_seconds: 420 }, { product_id: 9, name: 'Kartoshka fri', category_id: 2, target_seconds: 200 }])),
    });
    await typing.click(await screen.findByRole('button', { name: 'Grill: sexni tahrirlash' }));
    await typing.clear(screen.getByLabelText('Nomi'));
    await typing.type(screen.getByLabelText('Nomi'), 'Grill 1');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).not.toHaveProperty('target_seconds');

    await typing.selectOptions(await screen.findByLabelText('Taom'), '9');
    await typing.type(screen.getByLabelText('Soniya'), '200');
    await typing.click(screen.getByRole('button', { name: "Qo'shish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, items: [{ product_id: 9, target_seconds: 200 }] });
    expect(await screen.findByText('3:20')).toBeInTheDocument();

    await typing.click(await screen.findByRole('button', { name: "Katta burger: o'z vaqtini olib tashlash" }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, items: [{ product_id: 8, target_seconds: null }] });
  });

  it('without the module there is no target field and no dishes panel', async () => {
    const { typing } = open({}, ['kds']);
    await typing.click(await screen.findByRole('button', { name: "Yig'ish: sexni tahrirlash" }));
    expect(screen.queryByLabelText('Vaqt maqsadi')).not.toBeInTheDocument();
    expect(screen.getByText(/Vaqt maqsadi "Yig'ish ekrani va sex vaqtlari" moduli bilan ishlaydi/)).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: "Taomning o'z vaqti" })).not.toBeInTheDocument();
  });
});

describe('Tayyorlash vaqti', () => {
  const open = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) => {
    const mocked = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(keys),
      'GET /api/v1/owner/branches': () => ok([makeBranch()]),
      'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: KINDS, stations: [station(), station({ id: 3, name: "Yig'ish", kind: 'expo' })] }),
      'GET /api/v1/owner/reports/kitchen-times': () => ok(report()),
      ...over,
    });
    renderPage(<KitchenTimesView />);
    return { ...mocked, typing: userEvent.setup() };
  };

  it('shows the figures the server worked out per station, dish and hour', async () => {
    const { calls } = open();
    const stations = await screen.findByRole('table', { name: "Sexlar bo'yicha" });
    const grill = within(stations).getAllByRole('row')[1]!;
    expect(grill).toHaveTextContent('Grill');
    expect(grill).toHaveTextContent('5:00');
    expect(grill).toHaveTextContent('4:00');
    expect(grill).toHaveTextContent('5:36');
    expect(grill).toHaveTextContent('1 / 3 (33,3%)');
    expect(within(stations).getAllByRole('row')[2]).toHaveTextContent('Sexsiz');
    const hours = screen.getByRole('table', { name: "Soatlar bo'yicha" });
    // Only the hours that had lines
    expect(within(hours).getAllByRole('row')).toHaveLength(2);
    expect(within(hours).getAllByRole('row')[1]).toHaveTextContent('18:00–19:00');
    expect(screen.getByRole('heading', { name: "Yig'ish · Yig'ish" })).toBeInTheDocument();
    expect(screen.getByText('1 / 2 (50,0%)')).toBeInTheDocument();
    const request = new URL(calls.find((call) => call.url.includes('kitchen-times'))!.url);
    expect(request.searchParams.get('branch_id')).toBe('1');
    expect(request.searchParams.has('station_id')).toBe(false);
  });

  it('narrows to one station', async () => {
    const { calls, typing } = open();
    await screen.findByRole('table', { name: "Sexlar bo'yicha" });
    await typing.selectOptions(screen.getByLabelText('Sex'), '1');
    await waitFor(() => expect(calls.filter((call) => call.url.includes('station_id=1'))).toHaveLength(1));
  });

  it('says when nothing was ready, and shows the refusal of a branch without the kitchen display', async () => {
    open({ 'GET /api/v1/owner/reports/kitchen-times': () => ok(report({ summary: stats(), orders: stats(), stations: [], products: [], expo: null })) });
    expect(await screen.findByText("Bu davrda tayyor bo'lgan taom yo'q")).toBeInTheDocument();
  });

  it('shows the server refusal', async () => {
    open({ 'GET /api/v1/owner/reports/kitchen-times': () => json({ success: false, message: "Oshxona ekrani (KDS) bu filial formatida o'chirilgan.", data: { reason: 'module_disabled' } }, 403) });
    expect(await screen.findByText(/bu filial formatida o'chirilgan/)).toBeInTheDocument();
  });

  it('a manager who only reads reports gets every station without the station filter', async () => {
    const { calls } = open({}, ['reports.view', 'orders.view']);
    await screen.findByRole('table', { name: "Sexlar bo'yicha" });
    expect(screen.queryByLabelText('Sex')).not.toBeInTheDocument();
    expect(calls.some((call) => call.url.includes('/owner/kitchen-stations'))).toBe(false);
  });
});
