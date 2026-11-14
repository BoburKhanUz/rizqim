import { StaffApiError, unwrap, type StaffApi } from '@rizqim/staff';

// The banquet event's kitchen papers, contract and final settlement of P6-54 (docs/API.md "Tadbirlar: ishlab chiqarish va
// yakuniy hisob"). Called through the generated client's untyped door and read into the types below, so these screens do
// not wait for the spec to be regenerated; the server does every sum. The settlement itself is taken at the till.

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
  headers?: Record<string, string>;
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
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export type DishUnit = 'portion' | 'piece' | 'kg' | 'l';
export type IngredientUnit = 'kg' | 'l' | 'dona';

export interface Warning {
  // guests_over_threshold, outside_time_window (the event card's rule, P6-51)
  code: string;
  message: string;
}

export interface Dish {
  product_id: number | null;
  name: string;
  unit: DishUnit;
  // null on the shopping list (a sum of events)
  quantity_per_person: string | null;
  quantity: string;
  kg: string | null;
}

export interface Ingredient {
  key: string;
  name: string;
  unit: IngredientUnit;
  per_kg: string;
}

export interface OshResult {
  persons: number;
  persons_per_kg: string;
  osh_kg: string;
  ingredients: (Ingredient & { quantity: string })[];
  kazans: { size_kg: number; count: number }[];
  kazan_capacity_kg: string;
}

export interface ProductionSheet {
  event: { uuid: string; title: string | null; event_type: string; event_type_label: string; event_date: string; slot: string; starts_at: string | null; ends_at: string | null; local_time: string; status: string; halls: string[] };
  guests: { guaranteed: number; minimum: number; actual: number | null; base: number; reserve_percent: string; planned: number };
  stations: { station_id: number | null; name: string; dishes: Dish[] }[];
  osh: OshResult | null;
  warnings: Warning[];
}

export interface ShoppingList {
  branch_id: number;
  from: string;
  to: string;
  events: { uuid: string; title: string | null; event_type: string; event_date: string; slot: string; status: string; planned_guests: number }[];
  dishes: Dish[];
  osh: { persons: number; osh_kg: string; ingredients: (Ingredient & { quantity: string })[] };
}

export interface OshSettings {
  persons_per_kg: string;
  product_ids: number[];
  ingredients: Ingredient[];
  kazans: number[];
}

export interface MxikPart {
  mxik: string | null;
  package_code: string | null;
}

export interface OpsSettings {
  reserve_percent: string;
  osh: OshSettings;
  portion_weights: { product_id: number; grams: number }[];
  mxik: { package: MxikPart; hall: MxikPart; extra: MxikPart };
  contract_template: string;
  contract_template_is_default: boolean;
  contract_placeholders: string[];
  version: number;
}

export interface OpsSettingsInput {
  version: number;
  reserve_percent: string;
  osh: OshSettings;
  portion_weights: { product_id: number; grams: number }[];
  package_mxik: string | null;
  package_package_code: string | null;
  hall_mxik: string | null;
  hall_package_code: string | null;
  extra_mxik: string | null;
  extra_package_code: string | null;
  contract_template: string;
}

export interface ContractDocument {
  contract_no: string | null;
  text: string;
  lines: string[];
  fields: Record<string, string>;
  warnings: Warning[];
}

export interface SettlementLine {
  uuid: string;
  kind: 'package' | 'hall_rent' | 'extra';
  name: string;
  unit_price: string;
  quantity: string;
  persons: number | null;
  amount: string;
}

export interface Settlement {
  event: { uuid: string; status: string; version: number; order_uuid: string | null };
  guests: { guaranteed: number; minimum: number; actual: number | null; billed: number };
  lines: SettlementLine[];
  package_total: string;
  hall_total: string;
  extras_total: string;
  orders: { lines: string; discount: string; service_charge: string; other_charges: string; total: string };
  total: string;
  advances: { total: string; applied: string; rows: { uuid: string; tender: string; amount: string; paid_at: string | null }[] };
  due: string;
  excess: string;
  settlement: { uuid: string; status: 'open' | 'settled'; bill_uuid: string | null; applied_amount: string; excess_amount: string; excess_status: 'none' | 'pending' | 'refunded'; settled_at: string | null } | null;
  warnings: Warning[];
}

