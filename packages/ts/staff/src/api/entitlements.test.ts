import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { StaffApiError } from './errors';
import {
  billingRefusalOf,
  daysUntilLock,
  fetchEntitlements,
  hasFeature,
  invoiceOutstanding,
  planName,
  requiredPlanName,
  trialDaysLeft,
  type Entitlements,
} from './entitlements';

afterEach(() => vi.unstubAllGlobals());

const START: Entitlements = {
  plan: { code: 'start', name: 'Start', tier: 'start', version: 2 },
  legacy: false,
  period: 'monthly',
  status: 'trialing',
  trial_ends_at: '2026-11-10T00:00:00+05:00',
  current_period: { start: '2026-11-01T00:00:00+05:00', end: '2026-12-01T00:00:00+05:00' },
  term_ends_at: null,
  locked_since: null,
  lock_at: null,
  settings_locked: false,
  features: { hall: true, shifts: true, bookings: false, deposits: false, customer_accounts: false, kds: false, service_charge: false },
  required_plans: { bookings: 'asosiy', banquet: 'pro' },
  devices: { pos: { included: 1, active: 1, extra: 0, extra_price: '59000.00', can_add: true }, kds: { included: 0, active: 0, extra: 0, extra_price: '29000.00', can_add: false }, waiter: { included: null, unlimited: true, can_add: true } },
  notice: null,
};

describe('entitlements', () => {
  it('reads GET /owner/billing/entitlements', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(START) });
    expect((await fetchEntitlements(testApi())).plan?.code).toBe('start');
    expect(calls).toHaveLength(1);
  });

  it('says which features are off, and keeps everything open when it does not know', () => {
    expect(hasFeature(START, 'bookings')).toBe(false);
    expect(hasFeature(START, 'hall')).toBe(true);
    // A base feature is never off, even if the server said so by mistake
    expect(hasFeature({ ...START, features: { hall: false } }, 'hall')).toBe(true);
    // Not listed: on
    expect(hasFeature(START, 'banquet')).toBe(true);
    expect(hasFeature({ ...START, legacy: true }, 'bookings')).toBe(true);
    expect(hasFeature(undefined, 'bookings')).toBe(true);
    expect(hasFeature(null, 'deposits')).toBe(true);
  });

  it('names the cheapest plan that has a feature', () => {
    expect(requiredPlanName(START, 'bookings')).toBe('Asosiy');
    expect(requiredPlanName(START, 'banquet')).toBe('Pro');
    expect(requiredPlanName(START, 'kds')).toBeNull();
    expect(planName('custom')).toBe('Custom');
    expect(planName(null)).toBeNull();
  });

  it('counts the days to the lock and the trial days left', () => {
    const now = new Date('2026-11-07T10:00:00+05:00');
    expect(trialDaysLeft(START, now)).toBe(3);
    expect(trialDaysLeft({ ...START, status: 'active' }, now)).toBeNull();
    expect(daysUntilLock({ lock_at: '2026-11-12T00:00:00+05:00', settings_locked: false }, now)).toBe(5);
    expect(daysUntilLock({ lock_at: '2026-11-12T00:00:00+05:00', settings_locked: true }, now)).toBeNull();
    expect(daysUntilLock({ lock_at: '2026-11-01T00:00:00+05:00', settings_locked: false }, now)).toBe(0);
    expect(daysUntilLock({ lock_at: null, settings_locked: false }, now)).toBeNull();
  });
});

describe('the server refusals', () => {
  const refusal = (reason: string, data: Record<string, unknown>, top: Record<string, unknown> = {}, status = 403) =>
    new StaffApiError(status, { success: false, message: 'Bu imkoniyat (Bron) Asosiy tarifida bor.', code: reason, ...top, data: { reason, ...data } });

  it('reads plan_feature_missing', () => {
    const read = billingRefusalOf(refusal('plan_feature_missing', { feature: 'bookings', feature_label: 'Bron', required_plan: 'asosiy', required_plan_name: 'Asosiy' }));
    expect(read).toMatchObject({ reason: 'plan_feature_missing', feature: 'bookings', requiredPlan: 'asosiy', requiredPlanName: 'Asosiy', message: 'Bu imkoniyat (Bron) Asosiy tarifida bor.' });
  });

  it('reads subscription_locked and device_limit', () => {
    expect(billingRefusalOf(refusal('subscription_locked', { locked_since: '2026-11-19T00:00:00+05:00' }))).toMatchObject({ reason: 'subscription_locked', lockedSince: '2026-11-19T00:00:00+05:00' });
    expect(billingRefusalOf(refusal('device_limit', { device_type: 'pos', included: 1, active: 1 }))).toMatchObject({ reason: 'device_limit', deviceType: 'pos', included: 1, active: 1 });
  });

  it('is not fooled by other 403s or other errors', () => {
    expect(billingRefusalOf(refusal('approval_required', {}))).toBeUndefined();
    expect(billingRefusalOf(refusal('plan_feature_missing', {}, {}, 422))).toBeUndefined();
    expect(billingRefusalOf(new Error('boom'))).toBeUndefined();
    expect(billingRefusalOf(undefined)).toBeUndefined();
  });

  it('comes out of a real failed request', async () => {
    mockFetch({
      'POST /api/v1/pos/bookings': () => json({ success: false, message: 'Bu imkoniyat (Bron) Asosiy tarifida bor.', code: 'plan_feature_missing', feature: 'bookings', required_plan: 'asosiy', data: { reason: 'plan_feature_missing', feature: 'bookings', required_plan: 'asosiy', required_plan_name: 'Asosiy' } }, 403),
    });
    const response = await testApi().client.POST('/v1/pos/bookings', { body: {} as never });
    const error = new StaffApiError(response.response.status, response.error);
    expect(billingRefusalOf(error)?.requiredPlanName).toBe('Asosiy');
  });
});

describe('invoices', () => {
  it('works out what is still unpaid', () => {
    expect(invoiceOutstanding({ amount: 249000, paid_amount: '100000.00', status: 'issued' })).toBe(149000);
    expect(invoiceOutstanding({ amount: 249000, status: 'issued' })).toBe(249000);
    expect(invoiceOutstanding({ amount: 249000, paid_amount: '249000.00', status: 'paid' })).toBe(0);
    expect(invoiceOutstanding({ amount: 249000, paid_amount: '0.00', status: 'void' })).toBe(0);
    expect(invoiceOutstanding({ amount: 100, paid_amount: '300.00', status: 'issued' })).toBe(0);
  });
});
