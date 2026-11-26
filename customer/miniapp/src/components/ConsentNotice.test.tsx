import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/authStore';
import { useConsentStore } from '../store/consentStore';
import { useLanguageStore } from '../store/languageStore';
import { USER_STORE_NAMES, createUserStateStorage, userStorageKey } from '../lib/userStorage';
import { openExternalLink } from '../lib/openMap';
import { makeStore, makeUser } from '../test/factories';
import { TEST_TELEGRAM_ID } from '../test/telegramId';
import type { ApiUser } from '../types';
import { ConsentNotice } from './ConsentNotice';

vi.mock('../lib/openMap', () => ({ openExternalLink: vi.fn() }));

const signIn = (overrides: Partial<ApiUser> = {}) =>
  useAuthStore.getState().setAuth('token', makeUser({ phone: '+998901234567', ...overrides }), makeStore(), false);

beforeEach(() => {
  useConsentStore.setState({ noticeSeen: false });
  useLanguageStore.setState({ language: 'uz' });
  signIn();
});

describe('ConsentNotice: the first-launch "using the app = consent" line', () => {
  it('tells a new customer that using the app accepts both documents, with links to the site', () => {
    render(<ConsentNotice />);

    const notice = screen.getByTestId('consent-notice');
    expect(notice).toHaveTextContent('Ilovadan foydalanib, siz Foydalanish shartlari va Maxfiylik siyosatiga rozilik bildirasiz.');
    expect(screen.getByRole('link', { name: 'Foydalanish shartlari' })).toHaveAttribute('href', 'https://rizqim.uz/shartlar');
    expect(screen.getByRole('link', { name: 'Maxfiylik siyosati' })).toHaveAttribute('href', 'https://rizqim.uz/maxfiylik');
  });

  it('opens the documents through Telegram (openLink), not inside the Mini App', () => {
    render(<ConsentNotice />);

    fireEvent.click(screen.getByRole('link', { name: 'Maxfiylik siyosati' }));
    expect(openExternalLink).toHaveBeenCalledWith('https://rizqim.uz/maxfiylik');
    fireEvent.click(screen.getByRole('link', { name: 'Foydalanish shartlari' }));
    expect(openExternalLink).toHaveBeenCalledWith('https://rizqim.uz/shartlar');
  });

  it('is shown once: remembered as seen for this Telegram user, so the next launch has no line', () => {
    const { unmount } = render(<ConsentNotice />);
    expect(screen.getByTestId('consent-notice')).toBeInTheDocument();
    // Stays while this screen is open
    act(() => useAuthStore.getState().updateUser({ name: 'Boshqa Ism' }));
    expect(screen.getByTestId('consent-notice')).toBeInTheDocument();

    expect(useConsentStore.getState().noticeSeen).toBe(true);
    const saved = localStorage.getItem(userStorageKey('consent-storage', TEST_TELEGRAM_ID));
    expect(JSON.parse(saved ?? 'null')?.state?.noticeSeen).toBe(true);
    expect(localStorage.getItem('consent-storage')).toBeNull(); // never a device-wide key

    unmount();
    render(<ConsentNotice />);
    expect(screen.queryByTestId('consent-notice')).toBeNull();
  });

  it('can be closed', () => {
    render(<ConsentNotice />);
    fireEvent.click(screen.getByRole('button', { name: 'Yopish' }));
    expect(screen.queryByTestId('consent-notice')).toBeNull();
  });

  it('speaks the language the customer picked', () => {
    useLanguageStore.setState({ language: 'ru' });
    const { unmount } = render(<ConsentNotice />);
    expect(screen.getByTestId('consent-notice')).toHaveTextContent(
      'Пользуясь приложением, вы соглашаетесь с Условиями использования и Политикой конфиденциальности.',
    );
    unmount();

    useConsentStore.setState({ noticeSeen: false });
    useLanguageStore.setState({ language: 'en' });
    render(<ConsentNotice />);
    expect(screen.getByTestId('consent-notice')).toHaveTextContent(
      'By using the app, you agree to the Terms of Use and the Privacy Policy.',
    );
    expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', 'https://rizqim.uz/maxfiylik');
  });

  it.each(['owner', 'manager', 'courier', 'super_admin'])('is not shown to staff (%s), nor marked seen for them', (role) => {
    signIn({ role } as Partial<ApiUser>);
    render(<ConsentNotice />);
    expect(screen.queryByTestId('consent-notice')).toBeNull();
    expect(useConsentStore.getState().noticeSeen).toBe(false);
  });

  it('waits while the phone prompt covers the screen (no phone yet), and nobody signed in sees nothing', () => {
    signIn({ phone: null });
    const { rerender } = render(<ConsentNotice />);
    expect(screen.queryByTestId('consent-notice')).toBeNull();
    expect(useConsentStore.getState().noticeSeen).toBe(false);

    act(() => useAuthStore.getState().updateUser({ phone: '+998901234567' }));
    rerender(<ConsentNotice />);
    expect(screen.getByTestId('consent-notice')).toBeInTheDocument();

    act(() => useAuthStore.getState().logout());
    rerender(<ConsentNotice />);
    expect(screen.queryByTestId('consent-notice')).toBeNull();
  });
});

describe('consent storage', () => {
  it('is kept per Telegram user: another account on the device has not seen the notice', () => {
    const device = window.localStorage;
    createUserStateStorage('111', device).setItem('consent-storage', JSON.stringify({ state: { noticeSeen: true }, version: 0 }));
    expect(createUserStateStorage('222', device).getItem('consent-storage')).toBeNull();
    expect(device.getItem(userStorageKey('consent-storage', '111'))).not.toBeNull();
  });

  it('is one of the per-user stores, so it goes with the account when an unused account is pruned', () => {
    expect(USER_STORE_NAMES).toContain('consent-storage');
  });
});
