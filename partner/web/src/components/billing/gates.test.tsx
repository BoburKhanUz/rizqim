import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { AccountsView } from '../accounts/AccountsView';
import { BookingsView } from '../bookings/BookingsView';
import { PairingCodeDialog } from '../management/PairingCodeDialog';
import { ServiceFeesForm } from '../management/ServiceFeesForm';
import { makeBranch, signIn } from '../management/testSupport';
import { account, booking, calendar, ok as okPos, ownerAnswer } from '../pos/testSupport';
import { asosiyPlan, ok, refusalBody, startPlan } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const entitlements = (data: unknown) => ({ 'GET /api/v1/owner/billing/entitlements': () => ok(data) });

const accountRoutes = () => ({
  'GET /api/v1/me/permissions': () => ownerAnswer(),
  'GET /api/v1/pos/customer-accounts/debtors': () => okPos({ total_debt: '0.00', count: 0, accounts: [] }),
  'GET /api/v1/pos/customer-accounts': () => okPos({ accounts: [account()], meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 } }),
});

describe('nasiya on the Start plan', () => {
  it('locks the new-account button with "Asosiy tarifda bor" and leaves the accounts that exist open', async () => {
    mockFetch({ ...accountRoutes(), ...entitlements(startPlan()) });
    renderPage(<AccountsView />);
    expect(await screen.findByText('Nasiya — Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Hisob ochish' })).toBeDisabled();
    expect(await screen.findByText('Karim Rahimov')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Karim Rahimov: tahrirlash' })).toBeEnabled();
  });

  it('opens the form on the Asosiy plan', async () => {
    const typing = userEvent.setup();
    mockFetch({ ...accountRoutes(), ...entitlements(asosiyPlan()) });
    renderPage(<AccountsView />);
    await screen.findByText('Karim Rahimov');
    await waitFor(() => expect(screen.queryByText(/tarifda bor/)).not.toBeInTheDocument());
    await typing.click(screen.getByRole('button', { name: 'Hisob ochish' }));
    expect(await screen.findByLabelText('Ismi yoki guruh nomi')).toBeInTheDocument();
  });

  it('shows the same plain words when the server says no to a stale screen', async () => {
    const typing = userEvent.setup();
    mockFetch({
      ...accountRoutes(),
      // The screen has not heard about the plan change yet: the entitlements still say yes
      ...entitlements(asosiyPlan()),
      'POST /api/v1/pos/customer-accounts': () =>
        refusalBody('plan_feature_missing', { feature: 'customer_accounts', required_plan: 'asosiy', required_plan_name: 'Asosiy' }, 'Bu imkoniyat (Nasiya) Asosiy tarifida bor.'),
    });
    renderPage(<AccountsView />);
    await typing.click(await screen.findByRole('button', { name: 'Hisob ochish' }));
    await typing.type(screen.getByLabelText('Ismi yoki guruh nomi'), 'Yangi');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Nasiya Asosiy tarifda bor. Hozirgi tarifingizda yo'q.")).toBeInTheDocument();
  });
});

