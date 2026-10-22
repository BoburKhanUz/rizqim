import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { MenuBranch } from './menuAdmin';
import type { OwnerSettings, OwnerSettingsInput } from './settings';
import type { JsonRequest, OkData } from './types';

// The management screens of the partner web (P2-23): staff, branches and working hours, delivery zones, store settings,
// service fees and terminals. docs/API.md section 12 maps each screen to its endpoints. Like the rest of this package the
// functions only call the API: who may do what, which polygon is valid and why a branch cannot be deleted are the
// backend's answers (403/422 with an Uzbek `message`), shown by the screen as they come.

// --- Staff (GET/POST /owner/staff, PATCH /owner/staff/{id}) ----------------------------------------------------------------

export type StaffMember = OkData<'staff.index'>[number];
export type CreatedStaff = OkData<'staff.store', 201>;
export type StaffCreateInput = JsonRequest<'staff.store'>;
export type StaffPatchInput = JsonRequest<'staff.update'>;

// The staff the signed-in person manages (the owner: everybody; a branch manager: the ranks below in their branch).
// `can_edit` says whether this very person may change this member; `login_phone_enabled` whether the phone signs in by SMS.
export async function fetchManagedStaff(api: StaffApi): Promise<StaffMember[]> {
  const body = unwrap(await api.client.GET('/v1/owner/staff'));
  return body.data ?? [];
}

// 201: the member and the Telegram invite link (shown once; the list does not carry it).
export async function createManagedStaff(api: StaffApi, input: StaffCreateInput): Promise<CreatedStaff> {
  const body = unwrap(await api.client.POST('/v1/owner/staff', { body: input }));
  return body.data as CreatedStaff;
}

// Any subset of name, branch_id, phone. A phone the person proved by SMS themselves is refused with 422 `errors.phone`.
export async function updateManagedStaff(api: StaffApi, id: number, input: StaffPatchInput): Promise<StaffMember> {
  const body = unwrap(await api.client.PATCH('/v1/owner/staff/{id}', { params: { path: { id } }, body: input }));
  return body.data as StaffMember;
}

// --- Branches (/owner/branches) --------------------------------------------------------------------------------------------

// The list itself (GET /owner/branches) is fetchMenuBranches of api/menuAdmin.ts (P2-22): one function, one cache entry.
export type ManagedBranch = MenuBranch;
export type BranchCreateInput = JsonRequest<'branch.store'>;
export type BranchPatchInput = JsonRequest<'branch.update'>;

export async function createBranch(api: StaffApi, input: BranchCreateInput): Promise<ManagedBranch> {
  const body = unwrap(await api.client.POST('/v1/owner/branches', { body: input }));
  return body.data as ManagedBranch;
}

// open_time and close_time go together; both null = open all day. 422 on a bad value.
export async function updateBranch(api: StaffApi, id: number, input: BranchPatchInput): Promise<ManagedBranch> {
  const body = unwrap(await api.client.PATCH('/v1/owner/branches/{id}', { params: { path: { id } }, body: input }));
  return body.data as ManagedBranch;
}

// 422 `data.reason = branch_in_use` when the branch has orders or is the store's only one: the message says it.
export async function deleteBranch(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/branches/{id}', { params: { path: { id } } }));
}

export type LocationsList = OkData<'location.index'>;

// GET /locations: regions and districts for the branch form (open to every signed-in person, unlike /superadmin/locations).
export async function fetchRegionsAndDistricts(api: StaffApi): Promise<LocationsList> {
  const body = unwrap(await api.client.GET('/v1/locations'));
  return body.data;
}

// --- Delivery zones (/owner/delivery-zones, owner only) ---------------------------------------------------------------------

export type DeliveryZone = OkData<'deliveryZoneManage.index'>[number];

// GeoJSON as the backend takes it: `Polygon` or `MultiPolygon`, coordinates [longitude, latitude]. (The spec says
// `coordinates: list<unknown>`.)
export type ZonePolygon =
  | { type: 'Polygon'; coordinates: number[][][] }
  | { type: 'MultiPolygon'; coordinates: number[][][][] };

// The spec types `polygon` as an object or a string and every number as `number`; amounts leave as numbers, are read as strings.
export interface ZoneInput {
  name: string;
  polygon: ZonePolygon;
  branch_id?: number | null;
  priority?: number;
  active?: boolean;
  delivery_price?: number;
  min_order_amount?: number;
  free_delivery_from?: number | null;
  eta_minutes?: number | null;
}

export async function fetchDeliveryZones(api: StaffApi): Promise<DeliveryZone[]> {
  const body = unwrap(await api.client.GET('/v1/owner/delivery-zones'));
  return body.data ?? [];
}

// 422 `errors.polygon` carries the reason (unclosed ring, under three points, too many points ...) in Uzbek.
export async function createDeliveryZone(api: StaffApi, input: ZoneInput): Promise<DeliveryZone> {
  const body = unwrap(await api.client.POST('/v1/owner/delivery-zones', { body: input as JsonRequest<'deliveryZoneManage.store'> }));
  return body.data as DeliveryZone;
}

