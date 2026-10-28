import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { CustomersView } from './CustomersView';
import { cannedReplies, complaintCard, complaintPage, suspicious, userPage } from './fixtures';

const SUPPORT = ['users.view', 'support.view', 'support.manage', 'orders.view'].map((name) => `admin.${name}`);
const UUID = 'cc000000-1111-4222-8333-444455556666';

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = SUPPORT, initial: { tab: 'users' | 'complaints' | 'suspicious' | null; complaint: string | null } = { tab: null, complaint: null }) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /users': () => ok(userPage()),
    'GET /complaints': () => ok(complaintPage()),
    [`GET /complaints/${UUID}`]: () => ok(complaintCard()),
    'GET /support/canned-replies': () => ok(cannedReplies()),
    'GET /support/suspicious': () => ok(suspicious()),
    ...routes,
  });
  renderPage(<CustomersView initial={initial} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('CustomersView', () => {
  it('lists users with their role and block state and sends the filters', async () => {
    const { user, callsTo } = setup();
    const list = await screen.findByRole('list', { name: "Foydalanuvchilar ro'yxati" });
    const blocked = within(list).getByRole('link', { name: 'Kuryer Bek kartasini ochish' });
    expect(blocked).toHaveAttribute('href', '/users/8');
    expect(within(blocked).getByText('Bloklangan')).toBeInTheDocument();
    expect(within(blocked).getByText('Lazzat Oshxona')).toBeInTheDocument();
    expect(within(list).getAllByText('+998 90 *** ** 67')).toHaveLength(2);
    expect(screen.getByText("Telefon bo'yicha faqat to'liq raqam topiladi (+998 90 123 45 67).")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Kuryerlar' }));
    await vi.waitFor(() => expect(callsTo('GET', '/users').at(-1)?.query.get('role')).toBe('courier'));
    await user.selectOptions(screen.getByLabelText('Holat'), 'blocked');
    await vi.waitFor(() => expect(callsTo('GET', '/users').at(-1)?.query.get('status')).toBe('blocked'));
    await user.type(screen.getByLabelText('Qidirish'), '90 123 45 67');
    await vi.waitFor(() => expect(callsTo('GET', '/users').at(-1)?.query.get('q')).toBe('90 123 45 67'));
  });

  it('opens a complaint from the link of Bugun and replies with a tayyor javob', async () => {
    let card = complaintCard();
    const { user, callsTo } = setup(
      {
        [`GET /complaints/${UUID}`]: () => ok(card),
        [`POST /complaints/${UUID}/reply`]: () => {
          card = complaintCard({ support_status: 'answered', support_status_label: 'Javob berildi', replies: [{ id: 1, body: "Buyurtmangiz kechikkani uchun uzr so'raymiz.", author: { id: 1, name: 'Aziza Karimova' }, channel: 'telegram', created_at: '2026-10-28T10:00:00+05:00' }] });
          return ok(card);
        },
      },
      SUPPORT,
      { tab: 'complaints', complaint: UUID },
    );
    const dialog = await screen.findByRole('dialog', { name: 'Shikoyat: Sifat muammosi' });
    expect(within(dialog).getByText('Ovqat sovuq keldi, kuryer 1 soat kechikdi.')).toBeInTheDocument();
    expect(within(dialog).getByText('Uzr, kuryer adashib qoldi.')).toBeInTheDocument();
    expect(within(dialog).getByRole('link', { name: /Olim Egamov/ })).toHaveAttribute('href', '/users/7');
    expect(within(dialog).getByRole('link', { name: /#AB12CD/ })).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666');

    await user.click(within(dialog).getByRole('button', { name: 'Javob yozish' }));
    const reply = screen.getByRole('dialog', { name: 'Mijozga javob' });
    const send = within(reply).getByRole('button', { name: 'Yuborish' });
    expect(send).toBeDisabled();
    await user.selectOptions(within(reply).getByLabelText('Tayyor javob'), 'Kechikish uchun uzr');
    expect(within(reply).getByLabelText('Matn')).toHaveValue("Buyurtmangiz kechikkani uchun uzr so'raymiz.");
    await user.click(send);
    expect(await screen.findByText('Javob mijozga yuborildi.')).toBeInTheDocument();
    expect(callsTo('POST', `/complaints/${UUID}/reply`)[0]?.body).toEqual({ text: "Buyurtmangiz kechikkani uchun uzr so'raymiz.", canned_reply_id: 1 });
    expect(within(screen.getByRole('list', { name: 'RIZQIM javoblari' })).getByText("Buyurtmangiz kechikkani uchun uzr so'raymiz.")).toBeInTheDocument();
  });

  it('resolves with a note and shows a refusal', async () => {
    const { user, callsTo } = setup(
      { [`POST /complaints/${UUID}/resolve`]: () => refusal(409, 'Shikoyat allaqachon hal qilingan.', { reason: 'already_resolved' }) },
      SUPPORT,
      { tab: 'complaints', complaint: null },
    );
    const list = await screen.findByRole('list', { name: "Shikoyatlar ro'yxati" });
    expect(within(list).getByText('Kutmoqda')).toBeInTheDocument();
    expect(within(list).getByText('Mijoz RIZQIMga yubordi')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ochiq (1)' })).toHaveAttribute('aria-pressed', 'true');
    await user.click(within(list).getByRole('button', { name: 'Sifat muammosi: Olim Egamov shikoyatini ochish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Shikoyat: Sifat muammosi' });
    await user.click(within(dialog).getByRole('button', { name: 'Hal qilish' }));
    const resolve = screen.getByRole('dialog', { name: 'Shikoyatni hal qilish' });
    const confirm = within(resolve).getByRole('button', { name: 'Hal qilindi' });
    expect(confirm).toBeDisabled();
    await user.type(within(resolve).getByLabelText('Qanday hal qilindi'), "Pul qaytarildi");
    await user.click(confirm);
    expect(await within(resolve).findByText('Shikoyat allaqachon hal qilingan.')).toBeInTheDocument();
    expect(callsTo('POST', `/complaints/${UUID}/resolve`)[0]?.body).toEqual({ note: 'Pul qaytarildi' });

    await user.click(screen.getByRole('button', { name: 'Hal qilindi (3)' }));
    await vi.waitFor(() => expect(callsTo('GET', '/complaints').at(-1)?.query.get('status')).toBe('resolved'));
  });

  it('takes a complaint and gives it to a colleague', async () => {
    const { user, callsTo } = setup(
      {
        [`PATCH /complaints/${UUID}`]: () => ok(complaintCard({ support_status: 'in_progress', support_status_label: "Ko'rib chiqilmoqda", assignee: { id: 1, name: 'Aziza Karimova' } })),
      },
      SUPPORT,
      { tab: 'complaints', complaint: UUID },
    );
    const dialog = await screen.findByRole('dialog', { name: 'Shikoyat: Sifat muammosi' });
    await user.click(within(dialog).getByRole('button', { name: 'Olish' }));
    expect(await screen.findByText("Shikoyat sizga biriktirildi: «Ko'rib chiqilmoqda».")).toBeInTheDocument();
    expect(callsTo('PATCH', `/complaints/${UUID}`)[0]?.body).toEqual({ support_status: 'in_progress' });
    await user.selectOptions(within(screen.getByRole('dialog')).getByLabelText("Mas'ul xodim"), 'Madina Yordam');
    await vi.waitFor(() => expect(callsTo('PATCH', `/complaints/${UUID}`).at(-1)?.body).toEqual({ assigned_to: 3 }));
  });

  it('a viewer reads the complaint but has no actions, and reads the tayyor javoblar', async () => {
    const { user } = setup({ [`GET /complaints/${UUID}`]: () => ok(complaintCard({ actions: { manage: false, reply: false, resolve: false, reopen: false } })) }, ['admin.support.view'], { tab: 'complaints', complaint: UUID });
    const dialog = await screen.findByRole('dialog', { name: 'Shikoyat: Sifat muammosi' });
    expect(within(dialog).queryByRole('group', { name: 'Amallar' })).toBeNull();
    expect(within(dialog).queryByLabelText("Mas'ul xodim")).toBeNull();
    await user.click(within(dialog).getByRole('button', { name: 'Yopish' }));
    // Without users.view there is no Foydalanuvchilar tab
    expect(screen.queryByRole('tab', { name: 'Foydalanuvchilar' })).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Tayyor javoblar' }));
    const canned = screen.getByRole('dialog', { name: 'Tayyor javoblar' });
    expect(await within(canned).findByText("Buyurtmangiz kechikkani uchun uzr so'raymiz.")).toBeInTheDocument();
    expect(within(canned).queryByRole('button', { name: "Tayyor javob qo'shish" })).toBeNull();
  });

  it('adds a tayyor javob', async () => {
    const { user, callsTo } = setup({ 'POST /support/canned-replies': () => ok(cannedReplies()[0], { message: '' }) }, SUPPORT, { tab: 'complaints', complaint: null });
    await screen.findByRole('list', { name: "Shikoyatlar ro'yxati" });
    await user.click(screen.getByRole('button', { name: 'Tayyor javoblar' }));
    const canned = screen.getByRole('dialog', { name: 'Tayyor javoblar' });
    await user.click(await within(canned).findByRole('button', { name: "Tayyor javob qo'shish" }));
    await user.type(within(canned).getByLabelText('Nomi'), 'Rahmat');
    await user.type(within(canned).getByLabelText('Matni'), 'Fikringiz uchun rahmat.');
    await user.click(within(canned).getByRole('button', { name: 'Saqlash' }));
    await vi.waitFor(() => expect(callsTo('POST', '/support/canned-replies')[0]?.body).toEqual({ title: 'Rahmat', body: 'Fikringiz uchun rahmat.' }));
  });

  it('shows the suspicious cases with their reasons and links', async () => {
    setup({}, SUPPORT, { tab: 'suspicious', complaint: null });
    const cancellations = await screen.findByRole('list', { name: "Ko'p bekor qilish" });
    expect(within(cancellations).getByText("7 kunda 4 ta buyurtma bekor qilindi: 3 tasini o'zi bekor qildi, 1 tasini olib ketmadi.")).toBeInTheDocument();
    expect(within(cancellations).getByRole('link', { name: 'Olim Egamov' })).toHaveAttribute('href', '/users/7');
    const promo = screen.getByRole('list', { name: "Promo-kodni ko'p hisobdan ishlatish" });
    expect(within(promo).getByRole('link', { name: /Ikkinchi hisob/ })).toHaveAttribute('href', '/users/22');
    expect(screen.getByText("Hozircha shubhali holat yo'q")).toBeInTheDocument();
  });

  it('says so to a member without either permission', async () => {
    setup({}, ['admin.orders.view']);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});
