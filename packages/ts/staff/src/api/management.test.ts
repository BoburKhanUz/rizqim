import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { getApiErrorMessage, getApiFieldError, StaffApiError } from './errors';
import {
  createBranch,
  createDeliveryZone,
  createManagedStaff,
  createPairingCode,
  deleteBranch,
  deleteDeliveryZone,
  fetchDeliveryZones,
  fetchManagedStaff,
  fetchRegionsAndDistricts,
  fetchServiceFees,
  fetchStoreDevices,
  fetchStoreSettings,
  renameStoreDevice,
  revokeStoreDevice,
  saveServiceFee,
  saveStoreSettings,
  updateBranch,
  updateDeliveryZone,
  updateManagedStaff,
  type ZonePolygon,
} from './management';

afterEach(() => vi.unstubAllGlobals());

const POLYGON: ZonePolygon = { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.3]]] };

describe('staff', () => {
  it('lists the staff the caller manages', async () => {
    mockFetch({ 'GET /api/v1/owner/staff': () => ok([{ id: 4, name: 'Ali', role: 'cook', can_edit: true, login_phone_enabled: true }]) });
    const [member] = await fetchManagedStaff(testApi());
    expect(member!.name).toBe('Ali');
    expect(member!.can_edit).toBe(true);
  });

  it('adds a member and answers the invite link', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/staff': () => ok({ staff: { id: 9, name: 'Vali' }, invite_link: 'https://t.me/bot?start=invite_x' }, {}) });
    const created = await createManagedStaff(testApi(), { name: 'Vali', role: 'cashier', branch_id: 2, phone: '+998901234567' });
    expect(created.invite_link).toContain('invite_');
    expect(await calls[0]!.json()).toEqual({ name: 'Vali', role: 'cashier', branch_id: 2, phone: '+998901234567' });
  });

  it('sends only what it is given when editing, and carries the refusal of an SMS-proven phone', async () => {
    const { calls } = mockFetch({
      'PATCH /api/v1/owner/staff/4': async (request) => {
        const body = (await request.clone().json()) as Record<string, unknown>;
        return 'phone' in body ? json({ success: false, message: 'Bu raqamni xodimning...', errors: { phone: ["Bu raqamni xodimning o'zi SMS kod bilan tasdiqlagan"] } }, 422) : ok({ id: 4, name: 'Ali Yangi' });
      },
    });
    const api = testApi();
    expect((await updateManagedStaff(api, 4, { name: 'Ali Yangi' })).name).toBe('Ali Yangi');
    expect(await calls[0]!.json()).toEqual({ name: 'Ali Yangi' });
    const error = await updateManagedStaff(api, 4, { phone: '+998901111111' }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(StaffApiError);
    expect(getApiFieldError(error, 'phone')).toContain('SMS kod bilan');
  });
});

describe('branches', () => {
  it('creates and edits a branch with its working hours', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/branches': () => ok({ id: 2, name: 'Yunusobod' }),
      'PATCH /api/v1/owner/branches/2': () => ok({ id: 2, name: 'Yunusobod' }),
    });
    const api = testApi();
    await createBranch(api, { name: 'Yunusobod', address: 'Amir Temur 1', region_id: 1, district_id: 2, latitude: 41.3, longitude: 69.2 });
    await updateBranch(api, 2, { open_time: null, close_time: null });
    expect(await calls[1]!.json()).toEqual({ open_time: null, close_time: null });
  });

  it('says why a branch cannot be deleted', async () => {
    mockFetch({ 'DELETE /api/v1/owner/branches/1': () => json({ success: false, message: "Filialda buyurtmalar bor, uni o'chirib bo'lmaydi.", data: { reason: 'branch_in_use' } }, 422) });
    const error = await deleteBranch(testApi(), 1).catch((e: unknown) => e);
    expect((error as StaffApiError).reason).toBe('branch_in_use');
    expect(getApiErrorMessage(error)).toContain('buyurtmalar bor');
  });

  it('reads the regions and districts (any signed-in person may)', async () => {
    mockFetch({ 'GET /api/v1/locations': () => ok({ regions: [{ id: 1, name: 'Toshkent', is_active: true }], districts: [{ id: 5, region_id: 1, name: 'Chilonzor', is_active: true }] }) });
    const locations = await fetchRegionsAndDistricts(testApi());
    expect(locations.districts[0]!.region_id).toBe(1);
  });
});

