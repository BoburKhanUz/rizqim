import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import type { BanquetEvent, Calendar, Catalog, EventSummary } from './api';
import { BanquetEventView } from './BanquetEventView';
import { BanquetsView } from './BanquetsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const UUID = 'e0000000-0000-4000-8000-000000000001';
const KEYS = ['banquets.manage', 'reports.view', 'pos.take_payment'];
const SLOTS = { morning: { start: '06:00', end: '11:00' }, day: { start: '12:00', end: '16:00' }, evening: { start: '18:00', end: '23:00' } };

const summary = (over: Partial<EventSummary> = {}): EventSummary => ({
  uuid: UUID,
  branch_id: 1,
  event_date: '2030-03-20',
  slot: 'evening',
  slot_label: 'Kechqurun',
  start_time: '18:00',
  end_time: '23:00',
  starts_at: '2030-03-20T13:00:00.000000Z',
  ends_at: '2030-03-20T18:00:00.000000Z',
  hall_ids: [5, 6],
  halls: [
    { id: 5, name: 'Oq zal' },
    { id: 6, name: "Ko'k zal" },
  ],
  event_type: 'toy',
  event_type_label: "To'y",
  status: 'hold',
  status_label: 'Vaqtincha band',
  title: null,
  customer_name: 'Karimovlar',
  customer_phone: '+998901234567',
  guests_guaranteed: 180,
  guests_minimum: 200,
  guests_actual: null,
  guests_billed: 200,
  package_id: 3,
  package_name: 'Standart',
  total: '45300000.00',
  paid_total: '0.00',
  outstanding: '45300000.00',
  hold_expires_at: '2030-03-01T05:00:00.000000Z',
  frozen: false,
  contract_no: null,
  version: 2,
  ...over,
});

const detail = (over: Partial<BanquetEvent> = {}): BanquetEvent => ({
  ...summary(),
  package_snapshot: { name: 'Standart', per_person_price: '150000.00', minimum_guests: 200, included: ['Osh'] },
  package_total: '30000000.00',
  hall_total: '10000000.00',
  extras_total: '5300000.00',
  orders_total: '0.00',
  quote: null,
  frozen_at: null,
  note: null,
  order_uuid: null,
  cancel_reason: null,
  confirmed_at: null,
  cancelled_at: null,
  released_at: null,
  lines: [
    { uuid: 'l1', kind: 'package', kind_label: 'Paket', name: 'Standart', extra_id: null, hall_id: null, quantity: '1', persons: 200, per_person: true, unit_price: '150000.00', amount: '30000000.00', frozen: false, note: null },
    { uuid: 'l2', kind: 'hall_rent', kind_label: 'Zal ijarasi', name: 'Zal ijarasi: Oq zal', extra_id: null, hall_id: 5, quantity: '1', persons: null, per_person: false, unit_price: '5000000.00', amount: '5000000.00', frozen: false, note: null },
  ],
  payments: [],
  warnings: [{ code: 'guests_over_threshold', message: "Mehmonlar soni 260: 250 dan ko'p." }],
  ...over,
});

const calendar = (events: EventSummary[] = [summary()]): Calendar => ({
  branch_id: 1,
  from: '2030-03-01',
  to: '2030-03-31',
  timezone: 'Asia/Tashkent',
  slots: SLOTS,
  halls: [
    { id: 5, name: 'Oq zal', is_active: true, events },
    { id: 6, name: "Ko'k zal", is_active: true, events },
  ],
});

