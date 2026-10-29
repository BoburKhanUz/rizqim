'use client';

import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Mijozlar va yordam (P4-31, docs/API.md "Mijozlar va yordam"): the users list and the user card with its actions (block,
// unblock, close sessions), the complaints inbox (take, assign, reply through the bot, resolve), tayyor javoblar and
// Shubhali holatlar. Typed by hand until the generated client is regenerated (the shapes follow AdminCustomer*,
// AdminComplaint*, AdminSupport* controllers and App\Services\Admin\Customers). Money is a decimal string ("120000.00"),
// times are ISO 8601. Phones come masked unless the member has pii.view.

export interface Named {
  id: number;
  name: string;
}

export type RoleFilter = '' | 'customer' | 'courier' | 'staff' | 'owner' | 'manager' | 'cashier' | 'waiter' | 'cook';
export const ROLE_FILTERS: readonly RoleFilter[] = ['', 'customer', 'courier', 'staff'];

export interface UserRow {
  id: number;
  name: string;
  role: string;
  role_label: string;
  phone: string | null;
  telegram_linked: boolean;
  store: Named | null;
  branch: Named | null;
  orders_count: number;
  blocked: boolean;
  blocked_at: string | null;
  created_at: string | null;
}

export interface UserPage {
  data: UserRow[];
  next_cursor: string | null;
  per_page: number;
  roles: Record<string, string>;
}

export interface UserFilters {
  q: string;
  role: RoleFilter;
  status: '' | 'active' | 'blocked';
}

export const DEFAULT_USER_FILTERS: UserFilters = { q: '', role: '', status: '' };

export interface AuditEntry {
  id: number;
  action: string;
  actor: { id: number; name: string; team: boolean } | null;
  reason: string | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  created_at: string | null;
}

export interface UserCard {
  profile: {
    id: number;
    uuid: string;
    name: string;
    role: string;
    role_label: string;
    phone: string | null;
    phone_verified: boolean;
    telegram: { linked: boolean; username: string | null };
    courier_type: { value: string; label: string } | null;
    referred_by: Named | null;
    referrals: number;
    created_at: string | null;
  };
  block: { blocked: boolean; at: string | null; reason: string | null; by: Named | null };
  stats: {
    orders: number;
    delivered: number;
    cancelled: number;
    delivered_sum: string;
    avg_check: string | null;
    last_order_at: string | null;
    segment: { key: 'vip' | 'returning' | 'new' | 'none'; label: string };
  };
  orders: { uuid: string; short_code: string | null; store: Named | null; status: string; status_label: string; total: string; payment_method: string | null; created_at: string | null; href: string }[] | null;
  refunds: { uuid: string; order: { uuid: string; short_code: string | null } | null; amount: string; status: string; status_label: string; reason: string | null; created_at: string | null; decided_at: string | null }[] | null;
  keshbek: { balance: string; entries: { kind: 'spent' | 'refunded' | 'referral'; label: string; amount: string; order: { uuid: string; short_code: string | null } | null; at: string | null }[] };
  complaints: { uuid: string; type: string; type_label: string; support_status: SupportStatus; support_status_label: string; description: string | null; order: { uuid: string; short_code: string | null } | null; created_at: string | null }[] | null;
  sessions: {
    active_tokens: number;
    refresh_sessions: number;
    last_used_at: string | null;
    tokens: { id: number; kind: 'mini_app' | 'app' | 'staff_pin'; kind_label: string; device: string | null; created_at: string | null; last_used_at: string | null; expires_at: string | null }[];
    devices: { uuid: string; type: string; type_label: string; name: string; platform: string | null; app_version: string | null; last_seen_at: string | null }[];
  };
  membership: { store: { id: number; name: string; status: string } | null; branch: Named | null; role: string; role_label: string; login_phone: boolean; login_phone_proof: 'sms' | 'vouched' | null; pin_set: boolean } | null;
  linked_accounts: { id: number; name: string; role: string; role_label: string; store: Named | null; same: ('phone' | 'telegram')[]; blocked: boolean; href: string }[];
  audit: AuditEntry[] | null;
  actions: { block: boolean; unblock: boolean; close_sessions: boolean };
  links: { audit: string };
}

export type SupportStatus = 'new' | 'in_progress' | 'answered' | 'resolved';
export type ComplaintFilter = 'open' | 'new' | 'in_progress' | 'answered' | 'resolved' | 'all';
export const COMPLAINT_FILTERS: readonly ComplaintFilter[] = ['open', 'new', 'in_progress', 'answered', 'resolved', 'all'];

export interface ComplaintRow {
  uuid: string;
  type: string;
  type_label: string;
  description: string | null;
  support_status: SupportStatus;
  support_status_label: string;
  store_flow: string;
  store_flow_label: string;
  escalated: boolean;
  store_replied: boolean;
  waiting: boolean;
  customer: Named | null;
  order: { uuid: string; short_code: string | null } | null;
  store: Named | null;
  assignee: Named | null;
  created_at: string | null;
  answered_at: string | null;
  resolved_at: string | null;
}

export interface ComplaintPage {
  data: ComplaintRow[];
  next_cursor: string | null;
  per_page: number;
  counts: Record<SupportStatus | 'open' | 'waiting', number>;
  waiting_hours: number;
  assignees: Named[];
  types: Record<string, string>;
  statuses: Record<SupportStatus, string>;
}

export interface ComplaintFilters {
  status: ComplaintFilter;
  assigned: '' | 'me' | 'none';
  waiting: boolean;
  q: string;
}

export const DEFAULT_COMPLAINT_FILTERS: ComplaintFilters = { status: 'open', assigned: '', waiting: false, q: '' };

export interface ComplaintCard extends Omit<ComplaintRow, 'customer' | 'order' | 'description'> {
  description: string;
  customer: { id: number; name: string; phone: string | null; telegram_linked: boolean; blocked: boolean; href: string } | null;
  order: { uuid: string; short_code: string | null; status: string; status_label: string; total: string; created_at: string | null; href: string } | null;
  store_reply: string | null;
  replies: { id: number; body: string; author: Named | null; channel: string; created_at: string | null }[];
  resolution: { note: string | null; by: Named | null; at: string | null } | null;
  history: { id: number; action: string; actor: Named | null; before: Record<string, unknown> | null; after: Record<string, unknown> | null; created_at: string | null }[];
  actions: { manage: boolean; reply: boolean; resolve: boolean; reopen: boolean };
}

export interface CannedReply {
  id: number;
  title: string;
  body: string;
  sort_order: number;
  updated_by: Named | null;
  updated_at: string | null;
}

export interface SuspiciousRow {
  kind: 'cancellations' | 'promo_sharing' | 'refunds';
  user: { id: number; name: string; role_label: string | null; blocked: boolean };
  accounts: { id: number; name: string; role_label: string | null; blocked: boolean }[];
  reason: string;
  metric: number | string;
  since: string | null;
  href: string;
  promo_code?: string;
  shared_by?: 'phone' | 'telegram';
}

export interface Suspicious {
  generated_at: string | null;
  groups: { kind: SuspiciousRow['kind']; label: string; description: string; rows: SuspiciousRow[] }[];
}

// --- Calls ------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;

export function userQuery(filters: UserFilters, cursor: string | null): Record<string, string | number> {
  const query: Record<string, string | number> = { per_page: 30 };
  if (filters.role) query.role = filters.role;
  if (filters.status) query.status = filters.status;
  if (filters.q.trim().length >= 2) query.q = filters.q.trim();
  if (cursor) query.cursor = cursor;
  return query;
}

export function complaintQuery(filters: ComplaintFilters, cursor: string | null): Record<string, string | number> {
  const query: Record<string, string | number> = { status: filters.status, per_page: 30 };
  if (filters.assigned) query.assigned = filters.assigned;
  if (filters.waiting) query.waiting = 1;
  if (filters.q.trim().length >= 2) query.q = filters.q.trim();
  if (cursor) query.cursor = cursor;
  return query;
}

export const listUsers = (filters: UserFilters, cursor: string | null, signal?: AbortSignal) => data<UserPage>(call('GET', '/users', { query: userQuery(filters, cursor), signal }));
export const getUser = (id: number, signal?: AbortSignal) => data<UserCard>(call('GET', `/users/${id}`, { signal }));
export const blockUser = (id: number, reason: string) => data<UserCard>(call('POST', `/users/${id}/block`, { body: { reason } }));
export const unblockUser = (id: number, reason: string) => data<UserCard>(call('POST', `/users/${id}/unblock`, { body: { reason } }));
export const closeSessions = (id: number, reason: string) => data<UserCard>(call('POST', `/users/${id}/close-sessions`, { body: { reason } }));

export const listComplaints = (filters: ComplaintFilters, cursor: string | null, signal?: AbortSignal) => data<ComplaintPage>(call('GET', '/complaints', { query: complaintQuery(filters, cursor), signal }));
export const getComplaint = (uuid: string, signal?: AbortSignal) => data<ComplaintCard>(call('GET', `/complaints/${encodeURIComponent(uuid)}`, { signal }));
export const updateComplaint = (uuid: string, change: { support_status?: SupportStatus; assigned_to?: number | null }) => data<ComplaintCard>(call('PATCH', `/complaints/${encodeURIComponent(uuid)}`, { body: change }));
export const replyToComplaint = (uuid: string, text: string, cannedReplyId: number | null) =>
  data<ComplaintCard>(call('POST', `/complaints/${encodeURIComponent(uuid)}/reply`, { body: { text, ...(cannedReplyId ? { canned_reply_id: cannedReplyId } : {}) } }));
export const resolveComplaint = (uuid: string, note: string) => data<ComplaintCard>(call('POST', `/complaints/${encodeURIComponent(uuid)}/resolve`, { body: { note } }));

export const listCannedReplies = (signal?: AbortSignal) => data<CannedReply[]>(call('GET', '/support/canned-replies', { signal }));
export const createCannedReply = (input: { title: string; body: string; sort_order?: number }) => data<CannedReply>(call('POST', '/support/canned-replies', { body: input }));
export const updateCannedReply = (id: number, change: { title?: string; body?: string; sort_order?: number }) => data<CannedReply>(call('PATCH', `/support/canned-replies/${id}`, { body: change }));
export const deleteCannedReply = async (id: number): Promise<void> => {
  await call('DELETE', `/support/canned-replies/${id}`);
};

export const getSuspicious = (signal?: AbortSignal) => data<Suspicious>(call('GET', '/support/suspicious', { signal }));

// --- Hooks ------------------------------------------------------------------------------------------------------------

export const CUSTOMER_KEYS = {
  all: ['admin-customers'] as const,
  users: (filters: UserFilters) => ['admin-customers', 'users', filters] as const,
  user: (id: number) => ['admin-customers', 'user', id] as const,
  complaints: (filters: ComplaintFilters) => ['admin-customers', 'complaints', filters] as const,
  complaint: (uuid: string) => ['admin-customers', 'complaint', uuid] as const,
  canned: ['admin-customers', 'canned'] as const,
  suspicious: ['admin-customers', 'suspicious'] as const,
};

export const useUsers = (filters: UserFilters, enabled = true) =>
  useInfiniteQuery({
    queryKey: CUSTOMER_KEYS.users(filters),
    queryFn: ({ pageParam, signal }) => listUsers(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    placeholderData: keepPreviousData,
  });

export const useUser = (id: number, enabled = true) => useQuery({ queryKey: CUSTOMER_KEYS.user(id), queryFn: ({ signal }) => getUser(id, signal), enabled });

export const useComplaints = (filters: ComplaintFilters, enabled = true) =>
  useInfiniteQuery({
    queryKey: CUSTOMER_KEYS.complaints(filters),
    queryFn: ({ pageParam, signal }) => listComplaints(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    placeholderData: keepPreviousData,
    refetchInterval: 60_000,
  });

export const useComplaint = (uuid: string, enabled = true) => useQuery({ queryKey: CUSTOMER_KEYS.complaint(uuid), queryFn: ({ signal }) => getComplaint(uuid, signal), enabled });

export const useCannedReplies = (enabled = true) => useQuery({ queryKey: CUSTOMER_KEYS.canned, queryFn: ({ signal }) => listCannedReplies(signal), enabled, staleTime: 60_000 });

export const useSuspicious = (enabled = true) => useQuery({ queryKey: CUSTOMER_KEYS.suspicious, queryFn: ({ signal }) => getSuspicious(signal), enabled });

// Every write places what it answered (a user card or a complaint card) and refreshes the section's lists
function useCustomerWrite<I, R>(fn: (input: I) => Promise<R>, place?: (client: ReturnType<typeof useQueryClient>, result: R) => void) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (result) => {
      place?.(client, result);
      void client.invalidateQueries({ queryKey: CUSTOMER_KEYS.all });
    },
  });
}

