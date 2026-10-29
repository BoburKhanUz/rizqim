import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StaffApiProvider } from '@rizqim/staff';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AppShell } from './AppShell';
import { AuthProvider } from './AuthProvider';
import { I18nProvider } from './I18nProvider';
import { useBranchScope } from './shell/BranchScope';
import { ApiConfigContext } from '@/lib/apiConfig';
import { getStaffApi, setSession } from '@/lib/session/client';
import { API, json, mockFetch } from '@/test/helpers';

const replace = vi.fn();
let pathname = '/dashboard';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace, refresh: vi.fn() }),
  usePathname: () => pathname,
}));

beforeEach(() => {
  window.localStorage.clear();
  document.title = 'Rizqim Hamkor';
});

afterEach(() => {
  setSession(null);
  pathname = '/dashboard';
  replace.mockReset();
});

const refreshed = (id = 7, role = 'manager') =>
  json({ access_token: 'access', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id, name: 'Dilshod', role } });
const access = (permissions: string[], role = 'manager', branch: { id: number | null; scoped: boolean } = { id: 2, scoped: true }) =>
  json({ success: true, message: 'ok', data: { role, store_id: 1, branch_id: branch.id, branch_scoped: branch.scoped, permissions, device_uuid: null } });
const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: 'ok', data, ...extra });
const branch = (id: number, name: string) => ({
  id,
  name,
  phone: null,
  address: null,
  region_id: null,
  district_id: null,
  latitude: null,
  longitude: null,
  radius_km: 5,
  status: 'active',
  is_active: true,
  working_hours: { open: null, close: null, all_day: true, overnight: false, timezone: 'Asia/Tashkent' },
  is_open_now: true,
  created_at: null,
  updated_at: null,
});
const noOrders = () => ok([], { meta: { next_cursor: null, per_page: 100 } });

const OWNER = [
  'orders.view', 'orders.manage', 'orders.cancel', 'menu.view', 'menu.manage', 'stoplist.manage', 'batches.manage', 'staff.view', 'branches.manage', 'zones.manage', 'settings.manage', 'reports.view', 'finance.view',
  // P3-23: the POS sections
  'tables.manage', 'bookings.manage', 'customer_accounts.manage', 'pos.close_shift',
];

function ShowBranch() {
  const { branchId } = useBranchScope();
  return <p data-testid="branch">{branchId === null ? 'all' : String(branchId)}</p>;
}

function renderShell(children = <p>page content</p>) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <I18nProvider language="uz">
      <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
        <StaffApiProvider api={getStaffApi(API)}>
          <QueryClientProvider client={queryClient}>
            <AuthProvider apiBaseUrl={API}>
              <AppShell>{children}</AppShell>
            </AuthProvider>
          </QueryClientProvider>
        </StaffApiProvider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
}

const navLabels = async () => {
  const nav = await screen.findByRole('navigation', { name: 'Asosiy menyu' });
  return Array.from(nav.querySelectorAll('a')).map((link) => link.querySelector('span')?.textContent);
};

