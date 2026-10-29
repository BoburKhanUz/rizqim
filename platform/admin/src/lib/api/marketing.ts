'use client';

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BACKEND_PREFIX, call } from './client';

// Marketing (P4-31, docs/API.md "Marketing"): banners and promotions, promo codes with their usage numbers, categories. Typed by
// hand until the generated client is regenerated (the shapes follow AdminMarketing*Controller). Money is a decimal string
// ("5000.00"), times are ISO 8601 (Asia/Tashkent). Every change carries a `reason`; deleting also needs the step-up (the shell
// asks for it on a 403 reauth_required). Files go as multipart/form-data (a FormData body), flags as "1"/"0".

export interface Named {
  id: number;
  name: string;
}

// --- Banners and promotions -------------------------------------------------------------------------------------------

export const BANNER_STATUSES = ['live', 'scheduled', 'ended', 'inactive'] as const;
export type BannerStatus = (typeof BANNER_STATUSES)[number];

export interface Banner {
  id: number;
  title: string;
  image_url: string | null;
  // The admin path of the image (below /api/v1/admin), shown with imageSrc()
  image_preview: string | null;
  store: Named | null;
  product: (Named & { store_id: number }) | null;
  is_active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  sort_order: number;
  status: BannerStatus;
  status_label: string;
  created_at: string | null;
  updated_at: string | null;
}

export interface BannerList {
  data: Banner[];
  statuses: Record<string, string>;
}

// What the Mini App's home screen lists right now
export interface PreviewBanner {
  id: number;
  title: string;
  image_url: string | null;
  image_preview: string | null;
  store_uuid: string | null;
  product_id: number | null;
}

export interface BannerInput {
  title?: string;
  // A new picture (required for a new banner)
  image?: File | null;
  store_id?: number | null;
  product_id?: number | null;
  is_active?: boolean;
  // ISO 8601 with its offset, or null to clear
  starts_at?: string | null;
  ends_at?: string | null;
  reason: string;
}

// --- Promo codes -------------------------------------------------------------------------------------------------------------

export const CODE_STATUSES = ['active', 'inactive', 'expired', 'exhausted'] as const;
export type CodeStatus = (typeof CODE_STATUSES)[number];
export type CodeType = 'percentage' | 'fixed';

