'use client';

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Ovqatxonalar (P4-22, docs/API.md "Ovqatxonalar"): the stores list with health, plan and debt; the 360° card and its
// actions; the onboarding pipeline (applications); CRM-lite (notes, tasks, next contact); announcements to owners; the
// store and region picker. Typed by hand until the generated client is regenerated (the shapes follow AdminStore*Controller
// and App\Services\Admin\Stores\StorePresenter). Money is a decimal string ("120000.00"), times are ISO 8601, dates Y-m-d.

export interface Named {
  id: number;
  name: string;
}

export type HealthLevel = 'good' | 'attention' | 'churn_risk';
export const HEALTH_LEVELS: readonly HealthLevel[] = ['good', 'attention', 'churn_risk'];

export interface HealthBrief {
  level: HealthLevel;
  label: string;
  score: number;
  reasons: number;
  top_reason: string | null;
  computed_at: string | null;
}

export interface HealthReason {
  key: string;
  level: 'attention' | 'churn_risk';
  points: number;
  label: string;
}

export interface PlanBrief {
  code: string;
  name: string;
  status: string;
  status_label: string;
}

export interface StoreRow {
  id: number;
  uuid: string;
  name: string;
  status: string;
  status_label: string;
  is_active: boolean;
  is_premium: boolean;
  is_featured: boolean;
  region: Named | null;
  branches_count: number;
  owner: { id: number; name: string; telegram_linked: boolean } | null;
  plan: PlanBrief | null;
  debt_balance: string;
  // P4-21: debt = subscription debt (unpaid invoices) + commission debt; the credit limit counts the latter only
  subscription_debt: string;
  commission_debt: string;
  credit_limit: string;
  over_limit: boolean;
  health: HealthBrief | null;
  next_contact_on: string | null;
  created_at: string | null;
}

