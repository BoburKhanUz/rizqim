import { unwrap, type StaffApi } from '@rizqim/staff';

// The daily menu, kompleks, "keyingi partiya", write-off and yield endpoints of P6-15 (docs/API.md "Bugungi menyu,
// kompleks va partiya hisobi"). They are called through the generated client's untyped door and read into the types
// below, so this area does not wait for the spec to be regenerated; the server does every sum (sold, variance, percents).

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
  headers?: Record<string, string>;
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
  DELETE(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

// --- Bugungi menyu ------------------------------------------------------------------------------------------------------

export interface DailyMenuProduct {
  id: number;
  name: string;
  category_id: number | null;
  category_name: string | null;
  // Sold only while it is on the day's menu
  daily_rotation: boolean;
  on_menu: boolean;
  is_set: boolean;
}

export interface DailyMenu {
  branch_id: number;
  date: string;
  // The branch's today (the business day), which is not the calendar date before the day starts
  business_date: string;
  // The rule is in force (the module is on)
  enforced: boolean;
  // A menu was made for `date`; false: every dish is on sale
  set: boolean;
  product_ids: number[];
  previous_date: string | null;
  products: DailyMenuProduct[];
}

export const fetchDailyMenu = (api: StaffApi, branchId: number, date?: string) =>
  data<DailyMenu>(door(api).GET('/v1/owner/daily-menu', { params: { query: { branch_id: branchId, date } } }));

export const saveDailyMenu = (api: StaffApi, input: { branchId: number; date: string; productIds: number[] }) =>
  data<DailyMenu>(door(api).PUT('/v1/owner/daily-menu', { body: { branch_id: input.branchId, date: input.date, product_ids: input.productIds } }));

export const copyDailyMenu = (api: StaffApi, input: { branchId: number; date: string; fromDate?: string }) =>
  data<DailyMenu>(door(api).POST('/v1/owner/daily-menu/copy', { body: { branch_id: input.branchId, date: input.date, ...(input.fromDate ? { from_date: input.fromDate } : {}) } }));

export const saveRotation = (api: StaffApi, productIds: number[]) =>
  data<{ product_ids: number[] }>(door(api).PUT('/v1/owner/daily-menu/rotation', { body: { product_ids: productIds } }));

// --- Kompleks -----------------------------------------------------------------------------------------------------------

export interface MealSetPart {
  variant_id: number;
  variant_uuid: string;
  product_id: number;
  name: string;
  variant_name: string | null;
  quantity: string;
  unit: string;
  label: string | null;
}

export interface MealSet {
  id: number;
  uuid: string;
  product_id: number;
  product_name: string | null;
  prices: string[];
  is_active: boolean;
  parts: MealSetPart[];
}

export interface MealSetInput {
  productId?: number;
  isActive?: boolean;
  parts?: Array<{ variant_id: number; quantity: number; label: string | null }>;
}

export const fetchMealSets = (api: StaffApi) => data<{ sets: MealSet[] }>(door(api).GET('/v1/owner/meal-sets')).then((body) => body.sets);

export const createMealSet = (api: StaffApi, input: { productId: number; parts: NonNullable<MealSetInput['parts']> }) =>
  data<MealSet>(door(api).POST('/v1/owner/meal-sets', { body: { product_id: input.productId, parts: input.parts } }));

export const updateMealSet = (api: StaffApi, id: number, input: Omit<MealSetInput, 'productId'>) =>
  data<MealSet>(door(api).PUT('/v1/owner/meal-sets/{id}', { params: { path: { id } }, body: { ...(input.parts ? { parts: input.parts } : {}), ...(input.isActive === undefined ? {} : { is_active: input.isActive }) } }));

export const deleteMealSet = async (api: StaffApi, id: number): Promise<void> => {
  unwrap(await door(api).DELETE('/v1/owner/meal-sets/{id}', { params: { path: { id } } }));
};

// --- Keyingi partiya -----------------------------------------------------------------------------------------------------

export interface BatchPlan {
  id: number;
  uuid: string;
  branch_id: number;
  product_id: number;
  product_variant_id: number | null;
  product_name: string | null;
  variant_name: string | null;
  planned_at: string;
  // HH:MM in the store's time zone
  time: string;
  quantity: number | null;
  label: string | null;
  status: string;
  overdue: boolean;
}

export interface BatchPlans {
  branch_id: number;
  // Which batch features the branch's format has
  modules: { batches: boolean; batch_writeoff: boolean };
  plans: BatchPlan[];
}

export const fetchBatchPlans = (api: StaffApi, branchId: number) => data<BatchPlans>(door(api).GET('/v1/owner/batch-plans', { params: { query: { branch_id: branchId } } }));

export interface BatchPlanInput {
  branchId: number;
  productId: number;
  variantId: number | null;
  time: string;
  quantity: number | null;
  label: string | null;
}

export const createBatchPlan = (api: StaffApi, input: BatchPlanInput) =>
  data<BatchPlan>(
    door(api).POST('/v1/owner/batch-plans', {
      body: { branch_id: input.branchId, product_id: input.productId, product_variant_id: input.variantId, time: input.time, quantity: input.quantity, label: input.label },
    }),
  );

export const cancelBatchPlan = async (api: StaffApi, id: number): Promise<void> => {
  unwrap(await door(api).DELETE('/v1/owner/batch-plans/{id}', { params: { path: { id } } }));
};

// --- Hisobdan chiqarish ----------------------------------------------------------------------------------------------------

export const WRITE_OFF_REASONS = ['unsold', 'spoiled', 'staff_meal', 'dropped', 'expired', 'other'] as const;
export type WriteOffReason = (typeof WRITE_OFF_REASONS)[number];

export interface WriteOffInput {
  quantity: number;
  reason: WriteOffReason;
  note: string | null;
  close: boolean;
}

// One Idempotency-Key per attempt of the dialog: a retry after a lost answer writes the portions off once
export const writeOffBatch = (api: StaffApi, id: number, input: WriteOffInput, key: string) =>
  data<{ write_off: { uuid: string; quantity: number; unit: string; reason: string } }>(
    door(api).POST('/v1/owner/batches/{id}/write-off', {
      params: { path: { id } },
      headers: { 'Idempotency-Key': key },
      body: { quantity: input.quantity, reason: input.reason, note: input.note, close: input.close },
    }),
  );

export interface WriteOffRow {
  uuid: string;
  batch_id: number;
  product_id: number;
  product_name: string | null;
  variant_name: string | null;
  quantity: number;
  unit: string;
  reason: string;
  reason_label: string;
  note: string | null;
  business_date: string;
  shift_id: number | null;
  written_off_by: { id: number; name: string } | null;
  created_at: string | null;
}

export interface WriteOffList {
  branch_id: number;
  from: string;
  to: string;
  write_offs: WriteOffRow[];
}

export interface PeriodQuery {
  branchId: number;
  from: string;
  to: string;
}

export const fetchWriteOffs = (api: StaffApi, query: PeriodQuery) =>
  data<WriteOffList>(door(api).GET('/v1/owner/batch-writeoffs', { params: { query: { branch_id: query.branchId, from: query.from, to: query.to } } }));

// --- Hosil hisoboti --------------------------------------------------------------------------------------------------------

export interface YieldBatch {
  id: number;
  uuid: string;
  business_date: string;
  product_id: number;
  product_name: string | null;
  variant_name: string | null;
  unit: string;
  label: string | null;
  started_at: string;
  closed_at: string | null;
  is_open: boolean;
  produced: number;
  sold: number;
  written_off: number;
  left: number;
  oversold: number;
  variance: number;
  sold_percent: string | null;
  written_off_percent: string | null;
}

export interface YieldDay {
  business_date: string;
  product_id: number;
  product_name: string | null;
  unit: string;
  batches: number;
  produced: number;
  sold: number;
  written_off: number;
  left: number;
  variance: number;
  sold_percent: string | null;
  written_off_percent: string | null;
}

export interface YieldReport {
  branch_id: number;
  from: string;
  to: string;
  batches: YieldBatch[];
  days: YieldDay[];
  write_offs: Array<{ reason: string; label: string; count: number; quantity: number }>;
  in_sets: Array<{ business_date: string; product_id: number; name: string; unit: string; quantity: number }>;
}

export const fetchYield = (api: StaffApi, query: PeriodQuery) =>
  data<YieldReport>(door(api).GET('/v1/owner/batch-yield', { params: { query: { branch_id: query.branchId, from: query.from, to: query.to } } }));