export async function updateDeliveryZone(api: StaffApi, id: number, input: Partial<ZoneInput>): Promise<DeliveryZone> {
  const body = unwrap(await api.client.PATCH('/v1/owner/delivery-zones/{id}', { params: { path: { id } }, body: input as JsonRequest<'deliveryZoneManage.update'> }));
  return body.data as DeliveryZone;
}

export async function deleteDeliveryZone(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/delivery-zones/{id}', { params: { path: { id } } }));
}

// --- Store settings (/owner/settings) --------------------------------------------------------------------------------------

// GET /owner/settings (with the store's `timezone`)
export type StoreSettings = OwnerSettings;

// POST /owner/settings overwrites logo, banner, description, address, location_url, is_open and min_order_amount with what
// is sent (an omitted one becomes empty): a screen sends back every stored value next to the one it changed. `name` and
// `timezone` are kept when left out; `phone` too.
export type StoreSettingsInput = OwnerSettingsInput;

export async function fetchStoreSettings(api: StaffApi): Promise<StoreSettings | null> {
  const body = unwrap(await api.client.GET('/v1/owner/settings'));
  return body.data ?? null;
}

// 422: `errors.name`, `errors.timezone`, `errors.payment_settings` (a provider that cannot be enabled yet).
export async function saveStoreSettings(api: StaffApi, input: StoreSettingsInput): Promise<void> {
  unwrap(await api.client.POST('/v1/owner/settings', { body: input }));
}

// --- Service charge and place fee (/owner/service-fees) --------------------------------------------------------------------

export type PlaceFeeMode = 'per_person' | 'per_table';

// The spec leaves the channel lists as bare arrays and the mode as a string. Percent and amount come as decimal strings;
// a null channel list means the default (`["pos_hall"]`); `branch_id` null is the store's default row.
export interface ServiceFeeRow {
  branch_id: number | null;
  // P3-12: a hall's own row (null or missing: the branch's or the store's row)
  hall_id?: number | null;
  service_charge_percent: string;
  service_charge_channels: string[] | null;
  place_fee_amount: string;
  place_fee_mode: PlaceFeeMode;
  place_fee_channels: string[] | null;
}

export interface ServiceFees {
  // Every order channel the backend knows (telegram never carries a fee and is not accepted)
  channels: string[];
  settings: ServiceFeeRow[];
}

export interface ServiceFeeInput {
  branch_id?: number | null;
  // A hall's own settings (its branch is the hall's)
  hall_id?: number | null;
  service_charge_percent: number;
  service_charge_channels?: string[] | null;
  place_fee_amount: number;
  place_fee_mode: PlaceFeeMode;
  place_fee_channels?: string[] | null;
}

export async function fetchServiceFees(api: StaffApi): Promise<ServiceFees> {
  const body = unwrap(await api.client.GET('/v1/owner/service-fees'));
  const data = body.data as unknown as Partial<ServiceFees> | null | undefined;
  return { channels: data?.channels ?? [], settings: data?.settings ?? [] };
}

// PUT: the store's default (branch_id null) or one branch's override. 422: percent over 30, unknown channel ...
export async function saveServiceFee(api: StaffApi, input: ServiceFeeInput): Promise<ServiceFeeRow> {
  const body = unwrap(await api.client.PUT('/v1/owner/service-fees', { body: input as never }));
  return body.data as unknown as ServiceFeeRow;
}

// --- Terminals (/owner/devices) --------------------------------------------------------------------------------------------

export type StoreDevice = OkData<'storeDevice.index'>[number];
export type PairingCodeInput = JsonRequest<'storeDevice.createPairingCode'>;
export type PairingCode = OkData<'storeDevice.createPairingCode', 201>;

// The store's terminals and apps; revoked ones only with `includeRevoked`.
export async function fetchStoreDevices(api: StaffApi, includeRevoked = false): Promise<StoreDevice[]> {
  const body = unwrap(await api.client.GET('/v1/owner/devices', { params: { query: { include_revoked: includeRevoked } } }));
  return body.data ?? [];
}

export async function renameStoreDevice(api: StaffApi, uuid: string, name: string): Promise<StoreDevice> {
  const body = unwrap(await api.client.PATCH('/v1/owner/devices/{uuid}', { params: { path: { uuid } }, body: { name } }));
  return body.data as StoreDevice;
}

// The device loses its token at once.
export async function revokeStoreDevice(api: StaffApi, uuid: string): Promise<StoreDevice> {
  const body = unwrap(await api.client.POST('/v1/owner/devices/{uuid}/revoke', { params: { path: { uuid } } }));
  return body.data as StoreDevice;
}

// A one-time code (ABCD-EFGH, `expires_at`) a POS or kitchen display types in to join this store.
export async function createPairingCode(api: StaffApi, input: PairingCodeInput): Promise<PairingCode> {
  const body = unwrap(await api.client.POST('/v1/owner/devices/pairing-codes', { body: input }));
  // The spec also lists a bare `string` for the 200; the endpoint answers 201 with the code object
  return (body as { data: PairingCode }).data;
}