export interface StorePage {
  data: StoreRow[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  health_counts: Record<'all' | HealthLevel | 'none', number>;
  statuses: Record<string, string>;
}

export interface StoreFilters {
  q: string;
  status: string;
  region_id: string;
  health: '' | HealthLevel | 'none';
  plan: string;
  debt: '' | 'has_debt' | 'over_limit';
  sort: 'newest' | 'name' | 'debt' | 'health';
  page: number;
}

export const DEFAULT_STORE_FILTERS: StoreFilters = { q: '', status: '', region_id: '', health: '', plan: '', debt: '', sort: 'newest', page: 1 };

export interface AuditEntry {
  id: number;
  action: string;
  actor: { id: number; name: string; team: boolean } | null;
  reason: string | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  created_at: string | null;
}

export interface Note {
  id: number;
  body: string;
  author: Named | null;
  store_id: number | null;
  application_id: number | null;
  created_at: string | null;
}

export type TaskState = 'overdue' | 'today' | 'upcoming' | 'done';

export interface Task {
  id: number;
  title: string;
  due_on: string;
  state: TaskState;
  assignee: Named | null;
  created_by: Named | null;
  store: Named | null;
  application: { uuid: string; name: string } | null;
  done_at: string | null;
  created_at: string | null;
}

export interface Crm {
  next_contact_on: string | null;
  notes: Note[];
  tasks: Task[];
}

export interface ApplicationRow {
  id: number;
  uuid: string;
  name: string;
  format: string;
  format_label: string;
  region: Named | null;
  city: string | null;
  owner_name: string;
  source: string;
  stage: string;
  stage_label: string;
  stage_changed_at: string | null;
  assignee: Named | null;
  due_on: string | null;
  overdue: boolean;
  store: { id: number; name: string; status: string } | null;
  created_at: string | null;
}

export interface ChecklistItem {
  key: string;
  label: string;
  required: boolean;
  detect: boolean;
  done: boolean;
  ticked: boolean;
  detected: boolean;
  done_at: string | null;
  done_by: number | null;
}

export interface ChecklistStage {
  stage: string;
  label: string;
  current: boolean;
  passed: boolean;
  items: ChecklistItem[];
}

export interface ApplicationCard extends ApplicationRow {
  phone: string | null;
  tin: string | null;
  comment: string | null;
  rejected_reason: string | null;
  rejected_at: string | null;
  converted_at: string | null;
  activated_at: string | null;
  checklist: ChecklistStage[];
  crm: Crm;
  audit: AuditEntry[];
  actions: { manage: boolean; convert: boolean; reject: boolean };
}

export interface ApplicationPage {
  data: ApplicationRow[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  stage_counts: Record<string, number>;
  stages: { key: string; label: string }[];
  formats: { key: string; label: string }[];
}

export const PIPELINE_STAGES = ['application', 'review', 'menu', 'devices', 'training', 'trial', 'active'] as const;

export interface StoreCard {
  profile: {
    id: number;
    uuid: string;
    name: string;
    status: string;
    status_label: string;
    is_active: boolean;
    is_open: boolean;
    is_premium: boolean;
    is_featured: boolean;
    is_order_locked: boolean;
    phone: string | null;
    address: string | null;
    description: string | null;
    logo_url: string | null;
    tin: string | null;
    legal_name: string | null;
    tax_regime: string | null;
    payment_account_type: string;
    commission_rate: string;
    rating: string;
    review_count: number;
    created_at: string | null;
    // P4-32: whether the owner signs in by SMS code, and who proved the number (sms: the owner, vouched: RIZQIM)
    owner_login_phone?: { set: boolean; via: 'sms' | 'vouched' | null };
    telegram: { store_chat_linked: boolean; owner_linked: boolean; owner_invite_pending: boolean };
  };
  application: ApplicationRow | null;
  contacts: { kind: 'staff' | 'application'; user_id: number | null; name: string; role: string; role_label: string; phone: string | null; telegram_linked: boolean; branch_id: number | null }[];
  branches: { id: number; name: string; address: string | null; phone: string | null; region: Named | null; district: Named | null; is_active: boolean; open_time: string | null; close_time: string | null }[];
  status_history: { from_status: string | null; from_label: string | null; to_status: string; to_label: string; by: Named | null; comment: string | null; created_at: string | null }[];
  // P4-21's store billing (BillingPresenter::store, as GET /admin/billing/stores/{id})
  plan: {
    source: string;
    subscription: {
      status: string;
      status_label: string;
      plan: { code: string; name: string; version?: number };
      billing_period: string;
      next_billing_period: string | null;
      next_plan: { code: string; name: string } | null;
      cancel_at_period_end: boolean;
      trial_ends_at: string | null;
      current_period: { start: string; end: string };
      term_ends_at: string | null;
      locked_at: string | null;
    } | null;
    entitlements: {
      plan: { code: string; name: string } | null;
      status: string | null;
      settings_locked: boolean;
      locked_since: string | null;
      features: Record<string, boolean>;
      devices: Record<string, { included: number | null; active: number; extra: number } & Record<string, unknown>>;
    } & Record<string, unknown>;
    debt: { debt_balance: string; subscription_debt: string; commission_debt: string; credit_limit: string; over_credit_limit: boolean };
    commission: { rates: Record<string, { rate: string; source: string; default: string }>; overrides: Record<string, string>; flat_rate: string | null; delivery_courier: string };
    history: unknown[];
    invoices: { uuid: string; number: string; kind: string; period: string; amount: string; paid_amount: string; outstanding: string; status: string; due_date: string }[];
  };
  devices: {
    terminals: number;
    terminals_online: number;
    by_type: Record<string, number>;
    items: { uuid: string; name: string; type: string; type_label: string; terminal: boolean; branch: Named | null; platform: string | null; app_version: string | null; last_seen_at: string | null; online: boolean; paired_at: string | null }[];
  };
  fiscal: { enabled: boolean; driver: string | null; tax_regime: string | null; failed_7d: number; pending: number; missing_mxik: number; last_failed: { uuid: string; error: string | null; total: string; created_at: string | null }[] };
  finance: {
    debt_balance: string;
    subscription_debt: string;
    commission_debt: string;
    wallet_balance: string;
    credit_limit: string;
    over_limit: boolean;
    min_payout_reserve: string;
    available_for_payout: string;
    payouts: { id: number; amount: string; card: string | null; status: string; created_at: string | null; processed_at: string | null }[];
    ledger: { id: number; type: string; amount: string; balance_after: string; notes: string | null; created_at: string | null }[];
  };
  orders: {
    quality: {
      orders: number;
      acceptance_rate: number | null;
      avg_accept_minutes: number | null;
      avg_prep_minutes: number | null;
      cancel_rate: number | null;
      rating: string | null;
      review_count: number;
    } | null;
    last_30_days: { channel: string; orders: number; total: string }[];
    recent: { uuid: string; short_code: string | null; status: string; status_label: string; channel: string; marketplace: boolean; total: string; created_at: string | null }[];
  };
  complaints: { open: number; last_30_days: number; items: { uuid: string; type: string; status: string; description: string; order: { uuid: string; short_code: string | null } | null; created_at: string | null }[] };
  health: { level: HealthLevel; label: string; score: number; reasons: HealthReason[]; metrics: Record<string, unknown>; computed_at: string };
  crm: Crm;
  audit: AuditEntry[];
  actions: {
    manage: boolean;
    suspend: boolean;
    activate: boolean;
    credit_limit: boolean;
    display: boolean;
    telegram_link: boolean;
    message_owner: boolean;
    commission_override: boolean;
    // P4-32: the payment account and payout reserve (stores.manage); the owner's login phone (stores.impersonate)
    payment_account?: boolean;
    owner_login_phone?: boolean;
  };
  links: { orders: string; audit: string; subscription: string };
}

export interface Lookup {
  stores: { id: number; name: string; status: string; region: string | null }[];
  regions: Named[];
  districts: Named[];
}

export interface BroadcastAudience {
  audience: 'all' | 'selected';
  store_ids?: number[];
  statuses?: string[];
  region_id?: number | null;
}

export interface BroadcastPreview {
  stores: number;
  recipients: number;
  without_telegram: number;
  sample: string[];
}

export interface Broadcast {
  uuid: string;
  audience: string;
  text: string;
  reason: string;
  stores: number;
  recipients: number;
  queued: number;
  status: string;
  created_by: Named | null;
  created_at: string | null;
  finished_at: string | null;
}

export interface ConvertInput {
  branch_region_id?: number | null;
  branch_district_id: number;
  branch_address: string;
  branch_lat: string;
  branch_lng: string;
  branch_radius?: string;
  owner_phone?: string;
  name?: string;
  reason: string;
}

// --- Calls ------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;

export function storeQuery(filters: StoreFilters): Record<string, string | number> {
  const query: Record<string, string | number> = { sort: filters.sort, page: filters.page, per_page: 30 };
  for (const name of ['status', 'region_id', 'health', 'plan', 'debt'] as const) if (filters[name]) query[name] = filters[name];
  if (filters.q.trim().length >= 2) query.q = filters.q.trim();
  return query;
}

export const listStores = (filters: StoreFilters, signal?: AbortSignal) => data<StorePage>(call('GET', '/stores', { query: storeQuery(filters), signal }));
export const getStore = (id: number, signal?: AbortSignal) => data<StoreCard>(call('GET', `/stores/${id}`, { signal }));
export const lookup = (q: string, signal?: AbortSignal, id?: number | null, districtsOf?: number | null) =>
  data<Lookup>(call('GET', '/stores/lookup', { query: { ...(id ? { 'ids[]': id } : { q: q.trim() || undefined }), districts_of: districtsOf ?? undefined }, signal }));

export const suspendStore = (id: number, reason: string) => data<StoreCard>(call('POST', `/stores/${id}/suspend`, { body: { reason } }));
export const activateStore = (id: number, reason: string) => data<StoreCard>(call('POST', `/stores/${id}/activate`, { body: { reason } }));
export const setCreditLimit = (id: number, creditLimit: string, reason: string) => data<StoreCard>(call('PUT', `/stores/${id}/credit-limit`, { body: { credit_limit: creditLimit, reason } }));
export const setDisplay = (id: number, flags: { is_premium?: boolean; is_featured?: boolean }, reason: string) =>
  data<StoreCard>(call('PUT', `/stores/${id}/display`, { body: { ...flags, reason } }));
export const newTelegramLink = (id: number, reason: string) => data<{ invite_link: string | null; delivered_via: 'sms' | null; owner_linked: boolean }>(call('POST', `/stores/${id}/telegram-link`, { body: { reason } }));
export type PaymentAccountType = 'platform_account' | 'own_account';
export const setPaymentAccount = (id: number, change: { payment_account_type?: PaymentAccountType; min_payout_reserve?: string }, reason: string) =>
  data<StoreCard>(call('PUT', `/stores/${id}/payment-account`, { body: { ...change, reason } }));
export const setOwnerLoginPhone = (id: number, phone: string, reason: string) => data<StoreCard>(call('PUT', `/stores/${id}/owner-login-phone`, { body: { phone, reason } }));
export const messageOwner = (id: number, text: string, reason: string) => data<{ recipients: number }>(call('POST', `/stores/${id}/message`, { body: { text, reason } }));

export const addStoreNote = (id: number, body: string) => data<Note>(call('POST', `/stores/${id}/notes`, { body: { body } }));
export const setNextContact = (id: number, date: string | null) => data<{ next_contact_on: string | null }>(call('PUT', `/stores/${id}/next-contact`, { body: { next_contact_on: date } }));

export const listTasks = (query: { assignee_id?: string; due?: string; done?: boolean }, signal?: AbortSignal) =>
  data<{ tasks: Task[]; counts: { overdue: number; today: number; upcoming: number } }>(call('GET', '/store-tasks', { query: { ...query, done: query.done ? 1 : undefined }, signal }));
export const addTask = (input: { title: string; due_on: string; store_id?: number; application_uuid?: string; assignee_id?: number | null }) => data<Task>(call('POST', '/store-tasks', { body: input }));
export const updateTask = (id: number, change: { done?: boolean; title?: string; due_on?: string; assignee_id?: number | null }) => data<Task>(call('PATCH', `/store-tasks/${id}`, { body: change }));

export const listApplications = (query: { stage?: string; assignee_id?: string; q?: string }, signal?: AbortSignal) =>
  data<ApplicationPage>(call('GET', '/store-applications', { query: { ...query, per_page: 200 }, signal }));
export const getApplication = (uuid: string, signal?: AbortSignal) => data<ApplicationCard>(call('GET', `/store-applications/${encodeURIComponent(uuid)}`, { signal }));
export const createApplication = (input: { name: string; format: string; owner_name: string; phone: string; region_id?: number | null; city?: string; tin?: string; comment?: string }) =>
  data<ApplicationCard>(call('POST', '/store-applications', { body: input }));
export const updateApplication = (uuid: string, change: Record<string, unknown>) => data<ApplicationCard>(call('PATCH', `/store-applications/${encodeURIComponent(uuid)}`, { body: change }));
export const moveApplication = (uuid: string, input: { stage: string; note?: string; reason?: string }) =>
  data<ApplicationCard>(call('POST', `/store-applications/${encodeURIComponent(uuid)}/stage`, { body: input }));
export const tickChecklist = (uuid: string, item: string, done: boolean) => data<ApplicationCard>(call('POST', `/store-applications/${encodeURIComponent(uuid)}/checklist`, { body: { item, done } }));
export const convertApplication = (uuid: string, input: ConvertInput) =>
  data<{ application: ApplicationCard; store_id: number; invite_link: string }>(call('POST', `/store-applications/${encodeURIComponent(uuid)}/convert`, { body: input }));
export const rejectApplication = (uuid: string, reason: string) => data<ApplicationCard>(call('POST', `/store-applications/${encodeURIComponent(uuid)}/reject`, { body: { reason } }));
export const addApplicationNote = (uuid: string, body: string) => data<Note>(call('POST', `/store-applications/${encodeURIComponent(uuid)}/notes`, { body: { body } }));

export const listBroadcasts = (signal?: AbortSignal) => data<{ broadcasts: Broadcast[]; limit_per_day: number; sent_today: number }>(call('GET', '/store-broadcasts', { signal }));
export const previewBroadcast = (audience: BroadcastAudience) => data<BroadcastPreview>(call('POST', '/store-broadcasts/preview', { body: audience }));
export const sendBroadcast = (input: BroadcastAudience & { text: string; recipients: number; reason: string }) =>
  data<{ uuid: string; recipients: number; stores: number; status: string }>(call('POST', '/store-broadcasts', { body: input }));

// --- Hooks ------------------------------------------------------------------------------------------------------------

export const STORE_KEYS = {
  all: ['admin-stores'] as const,
  list: (filters: StoreFilters) => ['admin-stores', 'list', filters] as const,
  card: (id: number) => ['admin-stores', 'card', id] as const,
  lookup: (q: string, id: number | null) => ['admin-stores', 'lookup', q, id] as const,
  applications: (query: Record<string, string | undefined>) => ['admin-stores', 'applications', query] as const,
  application: (uuid: string) => ['admin-stores', 'application', uuid] as const,
  tasks: (query: Record<string, unknown>) => ['admin-stores', 'tasks', query] as const,
  broadcasts: ['admin-stores', 'broadcasts'] as const,
};

export const useStores = (filters: StoreFilters, enabled = true) =>
  useQuery({ queryKey: STORE_KEYS.list(filters), queryFn: ({ signal }) => listStores(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: 60_000 });

export const useStore = (id: number, enabled = true) => useQuery({ queryKey: STORE_KEYS.card(id), queryFn: ({ signal }) => getStore(id, signal), enabled });

export const useLookup = (q: string, id: number | null = null, enabled = true) =>
  useQuery({ queryKey: STORE_KEYS.lookup(q, id), queryFn: ({ signal }) => lookup(q, signal, id), enabled, staleTime: 60_000, placeholderData: keepPreviousData });

// The active districts of a region (the convert form's branch district)
export const useDistricts = (regionId: number | null) =>
  useQuery({ queryKey: ['admin-stores', 'districts', regionId] as const, queryFn: ({ signal }) => lookup('', signal, null, regionId), enabled: regionId !== null, staleTime: 300_000, select: (result) => result.districts });

export const useApplications = (query: { stage?: string; assignee_id?: string; q?: string }, enabled = true) =>
  useQuery({ queryKey: STORE_KEYS.applications(query), queryFn: ({ signal }) => listApplications(query, signal), enabled, placeholderData: keepPreviousData, refetchInterval: 60_000 });

export const useApplication = (uuid: string, enabled = true) => useQuery({ queryKey: STORE_KEYS.application(uuid), queryFn: ({ signal }) => getApplication(uuid, signal), enabled });

export const useTasks = (query: { assignee_id?: string; due?: string; done?: boolean }, enabled = true) =>
  useQuery({ queryKey: STORE_KEYS.tasks(query), queryFn: ({ signal }) => listTasks(query, signal), enabled });

export const useBroadcasts = (enabled = true) => useQuery({ queryKey: STORE_KEYS.broadcasts, queryFn: ({ signal }) => listBroadcasts(signal), enabled });

// Every write refreshes what it answered (a store card or an application card) and the section's lists
function useStoreWrite<I, R>(fn: (input: I) => Promise<R>, place?: (client: ReturnType<typeof useQueryClient>, result: R, input: I) => void) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (result, input) => {
      place?.(client, result, input);
      void client.invalidateQueries({ queryKey: STORE_KEYS.all });
    },
  });
}