describe('delivery zones', () => {
  it('lists, creates, edits and deletes a zone', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/delivery-zones': () => ok([{ id: 7, name: 'Markaz', polygon: POLYGON, delivery_price: '12000.00' }]),
      'POST /api/v1/owner/delivery-zones': () => ok({ id: 8, name: 'Yangi' }),
      'PATCH /api/v1/owner/delivery-zones/8': () => ok({ id: 8, name: 'Yangi', active: false }),
      'DELETE /api/v1/owner/delivery-zones/8': () => ok(null),
    });
    const api = testApi();
    expect((await fetchDeliveryZones(api))[0]!.delivery_price).toBe('12000.00');
    await createDeliveryZone(api, { name: 'Yangi', polygon: POLYGON, delivery_price: 12000, free_delivery_from: null, branch_id: 1 });
    expect(await calls[1]!.json()).toEqual({ name: 'Yangi', polygon: POLYGON, delivery_price: 12000, free_delivery_from: null, branch_id: 1 });
    await updateDeliveryZone(api, 8, { active: false });
    expect(await calls[2]!.json()).toEqual({ active: false });
    await deleteDeliveryZone(api, 8);
    expect(calls[3]!.method).toBe('DELETE');
  });

  it('carries the Uzbek reason of a bad polygon', async () => {
    mockFetch({ 'POST /api/v1/owner/delivery-zones': () => json({ success: false, message: 'Xato', errors: { polygon: ["Ko'pburchak yopiq bo'lishi kerak."] } }, 422) });
    const error = await createDeliveryZone(testApi(), { name: 'X', polygon: POLYGON }).catch((e: unknown) => e);
    expect(getApiFieldError(error, 'polygon')).toBe("Ko'pburchak yopiq bo'lishi kerak.");
  });
});

describe('store settings and fees', () => {
  it('reads the settings with the timezone and posts the name and timezone with the stored values', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/settings': () => ok({ id: 1, name: 'Osh markazi', timezone: 'Asia/Tashkent', is_open: true }),
      'POST /api/v1/owner/settings': () => ok(null),
    });
    const api = testApi();
    expect((await fetchStoreSettings(api))!.timezone).toBe('Asia/Tashkent');
    await saveStoreSettings(api, {
      name: 'Yangi nom',
      timezone: 'Asia/Samarkand',
      logo: 'a.png',
      banner: null,
      description: null,
      address: null,
      location_url: null,
      is_open: true,
      min_order_amount: 0,
      payment_settings: {
        delivery_fee: 0,
        delivery_enabled: true,
        pickup_enabled: true,
        working_hours: { open: '09:00', close: '22:00' },
        cash: { enabled: true },
        click: { enabled: false },
        payme: { enabled: false },
        uzum: { enabled: false },
        paynet: { enabled: false },
      },
    });
    const sent = (await calls[1]!.json()) as Record<string, unknown>;
    expect(sent).toMatchObject({ name: 'Yangi nom', timezone: 'Asia/Samarkand', logo: 'a.png', is_open: true });
  });

  it('reads the fee rows (the default and the branch overrides) and saves one', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/service-fees': () => ok({ channels: ['pos_hall', 'telegram'], settings: [{ branch_id: null, service_charge_percent: '10.00', service_charge_channels: null, place_fee_amount: '0.00', place_fee_mode: 'per_person', place_fee_channels: null }] }),
      'PUT /api/v1/owner/service-fees': () => ok({ branch_id: 3, service_charge_percent: '5.00', service_charge_channels: null, place_fee_amount: '2000.00', place_fee_mode: 'per_table', place_fee_channels: null }),
    });
    const api = testApi();
    const fees = await fetchServiceFees(api);
    expect(fees.settings[0]!.service_charge_percent).toBe('10.00');
    const saved = await saveServiceFee(api, { branch_id: 3, service_charge_percent: 5, place_fee_amount: 2000, place_fee_mode: 'per_table' });
    expect(saved.branch_id).toBe(3);
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, service_charge_percent: 5, place_fee_amount: 2000, place_fee_mode: 'per_table' });
  });

  it('survives an empty fee answer', async () => {
    mockFetch({ 'GET /api/v1/owner/service-fees': () => ok(null) });
    expect(await fetchServiceFees(testApi())).toEqual({ channels: [], settings: [] });
  });
});

describe('terminals', () => {
  it('lists, renames and revokes a device, asking for the revoked ones only when told to', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/devices': () => ok([{ uuid: 'd-1', name: 'Kassa 1', type: 'pos', revoked_at: null }]),
      'PATCH /api/v1/owner/devices/d-1': () => ok({ uuid: 'd-1', name: 'Kassa 2' }),
      'POST /api/v1/owner/devices/d-1/revoke': () => ok({ uuid: 'd-1', revoked_at: '2026-10-21T10:00:00+05:00' }),
    });
    const api = testApi();
    await fetchStoreDevices(api);
    await fetchStoreDevices(api, true);
    expect(new URL(calls[0]!.url).searchParams.get('include_revoked')).toBe('0');
    expect(new URL(calls[1]!.url).searchParams.get('include_revoked')).toBe('1');
    expect((await renameStoreDevice(api, 'd-1', 'Kassa 2')).name).toBe('Kassa 2');
    expect((await revokeStoreDevice(api, 'd-1')).revoked_at).not.toBeNull();
  });

  it('creates a pairing code', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/devices/pairing-codes': () => ok({ code: 'ABCD-EFGH', expires_at: '2026-10-21T10:10:00+05:00', type: 'kds', branch_id: 1 }) });
    const code = await createPairingCode(testApi(), { type: 'kds', branch_id: 1, name: 'Oshxona' });
    expect(code.code).toBe('ABCD-EFGH');
    expect(await calls[0]!.json()).toEqual({ type: 'kds', branch_id: 1, name: 'Oshxona' });
  });
});