const catalog: Catalog = {
  packages: [{ id: 3, uuid: 'p', name: 'Standart', per_person_price: '150000.00', minimum_guests: 200, included: ['Osh'], description: null, is_active: true, sort_order: 0, items: [] }],
  extras: [{ id: 7, uuid: 'x', name: 'Tort', price: '800000.00', pricing: 'per_event', pricing_label: 'Tadbirga', is_active: true, sort_order: 0 }],
  halls: [
    { hall_id: 5, hall_name: 'Oq zal', branch_id: 1, is_active: true, rates: { morning: null, day: '3000000.00', evening: '5000000.00', custom: null } },
    { hall_id: 6, hall_name: "Ko'k zal", branch_id: 1, is_active: true, rates: { morning: null, day: null, evening: '5000000.00', custom: null } },
  ],
  settings: { slots: SLOTS, hold_hours: 72, guest_warning_thresholds: [200, 250, 300], window_start: '06:00', window_end: '23:00', advance_mxik: null, advance_package_code: null, cancellation_mxik: null, cancellation_package_code: null, quote_currency: null, version: 1 },
  event_types: [],
};

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys = KEYS) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/pos/banquets/calendar': () => ok(calendar()),
    'GET /api/v1/pos/banquets/catalog': () => ok(catalog),
    [`GET /api/v1/pos/banquets/${UUID}`]: () => ok(detail()),
    [`GET /api/v1/pos/banquets/${UUID}/payments`]: () => json({ success: false, message: 'Topilmadi' }, 404),
    ...over,
  });

const openCalendar = async (over: Parameters<typeof mockFetch>[0] = {}, keys = KEYS) => {
  const mocked = base(over, keys);
  const typing = userEvent.setup();
  renderPage(<BanquetsView />);
  fireEvent.change(await screen.findByLabelText('Sana'), { target: { value: '2030-03-15' } });
  await screen.findByRole('row', { name: /Oq zal/ });
  return { ...mocked, typing };
};

const lastQuery = (calls: Request[], path: string) => new URL(calls.filter((call) => new URL(call.url).pathname === path).at(-1)!.url).searchParams;

