import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import {
  createPackagingRule,
  deletePackagingRule,
  fetchBusinessDays,
  fetchPackagingRules,
  fetchPickupSettings,
  PACKAGING_ORDER_TYPES,
  savePickupSettings,
  updatePackagingRule,
} from './takeaway';

afterEach(() => vi.unstubAllGlobals());

const RULE = {
  id: 9,
  branch_id: 3,
  name: 'Paket',
  basis: 'order',
  basis_label: 'Buyurtmaga',
  category_id: null,
  product_id: null,
  quantity: 1,
  unit_price: '1000.00',
  mxik: null,
  package_code: null,
  unit: 'dona',
  order_types: ['takeaway', 'pickup'],
  is_active: true,
  sort_order: 1,
};

describe('the packaging rules (P6-34)', () => {
  it('lists, adds, changes and deletes a branch rule on its own paths', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/packaging-rules': () => ok([RULE]),
      'POST /api/v1/owner/branches/3/packaging-rules': () => ok(RULE),
      'PUT /api/v1/owner/branches/3/packaging-rules/9': () => ok({ ...RULE, unit_price: '1500.00' }),
      'DELETE /api/v1/owner/branches/3/packaging-rules/9': () => ok({ id: 9 }),
    });
    const api = testApi();
    expect((await fetchPackagingRules(api, 3))[0]!.name).toBe('Paket');
    await createPackagingRule(api, 3, { name: 'Paket', basis: 'order', unit_price: '1000.00', order_types: ['takeaway'] });
    expect(await calls[1]!.json()).toEqual({ name: 'Paket', basis: 'order', unit_price: '1000.00', order_types: ['takeaway'] });
    expect((await updatePackagingRule(api, 3, 9, { unit_price: '1500.00' })).unit_price).toBe('1500.00');
    await deletePackagingRule(api, 3, 9);
    expect(calls.map((call) => call.method)).toEqual(['GET', 'POST', 'PUT', 'DELETE']);
    expect(PACKAGING_ORDER_TYPES).toEqual(['takeaway', 'pickup', 'delivery']);
  });

  it("keeps the server's refusal of a module that is off", async () => {
    mockFetch({
      'GET /api/v1/owner/branches/3/packaging-rules': () => json({ success: false, message: "Qadoq yoqilmagan.", data: { reason: 'module_disabled' } }, 403),
    });
    const error = (await fetchPackagingRules(testApi(), 3).catch((e: unknown) => e)) as { reason?: string; status?: number };
    expect(error.status).toBe(403);
    expect(error.reason).toBe('module_disabled');
  });
});

describe('the pickup-time rules and the business days (P6-34)', () => {
  it('reads and saves the pickup rules, and asks for a range of business days', async () => {
    const pickup = { branch_id: 3, configured: false, pickup_min_lead_minutes: 20, pickup_slot_minutes: 15, pickup_max_days_ahead: 1, pickup_prep_minutes: 15, round_the_clock: true, opening_hours: { open: null, close: null }, updated_at: null };
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/pickup-settings': () => ok(pickup),
      'PUT /api/v1/owner/branches/3/pickup-settings': () => ok({ ...pickup, pickup_prep_minutes: 25 }),
      'GET /api/v1/owner/branches/3/business-days': () => ok({ branch_id: 3, business_day: { business_date: '2026-10-23', starts_at: '05:00', started_at: '', next_starts_at: '', round_the_clock: true }, days: [] }),
    });
    const api = testApi();
    expect((await fetchPickupSettings(api, 3)).round_the_clock).toBe(true);
    expect((await savePickupSettings(api, 3, { pickup_prep_minutes: 25 })).pickup_prep_minutes).toBe(25);
    expect(await calls[1]!.json()).toEqual({ pickup_prep_minutes: 25 });
    expect((await fetchBusinessDays(api, { branchId: 3, from: '2026-10-20', to: '2026-10-23' })).business_day.business_date).toBe('2026-10-23');
    const params = new URL(calls[2]!.url).searchParams;
    expect([params.get('from'), params.get('to')]).toEqual(['2026-10-20', '2026-10-23']);
  });
});
