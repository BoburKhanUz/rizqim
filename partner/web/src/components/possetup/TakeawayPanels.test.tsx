import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const formatOf = (modules: string[]) => ({
  branch_id: 1,
  profile: 'fast_food',
  profile_label: 'Fast food',
  mode: 'counter',
  first_screen: 'quick_counter',
  modules,
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { profile: 'fast_food', stations: [], dishes: [] },
});

const rule = (over: Record<string, unknown> = {}) => ({
  id: 1,
  branch_id: 1,
  name: 'Paket',
  basis: 'order',
  basis_label: 'Buyurtmaga',
  category_id: null,
  product_id: null,
  quantity: 1,
  unit_price: '1000.00',
  mxik: null,
  package_code: null,
  unit: 'dona',
  order_types: ['takeaway', 'pickup', 'delivery'],
  is_active: true,
  sort_order: 1,
  ...over,
});

const PICKUP = {
  branch_id: 1,
  configured: false,
  pickup_min_lead_minutes: 20,
  pickup_slot_minutes: 15,
  pickup_max_days_ahead: 1,
  pickup_prep_minutes: 15,
  round_the_clock: false,
  opening_hours: { open: '09:00', close: '22:00' },
  updated_at: null,
};

const day = (date: string, over: Record<string, unknown> = {}) => ({
  business_date: date,
  started_at: `${date}T00:00:00.000000Z`,
  ends_at: `${date}T00:00:00.000000Z`,
  orders: 0,
  pos_orders: 0,
  online_orders: 0,
  revenue: '0.00',
  packaging_amount: '0.00',
  after_midnight_orders: 0,
  first_order_at: null,
  last_order_at: null,
  shifts: [],
  ...over,
});

