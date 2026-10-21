import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// The platform's own screens (super admin only; the backend answers 403 to anyone else).

// GET /superadmin/dashboard: counts and money figures (numbers, UZS).
export type DashboardStats = OkData<'superadmin.dashboard'>;

export async function fetchSuperadminDashboard(api: StaffApi): Promise<DashboardStats> {
  const body = unwrap(await api.client.GET('/v1/superadmin/dashboard'));
  return body.data;
}

// GET /superadmin/stores: every store with its owner (`invite_link` is null once the owner has joined).
export type SuperadminStore = OkData<'superadmin.stores'>[number];

export async function fetchSuperadminStores(api: StaffApi): Promise<SuperadminStore[]> {
  const body = unwrap(await api.client.GET('/v1/superadmin/stores'));
  return body.data ?? [];
}

// GET /superadmin/locations: regions and districts (their Uzbek names) for the forms.
export type Locations = OkData<'superadmin.getLocations'>;
export type LocationItem = Locations['regions'][number];
export type DistrictItem = Locations['districts'][number];

export async function fetchLocations(api: StaffApi): Promise<Locations> {
  const body = unwrap(await api.client.GET('/v1/superadmin/locations'));
  return body.data;
}

// The form keeps its select inputs' ids and the commission as strings; Laravel's integer / numeric rules take both.
type SpecCreateStore = JsonRequest<'superadmin.createStore'>;
export type CreateStoreInput = Omit<SpecCreateStore, 'branch_region_id' | 'branch_district_id' | 'commission_rate'> & {
  branch_region_id: number | string;
  branch_district_id: number | string;
  commission_rate: number | string;
};
export type CreatedStore = OkData<'superadmin.createStore'>;

// POST /superadmin/stores: a store, its first branch and the owner's invite link.
export async function createStore(api: StaffApi, input: CreateStoreInput): Promise<CreatedStore> {
  const body = unwrap(await api.client.POST('/v1/superadmin/stores', { body: input as SpecCreateStore }));
  return body.data;
}