describe('AppShell', () => {
  it('restores the session from the refresh cookie and shows only the sections the permissions open', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(),
      'GET /api/v1/me/permissions': () => access(['orders.view', 'menu.view', 'stoplist.manage', 'staff.view', 'branches.manage', 'reports.view']),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell();

    // reports.view also opens the shifts (X and Z reports) and the fiscal receipts
    expect(await navLabels()).toEqual(['Boshqaruv paneli', 'Buyurtmalar', 'Menyu', 'Stop-list', 'Bugungi menyu', 'Hosil hisoboti', 'Smenalar', 'Xodimlar', 'Filiallar', 'Fiskal', 'Hisobotlar']);
    expect(screen.getByText('page content')).toBeInTheDocument();
    expect(screen.getByText('Menejer')).toBeInTheDocument();
  });

  it('shows every section, zones included, to an owner, in the planned order', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(1, 'owner'),
      'GET /api/v1/me/permissions': () => access(OWNER, 'owner', { id: null, scoped: false }),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell();
    expect(await navLabels()).toEqual([
      'Boshqaruv paneli', 'Buyurtmalar', 'Bronlar', 'Zal va xonalar', 'Menyu', 'Stop-list', 'Qozon', 'Bugungi menyu', 'Hosil hisoboti', 'Smenalar', 'Nasiya', 'Xodimlar', 'Filiallar', 'Yetkazish zonalari', 'Sozlamalar',
      'Kassa sozlamalari', 'Fiskal', 'Hisobotlar', 'Moliya', "Tarif va to'lov",
    ]);
  });

  it('shows the unpaid-invoice banner across the panel, with the link to pay for a person who may open the billing page', async () => {
    const notice = {
      level: 'reminder',
      message: 'x',
      days_overdue: 3,
      lock_at: new Date(Date.now() + 4 * 86_400_000).toISOString(),
      invoice: { uuid: 'i-1', number: 'OB-202610-1', amount: '249000.00', outstanding: '249000.00', due_date: '2026-11-11' },
    };
    mockFetch({
      'POST /api/session/refresh': () => refreshed(1, 'owner'),
      'GET /api/v1/me/permissions': () => access(OWNER, 'owner', { id: null, scoped: false }),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
      'GET /api/v1/owner/billing/entitlements': () => ok({ notice, lock_at: notice.lock_at, settings_locked: false, legacy: false, features: {}, plan: null }),
    });
    renderShell();
    const banner = await screen.findByRole('status', { name: "To'lanmagan hisob-faktura haqida" });
    expect(banner).toHaveTextContent("To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi");
    expect(within(banner).getByRole('link', { name: "To'lash" })).toHaveAttribute('href', '/billing');
    expect(screen.getByText('page content')).toBeInTheDocument();
  });

  it('sends a signed-out browser to the sign-in page', async () => {
    mockFetch({ 'POST /api/session/refresh': () => json({ reason: 'no_session' }, 401) });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
  });

  it('says there is no access for an account without any panel permission', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(),
      'GET /api/v1/me/permissions': () => access([], 'customer'),
    });
    renderShell();
    expect(await screen.findByText("Bu hisob uchun panelga kirish yo'q")).toBeInTheDocument();
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Chiqish' })).toBeInTheDocument();
  });

  it('keeps a section the permissions do not open out and moves to the first one that is open', async () => {
    pathname = '/zones';
    mockFetch({
      'POST /api/session/refresh': () => refreshed(),
      'GET /api/v1/me/permissions': () => access(['orders.view', 'reports.view']),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/dashboard'));
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
  });

  it('opens the kazan page to a cook who has batches.manage but not the stop-list', async () => {
    pathname = '/stoplist/batches';
    mockFetch({
      'POST /api/session/refresh': () => refreshed(5, 'cook'),
      'GET /api/v1/me/permissions': () => access(['orders.view', 'kitchen.manage', 'menu.view', 'batches.manage'], 'cook'),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell();
    expect(await navLabels()).toEqual(['Boshqaruv paneli', 'Buyurtmalar', 'Menyu', 'Qozon']);
    expect(screen.getByText('page content')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Qozon' })).toHaveAttribute('aria-current', 'page');
    expect(replace).not.toHaveBeenCalled();
  });

  it('offers a retry when the server cannot be reached while restoring the session', async () => {
    mockFetch({ 'POST /api/session/refresh': () => json({}, 502) });
    renderShell();
    expect(await screen.findByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });
});

describe('the branch switcher', () => {
  const ownerRoutes = (staffOrders = noOrders) => ({
    'POST /api/session/refresh': () => refreshed(1, 'owner'),
    'GET /api/v1/me/permissions': () => access(OWNER, 'owner', { id: null, scoped: false }),
    'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor'), branch(3, 'Yunusobod')]),
    'GET /api/v1/staff/orders': staffOrders,
  });

  it('lets an owner work on all branches or one, and remembers the choice for this person', async () => {
    const user = userEvent.setup();
    const { calls } = mockFetch(ownerRoutes());
    const view = renderShell(<ShowBranch />);

    const picker = await screen.findByRole('combobox', { name: 'Filial' });
    expect(within(picker).getAllByRole('option').map((option) => option.textContent)).toEqual(['Barcha filiallar', 'Chilonzor', 'Yunusobod']);
    expect(screen.getByTestId('branch')).toHaveTextContent('all');

    await user.selectOptions(picker, '3');
    expect(screen.getByTestId('branch')).toHaveTextContent('3');
    expect(window.localStorage.getItem('rz_branch:1:1')).toBe('3');
    // The new-orders watch follows the branch
    await waitFor(() => expect(calls.some((call) => new URL(call.url).searchParams.get('branch_id') === '3')).toBe(true));

    view.unmount();
    mockFetch(ownerRoutes());
    renderShell(<ShowBranch />);
    await waitFor(() => expect(screen.getByTestId('branch')).toHaveTextContent('3'));
  });

  it('forgets a saved branch that is gone', async () => {
    window.localStorage.setItem('rz_branch:1:1', '99');
    mockFetch(ownerRoutes());
    renderShell(<ShowBranch />);
    await screen.findByRole('combobox', { name: 'Filial' });
    await waitFor(() => expect(screen.getByTestId('branch')).toHaveTextContent('all'));
  });

  it('fixes a branch-bound manager to their own branch', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(),
      'GET /api/v1/me/permissions': () => access(['orders.view', 'reports.view'], 'manager', { id: 2, scoped: true }),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell(<ShowBranch />);
    expect(await screen.findByText('Filialingiz: Chilonzor')).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Filial' })).not.toBeInTheDocument();
    expect(screen.getByTestId('branch')).toHaveTextContent('2');
  });

  it('survives a browser that refuses storage', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied');
    });
    const user = userEvent.setup();
    mockFetch(ownerRoutes());
    renderShell(<ShowBranch />);
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Filial' }), '2');
    expect(screen.getByTestId('branch')).toHaveTextContent('2');
  });
});

