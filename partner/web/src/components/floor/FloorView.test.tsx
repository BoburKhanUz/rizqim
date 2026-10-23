import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { product, variant } from '../menu/menuFixtures';
import type { Hall } from '@rizqim/staff';
import { hall, ok, ownerAnswer, signIn, table } from '../pos/testSupport';
import { FloorView } from './FloorView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const tariffs = (over: Record<string, unknown> = {}) => ({
  calendar: { branch_id: 1, weekend_days: [6, 7], holidays: ['2026-12-31'] },
  tariffs: [
    { id: 1, branch_id: 1, dining_table_id: null, dining_table_name: null, hall_id: null, table_kind: 'room', day_type: 'weekday', price: '200000.00', valid_from: null, valid_to: null },
    { id: 2, branch_id: 1, dining_table_id: 11, dining_table_name: 'Xona 1', hall_id: null, table_kind: null, day_type: 'weekend', price: '0.00', valid_from: '2026-11-01', valid_to: '2026-11-30' },
  ],
  ...over,
});

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[], role = 'owner') =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys, role),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/halls': () => ok([hall()]),
    'GET /api/v1/owner/room-tariffs': () => ok(tariffs()),
    'GET /api/v1/owner/auto-guest-items': () => ok([]),
    ...over,
  });