describe('bookings on the Start plan', () => {
  const routes = () => ({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => okPos([makeBranch()]),
    'GET /api/v1/pos/bookings/calendar': () => okPos(calendar({ bookings: [booking()] })),
  });

  it('locks "Bron qo\'shish" and keeps the calendar and its bookings', async () => {
    mockFetch({ ...routes(), ...entitlements(startPlan()) });
    renderPage(<BookingsView />);
    const date = await screen.findByLabelText('Sana');
    fireEvent.change(date, { target: { value: '2026-10-22' } });
    expect(await screen.findByText('Bron — Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: "Bron qo'shish" })).toBeDisabled();
    expect(await screen.findByRole('group', { name: 'Xona 1' })).toBeInTheDocument();
  });

  it("opens the booking form on the Asosiy plan", async () => {
    const typing = userEvent.setup();
    mockFetch({ ...routes(), ...entitlements(asosiyPlan()) });
    renderPage(<BookingsView />);
    await screen.findByRole('group', { name: 'Xona 1' });
    await waitFor(() => expect(screen.getByRole('button', { name: "Bron qo'shish" })).toBeEnabled());
    await typing.click(screen.getByRole('button', { name: "Bron qo'shish" }));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
  });
});

describe('service charge on the Start plan', () => {
  const feeRoutes = (save: () => Response) => ({
    'GET /api/v1/owner/branches': () => okPos([makeBranch()]),
    'GET /api/v1/owner/service-fees': () =>
      okPos({ settings: [{ id: 1, branch_id: null, hall_id: null, service_charge_percent: '0.00', service_charge_channels: ['pos_hall'], place_fee_amount: '0.00', place_fee_mode: 'per_person', place_fee_channels: ['pos_hall'] }], channels: ['pos_hall', 'telegram'] }),
    'PUT /api/v1/owner/service-fees': save,
  });

  it('disables the percent, explains why, and still saves the place fee', async () => {
    const typing = userEvent.setup();
    const { calls } = mockFetch({ ...feeRoutes(() => okPos({ id: 1 })), ...entitlements(startPlan()) });
    renderPage(<ServiceFeesForm />);
    expect(await screen.findByText("Xizmat haqi Asosiy tarifda bor. Hozir faqat joy puli sozlanadi.")).toBeInTheDocument();
    expect(screen.getByLabelText('Foiz')).toBeDisabled();
    await typing.clear(screen.getByLabelText('Miqdori'));
    await typing.type(screen.getByLabelText('Miqdori'), '5000');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await screen.findByText('Xizmat haqi saqlandi.');
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toMatchObject({ service_charge_percent: 0, place_fee_amount: 5000 });
  });

  it('leaves the percent open on the Asosiy plan', async () => {
    mockFetch({ ...feeRoutes(() => okPos({ id: 1 })), ...entitlements(asosiyPlan()) });
    renderPage(<ServiceFeesForm />);
    await screen.findByLabelText('Foiz');
    await waitFor(() => expect(screen.getByLabelText('Foiz')).toBeEnabled());
  });
});

describe('pairing a kitchen display', () => {
  const routes = (data: unknown) => ({ 'GET /api/v1/owner/branches': () => okPos([makeBranch()]), ...entitlements(data) });

  it('explains that the KDS belongs to a higher plan and does not offer a code', async () => {
    const typing = userEvent.setup();
    mockFetch(routes(startPlan()));
    renderPage(<PairingCodeDialog onClose={() => undefined} />);
    const dialog = within(await screen.findByRole('dialog'));
    expect(dialog.getByRole('button', { name: 'Kod yaratish' })).toBeEnabled();
    await typing.selectOptions(dialog.getByLabelText('Turi'), 'kds');
    expect(await dialog.findByText('Oshxona ekrani (KDS) Asosiy tarifda bor.')).toBeInTheDocument();
    expect(dialog.getByRole('button', { name: 'Kod yaratish' })).toBeDisabled();
  });

  it('explains the device limit of a locked subscription for a till', async () => {
    mockFetch(routes(startPlan({ status: 'locked', settings_locked: true, devices: { ...startPlan().devices, pos: { included: 1, active: 1, extra: 0, extra_price: '59000.00', can_add: false } } })));
    renderPage(<PairingCodeDialog onClose={() => undefined} />);
    const dialog = within(await screen.findByRole('dialog'));
    expect(await dialog.findByText(/tarifdagidan ortiq qurilma ulab bo'lmaydi/)).toBeInTheDocument();
    expect(dialog.getByRole('button', { name: 'Kod yaratish' })).toBeDisabled();
  });

  it('pairs a kitchen display on the Asosiy plan', async () => {
    const typing = userEvent.setup();
    mockFetch(routes(asosiyPlan()));
    renderPage(<PairingCodeDialog onClose={() => undefined} />);
    const dialog = within(await screen.findByRole('dialog'));
    await typing.selectOptions(dialog.getByLabelText('Turi'), 'kds');
    await waitFor(() => expect(dialog.getByRole('button', { name: 'Kod yaratish' })).toBeEnabled());
    expect(dialog.queryByText(/tarifda bor/)).not.toBeInTheDocument();
  });

  it('shows the device_limit refusal of the server in plain words', async () => {
    const typing = userEvent.setup();
    mockFetch({
      ...routes(asosiyPlan()),
      'POST /api/v1/owner/devices/pairing-codes': () => refusalBody('device_limit', { device_type: 'pos', included: 1, active: 1 }, "Server matni"),
    });
    renderPage(<PairingCodeDialog onClose={() => undefined} />);
    const dialog = within(await screen.findByRole('dialog'));
    await typing.click(dialog.getByRole('button', { name: 'Kod yaratish' }));
    expect(await dialog.findByText(/Eskisini o'chirib, o'rniga yangisini ulashingiz mumkin/)).toBeInTheDocument();
  });
});
