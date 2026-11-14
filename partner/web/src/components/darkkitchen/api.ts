import { StaffApiError, unwrap, type StaffApi } from '@rizqim/staff';

// P6-55 "Ko'p brend, ingredient stop-list, kanal tushumi" (docs/API.md): the owner's brands (module multi_brand), the
// ingredients and their quick stop (module ingredient_stoplist), the channel net revenue report and the channel
// commissions. Called through the generated client's untyped door and read into the types below, so this area does
// not wait for the spec to be regenerated; the server does every sum and every rule.

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query };
  body?: unknown;
  parseAs?: 'blob';
}
interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string, init?: Init): Promise<Result>;
  POST(path: string, init?: Init): Promise<Result>;
  PUT(path: string, init?: Init): Promise<Result>;
  PATCH(path: string, init?: Init): Promise<Result>;
  DELETE(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

// App\Enums\OrderChannel
export type Channel = 'telegram' | 'mobile' | 'pos_hall' | 'pos_takeaway' | 'aggregator';
export const CHANNELS: readonly Channel[] = ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'];

export interface BrandChannelRule {
  channel: Channel;
  is_hidden: boolean;
  // "+12.50": the brand's dishes cost that much more there (a variant's own channel price wins); null: no change
  price_percent: string | null;
}

export interface Brand {
  id: number;
  uuid: string;
  name: string;
  short_code: string;
  color: string;
  logo_url: string | null;
  is_active: boolean;
  sort_order: number;
  products_count: number;
  channels: BrandChannelRule[];
  off_branch_ids: number[];
}

export interface BrandInput {
  name: string;
  short_code: string;
  color: string;
  logo_url: string | null;
  is_active: boolean;
}

export const fetchBrands = (api: StaffApi) => data<{ brands: Brand[]; channels_available: Channel[] }>(door(api).GET('/v1/owner/brands'));
export const createBrand = (api: StaffApi, input: BrandInput) => data<Brand>(door(api).POST('/v1/owner/brands', { body: input }));
export const updateBrand = (api: StaffApi, id: number, input: Partial<BrandInput>) => data<Brand>(door(api).PATCH(`/v1/owner/brands/${id}`, { body: input }));
export const deleteBrand = (api: StaffApi, id: number) => data<null>(door(api).DELETE(`/v1/owner/brands/${id}`));
export const saveBrandChannels = (api: StaffApi, id: number, channels: { channel: Channel; is_hidden: boolean; price_percent: string | null }[]) =>
  data<Brand>(door(api).PUT(`/v1/owner/brands/${id}/channels`, { body: { channels } }));
export const saveBrandBranches = (api: StaffApi, id: number, branches: { branch_id: number; is_active: boolean }[]) =>
  data<Brand>(door(api).PUT(`/v1/owner/brands/${id}/branches`, { body: { branches } }));
export const assignBrand = (api: StaffApi, brandId: number | null, productIds: number[]) =>
  data<{ changed: number; brand_id: number | null }>(door(api).PUT('/v1/owner/product-brands', { body: { brand_id: brandId, product_ids: productIds } }));

export interface IngredientLink {
  product_id: number;
  name: string;
  is_critical: boolean;
}

export interface Ingredient {
  id: number;
  uuid: string;
  name: string;
  unit: string | null;
  links: IngredientLink[];
  stopped: { branch_id: number; entry_id: number; reason: string | null; until: string | null }[];
  stopped_here: boolean;
}

export const fetchIngredients = (api: StaffApi, branchId: number | null) =>
  data<{ branch_id: number | null; ingredients: Ingredient[] }>(door(api).GET('/v1/owner/ingredients', { params: { query: branchId !== null ? { branch_id: branchId } : {} } }));
export const createIngredient = (api: StaffApi, input: { name: string; unit: string | null }) => data<Ingredient>(door(api).POST('/v1/owner/ingredients', { body: input }));
export const updateIngredient = (api: StaffApi, id: number, input: { name?: string; unit?: string | null }) => data<Ingredient>(door(api).PATCH(`/v1/owner/ingredients/${id}`, { body: input }));
export const deleteIngredient = (api: StaffApi, id: number) => data<null>(door(api).DELETE(`/v1/owner/ingredients/${id}`));
export const saveIngredientLinks = (api: StaffApi, id: number, links: { product_id: number; is_critical: boolean }[]) =>
  data<Ingredient>(door(api).PUT(`/v1/owner/ingredients/${id}/products`, { body: { links } }));
export const linkIngredientCategories = (api: StaffApi, id: number, categoryIds: number[], isCritical: boolean) =>
  data<Ingredient & { added: number }>(door(api).POST(`/v1/owner/ingredients/${id}/link-categories`, { body: { category_ids: categoryIds, is_critical: isCritical } }));
export const stopIngredient = (api: StaffApi, id: number, branchId: number, input: { reason: string | null; until: string | null }) =>
  data<{ entry_id: number; branch_id: number; closed_product_ids: number[] }>(
    door(api).POST(`/v1/owner/ingredients/${id}/stop`, { body: { branch_id: branchId, ...(input.reason ? { reason: input.reason } : {}), ...(input.until ? { until: input.until } : {}) } }),
  );
export const reopenIngredient = (api: StaffApi, id: number, branchId: number) => data<null>(door(api).DELETE(`/v1/owner/ingredients/${id}/stop`, { params: { query: { branch_id: branchId } } }));

export interface RevenueFigures {
  orders: number;
  gross: string;
  discounts: string;
  refunds: string;
  commission: string;
  net: string;
}

export interface ChannelPart {
  channel: Channel;
  channel_label: string;
  commission_percent: string | null;
  // owner: the percent set below; billing: the order's own RIZQIM commission (docs/BILLING.md)
  commission_source: 'owner' | 'billing';
}

export interface BrandPart {
  // null: the house brand
  brand_id: number | null;
  brand_name: string | null;
  brand_code: string | null;
  brand_color: string | null;
}

export interface ChannelRevenue {
  period: { from: string; to: string; timezone: string };
  branch_id: number | null;
  currency: 'UZS';
  rows: (ChannelPart & BrandPart & RevenueFigures)[];
  by_channel: (ChannelPart & RevenueFigures)[];
  by_brand: (BrandPart & RevenueFigures)[];
  totals: RevenueFigures;
}

export interface RevenueFilter {
  from?: string;
  to?: string;
  branch_id?: number;
}

export const fetchChannelRevenue = (api: StaffApi, filter: RevenueFilter) => data<ChannelRevenue>(door(api).GET('/v1/owner/reports/channel-revenue', { params: { query: { ...filter } } }));

export interface CommissionRate {
  channel: Channel;
  percent: string;
  note: string | null;
}

export const fetchCommissions = (api: StaffApi) => data<{ rates: CommissionRate[]; channels: Channel[] }>(door(api).GET('/v1/owner/channel-commissions'));
export const saveCommissions = (api: StaffApi, rates: { channel: Channel; percent: string; note: string | null }[]) =>
  data<{ rates: CommissionRate[] }>(door(api).PUT('/v1/owner/channel-commissions', { body: { rates } }));

// GET /owner/reports/channel-revenue/export: the .xlsx with the session's token
export async function downloadChannelRevenue(api: StaffApi, filter: RevenueFilter): Promise<{ blob: Blob; filename: string }> {
  const result = await door(api).GET('/v1/owner/reports/channel-revenue/export', { params: { query: { ...filter } }, parseAs: 'blob' });
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  const header = result.response.headers.get('Content-Disposition');
  const name = header ? /filename\s*=\s*"?([^";]+)"?/i.exec(header)?.[1]?.trim() : undefined;
  return { blob: result.data as Blob, filename: name ?? `kanal-tushum-${filter.from ?? ''}-${filter.to ?? ''}.xlsx` };
}