export interface PromoCode {
  id: number;
  code: string;
  type: CodeType;
  type_label: string;
  // A percent for `percentage`, so'm for `fixed`
  value: string;
  min_order_amount: string;
  max_discount_amount: string | null;
  usage_limit: number | null;
  per_user_limit: number | null;
  store: Named | null;
  is_active: boolean;
  expires_at: string | null;
  status: CodeStatus;
  status_label: string;
  stats: { uses: number; unique_customers: number; discount_total: string; gmv: string };
  // Some order carries the code: what it gives can no longer change
  locked: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface PromoCodeCard extends PromoCode {
  recent_orders: Array<{ uuid: string; status: string; discount: string; total: string; created_at: string | null }>;
}

export interface PromoCodePage {
  data: PromoCode[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  statuses: Record<string, string>;
  types: Record<string, string>;
}

export interface CodeFilters {
  q: string;
  status: CodeStatus | '';
  page: number;
}

export const DEFAULT_CODE_FILTERS: CodeFilters = { q: '', status: '', page: 1 };

export interface CodeInput {
  code?: string;
  type?: CodeType;
  value?: string;
  min_order_amount?: string | null;
  max_discount_amount?: string | null;
  usage_limit?: number | null;
  per_user_limit?: number | null;
  store_id?: number | null;
  is_active?: boolean;
  expires_at?: string | null;
  reason: string;
}

// --- Categories --------------------------------------------------------------------------------------------------------------

export interface Category {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  parent: Named | null;
  image_url: string | null;
  image_preview: string | null;
  sort_order: number;
  is_active: boolean;
  products_count: number;
  children_count: number;
  updated_at: string | null;
}

export interface CategoryInput {
  name?: string;
  parent_id?: number | null;
  is_active?: boolean;
  image?: File | null;
  remove_image?: boolean;
  reason: string;
}

// --- Pickers -----------------------------------------------------------------------------------------------------------------

export interface Lookup {
  stores: Named[];
  products: Array<Named & { store_id: number }>;
}

// --- Calls -------------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;
const compact = (filters: object): Record<string, string | number> => {
  const query: Record<string, string | number> = {};
  for (const [name, value] of Object.entries(filters)) if (value !== '' && value !== null && value !== undefined) query[name] = value as string | number;
  return query;
};

// A path the backend gave (image_preview) as the address an <img> loads through the proxy (same origin, the session cookie)
export const imageSrc = (path: string | null): string | undefined => (path ? `${BACKEND_PREFIX}${path}` : undefined);

// Fields of a change as multipart/form-data: a flag is "1"/"0", an empty value clears (Laravel reads "" as null)
function form(fields: Record<string, string | number | boolean | File | null | undefined>): FormData {
  const body = new FormData();
  for (const [name, value] of Object.entries(fields)) {
    if (value === undefined) continue;
    if (value === null) body.set(name, '');
    else if (typeof value === 'boolean') body.set(name, value ? '1' : '0');
    else if (typeof value === 'number') body.set(name, String(value));
    else body.set(name, value);
  }
  return body;
}

const bannerBody = (input: BannerInput) => {
  const { image, ...rest } = input;
  return image ? form({ ...rest, image }) : rest;
};

export const listBanners = (status: BannerStatus | '', signal?: AbortSignal) => data<BannerList>(call('GET', '/marketing/promos', { query: compact({ status }), signal }));
export const previewBanners = (signal?: AbortSignal) => data<{ data: PreviewBanner[] }>(call('GET', '/marketing/promos/preview', { signal }));
export const createBanner = (input: BannerInput & { title: string; image: File }) => data<Banner>(call('POST', '/marketing/promos', { body: form({ ...input }) }));
export const updateBanner = (id: number, input: BannerInput) => data<Banner>(call('POST', `/marketing/promos/${id}`, { body: bannerBody(input) }));
export const orderBanners = (ids: number[], reason: string) => data<BannerList>(call('PUT', '/marketing/promos/order', { body: { ids, reason } }));
export const deleteBanner = (id: number, reason: string) => data<{ id: number }>(call('DELETE', `/marketing/promos/${id}`, { body: { reason } }));

export const listCodes = (filters: CodeFilters, signal?: AbortSignal) => data<PromoCodePage>(call('GET', '/marketing/promo-codes', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getCode = (id: number, signal?: AbortSignal) => data<PromoCodeCard>(call('GET', `/marketing/promo-codes/${id}`, { signal }));
export const createCode = (input: CodeInput & { code: string; type: CodeType; value: string }) => data<PromoCodeCard>(call('POST', '/marketing/promo-codes', { body: input }));
export const updateCode = (id: number, input: CodeInput) => data<PromoCodeCard>(call('PATCH', `/marketing/promo-codes/${id}`, { body: input }));
export const setCodeActive = (id: number, active: boolean, reason: string) => data<PromoCodeCard>(call('POST', `/marketing/promo-codes/${id}/${active ? 'activate' : 'deactivate'}`, { body: { reason } }));
export const deleteCode = (id: number, reason: string) => data<{ id: number }>(call('DELETE', `/marketing/promo-codes/${id}`, { body: { reason } }));

export const listCategories = (signal?: AbortSignal) => data<{ data: Category[] }>(call('GET', '/marketing/categories', { signal }));
export const createCategory = (input: CategoryInput & { name: string }) => {
  const { image, ...rest } = input;
  return data<Category>(call('POST', '/marketing/categories', { body: image ? form({ ...rest, image }) : rest }));
};
export const updateCategory = (id: number, input: CategoryInput) => {
  const { image, ...rest } = input;
  return data<Category>(call('POST', `/marketing/categories/${id}`, { body: image ? form({ ...rest, image }) : rest }));
};
export const orderCategories = (ids: number[], reason: string) => data<{ data: Category[] }>(call('PUT', '/marketing/categories/order', { body: { ids, reason } }));
export const deleteCategory = (id: number, reason: string) => data<{ id: number }>(call('DELETE', `/marketing/categories/${id}`, { body: { reason } }));

export const lookup = (query: { q?: string; store_id?: number }, signal?: AbortSignal) => data<Lookup>(call('GET', '/marketing/lookup', { query: compact(query), signal }));

// --- Hooks -------------------------------------------------------------------------------------------------------------------

export const MARKETING_KEYS = {
  all: ['admin-marketing'] as const,
  banners: (status: BannerStatus | '') => ['admin-marketing', 'banners', status] as const,
  preview: ['admin-marketing', 'preview'] as const,
  codes: (filters: CodeFilters) => ['admin-marketing', 'codes', filters] as const,
  code: (id: number) => ['admin-marketing', 'code', id] as const,
  categories: ['admin-marketing', 'categories'] as const,
  lookup: (query: object) => ['admin-marketing', 'lookup', query] as const,
};

// A write refreshes everything of the section (a banner's change also moves the preview, a code's the card and the list)
function useMarketingWrite<I, R>(fn: (input: I) => Promise<R>) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => client.invalidateQueries({ queryKey: MARKETING_KEYS.all }),
  });
}

export const useBanners = (status: BannerStatus | '', enabled = true) =>
  useQuery({ queryKey: MARKETING_KEYS.banners(status), queryFn: ({ signal }) => listBanners(status, signal), enabled, placeholderData: keepPreviousData });
export const useBannerPreview = (enabled = true) => useQuery({ queryKey: MARKETING_KEYS.preview, queryFn: ({ signal }) => previewBanners(signal), enabled });
export const useCreateBanner = () => useMarketingWrite(createBanner);
export const useUpdateBanner = () => useMarketingWrite(({ id, ...input }: BannerInput & { id: number }) => updateBanner(id, input));
export const useOrderBanners = () => useMarketingWrite(({ ids, reason }: { ids: number[]; reason: string }) => orderBanners(ids, reason));
export const useDeleteBanner = () => useMarketingWrite(({ id, reason }: { id: number; reason: string }) => deleteBanner(id, reason));

export const useCodes = (filters: CodeFilters, enabled = true) =>
  useQuery({ queryKey: MARKETING_KEYS.codes(filters), queryFn: ({ signal }) => listCodes(filters, signal), enabled, placeholderData: keepPreviousData });
export const useCode = (id: number | null, enabled = true) =>
  useQuery({ queryKey: MARKETING_KEYS.code(id ?? 0), queryFn: ({ signal }) => getCode(id as number, signal), enabled: enabled && id !== null });
export const useCreateCode = () => useMarketingWrite(createCode);
export const useUpdateCode = () => useMarketingWrite(({ id, ...input }: CodeInput & { id: number }) => updateCode(id, input));
export const useSetCodeActive = () => useMarketingWrite(({ id, active, reason }: { id: number; active: boolean; reason: string }) => setCodeActive(id, active, reason));
export const useDeleteCode = () => useMarketingWrite(({ id, reason }: { id: number; reason: string }) => deleteCode(id, reason));

export const useCategories = (enabled = true) => useQuery({ queryKey: MARKETING_KEYS.categories, queryFn: ({ signal }) => listCategories(signal), enabled });
export const useCreateCategory = () => useMarketingWrite(createCategory);
export const useUpdateCategory = () => useMarketingWrite(({ id, ...input }: CategoryInput & { id: number }) => updateCategory(id, input));
export const useOrderCategories = () => useMarketingWrite(({ ids, reason }: { ids: number[]; reason: string }) => orderCategories(ids, reason));
export const useDeleteCategory = () => useMarketingWrite(({ id, reason }: { id: number; reason: string }) => deleteCategory(id, reason));

// The store / product picker: results of a search (a few at a time)
export const useLookup = (query: { q: string; store_id?: number }, enabled = true) =>
  useQuery({ queryKey: MARKETING_KEYS.lookup(query), queryFn: ({ signal }) => lookup(query, signal), enabled, placeholderData: keepPreviousData, staleTime: 30_000 });
