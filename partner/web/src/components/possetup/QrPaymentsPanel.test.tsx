import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const formatOf = (modules: string[]) => ({
  branch_id: 1,
  profile: 'kafe',
  profile_label: 'Kafe',
  mode: 'hybrid',
  first_screen: 'quick_counter',
  modules,
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { profile: 'kafe', stations: [], dishes: [] },
});

const VIEW = {
  settings: { branch_id: 1, enabled: true, providers: ['click', 'payme', 'uzqr'], default_provider: 'click', expiry_minutes: 5, updated_at: null },
  merchants: [
    { provider: 'click', label: 'Click', configured: true, driver: 'click' },
    { provider: 'payme', label: 'Payme', configured: false, driver: 'payme' },
    { provider: 'uzqr', label: 'UzQR', configured: false, driver: 'uzqr:none' },
  ],
  options: { providers: [{ key: 'click', label: 'Click' }, { key: 'payme', label: 'Payme' }, { key: 'uzqr', label: 'UzQR' }], expiry_min: 1, expiry_max: 30 },
};

const open = async (modules: string[], over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(formatOf(modules)),
    'GET /api/v1/owner/pos/qr-payments': () => ok(VIEW),
    ...over,
  });
  renderPage(<PosSetupView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe("the QR to'lov tab", () => {
  it('is not on the page without the qr_payments module', async () => {
    await open(['quick_counter']);
    await screen.findByRole('tab', { name: 'Format' });
    await waitFor(() => expect(screen.queryByRole('tab', { name: "QR to'lov" })).not.toBeInTheDocument());
  });

  it('shows the providers with their merchant status and the defaults', async () => {
    const { typing } = await open(['qr_payments']);
    await typing.click(await screen.findByRole('tab', { name: "QR to'lov" }));

    expect(await screen.findByRole('checkbox', { name: /^Click/ })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /^Payme/ })).toBeChecked();
    expect(screen.getAllByText('Ulangan')).toHaveLength(1);
    expect(screen.getAllByText('Ulanmagan')).toHaveLength(2);
    expect(screen.getByRole('combobox', { name: 'Birinchi taklif qilinadigan' })).toHaveValue('click');
    expect(screen.getByRole('textbox', { name: 'QR amal qilish vaqti' })).toHaveValue('5');
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
  });

  it('saves only what changed and moves the default off a provider turned off', async () => {
    const { typing, calls } = await open(['qr_payments'], {
      'PUT /api/v1/owner/pos/qr-payments': () => ok({ ...VIEW, settings: { ...VIEW.settings, providers: ['payme', 'uzqr'], default_provider: 'payme', expiry_minutes: 10 } }),
    });
    await typing.click(await screen.findByRole('tab', { name: "QR to'lov" }));
    await typing.click(await screen.findByRole('checkbox', { name: /^Click/ }));
    expect(screen.getByRole('combobox', { name: 'Birinchi taklif qilinadigan' })).toHaveValue('payme');
    const expiry = screen.getByRole('textbox', { name: 'QR amal qilish vaqti' });
    await typing.clear(expiry);
    await typing.type(expiry, '10');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText("QR to'lov sozlamalari saqlandi.")).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, providers: ['payme', 'uzqr'], default_provider: 'payme', expiry_minutes: 10 });
  });

  it('refuses an expiry out of range before sending and shows the server refusal', async () => {
    const { typing, calls } = await open(['qr_payments'], {
      'PUT /api/v1/owner/pos/qr-payments': () => json({ success: false, message: "Modul o'chiq.", data: { reason: 'module_disabled' } }, 403),
    });
    await typing.click(await screen.findByRole('tab', { name: "QR to'lov" }));
    const expiry = await screen.findByRole('textbox', { name: 'QR amal qilish vaqti' });
    await typing.clear(expiry);
    await typing.type(expiry, '45');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('1 dan 30 gacha butun son kiriting.')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(0);

    await typing.clear(expiry);
    await typing.type(expiry, '7');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Modul o'chiq.")).toBeInTheDocument();
  });
});
