import type { StaffApi } from './client';
import { getApiErrorStatus, isStaffApiError, unwrap } from './errors';
import type { InvoicePage, Subscription } from './payments';

// What the store's plan lets it use (P4-21 Billing v2; docs/BILLING.md "Funksiyalar va cheklovlar", docs/API.md
// "Billing v2"): GET /owner/billing/entitlements, the same payload as `entitlements` in GET /pos/bootstrap. The routes
// are not in the generated spec yet, so the types are written by hand from the backend contract; replace them by the
// generated ones once the spec has them.

// Every App\Enums\PlanFeature: the base ones (never gated) and the gated ones a plan lists
export const BASE_FEATURES = ['hall', 'room_fee', 'guest_items', 'shifts', 'rizqim_orders'] as const;
export const GATED_FEATURES = ['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge', 'multi_branch_reports', 'aggregator_orders', 'banquet'] as const;
export type BaseFeature = (typeof BASE_FEATURES)[number];
export type GatedFeature = (typeof GATED_FEATURES)[number];
export type PlanFeatureKey = BaseFeature | GatedFeature;

export type BillingStatus = 'none' | 'trialing' | 'active' | 'past_due' | 'locked';

export interface EntitlementsPlan {
  code: string;
  name: string;
  tier: string | null;
  version: number;
}

// Terminals of one type. `included: null` is "no limit" (a store not on a v2 plan); waiter phones are unlimited and free.
export interface DeviceEntitlement {
  included: number | null;
  active?: number;
  extra?: number;
  // A decimal string ("59000.00"), null for a store not on a v2 plan
  extra_price?: string | null;
  can_add: boolean;
  unlimited?: boolean;
}

export interface BillingNotice {
  // reminder: an invoice is overdue and settings will close; locked: they are closed
  level: 'reminder' | 'locked';
  // The server's plain-words text (Uzbek)
  message: string;
  days_overdue: number;
  lock_at: string | null;
  invoice: { uuid: string; number: string; amount: string; outstanding: string; due_date: string };
}

export interface Entitlements {
  plan: EntitlementsPlan | null;
  // true: every feature is on (the store is not on a v2 plan yet)
  legacy: boolean;
  period: 'monthly' | 'yearly' | null;
  status: BillingStatus;
  trial_ends_at: string | null;
  current_period: { start: string; end: string } | null;
  term_ends_at: string | null;
  locked_since: string | null;
  lock_at: string | null;
  settings_locked: boolean;
  features: Partial<Record<PlanFeatureKey, boolean>>;
  // The cheapest plan that has each gated feature (a plan code), null when no plan has it
  required_plans: Partial<Record<PlanFeatureKey, string | null>>;
  devices: { pos: DeviceEntitlement; kds: DeviceEntitlement; waiter: DeviceEntitlement };
  notice: BillingNotice | null;
}

// The route is not in the generated spec: its path is borrowed from a neighbour (the cast keeps the call typed)
const ENTITLEMENTS_PATH = '/v1/owner/billing/entitlements' as '/v1/owner/subscription';

// GET /owner/billing/entitlements: any member of the store reads it (no finance.view needed)
export async function fetchEntitlements(api: StaffApi): Promise<Entitlements> {
  return unwrap(await api.client.GET(ENTITLEMENTS_PATH)).data as unknown as Entitlements;
}

// --- Reading it ------------------------------------------------------------------------------------------------------

// Is the feature on? Base features, a store on a legacy plan and anything the server did not list are on: the screen only
// hides what the server said is off, and the server refuses the write anyway.
export function hasFeature(entitlements: Pick<Entitlements, 'features' | 'legacy'> | null | undefined, feature: PlanFeatureKey): boolean {
  if (!entitlements || entitlements.legacy) return true;
  if ((BASE_FEATURES as readonly string[]).includes(feature)) return true;
  return entitlements.features[feature] !== false;
}

// Plan names as the owner knows them, for "<name> tarifida bor". The server sends the code of the cheapest plan with the
// feature; a refusal also carries its name.
const PLAN_NAMES: Record<string, string> = { start: 'Start', asosiy: 'Asosiy', pro: 'Pro' };

export function planName(code: string | null | undefined): string | null {
  if (!code) return null;
  return PLAN_NAMES[code] ?? code.charAt(0).toUpperCase() + code.slice(1);
}

// The plan that has the feature, to show next to "tarifda bor"; null when none is known
export function requiredPlanName(entitlements: Pick<Entitlements, 'required_plans'> | null | undefined, feature: PlanFeatureKey): string | null {
  return planName(entitlements?.required_plans?.[feature]);
}

