import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// Halls, places (tables, rooms, tapchans), the floor layout, room tariffs, the weekend/holiday calendar and the per-guest
// ("choy-non") items (P3-12; docs/API.md "Zal va xona hisobi"). Everything the guest pays - the room fee, the minimum
// spend, the service charge - is computed by the server from these settings; the panel only edits them. Changing halls and
// places needs `tables.manage` AND the owner or manager role, tariffs and guest items `settings.manage`.

// --- Halls and places ---------------------------------------------------------------------------------------------------------

export type Hall = OkData<'hall.index'>[number];
export type DiningTable = Hall['tables'][number];
export type HallInput = JsonRequest<'hall.store'>;
export type HallPatch = NonNullable<JsonRequest<'hall.update'>>;

export const TABLE_KINDS = ['table', 'room', 'tapchan'] as const;
export type TableKind = (typeof TABLE_KINDS)[number];
export const MIN_SPEND_MODES = ['per_table', 'per_person'] as const;
export type MinSpendMode = (typeof MIN_SPEND_MODES)[number];

// GET /owner/halls: the halls of a branch in order, each with its own service fee (null: the branch's) and its places
export async function fetchHalls(api: StaffApi, branchId?: number): Promise<Hall[]> {
  const body = unwrap(await api.client.GET('/v1/owner/halls', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

// `is_active: false` is a seasonal zone switched off: it leaves the POS map, open orders stay.
export async function createHall(api: StaffApi, input: HallInput): Promise<Hall> {
  return unwrap(await api.client.POST('/v1/owner/halls', { body: input })).data;
}

export async function updateHall(api: StaffApi, id: number, patch: HallPatch): Promise<Hall> {
  return unwrap(await api.client.PATCH('/v1/owner/halls/{id}', { params: { path: { id: String(id) } }, body: patch })).data;
}

// 422 `hall_in_use` while the hall has open orders
export async function deleteHall(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/halls/{id}', { params: { path: { id: String(id) } } }));
}

export type DiningTableInput = JsonRequest<'diningTable.store'>;
export type DiningTablePatch = Partial<DiningTableInput>;

export async function createDiningTable(api: StaffApi, input: DiningTableInput): Promise<DiningTable> {
  return unwrap(await api.client.POST('/v1/owner/dining-tables', { body: input })).data;
}

export async function updateDiningTable(api: StaffApi, id: number, patch: DiningTablePatch): Promise<DiningTable> {
  return unwrap(await api.client.PATCH('/v1/owner/dining-tables/{id}', { params: { path: { id: String(id) } }, body: patch as never })).data as DiningTable;
}

// 422 `table_in_use` while the place has an open order
export async function deleteDiningTable(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/dining-tables/{id}', { params: { path: { id: String(id) } } }));
}

// A place on the floor map in the map's own units (the POS scales the map to its screen). The server keeps two decimals.
export interface FloorRect {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface FloorLayoutRow {
  id: number;
  hall_id?: number;
  sort_order?: number;
  layout: FloorRect;
}

// PUT /owner/dining-tables/layout: the whole map of one branch at once, all or nothing (an id of another branch is a 422
// and nothing is saved). Answers every place of the branch.
export async function saveFloorLayout(api: StaffApi, branchId: number, tables: FloorLayoutRow[]): Promise<DiningTable[]> {
  const body = unwrap(await api.client.PUT('/v1/owner/dining-tables/layout', { body: { branch_id: branchId, tables } }));
  return body.data ?? [];
}

// --- Room tariffs --------------------------------------------------------------------------------------------------------------

export type RoomTariffs = OkData<'roomTariff.index'>;
export type RoomTariff = RoomTariffs['tariffs'][number];
export type TariffCalendar = RoomTariffs['calendar'];
export type RoomTariffInput = JsonRequest<'roomTariff.store'>;
export type RoomTariffPatch = NonNullable<JsonRequest<'roomTariff.update'>>;

export const DAY_TYPES = ['weekday', 'weekend', 'holiday'] as const;
export type DayType = (typeof DAY_TYPES)[number];

// GET /owner/room-tariffs: the branch's tariffs and its calendar. A tariff is for ONE room (`dining_table_id`) or for a kind
// of place (`table_kind`, optionally in one hall), on a day type, for a date range (null: always). Price 0 is a free room.
export async function fetchRoomTariffs(api: StaffApi, branchId?: number): Promise<RoomTariffs> {
  return unwrap(await api.client.GET('/v1/owner/room-tariffs', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } })).data;
}

export async function createRoomTariff(api: StaffApi, input: RoomTariffInput): Promise<RoomTariff> {
  return unwrap(await api.client.POST('/v1/owner/room-tariffs', { body: input })).data;
}

export async function updateRoomTariff(api: StaffApi, id: number, patch: RoomTariffPatch): Promise<RoomTariff> {
  return unwrap(await api.client.PATCH('/v1/owner/room-tariffs/{id}', { params: { path: { id: String(id) } }, body: patch })).data;
}

export async function deleteRoomTariff(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/room-tariffs/{id}', { params: { path: { id: String(id) } } }));
}

// PUT /owner/room-tariffs/calendar: weekend days as ISO weekdays (1 Monday ... 7 Sunday; default 6 and 7) and the holidays
// (YYYY-MM-DD). The list REPLACES the stored one.
export async function saveTariffCalendar(api: StaffApi, branchId: number, calendar: { weekend_days: number[]; holidays: string[] }): Promise<TariffCalendar> {
  return unwrap(await api.client.PUT('/v1/owner/room-tariffs/calendar', { body: { branch_id: branchId, ...calendar } })).data;
}

// --- Per-guest items ("choy-non") --------------------------------------------------------------------------------------------

export type AutoGuestItem = OkData<'autoGuestItem.index'>[number];
export type AutoGuestItemInput = JsonRequest<'autoGuestItem.store'>;
export type AutoGuestItemPatch = NonNullable<JsonRequest<'autoGuestItem.update'>>;

// GET /owner/auto-guest-items: what the POS adds to a room's order for every guest (tea, bread). `hall_id` null: every hall.
export async function fetchAutoGuestItems(api: StaffApi, branchId?: number): Promise<AutoGuestItem[]> {
  const body = unwrap(await api.client.GET('/v1/owner/auto-guest-items', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

export async function createAutoGuestItem(api: StaffApi, input: AutoGuestItemInput): Promise<AutoGuestItem> {
  return unwrap(await api.client.POST('/v1/owner/auto-guest-items', { body: input })).data;
}

export async function updateAutoGuestItem(api: StaffApi, id: number, patch: AutoGuestItemPatch): Promise<AutoGuestItem> {
  return unwrap(await api.client.PATCH('/v1/owner/auto-guest-items/{id}', { params: { path: { id: String(id) } }, body: patch })).data;
}

export async function deleteAutoGuestItem(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/auto-guest-items/{id}', { params: { path: { id: String(id) } } }));
}

// --- Service charge of one hall ----------------------------------------------------------------------------------------------
// PUT /owner/service-fees with `hall_id` (saveServiceFee of api/management.ts) sets a hall's own service charge and place
// fee (VIP 15%, a self-service zone 0%); this removes it so the branch's apply again.
export async function deleteHallServiceFee(api: StaffApi, hallId: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/service-fees/halls/{id}', { params: { path: { id: String(hallId) } } }));
}
