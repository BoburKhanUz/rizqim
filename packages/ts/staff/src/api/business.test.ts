import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { exportReport, fetchOwnerAnalytics, getCustomRangeProblem, MAX_CUSTOM_DAYS, toAnalyticsPeriod, toDateInputValue } from './analytics';
import { getApiFieldError } from './errors';
import { fetchInvoices, fetchOwnerFinance, fetchSubscription, initiateTopup, requestPayout } from './payments';
import { fetchOwnerSettings, saveOwnerSettings, uploadSettingsFile } from './settings';
import { fetchEmployees, fetchMyPermissions, inviteEmployee, removeEmployee, resetStaffPin, setStaffPin, transferEmployee, updateStaffRole } from './team';

afterEach(() => vi.unstubAllGlobals());

describe('team', () => {
  it('lists the employees (the spec types the list as a string, the controller sends users)', async () => {
    mockFetch({ 'GET /api/v1/owner/employees': () => ok([{ id: 4, name: 'Ali', role: 'courier', branch: { id: 1, name: 'Chilonzor' }, telegram_invite_token: 'emp_x' }]) });
    const [employee] = await fetchEmployees(testApi());
    expect(employee!.name).toBe('Ali');
    expect(employee!.branch?.name).toBe('Chilonzor');
    expect(employee!.telegram_invite_token).toBe('emp_x');
  });

  it('invites, transfers and removes an employee', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/employees/invite': () => ok({ employee: { id: 5 }, invite_link: 'https://t.me/bot?start=invite_x' }),
      'POST /api/v1/owner/employees/5/transfer': () => ok({ id: 5, branch_id: 2 }),
      'DELETE /api/v1/owner/employees/5': () => ok(null),
    });
    const api = testApi();
    const invite = await inviteEmployee(api, { role: 'manager', name: 'Vali', branchId: 1 });
    expect(invite.invite_link).toContain('invite_');
    expect(await calls[0]!.json()).toEqual({ role: 'manager', name: 'Vali', branch_id: 1 });
    await transferEmployee(api, 5, 2);
    expect(await calls[1]!.json()).toEqual({ branch_id: 2 });
    await removeEmployee(api, 5);
    expect(calls[2]!.method).toBe('DELETE');
  });

  it('carries the 422 message of the field to the caller', async () => {
    mockFetch({ 'POST /api/v1/owner/employees/5/transfer': () => json({ success: false, message: 'Xato', errors: { branch_id: ["Noto'g'ri filial"] } }, 422) });
    const error = await transferEmployee(testApi(), 5, 9).catch((e: unknown) => e);
    expect(getApiFieldError(error, 'branch_id')).toBe("Noto'g'ri filial");
  });

  it('sets and resets a PIN, changes a role and reads the permissions', async () => {
    const { calls } = mockFetch({
      'PUT /api/v1/owner/staff/5/pin': () => ok({ user_id: 5, staff_code: 'K-12', pin_set_at: null }),
      'DELETE /api/v1/owner/staff/5/pin': () => ok(null),
      'PUT /api/v1/owner/staff/5/role': () => ok({ id: 5, name: 'Ali', role: 'cook', branch_id: 1 }),
      'GET /api/v1/me/permissions': () => ok({ role: 'owner', store_id: 1, branch_id: null, branch_scoped: false, permissions: ['orders.view'], device_uuid: null }),
    });
    const api = testApi();
    expect((await setStaffPin(api, 5, '1234')).staff_code).toBe('K-12');
    expect(await calls[0]!.json()).toEqual({ pin: '1234' });
    await resetStaffPin(api, 5);
    await updateStaffRole(api, 5, { role: 'cook', branch_id: 1 });
    expect(await calls[2]!.json()).toEqual({ role: 'cook', branch_id: 1 });
    expect((await fetchMyPermissions(api)).permissions).toEqual(['orders.view']);
  });
});

describe('settings', () => {
  it('reads the store settings and says nothing for an empty answer', async () => {
    mockFetch({ 'GET /api/v1/owner/settings': () => ok({ id: 1, name: 'Oshxona', payment_settings: { cash: { enabled: true } } }) });
    expect((await fetchOwnerSettings(testApi()))?.payment_settings?.cash).toEqual({ enabled: true });
    mockFetch({ 'GET /api/v1/owner/settings': () => ok(null) });
    expect(await fetchOwnerSettings(testApi())).toBeNull();
  });

  it('saves them as the object the form builds', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/settings': () => ok({ id: 1 }) });
    const input = {
      logo: null,
      banner: null,
      description: null,
      address: 'Toshkent',
      location_url: null,
      is_open: true,
      min_order_amount: 30000,
      payment_settings: {
        delivery_fee: 8000,
        delivery_enabled: true,
        pickup_enabled: true,
        working_hours: { open: '09:00', close: '23:00' },
        cash: { enabled: true },
        click: { enabled: false },
        payme: { enabled: false },
        uzum: { enabled: false },
        paynet: { enabled: false },
      },
    };
    await saveOwnerSettings(testApi(), input);
    expect(await calls[0]!.json()).toEqual(input);
  });

  it('uploads a file as multipart under the name "file"', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/settings/upload': () => ok({ path: 'logos/a.png', url: '/storage/logos/a.png' }) });
    const result = await uploadSettingsFile(testApi(), new File(['x'], 'a.png', { type: 'image/png' }));
    expect(result.path).toBe('logos/a.png');
    const sent = (await calls[0]!.formData()).get('file') as Blob;
    expect([sent.size, sent.type]).toEqual([1, 'image/png']);
  });
});

