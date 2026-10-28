'use client';

import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// POS va obunalar (P4-24, docs/API.md "POS va obunalar"): subscriptions, the tariff catalog and a store's subscription
// (P4-21's /billing endpoints), invoices across stores, devices, shifts, fiscal receipts and offline sync errors
// (/pos endpoints). Typed by hand until the generated client is regenerated (the shapes follow BillingPresenter and
// AdminPos*Controller). Money is a decimal string ("249000.00"), times are ISO 8601, business dates Y-m-d.

export interface Named {
  id: number;
  name: string;
}

export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// --- Subscriptions and the catalog (P4-21) ---------------------------------------------------------------------------

export const SUBSCRIPTION_STATUSES = ['trialing', 'active', 'past_due', 'locked', 'cancelled'] as const;
export type SubscriptionStatus = (typeof SUBSCRIPTION_STATUSES)[number];
export type BillingPeriod = 'monthly' | 'yearly';

export interface Subscription {
  id: number;
  store_id: number;
  status: SubscriptionStatus;
  status_label: string;
  plan: { code: string; name: string; version: number };
  billing_period: BillingPeriod;
  next_billing_period: BillingPeriod | null;
  next_plan: { code: string; name: string } | null;
  cancel_at_period_end: boolean;
  started_at: string;
  trial_ends_at: string | null;
  current_period: { start: string; end: string };
  term_ends_at: string | null;
  changes_at: string;
  price_locked_until: string | null;
  locked_at: string | null;
  cancelled_at: string | null;
}

export interface SubscriptionRow {
  subscription: Subscription;
  store: Named;
}

export interface SubscriptionList {
  data: SubscriptionRow[];
  next_cursor: string | null;
  per_page: number;
  counts: Partial<Record<SubscriptionStatus, number>>;
}

export interface SubscriptionFilters {
  status: SubscriptionStatus | '';
  plan: string;
  period: BillingPeriod | '';
  trial_ends_within: string;
  store_id: string;
}

export const DEFAULT_SUBSCRIPTION_FILTERS: SubscriptionFilters = { status: '', plan: '', period: '', trial_ends_within: '', store_id: '' };

export interface Plan {
  id: number;
  code: string;
  name: string;
  version: number;
  tier: string | null;
  is_active: boolean;
  sort_order: number;
  description: string | null;
  monthly_fee: string;
  yearly_fee: string;
  included_pos: number;
  included_kds: number;
  extra_pos_fee: string;
  extra_kds_fee: string;
  trial_days: number;
  features: string[];
  subscriptions: number;
}

export interface FeatureInfo {
  key: string;
  label: string;
  base: boolean;
}

export interface Catalog {
  plans: Plan[];
  feature_catalog: FeatureInfo[];
}

export interface PlanChange {
  name?: string;
  description?: string | null;
  is_active?: boolean;
  monthly_fee?: string;
  yearly_fee?: string;
  included_pos?: number;
  included_kds?: number;
  extra_pos_fee?: string;
  extra_kds_fee?: string;
  trial_days?: number;
  features?: string[];
}

export interface DeviceAllowance {
  included: number | null;
  active?: number;
  extra?: number;
  extra_price: string | null;
  can_add: boolean;
  unlimited?: boolean;
}

export interface Entitlements {
  plan: { code: string; name: string; tier: string | null; version: number } | null;
  legacy: boolean;
  period: BillingPeriod | null;
  status: string;
  trial_ends_at: string | null;
  current_period: { start: string; end: string } | null;
  term_ends_at: string | null;
  locked_since: string | null;
  lock_at: string | null;
  settings_locked: boolean;
  features: Record<string, boolean>;
  required_plans: Record<string, string | null>;
  devices: Record<string, DeviceAllowance>;
  notice: { level: string; message: string; days_overdue: number; lock_at: string | null } | null;
}

export interface Commission {
  rates: Record<string, { rate: string; source: string; default: string }>;
  overrides: Record<string, string>;
  flat_rate: string | null;
  delivery_courier: 'own' | 'fleet' | string;
}

