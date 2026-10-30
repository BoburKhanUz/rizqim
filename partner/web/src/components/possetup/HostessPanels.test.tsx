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
  profile: 'restoran',
  profile_label: 'Restoran',
  mode: 'table',
  first_screen: 'hall_map',
  modules,
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { profile: 'restoran', stations: [], dishes: [] },
});

const RULES = {
  branch_id: 1,
  configured: false,
  table_slot_minutes: 120,
  room_slot_minutes: null,
  tapchan_slot_minutes: null,
  table_min_advance_minutes: 0,
  room_min_advance_minutes: 0,
  tapchan_min_advance_minutes: 0,
  max_advance_days: null,
  deposit_mode: 'off',
  deposit_mode_label: "Bo'nak olinmaydi",
  deposit_amount: '0.00',
  deposit_from_guests: 1,
  deposit_required_to_confirm: false,
  updated_at: null,
};

const entry = (over: Record<string, unknown> = {}) => ({
  uuid: 'w1',
  branch_id: 1,
  business_date: '2026-11-05',
  guest_name: 'Akmal Karimov',
  guest_phone: null,
  guest_phone_masked: '+99890*****67',
  party_size: 4,
  quoted_wait_minutes: 20,
  note: null,
  status: 'seated',
  status_label: 'Joylashtirildi',
  position: null,
  waited_minutes: 35,
  dining_table_id: 7,
  table_name: 'Stol 5',
  order_uuid: null,
  notified_at: null,
  seated_at: '2026-11-05T14:35:00Z',
  closed_at: '2026-11-05T14:35:00Z',
  version: 3,
  created_at: '2026-11-05T14:00:00Z',
  ...over,
});

const SUMMARY = { total: 2, waiting: 0, seated: 1, canceled: 0, no_show: 1, party_size_total: 6, avg_quoted_wait_minutes: 25, avg_seated_wait_minutes: 35, longest_waiting_minutes: null };

