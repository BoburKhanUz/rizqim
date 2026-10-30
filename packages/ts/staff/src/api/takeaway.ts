import type { StaffApi } from './client';
import { unwrap } from './errors';

// P6-34 "Qadoq, 24/7, olib ketish vaqti" (docs/API.md "Qadoq, 24/7 va olib ketish vaqti"), the owner's side:
//  - GET/POST /owner/branches/{id}/packaging-rules, PUT/DELETE .../packaging-rules/{rule}: the branch's packaging rules
//    (settings.manage; the `packaging` module), each a container with its own price and MXIK;
//  - GET/PUT /owner/branches/{id}/pickup-settings: the rules of a pickup for later (`scheduled_pickup`);
//  - GET /owner/branches/{id}/business-days: the report by business day of a 24/7 branch (reports.view; `round_the_clock`).
// Never from a terminal's PIN session. The routes are not in the generated spec yet: their paths are borrowed from routes that
// are (the cast keeps the call typed) and the answers are read as the contract says. Replace by the generated types once the
// spec has them.

export const PACKAGING_BASES = ['order', 'item'] as const;
export type PackagingBasis = (typeof PACKAGING_BASES)[number];

// Where a rule applies: the till's takeaway, a Telegram / Mini App pickup or delivery (never the hall)
export const PACKAGING_ORDER_TYPES = ['takeaway', 'pickup', 'delivery'] as const;
export type PackagingOrderType = (typeof PACKAGING_ORDER_TYPES)[number];

export interface PackagingRule {
  id: number;
  branch_id: number;
  name: string;
  basis: PackagingBasis;
  basis_label: string;
  // An `item` rule names one of them, an `order` rule none
  category_id: number | null;
  product_id: number | null;
  // Per order, or per portion of the dishes
  quantity: number;
  // Money as a decimal string
  unit_price: string;
  // null: the store's fiscal "packaging" code
  mxik: string | null;
  package_code: string | null;
  unit: string;
  order_types: PackagingOrderType[];
  is_active: boolean;
  sort_order: number;
}

export interface PackagingRuleInput {
  name: string;
  basis: PackagingBasis;
  category_id?: number | null;
  product_id?: number | null;
  quantity?: number;
  unit_price: string;
  mxik?: string | null;
  package_code?: string | null;
  unit?: string | null;
  order_types: PackagingOrderType[];
  is_active?: boolean;
  sort_order?: number;
}

export type PackagingRulePatch = Partial<PackagingRuleInput>;

export interface PickupSettings {
  branch_id: number;
  // false: nothing was saved, the values are the defaults
  configured: boolean;
  // The soonest a pickup time may be from now (0-720, 20)
  pickup_min_lead_minutes: number;
  // The step of the times offered (5-120, 15)
  pickup_slot_minutes: number;
  // 0: today's business day only (0-7, 1)
  pickup_max_days_ahead: number;
  // The kitchen gets the order this long before the pickup time (or the longest wait_minutes of its dishes; 0-240, 15)
  pickup_prep_minutes: number;
  // The 24/7 module: times at any hour, the opening hours are not kept to
  round_the_clock: boolean;
  opening_hours: { open: string | null; close: string | null };
  updated_at: string | null;
}

export type PickupSettingsPatch = Partial<Pick<PickupSettings, 'pickup_min_lead_minutes' | 'pickup_slot_minutes' | 'pickup_max_days_ahead' | 'pickup_prep_minutes'>>;

export interface BusinessDayInfo {
  business_date: string;
  // "HH:MM", the branch's day start
  starts_at: string;
  started_at: string;
  next_starts_at: string;
  round_the_clock: boolean;
}

export interface BusinessDayShift {
  uuid: string;
  business_date: string;
  z_number: number | null;
  opened_at: string | null;
  closed_at: string | null;
  status: string;
}

export interface BusinessDayRow {
  business_date: string;
  started_at: string;
  ends_at: string;
  orders: number;
  pos_orders: number;
  online_orders: number;
  revenue: string;
  packaging_amount: string;
  // Opened after midnight: their calendar date is the next day's
  after_midnight_orders: number;
  first_order_at: string | null;
  last_order_at: string | null;
  shifts: BusinessDayShift[];
}

export interface BusinessDayReport {
  branch_id: number;
  business_day: BusinessDayInfo;
  days: BusinessDayRow[];
}

export interface BusinessDayQuery {
  branchId: number;
  from?: string;
  to?: string;
}

const RULES_PATH = '/v1/owner/branches/{id}/packaging-rules' as '/v1/owner/branches/{id}/pos-settings';
const RULES_CREATE_PATH = '/v1/owner/branches/{id}/packaging-rules' as '/v1/owner/products/{id}';
const rulePath = (ruleId: number) => `/v1/owner/branches/{id}/packaging-rules/${ruleId}`;
const PICKUP_PATH = '/v1/owner/branches/{id}/pickup-settings' as '/v1/owner/branches/{id}/pos-settings';
const BUSINESS_DAYS_PATH = '/v1/owner/branches/{id}/business-days' as '/v1/owner/branches/{id}/pos-settings';

export async function fetchPackagingRules(api: StaffApi, branchId: number): Promise<PackagingRule[]> {
  return unwrap(await api.client.GET(RULES_PATH, { params: { path: { id: branchId } } })).data as unknown as PackagingRule[];
}

export async function createPackagingRule(api: StaffApi, branchId: number, input: PackagingRuleInput): Promise<PackagingRule> {
  return unwrap(await api.client.POST(RULES_CREATE_PATH, { params: { path: { id: String(branchId) } }, body: input as never })).data as unknown as PackagingRule;
}

export async function updatePackagingRule(api: StaffApi, branchId: number, ruleId: number, patch: PackagingRulePatch): Promise<PackagingRule> {
  return unwrap(await api.client.PUT(rulePath(ruleId) as '/v1/owner/meal-sets/{id}', { params: { path: { id: branchId } }, body: patch as never })).data as unknown as PackagingRule;
}

export async function deletePackagingRule(api: StaffApi, branchId: number, ruleId: number): Promise<void> {
  unwrap(await api.client.DELETE(rulePath(ruleId) as '/v1/owner/printers/{id}', { params: { path: { id: branchId } } }));
}

export async function fetchPickupSettings(api: StaffApi, branchId: number): Promise<PickupSettings> {
  return unwrap(await api.client.GET(PICKUP_PATH, { params: { path: { id: branchId } } })).data as unknown as PickupSettings;
}

export async function savePickupSettings(api: StaffApi, branchId: number, patch: PickupSettingsPatch): Promise<PickupSettings> {
  return unwrap(await api.client.PUT(PICKUP_PATH, { params: { path: { id: branchId } }, body: patch as never })).data as unknown as PickupSettings;
}

export async function fetchBusinessDays(api: StaffApi, { branchId, from, to }: BusinessDayQuery): Promise<BusinessDayReport> {
  const query: Record<string, string> = {};
  if (from) query.from = from;
  if (to) query.to = to;
  return unwrap(await api.client.GET(BUSINESS_DAYS_PATH, { params: { path: { id: branchId }, query: query as never } })).data as unknown as BusinessDayReport;
}
