import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { makeBranch, makeDevice, ok, permissions, refusal, signIn } from './testSupport';
import { SettingsView } from './SettingsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => {
  setSession(null);
  vi.useRealTimers();
});

const SETTINGS = {
  id: 1,
  name: 'Osh markazi',
  timezone: 'Asia/Tashkent',
  logo: 'store_assets/logo.png',
  logo_url: 'http://x/storage/store_assets/logo.png',
  banner: null,
  banner_url: null,
  description: 'Eng yaxshi osh',
  phone: '+998712000000',
  address: 'Amir Temur 1',
  location_url: 'https://yandex.com/maps/?pt=69.27,41.31',
  is_open: true,
  min_order_amount: 30000,
  payment_settings: {
    delivery_fee: 12000,
    delivery_enabled: true,
    pickup_enabled: false,
    working_hours: { open: '09:00', close: '23:00' },
    cash: { enabled: true },
    click: { enabled: true },
    payme: { enabled: false },
    uzum: { enabled: false, available: false },
    paynet: { enabled: false, available: false },
  },
  payment_account_type: 'platform_account',
  status: 'active',
  is_order_locked: false,
  allowed_methods: ['cash', 'click', 'payme'],
};

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissions(keys),
    'GET /api/v1/owner/settings': () => ok(SETTINGS),
    'GET /api/v1/owner/branches': () => ok([makeBranch(), makeBranch({ id: 2, name: 'Yunusobod' })]),
    ...over,
  });

