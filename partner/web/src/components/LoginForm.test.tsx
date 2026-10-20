import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { LoginForm } from './LoginForm';
import { getSession, setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';

const replace = vi.fn();
const start = vi.fn();

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace, refresh: vi.fn() }) }));
vi.mock('./AuthProvider', () => ({ useAuth: () => ({ start }) }));

afterEach(() => {
  setSession(null);
  vi.useRealTimers();
});

const user = { id: 7, name: 'Aziz', role: 'owner' };

describe('LoginForm', () => {
  it('asks for the phone, then the SMS code, then signs in', async () => {
    const { calls } = mockFetch({
      'POST /api/session/send-code': () => json({ ok: true }),
      'POST /api/session/login': () => json({ access_token: 'access-1', expires_at: new Date(Date.now() + 900_000).toISOString(), user }),
    });
    const typing = userEvent.setup();
    renderPage(<LoginForm />);

    await typing.type(screen.getByLabelText('Telefon raqami'), '901234567');
    expect(screen.getByLabelText('Telefon raqami')).toHaveValue('90 123 45 67');
    await typing.click(screen.getByRole('button', { name: 'Kod yuborish' }));

    expect(await screen.findByText('Kod +998901234567 raqamiga yuborildi.')).toBeInTheDocument();
    expect(await calls[0]?.json()).toEqual({ phone: '+998901234567' });

    await typing.type(screen.getByLabelText('SMS kod'), '123456');
    await typing.click(screen.getByRole('button', { name: 'Kirish' }));

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/'));
    expect(await calls[1]?.json()).toEqual({ phone: '+998901234567', code: '123456' });
    expect(getSession()?.accessToken).toBe('access-1');
    expect(start).toHaveBeenCalled();
  });

  it('rejects a short phone number without calling the server', async () => {
    const { fetchMock } = mockFetch({});
    const typing = userEvent.setup();
    renderPage(<LoginForm />);
    await typing.type(screen.getByLabelText('Telefon raqami'), '9012');
    await typing.click(screen.getByRole('button', { name: 'Kod yuborish' }));
    expect(await screen.findByRole('alert')).toHaveTextContent("Raqamni to'g'ri kiriting");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('shows the backend\'s reason for a wrong code and stays on the code step', async () => {
    mockFetch({
      'POST /api/session/send-code': () => json({ ok: true }),
      'POST /api/session/login': () => json({ message: "Kod noto'g'ri yoki muddati o'tgan." }, 422),
    });
    const typing = userEvent.setup();
    renderPage(<LoginForm />);
    await typing.type(screen.getByLabelText('Telefon raqami'), '901234567');
    await typing.click(screen.getByRole('button', { name: 'Kod yuborish' }));
    await typing.type(await screen.findByLabelText('SMS kod'), '000000');
    await typing.click(screen.getByRole('button', { name: 'Kirish' }));

    expect(await screen.findByRole('alert')).toHaveTextContent("Kod noto'g'ri yoki muddati o'tgan.");
    expect(replace).not.toHaveBeenCalled();
    expect(getSession()).toBeNull();
  });

  it('speaks Russian when the page is in Russian, and allows a resend only after the minute', async () => {
    mockFetch({ 'POST /api/session/send-code': () => json({ ok: true }) });
    const typing = userEvent.setup();
    renderPage(<LoginForm />, 'ru');
    await typing.type(screen.getByLabelText('Номер телефона'), '901234567');
    await typing.click(screen.getByRole('button', { name: 'Отправить код' }));
    expect(await screen.findByRole('button', { name: /Повторная отправка через 60 с/ })).toBeDisabled();
  });
});
