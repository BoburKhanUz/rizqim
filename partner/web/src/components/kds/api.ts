import { unwrap, type StaffApi } from '@rizqim/staff';

// KDS v2 (P6-33; docs/API.md "KDS v2"): the kitchen's time targets and the prep time report. Called through the generated
// client's untyped door and read into the types below, so this area does not wait for the spec to be regenerated; the
// server works out every figure (medians, p90, over-target shares).

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
}
interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string, init?: Init): Promise<Result>;
  PUT(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

// --- Time targets ---------------------------------------------------------------------------------------------------------

export interface TargetStation {
  id: number;
  name: string;
  kind: string;
  kind_label: string;
  is_active: boolean;
  // Seconds; null: none
  target_seconds: number | null;
  // What the kind suggests (grill 300, fryer 240, drinks 90, barista 180, expo 120)
  default_target_seconds: number | null;
}

export interface ProductTarget {
  product_id: number;
  name: string;
  category_id: number;
  target_seconds: number;
}

export interface KdsTargets {
  branch_id: number;
  // The branch has the `kds_expo` module: setting a target needs it, clearing one never does
  enabled: boolean;
  limits: { min: number; max: number };
  stations: TargetStation[];
  // The store's dishes with their own target (over their station's)
  products: ProductTarget[];
}

export interface ProductTargetInput {
  product_id: number;
  // null: the dish's own target goes, its station's applies again
  target_seconds: number | null;
}

export const fetchKdsTargets = (api: StaffApi, branchId: number) =>
  data<KdsTargets>(door(api).GET('/v1/owner/kds/targets', { params: { query: { branch_id: branchId } } }));

export const saveProductTargets = (api: StaffApi, branchId: number, items: ProductTargetInput[]) =>
  data<KdsTargets>(door(api).PUT('/v1/owner/kds/targets/products', { body: { branch_id: branchId, items } }));

// --- Prep time report -----------------------------------------------------------------------------------------------------

export interface PrepStats {
  count: number;
  median_seconds: number | null;
  p90_seconds: number | null;
  with_target: number;
  over_target: number;
  // Percent of `with_target`, one decimal ("33.3"); null when no line had a target
  over_target_share: string | null;
}

export interface PrepTimeReport {
  branch_id: number;
  from: string;
  to: string;
  timezone: string;
  day_starts_at: string;
  station_id: number | null;
  summary: PrepStats;
  // Each order: its first line in → its kitchen done (the expo's assembly, else its last line ready)
  orders: PrepStats;
  stations: Array<PrepStats & { station_id: number | null; name: string; kind: string | null; target_seconds: number | null }>;
  products: Array<PrepStats & { product_id: number | null; name: string }>;
  // All 24 local hours
  hours: Array<PrepStats & { hour: number }>;
  expo: (PrepStats & { station_id: number; name: string; target_seconds: number | null }) | null;
}

export interface PrepQuery {
  branchId: number;
  from: string;
  to: string;
  stationId: number | null;
}

export const fetchPrepTimes = (api: StaffApi, query: PrepQuery) =>
  data<PrepTimeReport>(
    door(api).GET('/v1/owner/reports/kitchen-times', {
      params: { query: { branch_id: query.branchId, from: query.from, to: query.to, ...(query.stationId !== null ? { station_id: query.stationId } : {}) } },
    }),
  );
