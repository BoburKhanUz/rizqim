import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { billingInvoice, catalog, devicePage, fiscalCard, fiscalRow, invoiceCard, invoiceRow, overview, page, storeBilling, subscription } from './fixtures';
import { initialTab, PosView, type PosInitial } from './PosView';

const FULL = ['subscriptions.manage', 'finance.view', 'finance.manage', 'devices.view', 'stores.manage', 'system.manage'].map((name) => `admin.${name}`);

function setup(options: { permissions?: string[]; initial?: PosInitial; overviewBody?: ReturnType<typeof overview> } = {}) {
  const { permissions = FULL, initial, overviewBody = overview() } = options;
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /pos/overview': () => ok(overviewBody),
    'GET /billing/subscriptions': () => ok({ data: [{ subscription: subscription(), store: { id: 12, name: 'Lazzat Oshxona' } }], next_cursor: null, per_page: 30, counts: { active: 1 } }),
    'GET /billing/plans': () => ok(catalog()),
    'GET /billing/stores/12': () => ok(storeBilling()),
    'GET /pos/invoices': () => ok(page([invoiceRow()], { counts: overviewBody.invoices })),
    'GET /pos/invoices/1a0c0000-0000-4000-8000-000000000001': () => ok(invoiceCard()),
    'GET /pos/devices': () => ok(devicePage()),
    'GET /pos/fiscal-receipts': () => ok(page([fiscalRow()], { summary: overviewBody.fiscal })),
    'GET /pos/fiscal-receipts/5f0c2a10-0000-4000-8000-000000000001': () => ok(fiscalCard()),
  });
  renderPage(<PosView initial={initial} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('initialTab', () => {
  it('opens the tab a deep link points at; a row id wins over ?tab=', () => {
    expect(initialTab({})).toBe('subscriptions');
    expect(initialTab({ tab: 'shifts' })).toBe('shifts');
    expect(initialTab({ tab: 'nonsense' })).toBe('subscriptions');
    expect(initialTab({ fiscal: 'f1' })).toBe('fiscal');
    expect(initialTab({ device: 'd1', tab: 'invoices' })).toBe('devices');
    expect(initialTab({ invoice: 'i1' })).toBe('invoices');
    expect(initialTab({ store: '12' })).toBe('subscriptions');
  });
});

describe('PosView', () => {
  it('shows a tab for each part the member may open, with the numbers that need a person', async () => {
    setup();
    const tabs = await screen.findByRole('tablist', { name: "POS va obunalar bo'limi" });
    // 1 trial ending + 1 past due; 1 overdue invoice; 1 offline + 1 outdated; 2 + 1 shifts; 2 + 1 receipts; 2 sync errors
    expect(await within(tabs).findByRole('tab', { name: 'Obunalar (2)' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Tariflar' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Hisob-fakturalar (1)' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Qurilmalar (2)' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Smenalar (3)' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Fiskal cheklar (3)' })).toBeInTheDocument();
    expect(within(tabs).getByRole('tab', { name: 'Sinxron xatolari (2)' })).toBeInTheDocument();
  });

  it('shows only the till side to a member with devices.view only', async () => {
    const { callsTo } = setup({ permissions: ['admin.devices.view'], overviewBody: overview({ subscriptions: null, invoices: null }) });
    const tabs = await screen.findByRole('tablist');
    expect(within(tabs).queryByRole('tab', { name: /Obunalar/ })).not.toBeInTheDocument();
    expect(within(tabs).queryByRole('tab', { name: /Hisob-fakturalar/ })).not.toBeInTheDocument();
    // The first tab they may open is the one that shows
    expect(await within(tabs).findByRole('tab', { name: /Qurilmalar/, selected: true })).toBeInTheDocument();
    await screen.findByRole('list', { name: "Qurilmalar ro'yxati" });
    expect(callsTo('GET', '/billing/subscriptions')).toHaveLength(0);
  });

  it('says so to a member with no part of the section', async () => {
    setup({ permissions: ['admin.orders.view'] });
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
  });

  it('opens the fiscal receipt of a Bugun link, with its reason and the retry for those who may', async () => {
    setup({ initial: { fiscal: '5f0c2a10-0000-4000-8000-000000000001' } });
    expect(await screen.findByRole('heading', { name: /Lazzat Oshxona · 86/ })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /Fiskal cheklar/, selected: true })).toBeInTheDocument();
    expect(screen.getAllByText('Soliq serveri javob bermadi').length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Qayta yuborish' })).toBeInTheDocument();
  });

  it('opens the invoice of a Bugun link and the store subscription of the store card link', async () => {
    const first = setup({ initial: { invoice: '1a0c0000-0000-4000-8000-000000000001' } });
    expect(await screen.findByRole('heading', { name: 'OB-202610-000015' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /Hisob-fakturalar/, selected: true })).toBeInTheDocument();
    expect(first.calls.some((call) => call.path === '/billing/stores/12')).toBe(false);
  });

  it('opens a store subscription from ?store=', async () => {
    setup({ initial: { store: '12' } });
    expect(await screen.findByRole('heading', { name: 'Lazzat Oshxona' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tarif biriktirish' })).toBeInTheDocument();
  });

  it('goes from a store subscription to one of its invoices', async () => {
    const { user } = setup({ initial: { store: '12' } });
    await screen.findByRole('heading', { name: 'Lazzat Oshxona' });
    await user.click(screen.getByRole('button', { name: billingInvoice().number }));
    expect(await screen.findByRole('heading', { name: 'OB-202610-000015' })).toBeInTheDocument();
    await vi.waitFor(() => expect(screen.getByRole('tab', { name: /Hisob-fakturalar/, selected: true })).toBeInTheDocument());
  });
});
