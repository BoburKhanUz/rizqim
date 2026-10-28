import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { AppShell } from './AppShell';

const replace = vi.fn();
const push = vi.fn();
let pathname = '/today';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace, push }),
  usePathname: () => pathname,
}));

afterEach(() => {
  pathname = '/today';
  replace.mockReset();
  push.mockReset();
});

const OPERATOR = ['admin.today.view', 'admin.orders.view', 'admin.stores.view', 'admin.monitoring.view', 'admin.devices.view'];

function renderShell() {
  return renderPage(
    <AppShell>
      <p>page content</p>
    </AppShell>,
  );
}

const sidebar = () => screen.getByRole('navigation', { name: 'Asosiy menyu' });
const linkNames = () => within(sidebar()).getAllByRole('link').map((link) => link.textContent);

describe('AppShell', () => {
  it('shows every section in the order of the plan, with Tizim and its four pages last', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()) });
    renderShell();
    await screen.findByText('page content');
    expect(linkNames()).toEqual([
      'Bugun', "Ko'rsatkichlar", 'Buyurtmalar', 'Ovqatxonalar', 'POS va obunalar', 'Moliya', 'Mijozlar va yordam', 'Marketing', 'Monitoring', 'Integratsiyalar',
      'Jamoa', 'Rollar', 'Harakatlar jurnali', 'Sessiyalarim',
    ]);
    expect(within(sidebar()).getByText('Tizim')).toBeInTheDocument();
    expect(within(sidebar()).getByRole('link', { name: 'Bugun' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('Superadmin')).toBeInTheDocument();
  });

  it('hides the sections the member may not open (from GET /auth/me)', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody({ platform_role: 'operator', member: { id: 2, name: 'Sardor', email: 's@rizqim.uz', platform_role: 'operator' }, permissions: OPERATOR })) });
    renderShell();
    await screen.findByText('page content');
    expect(linkNames()).toEqual(['Bugun', "Ko'rsatkichlar", 'Buyurtmalar', 'Ovqatxonalar', 'POS va obunalar', 'Monitoring', 'Sessiyalarim']);
    expect(screen.getByText('Operator')).toBeInTheDocument();
  });

  it('explains a menu that holds only Sessiyalarim (a role with no section yet)', async () => {
    pathname = '/sessions';
    mockFetch({ 'GET /auth/me': () => json(meBody({ permissions: [] })) });
    renderShell();
    await screen.findByText('page content');
    expect(linkNames()).toEqual(['Sessiyalarim']);
    expect(screen.getByText("Sizda hali hech bir bo'limga ruxsat yo'q")).toBeInTheDocument();
  });

  it('sends a member who opens a section they may not see to the first one they may', async () => {
    pathname = '/team';
    mockFetch({ 'GET /auth/me': () => json(meBody({ permissions: OPERATOR })) });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/today'));
    expect(screen.queryByText('page content')).toBeNull();
  });

  it('sends a signed-out visitor to the sign-in page', async () => {
    mockFetch({ 'GET /auth/me': () => refusal(401, 'Unauthenticated.') });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
  });

  it('says the server cannot be reached and offers another try', async () => {
    let up = false;
    mockFetch({ 'GET /auth/me': () => (up ? json(meBody()) : refusal(500, 'x')) });
    const user = userEvent.setup();
    renderShell();
    expect(await screen.findByText("Serverga ulanib bo'lmadi. Internetni tekshiring.")).toBeInTheDocument();
    up = true;
    await user.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByText('page content')).toBeInTheDocument();
  });

  it('signs out through the sidebar', async () => {
    const { callsTo } = mockFetch({ 'GET /auth/me': () => json(meBody()), 'POST /auth/logout': () => ok([]) });
    const user = userEvent.setup();
    renderShell();
    await screen.findByText('page content');
    await user.click(screen.getByRole('button', { name: 'Chiqish' }));
    await waitFor(() => expect(callsTo('POST', '/auth/logout')).toHaveLength(1));
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
  });

  it('opens the search with Ctrl+K and with the Qidirish button', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()) });
    const user = userEvent.setup();
    renderShell();
    await screen.findByText('page content');
    await user.keyboard('{Control>}k{/Control}');
    expect(await screen.findByRole('dialog', { name: 'Qidirish' })).toBeInTheDocument();
    expect(screen.getByRole('combobox')).toHaveFocus();
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).toBeNull();

    await user.click(screen.getAllByRole('button', { name: /Qidirish/ })[0]!);
    expect(await screen.findByRole('dialog', { name: 'Qidirish' })).toBeInTheDocument();
  });

  it('has a menu button for phones that opens the drawer', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()) });
    const user = userEvent.setup();
    renderShell();
    await screen.findByText('page content');
    await user.click(screen.getByRole('button', { name: 'Menyuni ochish' }));
    expect(screen.getByRole('button', { name: 'Menyuni yopish' })).toHaveAttribute('aria-expanded', 'true');
  });
});
