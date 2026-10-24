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
    cashier_discount_max_percent: '10.00',
    alerts: { open_room_hours: 6, void_amount: '200000.00', discount_percent: '10.00', unaccepted_online_minutes: 3, cash_out_amount: '500000.00' },
    holidays: ['2026-12-31'],
    booking_slot_minutes: null,
    booking_confirmation: { auto_send: true, sms: false, cancel_policy: null },
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

  it('saves what was changed: an alert switched off is null, the slot is a number, the holidays are the list', async () => {
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
        queue_numbers: true,
        alerts: { discount_percent: null },
        booking_slot_minutes: 240,
        holidays: ['2026-11-05', '2026-12-31'],
      },
    });
  });

  it('can only be saved once something changed, and then sends only that', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()) });
    const save = await screen.findByRole('button', { name: 'Saqlash' });
    expect(save).toBeDisabled();
    await typing.clear(screen.getByLabelText("O'chirilgan taom summasi: qiymat"));
    await typing.type(screen.getByLabelText("O'chirilgan taom summasi: qiymat"), '150000');
    await typing.click(save);
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { alerts: { void_amount: 150000 } } });
  });

  it('shows the settings as read again, not the cached ones, and does not send the cached holidays back', async () => {
    let holidays = ['2026-12-31'];
    const current = () => posSettings({ pos_settings: { ...posSettings().pos_settings, holidays } });
    const { calls, typing } = await open('Kassa sozlamalari', {
      'GET /api/v1/owner/branches/1/pos-settings': () => ok(current()),
      'PUT /api/v1/owner/branches/1/pos-settings': () => ok(current()),
    });
    await screen.findByText(/2026-12-31/);
    await typing.click(screen.getByRole('tab', { name: 'Sexlar' }));
    // The floor's calendar (or another device) added a holiday; the form opens from the cache while it is read again
    holidays = ['2026-12-31', '2027-01-01'];
    await typing.click(screen.getByRole('tab', { name: 'Kassa sozlamalari' }));
    expect(await screen.findByText(/2027-01-01/)).toBeInTheDocument();
    await typing.clear(screen.getByLabelText('Ekran qulfi'));
    await typing.type(screen.getByLabelText('Ekran qulfi'), '10');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { screen_lock_minutes: 10 } });
  });

  it("shows the booking confirmation block as the server keeps it (P3-38): automatic, SMS off with its price note, no rule", async () => {
    await open('Kassa sozlamalari');
    expect(await screen.findByRole('heading', { name: "Bron tasdig'i" })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Bron tasdiqlanganda mehmonga avtomatik yuborilsin' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /SMS orqali ham yuborilsin/ })).not.toBeChecked();
    expect(screen.getByText(/SMS pullik: har bir xabar uchun SMS xizmati haq oladi/)).toBeInTheDocument();
    expect(screen.getByLabelText('Bekor qilish qoidasi')).toHaveValue('');
  });

  it('uses the defaults when the server does not send the block yet', async () => {
    await open('Kassa sozlamalari', { 'GET /api/v1/owner/branches/1/pos-settings': () => ok(posSettings({ pos_settings: { ...posSettings().pos_settings, booking_confirmation: undefined } })) });
    expect(await screen.findByRole('checkbox', { name: 'Bron tasdiqlanganda mehmonga avtomatik yuborilsin' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /SMS orqali ham yuborilsin/ })).not.toBeChecked();
  });

  it('sends only the confirmation keys that changed: SMS on and the cancellation rule trimmed', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()) });
    await typing.click(await screen.findByRole('checkbox', { name: /SMS orqali ham yuborilsin/ }));
    await typing.type(screen.getByLabelText('Bekor qilish qoidasi'), " Bekor qilish bron vaqtidan 24 soat oldin; bo'nak qaytariladi ");
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { booking_confirmation: { sms: true, cancel_policy: "Bekor qilish bron vaqtidan 24 soat oldin; bo'nak qaytariladi" } } });
  });

  it('switches the automatic send off alone, and clears the rule with null', async () => {
    const withRule = posSettings({ pos_settings: { ...posSettings().pos_settings, booking_confirmation: { auto_send: true, sms: true, cancel_policy: '24 soat oldin' } } });
    const { calls, typing } = await open('Kassa sozlamalari', { 'GET /api/v1/owner/branches/1/pos-settings': () => ok(withRule), 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(withRule) });
    await typing.click(await screen.findByRole('checkbox', { name: 'Bron tasdiqlanganda mehmonga avtomatik yuborilsin' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { booking_confirmation: { auto_send: false } } });
    await typing.clear(screen.getByLabelText('Bekor qilish qoidasi'));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { booking_confirmation: { cancel_policy: null } } });
  });

  it("shows the server's refusal of the cancellation rule next to its field", async () => {
    const { typing } = await open('Kassa sozlamalari', {
      'PUT /api/v1/owner/branches/1/pos-settings': () => json({ success: false, message: 'The given data was invalid.', errors: { 'pos_settings.booking_confirmation.cancel_policy': ['Qoida juda uzun.'] } }, 422),
    });
    await typing.type(await screen.findByLabelText('Bekor qilish qoidasi'), 'Qoida');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Qoida juda uzun.')).toBeInTheDocument();
  });

  it("shows the cashier's discount limit and the cash-out alert as the server keeps them (P3-37)", async () => {
    await open('Kassa sozlamalari');
    expect(await screen.findByLabelText("Kassir menejersiz beradigan eng katta chegirma, %")).toHaveValue('10');
    expect(screen.getByText("Kassir shu foizgacha chegirmani o'zi beradi. Bo'sh qoldirsangiz, kassir har safar menejer PIN kodini so'raydi.")).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Kassadan katta summa chiqarildi' })).toBeChecked();
    expect(screen.getByLabelText('Kassadan katta summa chiqarildi: qiymat')).toHaveValue('500000');
    expect(screen.getByText("Kassadan shu summadan ko'p pul chiqarilsa xabar bering")).toBeInTheDocument();
  });

  it('sends only the discount limit when only it changed, a number with up to two decimals', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()) });
    const field = await screen.findByLabelText("Kassir menejersiz beradigan eng katta chegirma, %");
    await typing.clear(field);
    await typing.type(field, '12,5');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { cashier_discount_max_percent: 12.5 } });
  });

  it('sends null when the limit is emptied: the cashier then always needs a manager', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()) });
    await typing.clear(await screen.findByLabelText("Kassir menejersiz beradigan eng katta chegirma, %"));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { cashier_discount_max_percent: null } });
  });

  it('refuses a discount limit above 100 or with three decimals before saving', async () => {
    const { calls, typing } = await open('Kassa sozlamalari');
    const field = await screen.findByLabelText("Kassir menejersiz beradigan eng katta chegirma, %");
    await typing.clear(field);
    await typing.type(field, '101');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("0 dan 100 gacha son kiriting (ikki xonagacha kasr bilan) yoki bo'sh qoldiring.")).toBeInTheDocument();
    await typing.clear(field);
    await typing.type(field, '5.123');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(screen.getByText("0 dan 100 gacha son kiriting (ikki xonagacha kasr bilan) yoki bo'sh qoldiring.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('sends the cash-out alert as a number under alerts, and null when it is switched off', async () => {
    const { calls, typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(posSettings()) });
    const value = await screen.findByLabelText('Kassadan katta summa chiqarildi: qiymat');
    await typing.clear(value);
    await typing.type(value, '300 000');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { alerts: { cash_out_amount: 300000 } } });

    await typing.click(screen.getByRole('checkbox', { name: 'Kassadan katta summa chiqarildi' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { alerts: { cash_out_amount: null } } });
  });

  it('shows an older server that does not know the new settings as empty and off, and sends nothing for them', async () => {
    const old = posSettings();
    delete (old.pos_settings as Record<string, unknown>).cashier_discount_max_percent;
    delete (old.pos_settings.alerts as Record<string, unknown>).cash_out_amount;
    const { calls, typing } = await open('Kassa sozlamalari', { 'GET /api/v1/owner/branches/1/pos-settings': () => ok(old), 'PUT /api/v1/owner/branches/1/pos-settings': () => ok(old) });
    expect(await screen.findByLabelText("Kassir menejersiz beradigan eng katta chegirma, %")).toHaveValue('');
    expect(screen.getByRole('checkbox', { name: 'Kassadan katta summa chiqarildi' })).not.toBeChecked();
    await typing.clear(screen.getByLabelText('Ekran qulfi'));
    await typing.type(screen.getByLabelText('Ekran qulfi'), '7');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ pos_settings: { screen_lock_minutes: 7 } });
  });

  it("shows the server's refusal of a value under its field", async () => {
    const { typing } = await open('Kassa sozlamalari', { 'PUT /api/v1/owner/branches/1/pos-settings': () => json({ success: false, message: 'Xato', errors: { business_day_starts_at: ["Biznes kun 00:00 dan 11:59 gacha boshlanadi."] } }, 422) });
    fireTime(await screen.findByLabelText('Biznes kun boshlanishi'), '04:00');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Biznes kun 00:00 dan 11:59 gacha boshlanadi.')).toBeInTheDocument();
  });
});

function fireTime(input: HTMLElement, value: string) {
  // A time input takes its value in one go (typing into it is the browser's picker)
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
  setter.call(input, value);
  input.dispatchEvent(new Event('input', { bubbles: true }));
}
