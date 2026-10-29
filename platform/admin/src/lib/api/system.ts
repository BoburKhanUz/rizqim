'use client';

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Tizim (P4-31, docs/API.md "Tizim"): /admin/system/settings, /payment-providers, /regions, /feature-flags. Everything needs
// system.manage; every change a fresh 2FA code (the shell asks on 403 reauth_required) and a reason. Typed by hand until the
// generated client is regenerated. Secrets never arrive: a secret is `configured` + `last4` only, and is replaced on its own.

// --- Sozlamalar ------------------------------------------------------------------------------------------------------------

export type SettingSource = 'settings' | 'env' | 'default' | null;

export interface SettingItem {
  key: string;
  label: string;
  description: string;
  type: 'select' | 'email' | 'text' | 'secret';
  // false: from the server's configuration (.env), shown only
  editable: boolean;
  secret: boolean;
  // Null for a secret
  value: string | null;
  configured: boolean;
  // A secret's last 4 characters (null when short or unset)
  last4: string | null;
  source: SettingSource;
  default: string | null;
  options: Array<{ value: string; label: string }> | null;
  updated_at: string | null;
}

export interface SettingGroup {
  key: 'sms' | 'telegram' | 'general' | string;
  label: string;
  items: SettingItem[];
}

// --- To'lov tizimlari ------------------------------------------------------------------------------------------------------

export interface ProviderCredential {
  key: string;
  label: string;
  // A key the provider's code reads
  known: boolean;
  configured: boolean;
  last4: string | null;
  // settings: stored here; env: config/services.php (.env); null: nowhere
  source: 'settings' | 'env' | null;
}

export type ProviderMode = 'test' | 'production';

export interface PaymentProviderRow {
  id: number;
  slug: string;
  name: string;
  is_active: boolean;
  mode: ProviderMode;
  // Checkout and the webhooks use it (Payme, Click)
  wired: boolean;
  credentials: ProviderCredential[];
  updated_at: string | null;
}

// --- Viloyatlar va tumanlar ------------------------------------------------------------------------------------------------

export interface DistrictRow {
  id: number;
  region_id: number;
  name: string;
  is_active: boolean;
  sort_order: number;
  usage: { branches: number; addresses: number };
  deletable: boolean;
}

export interface RegionRow {
  id: number;
  name: string;
  is_active: boolean;
  sort_order: number;
  districts_count: number;
  active_districts: number;
  usage: { branches: number; addresses: number; applications: number };
  deletable: boolean;
  districts: DistrictRow[];
}

// --- Funksiyalar -----------------------------------------------------------------------------------------------------------

export interface FeatureFlag {
  name: string;
  label: string;
  description: string;
  default: boolean;
  stores_on: number;
  stores_off: number;
  stores_total: number;
}

export interface FlagStoreRow {
  store: { id: number; name: string; status: string | null };
  // flag name → its value for the store; `stored`: the store has its own value (false: it follows the default)
  flags: Record<string, { active: boolean; stored: boolean }>;
}