const lastOf = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('Zallar va joylar', () => {
  it('lists the halls with their places, the minimum spend, the service charge and a closed seasonal zone', async () => {
    base({
      'GET /api/v1/owner/halls': () =>
        ok([
          hall({ service_fee: { service_charge_percent: '15.00', place_fee_amount: '0.00', place_fee_mode: 'per_person' } }),
          hall({ id: 2, name: "Yozgi bog'", is_active: false, tables: [] }),
        ]),
    });
    renderPage(<FloorView />);
    const main = await screen.findByRole('group', { name: 'Asosiy zal' });
    expect(within(main).getByText('Xizmat haqi: 15%')).toBeInTheDocument();
    expect(within(main).getByText('Xona 1')).toBeInTheDocument();
    expect(within(main).getByText("600 000 so'm · Stol uchun")).toBeInTheDocument();
    expect(within(main).getByText('Stol 5')).toBeInTheDocument();
    const garden = screen.getByRole('group', { name: "Yozgi bog'" });
    expect(within(garden).getByText('Yopiq (mavsumiy)')).toBeInTheDocument();
    expect(within(garden).getByText('Xizmat haqi: filialniki')).toBeInTheDocument();
    expect(within(garden).getByText("Bu zalda joy yo'q.")).toBeInTheDocument();
  });

  it('asks for a name before it adds a hall, then adds it with the branch', async () => {
    const { calls } = base({ 'POST /api/v1/owner/halls': () => ok(hall({ id: 3, name: 'VIP' }), 201) });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('button', { name: "Zal qo'shish" }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Nomini kiriting.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
    await typing.type(screen.getByLabelText('Zal nomi'), 'VIP');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("VIP zali qo'shildi."));
    expect(await lastOf(calls, 'POST').json()).toEqual({ branch_id: 1, name: 'VIP', is_active: true });
  });

  it('closes a seasonal zone with its switch', async () => {
    const { calls } = base({ 'PATCH /api/v1/owner/halls/1': () => ok(hall({ is_active: false })) });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('switch', { name: 'Asosiy zal: zal ochiq' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await lastOf(calls, 'PATCH').json()).toEqual({ is_active: false });
    expect(await screen.findByText('Asosiy zal yopildi: kassa xaritasidan chiqadi, ochiq buyurtmalar qoladi.')).toBeInTheDocument();
  });

  it('shows the reason a hall in use cannot be deleted, in the dialog, and keeps the hall', async () => {
    base({ 'DELETE /api/v1/owner/halls/1': () => json({ success: false, message: "Zalda ochiq buyurtmalar bor: avval ularni yoping.", data: { reason: 'hall_in_use' } }, 422) });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('button', { name: "Asosiy zal: zalni o'chirish" }));
    const dialog = await screen.findByRole('alertdialog');
    await typing.click(within(dialog).getByRole('button', { name: "O'chirish" }));
    expect(await within(dialog).findByText('Zalda ochiq buyurtmalar bor: avval ularni yoping.')).toBeInTheDocument();
    expect(screen.getByRole('group', { name: 'Asosiy zal' })).toBeInTheDocument();
  });

  it('checks a place and sends it with its hall, kind, seats and minimum spend', async () => {
    const { calls } = base({ 'POST /api/v1/owner/dining-tables': () => ok(table({ id: 20, name: 'Xona 7' })) });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    const main = await screen.findByRole('group', { name: 'Asosiy zal' });
    await typing.click(within(main).getByRole('button', { name: "Joy qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), 'Xona 7');
    await typing.type(screen.getByLabelText("O'rinlar"), '0');
    await typing.type(screen.getByLabelText('Minimal summa'), 'ko\'p');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("O'rinlar soni 1 dan katta butun son bo'lishi kerak.")).toBeInTheDocument();
    expect(screen.getByText('Son kiriting (masalan, 12000).')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);

    await typing.clear(screen.getByLabelText("O'rinlar"));
    await typing.type(screen.getByLabelText("O'rinlar"), '10');
    await typing.clear(screen.getByLabelText('Minimal summa'));
    await typing.type(screen.getByLabelText('Minimal summa'), '500 000');
    await typing.selectOptions(screen.getByLabelText('Turi'), 'room');
    await typing.selectOptions(screen.getByLabelText('Minimal summa qanday hisoblanadi'), 'per_person');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    // With its layout: the first free spot of the hall's map (Xona 1 at 2,1 5x4 and Stol 5 at 10,2 2x2 are taken)
    expect(await lastOf(calls, 'POST').json()).toEqual({ hall_id: 1, name: 'Xona 7', kind: 'room', seats: 10, min_spend: 500000, min_spend_mode: 'per_person', is_active: true, layout: { x: 12, y: 0, w: 5, h: 4 } });
  });

  it('places a new place in the first free spot of the hall it is put in, after the places never positioned', async () => {
    const { calls } = base({
      'GET /api/v1/owner/halls': () =>
        ok([
          hall(),
          hall({ id: 2, name: 'Ayvon', tables: [table({ id: 30, hall_id: 2, name: 'Tapchan 1', kind: 'tapchan', layout: { x: null, y: null, w: null, h: null } })] }),
        ]),
      'POST /api/v1/owner/dining-tables': () => ok(table({ id: 31, hall_id: 2, name: 'Stol 9', kind: 'table' })),
    });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    const main = await screen.findByRole('group', { name: 'Asosiy zal' });
    await typing.click(within(main).getByRole('button', { name: "Joy qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), 'Stol 9');
    await typing.selectOptions(screen.getByLabelText('Zal'), '2');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    // The tapchan without a layout is drawn at 0,0 (4x3), so the table goes right of it
    expect(await lastOf(calls, 'POST').json()).toMatchObject({ hall_id: 2, kind: 'table', layout: { x: 4, y: 0, w: 2, h: 2 } });
  });

  it("sets and removes a hall's own service charge", async () => {
    const { calls } = base({
      'GET /api/v1/owner/halls': () => ok([hall({ service_fee: { service_charge_percent: '15.00', place_fee_amount: '0.00', place_fee_mode: 'per_person' } })]),
      'PUT /api/v1/owner/service-fees': () => ok({ branch_id: 1, hall_id: 1, service_charge_percent: '20.00', place_fee_amount: '0.00', place_fee_mode: 'per_person' }),
      'DELETE /api/v1/owner/service-fees/halls/1': () => ok({ hall_id: 1 }),
    });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('button', { name: 'Asosiy zal: xizmat haqi' }));
    expect(screen.getByLabelText('Xizmat haqi')).toHaveValue('15');
    await typing.clear(screen.getByLabelText('Xizmat haqi'));
    await typing.type(screen.getByLabelText('Xizmat haqi'), '20');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Asosiy zal: xizmat haqi saqlandi.'));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ hall_id: 1, branch_id: 1, service_charge_percent: 20, place_fee_amount: 0, place_fee_mode: 'per_person' });

    await typing.click(screen.getByRole('button', { name: 'Asosiy zal: xizmat haqi' }));
    await typing.click(screen.getByRole('button', { name: "Filialnikini qo'llash" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE')).toBe(true));
  });

  it("shows a hall's own service charge on the hall once it is saved (the halls are read again)", async () => {
    let fee: Hall['service_fee'] = null;
    const { calls } = base({
      'GET /api/v1/owner/halls': () => ok([hall({ service_fee: fee })]),
      'PUT /api/v1/owner/service-fees': () => {
        fee = { service_charge_percent: '20.00', place_fee_amount: '0.00', place_fee_mode: 'per_person' };
        return ok({ branch_id: 1, hall_id: 1, ...fee });
      },
    });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    const main = await screen.findByRole('group', { name: 'Asosiy zal' });
    expect(within(main).getByText('Xizmat haqi: filialniki')).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Asosiy zal: xizmat haqi' }));
    await typing.type(screen.getByLabelText('Xizmat haqi'), '20');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await within(main).findByText('Xizmat haqi: 20%')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'GET' && call.url.includes('/owner/halls')).length).toBeGreaterThan(1);
  });

  it('is read-only for a cashier: no buttons to add, change or delete', async () => {
    base({}, ['tables.manage'], 'cashier');
    renderPage(<FloorView />);
    expect(await screen.findByText("Faqat ko'rish: zal va joylarni ega yoki menejer o'zgartiradi.")).toBeInTheDocument();
    expect(screen.getByText('Xona 1')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Zal qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Joy qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
    // and no tariffs or guest rules: those are settings
    expect(screen.queryByRole('tab', { name: 'Xona narxi' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Choy-non' })).not.toBeInTheDocument();
  });

  it('gives a manager halls and the map, and the owner the tariffs and guest rules too', async () => {
    base({}, ['tables.manage'], 'manager');
    renderPage(<FloorView />);
    expect(await screen.findByRole('button', { name: "Zal qo'shish" })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Zal xaritasi' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Xona narxi' })).not.toBeInTheDocument();
  });
});

describe('Zal xaritasi', () => {
  const open = async (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[], role = 'owner') => {
    const mocked = base(over, keys, role);
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('tab', { name: 'Zal xaritasi' }));
    await screen.findByTestId('floor-canvas');
    return { ...mocked, typing };
  };

  it('draws the places where the server has them', async () => {
    await open();
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '2,1,5,4');
    expect(screen.getByTestId('place-12')).toHaveAttribute('data-rect', '10,2,2,2');
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    expect(screen.getByText("O'zgarish yo'q")).toBeInTheDocument();
  });

  it('moves a place with the arrow keys and resizes it with Shift, and saves only what changed', async () => {
    const { calls, typing } = await open({ 'PUT /api/v1/owner/dining-tables/layout': () => ok([]) });
    const place = screen.getByTestId('place-11');
    place.focus();
    await typing.keyboard('{ArrowRight}{ArrowRight}{ArrowDown}');
    expect(place).toHaveAttribute('data-rect', '4,2,5,4');
    await typing.keyboard('{Shift>}{ArrowRight}{ArrowDown}{/Shift}');
    expect(place).toHaveAttribute('data-rect', '4,2,6,5');
    expect(screen.getByText("1 ta joy o'zgardi, saqlanmagan")).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Zal xaritasi saqlandi.'));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ branch_id: 1, tables: [{ id: 11, hall_id: 1, layout: { x: 4, y: 2, w: 6, h: 5 } }] });
    // saved: nothing left to save
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
  });

  it('keeps the saved note and the hall on show when the halls are read again and the map is rebuilt from them', async () => {
    let moved = false;
    const { typing } = await open({
      // after the save the server holds the new place, so the halls read again are different and the map starts over from them
      'GET /api/v1/owner/halls': () => ok([hall({ id: 1 }), hall({ id: 2, name: 'VIP', tables: [table({ id: 31, hall_id: 2, name: 'VIP 1', layout: { x: moved ? '2' : '1', y: '1', w: '4', h: '4' } })] })]),
      'PUT /api/v1/owner/dining-tables/layout': () => {
        moved = true;
        return ok([]);
      },
    });
    await typing.selectOptions(screen.getByLabelText('Zal', { selector: 'select' }), 'VIP');
    screen.getByTestId('place-31').focus();
    await typing.keyboard('{ArrowRight}');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(moved).toBe(true));
    await waitFor(() => expect(screen.getByTestId('place-31')).toHaveAttribute('data-rect', '2,1,4,4'));
    expect(screen.getByText('Zal xaritasi saqlandi.')).toBeInTheDocument();
    expect(screen.getByLabelText('Zal', { selector: 'select' })).toHaveValue('2');
  });

  it('keeps a place inside the map', async () => {
    const { typing } = await open();
    const place = screen.getByTestId('place-12');
    place.focus();
    await typing.keyboard('{ArrowLeft>20/}{ArrowUp>5/}');
    expect(place).toHaveAttribute('data-rect', '0,0,2,2');
  });

  it('takes the numbers typed under the map, clamped to the map', async () => {
    const { typing } = await open();
    await typing.click(screen.getByTestId('place-11'));
    const panel = await screen.findByRole('region', { name: 'Xona 1: joylashuvi' }).catch(() => screen.getByLabelText('Xona 1: joylashuvi'));
    const x = within(panel).getByLabelText('X (chapdan)');
    fireEvent.change(x, { target: { value: '99' } });
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '35,1,5,4');
    fireEvent.change(within(panel).getByLabelText('Kengligi'), { target: { value: '8' } });
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '32,1,8,4');
  });

  it('warns about places that sit on each other', async () => {
    await open({ 'GET /api/v1/owner/halls': () => ok([hall({ tables: [table({ layout: { x: '0', y: '0', w: '4', h: '4' } }), table({ id: 12, name: 'Stol 5', kind: 'table', layout: { x: '2', y: '2', w: '3', h: '3' } })] })]) });
    expect(screen.getByText(/Ba'zi joylar bir-biriga tushib qolgan/)).toBeInTheDocument();
  });

  it('puts a place that was never positioned in the first free spot and saves it', async () => {
    const { calls, typing } = await open({
      'GET /api/v1/owner/halls': () => ok([hall({ tables: [table({ layout: { x: null, y: null, w: null, h: null } })] })]),
      'PUT /api/v1/owner/dining-tables/layout': () => ok([]),
    });
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '0,0,5,4');
    expect(screen.getByText("1 ta joy o'zgardi, saqlanmagan")).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ branch_id: 1, tables: [{ id: 11, hall_id: 1, layout: { x: 0, y: 0, w: 5, h: 4 } }] });
  });

  it("shows the server's refusal of the layout and keeps the edits", async () => {
    const { typing } = await open({ 'PUT /api/v1/owner/dining-tables/layout': () => json({ success: false, message: 'Stol bu filialda topilmadi.' }, 422) });
    screen.getByTestId('place-11').focus();
    await typing.keyboard('{ArrowRight}');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Stol bu filialda topilmadi.')).toBeInTheDocument();
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '3,1,5,4');
    await typing.click(screen.getByRole('button', { name: 'Bekor qilish' }));
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '2,1,5,4');
  });

  it('cannot be edited by a cashier: no save, no resize handle, the keys do nothing', async () => {
    const { typing } = await open({}, ['tables.manage'], 'cashier');
    expect(screen.queryByRole('button', { name: 'Saqlash' })).not.toBeInTheDocument();
    expect(screen.queryByTestId('resize-11')).not.toBeInTheDocument();
    screen.getByTestId('place-11').focus();
    await typing.keyboard('{ArrowRight}');
    expect(screen.getByTestId('place-11')).toHaveAttribute('data-rect', '2,1,5,4');
  });
});