describe('payments', () => {
  it('reads the finance of the store', async () => {
    mockFetch({ 'GET /api/v1/owner/finance': () => ok({ debt_balance: 100, wallet_balance: 50, ledger_transactions: [], wallet_transactions: [], payout_requests: [] }) });
    expect((await fetchOwnerFinance(testApi())).wallet_balance).toBe(50);
  });

  it('starts a top-up and asks for a payout', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/finance/top-up': () => ok({ topup_uuid: 't1', payment_url: 'https://pay.test/1' }),
      'POST /api/v1/owner/payouts': () => ok({ id: 3, status: 'pending' }),
    });
    const api = testApi();
    expect((await initiateTopup(api, { amount: 100000, provider: 'click' })).payment_url).toBe('https://pay.test/1');
    expect(await calls[0]!.json()).toEqual({ amount: 100000, provider: 'click' });
    await requestPayout(api, { amount: 50000, card_number: '8600123412341234' });
    expect(await calls[1]!.json()).toEqual({ amount: 50000, card_number: '8600123412341234' });
  });

  it('reads the tariff plan and the invoices', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/subscription': () => ok({ subscription: null }),
      'GET /api/v1/owner/invoices': () => ok({ current_page: 1, data: [], per_page: 15, total: 0 }),
    });
    const api = testApi();
    await fetchSubscription(api);
    expect((await fetchInvoices(api, { status: 'paid', per_page: 5 })).total).toBe(0);
    expect(new URL(calls[1]!.url).searchParams.get('status')).toBe('paid');
  });
});

describe('analytics', () => {
  it('maps the screen timeframes to the API periods', () => {
    expect(toAnalyticsPeriod('weekly')).toBe('week');
    expect(toAnalyticsPeriod('monthly')).toBe('month');
    expect(toAnalyticsPeriod('yearly')).toBe('year');
    expect(toAnalyticsPeriod('today')).toBe('today');
  });

  it('asks for a period, and for from/to only on a custom one', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/analytics': () => ok({ summary: { orders_count: 3 } }) });
    const api = testApi();
    await fetchOwnerAnalytics(api, 'weekly', { start: '2026-10-01', end: '2026-10-07' });
    await fetchOwnerAnalytics(api, 'custom', { start: '2026-10-01', end: '2026-10-07' });
    const first = new URL(calls[0]!.url).searchParams;
    expect(first.get('period')).toBe('week');
    expect(first.has('from')).toBe(false);
    const second = new URL(calls[1]!.url).searchParams;
    expect([second.get('period'), second.get('from'), second.get('to')]).toEqual(['custom', '2026-10-01', '2026-10-07']);
  });

  it('says why a custom range cannot be asked of the server', () => {
    expect(getCustomRangeProblem(undefined)).toBe('missing');
    expect(getCustomRangeProblem({ start: '', end: '2026-10-07' })).toBe('missing');
    expect(getCustomRangeProblem({ start: '2026-10-08', end: '2026-10-07' })).toBe('reversed');
    expect(getCustomRangeProblem({ start: '2026-01-01', end: '2026-12-31' })).toBe('too_long');
    expect(getCustomRangeProblem({ start: '2026-10-01', end: '2026-10-07' })).toBeNull();
    // both ends count: 93 days is the longest, 94 is refused
    expect(MAX_CUSTOM_DAYS).toBe(93);
    expect(getCustomRangeProblem({ start: '2026-01-01', end: '2026-04-03' })).toBeNull();
    expect(getCustomRangeProblem({ start: '2026-01-01', end: '2026-04-04' })).toBe('too_long');
  });

  it('accepts one day, refuses half-typed dates and counts days across a change of the clocks like any other', () => {
    expect(getCustomRangeProblem({ start: '2026-10-05', end: '2026-10-05' })).toBeNull();
    expect(getCustomRangeProblem({ start: '2026-06-01', end: '2026-09-01' })).toBeNull(); // 93 days, both ends counted
    expect(getCustomRangeProblem({ start: '2026-06-01', end: '2026-09-02' })).toBe('too_long');
    expect(getCustomRangeProblem({ start: '2026-10-05', end: '2026-1' })).toBe('missing');
    expect(getCustomRangeProblem({ start: '2026-03-01', end: '2026-05-31' })).toBeNull();
  });

  it('writes a date for <input type="date"> in the local calendar, not the UTC one', () => {
    expect(toDateInputValue(new Date(2026, 0, 5))).toBe('2026-01-05');
    expect(toDateInputValue(new Date(2026, 9, 5, 0, 30))).toBe('2026-10-05');
    expect(toDateInputValue(new Date(2026, 9, 5, 23, 30))).toBe('2026-10-05');
  });

  it('exports a report', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/reports/export': () => json({ success: true, message: 'Yuborildi', data: null }) });
    expect(await exportReport(testApi(), { timeframe: 'custom', start_date: '2026-10-01', end_date: '2026-10-07' })).toBe('Yuborildi');
    expect(await calls[0]!.json()).toEqual({ timeframe: 'custom', start_date: '2026-10-01', end_date: '2026-10-07' });
  });
});
