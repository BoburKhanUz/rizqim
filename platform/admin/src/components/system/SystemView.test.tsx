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

const smsGroups = ({ driver = 'eskiz', key = false, password = true }: { driver?: string; key?: boolean; password?: boolean } = {}) => [
  {
    key: 'sms',
    label: 'SMS',
    items: [
      item({
        key: 'sms_driver',
        label: 'SMS yuborish usuli',
        type: 'select',
        value: driver,
        configured: true,
        source: 'settings',
        default: 'log',
        updated_at: '2026-11-27T10:00:00Z',
        options: [
          { value: 'log', label: 'Log (SMS yuborilmaydi)' },
          { value: 'eskiz', label: 'Eskiz (haqiqiy SMS)' },
          { value: 'onlinegroup', label: 'Onlinegroup (haqiqiy SMS)' },
        ],
      }),
      item({ key: 'eskiz_email', label: 'Eskiz email', type: 'email', value: 'sms@rizqim.uz', configured: true, source: 'settings' }),
      item({ key: 'eskiz_from', label: "Jo'natuvchi nomi", value: '4546', configured: true, source: 'default', default: '4546' }),
      item({ key: 'eskiz_password', label: 'Eskiz paroli', type: 'secret', secret: true, configured: password, last4: password ? '9876' : null, source: password ? 'settings' : null }),
      item({ key: 'onlinegroup_api_key', label: 'Onlinegroup API kaliti', type: 'secret', secret: true, configured: key, last4: key ? 'Ip10' : null, source: key ? 'settings' : null }),
    ],
  },
  { key: 'telegram', label: 'Telegram', items: [item({ key: 'telegram_bot_token', label: 'Bot tokeni', type: 'secret', secret: true, editable: false, configured: true, last4: 'WXYZ', source: 'env' })] },
  { key: 'general', label: 'Umumiy', items: [item({ key: 'app_env', label: 'Muhit', editable: false, value: 'production', configured: true, source: 'env' })] },
];

