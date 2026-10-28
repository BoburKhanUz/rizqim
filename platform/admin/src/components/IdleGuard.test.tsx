import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { markActive } from '@/lib/api/client';
import { json, meBody, mockFetch, ok } from '@/test/helpers';
import { AuthProvider, useAuth } from './AuthProvider';
import { I18nProvider } from './I18nProvider';
import { IdleGuard } from './IdleGuard';

const MIN = 60_000;

function State() {
  const { state } = useAuth();
  return <p>{state.status === 'anonymous' ? `anonymous:${state.reason ?? ''}` : state.status}</p>;
}

function renderGuard() {
  return render(
    <I18nProvider language="uz">
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <State />
          <IdleGuard />
        </AuthProvider>
      </QueryClientProvider>
    </I18nProvider>,
  );
}

beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true, toFake: ['Date', 'setInterval', 'clearInterval', 'setTimeout', 'clearTimeout'] }));
afterEach(() => vi.useRealTimers());

describe('idle logout warning', () => {
  it('warns at 55 minutes without a call, with the minutes left, and signs out at 60', async () => {
    const { callsTo } = mockFetch({ 'GET /auth/me': () => json(meBody()), 'POST /auth/logout': () => ok([]) });
    renderGuard();
    await screen.findByText('ready');
    markActive();

    await act(async () => void vi.advanceTimersByTime(54 * MIN));
    expect(screen.queryByRole('alertdialog')).toBeNull();

    await act(async () => void vi.advanceTimersByTime(MIN + 2_000));
    const dialog = screen.getByRole('alertdialog', { name: 'Seans tugayapti' });
    expect(dialog).toHaveTextContent(/Qolgan vaqt: 4:5\d/);

    await act(async () => void vi.advanceTimersByTime(5 * MIN));
    expect(screen.getByText('anonymous:idle')).toBeInTheDocument();
    expect(callsTo('POST', '/auth/logout')).toHaveLength(1);
  });

  it('"Men shu yerdaman" renews the session with one cheap call and closes the warning', async () => {
    const { callsTo } = mockFetch({ 'GET /auth/me': () => json(meBody()) });
    const user = userEvent.setup({ advanceTimers: (ms) => vi.advanceTimersByTime(ms) });
    renderGuard();
    await screen.findByText('ready');
    markActive();
    await act(async () => void vi.advanceTimersByTime(56 * MIN));
    expect(screen.getByRole('alertdialog')).toBeInTheDocument();
    const before = callsTo('GET', '/auth/me').length;

    await user.click(screen.getByRole('button', { name: 'Men shu yerdaman' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).toBeNull());
    expect(callsTo('GET', '/auth/me')).toHaveLength(before + 1);

    // The idle clock restarted: no warning five minutes later
    await act(async () => void vi.advanceTimersByTime(5 * MIN));
    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(screen.getByText('ready')).toBeInTheDocument();
  });
});
