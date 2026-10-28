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

  it('a new Telegram link is shown once, to copy', async () => {
    const { user } = setup({ 'POST /stores/12/telegram-link': () => ok({ invite_link: 'https://t.me/rizqim_bot?start=invite_abc', owner_linked: true }) });
    await user.click(await screen.findByRole('button', { name: 'Telegram havola' }));
    const dialog = screen.getByRole('dialog', { name: 'Yangi Telegram havola' });
    expect(within(dialog).getByText(/allaqachon ulangan/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), "Egasi havolani yo'qotdi");
    await user.click(within(dialog).getByRole('button', { name: 'Havola yaratish' }));
    const ready = await screen.findByRole('dialog', { name: 'Havola tayyor' });
    expect(within(ready).getByDisplayValue('https://t.me/rizqim_bot?start=invite_abc')).toBeInTheDocument();
  });

  it('hides the actions from a member who may only look', async () => {
    setup({ 'GET /stores/12': () => ok(storeCard({ actions: { manage: false, suspend: false, activate: false, credit_limit: false, display: false, telegram_link: false, message_owner: false, commission_override: false } })) }, ['admin.stores.view']);
    await screen.findByRole('heading', { name: 'Lazzat Oshxona', level: 1 });
    expect(screen.queryByRole('group', { name: 'Amallar' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /To'liq jurnal/ })).not.toBeInTheDocument();
  });
});
