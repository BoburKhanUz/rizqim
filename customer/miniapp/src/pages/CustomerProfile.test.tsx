import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { openExternalLink } from '../lib/openMap';
import { makeStore, makeUser } from '../test/factories';
import CustomerProfile from './CustomerProfile';

// The profile refreshes the signed-in user from GET /user: the cashback balance must stay a number in the store

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: {} }));
vi.mock('../lib/openMap', () => ({ openExternalLink: vi.fn() }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

beforeEach(() => {
  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  get.mockImplementation((url) => {
    if (url === '/user') {
      return Promise.resolve({
        data: { success: true, data: { user: makeUser({ balance: '125000.00', name: 'Yangi Ism' }), store: null, sms_verification_required: false } },
      });
    }
    return Promise.resolve({ data: { success: true, data: url === '/locations' ? { regions: [], districts: [] } : [] } });
  });
});

describe('CustomerProfile: refreshing the user', () => {
  it('keeps the balance a number, not the decimal string the API sent', async () => {
    render(
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <CustomerProfile />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    await flush();

    const { user } = useAuthStore.getState();
    expect(user?.name).toBe('Yangi Ism');
    expect(user?.balance).toBe(125000);
    expect(typeof user?.balance).toBe('number');
  });
});

// Review finding 1: a referral bonus taken back after the referrer spent it leaves the balance below zero
describe('CustomerProfile: a negative keshbek balance', () => {
  it('shows it as a debt the next bonus pays off, never as spendable money', async () => {
    get.mockImplementation((url) => {
      if (url === '/user') {
        return Promise.resolve({
          data: { success: true, data: { user: makeUser({ balance: '-5000.00' }), store: null, sms_verification_required: false } },
        });
      }
      return Promise.resolve({ data: { success: true, data: url === '/locations' ? { regions: [], districts: [] } : [] } });
    });
    render(
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <CustomerProfile />
        </MemoryRouter>
      </QueryClientProvider>,
    );
    await flush();

    expect(useAuthStore.getState().user?.balance).toBe(-5000);
    const amount = screen.getByTestId('profile-cashback');
    expect(amount.textContent).toMatch(/^−\s?5\s?000/);
    expect(amount.className).toContain('text-danger');
    expect(screen.getByText(/keyingi bonusdan yopiladi/)).toBeTruthy();
    // Review 6: the invite names the minimum order
    expect(document.body.textContent).toMatch(/kamida 50\s000/);
  });
});

// The legal documents stay one tap away on the profile (the first-launch notice is shown only once)
describe('CustomerProfile: documents', () => {
  const renderProfile = () =>
    render(
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <CustomerProfile />
        </MemoryRouter>
      </QueryClientProvider>,
    );

  it('links the Terms of Use and the Privacy Policy, opened through Telegram', async () => {
    useLanguageStore.setState({ language: 'uz' });
    renderProfile();
    await flush();

    const documents = screen.getByRole('region', { name: 'Hujjatlar' });
    const terms = within(documents).getByRole('link', { name: 'Foydalanish shartlari' });
    const privacy = within(documents).getByRole('link', { name: 'Maxfiylik siyosati' });
    expect(terms).toHaveAttribute('href', 'https://rizqim.uz/shartlar');
    expect(privacy).toHaveAttribute('href', 'https://rizqim.uz/maxfiylik');

    fireEvent.click(privacy);
    expect(openExternalLink).toHaveBeenCalledWith('https://rizqim.uz/maxfiylik');
  });

  it.each([
    ['ru', 'Документы', 'Условия использования', 'Политика конфиденциальности'],
    ['en', 'Documents', 'Terms of Use', 'Privacy Policy'],
  ] as const)('names them in %s', async (language, title, terms, privacy) => {
    useLanguageStore.setState({ language });
    renderProfile();
    await flush();

    const documents = screen.getByRole('region', { name: title });
    expect(within(documents).getByRole('link', { name: terms })).toHaveAttribute('href', 'https://rizqim.uz/shartlar');
    expect(within(documents).getByRole('link', { name: privacy })).toHaveAttribute('href', 'https://rizqim.uz/maxfiylik');
    useLanguageStore.setState({ language: 'uz' });
  });
});