const placeCard = (client: ReturnType<typeof useQueryClient>, card: StoreCard) => client.setQueryData(STORE_KEYS.card(card.profile.id), card);
const placeApplication = (client: ReturnType<typeof useQueryClient>, card: ApplicationCard) => client.setQueryData(STORE_KEYS.application(card.uuid), card);

export type StoreAction =
  | { kind: 'suspend'; reason: string }
  | { kind: 'activate'; reason: string }
  | { kind: 'credit_limit'; credit_limit: string; reason: string }
  | { kind: 'display'; is_premium: boolean; is_featured: boolean; reason: string }
  | { kind: 'payment_account'; payment_account_type?: PaymentAccountType; min_payout_reserve?: string; reason: string }
  | { kind: 'owner_login_phone'; phone: string; reason: string };

export const useStoreAction = (id: number) =>
  useStoreWrite(
    (action: StoreAction) => {
      switch (action.kind) {
        case 'suspend':
          return suspendStore(id, action.reason);
        case 'activate':
          return activateStore(id, action.reason);
        case 'credit_limit':
          return setCreditLimit(id, action.credit_limit, action.reason);
        case 'display':
          return setDisplay(id, { is_premium: action.is_premium, is_featured: action.is_featured }, action.reason);
        case 'payment_account':
          return setPaymentAccount(id, { payment_account_type: action.payment_account_type, min_payout_reserve: action.min_payout_reserve }, action.reason);
        case 'owner_login_phone':
          return setOwnerLoginPhone(id, action.phone, action.reason);
      }
    },
    (client, card) => placeCard(client, card),
  );

