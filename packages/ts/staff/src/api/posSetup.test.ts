import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { getApiFieldError } from './errors';
import {
  assignStationRoutes,
  createKitchenStation,
  createPrinter,
  deleteKitchenStation,
  fetchBranchPosSettings,
  fetchKitchenStations,
  fetchPrinters,
  fetchStationRoutes,
  saveBranchPosSettings,
  updatePrinter,
} from './posSetup';

afterEach(() => vi.unstubAllGlobals());

describe('kitchen stations', () => {
  it('lists the stations of a branch with the kinds, creates one and keeps the refusal of a station in use', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: [{ value: 'osh', label: 'Osh' }], stations: [{ id: 1, name: 'Osh sexi', kind: 'osh' }] }),
      'POST /api/v1/owner/kitchen-stations': () => ok({ id: 2, name: 'Mangal' }),
      'DELETE /api/v1/owner/kitchen-stations/1': () => json({ success: false, message: "Sexga buyurtma yuborilgan. Uni o'chirmang, faolsizlantiring.", data: { reason: 'station_in_use' } }, 422),
    });
    const api = testApi();
    const list = await fetchKitchenStations(api, 3);
    expect(list.stations).toHaveLength(1);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');
    await createKitchenStation(api, { branch_id: 3, name: 'Mangal', kind: 'mangal', show_on_kds: true });
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, name: 'Mangal', kind: 'mangal', show_on_kds: true });
    const error = (await deleteKitchenStation(api, 1).catch((e: unknown) => e)) as { reason?: string };
    expect(error.reason).toBe('station_in_use');
  });
});

describe('routes', () => {
  it('reads the routes and assigns categories and dishes to a station in bulk, or takes them off', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/kitchen-station-routes': () => ok([{ id: 1, station_id: 1, category_id: 4, category_name: 'Issiq taomlar', product_id: null, product_name: null }]),
      'PUT /api/v1/owner/kitchen-station-routes': () => ok([]),
    });
    const api = testApi();
    expect((await fetchStationRoutes(api, 3))[0]!.category_name).toBe('Issiq taomlar');
    await assignStationRoutes(api, { branch_id: 3, station_id: 1, category_ids: [4, 5], product_ids: [9] });
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, station_id: 1, category_ids: [4, 5], product_ids: [9] });
    await assignStationRoutes(api, { branch_id: 3, station_id: null, product_ids: [9] });
    expect(await calls[2]!.json()).toEqual({ branch_id: 3, station_id: null, product_ids: [9] });
  });
});

describe('printers', () => {
  it('creates a LAN printer and a USB one (a terminal, no address), and edits one', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/printers': () => ok([{ id: 1, name: 'Oshxona', connection: 'lan', address: '192.168.0.50', port: 9100 }]),
      'POST /api/v1/owner/printers': () => ok({ id: 2 }),
      'PATCH /api/v1/owner/printers/2': () => ok({ id: 2 }),
    });
    const api = testApi();
    expect((await fetchPrinters(api, 3))[0]!.address).toBe('192.168.0.50');
    await createPrinter(api, { branch_id: 3, name: 'Kassa', purpose: 'receipt', connection: 'usb', device_id: 'dev-uuid', paper_width: 80 });
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, name: 'Kassa', purpose: 'receipt', connection: 'usb', device_id: 'dev-uuid', paper_width: 80 });
    await updatePrinter(api, 2, { is_active: false, print_as_image: true });
    expect(await calls[2]!.json()).toEqual({ is_active: false, print_as_image: true });
  });

  it('carries the field error of a LAN printer without an address', async () => {
    mockFetch({ 'POST /api/v1/owner/printers': () => json({ success: false, message: 'Xato', errors: { address: ['LAN printer uchun manzil majburiy.'] } }, 422) });
    const error = await createPrinter(testApi(), { branch_id: 3, name: 'X', purpose: 'kitchen', connection: 'lan' }).catch((e: unknown) => e);
    expect(getApiFieldError(error, 'address')).toContain('manzil');
  });
});

describe('branch POS settings', () => {
  it('reads them and saves only the change, an alert switched off with null', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: { mode: 'hybrid', screen_lock_minutes: 5, alerts: {}, holidays: [] } }),
      'PUT /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '04:00', pos_settings: { mode: 'table' } }),
    });
    const api = testApi();
    expect((await fetchBranchPosSettings(api, 3)).business_day_starts_at).toBe('05:00');
    const saved = await saveBranchPosSettings(api, 3, { business_day_starts_at: '04:00', pos_settings: { mode: 'table', alerts: { void_amount: null, open_room_hours: 8 } } });
    expect(saved.business_day_starts_at).toBe('04:00');
    expect(await calls[1]!.json()).toEqual({ business_day_starts_at: '04:00', pos_settings: { mode: 'table', alerts: { void_amount: null, open_room_hours: 8 } } });
  });

  it("reads and saves the cashier's discount limit and the cash-out alert (P3-37)", async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/pos-settings': () =>
        ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: { mode: 'hybrid', screen_lock_minutes: 5, cashier_discount_max_percent: '10.00', alerts: { cash_out_amount: '500000.00' }, holidays: [] } }),
      'PUT /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: { cashier_discount_max_percent: null, alerts: { cash_out_amount: null } } }),
    });
    const api = testApi();
    const settings = await fetchBranchPosSettings(api, 3);
    expect(settings.pos_settings.cashier_discount_max_percent).toBe('10.00');
    expect(settings.pos_settings.alerts.cash_out_amount).toBe('500000.00');
    await saveBranchPosSettings(api, 3, { pos_settings: { cashier_discount_max_percent: null, alerts: { cash_out_amount: null } } });
    expect(await calls[1]!.json()).toEqual({ pos_settings: { cashier_discount_max_percent: null, alerts: { cash_out_amount: null } } });
  });

  it('reads and saves the booking confirmation block key by key (P3-38)', async () => {
    const block = { auto_send: true, sms: false, cancel_policy: null };
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: { mode: 'hybrid', alerts: {}, holidays: [], booking_confirmation: block } }),
      'PUT /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: { alerts: {}, booking_confirmation: { ...block, sms: true, cancel_policy: '24 soat oldin' } } }),
    });
    const api = testApi();
    expect((await fetchBranchPosSettings(api, 3)).pos_settings.booking_confirmation).toEqual(block);
    const saved = await saveBranchPosSettings(api, 3, { pos_settings: { booking_confirmation: { sms: true, cancel_policy: '24 soat oldin' } } });
    expect(saved.pos_settings.booking_confirmation?.sms).toBe(true);
    expect(await calls[1]!.json()).toEqual({ pos_settings: { booking_confirmation: { sms: true, cancel_policy: '24 soat oldin' } } });
  });
});