describe('Xona narxi', () => {
  const open = async (over: Parameters<typeof mockFetch>[0] = {}) => {
    const mocked = base(over);
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('tab', { name: 'Xona narxi' }));
    await screen.findByText('Filial kalendari');
    return { ...mocked, typing };
  };

  it('lists the tariffs by room and by kind, free ones, dates, and the calendar', async () => {
    await open();
    const table_ = screen.getByRole('table', { name: 'Xona narxi' });
    expect(within(table_).getByText('Barcha xonalar · barcha zallar')).toBeInTheDocument();
    expect(within(table_).getByText("200 000 so'm")).toBeInTheDocument();
    expect(within(table_).getByText('Bepul')).toBeInTheDocument();
    expect(within(table_).getByText('Xona 1')).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Shanba' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Dushanba' })).not.toBeChecked();
    expect(screen.getByText(/31-dek|31 дек|Dec 31/)).toBeInTheDocument();
  });

  it('checks the price and the dates, then creates a tariff for a kind of place', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/owner/room-tariffs': () => ok({ id: 9 }) });
    await typing.click(screen.getByRole('button', { name: "Narx qo'shish" }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Narxni kiriting (0 — bepul xona).')).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Narxi'), '350 000');
    await typing.selectOptions(screen.getByLabelText('Kun turi'), 'holiday');
    await typing.type(screen.getByLabelText('Boshlanish sanasi'), '2026-12-30');
    await typing.type(screen.getByLabelText('Tugash sanasi'), '2026-12-01');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Tugash sanasi boshlanish sanasidan oldin bo'lmasin.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
    await typing.clear(screen.getByLabelText('Tugash sanasi'));
    await typing.type(screen.getByLabelText('Tugash sanasi'), '2027-01-05');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Narx qo'shildi."));
    expect(await lastOf(calls, 'POST').json()).toEqual({
      branch_id: 1,
      dining_table_id: null,
      table_kind: 'room',
      hall_id: null,
      day_type: 'holiday',
      price: 350000,
      valid_from: '2026-12-30',
      valid_to: '2027-01-05',
    });
  });

  it('creates a tariff for one room and needs the room chosen', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/owner/room-tariffs': () => ok({ id: 9 }) });
    await typing.click(screen.getByRole('button', { name: "Narx qo'shish" }));
    await typing.selectOptions(screen.getByLabelText('Narx nimaga'), 'room');
    await typing.type(screen.getByLabelText('Narxi'), '0');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Xonani tanlang.')).toBeInTheDocument();
    await typing.selectOptions(screen.getByLabelText('Xona'), 'Xona 1 (Asosiy zal)');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await lastOf(calls, 'POST').json()).toMatchObject({ dining_table_id: 11, table_kind: null, price: 0, day_type: 'weekday' });
  });

  it('shows what the server refuses in the dialog', async () => {
    const { typing } = await open({ 'POST /api/v1/owner/room-tariffs': () => json({ success: false, message: 'Xato', errors: { price: ["Narx 0 dan kam bo'lmasin."] } }, 422) });
    await typing.click(screen.getByRole('button', { name: "Narx qo'shish" }));
    await typing.type(screen.getByLabelText('Narxi'), '5');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Narx 0 dan kam bo'lmasin.")).toBeInTheDocument();
  });

  it('deletes a tariff after asking', async () => {
    const { calls, typing } = await open({ 'DELETE /api/v1/owner/room-tariffs/1': () => ok(null) });
    await typing.click(screen.getByRole('button', { name: "Barcha xonalar · barcha zallar, Ish kuni: narxni o'chirish" }));
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE')).toBe(true));
    expect(await screen.findByText("Narx o'chirildi.")).toBeInTheDocument();
  });

  it('saves the weekend days and the holidays of the calendar', async () => {
    const { calls, typing } = await open({ 'PUT /api/v1/owner/room-tariffs/calendar': () => ok({ branch_id: 1, weekend_days: [5, 6, 7], holidays: ['2026-12-31', '2026-11-05'] }) });
    const save = screen.getByRole('button', { name: 'Kalendarni saqlash' });
    expect(save).toBeDisabled();
    await typing.click(screen.getByRole('checkbox', { name: 'Juma' }));
    await typing.type(screen.getByLabelText('Sana'), '2026-11-05');
    await typing.click(screen.getByRole('button', { name: "Qo'shish" }));
    expect(save).toBeEnabled();
    await typing.click(save);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Kalendar saqlandi.'));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ branch_id: 1, weekend_days: [5, 6, 7], holidays: ['2026-11-05', '2026-12-31'] });
  });

  it("shows and saves each branch's own calendar when the branch is switched (a cached branch too)", async () => {
    const calendarOf = (branch: number) => (branch === 1 ? { branch_id: 1, weekend_days: [6, 7], holidays: ['2026-12-31'] } : { branch_id: 2, weekend_days: [5], holidays: ['2027-03-21'] });
    const { calls, typing } = await open({
      'GET /api/v1/owner/branches': () => ok([makeBranch(), makeBranch({ id: 2, name: 'Ikkinchi' })]),
      'GET /api/v1/owner/room-tariffs': (request) => ok(tariffs({ calendar: calendarOf(Number(new URL(request.url).searchParams.get('branch_id'))) })),
      'PUT /api/v1/owner/room-tariffs/calendar': () => ok({ branch_id: 1, weekend_days: [1, 6, 7], holidays: ['2026-12-31'] }),
    });
    await screen.findByText(/2026-12-31/);
    const branch = screen.getAllByRole('combobox')[0]!;
    await typing.selectOptions(branch, '2');
    await screen.findByText(/2027-03-21/);
    expect(screen.getByRole('checkbox', { name: 'Juma' })).toBeChecked();
    await typing.selectOptions(branch, '1');
    expect(await screen.findByText(/2026-12-31/)).toBeInTheDocument();
    expect(screen.queryByText(/2027-03-21/)).not.toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Juma' })).not.toBeChecked();
    expect(screen.getByRole('button', { name: 'Kalendarni saqlash' })).toBeDisabled();
    await typing.click(screen.getByRole('checkbox', { name: 'Dushanba' }));
    await typing.click(screen.getByRole('button', { name: 'Kalendarni saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ branch_id: 1, weekend_days: [1, 6, 7], holidays: ['2026-12-31'] });
  });

  it('takes a holiday added elsewhere when the calendar is read again, and keeps what was being edited', async () => {
    let holidays = ['2026-12-31'];
    const { calls, typing } = await open({
      'GET /api/v1/owner/room-tariffs': () => ok(tariffs({ calendar: { branch_id: 1, weekend_days: [6, 7], holidays } })),
      'PUT /api/v1/owner/room-tariffs/calendar': () => ok({ branch_id: 1, weekend_days: [6, 7], holidays: ['2026-11-05', '2027-01-01', '2027-03-21'] }),
      'DELETE /api/v1/owner/room-tariffs/1': () => ok(null),
    });
    // The other tab and back: the tariffs are read again (cached ones are shown meanwhile)
    holidays = ['2026-12-31', '2027-01-01'];
    await typing.click(screen.getByRole('tab', { name: 'Zallar va joylar' }));
    await typing.click(screen.getByRole('tab', { name: 'Xona narxi' }));
    expect(await screen.findByText(/2027-01-01/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Kalendarni saqlash' })).toBeDisabled();
    // Removed and added here while the POS settings add another one: all three changes are kept
    await typing.click(screen.getByRole('button', { name: '2026-12-31 bayramini olib tashlash' }));
    await typing.type(screen.getByLabelText('Sana'), '2026-11-05');
    await typing.click(screen.getByRole('button', { name: "Qo'shish" }));
    // A tariff is deleted meanwhile: the tariffs and the calendar are read again with the card open
    holidays = ['2026-12-31', '2027-01-01', '2027-03-21'];
    await typing.click(screen.getByRole('button', { name: "Barcha xonalar · barcha zallar, Ish kuni: narxni o'chirish" }));
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "O'chirish" }));
    expect(await screen.findByText(/2027-03-21/)).toBeInTheDocument();
    expect(screen.getByText(/2026-11-05/)).toBeInTheDocument();
    expect(screen.queryByText(/2026-12-31/)).not.toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Kalendarni saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await lastOf(calls, 'PUT').json()).toEqual({ branch_id: 1, weekend_days: [6, 7], holidays: ['2026-11-05', '2027-01-01', '2027-03-21'] });
  });

  it('removes a holiday from the list before saving', async () => {
    const { typing } = await open();
    await typing.click(screen.getByRole('button', { name: '2026-12-31 bayramini olib tashlash' }));
    expect(screen.getByText("Bayram kuni yo'q.")).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Kalendarni saqlash' })).toBeEnabled();
  });
});