describe('new orders in the shell', () => {
  it('counts the orders waiting for acceptance in the sidebar and the tab title', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(1, 'owner'),
      'GET /api/v1/me/permissions': () => access(OWNER, 'owner', { id: null, scoped: false }),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': (request) => {
        expect(new URL(request.url).searchParams.get('status')).toBe('new');
        return ok([{ uuid: 'a', status: 'new' }, { uuid: 'b', status: 'new' }], { meta: { next_cursor: null, per_page: 100 } });
      },
    });
    renderShell();
    const nav = await screen.findByRole('navigation', { name: 'Asosiy menyu' });
    await waitFor(() => expect(within(nav).getByRole('link', { name: /Buyurtmalar/ })).toHaveTextContent('2'));
    expect(within(nav).getByText('(2 ta yangi buyurtma)')).toBeInTheDocument();
    expect(document.title).toBe('(2) Rizqim Hamkor');
    // Real-time is not configured: the bar says the list polls
    expect(screen.getByText('Har 15 soniyada yangilanadi')).toBeInTheDocument();
  });

  it('mutes and unmutes the new order sound, remembered in this browser', async () => {
    const user = userEvent.setup();
    mockFetch({
      'POST /api/session/refresh': () => refreshed(1, 'owner'),
      'GET /api/v1/me/permissions': () => access(OWNER, 'owner', { id: null, scoped: false }),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
      'GET /api/v1/staff/orders': noOrders,
    });
    renderShell();
    const mute = await screen.findByRole('button', { name: "Yangi buyurtma ovozini o'chirish" });
    // No click on the page yet: the browser does not allow sound, the bar says how to allow it
    expect(screen.getByText('Ovoz chiqishi uchun sahifaning istalgan joyini bir marta bosing')).toBeInTheDocument();
    await user.click(mute);
    expect(window.localStorage.getItem('rz_order_sound')).toBe('off');
    expect(screen.getByRole('button', { name: 'Yangi buyurtma ovozini yoqish' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.queryByText('Ovoz chiqishi uchun sahifaning istalgan joyini bir marta bosing')).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Yangi buyurtma ovozini yoqish' }));
    expect(window.localStorage.getItem('rz_order_sound')).toBe('on');
  });

  it('shows no live controls to someone who does not see orders', async () => {
    mockFetch({
      'POST /api/session/refresh': () => refreshed(),
      'GET /api/v1/me/permissions': () => access(['menu.view'], 'manager'),
      'GET /api/v1/owner/branches': () => ok([branch(2, 'Chilonzor')]),
    });
    renderShell();
    await screen.findByRole('navigation', { name: 'Asosiy menyu' });
    expect(screen.queryByRole('button', { name: /ovozini/ })).not.toBeInTheDocument();
  });
});
