import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider, useAuth } from './AuthProvider';
import { setSession } from '@/lib/session/client';
import { API, json, mockFetch } from '@/test/helpers';

const permissions = { data: { role: 'owner', permissions: ['orders.view'], branch_scoped: false, store_id: 1, branch_id: null, device_uuid: null, timezone: 'Asia/Tashkent' } };
const restored = (user: unknown, token = 'access-1') => json({ access_token: token, expires_at: new Date(Date.now() + 900_000).toISOString(), user });

function Who() {
  const { state } = useAuth();
  return <p>{state.status === 'ready' ? `${state.userName}#${state.access.userId}` : state.status}</p>;
}

function renderProvider() {
  render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider apiBaseUrl={API}>
        <Who />
      </AuthProvider>
    </QueryClientProvider>,
  );
}

afterEach(() => {
  setSession(null);
  localStorage.clear();
});

describe('AuthProvider', () => {
  it('restores the session and takes the user from the refresh answer', async () => {
    const { calls } = mockFetch({
      'POST /api/session/refresh': () => restored({ id: 7, name: 'Aziz', role: 'owner' }),
      'GET /api/v1/me/permissions': () => json(permissions),
    });
    renderProvider();
    expect(await screen.findByText('Aziz#7')).toBeInTheDocument();
    expect(calls.some((call) => call.url.endsWith('/v1/user'))).toBe(false);
  });

  it('copes with a refresh answer that names no user: the user comes from GET /user', async () => {
    mockFetch({
      'POST /api/session/refresh': () => restored(null),
      'GET /api/v1/me/permissions': () => json(permissions),
      'GET /api/v1/user': () => json({ success: true, data: { user: { id: 9, name: 'Olim' }, store: null, sms_verification_required: false } }),
    });
    renderProvider();
    expect(await screen.findByText('Olim#9')).toBeInTheDocument();
  });

  it('follows another tab that signs in while this one shows the sign-in page', async () => {
    mockFetch({
      'POST /api/session/refresh': () => json({ message: 'Kirish talab qilinadi.', reason: 'no_session' }, 401),
      'POST /api/session/login': () => restored({ id: 7, name: 'Aziz', role: 'owner' }, 'access-2'),
      'GET /api/v1/me/permissions': () => json(permissions),
    });
    renderProvider();
    expect(await screen.findByText('anonymous')).toBeInTheDocument();

    // The other tab: its own copy of the session module, the same BroadcastChannel
    vi.resetModules();
    const otherTab = await import('@/lib/session/client');
    await otherTab.login('+998901234567', '123456');

    expect(await screen.findByText('Aziz#7')).toBeInTheDocument();
    otherTab.setSession(null);
  });
});
