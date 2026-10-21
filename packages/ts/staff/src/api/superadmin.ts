import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { Locations } from './specGaps';
import type { Amount } from '@rizqim/ui';
import type { JsonRequest, OkData } from './types';

// The platform's own screens (super admin only; the backend answers 403 to anyone else).

// The spec types the two sums the controller does not cast (`total_store_debt`, `total_cashback_balance`) as `unknown`;
// they are numbers or numeric strings like the other money figures.
export type DashboardStats = Omit<OkData<'superadmin.dashboard'>, 'total_store_debt' | 'total_cashback_balance'> & {
  total_store_debt: Amount;
  total_cashback_balance: Amount;
};

// GET /superadmin/dashboard: the money figures are PHP/SQL sums, so each is a number or a numeric string.
export async function fetchSuperadminDashboard(api: StaffApi): Promise<DashboardStats> {
  const body = unwrap(await api.client.GET('/v1/superadmin/dashboard'));
  return body.data as DashboardStats;
}

// The spec has `owner.invite_link: string`; it is null once the owner has joined (SuperadminController::stores).
type SpecStore = OkData<'superadmin.stores'>[number];
export type SuperadminStore = Omit<SpecStore, 'owner'> & {
  owner: (Omit<NonNullable<SpecStore['owner']>, 'invite_link'> & { invite_link: string | null }) | null;
};

export async function fetchSuperadminStores(api: StaffApi): Promise<SuperadminStore[]> {
  const body = unwrap(await api.client.GET('/v1/superadmin/stores'));
  return (body.data ?? []) as SuperadminStore[];
}

// GET /superadmin/locations: regions and districts for the forms (the spec shows `name_uz`: api/specGaps.ts).
export async function fetchLocations(api: StaffApi): Promise<Locations> {
  const body = unwrap(await api.client.GET('/v1/superadmin/locations'));
  return body.data as unknown as Locations;
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
