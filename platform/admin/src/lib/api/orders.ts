'use client';

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Buyurtmalar (P4-14, docs/API.md "Buyurtmalar"): the marketplace orders of every store, the order card, interventions,
// refund requests with four-eyes approval, the SLA limits and store quality. Typed by hand until the generated client is
// regenerated (the shapes follow AdminOrderController, AdminOrderInterventionController and AdminRefundRequestController).
// Money is a decimal string ("120000.00"), times are ISO 8601.

export const STATUS_GROUPS = ['active', 'new', 'kitchen', 'en_route', 'done', 'canceled'] as const;
export type StatusGroup = (typeof STATUS_GROUPS)[number];
export const SLA_FILTERS = ['late', 'warning'] as const;
export type SlaFilter = (typeof SLA_FILTERS)[number];
export const ORDER_CHANNELS = ['telegram', 'mobile'] as const;

export type SlaStage = 'accept' | 'prepare' | 'deliver';
export type SlaState = 'ok' | 'warning' | 'late';

export interface OrderSla {
  stage: SlaStage;
  stage_label: string;
  state: SlaState;
  limit_minutes: number;
  started_at: string;
  warning_at: string;
  due_at: string;
  late_by_minutes: number;
  minutes_left: number;
}

export interface Named {
  id: number;
  name: string;
}