const DAY_MS = 86_400_000;

// Days until the settings close, in whole days from `now` (0: today); null without a date or when they are closed already
export function daysUntilLock(entitlements: Pick<Entitlements, 'lock_at' | 'settings_locked'> | null | undefined, now: Date = new Date()): number | null {
  if (!entitlements?.lock_at || entitlements.settings_locked) return null;
  const left = Date.parse(entitlements.lock_at) - now.getTime();
  if (Number.isNaN(left)) return null;
  return Math.max(0, Math.ceil(left / DAY_MS));
}

// Trial days left (whole days, rounded up; 0 when it ends today); null outside a trial
export function trialDaysLeft(entitlements: Pick<Entitlements, 'status' | 'trial_ends_at'> | null | undefined, now: Date = new Date()): number | null {
  if (entitlements?.status !== 'trialing' || !entitlements.trial_ends_at) return null;
  const left = Date.parse(entitlements.trial_ends_at) - now.getTime();
  if (Number.isNaN(left)) return null;
  return Math.max(0, Math.ceil(left / DAY_MS));
}

// --- The server's refusals ---------------------------------------------------------------------------------------------

export const BILLING_REFUSAL_REASONS = ['plan_feature_missing', 'subscription_locked', 'device_limit'] as const;
export type BillingRefusalReason = (typeof BILLING_REFUSAL_REASONS)[number];

export interface BillingRefusal {
  reason: BillingRefusalReason;
  // plan_feature_missing: the feature, the cheapest plan that has it and its name
  feature: string | null;
  requiredPlan: string | null;
  requiredPlanName: string | null;
  // subscription_locked: since when
  lockedSince: string | null;
  // device_limit: the type and the counts
  deviceType: string | null;
  included: number | null;
  active: number | null;
  // The server's own plain words (Uzbek)
  message: string | undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const str = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value : null);
const int = (value: unknown): number | null => (typeof value === 'number' && Number.isFinite(value) ? value : null);

// A 403 plan_feature_missing / subscription_locked / device_limit, read from the error of any request; undefined for any
// other error. The body is {code, feature?, required_plan?, data: {reason, ...}}.
export function billingRefusalOf(err: unknown): BillingRefusal | undefined {
  if (!isStaffApiError(err) || getApiErrorStatus(err) !== 403) return undefined;
  const body = isRecord(err.body) ? err.body : {};
  const data = isRecord(body.data) ? body.data : {};
  const reason = err.reason ?? str(body.code);
  if (!reason || !(BILLING_REFUSAL_REASONS as readonly string[]).includes(reason)) return undefined;
  const requiredPlan = str(data.required_plan) ?? str(body.required_plan);
  return {
    reason: reason as BillingRefusalReason,
    feature: str(data.feature) ?? str(body.feature),
    requiredPlan,
    requiredPlanName: str(data.required_plan_name) ?? planName(requiredPlan),
    lockedSince: str(data.locked_since),
    deviceType: str(data.device_type),
    included: int(data.included),
    active: int(data.active),
    message: err.hasServerMessage ? err.message : undefined,
  };
}

export const isBillingRefusal = (err: unknown): boolean => billingRefusalOf(err) !== undefined;

// --- The plan and invoice rows with the Billing v2 fields -------------------------------------------------------------------
// GET /owner/subscription and /owner/invoices answer the P1-23 shapes of the generated spec plus these fields (new in P4-21).

export type SubscriptionOverview = Subscription;
export type SubscriptionPlanState = NonNullable<Subscription['subscription']> & {
  billing_period?: 'monthly' | 'yearly';
  next_billing_period?: 'monthly' | 'yearly' | null;
  term_ends_at?: string | null;
};

export function subscriptionState(overview: Subscription | undefined): SubscriptionPlanState | null {
  return (overview?.subscription as SubscriptionPlanState | null | undefined) ?? null;
}

export type InvoiceRow = InvoicePage['data'][number] & {
  // monthly | yearly | upgrade ...
  kind?: string;
  // What the ledger paid of it, a decimal string
  paid_amount?: string;
};

// What is still unpaid of an invoice (so'm); 0 for a paid or void one
export function invoiceOutstanding(invoice: Pick<InvoiceRow, 'amount' | 'status'> & { paid_amount?: string | null }): number {
  if (invoice.status !== 'issued') return 0;
  const paid = Number(invoice.paid_amount ?? 0);
  return Math.max(0, Math.round((invoice.amount - (Number.isFinite(paid) ? paid : 0)) * 100) / 100);
}