export interface DunningStep {
  stage: string;
  label: string;
  notified: boolean;
  recipients: number;
  occurred_at: string;
  invoice: string | null;
}

export interface InvoiceLine {
  type: string;
  label: string;
  quantity: number;
  unit_price: string;
  amount: string;
}

export interface BillingInvoice {
  uuid: string;
  number: string;
  kind: string;
  period: string;
  period_start: string;
  period_end: string;
  plan: { code: string; name: string } | null;
  lines: InvoiceLine[];
  amount: string;
  paid_amount: string;
  outstanding: string;
  status: 'issued' | 'paid' | 'void';
  due_date: string;
  issued_at: string;
  paid_at: string | null;
  voided_at: string | null;
  dunning: DunningStep[];
}

export interface StoreBilling {
  store: { id: number; name: string; status: string };
  subscription: Subscription | null;
  entitlements: Entitlements;
  debt: { debt_balance: string; subscription_debt: string; commission_debt: string; credit_limit: string; over_credit_limit: boolean };
  commission: Commission;
  history: Subscription[];
  invoices: BillingInvoice[];
}

export interface AssignInput {
  plan: string;
  period?: BillingPeriod;
  immediately?: boolean;
  reason: string;
}

export interface CommissionInput {
  // channel → percent ("3.00"), or null to remove the override
  overrides?: Record<string, string | null>;
  delivery_courier?: 'own' | 'fleet';
  reason: string;
}

// --- Invoices, devices, shifts, fiscal receipts, sync errors (P4-24) --------------------------------------------------------

export const INVOICE_STATUSES = ['overdue', 'issued', 'paid', 'void'] as const;
export const DUNNING_STAGES = ['reminder_1', 'reminder_3', 'reminder_7', 'locked', 'unlocked'] as const;

export interface InvoiceRow {
  uuid: string;
  number: string;
  kind: string;
  period: string;
  store: Named | null;
  plan: { code: string; name: string } | null;
  amount: string;
  paid_amount: string;
  outstanding: string;
  status: 'issued' | 'paid' | 'void';
  status_label: string;
  due_date: string;
  issued_at: string;
  paid_at: string | null;
  voided_at: string | null;
  overdue: boolean;
  overdue_days: number;
  dunning_stage: string | null;
  dunning_label: string | null;
  dunning_at: string | null;
}

export interface InvoiceCounts {
  issued: number;
  overdue: number;
  paid: number;
  void: number;
  outstanding: string;
}

export interface InvoiceCard {
  invoice: InvoiceRow &
    BillingInvoice & {
      void_reason: string | null;
      // A void from the threshold up waits for a second member (four-eyes): who asked, when and why
      void_request?: { by: { id: number; name: string } | null; at: string | null; reason: string | null } | null;
      void_needs_second_approval?: boolean;
    };
  store: { id: number; name: string; status: string; debt_balance: string; subscription_debt: string; commission_debt: string } | null;
}

export interface InvoiceFilters {
  status: (typeof INVOICE_STATUSES)[number] | '';
  dunning: string;
  store_id: string;
  period: string;
  q: string;
  page: number;
}

export const DEFAULT_INVOICE_FILTERS: InvoiceFilters = { status: '', dunning: '', store_id: '', period: '', q: '', page: 1 };

export const DEVICE_STATES = ['online', 'offline', 'never', 'revoked'] as const;
export type DeviceState = (typeof DEVICE_STATES)[number];

export interface DeviceRow {
  uuid: string;
  name: string;
  type: string;
  type_label: string;
  terminal: boolean;
  store: Named | null;
  branch: Named | null;
  platform: string | null;
  app_version: string | null;
  latest_version: string | null;
  outdated: boolean;
  state: DeviceState;
  last_seen_at: string | null;
  paired_at: string | null;
  revoked_at: string | null;
  sync: { pending_ops: number; oldest_op_at: string | null; reported_at: string | null; clock_skew_seconds: number | null; clock_warning: boolean };
}

export interface DeviceCounts {
  total: number;
  online: number;
  offline: number;
  never: number;
  revoked: number;
  outdated: number;
  by_type: Record<string, number>;
}