describe('Choy-non', () => {
  const open = async (over: Parameters<typeof mockFetch>[0] = {}) => {
    const mocked = base({ 'GET /api/v1/owner/products': () => json({ success: true, data: [product({ id: 30, name: 'Choy', variants: [variant({ id: 301, name: 'Choynak' })] })] }), ...over });
    const typing = userEvent.setup();
    renderPage(<FloorView />);
    await typing.click(await screen.findByRole('tab', { name: 'Choy-non' }));
    return { ...mocked, typing };
  };

  it('lists the rules and switches one off', async () => {
    const { calls, typing } = await open({
      'GET /api/v1/owner/auto-guest-items': () => ok([{ id: 1, branch_id: 1, hall_id: null, product_variant_id: 301, product_name: 'Choy', variant_name: 'Choynak', unit: 'piece', quantity_per_guest: 1, is_active: true, sort_order: 0 }]),
      'PATCH /api/v1/owner/auto-guest-items/1': () => ok({ id: 1 }),
    });
    expect(await screen.findByText('Choy · Choynak')).toBeInTheDocument();
    expect(screen.getByText('Barcha zallar')).toBeInTheDocument();
    await typing.click(screen.getByRole('switch', { name: 'Choy · Choynak: qoida faol' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await lastOf(calls, 'PATCH').json()).toEqual({ is_active: false });
  });

  it('needs a dish and a quantity above zero, then adds the rule', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/owner/auto-guest-items': () => ok({ id: 2 }) });
    await typing.click(await screen.findByRole('button', { name: "Qoida qo'shish" }));
    await typing.clear(screen.getByLabelText('Kishi boshiga miqdori'));
    await typing.type(screen.getByLabelText('Kishi boshiga miqdori'), '0');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Taomni tanlang.')).toBeInTheDocument();
    expect(screen.getByText("Miqdor noldan katta son bo'lishi kerak.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
    await typing.selectOptions(screen.getByLabelText('Taom'), 'Choy · Choynak');
    await typing.clear(screen.getByLabelText('Kishi boshiga miqdori'));
    await typing.type(screen.getByLabelText('Kishi boshiga miqdori'), '1,5');
    await typing.selectOptions(screen.getByLabelText('Zal'), 'Asosiy zal');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await lastOf(calls, 'POST').json()).toEqual({ branch_id: 1, product_variant_id: 301, quantity_per_guest: 1.5, hall_id: 1, is_active: true });
  });
});