const open = async (modules: string[], over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(formatOf(modules)),
    'GET /api/v1/owner/service-fees': () => ok({ channels: ['pos_hall'], settings: [] }),
    'GET /api/v1/owner/categories': () => ok([{ id: 41, name: "Sho'rvalar" }]),
    'GET /api/v1/owner/products': () => ok([]),
    ...over,
  });
  renderPage(<PosSetupView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('the takeaway tabs', () => {
  it('come with their modules only', async () => {
    await open(['packaging', 'scheduled_pickup', 'round_the_clock']);
    expect(await screen.findByRole('tab', { name: 'Qadoq qoidalari' }, { timeout: 5000 })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Oldindan buyurtma' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: '24/7' })).toBeInTheDocument();
  });

  it('are not there for a choyxona', async () => {
    await open(['rooms']);
    await screen.findByRole('tab', { name: 'Format' });
    expect(screen.queryByRole('tab', { name: 'Qadoq qoidalari' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: '24/7' })).not.toBeInTheDocument();
  });
});

describe('Qadoq qoidalari', () => {
  it('lists the rules and adds a bowl per soup portion', async () => {
    const { typing, calls } = await open(['packaging'], {
      'GET /api/v1/owner/branches/1/packaging-rules': () => ok([rule()]),
      'POST /api/v1/owner/branches/1/packaging-rules': () => ok(rule({ id: 2, name: "Sho'rva idishi", basis: 'item', category_id: 41, unit_price: '3000.00' }), 201),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Qadoq qoidalari' }));

    const list = await screen.findByRole('list', { name: 'Qadoq qoidalari' });
    expect(within(list).getByText('Paket')).toBeInTheDocument();
    expect(within(list).getByText(/1 dona har buyurtmaga/)).toBeInTheDocument();
    expect(within(list).getByText(/standart kod/)).toBeInTheDocument();

    await typing.click(screen.getByRole('button', { name: "Qoida qo'shish" }));
    const form = screen.getByRole('form', { name: "Qoida qo'shish" });
    await typing.type(within(form).getByRole('textbox', { name: 'Qadoq nomi' }), "Sho'rva idishi");
    await typing.selectOptions(within(form).getByRole('combobox', { name: 'Qanday hisoblanadi' }), 'item');
    await typing.selectOptions(within(form).getByRole('combobox', { name: 'Kategoriya' }), '41');
    await typing.type(within(form).getByRole('textbox', { name: 'Narxi (bir dona)' }), '3000');
    await typing.click(within(form).getByRole('checkbox', { name: 'Yetkazib berish' }));
    await typing.click(within(form).getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Qoida saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'POST').json()).toEqual({
      name: "Sho'rva idishi",
      basis: 'item',
      category_id: 41,
      product_id: null,
      quantity: 1,
      unit_price: '3000.00',
      mxik: null,
      package_code: null,
      unit: 'dona',
      order_types: ['takeaway', 'pickup'],
      is_active: true,
    });
  });

  it('checks the form before it asks the server, and shows the server refusal', async () => {
    const { typing, calls } = await open(['packaging'], {
      'GET /api/v1/owner/branches/1/packaging-rules': () => ok([]),
      'POST /api/v1/owner/branches/1/packaging-rules': () => json({ success: false, message: 'The given data was invalid.', errors: { name: ["Filialda ko'pi bilan 50 ta qadoq qoidasi bo'ladi."] } }, 422),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Qadoq qoidalari' }));
    expect(await screen.findByText("Qadoq qoidasi yo'q")).toBeInTheDocument();

    await typing.click(screen.getByRole('button', { name: "Qoida qo'shish" }));
    const form = screen.getByRole('form', { name: "Qoida qo'shish" });
    await typing.selectOptions(within(form).getByRole('combobox', { name: 'Qanday hisoblanadi' }), 'item');
    await typing.type(within(form).getByRole('textbox', { name: 'MXIK' }), '123');
    await typing.click(within(form).getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Nomini yozing.')).toBeInTheDocument();
    expect(screen.getByText('Kategoriya yoki taomni tanlang.')).toBeInTheDocument();
    expect(screen.getByText("MXIK 17 ta raqam bo'lishi kerak.")).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0);

    await typing.selectOptions(within(form).getByRole('combobox', { name: 'Qanday hisoblanadi' }), 'order');
    await typing.clear(within(form).getByRole('textbox', { name: 'MXIK' }));
    await typing.type(within(form).getByRole('textbox', { name: 'Qadoq nomi' }), 'Paket');
    await typing.type(within(form).getByRole('textbox', { name: 'Narxi (bir dona)' }), '1000');
    await typing.click(within(form).getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Filialda ko'pi bilan 50 ta qadoq qoidasi bo'ladi.")).toBeInTheDocument();
  });

  it('edits a rule and deletes one after asking', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const { typing, calls } = await open(['packaging'], {
      'GET /api/v1/owner/branches/1/packaging-rules': () => ok([rule()]),
      'PUT /api/v1/owner/branches/1/packaging-rules/1': () => ok(rule({ unit_price: '1500.00' })),
      'DELETE /api/v1/owner/branches/1/packaging-rules/1': () => ok({ id: 1 }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Qadoq qoidalari' }));
    await typing.click(await screen.findByRole('button', { name: "O'zgartirish" }));
    const form = screen.getByRole('form', { name: 'Paket' });
    const price = within(form).getByRole('textbox', { name: 'Narxi (bir dona)' });
    expect(price).toHaveValue('1000');
    await typing.clear(price);
    await typing.type(price, '1500');
    await typing.click(within(form).getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Qoida saqlandi.')).toBeInTheDocument();
    expect((await last(calls, 'PUT').json()).unit_price).toBe('1500.00');

    await typing.click(screen.getByRole('button', { name: "O'chirish" }));
    expect(await screen.findByText("Qoida o'chirildi.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'DELETE' && call.url.endsWith('/owner/branches/1/packaging-rules/1'))).toBe(true);
  });
});

describe('Oldindan buyurtma', () => {
  it('shows the hours and saves only what changed', async () => {
    const { typing, calls } = await open(['scheduled_pickup'], {
      'GET /api/v1/owner/branches/1/pickup-settings': () => ok(PICKUP),
      'PUT /api/v1/owner/branches/1/pickup-settings': () => ok({ ...PICKUP, configured: true, pickup_min_lead_minutes: 30 }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Oldindan buyurtma' }));
    expect(await screen.findByText(/Ish vaqti: 09:00–22:00/)).toBeInTheDocument();

    const lead = screen.getByRole('textbox', { name: 'Eng kamida (daqiqa oldin)' });
    expect(lead).toHaveValue('20');
    await typing.clear(lead);
    await typing.type(lead, '30');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Olib ketish vaqti sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ pickup_min_lead_minutes: 30 });

    const slot = screen.getByRole('textbox', { name: 'Vaqt qadami (daqiqa)' });
    await typing.clear(slot);
    await typing.type(slot, '2');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('5 dan 120 gacha butun son.')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1);
  });
});

describe('24/7', () => {
  it('explains the business day and lists the days with their shifts', async () => {
    const { typing, calls } = await open(['round_the_clock'], {
      'GET /api/v1/owner/branches/1/pos-settings': () => ok({ branch_id: 1, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: {} }),
      'GET /api/v1/owner/branches/1/business-days': () =>
        ok({
          branch_id: 1,
          business_day: { business_date: '2026-10-23', starts_at: '05:00', started_at: '2026-10-23T00:00:00.000000Z', next_starts_at: '2026-10-24T00:00:00.000000Z', round_the_clock: true },
          days: [
            day('2026-10-22', { orders: 3, after_midnight_orders: 2, revenue: '111000.00', packaging_amount: '3000.00', shifts: [{ uuid: 's1', business_date: '2026-10-22', z_number: 7, opened_at: null, closed_at: null, status: 'closed' }] }),
            day('2026-10-23', { orders: 2, revenue: '74000.00' }),
          ],
        }),
    });
    await typing.click(await screen.findByRole('tab', { name: '24/7' }));
    expect(await screen.findByText(/23:59, 00:00 va 04:59 dagi buyurtmalar oldingi kunniki/)).toBeInTheDocument();

    const table = await screen.findByRole('table', { name: 'Biznes kunlar' });
    const thursday = within(table).getByRole('rowheader', { name: '2026-10-22' }).closest('tr')!;
    expect(within(thursday).getByText('Z-7')).toBeInTheDocument();
    expect(thursday).toHaveTextContent('3');
    expect(thursday).toHaveTextContent('111');
    expect(within(table).getByRole('rowheader', { name: '2026-10-23' }).closest('tr')).toHaveTextContent("Smena yo'q");

    await typing.type(screen.getByLabelText('Dan'), '2026-10-01');
    await typing.type(screen.getByLabelText('Gacha'), '2026-10-22');
    await typing.click(screen.getByRole('button', { name: 'Biznes kunlar' }));
    await waitFor(() => {
      const params = new URL(calls.filter((call) => call.url.includes('/business-days')).at(-1)!.url).searchParams;
      expect([params.get('from'), params.get('to')]).toEqual(['2026-10-01', '2026-10-22']);
    });

    await typing.clear(screen.getByLabelText('Dan'));
    await typing.type(screen.getByLabelText('Dan'), '2026-08-01');
    await typing.click(screen.getByRole('button', { name: 'Biznes kunlar' }));
    expect(await screen.findByText("Davr ko'pi bilan 31 kun, boshlanishi tugashidan oldin.")).toBeInTheDocument();
  });
});