const GROUPS = smsGroups();

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
    expect(within(sms).getByLabelText('SMS yuborish usuli')).toHaveValue('eskiz');
    expect(within(sms).getByText('Tayyor: SMS Eskiz orqali yuboriladi')).toBeInTheDocument();
    expect(within(sms).getByText('••••9876')).toBeInTheDocument();
    expect(within(sms).queryByText(/Onlinegroup API kaliti/)).not.toBeInTheDocument();
    expect(within(sms).getByText(/^Oxirgi o'zgarish: 27-noy, /)).toBeInTheDocument();
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

  it('shows only what the chosen method needs; the other fields are hidden, kept and not sent', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups: smsGroups({ driver: 'onlinegroup', key: true }) }), 'PATCH /system/settings': () => ok({ groups: smsGroups({ driver: 'eskiz', key: true }) }) });
    renderPage(<SystemView />);

    const sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByText('Tayyor: SMS Onlinegroup orqali yuboriladi')).toBeInTheDocument();
    expect(within(sms).getByText('••••Ip10')).toBeInTheDocument();
    expect(within(sms).getByText('sms.onlinegroup.uz → Ilovalar: ilova kaliti, «sms:send» ruxsati bilan.')).toBeInTheDocument();
    // Onlinegroup sends no sender name: no Eskiz field and no sender name
    expect(within(sms).queryByLabelText('Eskiz email')).not.toBeInTheDocument();
    expect(within(sms).queryByLabelText("Jo'natuvchi nomi")).not.toBeInTheDocument();
    expect(within(sms).queryByText(/Eskiz paroli/)).not.toBeInTheDocument();
    expect(within(sms).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    expect(within(sms).getByLabelText('SMS yuborish usuli')).toHaveAccessibleDescription('sms.onlinegroup.uz orqali haqiqiy SMS: faqat API kaliti kerak.');

    // Eskiz chosen, not saved: its fields show, the readiness line still tells the saved state
    await user.selectOptions(within(sms).getByLabelText('SMS yuborish usuli'), 'eskiz');
    expect(within(sms).getByText("Saqlanmagan o'zgarish")).toBeInTheDocument();
    expect(within(sms).getByText('Tayyor: SMS Onlinegroup orqali yuboriladi')).toBeInTheDocument();
    expect(within(sms).getByLabelText('Eskiz email')).toHaveValue('sms@rizqim.uz');
    expect(within(sms).getByLabelText("Jo'natuvchi nomi")).toHaveAccessibleDescription("Eskiz tasdiqlagan nom yoki raqam. Bo'sh bo'lsa: 4546.");
    expect(within(sms).queryByText(/Onlinegroup API kaliti/)).not.toBeInTheDocument();
    await user.type(within(sms).getByLabelText("Jo'natuvchi nomi"), 'RIZQIM');

    // Log: nothing to enter; back to Onlinegroup: nothing changed; Eskiz again: the typed name is still there
    await user.selectOptions(within(sms).getByLabelText('SMS yuborish usuli'), 'log');
    expect(within(sms).queryByLabelText('Eskiz email')).not.toBeInTheDocument();
    expect(within(sms).queryByRole('button', { name: /kiritish|almashtirish/ })).not.toBeInTheDocument();
    expect(within(sms).getByText('SMS yuborilmaydi: kodlar faqat sinov muhitida server jurnaliga yoziladi.')).toBeInTheDocument();
    await user.selectOptions(within(sms).getByLabelText('SMS yuborish usuli'), 'onlinegroup');
    expect(within(sms).queryByText("Saqlanmagan o'zgarish")).not.toBeInTheDocument();
    expect(within(sms).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    await user.selectOptions(within(sms).getByLabelText('SMS yuborish usuli'), 'eskiz');
    expect(within(sms).getByLabelText("Jo'natuvchi nomi")).toHaveValue('RIZQIM');

    await user.click(within(sms).getByRole('button', { name: 'Saqlash' }));
    const dialog = await screen.findByRole('dialog', { name: "Sozlamalarni o'zgartirish" });
    expect(within(dialog).getByText("O'zgaradi: SMS yuborish usuli, Jo'natuvchi nomi.")).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), "Eskiz'ga o'tamiz");
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(callsTo('PATCH', '/system/settings')).toHaveLength(1));
    expect(callsTo('PATCH', '/system/settings')[0]!.body).toEqual({ values: { sms_driver: 'eskiz', eskiz_from: 'RIZQIM' }, reason: "Eskiz'ga o'tamiz" });
    expect(await within(sms).findByText('Tayyor: SMS Eskiz orqali yuboriladi')).toBeInTheDocument();
    expect(within(sms).queryByText("Saqlanmagan o'zgarish")).not.toBeInTheDocument();
  });

  it('says when SMS does not go out: a missing key, a missing Eskiz password, the test mode', async () => {
    let groups = smsGroups({ driver: 'onlinegroup', key: false });
    mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups }) });
    const { unmount } = renderPage(<SystemView />);
    let sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByText('API kaliti kiritilmagan — SMS yuborilmaydi.')).toBeInTheDocument();
    expect(within(sms).getByText('Kiritilmagan')).toBeInTheDocument();
    // Nothing to replace yet: "Kiritish", and nothing to clear
    expect(within(sms).getByRole('button', { name: 'Onlinegroup API kaliti: kiritish' })).toBeInTheDocument();
    expect(within(sms).queryByRole('button', { name: /o'chirish/ })).not.toBeInTheDocument();
    unmount();

    groups = smsGroups({ driver: 'eskiz', password: false });
    const second = renderPage(<SystemView />);
    sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByText('Eskiz paroli kiritilmagan — SMS yuborilmaydi.')).toBeInTheDocument();
    second.unmount();

    groups = smsGroups({ driver: 'log' });
    renderPage(<SystemView />);
    sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByText('Sinov rejimi: SMS yuborilmaydi.')).toBeInTheDocument();
    expect(within(sms).getByText('Kirish kodlari telefonga bormaydi.')).toBeInTheDocument();
  });

  it('sends the fixed test SMS through the saved settings and says it went out', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({
      'GET /auth/me': me(),
      'GET /system/settings': () => ok({ groups: smsGroups({ driver: 'onlinegroup', key: true }) }),
      'POST /system/settings/sms-test': () => ok({ driver: 'onlinegroup', phone: '+998 90 *** ** 67' }, { message: 'Sinov SMS yuborildi.' }),
    });
    renderPage(<SystemView />);

    const sms = await screen.findByRole('region', { name: 'SMS' });
    await user.click(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Sinov SMS yuborish' });
    // The text is fixed: only the phone is asked
    expect(within(dialog).getByText(/«RIZQIM: sinov xabari\. SMS sozlamalari ishlayapti\.»/)).toBeInTheDocument();
    expect(within(dialog).getAllByRole('textbox')).toHaveLength(1);
    await user.type(within(dialog).getByLabelText('Telefon raqami'), '90 123 45 67');
    await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));

    await waitFor(() => expect(callsTo('POST', '/system/settings/sms-test')).toHaveLength(1));
    expect(callsTo('POST', '/system/settings/sms-test')[0]!.body).toEqual({ phone: '90 123 45 67' });
    expect(await within(sms).findByText('Sinov SMS yuborildi: +998 90 *** ** 67. Telefonga kelganini tekshiring.')).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: 'Sinov SMS yuborish' })).not.toBeInTheDocument();
  });

  it("checks the phone before sending and says the provider's refusal and the limit in plain words", async () => {
    const user = userEvent.setup();
    const answers = [
      () => refusal(422, 'Backend', { reason: 'sms_key_refused', driver: 'onlinegroup' }, { code: 'sms_key_refused' }),
      () => json({ success: false, message: 'Backend', code: 'sms_test_rate_limited', data: { reason: 'sms_test_rate_limited' } }, 429, { 'Retry-After': '1800' }),
    ];
    let next = 0;
    const { callsTo } = mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups: smsGroups({ driver: 'onlinegroup', key: true }) }), 'POST /system/settings/sms-test': () => answers[next++]!() });
    renderPage(<SystemView />);

    const sms = await screen.findByRole('region', { name: 'SMS' });
    await user.click(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Sinov SMS yuborish' });
    // Not an Uzbek mobile number: said here, nothing sent (and nothing used of the hourly allowance)
    for (const wrong of ['+1 202 555 0123', '90 123 45', '+998 90 123 45 678']) {
      await user.clear(within(dialog).getByLabelText('Telefon raqami'));
      await user.type(within(dialog).getByLabelText('Telefon raqami'), wrong);
      await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
      expect(await within(dialog).findByText("O'zbekiston mobil raqamini kiriting: +998 va 9 ta raqam.")).toBeInTheDocument();
    }
    expect(callsTo('POST', '/system/settings/sms-test')).toHaveLength(0);

    await user.clear(within(dialog).getByLabelText('Telefon raqami'));
    await user.type(within(dialog).getByLabelText('Telefon raqami'), '+998 (90) 123-45-67');
    expect(within(dialog).queryByText("O'zbekiston mobil raqamini kiriting: +998 va 9 ta raqam.")).not.toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent("SMS xizmati kalitni (yoki Eskiz email va parolini) qabul qilmadi. Tekshiring yoki almashtiring (Onlinegroup kalitiga «sms:send» ruxsati kerak).");

    await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
    await waitFor(() => expect(within(dialog).getByRole('alert')).toHaveTextContent("Sinov SMS juda ko'p yuborildi (har a'zoga soatiga 5 ta, butun platformaga 30 ta). 30 daqiqadan so'ng qayta urinib ko'ring."));
    expect(callsTo('POST', '/system/settings/sms-test')).toHaveLength(2);
  });

  it('clears the "sent" note when the dialog opens again and when the SMS settings change', async () => {
    const user = userEvent.setup();
    const replaced = smsGroups({ driver: 'onlinegroup', key: true }).map((group) => ({
      ...group,
      items: group.items.map((candidate) => (candidate.key === 'onlinegroup_api_key' ? { ...candidate, last4: 'NEW1', updated_at: '2026-11-28T09:00:00Z' } : candidate)),
    }));
    mockFetch({
      'GET /auth/me': me(),
      'GET /system/settings': () => ok({ groups: smsGroups({ driver: 'onlinegroup', key: true }) }),
      'POST /system/settings/sms-test': () => ok({ driver: 'onlinegroup', phone: '+998 90 *** ** 67' }),
      'PUT /system/settings/secrets/onlinegroup_api_key': () => ok({ groups: replaced }),
    });
    renderPage(<SystemView />);
    const sent = 'Sinov SMS yuborildi: +998 90 *** ** 67. Telefonga kelganini tekshiring.';
    const sms = await screen.findByRole('region', { name: 'SMS' });

    const sendOnce = async () => {
      await user.click(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' }));
      const dialog = await screen.findByRole('dialog', { name: 'Sinov SMS yuborish' });
      await user.type(within(dialog).getByLabelText('Telefon raqami'), '901234567');
      await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
      expect(await within(sms).findByText(sent)).toBeInTheDocument();
    };

    await sendOnce();
    // Opening the dialog again: the old result no longer stands
    await user.click(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' }));
    expect(within(sms).queryByText(sent)).not.toBeInTheDocument();
    await user.click(within(await screen.findByRole('dialog', { name: 'Sinov SMS yuborish' })).getByRole('button', { name: 'Bekor qilish' }));

    await sendOnce();
    // A new key: the test was of the old one
    await user.click(within(sms).getByRole('button', { name: 'Onlinegroup API kaliti: almashtirish' }));
    const secret = await screen.findByRole('dialog');
    await user.type(within(secret).getByLabelText('Yangi qiymat'), 'sk_new_value_NEW1');
    await user.type(within(secret).getByLabelText('Sabab'), 'Yangi kalit');
    await user.click(within(secret).getByRole('button', { name: 'Almashtirish' }));
    expect(await within(sms).findByText('••••NEW1')).toBeInTheDocument();
    expect(within(sms).queryByText(sent)).not.toBeInTheDocument();
  });

  it('offers the test only when the saved settings can send one', async () => {
    let groups = smsGroups({ driver: 'onlinegroup', key: false });
    mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups }) });
    const { unmount } = renderPage(<SystemView />);
    let sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' })).toBeDisabled();
    expect(within(sms).getByText("Sinov uchun avval SMS usulini tanlang, kalitini kiriting va saqlang.")).toBeInTheDocument();
    unmount();

    // SMS off, but the Onlinegroup key is saved: it can be checked before switching
    groups = smsGroups({ driver: 'log', key: true });
    renderPage(<SystemView />);
    sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' })).toBeEnabled();
    expect(within(sms).getByText("SMS hozir o'chiq: sinov saqlangan Onlinegroup kaliti bilan yuboriladi, kirish kodlari yoqilmaydi.")).toBeInTheDocument();
  });

  it('treats an empty SMS method as Log, as the backend does', async () => {
    mockFetch({ 'GET /auth/me': me(), 'GET /system/settings': () => ok({ groups: smsGroups({ driver: '', key: true }) }) });
    renderPage(<SystemView />);
    const sms = await screen.findByRole('region', { name: 'SMS' });
    expect(within(sms).getByText('Sinov rejimi: SMS yuborilmaydi.')).toBeInTheDocument();
    expect(within(sms).getByRole('button', { name: 'Sinov SMS yuborish' })).toBeEnabled();
    expect(within(sms).getByText("SMS hozir o'chiq: sinov saqlangan Onlinegroup kaliti bilan yuboriladi, kirish kodlari yoqilmaydi.")).toBeInTheDocument();
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

  it('says why production refuses the test mode (review S#7)', async () => {
    const user = userEvent.setup();
    const message = "Productionda to'lov tizimini test rejimiga o'tkazib bo'lmaydi: mijozlar to'lovi sinov (sandbox) hisobiga ketadi. Ataylab sinash kerak bo'lsa, serverda PAYMENTS_ALLOW_TEST_MODE=true qiling.";
    const live = [{ ...PROVIDERS[0]!, mode: 'production' }];
    const { callsTo } = mockFetch({
      'GET /auth/me': me(),
      'GET /system/payment-providers': () => ok(live),
      'PATCH /system/payment-providers/payme': () => refusal(422, message, { reason: 'test_mode_not_allowed' }, { code: 'test_mode_not_allowed' }),
    });
    renderPage(<SystemView initialTab="payments" />);

    const payme = await screen.findByRole('region', { name: 'Payme' });
    await user.selectOptions(within(payme).getByLabelText('Rejim'), 'test');
    await user.click(within(payme).getByRole('button', { name: 'Payme: saqlash' }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Sinab ko‘ramiz');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    expect(await within(dialog).findByText(message)).toBeInTheDocument();
    expect(callsTo('PATCH', '/system/payment-providers/payme')[0]!.body).toEqual({ mode: 'test', reason: 'Sinab ko‘ramiz' });
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