describe('the events calendar', () => {
  it('lists the merged event under both halls and offers only the free slots', async () => {
    const { calls } = await openCalendar();
    await waitFor(() => expect(lastQuery(calls, '/api/v1/pos/banquets/calendar').get('from')).toBe('2030-03-01'));
    expect(lastQuery(calls, '/api/v1/pos/banquets/calendar').get('to')).toBe('2030-03-31');

    const rows = screen.getAllByRole('link', { name: /Karimovlar, 18:00–23:00, Vaqtincha band/ });
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveAttribute('href', `/banquets/${UUID}`);
    expect(rows[0]).toHaveTextContent('200 mehmon');
    expect(screen.queryByRole('button', { name: 'Oq zal, 2030-03-20, Kechqurun: zalni band qilish' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Oq zal, 2030-03-20, Kunduzi: zalni band qilish' })).toBeInTheDocument();
  });

  it('badges an event with an overdue payment', async () => {
    await openCalendar({ 'GET /api/v1/pos/banquets/calendar': () => ok(calendar([summary({ overdue: true })])) });
    const rows = screen.getAllByRole('link', { name: /Karimovlar, 18:00–23:00, Vaqtincha band/ });
    expect(rows[0]).toHaveTextContent("Muddati o'tgan");
  });

  it('shows no badge when nothing is overdue', async () => {
    await openCalendar({ 'GET /api/v1/pos/banquets/calendar': () => ok(calendar([summary({ overdue: false })])) });
    expect(screen.getAllByRole('link', { name: /Karimovlar/ })[0]).not.toHaveTextContent("Muddati o'tgan");
  });

  it('links to the shopping list and the production settings', async () => {
    await openCalendar();
    expect(screen.getByRole('link', { name: "Bozorlik ro'yxati" })).toHaveAttribute('href', '/banquets/shopping');
    expect(screen.getByRole('link', { name: 'Ishlab chiqarish sozlamalari' })).toHaveAttribute('href', '/banquets/ops-settings');
  });

  it('holds a hall from a free slot and shows the server refusal of a taken one', async () => {
    let answer = () => json({ success: false, message: "Bu vaqtda zal band: Oq zal. Boshqa sana, vaqt yoki zalni tanlang.", data: { reason: 'slot_taken', conflicts: [] } }, 409);
    const { calls, typing } = await openCalendar({ 'POST /api/v1/pos/banquets': () => answer() });
    await typing.click(screen.getByRole('button', { name: 'Oq zal, 2030-03-20, Kunduzi: zalni band qilish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Zalni band qilish' });
    await within(dialog).findByLabelText('Mijoz');
    await typing.type(within(dialog).getByLabelText('Mijoz'), 'Aliyevlar');
    await typing.type(within(dialog).getByLabelText('Kafolatlangan mehmonlar'), '150');
    await typing.selectOptions(within(dialog).getByLabelText('Paket'), '3');
    await typing.click(within(dialog).getByRole('checkbox', { name: /Tort/ }));
    await typing.click(within(dialog).getByRole('button', { name: 'Band qilish' }));

    expect(await within(dialog).findByText(/Bu vaqtda zal band/)).toBeInTheDocument();
    const post = calls.filter((call) => call.method === 'POST').at(-1)!;
    expect(post.headers.get('Idempotency-Key')).toBeTruthy();
    expect(await post.json()).toEqual({
      event_date: '2030-03-20',
      slot: 'day',
      start_time: null,
      end_time: null,
      hall_ids: [5],
      event_type: 'toy',
      title: null,
      customer_name: 'Aliyevlar',
      customer_phone: null,
      guests_guaranteed: 150,
      banquet_package_id: 3,
      extras: [{ extra_id: 7, quantity: '1' }],
      quote_currency: null,
      quote_rate: null,
      note: null,
      branch_id: 1,
    });

    answer = () => ok(detail({ customer_name: 'Aliyevlar' }), 201);
    await typing.click(within(dialog).getByRole('button', { name: 'Band qilish' }));
    expect(await screen.findByText('Aliyevlar uchun zal band qilindi.')).toBeInTheDocument();
  });

  it('a reader without banquets.manage is told so', async () => {
    base({}, ['reports.view']);
    renderPage(<BanquetsView />);
    expect(await screen.findByText("Tadbirlarni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});

describe('the event page', () => {
  it('shows the lines, totals and warnings, and freezes with the version read', async () => {
    let frozen = false;
    const { calls } = base({
      [`GET /api/v1/pos/banquets/${UUID}`]: () => ok(detail(frozen ? { frozen: true, version: 3 } : {})),
      [`POST /api/v1/pos/banquets/${UUID}/freeze`]: () => {
        frozen = true;
        return ok(detail({ frozen: true, version: 3 }));
      },
    });
    const typing = userEvent.setup();
    renderPage(<BanquetEventView uuid={UUID} />);
    expect(await screen.findByText("Mehmonlar soni 260: 250 dan ko'p.")).toBeInTheDocument();
    const table = screen.getByRole('table', { name: 'Hisob' });
    expect(within(table).getByText('Zal ijarasi: Oq zal')).toBeInTheDocument();
    expect(within(table).getByText(/1 × 200 mehmon/)).toBeInTheDocument();

    await typing.click(screen.getByRole('button', { name: 'Narxlarni muzlatish' }));
    expect(await screen.findByText(/Narxlar muzlatildi/)).toBeInTheDocument();
    const post = calls.filter((call) => call.method === 'POST').at(-1)!;
    expect(await post.json()).toEqual({ version: 2 });
    expect(await screen.findByText('Narxlar muzlatilgan')).toBeInTheDocument();
  });

  it('unfreezing asks for a reason first', async () => {
    const { calls } = base({
      [`GET /api/v1/pos/banquets/${UUID}`]: () => ok(detail({ frozen: true, version: 4 })),
      [`POST /api/v1/pos/banquets/${UUID}/unfreeze`]: () => ok(detail({ frozen: false, version: 5 })),
    });
    const typing = userEvent.setup();
    renderPage(<BanquetEventView uuid={UUID} />);
    await typing.click(await screen.findByRole('button', { name: 'Muzlatishni bekor qilish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Muzlatishni bekor qilish' });
    await typing.click(within(dialog).getByRole('button', { name: 'Muzlatishni bekor qilish' }));
    expect(within(dialog).getByText('Sababni yozing (kamida 3 belgi).')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0);

    await typing.type(within(dialog).getByLabelText('Sabab'), 'Shartnoma qayta tuzildi');
    await typing.click(within(dialog).getByRole('button', { name: 'Muzlatishni bekor qilish' }));
    expect(await screen.findByText(/Muzlatish bekor qilindi/)).toBeInTheDocument();
    expect(await calls.filter((call) => call.method === 'POST').at(-1)!.json()).toEqual({ version: 4, reason: 'Shartnoma qayta tuzildi' });
  });
});
