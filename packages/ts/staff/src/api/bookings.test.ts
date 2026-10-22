import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { createCustomerAccount, fetchAccountStatement, fetchCustomerAccounts, fetchDebtors, updateCustomerAccount } from './accounts';
import { cancelBooking, confirmBooking, createBooking, fetchBooking, fetchBookingCalendar, fetchBookings, updateBooking } from './bookings';
import { newIdempotencyKey, withIdempotency } from './idempotency';

afterEach(() => vi.unstubAllGlobals());

describe('idempotency keys', () => {
  it('makes a new key each time and puts it in the header', () => {
    expect(newIdempotencyKey()).not.toBe(newIdempotencyKey());
    expect(withIdempotency('abc')).toEqual({ headers: { 'Idempotency-Key': 'abc' } });
  });
});

describe('bookings', () => {
  it('reads the calendar of a branch for a range', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/bookings/calendar': () => ok({ branch_id: 2, from: '2026-10-22', to: '2026-10-22', timezone: 'Asia/Tashkent', rooms: [], unassigned: [] }) });
    const calendar = await fetchBookingCalendar(testApi(), { branch_id: 2, from: '2026-10-22', to: '2026-10-28', include_canceled: false });
    expect(calendar.timezone).toBe('Asia/Tashkent');
    expect(Object.fromEntries(new URL(calls[0]!.url).searchParams)).toEqual({ branch_id: '2', from: '2026-10-22', to: '2026-10-28', include_canceled: 'false' });
  });

  it('lists by phone and unwraps the bookings; shows one with its deposits', async () => {
    mockFetch({
      'GET /api/v1/pos/bookings': () => ok({ bookings: [{ uuid: 'b1' }] }),
      'GET /api/v1/pos/bookings/b1': () => ok({ uuid: 'b1', items: [], payments: [{ uuid: 'p1', amount: '200000.00' }] }),
    });
    const api = testApi();
    expect(await fetchBookings(api, { phone: '9012' })).toHaveLength(1);
    expect((await fetchBooking(api, 'b1')).payments[0]!.amount).toBe('200000.00');
  });

  it('creates with an idempotency key and keeps the conflicts of a 409', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/pos/bookings': () =>
        json({ success: false, message: 'Xona shu vaqtda band.', data: { reason: 'booking_conflict', conflicts: [{ uuid: 'x', status: 'confirmed' }] } }, 409),
    });
    const error = (await createBooking(testApi(), { dining_table_id: 5, starts_at: '2026-10-25T18:00:00', contact_name: 'Aziz', guests_planned: 20 }, 'key-1').catch((e: unknown) => e)) as { status: number; reason?: string; body: { data: { conflicts: unknown[] } } };
    expect(error.status).toBe(409);
    expect(error.reason).toBe('booking_conflict');
    expect(error.body.data.conflicts).toHaveLength(1);
    expect(calls[0]!.headers.get('Idempotency-Key')).toBe('key-1');
  });

  it('edits with the version, confirms, and cancels with a reason', async () => {
    const { calls } = mockFetch({
      'PATCH /api/v1/pos/bookings/b1': () => ok({ uuid: 'b1', version: 4 }),
      'POST /api/v1/pos/bookings/b1/confirm': () => ok({ uuid: 'b1', status: 'confirmed' }),
      'POST /api/v1/pos/bookings/b1/cancel': () => ok({ uuid: 'b1', status: 'canceled' }),
    });
    const api = testApi();
    await updateBooking(api, 'b1', { guests_planned: 25, version: 3 });
    expect(await calls[0]!.json()).toEqual({ guests_planned: 25, version: 3 });
    expect(calls[0]!.headers.get('Idempotency-Key')).toBeTruthy();
    await confirmBooking(api, 'b1');
    expect(calls[1]!.headers.get('Idempotency-Key')).toBeTruthy();
    await cancelBooking(api, 'b1', 'Mijoz bekor qildi', 4);
    expect(await calls[2]!.json()).toEqual({ reason: 'Mijoz bekor qildi', version: 4 });
    await cancelBooking(api, 'b1', 'Boshqa sabab').catch(() => undefined);
  });
});

describe('customer accounts', () => {
  it('searches, lists the debtors with the total, and reads a statement for a range', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/pos/customer-accounts': () => ok({ accounts: [{ uuid: 'a1', name: 'Karim', balance: '150000.00' }], meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 } }),
      'GET /api/v1/pos/customer-accounts/debtors': () => ok({ total_debt: '150000.00', count: 1, accounts: [{ uuid: 'a1', balance: '150000.00' }] }),
      'GET /api/v1/pos/customer-accounts/a1/statement': () => ok({ opening_balance: '0.00', closing_balance: '150000.00', entries: [] }),
    });
    const api = testApi();
    const page = await fetchCustomerAccounts(api, { q: 'Kar', debtors: true, per_page: 30 });
    expect(page.meta.total).toBe(1);
    expect(Object.fromEntries(new URL(calls[0]!.url).searchParams)).toEqual({ q: 'Kar', debtors: 'true', per_page: '30' });
    expect((await fetchDebtors(api)).total_debt).toBe('150000.00');
    const statement = await fetchAccountStatement(api, 'a1', { from: '2026-10-01', to: '2026-10-22' });
    expect(statement.closing_balance).toBe('150000.00');
    expect(Object.fromEntries(new URL(calls[2]!.url).searchParams)).toEqual({ from: '2026-10-01', to: '2026-10-22' });
  });

  it('opens an account and changes the credit limit and the block; no limit is null, no credit is 0', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/pos/customer-accounts': () => ok({ uuid: 'a2', name: 'Guruh' }),
      'PATCH /api/v1/pos/customer-accounts/a2': () => ok({ uuid: 'a2', is_blocked: true, credit_limit: '0.00' }),
    });
    const api = testApi();
    expect((await createCustomerAccount(api, { name: 'Guruh', credit_limit: null })).uuid).toBe('a2');
    expect(await calls[0]!.json()).toEqual({ name: 'Guruh', credit_limit: null });
    expect((await updateCustomerAccount(api, 'a2', { is_blocked: true, credit_limit: 0 })).is_blocked).toBe(true);
    expect(await calls[1]!.json()).toEqual({ is_blocked: true, credit_limit: 0 });
  });
});
