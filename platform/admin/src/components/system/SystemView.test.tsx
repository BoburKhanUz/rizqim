import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { forgetAcceptedCode } from '@/lib/api/usedCodes';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { SystemView } from './SystemView';

const SYSTEM = ['admin.system.manage', 'admin.team.manage', 'admin.audit.view', 'admin.orders.view'];

const item = (overrides: Record<string, unknown>) => ({
  key: 'x',
  label: 'X',
  description: '',
  type: 'text',
  editable: true,
  secret: false,
  value: null,
  configured: false,
  last4: null,
  source: null,
  default: null,
  options: null,
  updated_at: null,
  ...overrides,
});

const GROUPS = [
  {
    key: 'sms',
    label: 'SMS',
    items: [
      item({ key: 'sms_driver', label: 'SMS yuborish usuli', type: 'select', value: 'log', configured: true, source: 'settings', default: 'log', options: [{ value: 'log', label: 'Log (SMS yuborilmaydi)' }, { value: 'eskiz', label: 'Eskiz (haqiqiy SMS)' }] }),
      item({ key: 'eskiz_email', label: 'Eskiz email', type: 'email', value: 'sms@rizqim.uz', configured: true, source: 'settings' }),
      item({ key: 'eskiz_from', label: "Jo'natuvchi nomi", value: '4546', configured: true, source: 'default', default: '4546' }),
      item({ key: 'eskiz_password', label: 'Eskiz paroli', type: 'secret', secret: true, configured: true, last4: '9876', source: 'settings' }),
    ],
  },
  { key: 'telegram', label: 'Telegram', items: [item({ key: 'telegram_bot_token', label: 'Bot tokeni', type: 'secret', secret: true, editable: false, configured: true, last4: 'WXYZ', source: 'env' })] },
  { key: 'general', label: 'Umumiy', items: [item({ key: 'app_env', label: 'Muhit', editable: false, value: 'production', configured: true, source: 'env' })] },
];

const PROVIDERS = [
  {
    id: 1,
    slug: 'payme',
    name: 'Payme',
    is_active: true,
    mode: 'test',
    wired: true,
    credentials: [
      { key: 'merchant_id', label: 'Merchant ID', known: true, configured: true, last4: '4912', source: 'settings' },
      { key: 'secret_key', label: 'Maxfiy kalit', known: true, configured: false, last4: null, source: null },
    ],
    updated_at: null,
  },
];

const REGIONS = [
  {
    id: 1,
    name: 'Toshkent shahri',
    is_active: true,
    sort_order: 1,
    districts_count: 1,
    active_districts: 1,
    usage: { branches: 2, addresses: 1, applications: 0 },
    deletable: false,
    districts: [{ id: 10, region_id: 1, name: 'Chilonzor tumani', is_active: true, sort_order: 1, usage: { branches: 2, addresses: 1 }, deletable: false }],
  },
  { id: 2, name: 'Andijon viloyati', is_active: false, sort_order: 2, districts_count: 0, active_districts: 0, usage: { branches: 0, addresses: 0, applications: 0 }, deletable: true, districts: [] },
];

const FLAG = { name: 'new-checkout-copy', label: 'Yangi buyurtma matni', description: 'Namunaviy flag.', default: false, stores_on: 1, stores_off: 1, stores_total: 2 };
const flagStore = (id: number, name: string, active: boolean, stored: boolean) => ({ store: { id, name, status: 'active' }, flags: { 'new-checkout-copy': { active, stored } } });

afterEach(() => {
  vi.unstubAllGlobals();
  forgetAcceptedCode();
  window.history.replaceState(null, '', '/');
});

const me = (permissions = SYSTEM) => () => json(meBody({ permissions }));

