import type { Amount, Decimal } from './api';

// GET /superadmin/dashboard. The money figures are PHP/SQL sums, so each is a number or a numeric string.
export interface DashboardStats {
  total_stores: number;
  active_stores: number;
  payment_required_stores: number;
  total_users: number;
  total_gmv: Amount;
  total_commission: Amount;
  monthly_gmv: Amount;
  monthly_commission: Amount;
  last_30_days_gmv: Amount;
  last_30_days_commission: Amount;
  today_gmv: Amount;
  today_orders: number;
  today_commission: Amount;
  pending_payouts: number;
  total_store_debt: Amount;
  total_cashback_balance: Amount;
}

export interface SuperadminStoreOwner {
  name: string;
  phone: string | null;
  invite_link: string | null; // null once the owner has joined
  has_joined: boolean;
}

// A row of GET /superadmin/stores.
export interface SuperadminStore {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  is_active: boolean;
  commission_rate: Decimal;
  branches_count: number;
  created_at: string;
  owner: SuperadminStoreOwner | null;
}

export interface Region {
  id: number;
  name: string;
  is_active?: boolean; // only on GET /locations
}

export interface District extends Region {
  region_id: number;
}

// GET /superadmin/locations
export interface Locations {
  regions: Region[];
  districts: District[];
}

// POST /superadmin/stores. The select inputs keep their ids as strings until the request.
export interface CreateStorePayload {
  name: string;
  phone: string;
  commission_rate: number | string;
  owner_name: string;
  owner_phone: string;
  branch_region_id: string;
  branch_district_id: string;
  branch_address: string;
  branch_phone: string;
  branch_lat: number;
  branch_lng: number;
  branch_radius: number;
  branch_open_time: string;
  branch_close_time: string;
}

export interface CreatedStore {
  store_id: number;
  invite_link: string;
}
