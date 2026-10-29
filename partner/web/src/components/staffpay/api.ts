import { unwrap, type StaffApi } from '@rizqim/staff';

// The staff pay endpoints of P6-23 (docs/API.md "Ish haqi qoidalari"): the wage rules of a branch, its staff's work time
// and the pay statements per period. Called through the generated client's untyped door and read into the types below,
// so this area does not wait for the spec to be regenerated; the server does every sum.

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

// --- Rules ---------------------------------------------------------------------------------------------------------------

export const COMPONENT_TYPES = ['shift_rate', 'hourly_rate', 'sales_percent', 'service_charge_percent', 'guarantee', 'event_rate', 'tips'] as const;
export type ComponentType = (typeof COMPONENT_TYPES)[number];
export const PERIODS = ['day', 'week', 'half_month', 'month'] as const;
export type PayPeriod = (typeof PERIODS)[number];

// One part of a rule; the fields a type does not use are absent
export interface PayComponent {
  type: ComponentType;
  label?: string;
  amount?: string;
  percent?: string;
  per?: 'shift' | 'period';
  mode?: 'max' | 'plus';
  base?: 'sales' | 'service_charge';
  event_types?: string[];
}

export interface PayRule {
  uuid: string;
  branch_id: number;
  user_id: number | null;
  user_name: string | null;
  role: string | null;
  role_label: string | null;
  effective_from: string;
  components: PayComponent[];
  note: string | null;
  updated_at: string | null;
}

export interface Option {
  value: string;
  label: string;
}

export interface StaffMember {
  id: number;
  name: string;
  role: string;
  role_label: string;
  branch_id: number | null;
}

export interface RulesPage {
  branch: { id: number; name: string };
  period: PayPeriod;
  period_label: string;
  periods: Option[];
  component_types: Option[];
  guarantee_modes: Option[];
  guarantee_per: Option[];
  percent_bases: Option[];
  event_types: Option[];
  roles: Option[];
  staff: StaffMember[];
  rules: PayRule[];
}

export interface RuleInput {
  branchId: number;
  userId: number | null;
  role: string | null;
  effectiveFrom: string;
  components: PayComponent[];
  note: string | null;
}

// The labels came from the server: they are not sent back
const componentBody = (components: PayComponent[]) =>
  components.map((component) => {
    const body: PayComponent = { ...component };
    delete body.label;
    return body;
  });

export const fetchRules = (api: StaffApi, branchId: number) => data<RulesPage>(door(api).GET('/v1/owner/staff-pay/rules', { params: { query: { branch_id: branchId } } }));

export const savePeriod = (api: StaffApi, branchId: number, period: PayPeriod) =>
  data<{ period: PayPeriod; period_label: string }>(door(api).PUT('/v1/owner/staff-pay/settings', { body: { branch_id: branchId, period } }));

export const createRule = (api: StaffApi, input: RuleInput) =>
  data<PayRule>(
    door(api).POST('/v1/owner/staff-pay/rules', {
      body: { branch_id: input.branchId, user_id: input.userId, role: input.role, effective_from: input.effectiveFrom, components: componentBody(input.components), note: input.note },
    }),
  );

export const updateRule = (api: StaffApi, uuid: string, input: Pick<RuleInput, 'effectiveFrom' | 'components' | 'note'>) =>
  data<PayRule>(door(api).PUT('/v1/owner/staff-pay/rules/{uuid}', { params: { path: { uuid } }, body: { effective_from: input.effectiveFrom, components: componentBody(input.components), note: input.note } }));

export const deleteRule = async (api: StaffApi, uuid: string): Promise<void> => {
  unwrap(await door(api).DELETE('/v1/owner/staff-pay/rules/{uuid}', { params: { path: { uuid } } }));
};

// --- Work time -----------------------------------------------------------------------------------------------------------

export interface WorkSession {
  uuid: string;
  branch_id: number;
  user_id: number;
  user_name: string | null;
  business_date: string;
  clock_in_at: string;
  clock_out_at: string | null;
  // Left open on an earlier day and set aside: not counted until it has an end
  abandoned: boolean;
  open: boolean;
  minutes: number;
  source: 'pin' | 'manual' | string;
  note: string | null;
}

export interface SessionInput {
  clockInAt: string;
  clockOutAt: string | null;
  reason: string;
}

export const fetchSessions = (api: StaffApi, query: { branchId: number; from: string; to: string }) =>
  data<WorkSession[]>(door(api).GET('/v1/owner/staff-pay/work-sessions', { params: { query: { branch_id: query.branchId, from: query.from, to: query.to } } }));

export const addSession = (api: StaffApi, input: SessionInput & { branchId: number; userId: number }) =>
  data<WorkSession>(door(api).POST('/v1/owner/staff-pay/work-sessions', { body: { branch_id: input.branchId, user_id: input.userId, clock_in_at: input.clockInAt, clock_out_at: input.clockOutAt, reason: input.reason } }));

