import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { booking, calendar, ok, ownerAnswer, signIn } from '../pos/testSupport';
import { BookingsView } from './BookingsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const detail = (over: Record<string, unknown> = {}) => ({
  ...booking(),
  items: [{ id: 1, product_variant_id: 301, product_name: 'Osh', variant_name: '1 porsiya', unit: 'piece', quantity: 20, modifiers: [], unit_price: '35000.00', note: null, due_at: '2026-10-22T14:00:00+00:00' }],
  payments: [
    {
      uuid: 'dep-1',
      amount: '200000.00',
      tender: 'cash',
      card_type: null,
      transaction_id: null,
      status: 'held',
      status_label: 'Ushlab turilmoqda',
      remaining: '200000.00',
      applied_amount: '0.00',
      refunded_amount: '50000.00',
      retained_amount: '0.00',
      fiscal_receipt_uuid: 'rc-1',
      fiscal_status: 'issued',
      settle_reason: null,
      settle_receipt_uuid: null,
      settle_fiscal_status: null,
      approved_by: null,
      user_id: 5,
      created_at: '2026-10-20T09:30:00+00:00',
      settled_at: null,
    },
  ],
  ...over,
});

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/pos/bookings/calendar': () => ok(calendar()),
    'GET /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001': () => ok(detail()),
    ...over,
  });

const UUID = 'b0000000-0000-4000-8000-000000000001';

// Opens the page on 22 October 2026, the day of the fixtures (the page starts on the real today)
const open = async (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) => {
  const mocked = base(over, keys);
  const typing = userEvent.setup();
  renderPage(<BookingsView />);
  const date = await screen.findByLabelText('Sana');
  fireEvent.change(date, { target: { value: '2026-10-22' } });
  await screen.findByRole('group', { name: 'Xona 1' });
  return { ...mocked, typing };
};

const lastCalendar = (calls: Request[]) => new URL(calls.filter((call) => new URL(call.url).pathname === '/api/v1/pos/bookings/calendar').at(-1)!.url).searchParams;
const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('the day line', () => {
  it('draws the rooms as rows with the booking where it is in time, its guests, status and deposit', async () => {
    const { calls } = await open();
    const room = screen.getByRole('group', { name: 'Xona 1' });
    const chip = within(room).getByRole('button', { name: /Aziz Karimov, 18:00–22:00, Tasdiqlangan/ });
    expect(chip).toHaveTextContent('20 kishi');
    expect(chip).toHaveTextContent('avans 200 000');
    // 18:00 on a line of 08:00-24:00 is 62.5% of the way
    expect(chip.style.left).toBe('62.5%');
    expect(chip.style.width).toBe('25%');
    expect(within(screen.getByRole('group', { name: 'Xona 2' })).queryByRole('button', { name: /Aziz/ })).not.toBeInTheDocument();
    const query = lastCalendar(calls);
    expect(Object.fromEntries(query)).toEqual({ branch_id: '1', from: '2026-10-22', to: '2026-10-22', include_canceled: 'false' });
  });

  it('asks for the canceled bookings too when ticked, and for a week in the week view', async () => {
    const { calls, typing } = await open();
    await typing.click(screen.getByRole('checkbox', { name: 'Bekor qilinganlar ham' }));
    await waitFor(() => expect(lastCalendar(calls).get('include_canceled')).toBe('true'));
    await typing.click(screen.getByRole('button', { name: 'Hafta' }));
    await waitFor(() => expect(lastCalendar(calls).get('from')).toBe('2026-10-19'));
    expect(lastCalendar(calls).get('to')).toBe('2026-10-25');
  });

  it('moves a day forward and back', async () => {
    const { calls, typing } = await open();
    await typing.click(screen.getByRole('button', { name: 'Keyingi' }));
    await waitFor(() => expect(lastCalendar(calls).get('from')).toBe('2026-10-23'));
    await typing.click(screen.getByRole('button', { name: 'Oldingi' }));
    await typing.click(screen.getByRole('button', { name: 'Oldingi' }));
    await waitFor(() => expect(lastCalendar(calls).get('from')).toBe('2026-10-21'));
  });

  it('shows a booking over midnight on the line of its first day, and lists a booking without a room apart', async () => {
    await open({
      'GET /api/v1/pos/bookings/calendar': () =>
        ok(
          calendar({
            rooms: [{ id: 11, name: 'Xona 1', kind: 'room', seats: 12, hall_id: 1, hall_name: 'Asosiy zal', is_active: true, min_spend: null, bookings: [booking({ starts_at: '2026-10-22T17:00:00+00:00', ends_at: '2026-10-22T20:00:00+00:00', contact_name: 'Kech' })] }],
            unassigned: [booking({ uuid: 'u1', dining_table_id: null, table_ids: [], contact_name: 'Xonasiz mehmon', status: 'requested', status_label: "So'rov" })],
          }),
        ),
    });
    expect(within(screen.getByRole('group', { name: 'Xona 1' })).getByRole('button', { name: /Kech, 22:00–01:00/ })).toBeInTheDocument();
    const unassigned = screen.getByLabelText('Xonasiz bronlar');
    expect(within(unassigned).getByRole('button', { name: /Xonasiz mehmon/ })).toBeInTheDocument();
  });

  it('says there are no rooms', async () => {
    base({ 'GET /api/v1/pos/bookings/calendar': () => ok(calendar({ rooms: [] })) });
    renderPage(<BookingsView />);
    expect(await screen.findByText("Xona yo'q")).toBeInTheDocument();
  });
});

