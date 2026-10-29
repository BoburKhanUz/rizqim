import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { storeCard } from './fixtures';
import { StoreCardView } from './StoreCardView';

const PERMS = ['stores.view', 'stores.manage', 'subscriptions.manage', 'audit.view'].map((name) => `admin.${name}`);

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = PERMS) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /stores/12': () => ok(storeCard()),
    ...routes,
  });
  renderPage(<StoreCardView id={12} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('StoreCardView', () => {
  it('shows the health with its reasons, the profile, contacts and links', async () => {
    setup();
    expect(await screen.findByRole('heading', { name: 'Lazzat Oshxona', level: 1 })).toBeInTheDocument();
    const reasons = screen.getByRole('list', { name: 'Sabablar' });
    expect(within(reasons).getByText('Kassa 3 kundan beri aloqa qilmagan')).toBeInTheDocument();
    expect(screen.getAllByText('Ketish xavfi · 55 ball').length).toBeGreaterThan(0);
    expect(screen.getByText('+998 90 *** ** 67')).toBeInTheDocument();
    expect(within(screen.getByRole('list', { name: 'Aloqa shaxslari' })).getByText('+998 90 *** ** 21')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Buyurtmalarini ochish/ })).toHaveAttribute('href', '/orders?store_id=12');
    expect(screen.getByText("Obuna yo'q: ovqatxona faqat komissiya bilan ishlaydi.")).toBeInTheDocument();
    expect(within(screen.getByRole('list', { name: "Komissiya kanallar bo'yicha" })).getByText('RIZQIM kuryeri: 15%')).toBeInTheDocument();
  });

  it('switches tabs: devices, finance, history', async () => {
    const { user } = setup();
    await screen.findByRole('heading', { name: 'Lazzat Oshxona', level: 1 });
    await user.click(screen.getByRole('tab', { name: 'Qurilmalar va fiskal' }));
    expect(screen.getByText('Terminallar: 0 / 1 onlayn (kassa 1, oshxona ekrani 0)')).toBeInTheDocument();
    expect(screen.getByText('Oflayn')).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Moliya' }));
    expect(screen.getByText("250 000 so'm")).toBeInTheDocument();
    // P4-21: the debt split, the credit limit counts the commission part
    expect(screen.getByText("Shundan obuna qarzi (buyurtmalarni to'xtatmaydi)")).toBeInTheDocument();
    expect(screen.getByText("200 000 so'm")).toBeInTheDocument();
    expect(screen.getByText("100 000 so'm · Limitdan oshgan")).toBeInTheDocument();
    expect(screen.getByText("Qolgan: 50 000 so'm")).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Tarix' }));
    expect(screen.getByText('Ulanmoqda → Faol')).toBeInTheDocument();
    expect(screen.getByText('Ulash jarayoni yakunlandi')).toBeInTheDocument();
  });

  it('counts and names the customer displays among the devices and the plan rows (P6-14)', async () => {
    const card = storeCard();
    card.devices = {
      terminals: 2,
      terminals_online: 1,
      by_type: { pos: 1, kds: 0 },
      items: [
        ...card.devices.items,
        { uuid: 'd-2', name: 'Raqamlar ekrani', type: 'display', type_label: 'Mijoz ekrani', terminal: true, branch: { id: 5, name: 'Asosiy Filial' }, platform: 'android', app_version: '0.1.0', last_seen_at: '2026-11-05T10:00:00+05:00', online: true, paired_at: null },
      ],
    };
    card.plan.entitlements.devices = { pos: { included: null, active: 1, extra: 0 }, display: { included: null, active: 1, extra: 0 } };
    const { user } = setup({ 'GET /stores/12': () => ok(card) });
    await screen.findByRole('heading', { name: 'Lazzat Oshxona', level: 1 });
    expect(screen.getByText('Mijoz ekrani: 1 ta (tarifda ∞)')).toBeInTheDocument();
    expect(screen.getByText('Kassa: 1 ta (tarifda ∞)')).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Qurilmalar va fiskal' }));
    expect(screen.getByText('Terminallar: 1 / 2 onlayn (kassa 1, oshxona ekrani 0, mijoz ekrani 1)')).toBeInTheDocument();
    expect(within(screen.getByRole('list', { name: 'Qurilmalar' })).getByText('Mijoz ekrani')).toBeInTheDocument();
  });

  it('suspends with a reason and shows the answer', async () => {
    const suspended = storeCard({ profile: { ...storeCard().profile, status: 'suspended', status_label: "To'xtatilgan", is_active: false }, actions: { ...storeCard().actions, suspend: false, activate: true } });
    let current = storeCard();
    const { user, callsTo } = setup({
      'GET /stores/12': () => ok(current),
      'POST /stores/12/suspend': () => {
        current = suspended;
        return ok(suspended);
      },
    });
    await user.click(await screen.findByRole('button', { name: "To'xtatish" }));
    const dialog = screen.getByRole('dialog', { name: "Ovqatxonani to'xtatish" });
    expect(within(dialog).getByText(/Kassa ishlashda davom etadi/)).toBeInTheDocument();
    const confirm = within(dialog).getByRole('button', { name: "Ha, to'xtatish" });
    expect(confirm).toBeDisabled();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Gigiyena tekshiruvi');
    await user.click(confirm);
    expect(await screen.findByText("Ovqatxona to'xtatildi.")).toBeInTheDocument();
    expect(callsTo('POST', '/stores/12/suspend')[0]?.body).toEqual({ reason: 'Gigiyena tekshiruvi' });
    expect(screen.getByRole('button', { name: 'Faollashtirish' })).toBeInTheDocument();
  });

  it('shows the backend refusal in the dialog', async () => {
    const { user } = setup({ 'PUT /stores/12/credit-limit': () => refusal(422, 'Limit juda katta.', { reason: 'validation' }, { errors: { credit_limit: ['Limit juda katta.'] } }) });
    await user.click(await screen.findByRole('button', { name: 'Kredit limiti' }));
    const dialog = screen.getByRole('dialog', { name: 'Kredit limiti' });
    await user.clear(within(dialog).getByLabelText("Limit (so'm)"));
    await user.type(within(dialog).getByLabelText("Limit (so'm)"), '999999999');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Ishonchli hamkor');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findAllByText('Limit juda katta.')).not.toHaveLength(0);
  });

  it('a new Telegram link goes to the owner by SMS and is not shown', async () => {
    const { user } = setup({ 'POST /stores/12/telegram-link': () => ok({ invite_link: null, delivered_via: 'sms', owner_linked: false }) });
    await user.click(await screen.findByRole('button', { name: 'Telegram havola' }));
    const dialog = screen.getByRole('dialog', { name: 'Yangi Telegram havola' });
    expect(within(dialog).getByText(/SMS bilan yuboriladi/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), "Egasi havolani yo'qotdi");
    await user.click(within(dialog).getByRole('button', { name: 'Havola yaratish' }));
    const sent = await screen.findByRole('dialog', { name: 'Havola yuborildi' });
    expect(within(sent).getByText(/telefoniga SMS bilan yuborildi/)).toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('shows the link, once, only when the server hands it over', async () => {
    const { user } = setup({ 'POST /stores/12/telegram-link': () => ok({ invite_link: 'https://t.me/rizqim_bot?start=invite_abc', delivered_via: null, owner_linked: false }) });
    await user.click(await screen.findByRole('button', { name: 'Telegram havola' }));
    const dialog = screen.getByRole('dialog', { name: 'Yangi Telegram havola' });
    await user.type(within(dialog).getByLabelText('Sabab'), "Egasi havolani yo'qotdi");
    await user.click(within(dialog).getByRole('button', { name: 'Havola yaratish' }));
    const ready = await screen.findByRole('dialog', { name: 'Havola tayyor' });
    expect(within(ready).getByDisplayValue('https://t.me/rizqim_bot?start=invite_abc')).toBeInTheDocument();
  });

  it('says why when the link cannot be made', async () => {
    const { user } = setup({ 'POST /stores/12/telegram-link': () => refusal(409, 'Egasi Telegramga allaqachon ulangan.', { reason: 'owner_already_linked' }) });
    await user.click(await screen.findByRole('button', { name: 'Telegram havola' }));
    const dialog = screen.getByRole('dialog', { name: 'Yangi Telegram havola' });
    await user.type(within(dialog).getByLabelText('Sabab'), "Egasi havolani yo'qotdi");
    await user.click(within(dialog).getByRole('button', { name: 'Havola yaratish' }));
    expect(await within(dialog).findAllByText('Egasi Telegramga allaqachon ulangan.')).not.toHaveLength(0);
  });

  it('changes the payment account and the payout reserve with a reason', async () => {
    const base = storeCard();
    const changed = storeCard({ profile: { ...base.profile, payment_account_type: 'own_account' }, finance: { ...base.finance, min_payout_reserve: '50000.00' }, actions: { ...base.actions, payment_account: true } });
    const { user, callsTo } = setup({
      'GET /stores/12': () => ok(storeCard({ actions: { ...base.actions, payment_account: true } })),
      'PUT /stores/12/payment-account': () => ok(changed),
    });
    await user.click(await screen.findByRole('button', { name: "To'lov hisobi" }));
    const dialog = screen.getByRole('dialog', { name: "To'lov hisobi va zaxira" });
    const save = within(dialog).getByRole('button', { name: 'Saqlash' });
    await user.type(within(dialog).getByLabelText('Sabab'), "O'z Payme kassasi");
    // Nothing changed yet
    expect(save).toBeDisabled();
    await user.selectOptions(within(dialog).getByLabelText("To'lov hisobi"), 'own_account');
    expect(within(dialog).getByText(/Kalitlarni egasi hamkor panelida kiritadi/)).toBeInTheDocument();
    await user.clear(within(dialog).getByLabelText("Payoutdan keyin hamyonda qoladigan summa (so'm)"));
    await user.type(within(dialog).getByLabelText("Payoutdan keyin hamyonda qoladigan summa (so'm)"), '50 000');
    await user.click(save);
    expect(await screen.findByText("To'lov hisobi sozlamalari saqlandi.")).toBeInTheDocument();
    expect(callsTo('PUT', '/stores/12/payment-account')[0]?.body).toEqual({ payment_account_type: 'own_account', min_payout_reserve: '50000', reason: "O'z Payme kassasi" });
  });

  it('shows why the payment account cannot change yet', async () => {
    const { user } = setup({
      'GET /stores/12': () => ok(storeCard({ actions: { ...storeCard().actions, payment_account: true } })),
      'PUT /stores/12/payment-account': () => refusal(409, "Ovqatxonada to'lovi kutilayotgan onlayn buyurtma bor.", { reason: 'payments_in_flight' }),
    });
    await user.click(await screen.findByRole('button', { name: "To'lov hisobi" }));
    const dialog = screen.getByRole('dialog', { name: "To'lov hisobi va zaxira" });
    await user.selectOptions(within(dialog).getByLabelText("To'lov hisobi"), 'own_account');
    await user.type(within(dialog).getByLabelText('Sabab'), "O'z Payme kassasi");
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findAllByText("Ovqatxonada to'lovi kutilayotgan onlayn buyurtma bor.")).not.toHaveLength(0);
  });

  it("sets the owner's login phone only when the backend offers it", async () => {
    const { user, callsTo } = setup({
      'GET /stores/12': () => ok(storeCard({ actions: { ...storeCard().actions, owner_login_phone: true } })),
      'PUT /stores/12/owner-login-phone': () => ok(storeCard()),
    });
    await user.click(await screen.findByRole('button', { name: 'Kirish raqami' }));
    const dialog = screen.getByRole('dialog', { name: 'Egasining kirish raqami' });
    expect(within(dialog).getByText(/eski raqamdagi barcha sessiyalar yopiladi/)).toBeInTheDocument();
    const save = within(dialog).getByRole('button', { name: 'Saqlash' });
    await user.type(within(dialog).getByLabelText('Yangi raqam'), '90 12');
    await user.type(within(dialog).getByLabelText('Sabab'), "Egasi raqamini yo'qotdi");
    expect(within(dialog).getByText("Raqam +998 va 9 ta raqam bo'lsin.")).toBeInTheDocument();
    expect(save).toBeDisabled();
    await user.type(within(dialog).getByLabelText('Yangi raqam'), '3 45 67');
    await user.click(save);
    expect(await screen.findByText('Egasining kirish raqami saqlandi.')).toBeInTheDocument();
    expect(callsTo('PUT', '/stores/12/owner-login-phone')[0]?.body).toEqual({ phone: '+998901234567', reason: "Egasi raqamini yo'qotdi" });
  });

  it('hides the actions from a member who may only look', async () => {
    setup({ 'GET /stores/12': () => ok(storeCard({ actions: { manage: false, suspend: false, activate: false, credit_limit: false, display: false, telegram_link: false, message_owner: false, commission_override: false } })) }, ['admin.stores.view']);
    await screen.findByRole('heading', { name: 'Lazzat Oshxona', level: 1 });
    expect(screen.queryByRole('group', { name: 'Amallar' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /To'liq jurnal/ })).not.toBeInTheDocument();
  });
});
