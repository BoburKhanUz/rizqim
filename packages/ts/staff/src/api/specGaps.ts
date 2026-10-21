import type { Branch, Store, User } from '../types';

// Where the generated schema (packages/api-spec/openapi.json, written from the controllers) is looser than, or differs
// from, what the endpoint really sends. These are the only API shapes written by hand in this package; each one names
// the endpoint and what the spec says, so it can be deleted when the backend annotation is fixed.

// GET /owner/employees: the spec says `data: string`; the controller sends the store's staff as users, with the branch
// (id, name) and the invite token of an employee who has not joined yet (OwnerEmployeeController::index).
export type Employee = Omit<User, 'branch'> & {
  branch?: Pick<Branch, 'id' | 'name'> | null;
  telegram_invite_token?: string | null;
};

// The roles an owner can give (App\Enums\UserRole::assignableStaffRoles)
export type AssignableRole = 'manager' | 'cashier' | 'waiter' | 'cook' | 'courier';

// GET /superadmin/locations: the spec shows the models (`name_uz`); the controller selects `name_uz as name`.
export interface LocationItem {
  id: number;
  name: string;
}
export interface DistrictItem extends LocationItem {
  region_id: number;
}
export interface Locations {
  regions: LocationItem[];
  districts: DistrictItem[];
}

// One payment provider of the store's payment_settings. The API strips every secret (secret_key, ...) from it.
export interface PaymentProviderSettings {
  enabled?: boolean;
  merchant_id?: string;
  service_id?: string;
  available?: boolean; // false for a provider the platform cannot process yet (uzum, paynet)
}

// GET /owner/settings -> data.payment_settings: the spec says `string`; it is the store's JSON column. A provider is
// either a bare boolean (legacy data) or a settings object.
export interface OwnerPaymentSettings {
  cash?: boolean | PaymentProviderSettings;
  click?: boolean | PaymentProviderSettings;
  payme?: boolean | PaymentProviderSettings;
  uzum?: boolean | PaymentProviderSettings;
  paynet?: boolean | PaymentProviderSettings;
  delivery_fee?: string | number; // a JSON column: whatever the owner's form saved
  delivery_enabled?: boolean;
  pickup_enabled?: boolean;
  working_hours?: { open?: string; close?: string };
  allowed_methods?: string[];
}

// GET /owner/settings (OwnerSettingsController::index): the owner's own store. The spec has `payment_settings: string` and
// `allowed_methods: unknown[]`.
export interface OwnerSettings {
  id: number;
  name: string;
  logo: string | null;
  logo_url: string | null;
  banner: string | null;
  banner_url: string | null;
  description: string | null;
  phone: string | null;
  address: string | null;
  location_url: string | null;
  is_open: boolean;
  min_order_amount: number;
  payment_settings: OwnerPaymentSettings | null;
  payment_account_type: 'own_account' | 'platform_account';
  status: Store['status'];
  is_order_locked: boolean;
  allowed_methods: string[];
}

// POST /owner/settings: the spec says `payment_settings` is a list of providers; the controller takes the object the
// owner's form saves (and answers 422 on `payment_settings` for a provider that cannot be enabled yet).
export interface OwnerSettingsInput {
  logo: string | null;
  banner: string | null;
  description: string | null;
  address: string | null;
  location_url: string | null;
  is_open: boolean;
  min_order_amount: number;
  payment_settings: {
    delivery_fee: number;
    delivery_enabled: boolean;
    pickup_enabled: boolean;
    working_hours: { open: string; close: string };
    cash: { enabled: boolean };
    click: { enabled: boolean };
    payme: { enabled: boolean };
    uzum: { enabled: boolean };
    paynet: { enabled: boolean };
  };
}