describe('the week', () => {
  it('puts each booking in the column of its day', async () => {
    const { typing } = await open();
    await typing.click(screen.getByRole('button', { name: 'Hafta' }));
    const table = await screen.findByRole('table');
    const row = within(table).getByRole('row', { name: /Xona 1/ });
    const cells = within(row).getAllByRole('cell');
    // Monday 19 ... Thursday 22 is the fourth column
    expect(within(cells[3]!).getByRole('button', { name: /Aziz Karimov/ })).toBeInTheDocument();
    expect(within(cells[0]!).queryByRole('button', { name: /Aziz Karimov/ })).not.toBeInTheDocument();
  });
});

describe('making a booking', () => {
  it('asks for a name, a sensible end and a phone that is whole, and sends nothing meanwhile', async () => {
    const { calls, typing } = await open();
    await typing.click(screen.getByRole('button', { name: "Bron qo'shish" }));
    await typing.type(screen.getByLabelText('Telefon'), '90 12');
    fireEvent.change(screen.getByLabelText('Tugashi'), { target: { value: '18:00' } });
    await typing.type(screen.getByLabelText('Mehmonlar soni'), '0');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Ismini kiriting.')).toBeInTheDocument();
    expect(screen.getByText("Raqamni to'liq kiriting: 90 123 45 67")).toBeInTheDocument();
    expect(screen.getByText("Tugash vaqti boshlanish vaqtiga teng bo'lmasin.")).toBeInTheDocument();
    expect(screen.getByText("Mehmonlar soni 1 dan katta butun son bo'lishi kerak.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it('creates a booking in the room of the row, in the store time, confirmed at once', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/pos/bookings': () => ok(detail(), 201) });
    await typing.click(screen.getByRole('button', { name: "Xona 2: bron qo'shish" }));
    expect(screen.getByLabelText('Xona')).toHaveValue('13');
    await typing.type(screen.getByLabelText('Ismi (yoki guruh nomi)'), 'Dilnoza');
    await typing.type(screen.getByLabelText('Telefon'), '90 123 45 67');
    await typing.type(screen.getByLabelText('Mehmonlar soni'), '25');
    await typing.selectOptions(screen.getByLabelText('Tadbir turi'), 'korporativ');
    fireEvent.change(screen.getByLabelText('Boshlanishi'), { target: { value: '19:30' } });
    fireEvent.change(screen.getByLabelText('Tugashi'), { target: { value: '23:00' } });
    await typing.type(screen.getByLabelText('Minimal summa'), '1 500 000');
    await typing.click(screen.getByRole('checkbox', { name: /Darhol tasdiqlash/ }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Dilnoza uchun bron qo'shildi."));
    const request = last(calls, 'POST');
    expect(request.headers.get('Idempotency-Key')).toBeTruthy();
    expect(await request.json()).toEqual({
      branch_id: 1,
      dining_table_id: 13,
      extra_table_ids: [],
      starts_at: '2026-10-22T19:30:00',
      ends_at: '2026-10-22T23:00:00',
      guests_planned: 25,
      event_type: 'korporativ',
      contact_name: 'Dilnoza',
      contact_phone: '+998901234567',
      source: 'phone',
      min_spend: 1500000,
      note: null,
      confirm: true,
    });
  });

  it('ends the next day when the end time is before the start', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/pos/bookings': () => ok(detail(), 201) });
    await typing.click(screen.getByRole('button', { name: "Bron qo'shish" }));
    await typing.type(screen.getByLabelText('Ismi (yoki guruh nomi)'), 'Kech');
    fireEvent.change(screen.getByLabelText('Boshlanishi'), { target: { value: '22:00' } });
    fireEvent.change(screen.getByLabelText('Tugashi'), { target: { value: '01:00' } });
    expect(screen.getByText("Tugash vaqti ertasi kuniga o'tadi.")).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await last(calls, 'POST').json()).toMatchObject({ starts_at: '2026-10-22T22:00:00', ends_at: '2026-10-23T01:00:00', dining_table_id: null });
  });

  it('shows the booking in the way when the room is taken, and keeps the form', async () => {
    const { typing } = await open({
      'POST /api/v1/pos/bookings': () =>
        json({ success: false, message: 'Xona shu vaqtda band.', data: { reason: 'booking_conflict', conflicts: [{ uuid: 'x', status: 'confirmed', starts_at: '2026-10-22T13:00:00+00:00', ends_at: '2026-10-22T17:00:00+00:00', table_ids: [11] }] } }, 409),
    });
    await typing.click(screen.getByRole('button', { name: "Xona 1: bron qo'shish" }));
    await typing.type(screen.getByLabelText('Ismi (yoki guruh nomi)'), 'Dilnoza');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Xona shu vaqtda band.')).toBeInTheDocument();
    expect(screen.getByText('Xona shu vaqtda band:')).toBeInTheDocument();
    expect(screen.getByText('18:00–22:00 (Tasdiqlangan)')).toBeInTheDocument();
    expect(screen.getByLabelText('Ismi (yoki guruh nomi)')).toHaveValue('Dilnoza');
  });
});

describe('a booking', () => {
  const openBooking = async (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) => {
    const mocked = await open(over, keys);
    await mocked.typing.click(screen.getByRole('button', { name: /Aziz Karimov, 18:00–22:00/ }));
    const dialog = await screen.findByRole('dialog');
    await within(dialog).findByRole('heading', { name: 'Avanslar' });
    return { ...mocked, dialog };
  };

  it('shows its details, deposits with their fiscal status, and the pre-ordered lines', async () => {
    const { dialog } = await openBooking();
    expect(within(dialog).getByText('+998901234567')).toBeInTheDocument();
    expect(within(dialog).getByText('Tasdiqlangan')).toBeInTheDocument();
    const deposits = within(dialog).getByRole('table', { name: 'Avanslar' });
    expect(within(deposits).getAllByText("200 000 so'm")).toHaveLength(2); // the amount and what is left
    expect(within(deposits).getByText('Ushlab turilmoqda')).toBeInTheDocument();
    expect(within(deposits).getByText("qaytarildi: 50 000 so'm")).toBeInTheDocument();
    expect(within(deposits).getByText('issued')).toBeInTheDocument();
    expect(within(dialog).getByText(/Avans olish, qaytarish va ushlab qolish kassada bajariladi/)).toBeInTheDocument();
    expect(within(dialog).getByText('20 × Osh · 1 porsiya')).toBeInTheDocument();
  });

  it('edits with the version it was read at, so another device\'s change is not overwritten', async () => {
    const { calls, typing, dialog } = await openBooking({ 'PATCH /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001': () => ok(detail()) });
    await typing.click(within(dialog).getByRole('button', { name: 'Tahrirlash' }));
    const guests = screen.getByLabelText('Mehmonlar soni');
    expect(guests).toHaveValue('20');
    expect(screen.getByLabelText('Boshlanishi')).toHaveValue('18:00');
    await typing.clear(guests);
    await typing.type(guests, '30');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PATCH')).toBe(true));
    expect(await last(calls, 'PATCH').json()).toMatchObject({ guests_planned: 30, version: 3, starts_at: '2026-10-22T18:00:00', ends_at: '2026-10-22T22:00:00', contact_phone: '+998901234567' });
    expect(await screen.findByText('Aziz Karimov broni saqlandi.')).toBeInTheDocument();
  });

  it('tells the person a stale edit was refused', async () => {
    const { typing, dialog } = await openBooking({ 'PATCH /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001': () => json({ success: false, message: "Bron boshqa qurilmada o'zgartirilgan. Sahifani yangilang.", data: { reason: 'stale_version' } }, 409) });
    await typing.click(within(dialog).getByRole('button', { name: 'Tahrirlash' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Bron boshqa qurilmada o'zgartirilgan. Sahifani yangilang.")).toBeInTheDocument();
  });

  it('needs a reason to cancel, then cancels with it and the version', async () => {
    const { calls, typing, dialog } = await openBooking({ 'POST /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001/cancel': () => ok(detail({ status: 'canceled' })) });
    await typing.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));
    await typing.click(within(dialog).getByRole('button', { name: 'Bronni bekor qilish' }));
    expect(await within(dialog).findByText('Bekor qilish sababini yozing.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
    await typing.type(within(dialog).getByLabelText('Bekor qilish sababi', { selector: 'input' }), 'Mijoz bekor qildi');
    await typing.click(within(dialog).getByRole('button', { name: 'Bronni bekor qilish' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Aziz Karimov broni bekor qilindi.'));
    expect(await last(calls, 'POST').json()).toEqual({ reason: 'Mijoz bekor qildi', version: 3 });
  });

  it('confirms a requested booking and offers no "no-show" for it', async () => {
    const { calls, typing, dialog } = await openBooking({
      'GET /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001': () => ok(detail({ status: 'requested', status_label: "So'rov" })),
      'POST /api/v1/pos/bookings/b0000000-0000-4000-8000-000000000001/confirm': () => ok(detail({ status: 'confirmed' })),
    });
    expect(within(dialog).queryByRole('button', { name: 'Kelmadi' })).not.toBeInTheDocument();
    await typing.click(within(dialog).getByRole('button', { name: 'Tasdiqlash' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Aziz Karimov broni tasdiqlandi.'));
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1);
  });

  it('marks a confirmed booking "did not come", and shows the server\'s refusal when it is too early', async () => {
    const { typing, dialog } = await openBooking({ [`POST /api/v1/pos/bookings/${UUID}/no-show`]: () => json({ success: false, message: "Kelmadi deb faqat bron boshlangandan keyin belgilanadi." }, 422) });
    await typing.click(within(dialog).getByRole('button', { name: 'Kelmadi' }));
    expect(await within(dialog).findByText('Kelmadi deb faqat bron boshlangandan keyin belgilanadi.')).toBeInTheDocument();
  });

  it('can only be read once it is over: no edit, cancel or confirm', async () => {
    const { dialog } = await openBooking({ [`GET /api/v1/pos/bookings/${UUID}`]: () => ok(detail({ status: 'completed', status_label: 'Yakunlandi' })) });
    expect(within(dialog).queryByRole('button', { name: 'Tahrirlash' })).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Bekor qilish' })).not.toBeInTheDocument();
    expect(within(dialog).getAllByRole('button', { name: 'Yopish' }).length).toBeGreaterThan(0);
  });
});

describe('the confirmation the guest gets (P3-38)', () => {
  const TEXT = "Bron tasdiqlandi ✅\nChoyxona, Chilonzor\n📅 Payshanba, 22-oktabr, 18:00–22:00\n💵 Xona puli: 300 000 so'm (dam olish kuni narxi)";
  const confirmation = (over: Record<string, unknown> = {}) => ({
    text: TEXT,
    lines: [{ kind: 'room_fee', label: 'Xona puli', value: "300 000 so'm" }],
    room_fee: { amount: '300000.00', day_type: 'weekend', tariff_id: 4 },
    channels: { telegram: true, sms: false },
    ...over,
  });
  const CONF = `GET /api/v1/pos/bookings/${UUID}/confirmation`;
  const SEND = `POST /api/v1/pos/bookings/${UUID}/confirmation/send`;
  const openBooking = async (over: Parameters<typeof mockFetch>[0] = {}) => {
    const mocked = await open(over);
    await mocked.typing.click(screen.getByRole('button', { name: /Aziz Karimov, 18:00–22:00/ }));
    const dialog = await screen.findByRole('dialog');
    await within(dialog).findByRole('heading', { name: 'Avanslar' });
    return { ...mocked, dialog };
  };
  const section = (dialog: HTMLElement) => within(dialog).getByRole('region', { name: 'Tasdiq' });

  it('reads the text only when asked, and shows it with the room fee and the channels', async () => {
    const { calls, typing, dialog } = await openBooking({ [CONF]: () => ok(confirmation()) });
    expect(calls.some((call) => new URL(call.url).pathname.endsWith('/confirmation'))).toBe(false);
    await typing.click(within(section(dialog)).getByRole('button', { name: "Tasdiqni ko'rish" }));
    expect(await within(dialog).findByText(/Xona puli: 300 000 so'm \(dam olish kuni narxi\)/)).toBeInTheDocument();
    expect(within(dialog).getByText('Xona puli: 300 000 so\'m')).toBeInTheDocument();
    expect(within(dialog).getByText('Yuborish mumkin: Telegram')).toBeInTheDocument();
  });

  it('is offered for a confirmed booking only', async () => {
    const { dialog } = await openBooking({ [`GET /api/v1/pos/bookings/${UUID}`]: () => ok(detail({ status: 'requested', status_label: "So'rov" })) });
    expect(within(dialog).queryByRole('region', { name: 'Tasdiq' })).not.toBeInTheDocument();
  });

  it('sends with "auto" and says where it went, then shows when it was sent', async () => {
    let sent = false;
    const { calls, typing, dialog } = await openBooking({
      [CONF]: () => ok(confirmation()),
      [`GET /api/v1/pos/bookings/${UUID}`]: () => ok(detail(sent ? { confirmation_sent_at: '2026-10-22T14:42:00+00:00', confirmation_channel: 'telegram' } : {})),
      [SEND]: () => {
        sent = true;
        return ok({ channel: 'telegram', queued: true });
      },
    });
    await typing.click(within(section(dialog)).getByRole('button', { name: "Tasdiqni ko'rish" }));
    await typing.click(await within(dialog).findByRole('button', { name: 'Yuborish' }));
    expect(await within(dialog).findByText("Telegram'ga yuborildi.")).toBeInTheDocument();
    const post = calls.find((call) => call.method === 'POST' && call.url.endsWith('/confirmation/send'))!;
    expect(await post.json()).toEqual({ channel: 'auto' });
    expect(post.headers.get('Idempotency-Key')).toBeTruthy();
    // 14:42 UTC is 19:42 in Tashkent
    expect(await within(dialog).findByText('Tasdiq yuborilgan: 19:42, Telegram')).toBeInTheDocument();
  });

  it('shows no_channel as a clear message that says what to do', async () => {
    const { typing, dialog } = await openBooking({
      [CONF]: () => ok(confirmation({ channels: { telegram: false, sms: false } })),
      [SEND]: () => json({ success: false, message: "Kanal yo'q.", data: { reason: 'no_channel' } }, 422),
    });
    await typing.click(within(section(dialog)).getByRole('button', { name: "Tasdiqni ko'rish" }));
    expect(await within(dialog).findByText("Yuborish mumkin: yo'q (mehmon Telegram'da emas, SMS yoqilmagan)")).toBeInTheDocument();
    await typing.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent(/Yuborib bo'lmadi: mehmon Telegram'da yo'q, SMS esa filial sozlamalarida yoqilmagan/);
  });

  it("shows the server's own words for any other refusal", async () => {
    const { typing, dialog } = await openBooking({ [CONF]: () => ok(confirmation()), [SEND]: () => json({ success: false, message: 'Bron bekor qilingan.' }, 422) });
    await typing.click(within(section(dialog)).getByRole('button', { name: "Tasdiqni ko'rish" }));
    await typing.click(await within(dialog).findByRole('button', { name: 'Yuborish' }));
    expect(await within(dialog).findByText('Bron bekor qilingan.')).toBeInTheDocument();
  });

  it('can copy the text for a messenger of the cashier\'s own', async () => {
    const { typing, dialog } = await openBooking({ [CONF]: () => ok(confirmation()) });
    // user-event puts its own clipboard in place when it is set up
    const writeText = vi.spyOn(navigator.clipboard, 'writeText');
    await typing.click(within(section(dialog)).getByRole('button', { name: "Tasdiqni ko'rish" }));
    await typing.click(await within(dialog).findByRole('button', { name: 'Nusxa olish' }));
    await waitFor(() => expect(writeText).toHaveBeenCalledWith(TEXT));
    expect(await within(dialog).findByText('Matn nusxalandi.')).toBeInTheDocument();
  });
});

describe('access', () => {
  it('says so to someone without bookings.manage', async () => {
    base({}, ['orders.view']);
    renderPage(<BookingsView />);
    expect(await screen.findByText("Bronlarni ko'rish uchun ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Bron qo'shish" })).not.toBeInTheDocument();
  });

  it('shows the server\'s 403 when the sign-in is one the API does not take', async () => {
    base({ 'GET /api/v1/pos/bookings/calendar': () => json({ success: false, message: 'Ruxsat etilmagan' }, 403) });
    renderPage(<BookingsView />);
    expect(await screen.findByText('Ruxsat etilmagan')).toBeInTheDocument();
  });
});