export interface FlagStoresPage {
  data: FlagStoreRow[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);
const groupsOf = (raw: unknown): SettingGroup[] => (isRecord(raw) && Array.isArray(raw.groups) ? (raw.groups as SettingGroup[]) : []);

// --- Calls -----------------------------------------------------------------------------------------------------------------

export const getSettings = async (signal?: AbortSignal): Promise<SettingGroup[]> => groupsOf((await call('GET', '/system/settings', { signal })).data);

// Only what changed; an empty value removes the stored one (the .env value or the default applies). Answers every group.
export const updateSettings = async (values: Record<string, string>, reason: string): Promise<SettingGroup[]> => groupsOf((await call('PATCH', '/system/settings', { body: { values, reason } })).data);

export const replaceSecret = async (key: string, value: string, reason: string): Promise<SettingGroup[]> => groupsOf((await call('PUT', `/system/settings/secrets/${key}`, { body: { value, reason } })).data);

export const clearSecret = async (key: string, reason: string): Promise<SettingGroup[]> => groupsOf((await call('DELETE', `/system/settings/secrets/${key}`, { body: { reason } })).data);

export const getPaymentProviders = async (signal?: AbortSignal): Promise<PaymentProviderRow[]> => {
  const raw = (await call('GET', '/system/payment-providers', { signal })).data;
  return Array.isArray(raw) ? (raw as PaymentProviderRow[]) : [];
};

export const updatePaymentProvider = async (slug: string, change: { is_active?: boolean; mode?: ProviderMode }, reason: string): Promise<PaymentProviderRow> =>
  (await call('PATCH', `/system/payment-providers/${slug}`, { body: { ...change, reason } })).data as PaymentProviderRow;

export const replaceCredential = async (slug: string, key: string, value: string, reason: string): Promise<PaymentProviderRow> =>
  (await call('PUT', `/system/payment-providers/${slug}/credentials/${key}`, { body: { value, reason } })).data as PaymentProviderRow;

export const clearCredential = async (slug: string, key: string, reason: string): Promise<PaymentProviderRow> =>
  (await call('DELETE', `/system/payment-providers/${slug}/credentials/${key}`, { body: { reason } })).data as PaymentProviderRow;

export const getRegions = async (signal?: AbortSignal): Promise<RegionRow[]> => {
  const raw = (await call('GET', '/system/regions', { signal })).data;
  return Array.isArray(raw) ? (raw as RegionRow[]) : [];
};

export const createRegion = async (name: string, isActive: boolean, reason: string): Promise<RegionRow> => (await call('POST', '/system/regions', { body: { name, is_active: isActive, reason } })).data as RegionRow;

export const updateRegion = async (id: number, change: { name?: string; is_active?: boolean }, reason: string): Promise<RegionRow> => (await call('PATCH', `/system/regions/${id}`, { body: { ...change, reason } })).data as RegionRow;

export const deleteRegion = async (id: number, reason: string): Promise<void> => {
  await call('DELETE', `/system/regions/${id}`, { body: { reason } });
};

// The full list of ids in the new order
export const orderRegions = async (ids: number[], reason: string): Promise<RegionRow[]> => {
  const raw = (await call('PUT', '/system/regions/order', { body: { ids, reason } })).data;
  return Array.isArray(raw) ? (raw as RegionRow[]) : [];
};

export const createDistrict = async (regionId: number, name: string, isActive: boolean, reason: string): Promise<DistrictRow> =>
  (await call('POST', `/system/regions/${regionId}/districts`, { body: { name, is_active: isActive, reason } })).data as DistrictRow;

export const updateDistrict = async (id: number, change: { name?: string; is_active?: boolean; region_id?: number }, reason: string): Promise<DistrictRow> =>
  (await call('PATCH', `/system/districts/${id}`, { body: { ...change, reason } })).data as DistrictRow;

export const deleteDistrict = async (id: number, reason: string): Promise<void> => {
  await call('DELETE', `/system/districts/${id}`, { body: { reason } });
};

export const orderDistricts = async (regionId: number, ids: number[], reason: string): Promise<RegionRow> => (await call('PUT', `/system/regions/${regionId}/districts/order`, { body: { ids, reason } })).data as RegionRow;

export const getFlags = async (signal?: AbortSignal): Promise<FeatureFlag[]> => {
  const raw = (await call('GET', '/system/feature-flags', { signal })).data;
  return isRecord(raw) && Array.isArray(raw.flags) ? (raw.flags as FeatureFlag[]) : [];
};

export async function getFlagStores(q: string, page: number, signal?: AbortSignal): Promise<FlagStoresPage> {
  const raw = (await call('GET', '/system/feature-flags/stores', { query: { q: q.trim().length >= 2 ? q.trim() : undefined, page, per_page: 30 }, signal })).data;
  const answer = isRecord(raw) ? raw : {};
  return {
    data: Array.isArray(answer.data) ? (answer.data as FlagStoreRow[]) : [],
    current_page: Number(answer.current_page ?? 1),
    last_page: Number(answer.last_page ?? 1),
    per_page: Number(answer.per_page ?? 30),
    total: Number(answer.total ?? 0),
  };
}

export const setFlag = async (flag: string, storeId: number, active: boolean, reason: string): Promise<FlagStoreRow> =>
  (await call('PUT', `/system/feature-flags/${flag}/stores/${storeId}`, { body: { active, reason } })).data as FlagStoreRow;

export const resetFlags = async (storeId: number, reason: string): Promise<FlagStoreRow> => (await call('DELETE', `/system/feature-flags/stores/${storeId}`, { body: { reason } })).data as FlagStoreRow;

// --- Hooks -----------------------------------------------------------------------------------------------------------------

export const SYSTEM_KEYS = {
  settings: ['admin-system-settings'] as const,
  providers: ['admin-system-providers'] as const,
  regions: ['admin-system-regions'] as const,
  flags: ['admin-system-flags'] as const,
  flagStores: (q: string, page: number) => ['admin-system-flag-stores', q, page] as const,
  allFlagStores: ['admin-system-flag-stores'] as const,
};

export const useSettings = (enabled = true) => useQuery({ queryKey: SYSTEM_KEYS.settings, queryFn: ({ signal }) => getSettings(signal), enabled });
export const usePaymentProviders = (enabled = true) => useQuery({ queryKey: SYSTEM_KEYS.providers, queryFn: ({ signal }) => getPaymentProviders(signal), enabled });
export const useRegions = (enabled = true) => useQuery({ queryKey: SYSTEM_KEYS.regions, queryFn: ({ signal }) => getRegions(signal), enabled });
export const useFlags = (enabled = true) => useQuery({ queryKey: SYSTEM_KEYS.flags, queryFn: ({ signal }) => getFlags(signal), enabled });
export const useFlagStores = (q: string, page: number, enabled = true) =>
  useQuery({ queryKey: SYSTEM_KEYS.flagStores(q, page), queryFn: ({ signal }) => getFlagStores(q, page, signal), enabled, placeholderData: keepPreviousData });

// A settings change answers every group: the cache takes the answer as it is
export function useSettingsAction<V>(action: (vars: V) => Promise<SettingGroup[]>) {
  const client = useQueryClient();
  return useMutation({ mutationFn: action, onSuccess: (groups) => client.setQueryData(SYSTEM_KEYS.settings, groups) });
}

export function useProviderAction<V>(action: (vars: V) => Promise<PaymentProviderRow>) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: action,
    onSuccess: (row) => client.setQueryData<PaymentProviderRow[]>(SYSTEM_KEYS.providers, (rows) => (rows ?? []).map((existing) => (existing.slug === row.slug ? row : existing))),
  });
}

// Regions and districts: every change asks the list again (usage counts and the order come from the server)
export function useLocationAction<V, R>(action: (vars: V) => Promise<R>) {
  const client = useQueryClient();
  return useMutation({ mutationFn: action, onSuccess: () => client.invalidateQueries({ queryKey: SYSTEM_KEYS.regions }) });
}

export function useFlagAction<V>(action: (vars: V) => Promise<FlagStoreRow>) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: action,
    onSuccess: () => Promise.all([client.invalidateQueries({ queryKey: SYSTEM_KEYS.allFlagStores }), client.invalidateQueries({ queryKey: SYSTEM_KEYS.flags })]),
  });
}
