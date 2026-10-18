import type { Amount, Decimal } from './api';

// Mirrors App\Enums\StoreStatus.
export type StoreStatus =
  | 'pending'
  | 'under_review'
  | 'active'
  | 'suspended'
  | 'rejected'
  | 'payment_required'
  | 'finance_hold';

// Mirrors App\Enums\BranchStatus.
export type BranchStatus = 'active' | 'inactive' | 'temporarily_closed';

// Branch columns that the public store endpoints select (GET /stores, GET /catalog?store_id=...).
export interface BranchSummary {
  id: number;
  store_id: number;
  name: string;
  latitude: number | null;
  longitude: number | null;
  radius_km: number;
  address: string | null;
  phone: string | null;
}

// A full branch row (auth payload, owner endpoints).
export interface Branch extends BranchSummary {
  open_time: string | null; // "HH:MM:SS"
  close_time: string | null;
  is_active: boolean;
  status: BranchStatus;
  region_id: number | null;
  district_id: number | null;
  created_at: string;
  updated_at: string;
}

// One payment provider of Store.payment_settings. The API strips every secret (secret_key, ...) from it.
export interface PaymentProviderSettings {
  enabled?: boolean;
  merchant_id?: string;
  service_id?: string;
  available?: boolean; // false for a provider the platform cannot process yet (uzum, paynet)
}

// Store.payment_settings (a JSON column). A provider is either a bare boolean (legacy) or a settings object.
export interface PaymentSettings {
  cash?: boolean | PaymentProviderSettings;
  click?: boolean | PaymentProviderSettings;
  payme?: boolean | PaymentProviderSettings;
  uzum?: boolean | PaymentProviderSettings;
  paynet?: boolean | PaymentProviderSettings;
  delivery_fee?: Amount; // a JSON column: whatever the owner's form saved
  delivery_enabled?: boolean;
  pickup_enabled?: boolean;
  working_hours?: { open?: string; close?: string };
  allowed_methods?: string[];
}

export interface StoreRatingAuthor {
  id: number;
  name: string;
}

// A customer review (App\Models\StoreRating); `user` is eager-loaded by the store catalog endpoint.
export interface StoreRating {
  id: number;
  user_id: number;
  store_id: number;
  order_id: number | null;
  rating: number;
  comment: string | null;
  created_at: string;
  updated_at: string;
  user?: StoreRatingAuthor | null;
}

// The store columns the API serializes. Financial and internal columns (debt_balance, wallet_balance, credit_limit,
// commission_rate, tax numbers, ...) and payment secrets are removed by Store::toArray(); the public JSON does not
// carry the store's debt at all, only the derived `is_order_locked`.
export interface StoreBase {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  is_active: boolean;
  status: StoreStatus;
  timezone: string;
  currency: string;
  language: string;
  theme_color: string;
  telegram_bot_username: string | null;
  payment_settings: PaymentSettings | null;
  address: string | null;
  location_url: string | null;
  logo: string | null;
  logo_url: string | null;
  banner: string | null;
  banner_url: string | null;
  description: string | null;
  is_open: boolean;
  open_time: string | null;
  close_time: string | null;
  min_order_amount: Decimal;
  min_order: number; // min_order_amount as a float (an accessor, so a JSON number)
  delivery_fee: number; // payment_settings.delivery_fee as a float (an accessor)
  is_premium: boolean;
  is_featured: boolean;
  is_order_locked: boolean;
  review_count: number;
  rating: Decimal;
  delivery_time?: string; // not sent by the API: screens show a placeholder
  created_at: string;
  updated_at: string;
}

// A store with the relations an endpoint may eager-load (the auth payload carries `branches`).
export interface Store extends StoreBase {
  branches?: Branch[];
  ratings?: StoreRating[];
}

// A store of the public list, GET /stores: only the columns StoreController::index selects. `rating` is not one
// of them, so the UI shows a placeholder for it.
export interface StoreListItem {
  id: number;
  uuid: string;
  name: string;
  address: string | null;
  logo: string | null;
  logo_url: string | null;
  banner: string | null;
  banner_url: string | null;
  description: string | null;
  is_open: boolean;
  is_premium: boolean;
  review_count: number;
  payment_settings: PaymentSettings | null;
  status: StoreStatus;
  open_time: string | null;
  close_time: string | null;
  min_order_amount: Decimal;
  min_order: number;
  delivery_fee: number;
  is_order_locked: boolean;
  branches: BranchSummary[];
  min_distance?: number; // km to the nearest branch, only when the request carried coordinates
  rating?: Decimal; // not sent by this endpoint
  delivery_time?: string; // not sent by this endpoint
}

// What GET /catalog?store_id=... returns as `store`: the store plus its branches and reviews.
export interface StoreCatalogStore extends StoreBase {
  branches: BranchSummary[];
  ratings: StoreRating[];
}

// GET /owner/settings (the owner's own store, settings screen).
export interface OwnerStoreSettings {
  id: number;
  name: string;
  logo: string | null;
  logo_url: string | null;
  banner: string | null;
  banner_url: string | null;
  description: string | null;
  address: string | null;
  location_url: string | null;
  is_open: boolean;
  min_order_amount: number;
  payment_settings: PaymentSettings;
  payment_account_type: string;
  status: StoreStatus;
  is_order_locked: boolean;
  allowed_methods: string[];
}
