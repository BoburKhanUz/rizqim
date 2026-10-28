import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { permissions, signIn } from '../management/testSupport';
import { subscription as oldSubscription } from '../reports/fixtures';
import { BillingView } from './BillingView';
import { asosiyPlan, locked, ok, reminder, startPlan } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), usePathname: () => '/billing' }));

beforeEach(signIn);
afterEach(() => setSession(null));

const subscription = (over: Record<string, unknown> = {}) => ({
  ...oldSubscription,
  subscription: {
    ...oldSubscription.subscription,
    plan: { ...oldSubscription.subscription.plan, code: 'start', name: 'Start', monthly_base_fee: 149000 },
    billing_period: 'monthly',
    next_billing_period: null,
    term_ends_at: null,
    ...over,
  },
});

const invoice = (over: Record<string, unknown>) => ({
  uuid: 'i-1',
  number: 'OB-202610-000042',
  period: '2026-10',
  period_start: '2026-10-01T00:00:00+05:00',
  period_end: '2026-10-31T00:00:00+05:00',
  plan: { code: 'start', name: 'Start' },
  terminals_count: 1,
  included_terminals: 1,
  base_fee: 149000,
  fee_per_terminal: 0,
  lines: [],
  amount: 149000,
  kind: 'monthly',
  paid_amount: '0.00',
  status: 'issued',
  due_date: '2026-11-10',
  issued_at: '2026-11-01T00:15:00+05:00',
  paid_at: null,
  voided_at: null,
  ...over,
});

const page = (rows: unknown[]) => ({ current_page: 1, data: rows, per_page: 15, total: rows.length, last_page: 1 });

const routes = (entitlements: unknown, rows: unknown[] = [], sub: unknown = subscription()) => ({
  'GET /api/v1/me/permissions': () => permissions(['finance.view']),
  'GET /api/v1/owner/billing/entitlements': () => ok(entitlements),
  'GET /api/v1/owner/subscription': () => ok(sub),
  'GET /api/v1/owner/invoices': () => ok(page(rows)),
});

describe('Tarif va to\'lov', () => {
  it('shows the plan, the trial days left, the devices with the price of an extra one and the locked features', async () => {
    const trialEnds = new Date(Date.now() + 5.5 * 86_400_000).toISOString();
    mockFetch(routes(startPlan({ status: 'trialing', trial_ends_at: trialEnds }), [], subscription({ status: 'trialing' })));
    renderPage(<BillingView />);

    const plan = (await screen.findByRole('heading', { name: 'Joriy tarif' })).closest('div')!.parentElement!;
    expect(await within(plan).findByText('Start')).toBeInTheDocument();
    expect(within(plan).getByText('Sinov davri')).toBeInTheDocument();
    expect(within(plan).getByText('Sinov davri: 6 kun qoldi')).toBeInTheDocument();
    expect(within(plan).getByText('Oylik')).toBeInTheDocument();
    expect(within(plan).getByText("149 000 so'm")).toBeInTheDocument();

    const devices = screen.getByRole('heading', { name: 'Qurilmalar' }).parentElement!;
    expect(within(devices).getByText('Kassa (POS)')).toBeInTheDocument();
    expect(within(devices).getByText('1 / 1 ta')).toBeInTheDocument();
    expect(within(devices).getByText("Qo'shimcha qurilma: 59 000 so'm oyiga")).toBeInTheDocument();
    expect(within(devices).getByText('Cheksiz va bepul')).toBeInTheDocument();
    expect(within(devices).getByText('Asosiy tarifda bor')).toBeInTheDocument();

    const features = screen.getByRole('heading', { name: 'Funksiyalar' }).closest('section')!;
    expect(within(features).getAllByText('Asosiy tarifda bor').length).toBe(5);
    expect(within(features).getAllByText('Pro tarifda bor').length).toBe(3);
  });

  it('shows the features an Asosiy store has as included, and Pro ones as locked', async () => {
    mockFetch(routes(asosiyPlan()));
    renderPage(<BillingView />);
    const features = (await screen.findByRole('heading', { name: 'Funksiyalar' })).closest('section')!;
    expect(within(features).getAllByText('Bor')).toHaveLength(5);
    expect(within(features).getAllByText('Pro tarifda bor')).toHaveLength(3);
  });

  it('lists the invoices with what is still unpaid and marks the overdue one', async () => {
    mockFetch(
      routes(startPlan({ status: 'past_due', notice: reminder() }), [
        invoice({ paid_amount: '49000.00', due_date: '2026-10-20' }),
        invoice({ uuid: 'i-0', number: 'OB-202609-000041', status: 'paid', paid_amount: '149000.00', due_date: '2026-10-10' }),
      ]),
    );
    renderPage(<BillingView />);
    const table = await screen.findByRole('table', { name: 'Hisob-fakturalar' });
    const rows = within(table).getAllByRole('row');
    expect(within(rows[1]!).getByText('OB-202610-000042')).toBeInTheDocument();
    expect(within(rows[1]!).getByText("149 000 so'm")).toBeInTheDocument();
    expect(within(rows[1]!).getByText("100 000 so'm")).toBeInTheDocument();
    expect(within(rows[1]!).getByText("To'lanmagan")).toBeInTheDocument();
    expect(within(rows[1]!).getByText("Muddati o'tgan")).toBeInTheDocument();
    expect(within(rows[2]!).getByText("To'langan")).toBeInTheDocument();
    expect(within(rows[2]!).getByText('—')).toBeInTheDocument();
  });

  it('says when the settings close and when they are closed', async () => {
    const lock = new Date(Date.now() + 3 * 86_400_000).toISOString();
    mockFetch(routes(startPlan({ status: 'past_due', notice: reminder(), lock_at: lock })));
    const view = renderPage(<BillingView />);
    expect(await screen.findByText(/Sozlamalar .* dan yopiladi/)).toBeInTheDocument();
    view.unmount();

    mockFetch(routes(startPlan({ status: 'locked', settings_locked: true, locked_since: '2026-11-19T00:00:00+05:00', lock_at: '2026-11-19T00:00:00+05:00', notice: locked() })));
    renderPage(<BillingView />);
    expect(await screen.findByText('Sozlamalar yopiq')).toBeInTheDocument();
    expect(await screen.findByText(/Sozlamalar .* dan yopiq/)).toBeInTheDocument();
  });

  it('explains how to pay and links to the finance page', async () => {
    mockFetch(routes(startPlan()));
    renderPage(<BillingView />);
    expect(await screen.findByText("Kassa har doim ishlaydi: to'lov qabul qilish, chek chiqarish va smena to'xtamaydi.")).toBeInTheDocument();
  });

  it('says so for a store with no plan', async () => {
    mockFetch(routes(startPlan({ plan: null, legacy: true, status: 'none', period: null, current_period: null }), [], { ...oldSubscription, subscription: null }));
    renderPage(<BillingView />);
    expect(await screen.findByText('Tarif biriktirilmagan')).toBeInTheDocument();
  });

  it('offers a retry when the entitlements cannot be read', async () => {
    const typing = userEvent.setup();
    let fail = true;
    mockFetch({
      ...routes(startPlan()),
      'GET /api/v1/owner/billing/entitlements': () => (fail ? json({ success: false, message: 'Server xatosi' }, 500) : ok(startPlan())),
    });
    renderPage(<BillingView />);
    const retry = await screen.findByRole('button', { name: 'Qayta urinish' });
    fail = false;
    await typing.click(retry);
    expect(await screen.findByText('Start')).toBeInTheDocument();
  });
});