describe('SystemView', () => {
  it('says calmly that it needs system.manage', async () => {
    mockFetch({ 'GET /auth/me': me(['admin.team.manage']) });
    renderPage(<SystemView />);
    expect(await screen.findByText("Tizim sozlamalarini ko'rish uchun «Tizim sozlamalari» ruxsati kerak.")).toBeInTheDocument();
  });

  it('shows the settings, a secret only by its last 4 characters, and the links to the other pages', async () => {
    mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups: GROUPS }) });
    renderPage(<SystemView />);

    const sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByLabelText('SMS yuborish usuli')).toHaveValue('log');
    expect(within(sms).getByText('Kiritilgan, oxiri ••••9876')).toBeInTheDocument();
    const telegram = screen.getByRole('region', { name: 'Telegram' });
    expect(within(telegram).getByText('Kiritilgan, oxiri ••••WXYZ')).toBeInTheDocument();
    expect(within(telegram).queryByRole('button', { name: /almashtirish/i })).not.toBeInTheDocument();
    expect(screen.getByText('production')).toBeInTheDocument();

    // Links: SLA (orders.view), Jamoa, Rollar, Jurnal; no tariffs without a POS permission
    expect(screen.getByRole('link', { name: /SLA chegaralari/ })).toHaveAttribute('href', '/orders');
    expect(screen.getByRole('link', { name: /Harakatlar jurnali/ })).toHaveAttribute('href', '/audit');
    expect(screen.queryByRole('link', { name: /Tariflar katalogi/ })).not.toBeInTheDocument();
  });

  it('saves only the changed plain settings with a reason', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups: GROUPS }), 'PATCH /system/settings': () => ok({ groups: GROUPS }) });
    renderPage(<SystemView />);

    const sms = await screen.findByRole('region', { name: 'SMS' });
    await user.type(within(sms).getByLabelText("Jo'natuvchi nomi"), 'RIZQIM');
    await user.click(within(sms).getByRole('button', { name: 'Saqlash' }));
    const dialog = await screen.findByRole('dialog', { name: "Sozlamalarni o'zgartirish" });
    expect(within(dialog).getByText("O'zgaradi: Jo'natuvchi nomi.")).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Nom tasdiqlandi');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('PATCH', '/system/settings')).toHaveLength(1));
    expect(callsTo('PATCH', '/system/settings')[0]!.body).toEqual({ values: { eskiz_from: 'RIZQIM' }, reason: 'Nom tasdiqlandi' });
    expect(await screen.findByText('Sozlamalar saqlandi.')).toBeInTheDocument();
  });

  it('replaces a secret on its own after a fresh 2FA code', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const { callsTo } = mockFetch({
      'GET /auth/me': me(),
      'GET /system/settings': () => ok({ groups: GROUPS }),
      'PUT /system/settings/secrets/eskiz_password': () => (++attempts === 1 ? json({ success: false, message: 'Amalni qayta tasdiqlang.', code: 'reauth_required' }, 403) : ok({ groups: GROUPS })),
      'POST /auth/confirm': () => ok({ confirmed_until: '2026-10-28T12:10:00Z' }),
    });
    renderPage(<SystemView />);

    await user.click(await screen.findByRole('button', { name: 'Eskiz paroli: almashtirish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Eskiz paroli: yangi qiymat' });
    const input = within(dialog).getByLabelText('Yangi qiymat');
    expect(input).toHaveAttribute('type', 'password');
    await user.type(input, 'new-eskiz-secret');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Parol almashdi');
    await user.click(within(dialog).getByRole('button', { name: 'Almashtirish' }));

    const step = await screen.findByRole('alertdialog', { name: 'Amalni tasdiqlang' });
    await user.type(within(step).getByLabelText('6 xonali kod'), '123456');
    await user.click(within(step).getByRole('button', { name: 'Tasdiqlash' }));

    expect(await screen.findByText('Eskiz paroli almashtirildi.')).toBeInTheDocument();
    expect(callsTo('PUT', '/system/settings/secrets/eskiz_password').at(-1)!.body).toEqual({ value: 'new-eskiz-secret', reason: 'Parol almashdi' });
  });

  it('shows payment keys masked and replaces one', async () => {
    const user = userEvent.setup();
    const updated = { ...PROVIDERS[0], credentials: [PROVIDERS[0]!.credentials[0], { key: 'secret_key', label: 'Maxfiy kalit', known: true, configured: true, last4: 'ABCD', source: 'settings' }] };
    const { callsTo } = mockFetch({ 'GET /auth/me': me(), 'GET /system/payment-providers': () => ok(PROVIDERS), 'PUT /system/payment-providers/payme/credentials/secret_key': () => ok(updated) });
    renderPage(<SystemView initialTab="payments" />);

    const payme = await screen.findByRole('region', { name: 'Payme' });
    expect(within(payme).getByText('Kiritilgan, oxiri ••••4912')).toBeInTheDocument();
    expect(within(payme).getByText('Sinov', { selector: 'span' })).toBeInTheDocument();
    await user.click(within(payme).getByRole('button', { name: 'Payme: Maxfiy kalit: almashtirish' }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText('Yangi qiymat'), 'payme-key-ABCD');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Yangi kalit');
    await user.click(within(dialog).getByRole('button', { name: 'Almashtirish' }));

    expect(await within(payme).findByText('Kiritilgan, oxiri ••••ABCD')).toBeInTheDocument();
    expect(callsTo('PUT', '/system/payment-providers/payme/credentials/secret_key')[0]!.body).toEqual({ value: 'payme-key-ABCD', reason: 'Yangi kalit' });
  });

  it('adds a region, says why one in use cannot go, and saves a new order as the full list', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({
      'GET /auth/me': me(),
      'GET /system/regions': () => ok(REGIONS),
      'POST /system/regions': () => json({ success: true, message: '', data: { ...REGIONS[1], id: 3, name: 'Xorazm viloyati' } }, 201),
      'PUT /system/regions/order': () => ok(REGIONS),
    });
    renderPage(<SystemView initialTab="locations" />);

    await screen.findByText('Toshkent shahri');
    await user.click(screen.getByRole('button', { name: 'Toshkent shahri: tumanlarni ochish' }));
    expect(screen.getByText(/Tumanlari bor viloyat o'chirilmaydi/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Chilonzor tumani: o'chirish" })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: "Viloyat qo'shish" }));
    let dialog = await screen.findByRole('dialog', { name: "Viloyat qo'shish" });
    await user.type(within(dialog).getByLabelText('Nomi'), 'Xorazm viloyati');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Yangi hudud');
    await user.click(within(dialog).getByRole('button', { name: "Qo'shish" }));
    await waitFor(() => expect(callsTo('POST', '/system/regions')).toHaveLength(1));
    expect(callsTo('POST', '/system/regions')[0]!.body).toEqual({ name: 'Xorazm viloyati', is_active: false, reason: 'Yangi hudud' });
    expect(await screen.findByText("Viloyat qo'shildi.")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: "Tartibni o'zgartirish" }));
    await user.click(screen.getByRole('button', { name: 'Andijon viloyati: yuqoriga' }));
    await user.click(screen.getByRole('button', { name: 'Tartibni saqlash' }));
    dialog = await screen.findByRole('dialog', { name: 'Yangi tartib' });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Andijon birinchi');
    await user.click(within(dialog).getByRole('button', { name: 'Tartibni saqlash' }));
    await waitFor(() => expect(callsTo('PUT', '/system/regions/order')).toHaveLength(1));
    expect(callsTo('PUT', '/system/regions/order')[0]!.body).toEqual({ ids: [2, 1], reason: 'Andijon birinchi' });
  });

  it('shows the refusal of the server inside the dialog', async () => {
    const user = userEvent.setup();
    mockFetch({
      'GET /auth/me': me(),
      'GET /system/regions': () => ok(REGIONS),
      'DELETE /system/regions/2': () => refusal(422, "Viloyat ishlatilmoqda (1 ta filial): o'chirib bo'lmaydi, faol emas qilib qo'ying.", { reason: 'in_use' }, { code: 'in_use' }),
    });
    renderPage(<SystemView initialTab="locations" />);

    await user.click(await screen.findByRole('button', { name: 'Andijon viloyati: tumanlarni ochish' }));
    await user.click(screen.getByRole('button', { name: "O'chirish" }));
    const dialog = await screen.findByRole('dialog', { name: "Andijon viloyati o'chirilsinmi?" });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Kerak emas');
    await user.click(within(dialog).getByRole('button', { name: "O'chirish" }));
    expect(await within(dialog).findByText(/Viloyat ishlatilmoqda/)).toBeInTheDocument();
  });

  it('switches a feature flag for one store with a reason', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({
      'GET /auth/me': me(),
      'GET /system/feature-flags': () => ok({ flags: [FLAG] }),
      'GET /system/feature-flags/stores': () => ok({ data: [flagStore(13, 'Other Store', false, false), flagStore(12, 'Pilot Store', true, true)], current_page: 1, last_page: 1, per_page: 30, total: 2 }),
      'PUT /system/feature-flags/new-checkout-copy/stores/13': () => ok(flagStore(13, 'Other Store', true, true)),
    });
    renderPage(<SystemView initialTab="flags" />);

    expect(await screen.findByText("1 ta ovqatxonada yoqilgan, 1 tasida o'chiq (jami 2)")).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: 'Pilot Store: standartga qaytarish' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Other Store: standartga qaytarish' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('switch', { name: 'Yangi buyurtma matni: Other Store' }));
    const dialog = await screen.findByRole('dialog', { name: 'Other Store: «Yangi buyurtma matni» yoqilsinmi?' });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Pilot kengaydi');
    await user.click(within(dialog).getByRole('button', { name: 'Yoqish' }));
    await waitFor(() => expect(callsTo('PUT', '/system/feature-flags/new-checkout-copy/stores/13')).toHaveLength(1));
    expect(callsTo('PUT', '/system/feature-flags/new-checkout-copy/stores/13')[0]!.body).toEqual({ active: true, reason: 'Pilot kengaydi' });
  });
});
