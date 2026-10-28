import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { call } from '@/lib/api/client';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { useAuth, useCan } from './AuthProvider';

function Who() {
  const { state, logout } = useAuth();
  const { can } = useCan();
  return (
    <div>
      <p>{state.status === 'ready' ? `${state.me.member.name}|${state.me.platform_role}` : state.status === 'anonymous' ? `anonymous:${state.reason ?? ''}` : state.status}</p>
      <p>{can('team.manage') ? 'may-team' : 'no-team'}</p>
      <button onClick={() => void logout()}>out</button>
      <button onClick={() => void call('GET', '/thing').catch(() => undefined)}>poke</button>
    </div>
  );
}

describe('AuthProvider', () => {
  it('restores the session from GET /auth/me and knows the permissions', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()) });
    renderPage(<Who />);
    expect(await screen.findByText('Aziza Karimova|superadmin')).toBeInTheDocument();
    expect(screen.getByText('may-team')).toBeInTheDocument();
  });

  it('reads "admin."-prefixed and bare permissions alike and refuses what is not granted', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody({ permissions: ['today.view', 'admin.audit.view'] })) });
    renderPage(<Who />);
    expect(await screen.findByText('no-team')).toBeInTheDocument();
  });

  it('is anonymous without a session (401) and in error when the server cannot be reached', async () => {
    mockFetch({ 'GET /auth/me': () => refusal(401, 'Unauthenticated.') });
    const first = renderPage(<Who />);
    expect(await screen.findByText('anonymous:')).toBeInTheDocument();
    first.unmount();

    mockFetch({ 'GET /auth/me': () => refusal(500, 'Server xatosi') });
    renderPage(<Who />);
    expect(await screen.findByText('error')).toBeInTheDocument();
  });

  it('goes anonymous when any later call finds the session over (idle limit, revoked elsewhere)', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()), 'GET /thing': () => refusal(401, 'Unauthenticated.') });
    const user = userEvent.setup();
    renderPage(<Who />);
    await screen.findByText('Aziza Karimova|superadmin');
    await user.click(screen.getByRole('button', { name: 'poke' }));
    expect(await screen.findByText('anonymous:expired')).toBeInTheDocument();
  });

  it('signs out on the backend and forgets the member', async () => {
    const { callsTo } = mockFetch({ 'GET /auth/me': () => json(meBody()), 'POST /auth/logout': () => ok([]) });
    const user = userEvent.setup();
    renderPage(<Who />);
    await screen.findByText('Aziza Karimova|superadmin');
    await user.click(screen.getByRole('button', { name: 'out' }));
    await waitFor(() => expect(screen.getByText('anonymous:')).toBeInTheDocument());
    expect(callsTo('POST', '/auth/logout')).toHaveLength(1);
  });
});
