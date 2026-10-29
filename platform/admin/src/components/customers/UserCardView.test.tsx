import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { userCard } from './fixtures';
import { UserCardView } from './UserCardView';

const SUPPORT = ['users.view', 'support.view', 'support.manage', 'orders.view'].map((name) => `admin.${name}`);

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = SUPPORT) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /users/7': () => ok(userCard()),
    ...routes,
  });
  renderPage(<UserCardView id={7} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('UserCardView', () => {
  it('shows the profile, totals and linked accounts, and the other tabs', async () => {
    const { user } = setup();
    expect(await screen.findByRole('heading', { name: 'Olim Egamov', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('+998 90 *** ** 67 · SMS bilan tasdiqlangan')).toBeInTheDocument();
    expect(screen.getByText('12 ta (yetkazilgan 10, bekor qilingan 2)')).toBeInTheDocument();
    expect(screen.getAllByText('Doimiy mijoz (VIP)').length).toBeGreaterThan(0);
    expect(within(screen.getByRole('list', { name: 'Shu telefon yoki Telegramdagi boshqa hisoblar' })).getByRole('link', { name: 'Olim (xodim)' })).toHaveAttribute('href', '/users/9');

    await user.click(screen.getByRole('tab', { name: 'Buyurtmalar' }));
    expect(within(screen.getByRole('list', { name: 'Oxirgi buyurtmalar' })).getByRole('link', { name: '#AB12CD' })).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666');
    expect(screen.getByText('Bir taom kelmadi')).toBeInTheDocument();

    await user.click(screen.getByRole('tab', { name: 'Keshbek' }));
    expect(screen.getByText(/Balans: 15 000/)).toBeInTheDocument();
    expect(within(screen.getByRole('list', { name: 'Keshbek harakatlari' })).getByText(/−30 000/)).toBeInTheDocument();

    await user.click(screen.getByRole('tab', { name: 'Shikoyatlar' }));
    expect(screen.getByRole('link', { name: 'Sifat muammosi' })).toHaveAttribute('href', '/customers?tab=complaints&complaint=cc000000-1111-4222-8333-444455556666');

    await user.click(screen.getByRole('tab', { name: 'Sessiyalar' }));
    expect(screen.getByText('Pixel 8')).toBeInTheDocument();
    expect(screen.getByText('Mini App / Telegram')).toBeInTheDocument();
  });

  it('blocks with a reason and shows the answer', async () => {
    const blocked = userCard({ block: { blocked: true, at: '2026-10-28T10:00:00+05:00', reason: 'Firibgarlik', by: { id: 1, name: 'Aziza Karimova' } }, actions: { block: false, unblock: true, close_sessions: true } });
    let current = userCard();
    const { user, callsTo } = setup({
      'GET /users/7': () => ok(current),
      'POST /users/7/block': () => {
        current = blocked;
        return ok(blocked);
      },
    });
    await user.click(await screen.findByRole('button', { name: 'Bloklash' }));
    const dialog = screen.getByRole('dialog', { name: 'Hisobni bloklash' });
    expect(within(dialog).getByText(/hozirgi barcha sessiyalari \(3\) yopiladi/)).toBeInTheDocument();
    const confirm = within(dialog).getByRole('button', { name: 'Ha, bloklash' });
    expect(confirm).toBeDisabled();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Firibgarlik');
    await user.click(confirm);
    expect(await screen.findByText('Foydalanuvchi bloklandi. Uning barcha sessiyalari yopildi.')).toBeInTheDocument();
    expect(callsTo('POST', '/users/7/block')[0]?.body).toEqual({ reason: 'Firibgarlik' });
    expect(screen.getByRole('button', { name: 'Blokdan chiqarish' })).toBeInTheDocument();
    expect(screen.getByText(/Aziza Karimova blokladi. Sabab: Firibgarlik/)).toBeInTheDocument();
  });

  it('a viewer reads without actions; parts without permission are not shown', async () => {
    setup({ 'GET /users/7': () => ok(userCard({ orders: null, refunds: null, complaints: null, audit: null, actions: { block: false, unblock: false, close_sessions: false } })) }, ['admin.users.view']);
    await screen.findByRole('heading', { name: 'Olim Egamov', level: 1 });
    expect(screen.queryByRole('group', { name: 'Amallar' })).toBeNull();
    expect(screen.queryByRole('tab', { name: 'Buyurtmalar' })).toBeNull();
    expect(screen.queryByRole('tab', { name: 'Jurnal' })).toBeNull();
    expect(screen.getByRole('tab', { name: 'Keshbek' })).toBeInTheDocument();
  });

  it('says a team account or a missing one is not here', async () => {
    setup({ 'GET /users/7': () => refusal(404, 'Foydalanuvchi topilmadi.', { reason: 'not_found' }) });
    expect(await screen.findByText('Foydalanuvchi topilmadi')).toBeInTheDocument();
  });
});