const open = async (modules: string[], over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(formatOf(modules)),
    'GET /api/v1/owner/service-fees': () => ok({ channels: ['pos_hall'], settings: [] }),
    ...over,
  });
  renderPage(<PosSetupView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('the restaurant floor tabs', () => {
  it('are not there for a choyxona: its page keeps its five tabs', async () => {
    await open(['rooms', 'bookings']);
    await screen.findByRole('tab', { name: 'Format' });
    await waitFor(() => expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Format', 'Sexlar', "Yo'naltirish", 'Printerlar', 'Kassa sozlamalari']));
  });

  it('come with their modules', async () => {
    await open(['table_booking', 'hostess']);
    expect(await screen.findByRole('tab', { name: 'Stol broni' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: "Kutish ro'yxati" })).toBeInTheDocument();
  });
});

describe('Stol broni qoidalari', () => {
  it('shows the defaults and saves only what changed', async () => {
    const { typing, calls } = await open(['table_booking'], {
      'GET /api/v1/owner/branches/1/booking-rules': () => ok(RULES),
      'PUT /api/v1/owner/branches/1/booking-rules': () => ok({ ...RULES, configured: true, table_slot_minutes: 90, room_min_advance_minutes: 180 }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stol broni' }));

    const tableSlot = await screen.findByRole('textbox', { name: 'Stol (daqiqa)' });
    expect(tableSlot).toHaveValue('120');
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();

    await typing.clear(tableSlot);
    await typing.type(tableSlot, '90');
    await typing.clear(screen.getByRole('textbox', { name: 'Xona uchun (daqiqa)' }));
    await typing.type(screen.getByRole('textbox', { name: 'Xona uchun (daqiqa)' }), '180');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Stol broni sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ table_slot_minutes: 90, room_min_advance_minutes: 180 });
    expect(screen.getByRole('textbox', { name: 'Stol (daqiqa)' })).toHaveValue('90');
  });

  it('sends the deposit as money and keeps its fields off until a deposit is chosen', async () => {
    const { typing, calls } = await open(['table_booking'], {
      'GET /api/v1/owner/branches/1/booking-rules': () => ok(RULES),
      'PUT /api/v1/owner/branches/1/booking-rules': () => ok({ ...RULES, configured: true, deposit_mode: 'per_guest', deposit_amount: '50000.00', deposit_from_guests: 6, deposit_required_to_confirm: true }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stol broni' }));

    expect(await screen.findByRole('textbox', { name: "Bo'nak summasi" })).toBeDisabled();
    expect(screen.getByRole('checkbox', { name: /Bo'nak olinmaguncha tasdiqlanmasin/ })).toBeDisabled();

    await typing.selectOptions(screen.getByRole('combobox', { name: "Bo'nak turi" }), 'per_guest');
    const amount = screen.getByRole('textbox', { name: "Bo'nak summasi" });
    await typing.clear(amount);
    await typing.type(amount, '50 000');
    const guests = screen.getByRole('textbox', { name: "Necha kishidan boshlab" });
    await typing.clear(guests);
    await typing.type(guests, '6');
    await typing.click(screen.getByRole('checkbox', { name: /Bo'nak olinmaguncha tasdiqlanmasin/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    await screen.findByText('Stol broni sozlamalari saqlandi.');
    expect(await last(calls, 'PUT').json()).toEqual({ deposit_mode: 'per_guest', deposit_amount: '50000.00', deposit_from_guests: 6, deposit_required_to_confirm: true });
  });

  it('checks the limits before it asks the server, and shows the server\'s own refusal', async () => {
    const { typing, calls } = await open(['table_booking'], {
      'GET /api/v1/owner/branches/1/booking-rules': () => ok(RULES),
      'PUT /api/v1/owner/branches/1/booking-rules': () => json({ success: false, message: 'The given data was invalid.', errors: { max_advance_days: ["Eng ko'p oldindan kun noto'g'ri."] } }, 422),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stol broni' }));

    const tableSlot = await screen.findByRole('textbox', { name: 'Stol (daqiqa)' });
    await typing.clear(tableSlot);
    await typing.type(tableSlot, '10');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Daqiqa 30 dan 480 gacha butun son bo'lishi kerak.")).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(0);

    await typing.clear(tableSlot);
    await typing.type(tableSlot, '100');
    await typing.type(screen.getByRole('textbox', { name: "Eng ko'pi bilan (kun oldin)" }), '30');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Eng ko'p oldindan kun noto'g'ri.")).toBeInTheDocument();
  });
});

describe("Kutish ro'yxati (egaga ko'rinish)", () => {
  it('lists the days with the numbers masked, the summary and the over-quoted waits', async () => {
    const { typing, calls } = await open(['hostess'], {
      'GET /api/v1/owner/waitlist': () =>
        ok({
          branch_id: 1,
          from: '2026-11-05',
          to: '2026-11-05',
          entries: [entry(), entry({ uuid: 'w2', guest_name: 'Dilnoza', status: 'no_show', status_label: 'Kelmadi', party_size: 2, quoted_wait_minutes: 40, waited_minutes: 10, table_name: null, dining_table_id: null })],
          summary: SUMMARY,
        }),
    });
    await typing.click(await screen.findByRole('tab', { name: "Kutish ro'yxati" }));

    const table = await screen.findByRole('table', { name: "Kutish ro'yxati yozuvlari" });
    expect(within(table).getByText('Akmal Karimov')).toBeInTheDocument();
    expect(within(table).getAllByText('+99890*****67')).toHaveLength(2);
    expect(within(table).getByText('Joylashtirildi')).toBeInTheDocument();
    expect(within(table).getByText('aytilganidan uzoq')).toBeInTheDocument();
    expect(within(table).getByText('Stol 5')).toBeInTheDocument();

    const summary = screen.getByRole('region', { name: 'Xulosa' });
    expect(within(summary).getByText('Haqiqiy kutish (joylashganlar)').nextElementSibling).toHaveTextContent('35 daq');
    expect(within(summary).getByText('Aytilgan kutish').nextElementSibling).toHaveTextContent('25 daq');

    const sent = new URL(calls.find((call) => call.url.includes('/owner/waitlist'))!.url).searchParams;
    expect(sent.get('branch_id')).toBe('1');
    expect(sent.get('status')).toBeNull();
  });

  it('asks for one status and a range of days', async () => {
    const { typing, calls } = await open(['hostess'], {
      'GET /api/v1/owner/waitlist': () => ok({ branch_id: 1, from: '2026-11-01', to: '2026-11-05', entries: [], summary: { ...SUMMARY, total: 0, seated: 0, no_show: 0, party_size_total: 0, avg_quoted_wait_minutes: null, avg_seated_wait_minutes: null } }),
    });
    await typing.click(await screen.findByRole('tab', { name: "Kutish ro'yxati" }));
    expect(await screen.findByText("Kutish ro'yxati bo'sh")).toBeInTheDocument();

    await typing.selectOptions(screen.getByRole('combobox', { name: 'Holat' }), 'no_show');
    await typing.type(screen.getByLabelText('Dan'), '2026-11-01');
    await typing.type(screen.getByLabelText('Gacha'), '2026-11-05');
    await waitFor(() => {
      const params = new URL(calls.filter((call) => call.url.includes('/owner/waitlist')).at(-1)!.url).searchParams;
      expect([params.get('status'), params.get('from'), params.get('to')]).toEqual(['no_show', '2026-11-01', '2026-11-05']);
    });
  });
});