export interface OrderRow {
  uuid: string;
  short_code: string | null;
  status: string;
  status_label: string;
  status_group: StatusGroup;
  type: string;
  channel: string;
  channel_label: string;
  store: Named | null;
  branch: Named | null;
  region: Named | null;
  customer_name: string | null;
  total: string;
  payment_method: string;
  payment_status: string;
  courier: Named | null;
  sla: OrderSla | null;
  refund_pending: boolean;
  manual_refund_required: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export type GroupCounts = Record<StatusGroup, number>;
export interface SlaLimits {
  accept: number;
  prepare: number;
  deliver: number;
}

export interface OrderPage {
  data: OrderRow[];
  next_cursor: string | null;
  per_page: number;
  counts: GroupCounts;
  sla_limits: SlaLimits;
  server_time: string;
}

export interface OrderFilters {
  status: StatusGroup;
  sla: SlaFilter | '';
  channel: string;
  store_id: string;
  region_id: string;
  from: string;
  to: string;
  q: string;
}

export const DEFAULT_FILTERS: OrderFilters = { status: 'active', sla: '', channel: '', store_id: '', region_id: '', from: '', to: '', q: '' };

export interface Actor {
  id: number;
  name: string;
  role: string;
  team: boolean;
}

export interface RefundExecution {
  mode: 'order_refunded' | 'manual';
  order_status_before: string;
  order_status_after: string;
  payment_method: string;
  automatic_amount: string;
  manual_amount: string;
  provider_refund: 'manual' | 'none';
}

export interface RefundRequest {
  id: number;
  uuid: string;
  amount: string;
  status: 'pending' | 'approved' | 'rejected';
  status_label: string;
  reason: string;
  requested_by: Named | null;
  requested_at: string | null;
  decided_by: Named | null;
  decided_at: string | null;
  decision_note: string | null;
  execution: RefundExecution | null;
  mine: boolean;
  can_decide: boolean;
}

export interface QueuedRefundRequest extends RefundRequest {
  order: { uuid: string; short_code: string | null; status: string; status_label: string; total: string; payment_method: string } | null;
  store: Named | null;
}

export interface SlaTimelineStage {
  stage: SlaStage;
  stage_label: string;
  limit_minutes: number;
  started_at: string;
  ended_at: string | null;
  took_minutes: number | null;
  running: boolean;
  state: SlaState;
  late_by_minutes: number;
}

export interface OrderCard extends Omit<OrderRow, 'store' | 'branch' | 'courier'> {
  notes: string | null;
  delivery_address: string | null;
  customer: { id: number; name: string | null; phone: string | null; has_telegram: boolean } | null;
  courier: { id: number; name: string; phone: string | null } | null;
  store: { id: number; name: string; phone: string | null } | null;
  branch: { id: number; name: string; address: string | null } | null;
  items: { id: number; name: string; variant: string | null; modifiers: string | null; quantity: string; unit_price: string; total: string; void: boolean }[];
  totals: { subtotal: string; discount: string; delivery_price: string; service_charge: string; place_fee: string; total: string; commission: string; payment_owner: string };
  cancel: { reason: string | null; reason_label: string | null; note: string | null } | null;
  payments: {
    uuid: string;
    provider: string;
    amount: string;
    status: string;
    paid_at: string | null;
    manual_refund_required: boolean;
    manual_refund_approved: { refund_request_id: number; amount: string; approved_at: string | null }[];
    created_at: string | null;
  }[];
  refunds: { paid: string; refunded: string; refundable: string; requests: RefundRequest[] };
  history: { status: string; status_label: string; from_status: string | null; at: string | null; actor: Actor | null; notes: string | null }[];
  sla_timeline: SlaTimelineStage[];
  sla_events: { stage: SlaStage; stage_label: string; state: 'warning' | 'late'; limit_minutes: number; crossed_at: string }[];
  fiscal_receipts: { uuid: string; type: string; status: string; provider: string | null; total: string; receipt_number: string | null; qr_url: string | null; issued_at: string | null; error: string | null }[];
  audit: { id: number; action: string; actor: Actor | null; reason: string | null; before: Record<string, unknown> | null; after: Record<string, unknown> | null; meta: Record<string, unknown> | null; created_at: string }[];
  actions: {
    can_intervene: boolean;
    can_cancel: boolean;
    cancel_reasons: { value: string; label: string }[];
    transitions: { status: string; label: string }[];
    message_store: boolean;
    message_customer: boolean;
    can_request_refund: boolean;
    can_approve_refund: boolean;
  };
}

export interface SlaSettings {
  limits: SlaLimits;
  defaults: SlaLimits;
  bounds: Record<keyof SlaLimits, [number, number]>;
  warning_ratio: number;
}

export interface StoreQualityRow {
  store: Named;
  orders: number;
  accepted: number;
  acceptance_rate: number | null;
  avg_accept_minutes: number | null;
  avg_prep_minutes: number | null;
  canceled: number;
  cancel_rate: number | null;
  rating: string | null;
  review_count: number;
  ratings_in_period: number;
  rating_in_period: number | null;
}

export interface StoreQuality {
  period: { from: string; to: string; days: number };
  stores: StoreQualityRow[];
}

// --- Calls ------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;

export function filterQuery(filters: OrderFilters): Record<string, string> {
  const query: Record<string, string> = { status: filters.status };
  for (const name of ['sla', 'channel', 'store_id', 'region_id', 'from', 'to'] as const) if (filters[name]) query[name] = filters[name];
  if (filters.q.trim().length >= 2) query.q = filters.q.trim();
  return query;
}

export const listOrders = (filters: OrderFilters, cursor: string | null, signal?: AbortSignal) =>
  data<OrderPage>(call('GET', '/orders', { query: { ...filterQuery(filters), per_page: 30, cursor }, signal }));

export const getOrder = (uuid: string, signal?: AbortSignal) => data<OrderCard>(call('GET', `/orders/${encodeURIComponent(uuid)}`, { signal }));

export const getSla = (signal?: AbortSignal) => data<SlaSettings>(call('GET', '/orders/sla', { signal }));
export const updateSla = (change: Partial<SlaLimits> & { reason: string }) => data<SlaSettings>(call('PUT', '/orders/sla', { body: change }));

export const getStoreQuality = (storeId: number | null, signal?: AbortSignal) =>
  data<StoreQuality>(call('GET', '/orders/store-quality', { query: { store_id: storeId }, signal }));

export const cancelOrder = (uuid: string, input: { cancel_reason: string; note?: string; reason: string }) =>
  data<OrderCard>(call('POST', `/orders/${encodeURIComponent(uuid)}/cancel`, { body: input }));

export const fixStatus = (uuid: string, input: { status: string; reason: string }) =>
  data<OrderCard>(call('POST', `/orders/${encodeURIComponent(uuid)}/status`, { body: input }));

export const messageOrder = (uuid: string, input: { to: 'store' | 'customer'; text: string; reason: string }) =>
  data<{ recipients: number }>(call('POST', `/orders/${encodeURIComponent(uuid)}/message`, { body: input }));

// Money actions carry an Idempotency-Key: one per dialog, so a retried click never runs twice (docs/API.md section 5)
export const requestRefund = (uuid: string, input: { amount: string; reason: string }, key: string) =>
  data<RefundRequest>(call('POST', `/orders/${encodeURIComponent(uuid)}/refund-requests`, { body: input, headers: { 'Idempotency-Key': key } }));

export const decideRefund = (id: number, decision: 'approve' | 'reject', reason: string, key: string) =>
  data<RefundRequest>(call('POST', `/refund-requests/${id}/${decision}`, { body: { reason }, headers: { 'Idempotency-Key': key } }));

export const listRefundRequests = (status: 'pending' | 'all', signal?: AbortSignal) =>
  data<{ data: QueuedRefundRequest[]; next_cursor: string | null }>(call('GET', '/refund-requests', { query: { status, per_page: 50 }, signal }));

export function idempotencyKey(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `k-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

// --- Hooks ------------------------------------------------------------------------------------------------------------

export const ORDER_KEYS = {
  all: ['admin-orders'] as const,
  list: (filters: OrderFilters) => ['admin-orders', 'list', filters] as const,
  card: (uuid: string) => ['admin-orders', 'card', uuid] as const,
  sla: ['admin-orders', 'sla'] as const,
  refunds: (status: string) => ['admin-orders', 'refund-requests', status] as const,
  quality: (storeId: number | null) => ['admin-orders', 'quality', storeId] as const,
};

// Polling while real-time is not live, slower while it is (an event refetches at once)
export const ORDERS_POLL_MS = 15_000;
export const ORDERS_LIVE_POLL_MS = 60_000;

export const useOrders = (filters: OrderFilters, { enabled = true, live = false } = {}) =>
  useInfiniteQuery({
    queryKey: ORDER_KEYS.list(filters),
    queryFn: ({ pageParam, signal }) => listOrders(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    refetchInterval: live ? ORDERS_LIVE_POLL_MS : ORDERS_POLL_MS,
  });

export const useOrder = (uuid: string, { enabled = true, live = false } = {}) =>
  useQuery({ queryKey: ORDER_KEYS.card(uuid), queryFn: ({ signal }) => getOrder(uuid, signal), enabled, refetchInterval: live ? ORDERS_LIVE_POLL_MS : ORDERS_POLL_MS });

export const useSla = (enabled = true) => useQuery({ queryKey: ORDER_KEYS.sla, queryFn: ({ signal }) => getSla(signal), enabled });

export const useRefundQueue = (enabled = true) =>
  useQuery({ queryKey: ORDER_KEYS.refunds('pending'), queryFn: ({ signal }) => listRefundRequests('pending', signal), enabled, refetchInterval: ORDERS_LIVE_POLL_MS });

export const useStoreQuality = (storeId: number | null, enabled = true) =>
  useQuery({ queryKey: ORDER_KEYS.quality(storeId), queryFn: ({ signal }) => getStoreQuality(storeId, signal), enabled });

// Every write refreshes the order's card (answered by most of them) and the lists
function useOrderWrite<I, R>(fn: (input: I) => Promise<R>, uuidOf: (input: I, result: R) => string | null) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (result, input) => {
      const uuid = uuidOf(input, result);
      if (uuid && result && typeof result === 'object' && 'actions' in (result as object)) client.setQueryData(ORDER_KEYS.card(uuid), result);
      void client.invalidateQueries({ queryKey: ORDER_KEYS.all });
    },
  });
}

export const useCancelOrder = () =>
  useOrderWrite(({ uuid, ...body }: { uuid: string; cancel_reason: string; note?: string; reason: string }) => cancelOrder(uuid, body), (input) => input.uuid);
export const useFixStatus = () => useOrderWrite(({ uuid, ...body }: { uuid: string; status: string; reason: string }) => fixStatus(uuid, body), (input) => input.uuid);
export const useMessageOrder = () =>
  useOrderWrite(({ uuid, ...body }: { uuid: string; to: 'store' | 'customer'; text: string; reason: string }) => messageOrder(uuid, body), (input) => input.uuid);
export const useRequestRefund = () =>
  useOrderWrite(({ uuid, key, ...body }: { uuid: string; amount: string; reason: string; key: string }) => requestRefund(uuid, body, key), (input) => input.uuid);
export const useDecideRefund = () =>
  useOrderWrite((input: { id: number; decision: 'approve' | 'reject'; reason: string; key: string; uuid?: string }) => decideRefund(input.id, input.decision, input.reason, input.key), (input) => input.uuid ?? null);

export function useUpdateSla() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: updateSla,
    onSuccess: (settings) => {
      client.setQueryData(ORDER_KEYS.sla, settings);
      void client.invalidateQueries({ queryKey: ORDER_KEYS.all });
    },
  });
}
