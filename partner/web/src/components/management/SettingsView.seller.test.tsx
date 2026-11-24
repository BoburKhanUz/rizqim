import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { makeBranch, ok, permissions, refusal, signIn } from './testSupport';
import { SettingsView } from './SettingsView';

// Wave P (receipts): the seller's details customers see before ordering (consumer law art. 28-1). The owner fills the
// legal address and the complaint contact; the legal name and the STIR are shown as RIZQIM and the fiscal settings hold them.

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const SETTINGS = {
  id: 1,
  name: 'Osh markazi',
  timezone: 'Asia/Tashkent',
  logo: null,
  logo_url: null,
  banner: null,
  banner_url: null,
  description: null,
  phone: null,
  address: 'Amir Temur 1',
  location_url: null,
  is_open: true,
  min_order_amount: 0,
  payment_settings: { cash: { enabled: true } },
  payment_account_type: 'platform_account',
  status: 'active',
  is_order_locked: false,
  allowed_methods: ['cash'],
  tin: '305123456',
  tax_regime: 'turnover',
  legal_name: '"Osh markazi" MChJ',
  legal_address: 'Toshkent sh., Mirobod t., 7',
  complaint_contact: null,
  telegram: { group_link: null, group_linked: false },
};

const base = (over: Parameters<typeof mockFetch>[0] = {}) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissions(),
    'GET /api/v1/owner/settings': () => ok(SETTINGS),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    ...over,
  });

describe('SettingsView: the seller details', () => {
  it('shows the legal name and STIR as stored and saves the legal address and complaint contact', async () => {
    const { calls } = base({ 'POST /api/v1/owner/settings': () => ok(null) });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);

    const section = await screen.findByRole('region', { name: "Sotuvchi ma'lumotlari" });
    expect(within(section).getByText('"Osh markazi" MChJ')).toBeInTheDocument();
    expect(within(section).getByText('305123456')).toBeInTheDocument();
    expect(within(section).getByLabelText('Yuridik manzil')).toHaveValue('Toshkent sh., Mirobod t., 7');
    expect(within(section).getByLabelText('Shikoyat uchun aloqa')).toHaveValue('');

    await typing.type(within(section).getByLabelText('Shikoyat uchun aloqa'), '@osh_markazi');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Sozlamalar saqlandi.');
    const body = await calls.find((call) => call.method === 'POST')!.json();
    expect(body.legal_address).toBe('Toshkent sh., Mirobod t., 7');
    expect(body.complaint_contact).toBe('@osh_markazi');
    expect(body).not.toHaveProperty('legal_name');
  });

  it('sends an emptied field as null and shows the server refusal under it', async () => {
    const { calls } = base({
      'POST /api/v1/owner/settings': () => refusal('Xato', 422, { errors: { legal_address: ["Yuridik manzil ko'pi bilan 500 belgi bo'lishi mumkin."] } }),
    });
    const typing = userEvent.setup();
    renderPage(<SettingsView />);

    const field = await screen.findByLabelText('Yuridik manzil');
    await typing.clear(field);
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText("Yuridik manzil ko'pi bilan 500 belgi bo'lishi mumkin.")).toBeInTheDocument();
    const body = await calls.find((call) => call.method === 'POST')!.json();
    expect(body.legal_address).toBeNull();
  });

  it('says where the legal name comes from when RIZQIM has not set it', async () => {
    base({ 'GET /api/v1/owner/settings': () => ok({ ...SETTINGS, legal_name: null, tin: null }) });
    renderPage(<SettingsView />);

    const section = await screen.findByRole('region', { name: "Sotuvchi ma'lumotlari" });
    expect(within(section).getAllByText('Kiritilmagan')).toHaveLength(2);
  });
});