export const useTelegramLink = (id: number) => useStoreWrite((reason: string) => newTelegramLink(id, reason));
export const useMessageOwner = (id: number) => useStoreWrite((input: { text: string; reason: string }) => messageOwner(id, input.text, input.reason));
export const useStoreNote = (id: number) => useStoreWrite((body: string) => addStoreNote(id, body));
export const useNextContact = (id: number) => useStoreWrite((date: string | null) => setNextContact(id, date));
export const useAddTask = () => useStoreWrite(addTask);
export const useUpdateTask = () => useStoreWrite((input: { id: number; done?: boolean }) => updateTask(input.id, { done: input.done }));

export const useCreateApplication = () => useStoreWrite(createApplication, (client, card) => placeApplication(client, card));
export const useUpdateApplication = (uuid: string) => useStoreWrite((change: Record<string, unknown>) => updateApplication(uuid, change), (client, card) => placeApplication(client, card));
export const useMoveApplication = (uuid: string) =>
  useStoreWrite((input: { stage: string; note?: string; reason?: string }) => moveApplication(uuid, input), (client, card) => placeApplication(client, card));
export const useTick = (uuid: string) => useStoreWrite((input: { item: string; done: boolean }) => tickChecklist(uuid, input.item, input.done), (client, card) => placeApplication(client, card));
export const useConvert = (uuid: string) => useStoreWrite((input: ConvertInput) => convertApplication(uuid, input), (client, result) => placeApplication(client, result.application));
export const useReject = (uuid: string) => useStoreWrite((reason: string) => rejectApplication(uuid, reason), (client, card) => placeApplication(client, card));
export const useApplicationNote = (uuid: string) => useStoreWrite((body: string) => addApplicationNote(uuid, body));

export const usePreviewBroadcast = () => useMutation({ mutationFn: previewBroadcast });
export const useSendBroadcast = () => useStoreWrite(sendBroadcast);
