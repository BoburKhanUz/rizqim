import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AppShell } from './AppShell';
import { AuthProvider } from './AuthProvider';
import { I18nProvider } from './I18nProvider';
import { ApiConfigContext } from '@/lib/apiConfig';
import { setSession } from '@/lib/session/client';
import { API, json, mockFetch } from '@/test/helpers';

const replace = vi.fn();
let pathname = '/dashboard';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace, refresh: vi.fn() }),
  usePathname: () => pathname,
}));

afterEach(() => {
  setSession(null);
  pathname = '/dashboard';
});

const refreshed = () => json({ access_token: 'access', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id: 7, name: 'Dilshod', role: 'manager' } });
const access = (permissions: string[], role = 'manager') => json({ success: true, message: 'ok', data: { role, store_id: 1, branch_id: 2, branch_scoped: true, permissions, device_uuid: null } });

function renderShell() {
  const queryClient = new QueryClient();
  return render(
    <I18nProvider language="uz">
      <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
        <QueryClientProvider client={queryClient}>
          <AuthProvider apiBaseUrl={API}>
            <AppShell>
              <p>page content</p>
            </AppShell>
          </AuthProvider>
        </QueryClientProvider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
}

describe('AppShell', () => {
  it('restores the session from the refresh cookie and shows only the sections the permissions open', async () => {
    mockFetch({
      'POST /api/session/refresh': refreshed,
      'GET /api/v1/me/permissions': () => access(['orders.view', 'menu.view', 'stoplist.manage', 'staff.view', 'reports.view']),
    });
    renderShell();

    const nav = await screen.findByRole('navigation', { name: 'Asosiy menyu' });
    const labels = Array.from(nav.querySelectorAll('a')).map((link) => link.textContent);
    expect(labels).toEqual(['Boshqaruv paneli', 'Buyurtmalar', 'Menyu', 'Stop-list', 'Xodimlar', 'Hisobotlar']);
    expect(screen.getByText('page content')).toBeInTheDocument();
    expect(screen.getByText('Menejer')).toBeInTheDocument();
  });

  it('sends a signed-out browser to the sign-in page', async () => {
    mockFetch({ 'POST /api/session/refresh': () => json({ reason: 'no_session' }, 401) });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
  });

  it('says there is no access for an account without any panel permission', async () => {
    mockFetch({
      'POST /api/session/refresh': refreshed,
      'GET /api/v1/me/permissions': () => access([], 'customer'),
    });
    renderShell();
    expect(await screen.findByText("Bu hisob uchun panelga kirish yo'q")).toBeInTheDocument();
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Chiqish' })).toBeInTheDocument();
  });

  it('keeps a section the permissions do not open out and moves to the first one that is open', async () => {
    pathname = '/finance';
    mockFetch({
      'POST /api/session/refresh': refreshed,
      'GET /api/v1/me/permissions': () => access(['orders.view', 'reports.view']),
    });
    renderShell();
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/dashboard'));
    expect(screen.queryByText('page content')).not.toBeInTheDocument();
  });

  it('offers a retry when the server cannot be reached while restoring the session', async () => {
    mockFetch({ 'POST /api/session/refresh': () => json({}, 502) });
    renderShell();
    expect(await screen.findByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });
});
