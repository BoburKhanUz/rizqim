import { unwrap, type StaffApi } from '@rizqim/staff';

// The tips (choy puli) and service charge rules endpoints of P6-22 (docs/API.md "Choy puli", "Xizmat haqi
// qoidalari"). Called through the generated client's untyped door and read into the types below, so this area does not
// wait for the spec to be regenerated; the server does every sum and every split.

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
  POST(path: string, init?: Init): Promise<Result>;
  PUT(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export interface Option {
  key: string;
  label: string;
}

// --- Settings ------------------------------------------------------------------------------------------------------------

export type TipMode = 'personal' | 'pool';
export type PoolRule = 'equal' | 'hours' | 'sales' | 'role_weights';
export type PoolPeriod = 'day' | 'shift';

export interface TipSettings {
  branch_id: number;
  // The branch has the `tips` module (changing the settings needs it)
  enabled: boolean;
  mode: TipMode;
  pool_rule: PoolRule;
  pool_period: PoolPeriod;
  roles: string[];
  role_weights: Record<string, string>;
  updated_at: string | null;
}

export interface TipSettingsPage {
  settings: TipSettings;
  options: { modes: Option[]; pool_rules: Option[]; pool_periods: Option[]; roles: Option[] };
}

export type TipSettingsInput = Partial<Pick<TipSettings, 'mode' | 'pool_rule' | 'pool_period' | 'roles' | 'role_weights'>>;

export const fetchTipSettings = (api: StaffApi, branchId: number) =>
  data<TipSettingsPage>(door(api).GET('/v1/owner/pos/tips/settings', { params: { query: { branch_id: branchId } } }));

export const saveTipSettings = (api: StaffApi, branchId: number, input: TipSettingsInput) =>
  data<TipSettingsPage>(door(api).PUT('/v1/owner/pos/tips/settings', { body: { branch_id: branchId, ...input } }));

// --- Report and tips -----------------------------------------------------------------------------------------------------

export interface Person {
  id: number;
  name: string | null;
}

export interface TipsSummary {
  count: number;
  amount: string;
  by_method: { method: 'cash' | 'card' | 'qr'; count: number; amount: string }[];
  personal: string;
  pool: string;
  // Personal tips with no waiter yet (a manager gives them to someone)
  unassigned: string;
  by_staff: { staff_id: number; name: string | null; amount: string }[];
  corrections: { count: number; amount: string };
}

export interface DayDistribution {
  uuid: string;
  shift_uuid: string | null;
  status: 'open' | 'closed';
  pool_amount: string;
  distributed: string;
}

export interface TipsReport {
  branch_id: number;
  from: string;
  to: string;
  totals: TipsSummary;
  by_day: {
    business_date: string;
    amount: string;
    personal: string;
    pool: string;
    cash: string;
    card: string;
    qr: string;
    distributions: DayDistribution[];
    undistributed: string;
  }[];
  by_staff: { staff_id: number; name: string | null; role: string | null; personal: string; pool_share: string; pool_share_closed: string; total: string }[];
  undistributed: string;
  // Whom a tip may be given to (the branch's floor staff)
  staff: { id: number; name: string; role: string }[];
}

export interface TipHolder {
  mode: TipMode;
  staff: Person | null;
  amount: string;
}

export interface TipCorrection {
  amount: string;
  mode: TipMode;
  staff_id: number | null;
  reason: string | null;
  by: Person | null;
  approved_by: number | null;
  created_at: string | null;
}

export interface Tip {
  uuid: string;
  method: 'cash' | 'card' | 'qr';
  source: 'tender' | 'change' | null;
  amount: string;
  original_amount: string;
  mode: TipMode;
  staff: Person | null;
  holders: TipHolder[];
  business_date: string;
  order_uuid: string | null;
  bill_uuid: string | null;
  payment_uuid: string | null;
  shift_uuid: string | null;
  recorded_by: Person | null;
  // A closed pool split covers it: no more corrections
  closed: boolean;
  corrections: TipCorrection[];
  created_at: string | null;
}

export interface Range {
  branchId: number;
  from: string;
  to: string;
}

const rangeQuery = (range: Range) => ({ branch_id: range.branchId, from: range.from, to: range.to });

export const fetchTipsReport = (api: StaffApi, range: Range) => data<TipsReport>(door(api).GET('/v1/owner/pos/tips', { params: { query: rangeQuery(range) } }));

export const fetchTips = (api: StaffApi, range: Range & { staffId?: number | null }) =>
  data<{ tips: Tip[]; truncated: boolean }>(
    door(api).GET('/v1/owner/pos/tips/entries', { params: { query: { ...rangeQuery(range), ...(range.staffId ? { staff_id: range.staffId } : {}) } } }),
  );

export interface CorrectionInput {
  uuid: string;
  amount: string | null;
  mode: TipMode;
  staffId: number | null;
  reason: string;
}

export const correctTip = (api: StaffApi, input: CorrectionInput) =>
  data<Tip>(
    door(api).POST('/v1/owner/pos/tips/{uuid}/corrections', {
      params: { path: { uuid: input.uuid } },
      body: { amount: input.amount, mode: input.mode, staff_id: input.mode === 'personal' ? input.staffId : null, reason: input.reason },
    }),
  );

// --- Pool splits ---------------------------------------------------------------------------------------------------------

export interface DistributionLine {
  staff_id: number | null;
  name: string | null;
  role: string | null;
  // 1 (equal), minutes (hours), sales in so'm (sales) or the role's weight (role_weights)
  basis: string;
  amount: string;
}

export interface Distribution {
  uuid: string;
  branch_id: number;
  business_date: string;
  shift_uuid: string | null;
  rule: PoolRule;
  roles: string[];
  role_weights: Record<string, string>;
  pool_amount: string;
  tips_count: number;
  distributed: string;
  status: 'open' | 'closed';
  computed_at: string | null;
  closed_at: string | null;
  closed_by: Person | null;
  manual_participants: boolean;
  lines: DistributionLine[];
}

export interface DistributionsPage {
  // The branch's pool period now: `day` (one split per business day) or `shift` (one per shift, with its uuid)
  pool_period: PoolPeriod;
  distributions: Distribution[];
}

export const fetchDistributions = (api: StaffApi, range: Range) =>
  data<DistributionsPage>(door(api).GET('/v1/owner/pos/tips/distributions', { params: { query: rangeQuery(range) } }));

export const computeDistribution = (api: StaffApi, input: { branchId: number; businessDate: string; shiftUuid?: string | null }) =>
  data<Distribution>(
    door(api).POST('/v1/owner/pos/tips/distributions', {
      body: { branch_id: input.branchId, business_date: input.businessDate, ...(input.shiftUuid ? { shift_uuid: input.shiftUuid } : {}) },
    }),
  );

export const closeDistribution = (api: StaffApi, uuid: string) =>
  data<Distribution>(door(api).POST('/v1/owner/pos/tips/distributions/{uuid}/close', { params: { path: { uuid } } }));

// --- Service charge rules ------------------------------------------------------------------------------------------------

export type ServiceOrderType = 'hall' | 'table_service' | 'takeaway';

export interface ServiceChargeRules {
  by_order_type: Partial<Record<ServiceOrderType, string>>;
  large_group_min_guests: number | null;
  large_group_percent: string | null;
  cover_amount: string | null;
}

export interface ServiceChargeRow {
  branch_id: number | null;
  hall_id: number | null;
  hall_name: string | null;
  service_charge_percent: string;
  service_charge_channels: string[] | null;
  place_fee_amount: string;
  place_fee_mode: string;
  rules: ServiceChargeRules;
  notice: string | null;
  notice_preview: string | null;
}

export interface ServiceChargePage {
  branch_id: number;
  // The branch has the `service_charge_rules` module
  enabled: boolean;
  // A stolovaya: 0% by law
  locked: boolean;
  order_types: { key: ServiceOrderType; label: string }[];
  rows: ServiceChargeRow[];
}

export interface ServiceChargeInput {
  hallId: number | null;
  byOrderType: Partial<Record<ServiceOrderType, string>>;
  largeGroupMinGuests: number | null;
  largeGroupPercent: string | null;
  coverAmount: string | null;
  notice: string | null;
}

export const fetchServiceChargeRules = (api: StaffApi, branchId: number) =>
  data<ServiceChargePage>(door(api).GET('/v1/owner/pos/service-charge-rules', { params: { query: { branch_id: branchId } } }));

export const saveServiceChargeRules = (api: StaffApi, branchId: number, input: ServiceChargeInput) =>
  data<ServiceChargePage>(
    door(api).PUT('/v1/owner/pos/service-charge-rules', {
      body: {
        branch_id: branchId,
        ...(input.hallId !== null ? { hall_id: input.hallId } : {}),
        by_order_type: input.byOrderType,
        large_group_min_guests: input.largeGroupMinGuests,
        large_group_percent: input.largeGroupPercent,
        cover_amount: input.coverAmount,
        notice: input.notice,
      },
    }),
  );
