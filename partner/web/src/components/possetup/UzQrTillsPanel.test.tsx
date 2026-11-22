import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const format = {
  branch_id: 1,
  profile: 'kafe',
  profile_label: 'Kafe',
  mode: 'hybrid',
  first_screen: 'quick_counter',
  modules: ['qr_payments'],
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { profile: 'kafe', stations: [], dishes: [] },
};

const qrView = (driver: string) => ({
  settings: { branch_id: 1, enabled: true, providers: ['click', 'payme', 'uzqr'], default_provider: 'click', expiry_minutes: 5, updated_at: null },
  merchants: [
    { provider: 'click', label: 'Click', configured: true, driver: 'click' },
    { provider: 'payme', label: 'Payme', configured: false, driver: 'payme' },
    { provider: 'uzqr', label: 'UzQR', configured: true, driver },
  ],
  options: { providers: [{ key: 'click', label: 'Click' }, { key: 'payme', label: 'Payme' }, { key: 'uzqr', label: 'UzQR' }], expiry_min: 1, expiry_max: 30 },
});

const UUID = '0d7f0c2e-6f4a-4f4e-9d5e-3a6b0d5d1a11';
const till = (over: Record<string, unknown> = {}) => ({ uuid: UUID, name: 'Kassa 1', branch_id: 1, has_api_key: false, api_key_last4: null, api_key_set_at: null, ready: false, message: 'Bu kassaga UzQR API kaliti kiritilmagan.', ...over });

const open = async (driver: string) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(format),
    'GET /api/v1/owner/pos/qr-payments': () => ok(qrView(driver)),
    'GET /api/v1/owner/fiscal/uzqr': () => ok({ driver: 'uzqr_operator', tin: '301234567', tin_set: true, tills: [till()] }),
    [`PUT /api/v1/owner/fiscal/uzqr/tills/${UUID}`]: async (request: Request) => {
      const body = (await request.clone().json()) as { remove_api_key?: boolean };
      return ok(body.remove_api_key ? till() : till({ has_api_key: true, api_key_last4: 'a1b2', ready: true, message: null }));
    },
  });
  renderPage(<PosSetupView />);
  const typing = userEvent.setup();
  await typing.click(await screen.findByRole('tab', { name: "QR to'lov" }));
  return { ...mocked, typing };
};

describe('the UzQR tills (Wave P)', () => {
  it('is shown only with the real UzQR driver', async () => {
    await open('uzqr:sandbox');
    await screen.findByRole('checkbox', { name: /^Click/ });
    expect(screen.queryByRole('heading', { name: 'UzQR kassalari' })).not.toBeInTheDocument();
  });

  it("sets a till's key without ever showing it and removes it", async () => {
    const { typing, calls } = await open('uzqr:uzqr_operator');
    const panel = (await screen.findByRole('heading', { name: 'UzQR kassalari' })).closest('section') as HTMLElement;
    expect(within(panel).getByText(/301234567/)).toBeInTheDocument();
    expect(within(panel).getByText('Bu kassaga UzQR API kaliti kiritilmagan.')).toBeInTheDocument();

    const key = `k${Math.random().toString(36).slice(2)}${Date.now()}`;
    const field = within(panel).getByLabelText('UzQR API kaliti');
    expect(field).toHaveAttribute('type', 'password');
    await typing.type(field, key);
    await typing.click(within(panel).getByRole('button', { name: 'Kalitni saqlash' }));

    expect(await within(panel).findByText('Kalit kiritilgan: ••••a1b2')).toBeInTheDocument();
    const put = calls.filter((call) => call.method === 'PUT');
    expect(await put.at(-1)!.json()).toEqual({ api_key: key });
    expect(within(panel).getByLabelText('UzQR API kaliti')).toHaveValue('');
    expect(document.body.textContent).not.toContain(key);

    await typing.click(within(panel).getByRole('button', { name: "Kalitni o'chirish" }));
    expect(await within(panel).findByText("Kalit yo'q")).toBeInTheDocument();
    expect(await calls.filter((call) => call.method === 'PUT').at(-1)!.json()).toEqual({ remove_api_key: true });
  });
});
