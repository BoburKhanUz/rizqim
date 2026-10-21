import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MenuView } from './MenuView';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { group, ok, permissionsAnswer, product, variant } from './menuFixtures';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const osh = product();
const lagman = product({ id: 8, name: 'Lag\'mon', sort_order: 1, batch_tracked: false, is_active: false, variants: [variant({ id: 21, product_id: 8, name: 'Kichik', price: '30000.00' }), variant({ id: 22, product_id: 8, name: 'Katta', price: '42000.00' })] });
const choy = product({ id: 9, name: 'Ko\'k choy', category_id: 5, category: { id: 5, name: 'Ichimliklar' }, batch_tracked: false, variants: [variant({ id: 31, product_id: 9, name: '1 choynak', price: '8000.00' })] });

describe('the menu', () => {
  it('lists the dishes by category with their prices, badges and status', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(), 'GET /api/v1/owner/products': () => json({ success: true, data: [osh, lagman, choy] }) });
    renderPage(<MenuView />);

    const issiq = await screen.findByRole('region', { name: 'Issiq taomlar' });
    expect(within(issiq).getByText('Osh')).toBeInTheDocument();
    expect(within(issiq).getByText("Qozon bo'yicha")).toBeInTheDocument();
    expect(within(issiq).getByText(/35\s000\sso'm/)).toBeInTheDocument();
    // two variants: the range
    expect(within(issiq).getByText(/30\s000\sso'm\s–\s42\s000\sso'm/)).toBeInTheDocument();
    expect(within(issiq).getByRole('switch', { name: /Lag'mon/ })).toHaveAttribute('aria-checked', 'false');
    expect(within(screen.getByRole('region', { name: 'Ichimliklar' })).getByText("Ko'k choy")).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Taom qo'shish/ })).toHaveAttribute('href', '/menu/new');
  });

  it('searches by name and filters by category and status', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(), 'GET /api/v1/owner/products': () => json({ success: true, data: [osh, lagman, choy] }) });
    renderPage(<MenuView />);
    await screen.findByText('Osh');
    const user = userEvent.setup();

    await user.type(screen.getByRole('searchbox'), 'choy');
    expect(screen.queryByText('Osh')).not.toBeInTheDocument();
    expect(screen.getByText("Ko'k choy")).toBeInTheDocument();
    await user.clear(screen.getByRole('searchbox'));

    await user.selectOptions(screen.getByLabelText('Kategoriya'), 'Issiq taomlar');
    expect(screen.queryByText("Ko'k choy")).not.toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText('Holati'), "Faol emas");
    expect(screen.queryByText('Osh')).not.toBeInTheDocument();
    expect(screen.getByText("Lag'mon")).toBeInTheDocument();

    await user.type(screen.getByRole('searchbox'), 'zzz');
    expect(await screen.findByText('Hech narsa topilmadi')).toBeInTheDocument();
  });

  it('switches a dish on or off with the toggle_active flag and shows the server\'s refusal', async () => {
    let status = 403;
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }),
      'POST /api/v1/owner/products/7': () => (status === 403 ? json({ error: 'Unauthorized' }, 403) : ok(null)),
    });
    renderPage(<MenuView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('switch', { name: /Osh/ }));
    // an English refusal is shown in Uzbek
    expect(await screen.findByRole('alert')).toHaveTextContent("Bu amal uchun ruxsatingiz yo'q.");
    const post = calls.find((call) => call.method === 'POST')!;
    expect(new URL(post.url).searchParams.get('toggle_active')).toBe('1');

    status = 200;
    await user.click(screen.getByRole('switch', { name: /Osh/ }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'POST')).toHaveLength(2));
  });

  it('deletes a dish after a confirmation', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }),
      'DELETE /api/v1/owner/products/7': () => json({ success: true, message: 'Product deleted successfully' }),
    });
    renderPage(<MenuView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: "O'chirish: Osh" }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('«Osh» menyudan o\'chiriladi');
    await user.click(within(dialog).getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE')).toBe(true));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
  });

  it('is read-only without menu.manage', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']), 'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }) });
    renderPage(<MenuView />);
    await screen.findByText('Osh');
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /qo'shish/ })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ochish' })).toHaveAttribute('href', '/menu/7');
  });

  it('says the menu is empty, and offers the first dish', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(), 'GET /api/v1/owner/products': () => json({ success: true, data: [] }) });
    renderPage(<MenuView />);
    expect(await screen.findByText("Menyu hali bo'sh")).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: "Taom qo'shish" }).length).toBeGreaterThan(0);
  });

  it('shows a retry when the dishes do not load', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissionsAnswer(), 'GET /api/v1/owner/products': () => json({ message: 'x' }, 500) });
    renderPage(<MenuView />);
    expect(await screen.findByText("Ma'lumot yuklanmadi")).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });
});

describe('the modifier groups tab', () => {
  it('lists the groups with their rules, options and price deltas', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }),
      'GET /api/v1/owner/modifier-groups': () => ok([group({ is_required: true, min_select: 1, max_select: 1 }), group({ id: 4, name: "Qo'shimchalar", max_select: null, product_ids: [] })]),
    });
    renderPage(<MenuView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('tab', { name: 'Modifikatorlar' }));

    expect(await screen.findByText('Sous tanlang')).toBeInTheDocument();
    expect(screen.getByText('Majburiy')).toBeInTheDocument();
    expect(screen.getByText('1 ta tanlanadi · 1 ta taomda')).toBeInTheDocument();
    expect(screen.getByText('Kamida 0 ta tanlanadi · 0 ta taomda')).toBeInTheDocument();
    expect(screen.getAllByText(/Achchiq/)[0]).toHaveTextContent(/3\s000\sso'm/);
    expect(screen.getByRole('link', { name: "Guruh qo'shish" })).toHaveAttribute('href', '/menu/groups/new');
  });

  it('deletes a group after a confirmation and shows an empty state when none is left', async () => {
    let groups = [group()];
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissionsAnswer(),
      'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }),
      'GET /api/v1/owner/modifier-groups': () => ok(groups),
      'DELETE /api/v1/owner/modifier-groups/3': () => {
        groups = [];
        return ok(null);
      },
    });
    renderPage(<MenuView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('tab', { name: 'Modifikatorlar' }));
    await user.click(await screen.findByRole('button', { name: "O'chirish: Sous tanlang" }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "O'chirish" }));
    expect(await screen.findByText("Modifikator guruhlari yo'q")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'DELETE')).toBe(true);
  });
});

