import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { makeBranch, permissions } from '../management/testSupport';
import { ok, signIn } from '../pos/testSupport';
import { OpsSettingsView } from '../banquetops/OpsSettingsView';
import { PaymentRulesCard } from '../banquetpay/PaymentRulesCard';
import type { Package, Settings } from './api';
import { BanquetSetupView } from './BanquetSetupView';

// P6 wave 5 S1: the banquet packages, extras, settings, payment rules and operations settings are store-wide; a manager
// bound to one branch sees them read-only with a plain note (the server answers 403 store_wide_only), the hall rent stays
// theirs to set. The owner keeps every button.

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), useSearchParams: () => new URLSearchParams('') }));

beforeEach(signIn);
afterEach(() => setSession(null));

const NOTE = "Bu sozlamalar butun do'kon uchun: ularni faqat egasi yoki butun do'kon menejeri o'zgartiradi. Siz faqat ko'rasiz.";
const SLOTS = { morning: { start: '06:00', end: '11:00' }, day: { start: '12:00', end: '16:00' }, evening: { start: '18:00', end: '23:00' } };
const pkg: Package = { id: 3, uuid: 'p', name: 'Standart', per_person_price: '150000.00', minimum_guests: 200, included: ['Osh'], description: null, is_active: true, sort_order: 0, items: [] };
const settings: Settings = { slots: SLOTS, hold_hours: 72, guest_warning_thresholds: [200, 250, 300], window_start: '06:00', window_end: '23:00', advance_mxik: null, advance_package_code: null, cancellation_mxik: null, cancellation_package_code: null, quote_currency: null, version: 1 };

const manager = () => permissions(['banquets.manage'], { role: 'manager', branch_id: 1, branch_scoped: true });
const owner = () => permissions(['banquets.manage']);

const setup = (answer: () => Response) =>
  mockFetch({
    'GET /api/v1/me/permissions': answer,
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/banquets/packages': () => ok({ packages: [pkg] }),
    'GET /api/v1/owner/pos/banquets/extras': () => ok({ extras: [] }),
    'GET /api/v1/owner/pos/banquets/settings': () => ok(settings),
    'GET /api/v1/owner/pos/banquets/halls': () => ok({ halls: [{ hall_id: 5, hall_name: 'Oq zal', branch_id: 1, is_active: true, rates: { morning: null, day: null, evening: '5000000.00', custom: null } }] }),
    'GET /api/v1/owner/pos/banquets/payment-settings': () =>
      ok({ schedule_template: [{ kind: 'deposit', percent: '30', days_before: null }, { kind: 'final', percent: null, days_before: 3 }], cancellation_rules: [{ min_days: 0, retained_percent: '100' }], reminder_days: [3, 1], overdue_reminders: true, version: 2 }),
    'GET /api/v1/owner/pos/banquets/ops-settings': () =>
      ok({
        reserve_percent: '10.00',
        osh: { persons_per_kg: '5.0', product_ids: [], ingredients: [{ key: 'rice', name: 'Guruch', unit: 'kg', per_kg: '1.000' }], kazans: [10, 20, 50] },
        portion_weights: [],
        mxik: { package: { mxik: null, package_code: null }, hall: { mxik: null, package_code: null }, extra: { mxik: null, package_code: null } },
        contract_template: 'Shartnoma',
        contract_template_is_default: false,
        contract_placeholders: [],
        version: 3,
      }),
    'GET /api/v1/pos/banquets/osh-calculator': () => ok({ persons: 200, persons_per_kg: '5.0', osh_kg: '40.0', ingredients: [], kazans: [], kazan_capacity_kg: '50.0' }),
    'GET /api/v1/owner/products': () => ok([]),
  });

describe('store-wide banquet setup', () => {
  it('is read-only for a manager bound to one branch, the hall rent stays editable', async () => {
    setup(manager);
    const typing = userEvent.setup();
    renderPage(<BanquetSetupView />);
    expect(await screen.findByText('Standart')).toBeInTheDocument();
    expect(screen.getByText(NOTE)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Paket qo'shish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Standart ni o'zgartirish" })).not.toBeInTheDocument();

    await typing.click(screen.getByRole('tab', { name: 'Sozlamalar' }));
    const hold = await screen.findByDisplayValue('72');
    expect(hold).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Saqlash' })).not.toBeInTheDocument();

    await typing.click(screen.getByRole('tab', { name: 'Zal ijarasi' }));
    expect(await screen.findByRole('button', { name: 'Saqlash' })).toBeEnabled();
    expect(screen.queryByText(NOTE)).not.toBeInTheDocument();
  });

  it('keeps every button for the owner', async () => {
    setup(owner);
    renderPage(<BanquetSetupView />);
    expect(await screen.findByRole('button', { name: "Paket qo'shish" })).toBeInTheDocument();
    expect(screen.queryByText(NOTE)).not.toBeInTheDocument();
  });

  it('shows the payment rules read-only to a branch-bound manager', async () => {
    setup(manager);
    renderPage(<PaymentRulesCard />);
    expect(await screen.findByText(NOTE)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Saqlash', hidden: true })).not.toBeVisible();
    expect(screen.getAllByRole('textbox')[0]).toBeDisabled();
  });

  it('shows the operations settings read-only to a branch-bound manager', async () => {
    setup(manager);
    renderPage(<OpsSettingsView />);
    expect(await screen.findByText(NOTE)).toBeInTheDocument();
    expect(screen.getByLabelText('Zaxira, %')).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Saqlash' })).not.toBeInTheDocument();
  });
});