export interface DateRange {
  from?: string;
  to?: string;
}

export function fetchProductionSheet(api: StaffApi, uuid: string): Promise<ProductionSheet> {
  return data(door(api).GET('/v1/pos/banquets/{uuid}/production-sheet', { params: { path: { uuid } } }));
}

export function fetchShoppingList(api: StaffApi, range: DateRange): Promise<ShoppingList> {
  return data(door(api).GET('/v1/pos/banquets/shopping-list', { params: { query: { ...range } } }));
}

export function fetchOshCalculation(api: StaffApi, persons: number): Promise<OshResult> {
  return data(door(api).GET('/v1/pos/banquets/osh-calculator', { params: { query: { persons } } }));
}

export function fetchOpsSettings(api: StaffApi): Promise<OpsSettings> {
  return data(door(api).GET('/v1/owner/pos/banquets/ops-settings'));
}

export function saveOpsSettings(api: StaffApi, input: OpsSettingsInput, key: string): Promise<OpsSettings> {
  return data(door(api).PUT('/v1/owner/pos/banquets/ops-settings', { body: input, headers: { 'Idempotency-Key': key } }));
}

export function fetchContract(api: StaffApi, uuid: string): Promise<ContractDocument> {
  return data(door(api).GET('/v1/pos/banquets/{uuid}/contract', { params: { path: { uuid } } }));
}

export function numberContract(api: StaffApi, uuid: string, key: string): Promise<ContractDocument> {
  return data(door(api).POST('/v1/pos/banquets/{uuid}/contract/number', { params: { path: { uuid } }, headers: { 'Idempotency-Key': key } }));
}

export function fetchSettlement(api: StaffApi, uuid: string): Promise<Settlement> {
  return data(door(api).GET('/v1/pos/banquets/{uuid}/settlement', { params: { path: { uuid } } }));
}

export function saveGuestsActual(api: StaffApi, uuid: string, guests: number, key: string): Promise<{ settlement: Settlement }> {
  return data(door(api).PATCH('/v1/pos/banquets/{uuid}/guests', { params: { path: { uuid } }, body: { guests_actual: guests }, headers: { 'Idempotency-Key': key } }));
}

function filenameOf(header: string | null): string | undefined {
  if (!header) return undefined;
  const encoded = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded);
    } catch {
      // fall through to the plain name
    }
  }
  return /filename\s*=\s*"?([^";]+)"?/i.exec(header)?.[1]?.trim() || undefined;
}

async function download(promise: Promise<Result>, fallback: string): Promise<{ blob: Blob; filename: string }> {
  const result = await promise;
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  return { blob: result.data as Blob, filename: filenameOf(result.response.headers.get('Content-Disposition')) ?? fallback };
}

// The server's name when the browser may read it, else the same name from the event's date
export function downloadProductionSheet(api: StaffApi, uuid: string, eventDate?: string): Promise<{ blob: Blob; filename: string }> {
  return download(door(api).GET('/v1/pos/banquets/{uuid}/production-sheet/export', { params: { path: { uuid } }, parseAs: 'blob' }), eventDate ? `ishlab-chiqarish-${eventDate}.xlsx` : 'ishlab-chiqarish.xlsx');
}

export function downloadShoppingList(api: StaffApi, range: DateRange): Promise<{ blob: Blob; filename: string }> {
  return download(door(api).GET('/v1/pos/banquets/shopping-list/export', { params: { query: { ...range } }, parseAs: 'blob' }), `bozorlik-${range.from ?? ''}-${range.to ?? ''}.xlsx`);
}

// The kg sizes typed as "10, 20, 50": whole positive numbers, sorted, each once (the server checks them again)
export function parseKazans(text: string): number[] {
  const sizes = text
    .split(/[\s,;]+/)
    .map((part) => Number(part))
    .filter((size) => Number.isInteger(size) && size > 0 && size <= 1000);
  return [...new Set(sizes)].sort((a, b) => a - b);
}

// "3 × 50 kg, 1 × 20 kg"
export function kazansText(kazans: { size_kg: number; count: number }[]): string {
  return kazans.map((kazan) => `${kazan.count} × ${kazan.size_kg} kg`).join(', ');
}