export interface DevicePage extends Page<DeviceRow> {
  counts: DeviceCounts;
  latest_versions: Record<string, string>;
}

export interface DeviceFilters {
  state: DeviceState | '';
  type: string;
  outdated: boolean;
  store_id: string;
  q: string;
  page: number;
}

export const DEFAULT_DEVICE_FILTERS: DeviceFilters = { state: '', type: '', outdated: false, store_id: '', q: '', page: 1 };

export const SHIFT_KINDS = ['difference', 'force_closed', 'open'] as const;
export type ShiftKind = (typeof SHIFT_KINDS)[number];

export interface ShiftRow {
  uuid: string;
  status: 'open' | 'closed';
  store: Named | null;
  branch: Named | null;
  device: { uuid: string; name: string | null } | null;
  business_date: string;
  opened_at: string;
  opened_by: Named | null;
  opening_cash: string;
  closed_at: string | null;
  closed_by: Named | null;
  expected_cash: string | null;
  counted_cash: string | null;
  difference: string | null;
  has_difference: boolean;
  z_number: number | null;
  force_closed: boolean;
  close_note: string | null;
}

export interface ShiftCard extends ShiftRow {
  forced_by: Named | null;
  counted_breakdown: Record<string, number> | null;
  summary: Record<string, unknown> | null;
  cash_movements: { uuid: string; type: string; category: string | null; amount: string; direction: 1 | -1; note: string | null; user: Named | null; created_at: string | null }[];
}

export interface ShiftPage extends Page<ShiftRow> {
  counts: { difference: number; force_closed: number; open: number; all: number };
}

export interface ShiftFilters {
  kind: ShiftKind | '';
  store_id: string;
  from: string;
  to: string;
  page: number;
}

export const DEFAULT_SHIFT_FILTERS: ShiftFilters = { kind: '', store_id: '', from: '', to: '', page: 1 };

export const FISCAL_STATUSES = ['open', 'failed', 'pending', 'issued', 'all'] as const;
export type FiscalStatus = (typeof FISCAL_STATUSES)[number];

export interface FiscalRow {
  uuid: string;
  type: string;
  status: 'pending' | 'issued' | 'failed';
  driver: string;
  provider: string | null;
  non_fiscal: boolean;
  total: string;
  error: string | null;
  attempts: number;
  next_attempt_at: string | null;
  created_at: string;
  issued_at: string | null;
  store: Named | null;
  branch: Named | null;
  retryable: boolean;
  subject_type: string | null;
  subject_id: number | null;
}

export interface FiscalCard extends FiscalRow {
  full_error: string | null;
  payment_form: string;
  tax_regime: string | null;
  vat_total: string;
  fiscal_sign: string | null;
  receipt_number: string | null;
  terminal_id: string | null;
  original_receipt_uuid: string | null;
  sold_at: string | null;
  lines: { name?: string; mxik_code?: string; quantity?: string | number; amount?: string }[];
  payments: { form: string; amount: string }[];
}

export interface FiscalSummary {
  failed: number;
  pending: number;
  by_driver: { driver: string; failed: number; pending: number; last_error: string | null }[];
}

export interface FiscalPage extends Page<FiscalRow> {
  summary: FiscalSummary;
}

export interface FiscalFilters {
  status: FiscalStatus;
  driver: string;
  store_id: string;
  q: string;
  page: number;
}

export const DEFAULT_FISCAL_FILTERS: FiscalFilters = { status: 'open', driver: '', store_id: '', q: '', page: 1 };

export interface SyncRow {
  op_id: string;
  seq: number;
  type: string;
  type_label: string;
  status: 'rejected' | 'conflict';
  reason: string | null;
  message: string | null;
  store: Named | null;
  device: { uuid: string; name: string | null } | null;
  staff: { id: number; name: string | null } | null;
  order: { uuid: string; local_number: string | null; short_code: string | null; table_ref: string | null; status: string } | null;
  occurred_at: string | null;
  received_at: string | null;
  clock_skew_seconds: number | null;
  resolved_at: string | null;
  resolved_by: { id: number; name: string | null } | null;
  resolution: 'resolved' | 'dismissed' | null;
  resolution_note: string | null;
}

