import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import {
  createDiningTable,
  createHall,
  createRoomTariff,
  deleteHall,
  deleteHallServiceFee,
  fetchAutoGuestItems,
  fetchHalls,
  fetchRoomTariffs,
  saveFloorLayout,
  saveTariffCalendar,
  updateDiningTable,
  updateHall,
  updateRoomTariff,
} from './floor';
import { saveServiceFee } from './management';

afterEach(() => vi.unstubAllGlobals());

describe('halls and places', () => {
  it('lists the halls of a branch and creates a seasonal zone switched off', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/halls': () => ok([{ id: 1, branch_id: 2, name: 'Asosiy zal', tables: [] }]),
      'POST /api/v1/owner/halls': () => ok({ id: 2, name: 'Yozgi bog' }),
      'PATCH /api/v1/owner/halls/2': () => ok({ id: 2, name: 'Yozgi bog', is_active: true }),
    });
    const api = testApi();
    expect((await fetchHalls(api, 2))[0]!.name).toBe('Asosiy zal');
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('2');
    await createHall(api, { branch_id: 2, name: 'Yozgi bog', is_active: false });
    expect(await calls[1]!.json()).toEqual({ branch_id: 2, name: 'Yozgi bog', is_active: false });
    await updateHall(api, 2, { is_active: true });
    expect(await calls[2]!.json()).toEqual({ is_active: true });
  });

  it('keeps the reason of a hall that is in use', async () => {
    mockFetch({ 'DELETE /api/v1/owner/halls/1': () => json({ success: false, message: "Zalda ochiq buyurtma bor.", data: { reason: 'hall_in_use' } }, 422) });
    const error = (await deleteHall(testApi(), 1).catch((e: unknown) => e)) as { reason?: string };
    expect(error.reason).toBe('hall_in_use');
  });

  it('creates a room with a minimum spend and moves a place', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/dining-tables': () => ok({ id: 5 }),
      'PATCH /api/v1/owner/dining-tables/5': () => ok({ id: 5 }),
    });
    const api = testApi();
    await createDiningTable(api, { hall_id: 1, name: 'Xona 3', kind: 'room', seats: 12, min_spend: 600000, min_spend_mode: 'per_table' });
    expect(await calls[0]!.json()).toMatchObject({ kind: 'room', min_spend: 600000, min_spend_mode: 'per_table' });
    await updateDiningTable(api, 5, { is_active: false });
    expect(await calls[1]!.json()).toEqual({ is_active: false });
  });

  it('saves the whole floor layout of a branch at once', async () => {
    const { calls } = mockFetch({ 'PUT /api/v1/owner/dining-tables/layout': () => ok([{ id: 5 }]) });
    await saveFloorLayout(testApi(), 2, [{ id: 5, hall_id: 1, layout: { x: 2, y: 1, w: 4, h: 3 } }]);
    expect(await calls[0]!.json()).toEqual({ branch_id: 2, tables: [{ id: 5, hall_id: 1, layout: { x: 2, y: 1, w: 4, h: 3 } }] });
  });

  it('sets and removes a hall own service charge', async () => {
    const { calls } = mockFetch({
      'PUT /api/v1/owner/service-fees': () => ok({ branch_id: 2, hall_id: 1, service_charge_percent: '15.00', place_fee_amount: '0.00', place_fee_mode: 'per_person' }),
      'DELETE /api/v1/owner/service-fees/halls/1': () => ok({ hall_id: 1 }),
    });
    const api = testApi();
    await saveServiceFee(api, { hall_id: 1, service_charge_percent: 15, place_fee_amount: 0, place_fee_mode: 'per_person' });
    expect(await calls[0]!.json()).toMatchObject({ hall_id: 1, service_charge_percent: 15 });
    await deleteHallServiceFee(api, 1);
    expect(calls[1]!.method).toBe('DELETE');
  });
});

describe('room tariffs', () => {
  it('reads the tariffs with the calendar, creates one for a kind of place and one for a room', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/room-tariffs': () => ok({ calendar: { branch_id: 2, weekend_days: [6, 7], holidays: ['2026-12-31'] }, tariffs: [{ id: 1, day_type: 'weekend', price: '500000.00' }] }),
      'POST /api/v1/owner/room-tariffs': () => ok({ id: 2 }),
      'PATCH /api/v1/owner/room-tariffs/2': () => ok({ id: 2 }),
    });
    const api = testApi();
    const data = await fetchRoomTariffs(api, 2);
    expect(data.calendar.weekend_days).toEqual([6, 7]);
    await createRoomTariff(api, { branch_id: 2, table_kind: 'room', day_type: 'weekday', price: 200000, valid_from: '2026-11-01', valid_to: null });
    expect(await calls[1]!.json()).toEqual({ branch_id: 2, table_kind: 'room', day_type: 'weekday', price: 200000, valid_from: '2026-11-01', valid_to: null });
    await updateRoomTariff(api, 2, { price: 0 });
    expect(await calls[2]!.json()).toEqual({ price: 0 });
  });

  it('replaces the calendar', async () => {
    const { calls } = mockFetch({ 'PUT /api/v1/owner/room-tariffs/calendar': () => ok({ branch_id: 2, weekend_days: [5, 6, 7], holidays: [] }) });
    await saveTariffCalendar(testApi(), 2, { weekend_days: [5, 6, 7], holidays: [] });
    expect(await calls[0]!.json()).toEqual({ branch_id: 2, weekend_days: [5, 6, 7], holidays: [] });
  });
});

describe('per-guest items', () => {
  it('lists them for a branch', async () => {
    mockFetch({ 'GET /api/v1/owner/auto-guest-items': () => ok([{ id: 1, product_name: 'Choy', quantity_per_guest: 1, hall_id: null, is_active: true }]) });
    const [item] = await fetchAutoGuestItems(testApi(), 2);
    expect(item!.product_name).toBe('Choy');
  });
});