export const correctSession = (api: StaffApi, uuid: string, input: SessionInput) =>
  data<WorkSession>(door(api).PUT('/v1/owner/staff-pay/work-sessions/{uuid}', { params: { path: { uuid } }, body: { clock_in_at: input.clockInAt, clock_out_at: input.clockOutAt, reason: input.reason } }));

export const removeSession = async (api: StaffApi, uuid: string, reason: string): Promise<void> => {
  unwrap(await door(api).DELETE('/v1/owner/staff-pay/work-sessions/{uuid}', { params: { path: { uuid }, query: { reason } } }));
};

// --- Statements ----------------------------------------------------------------------------------------------------------

export interface ComponentResult {
  type: ComponentType;
  label: string;
  rule_uuid: string | null;
  days: number;
  quantity: string | null;
  rate: string | null;
  base: string | null;
  amount: string;
  // How it was worked out, in Uzbek ("2 smena × 100 000 so'm")
  detail: string;
}

export interface LineMetrics {
  shifts: number;
  minutes: number;
  bills: number;
  sales: string;
  service_charge: string;
  events: number;
  tips: string;
  uncounted_sessions: number;
  rules: string[];
  own_rule: boolean;
}

export interface StatementLine {
  uuid: string;
  user_id: number | null;
  user_name: string | null;
  role: string | null;
  role_label: string | null;
  metrics: LineMetrics;
  components: ComponentResult[];
  gross: string;
  advances: string;
  adjustment: string;
  adjustment_reason: string | null;
  payable: string;
  paid: boolean;
  paid_at: string | null;
  paid_method: 'cash_drawer' | 'other' | null;
  paid_note: string | null;
  cash_movement_id: number | null;
}

export type StatementStatus = 'draft' | 'closed' | 'paid';

export interface StatementWarning {
  type: 'no_rule' | 'open_sessions' | string;
  user_id: number | null;
  name: string | null;
  count?: number;
}

export interface StatementSummary {
  uuid: string;
  branch_id: number;
  period: PayPeriod;
  period_label: string;
  period_from: string;
  period_to: string;
  status: StatementStatus;
  status_label: string;
  gross_total: string;
  payable_total: string;
  paid_total: string;
  lines_count: number;
  warnings: StatementWarning[];
  computed_at: string | null;
  closed_at: string | null;
  version: number;
  created_at: string | null;
}

export interface OpenShift {
  uuid: string;
  device_name: string | null;
  business_date: string;
  opened_at: string;
  expected_cash: string;
}

export interface Statement extends StatementSummary {
  lines: StatementLine[];
  open_shifts: OpenShift[];
}

export const fetchStatements = (api: StaffApi, branchId: number) => data<StatementSummary[]>(door(api).GET('/v1/owner/staff-pay/statements', { params: { query: { branch_id: branchId } } }));

export const fetchStatement = (api: StaffApi, uuid: string) => data<Statement>(door(api).GET('/v1/owner/staff-pay/statements/{uuid}', { params: { path: { uuid } } }));

export const createStatement = (api: StaffApi, input: { branchId: number; date: string; key: string }) =>
  data<Statement>(door(api).POST('/v1/owner/staff-pay/statements', { body: { branch_id: input.branchId, date: input.date }, headers: { 'Idempotency-Key': input.key } }));

export type StatementAction = 'recompute' | 'close';

export const statementAction = (api: StaffApi, uuid: string, action: StatementAction) =>
  data<Statement>(door(api).POST(`/v1/owner/staff-pay/statements/{uuid}/${action}`, { params: { path: { uuid } } }));

export const reopenStatement = (api: StaffApi, uuid: string, reason: string) =>
  data<Statement>(door(api).POST('/v1/owner/staff-pay/statements/{uuid}/reopen', { params: { path: { uuid } }, body: { reason } }));

export const deleteStatement = async (api: StaffApi, uuid: string): Promise<void> => {
  unwrap(await door(api).DELETE('/v1/owner/staff-pay/statements/{uuid}', { params: { path: { uuid } } }));
};

export const adjustLine = (api: StaffApi, input: { uuid: string; line: string; adjustment: string; reason: string | null }) =>
  data<Statement>(door(api).PUT('/v1/owner/staff-pay/statements/{uuid}/lines/{line}', { params: { path: { uuid: input.uuid, line: input.line } }, body: { adjustment: input.adjustment, reason: input.reason } }));

export const payLine = (api: StaffApi, input: { uuid: string; line: string; method: 'cash_drawer' | 'other'; shiftUuid: string | null; note: string | null; key: string }) =>
  data<Statement>(
    door(api).POST('/v1/owner/staff-pay/statements/{uuid}/lines/{line}/pay', {
      params: { path: { uuid: input.uuid, line: input.line } },
      body: { method: input.method, shift_uuid: input.shiftUuid, note: input.note },
      headers: { 'Idempotency-Key': input.key },
    }),
  );