export type UserAction = { kind: 'block' | 'unblock' | 'close_sessions'; reason: string };

export const useUserAction = (id: number) =>
  useCustomerWrite(
    (action: UserAction) => (action.kind === 'block' ? blockUser(id, action.reason) : action.kind === 'unblock' ? unblockUser(id, action.reason) : closeSessions(id, action.reason)),
    (client, card) => client.setQueryData(CUSTOMER_KEYS.user(card.profile.id), card),
  );

const placeComplaint = (client: ReturnType<typeof useQueryClient>, card: ComplaintCard) => client.setQueryData(CUSTOMER_KEYS.complaint(card.uuid), card);

export const useUpdateComplaint = (uuid: string) =>
  useCustomerWrite((change: { support_status?: SupportStatus; assigned_to?: number | null }) => updateComplaint(uuid, change), placeComplaint);
export const useReplyToComplaint = (uuid: string) =>
  useCustomerWrite((input: { text: string; canned_reply_id: number | null }) => replyToComplaint(uuid, input.text, input.canned_reply_id), placeComplaint);
export const useResolveComplaint = (uuid: string) => useCustomerWrite((note: string) => resolveComplaint(uuid, note), placeComplaint);

export const useCreateCannedReply = () => useCustomerWrite(createCannedReply);
export const useUpdateCannedReply = () => useCustomerWrite((input: { id: number; title: string; body: string }) => updateCannedReply(input.id, { title: input.title, body: input.body }));
export const useDeleteCannedReply = () => useCustomerWrite((id: number) => deleteCannedReply(id));
