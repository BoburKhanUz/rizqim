import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { category, product, variant } from '../menu/menuFixtures';
import { device, ok, ownerAnswer, printer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const station = (over: Record<string, unknown> = {}) => ({ id: 1, branch_id: 1, name: 'Osh sexi', kind: 'osh', kind_label: 'Osh', sort_order: 0, is_active: true, show_on_kds: true, ...over });
const KINDS = ['osh', 'mangal', 'salad_bar', 'tea', 'kitchen', 'other'].map((value) => ({ value, label: value }));

const posSettings = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  timezone: 'Asia/Tashkent',
  business_day_starts_at: '05:00',
  pos_settings: {
    mode: 'hybrid',
    printing_device_id: null,
    screen_lock_minutes: 5,
    queue_numbers: false,
    alerts: { open_room_hours: 6, void_amount: '200000.00', discount_percent: '10.00', unaccepted_online_minutes: 3 },
    holidays: ['2026-12-31'],
    booking_slot_minutes: null,
  },
  ...over,
});

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: KINDS, stations: [station(), station({ id: 2, name: 'Mangal', kind: 'mangal', is_active: false, show_on_kds: false })] }),
    'GET /api/v1/owner/kitchen-station-routes': () => ok([{ id: 1, branch_id: 1, station_id: 1, category_id: 2, category_name: 'Issiq taomlar', product_id: null, product_name: null }]),
    'GET /api/v1/owner/printers': () => ok([printer(), printer({ id: 2, name: 'Oshxona', purpose: 'kitchen', connection: 'usb', address: null, port: null, device_id: 'dev-1', station_id: 1, paper_width: 58 })]),
    'GET /api/v1/owner/devices': () => ok([device(), device({ uuid: 'dev-2', name: 'Kassa 2' }), device({ uuid: 'dev-3', name: 'Oshxona ekrani', type: 'kds' })]),
    'GET /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()),
    'GET /api/v1/owner/categories': () => json({ success: true, data: [category(), category({ id: 5, name: 'Ichimliklar' })] }),
    'GET /api/v1/owner/products': () => json({ success: true, data: [product({ id: 7, name: 'Osh', variants: [variant()] }), product({ id: 8, name: 'Shashlik', category_id: 5, variants: [variant({ id: 12, product_id: 8 })] })] }),
    ...over,
  });

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

const open = async (tab: string, over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = base(over);
  const typing = userEvent.setup();
  renderPage(<PosSetupView />);
  await typing.click(await screen.findByRole('tab', { name: tab }));
  return { ...mocked, typing };
};

describe('Kassa sozlamalari: access', () => {
  it('is for the owner: another role is told so', async () => {
    base({}, ['orders.view']);
    renderPage(<PosSetupView />);
    expect(await screen.findByText("Kassa sozlamalarini faqat do'kon egasi o'zgartira oladi.")).toBeInTheDocument();
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument();
  });
});

