import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { deviceName, SessionsView } from './SessionsView';

const SESSIONS = [
  { id: 'a1', current: true, ip: '10.0.0.5', user_agent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0 Safari/537.36', created_at: '2026-10-27T08:00:00+05:00', last_used_at: '2026-10-27T10:00:00+05:00', expires_at: '2026-10-27T20:00:00+05:00' },
  { id: 'b2', current: false, ip: '10.0.0.9', user_agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', created_at: '2026-10-26T08:00:00+05:00', last_used_at: '2026-10-26T09:00:00+05:00', expires_at: '2026-10-26T20:00:00+05:00' },
];

function setup(extra: Parameters<typeof mockFetch>[0] = {}) {
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody()), 'GET /auth/sessions': () => ok(SESSIONS), ...extra });
  renderPage(<SessionsView />);
  return { ...mocks, user: userEvent.setup() };
}

describe('Sessiyalarim', () => {
  it('lists the sessions, marks this device and offers to end the others', async () => {
    setup();
    const list = await screen.findByRole('list', { name: 'Sessiyalarim' });
    const [mine, other] = within(list).getAllByRole('listitem');
    expect(mine).toHaveTextContent('Chrome, Linux');
    expect(within(mine!).getByText('Shu qurilma')).toBeInTheDocument();
    expect(within(mine!).queryByRole('button')).toBeNull();
    expect(other).toHaveTextContent('Safari, iOS');
    expect(within(other!).getByRole('button', { name: /Sessiyani tugatish/ })).toBeInTheDocument();
  });

  it('ends another session after a question', async () => {
    const { callsTo, user } = setup({ 'DELETE /auth/sessions/b2': () => ok([]) });
    await user.click(await screen.findByRole('button', { name: /Sessiyani tugatish: Safari, iOS/ }));
    const ask = await screen.findByRole('alertdialog', { name: 'Sessiyani tugatasizmi?' });
    await user.click(within(ask).getByRole('button', { name: 'Ha, tugatish' }));
    expect(await screen.findByText('Sessiya tugatildi.')).toBeInTheDocument();
    expect(callsTo('DELETE', '/auth/sessions/b2')).toHaveLength(1);
  });

  it('shows the security card and makes new recovery codes (shown once, with copy and download)', async () => {
    const { callsTo, user } = setup({ 'POST /auth/2fa/recovery-codes': () => ok({ recovery_codes: ['nnnn-1111', 'nnnn-2222'] }) });
    expect(await screen.findByText('Yoqilgan')).toBeInTheDocument();
    expect(screen.getByText('Tiklash kodlari qoldi').nextSibling).toHaveTextContent('8');

    await user.click(screen.getByRole('button', { name: 'Yangi tiklash kodlarini yaratish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Yangi tiklash kodlari' });
    expect(within(within(dialog).getByRole('list', { name: 'Tiklash kodlari' })).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['nnnn-1111', 'nnnn-2222']);
    expect(within(dialog).getByRole('button', { name: 'Yuklab olish (.txt)' })).toBeInTheDocument();
    expect(callsTo('POST', '/auth/2fa/recovery-codes')).toHaveLength(1);
  });

  it('says it when the sessions cannot be loaded', async () => {
    setup({ 'GET /auth/sessions': () => refusal(500, 'Server xatosi') });
    expect(await screen.findByText("Sessiyalar ro'yxati yuklanmadi")).toBeInTheDocument();
  });

  it('reads a User-Agent into a short device name', () => {
    expect(deviceName('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Edg/120', 'x')).toBe('Edge, Windows');
    expect(deviceName('Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile', 'x')).toBe('Chrome, Android');
    expect(deviceName(null, "Noma'lum")).toBe("Noma'lum");
    expect(deviceName('curl/8.0', 'x')).toBe('curl/8.0');
  });
});