export interface SyncCard extends SyncRow {
  applied_at: string | null;
  payload: Record<string, unknown>;
  refs: Record<string, unknown>;
}

export interface SyncPage extends Page<SyncRow> {
  counts: { rejected: number; conflict: number; open: number };
}

export interface SyncFilters {
  state: 'open' | 'resolved' | 'all';
  status: '' | 'rejected' | 'conflict';
  store_id: string;
  page: number;
}

export const DEFAULT_SYNC_FILTERS: SyncFilters = { state: 'open', status: '', store_id: '', page: 1 };

export interface Overview {
  subscriptions: { counts: Partial<Record<SubscriptionStatus, number>>; trial_ending: number; trial_ending_days: number } | null;
  invoices: InvoiceCounts | null;
  devices: DeviceCounts | null;
  shifts: { difference: number; force_closed: number; open: number; all: number } | null;
  fiscal: FiscalSummary | null;
  sync_errors: { rejected: number; conflict: number; open: number } | null;
}

// --- Calls -------------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;
const compact = (filters: object): Record<string, string | number> => {
  const query: Record<string, string | number> = {};
  for (const [name, value] of Object.entries(filters)) {
    if (value === '' || value === false || value === null || value === undefined) continue;
    query[name] = value === true ? 1 : (value as string | number);
  }
  return query;
};

export const getOverview = (signal?: AbortSignal) => data<Overview>(call('GET', '/pos/overview', { signal }));

export const listSubscriptions = (filters: SubscriptionFilters, cursor: string | null, signal?: AbortSignal) =>
  data<SubscriptionList>(call('GET', '/billing/subscriptions', { query: { ...compact(filters), per_page: 30, cursor }, signal }));
export const getCatalog = (signal?: AbortSignal) => data<Catalog>(call('GET', '/billing/plans', { query: { all: 1 }, signal }));
export const updatePlan = (code: string, change: PlanChange & { reason: string }) => data<Plan>(call('PUT', `/billing/plans/${encodeURIComponent(code)}`, { body: change }));
export const getStoreBilling = (storeId: number, signal?: AbortSignal) => data<StoreBilling>(call('GET', `/billing/stores/${storeId}`, { signal }));
export const assignPlan = (storeId: number, input: AssignInput) => data<StoreBilling>(call('POST', `/billing/stores/${storeId}/subscription`, { body: input }));
export const changePeriod = (storeId: number, input: { period: BillingPeriod; reason: string }) => data<StoreBilling>(call('POST', `/billing/stores/${storeId}/subscription/period`, { body: input }));
export const cancelSubscription = (storeId: number, input: { immediately: boolean; reason: string }) => data<StoreBilling>(call('POST', `/billing/stores/${storeId}/subscription/cancel`, { body: input }));
export const extendTrial = (storeId: number, input: { days: number; reason: string }) => data<StoreBilling>(call('POST', `/billing/stores/${storeId}/subscription/trial`, { body: input }));
export const setCommission = (storeId: number, input: CommissionInput) => data<Commission>(call('PUT', `/billing/stores/${storeId}/commission`, { body: input }));