describe('Sexlar', () => {
  it('lists the stations, the inactive one marked by its switch', async () => {
    const { typing } = await open('Sexlar');
    expect(await screen.findByText('Osh sexi')).toBeInTheDocument();
    expect(screen.getByRole('switch', { name: 'Osh sexi: sex faol' })).toBeChecked();
    expect(screen.getByRole('switch', { name: 'Mangal: sex faol' })).not.toBeChecked();
    expect(screen.getByRole('switch', { name: 'Mangal: oshxona ekranida ko\'rsatiladi' })).not.toBeChecked();
    void typing;
  });

  it('asks for a name, then adds a station of a kind to the branch', async () => {
    const { calls, typing } = await open('Sexlar', { 'POST /api/v1/owner/kitchen-stations': () => ok(station({ id: 3, name: 'Salat bar' })) });
    await typing.click(await screen.findByRole('button', { name: "Sex qo'shish" }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Nomini kiriting.')).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Nomi'), 'Salat bar');
    await typing.selectOptions(screen.getByLabelText('Turi'), 'salad_bar');
    await typing.click(screen.getByRole('checkbox', { name: /Oshxona ekranida ko'rsatilsin/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Salat bar sexi qo'shildi."));
    expect(await last(calls, 'POST').json()).toEqual({ branch_id: 1, name: 'Salat bar', kind: 'salad_bar', is_active: true, show_on_kds: false });
  });

  it('shows the field message of a name that is taken', async () => {
    const { typing } = await open('Sexlar', { 'POST /api/v1/owner/kitchen-stations': () => json({ success: false, message: 'Xato', errors: { name: ['Bu nomli sex filialda bor.'] } }, 422) });
    await typing.click(await screen.findByRole('button', { name: "Sex qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), 'Osh sexi');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Bu nomli sex filialda bor.')).toBeInTheDocument();
  });

  it('switches a station on and off', async () => {
    const { calls, typing } = await open('Sexlar', { 'PATCH /api/v1/owner/kitchen-stations/2': () => ok(station({ id: 2 })) });
    await typing.click(await screen.findByRole('switch', { name: 'Mangal: sex faol' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).toEqual({ is_active: true });
  });

  it('says why a station that took an order cannot be deleted', async () => {
    const { typing } = await open('Sexlar', { 'DELETE /api/v1/owner/kitchen-stations/1': () => json({ success: false, message: "Sexga buyurtma yuborilgan. Uni o'chirmang, faolsizlantiring.", data: { reason: 'station_in_use' } }, 422) });
    await typing.click(await screen.findByRole('button', { name: "Osh sexi: sexni o'chirish" }));
    const dialog = await screen.findByRole('alertdialog');
    await typing.click(within(dialog).getByRole('button', { name: "O'chirish" }));
    expect(await within(dialog).findByText("Sexga buyurtma yuborilgan. Uni o'chirmang, faolsizlantiring.")).toBeInTheDocument();
  });
});

describe("Yo'naltirish", () => {
  it('shows where each category and dish goes now, and assigns the ticked ones to a station in one request', async () => {
    const { calls, typing } = await open("Yo'naltirish", { 'PUT /api/v1/owner/kitchen-station-routes': () => ok([]) });
    const categories = await screen.findByRole('group', { name: 'Kategoriyalar' });
    // Issiq taomlar is on the Osh station; its dish Osh goes there via the category
    expect(within(categories).getByText('Osh sexi')).toBeInTheDocument();
    expect(within(screen.getByRole('group', { name: 'Taomlar' })).getByText('Kategoriya orqali: Osh sexi')).toBeInTheDocument();
    const assign = screen.getByRole('button', { name: /^Biriktirish/ });
    expect(assign).toBeDisabled();
    await typing.click(within(categories).getByRole('checkbox', { name: /Ichimliklar/ }));
    await typing.click(within(screen.getByRole('group', { name: 'Taomlar' })).getByRole('checkbox', { name: /Osh/ }));
    await typing.selectOptions(screen.getByLabelText('Sex'), 'Osh sexi');
    expect(assign).toHaveTextContent('Biriktirish (2)');
    await typing.click(assign);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('2 ta element «Osh sexi» sexiga biriktirildi.'));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, station_id: 1, category_ids: [5], product_ids: [7] });
  });

  it('does not offer a switched-off station, and takes items off a station with null', async () => {
    const { calls, typing } = await open("Yo'naltirish", { 'PUT /api/v1/owner/kitchen-station-routes': () => ok([]) });
    const select = await screen.findByLabelText('Sex');
    expect(within(select).queryByRole('option', { name: 'Mangal' })).not.toBeInTheDocument();
    await typing.click(within(screen.getByRole('group', { name: 'Kategoriyalar' })).getByRole('checkbox', { name: /Issiq taomlar/ }));
    await typing.click(screen.getByRole('button', { name: 'Sexdan olib tashlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, station_id: null, category_ids: [2], product_ids: [] });
    expect(await screen.findByText('1 ta elementning sexi olib tashlandi.')).toBeInTheDocument();
  });

  it('shows the server\'s refusal', async () => {
    const { typing } = await open("Yo'naltirish", { 'PUT /api/v1/owner/kitchen-station-routes': () => json({ success: false, message: "Bir martada 200 tagacha kategoriya biriktiriladi." }, 422) });
    await typing.click(within(await screen.findByRole('group', { name: 'Kategoriyalar' })).getByRole('checkbox', { name: /Ichimliklar/ }));
    await typing.selectOptions(screen.getByLabelText('Sex'), 'Osh sexi');
    await typing.click(screen.getByRole('button', { name: /^Biriktirish/ }));
    expect(await screen.findByText('Bir martada 200 tagacha kategoriya biriktiriladi.')).toBeInTheDocument();
  });

  it('asks for a station first when there is none', async () => {
    await open("Yo'naltirish", { 'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: KINDS, stations: [] }) });
    expect(await screen.findByText("Avval sex qo'shing")).toBeInTheDocument();
  });
});

describe('Printerlar', () => {
  it('lists the printers with where they are reached: an address, or the terminal they belong to', async () => {
    await open('Printerlar');
    const table = await screen.findByRole('table');
    expect(within(table).getByText('192.168.0.50:9100')).toBeInTheDocument();
    expect(within(table).getByText('Kassa 1')).toBeInTheDocument();
    expect(within(table).getByText('58 mm')).toBeInTheDocument();
    expect(within(table).getByText('Osh sexi')).toBeInTheDocument();
  });

  it('needs an address for a LAN printer and sends only the fields its connection uses', async () => {
    const { calls, typing } = await open('Printerlar', { 'POST /api/v1/owner/printers': () => ok(printer({ id: 3 })) });
    await typing.click(await screen.findByRole('button', { name: "Printer qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), 'Zal printeri');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Manzilni kiriting.')).toBeInTheDocument();
    await typing.type(screen.getByLabelText('Manzil'), '192.168.0.77');
    await typing.type(screen.getByLabelText('Port'), '99999');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Port 1 dan 65535 gacha butun son bo'lishi kerak.")).toBeInTheDocument();
    await typing.clear(screen.getByLabelText('Port'));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await last(calls, 'POST').json()).toEqual({
      branch_id: 1,
      name: 'Zal printeri',
      purpose: 'receipt',
      connection: 'lan',
      address: '192.168.0.77',
      port: null,
      device_id: null,
      paper_width: 80,
      station_id: null,
      encoding: null,
      print_as_image: false,
      is_active: true,
    });
  });

  it('asks for a POS terminal (not an address) for a USB printer, and offers only the branch\'s POS terminals', async () => {
    const { calls, typing } = await open('Printerlar', { 'POST /api/v1/owner/printers': () => ok(printer({ id: 3 })) });
    await typing.click(await screen.findByRole('button', { name: "Printer qo'shish" }));
    await typing.type(screen.getByLabelText('Nomi'), 'USB printer');
    await typing.selectOptions(screen.getByLabelText('Ulanish'), 'usb');
    expect(screen.queryByLabelText('Manzil')).not.toBeInTheDocument();
    const terminals = screen.getByLabelText('Kassa terminali');
    expect(within(terminals).getAllByRole('option').map((option) => option.textContent)).toEqual(['Terminalni tanlang', 'Kassa 1', 'Kassa 2']);
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Kassa terminalini tanlang.')).toBeInTheDocument();
    await typing.selectOptions(terminals, 'Kassa 2');
    await typing.selectOptions(screen.getByLabelText('Vazifasi'), 'kitchen');
    await typing.selectOptions(screen.getByLabelText('Sex'), 'Osh sexi');
    await typing.selectOptions(screen.getByLabelText("Qog'oz kengligi"), '58');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await last(calls, 'POST').json()).toMatchObject({ connection: 'usb', address: null, port: null, device_id: 'dev-2', purpose: 'kitchen', station_id: 1, paper_width: 58 });
  });

  it('edits and deletes a printer', async () => {
    const { calls, typing } = await open('Printerlar', { 'PATCH /api/v1/owner/printers/1': () => ok(printer()), 'DELETE /api/v1/owner/printers/2': () => ok(null) });
    await typing.click(await screen.findByRole('button', { name: 'Kassa printeri: printerni tahrirlash' }));
    expect(screen.getByLabelText('Manzil')).toHaveValue('192.168.0.50');
    await typing.click(screen.getByRole('checkbox', { name: /Matnni rasm sifatida chop etish/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).toMatchObject({ print_as_image: true, connection: 'lan', address: '192.168.0.50' });
    await typing.click(await screen.findByRole('button', { name: "Oshxona: printerni o'chirish" }));
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE')).toBe(true));
  });
});

describe('Kassa sozlamalari (filial)', () => {
  it('shows the branch settings as the server keeps them', async () => {
    await open('Kassa sozlamalari');
    expect(await screen.findByLabelText('Kassa rejimi')).toHaveValue('hybrid');
    expect(screen.getByLabelText('Biznes kun boshlanishi')).toHaveValue('05:00');
    expect(screen.getByLabelText('Ekran qulfi')).toHaveValue('5');
    expect(screen.getByRole('checkbox', { name: 'Xona uzoq ochiq' })).toBeChecked();
    expect(screen.getByLabelText("Xona uzoq ochiq: qiymat")).toHaveValue('6');
    expect(screen.getByLabelText("O'chirilgan taom summasi: qiymat")).toHaveValue('200000');
    expect(screen.getByText('Aralash: ikkalasi ham, kassir tanlaydi (tavsiya etiladi).')).toBeInTheDocument();
    expect(screen.getByText(/31-dek|31 дек|Dec 31/)).toBeInTheDocument();
  });

  it('checks the day start, the lock, the booking slot and the alert limits before saving', async () => {
    const { calls, typing } = await open('Kassa sozlamalari');
    await typing.clear(await screen.findByLabelText('Ekran qulfi'));
    await typing.type(screen.getByLabelText('Ekran qulfi'), '500');
    await typing.type(screen.getByLabelText('Bron ushlab turish vaqti'), '10');
    await typing.clear(screen.getByLabelText('Xona uzoq ochiq: qiymat'));
    await typing.type(screen.getByLabelText('Xona uzoq ochiq: qiymat'), '100');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('0 dan 240 gacha butun son kiriting.')).toBeInTheDocument();
    expect(screen.getByText('30 dan 1440 gacha butun son kiriting.')).toBeInTheDocument();
    expect(screen.getByText('1 dan 72 gacha son kiriting.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('saves the whole settings: an alert switched off is null, the slot is a number, the holidays are the list', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings({ business_day_starts_at: '04:00' })) });
    await typing.selectOptions(await screen.findByLabelText('Kassa rejimi'), 'table');
    fireTime(screen.getByLabelText('Biznes kun boshlanishi'), '04:00');
    await typing.click(screen.getByRole('checkbox', { name: 'Chegirma foizi' }));
    await typing.type(screen.getByLabelText('Bron ushlab turish vaqti'), '240');
    await typing.selectOptions(screen.getByLabelText('Chop etuvchi kassa'), 'Kassa 1');
    await typing.click(screen.getByRole('checkbox', { name: /Navbat raqamlari/ }));
    await typing.type(screen.getByLabelText('Sana'), '2026-11-05');
    await typing.click(screen.getByRole('button', { name: "Qo'shish" }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Kassa sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({
      business_day_starts_at: '04:00',
      pos_settings: {
        mode: 'table',
        printing_device_id: 'dev-1',
        screen_lock_minutes: 5,
        queue_numbers: true,
        alerts: { open_room_hours: 6, void_amount: 200000, discount_percent: null, unaccepted_online_minutes: 3 },
        booking_slot_minutes: 240,
        holidays: ['2026-11-05', '2026-12-31'],
      },
    });
  });

  it("shows the server's refusal of a value under its field", async () => {
    const { typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => json({ success: false, message: 'Xato', errors: { business_day_starts_at: ["Biznes kun 00:00 dan 11:59 gacha boshlanadi."] } }, 422) });
    await typing.click(await screen.findByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Biznes kun 00:00 dan 11:59 gacha boshlanadi.')).toBeInTheDocument();
  });
});

function fireTime(input: HTMLElement, value: string) {
  // A time input takes its value in one go (typing into it is the browser's picker)
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
  setter.call(input, value);
  input.dispatchEvent(new Event('input', { bubbles: true }));
}