describe('SettingsView: the store tab', () => {
  it('shows the stored values and saves the name and timezone together with the stored logo and the other values', async () => {
    const { calls } = base({ 'POST /api/v1/owner/settings': () => ok(null) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);

    expect(await screen.findByLabelText("Do'kon nomi")).toHaveValue('Osh markazi');
    expect(screen.getByLabelText('Vaqt mintaqasi')).toHaveValue('Asia/Tashkent');
    expect(screen.getByLabelText("Do'kon telefoni")).toHaveValue('71 200 00 00');
    expect(screen.getByLabelText('Yetkazish narxi')).toHaveValue('12000');
    expect(screen.getByLabelText('Ochilish vaqti')).toHaveValue('09:00');
    expect(screen.getByRole('checkbox', { name: /^Olib ketish/ })).not.toBeChecked();

    await typing.clear(screen.getByLabelText("Do'kon nomi"));
    await typing.type(screen.getByLabelText("Do'kon nomi"), 'Yangi nom');
    await typing.clear(screen.getByLabelText('Vaqt mintaqasi'));
    await typing.type(screen.getByLabelText('Vaqt mintaqasi'), 'Asia/Samarkand');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Sozlamalar saqlandi.');
    const body = await calls.find((call) => call.method === 'POST')!.json();
    expect(body).toEqual({
      name: 'Yangi nom',
      timezone: 'Asia/Samarkand',
      phone: '+998712000000',
      logo: 'store_assets/logo.png',
      banner: null,
      description: 'Eng yaxshi osh',
      address: 'Amir Temur 1',
      location_url: 'https://yandex.com/maps/?pt=69.27,41.31',
      is_open: true,
      min_order_amount: 30000,
      payment_settings: {
        delivery_fee: 12000,
        delivery_enabled: true,
        pickup_enabled: false,
        working_hours: { open: '09:00', close: '23:00' },
        cash: { enabled: true },
        click: { enabled: true },
        payme: { enabled: false },
        uzum: { enabled: false },
        paynet: { enabled: false },
      },
    });
  });

  it('toggles payment methods, keeps the not-yet-available ones off and shows the server refusal of a timezone', async () => {
    const { calls } = base({ 'POST /api/v1/owner/settings': () => refusal('Xato', 422, { errors: { timezone: ["Vaqt mintaqasi noto'g'ri (masalan Asia/Tashkent)."] } }) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await screen.findByLabelText("Do'kon nomi");

    expect(screen.getByRole('checkbox', { name: 'Uzum Bank' })).toBeDisabled();
    expect(screen.getByRole('checkbox', { name: 'Paynet' })).toBeDisabled();
    expect(screen.getAllByText('Tez orada')).toHaveLength(2);
    await typing.click(screen.getByRole('checkbox', { name: 'Payme' }));
    await typing.click(screen.getByRole('checkbox', { name: 'Click' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText("Vaqt mintaqasi noto'g'ri (masalan Asia/Tashkent).")).toBeInTheDocument();
    const body = (await calls.find((call) => call.method === 'POST')!.json()) as { payment_settings: Record<string, unknown> };
    expect(body.payment_settings).toMatchObject({ payme: { enabled: true }, click: { enabled: false }, uzum: { enabled: false }, paynet: { enabled: false } });
  });

  it('checks the name and the numbers before saving', async () => {
    const { calls } = base();
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.clear(await screen.findByLabelText("Do'kon nomi"));
    await typing.clear(screen.getByLabelText('Eng kam buyurtma'));
    await typing.type(screen.getByLabelText('Eng kam buyurtma'), 'ko\'p');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(screen.getByText("Do'kon nomini kiriting.")).toBeInTheDocument();
    expect(screen.getByText('Son kiriting (masalan, 12000).')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it('shows the error with a retry when the settings do not load', async () => {
    base({ 'GET /api/v1/owner/settings': () => refusal('Ruxsat etilmagan', 403) });
    renderPage(<SettingsView />);
    expect(await screen.findByRole('alert')).toHaveTextContent('Ruxsat etilmagan');
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  it('shows only the tabs the permissions open', async () => {
    base({ 'GET /api/v1/owner/devices': () => ok([]) }, ['devices.view']);
    renderPage(<SettingsView />);
    await waitFor(() => expect(screen.queryByRole('tab', { name: "Do'kon" })).not.toBeInTheDocument());
    expect(screen.getByRole('tab', { name: 'Qurilmalar' })).toHaveAttribute('aria-selected', 'true');
  });
});

describe('SettingsView: service charge and place fee', () => {
  const FEES = {
    channels: ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'],
    settings: [
      { branch_id: null, service_charge_percent: '10.00', service_charge_channels: null, place_fee_amount: '5000.00', place_fee_mode: 'per_person', place_fee_channels: ['pos_hall', 'pos_takeaway'] },
      { branch_id: 2, service_charge_percent: '5.00', service_charge_channels: ['pos_hall'], place_fee_amount: '0.00', place_fee_mode: 'per_table', place_fee_channels: null },
    ],
  };

  it("shows the store's values, never offers Telegram, and saves the store's row", async () => {
    const { calls } = base({ 'GET /api/v1/owner/service-fees': () => ok(FEES), 'PUT /api/v1/owner/service-fees': () => ok(FEES.settings[0]) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Xizmat haqi' }));

    expect(await screen.findByLabelText('Foiz')).toHaveValue('10');
    expect(screen.getByLabelText('Miqdori')).toHaveValue('5000');
    expect(screen.getByLabelText('Hisoblash')).toHaveValue('per_person');
    expect(screen.queryByRole('checkbox', { name: 'Telegram' })).not.toBeInTheDocument();
    // A null channel list is the hall only
    const charge = within(screen.getByText('Xizmat haqi', { selector: 'h2' }).closest('section') as HTMLElement);
    expect(charge.getByRole('checkbox', { name: 'Zal (kassa)' })).toBeChecked();
    expect(charge.getByRole('checkbox', { name: 'Olib ketish (kassa)' })).not.toBeChecked();

    await typing.clear(screen.getByLabelText('Foiz'));
    await typing.type(screen.getByLabelText('Foiz'), '12,5');
    await typing.click(charge.getByRole('checkbox', { name: 'Olib ketish (kassa)' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Xizmat haqi saqlandi.');
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({
      branch_id: null,
      service_charge_percent: 12.5,
      service_charge_channels: ['pos_hall', 'pos_takeaway'],
      place_fee_amount: 5000,
      place_fee_mode: 'per_person',
      place_fee_channels: ['pos_hall', 'pos_takeaway'],
    });
  });

  it("starts a branch without its own row from the store's values and says so; a branch with a row shows its own", async () => {
    const { calls } = base({ 'GET /api/v1/owner/service-fees': () => ok(FEES), 'PUT /api/v1/owner/service-fees': () => ok(FEES.settings[0]) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Xizmat haqi' }));
    await screen.findByLabelText('Foiz');

    await typing.selectOptions(screen.getByLabelText('Qaysi uchun'), 'Markaz');
    expect(screen.getByText(/Bu filial uchun alohida sozlama yo'q/)).toBeInTheDocument();
    expect(screen.getByLabelText('Foiz')).toHaveValue('10');

    await typing.selectOptions(screen.getByLabelText('Qaysi uchun'), 'Yunusobod');
    expect(screen.queryByText(/alohida sozlama yo'q/)).not.toBeInTheDocument();
    expect(screen.getByLabelText('Foiz')).toHaveValue('5');
    expect(screen.getByLabelText('Hisoblash')).toHaveValue('per_table');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await screen.findByRole('status');
    expect(((await calls.find((call) => call.method === 'PUT')!.json()) as { branch_id: number }).branch_id).toBe(2);
  });

  it("does not mistake a hall's own row for the branch's: a branch with only a hall row still starts from the store's values", async () => {
    const withHall = { ...FEES, settings: [{ ...FEES.settings[0]! }, { hall_id: 7, ...FEES.settings[1]!, branch_id: 1, service_charge_percent: '15.00' }, FEES.settings[1]!] };
    base({ 'GET /api/v1/owner/service-fees': () => ok(withHall), 'PUT /api/v1/owner/service-fees': () => ok(FEES.settings[0]) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Xizmat haqi' }));
    await screen.findByLabelText('Foiz');

    await typing.selectOptions(screen.getByLabelText('Qaysi uchun'), 'Markaz');
    expect(screen.getByText(/Bu filial uchun alohida sozlama yo'q/)).toBeInTheDocument();
    expect(screen.getByLabelText('Foiz')).toHaveValue('10');
  });

  it("shows the server's refusal of a percent", async () => {
    base({ 'GET /api/v1/owner/service-fees': () => ok(FEES), 'PUT /api/v1/owner/service-fees': () => refusal('Xato', 422, { errors: { service_charge_percent: ['Foiz 30 dan oshmasin.'] } }) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Xizmat haqi' }));
    await typing.clear(await screen.findByLabelText('Foiz'));
    await typing.type(screen.getByLabelText('Foiz'), '45');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Foiz 30 dan oshmasin.')).toBeInTheDocument();
  });
});

describe('SettingsView: devices', () => {
  const open = async () => {
    const typing = userEvent.setup();
    renderPage(<SettingsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Qurilmalar' }));
    return typing;
  };

  it('lists the terminals with their branch and state, and asks for the removed ones only when told', async () => {
    const { calls } = base({
      'GET /api/v1/owner/devices': (request) =>
        ok(
          new URL(request.url).searchParams.get('include_revoked') === 'true'
            ? [makeDevice(), makeDevice({ uuid: 'dev-2', name: 'Eski kassa', revoked_at: '2026-10-01T10:00:00+05:00' })]
            : [makeDevice()],
        ),
    });
    const typing = await open();
    const card = within((await screen.findByRole('heading', { name: 'Kassa 1' })).closest('li') as HTMLElement);
    expect(card.getByText('Kassa (POS) · windows · v1.2.0')).toBeInTheDocument();
    expect(card.getByText('Kassir Ali (Kassir) · Markaz')).toBeInTheDocument();
    expect(card.getByText('Faol')).toBeInTheDocument();
    expect(screen.queryByText('Eski kassa')).not.toBeInTheDocument();

    await typing.click(screen.getByRole('checkbox', { name: "O'chirilganlarni ham ko'rsatish" }));
    const old = within((await screen.findByRole('heading', { name: 'Eski kassa' })).closest('li') as HTMLElement);
    expect(old.getByText("O'chirilgan")).toBeInTheDocument();
    expect(old.queryByRole('button')).not.toBeInTheDocument();
    expect(calls.filter((call) => call.url.includes('/owner/devices')).map((call) => new URL(call.url).searchParams.get('include_revoked'))).toEqual(['false', 'true']);
  });

  it('renames and revokes a device', async () => {
    const { calls } = base({
      'GET /api/v1/owner/devices': () => ok([makeDevice()]),
      'PATCH /api/v1/owner/devices/dev-1': () => ok(makeDevice({ name: 'Kassa 2' })),
      'POST /api/v1/owner/devices/dev-1/revoke': () => ok(makeDevice({ revoked_at: '2026-10-21T10:00:00+05:00' })),
    });
    const typing = await open();
    await typing.click(await screen.findByRole('button', { name: 'Kassa 1: nomini o\'zgartirish' }));
    let dialog = within(await screen.findByRole('dialog'));
    await typing.clear(dialog.getByLabelText('Nomi'));
    await typing.type(dialog.getByLabelText('Nomi'), 'Kassa 2');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({ name: 'Kassa 2' });

    await typing.click(screen.getByRole('button', { name: "Kassa 1: o'chirish" }));
    dialog = within(await screen.findByRole('alertdialog'));
    expect(calls.some((call) => call.url.endsWith('/revoke'))).toBe(false);
    await typing.click(dialog.getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent("Kassa 1 o'chirildi.");
  });

  it('hides the buttons from a person who may only view the devices', async () => {
    base({ 'GET /api/v1/owner/devices': () => ok([makeDevice()]) }, ['devices.view']);
    renderPage(<SettingsView />);
    await screen.findByRole('heading', { name: 'Kassa 1' });
    expect(screen.queryByRole('button', { name: 'Ulash kodini yaratish' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /o'chirish/ })).not.toBeInTheDocument();
  });

  it('creates a pairing code and shows it big with the time it stops working, counting down', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true, now: new Date('2026-10-21T10:00:00+05:00') });
    const expires = new Date('2026-10-21T10:00:05+05:00').toISOString();
    const { calls } = base({
      'GET /api/v1/owner/devices': () => ok([]),
      'POST /api/v1/owner/devices/pairing-codes': () => ok({ code: 'ABCD-EFGH', expires_at: expires, type: 'kds', branch_id: 1 }, 201),
    });
    const typing = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    await (async () => {
      renderPage(<SettingsView />);
      await typing.click(await screen.findByRole('tab', { name: 'Qurilmalar' }));
    })();
    await typing.click(await screen.findByRole('button', { name: 'Ulash kodini yaratish' }));
    const form = within(await screen.findByRole('dialog'));
    await typing.selectOptions(form.getByLabelText('Turi'), 'kds');
    await typing.selectOptions(form.getByLabelText('Filial'), 'Markaz');
    await typing.type(form.getByLabelText('Nomi'), 'Oshxona');
    await typing.click(form.getByRole('button', { name: 'Kod yaratish' }));

    expect(await screen.findByTestId('pairing-code')).toHaveTextContent('ABCD-EFGH');
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ type: 'kds', branch_id: 1, name: 'Oshxona' });
    expect(screen.getByRole('timer')).toHaveTextContent(/\(0:0\d qoldi\)/);
    expect(screen.getByText('Kodni Oshxona ekrani ilovasida kiriting.')).toBeInTheDocument();

    // It runs out
    await act(async () => {
      await vi.advanceTimersByTimeAsync(6000);
    });
    expect(await screen.findByText('Kodning muddati tugadi.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Yangi kod' })).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Yangi kod' }));
    expect(await screen.findByRole('button', { name: 'Kod yaratish' })).toBeInTheDocument();
  });

  it("shows the server's refusal when the code cannot be created", async () => {
    base({ 'GET /api/v1/owner/devices': () => ok([]), 'POST /api/v1/owner/devices/pairing-codes': () => refusal('Xato', 422, { errors: { branch_id: ['Filial topilmadi.'] } }) });
    const typing = await open();
    await typing.click(await screen.findByRole('button', { name: 'Ulash kodini yaratish' }));
    const form = within(await screen.findByRole('dialog'));
    await typing.click(form.getByRole('button', { name: 'Kod yaratish' }));
    expect(await form.findByText('Filial topilmadi.')).toBeInTheDocument();
  });
});