export const listInvoices = (filters: InvoiceFilters, signal?: AbortSignal) =>
  data<Page<InvoiceRow> & { counts: InvoiceCounts }>(call('GET', '/pos/invoices', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getInvoice = (uuid: string, signal?: AbortSignal) => data<InvoiceCard>(call('GET', `/pos/invoices/${encodeURIComponent(uuid)}`, { signal }));
export const markInvoicePaid = (uuid: string, reason: string) => data<InvoiceCard>(call('POST', `/pos/invoices/${encodeURIComponent(uuid)}/paid`, { body: { reason } }));
export const voidInvoice = (uuid: string, reason: string) => data<InvoiceCard>(call('POST', `/pos/invoices/${encodeURIComponent(uuid)}/void`, { body: { reason } }));

export const listDevices = (filters: DeviceFilters, signal?: AbortSignal) => data<DevicePage>(call('GET', '/pos/devices', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getDevice = (uuid: string, signal?: AbortSignal) => data<DeviceRow>(call('GET', `/pos/devices/${encodeURIComponent(uuid)}`, { signal }));
export const revokeDevice = (uuid: string, reason: string) => data<DeviceRow>(call('POST', `/pos/devices/${encodeURIComponent(uuid)}/revoke`, { body: { reason } }));

export const listShifts = (filters: ShiftFilters, signal?: AbortSignal) => data<ShiftPage>(call('GET', '/pos/shifts', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getShift = (uuid: string, signal?: AbortSignal) => data<ShiftCard>(call('GET', `/pos/shifts/${encodeURIComponent(uuid)}`, { signal }));

export const listFiscal = (filters: FiscalFilters, signal?: AbortSignal) => data<FiscalPage>(call('GET', '/pos/fiscal-receipts', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getFiscal = (uuid: string, signal?: AbortSignal) => data<FiscalCard>(call('GET', `/pos/fiscal-receipts/${encodeURIComponent(uuid)}`, { signal }));
export const retryFiscal = (uuid: string, reason: string) => data<FiscalCard>(call('POST', `/pos/fiscal-receipts/${encodeURIComponent(uuid)}/retry`, { body: { reason } }));

export const listSyncErrors = (filters: SyncFilters, signal?: AbortSignal) => data<SyncPage>(call('GET', '/pos/sync-errors', { query: { ...compact(filters), per_page: 30 }, signal }));
export const getSyncError = (opId: string, signal?: AbortSignal) => data<SyncCard>(call('GET', `/pos/sync-errors/${encodeURIComponent(opId)}`, { signal }));
export const resolveSyncError = (opId: string, input: { resolution: 'resolved' | 'dismissed'; reason: string }) =>
  data<SyncCard>(call('POST', `/pos/sync-errors/${encodeURIComponent(opId)}/resolve`, { body: input }));

// --- Hooks -------------------------------------------------------------------------------------------------------------------

export const POS_KEYS = {
  all: ['admin-pos'] as const,
  overview: ['admin-pos', 'overview'] as const,
  subscriptions: (filters: SubscriptionFilters) => ['admin-pos', 'subscriptions', filters] as const,
  catalog: ['admin-pos', 'catalog'] as const,
  store: (id: number) => ['admin-pos', 'store', id] as const,
  invoices: (filters: InvoiceFilters) => ['admin-pos', 'invoices', filters] as const,
  invoice: (uuid: string) => ['admin-pos', 'invoice', uuid] as const,
  devices: (filters: DeviceFilters) => ['admin-pos', 'devices', filters] as const,
  device: (uuid: string) => ['admin-pos', 'device', uuid] as const,
  shifts: (filters: ShiftFilters) => ['admin-pos', 'shifts', filters] as const,
  shift: (uuid: string) => ['admin-pos', 'shift', uuid] as const,
  fiscal: (filters: FiscalFilters) => ['admin-pos', 'fiscal', filters] as const,
  fiscalCard: (uuid: string) => ['admin-pos', 'fiscal-card', uuid] as const,
  sync: (filters: SyncFilters) => ['admin-pos', 'sync', filters] as const,
  syncCard: (opId: string) => ['admin-pos', 'sync-card', opId] as const,
};

// The numbers on the tabs and the lists refresh by themselves (a minute); a write refreshes everything of the section
export const POS_POLL_MS = 60_000;

export const useOverview = (enabled = true) => useQuery({ queryKey: POS_KEYS.overview, queryFn: ({ signal }) => getOverview(signal), enabled, refetchInterval: POS_POLL_MS });

export const useSubscriptions = (filters: SubscriptionFilters, enabled = true) =>
  useInfiniteQuery({
    queryKey: POS_KEYS.subscriptions(filters),
    queryFn: ({ pageParam, signal }) => listSubscriptions(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    refetchInterval: POS_POLL_MS,
  });

export const useCatalog = (enabled = true) => useQuery({ queryKey: POS_KEYS.catalog, queryFn: ({ signal }) => getCatalog(signal), enabled });

export const useStoreBilling = (storeId: number | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.store(storeId ?? 0), queryFn: ({ signal }) => getStoreBilling(storeId as number, signal), enabled: enabled && storeId !== null });

export const useInvoices = (filters: InvoiceFilters, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.invoices(filters), queryFn: ({ signal }) => listInvoices(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POS_POLL_MS });
export const useInvoice = (uuid: string | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.invoice(uuid ?? ''), queryFn: ({ signal }) => getInvoice(uuid as string, signal), enabled: enabled && uuid !== null });

export const useDevices = (filters: DeviceFilters, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.devices(filters), queryFn: ({ signal }) => listDevices(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POS_POLL_MS });
export const useDevice = (uuid: string | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.device(uuid ?? ''), queryFn: ({ signal }) => getDevice(uuid as string, signal), enabled: enabled && uuid !== null, refetchInterval: POS_POLL_MS });

export const useShifts = (filters: ShiftFilters, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.shifts(filters), queryFn: ({ signal }) => listShifts(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POS_POLL_MS });
export const useShift = (uuid: string | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.shift(uuid ?? ''), queryFn: ({ signal }) => getShift(uuid as string, signal), enabled: enabled && uuid !== null });

export const useFiscal = (filters: FiscalFilters, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.fiscal(filters), queryFn: ({ signal }) => listFiscal(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POS_POLL_MS });
export const useFiscalCard = (uuid: string | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.fiscalCard(uuid ?? ''), queryFn: ({ signal }) => getFiscal(uuid as string, signal), enabled: enabled && uuid !== null });

export const useSyncErrors = (filters: SyncFilters, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.sync(filters), queryFn: ({ signal }) => listSyncErrors(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POS_POLL_MS });
export const useSyncCard = (opId: string | null, enabled = true) =>
  useQuery({ queryKey: POS_KEYS.syncCard(opId ?? ''), queryFn: ({ signal }) => getSyncError(opId as string, signal), enabled: enabled && opId !== null });

// Every write refreshes what the section shows (the answer is not the whole picture: lists and tab numbers change too)
function usePosWrite<I, R>(fn: (input: I) => Promise<R>) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => client.invalidateQueries({ queryKey: POS_KEYS.all }),
  });
}

export const useAssignPlan = (storeId: number) => usePosWrite((input: AssignInput) => assignPlan(storeId, input));
export const useChangePeriod = (storeId: number) => usePosWrite((input: { period: BillingPeriod; reason: string }) => changePeriod(storeId, input));
export const useCancelSubscription = (storeId: number) => usePosWrite((input: { immediately: boolean; reason: string }) => cancelSubscription(storeId, input));
export const useExtendTrial = (storeId: number) => usePosWrite((input: { days: number; reason: string }) => extendTrial(storeId, input));
export const useSetCommission = (storeId: number) => usePosWrite((input: CommissionInput) => setCommission(storeId, input));
export const useUpdatePlan = () => usePosWrite(({ code, ...change }: PlanChange & { code: string; reason: string }) => updatePlan(code, change));
export const useMarkInvoicePaid = () => usePosWrite(({ uuid, reason }: { uuid: string; reason: string }) => markInvoicePaid(uuid, reason));
export const useVoidInvoice = () => usePosWrite(({ uuid, reason }: { uuid: string; reason: string }) => voidInvoice(uuid, reason));
export const useRevokeDevice = () => usePosWrite(({ uuid, reason }: { uuid: string; reason: string }) => revokeDevice(uuid, reason));
export const useRetryFiscal = () => usePosWrite(({ uuid, reason }: { uuid: string; reason: string }) => retryFiscal(uuid, reason));
export const useResolveSync = () => usePosWrite(({ opId, resolution, reason }: { opId: string; resolution: 'resolved' | 'dismissed'; reason: string }) => resolveSyncError(opId, { resolution, reason }));
