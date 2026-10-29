'use client';

import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Integratsiyalar (P4-31, docs/API.md "Integratsiyalar"): /admin/integrations/connections, /events, /stuck-orders. Reading needs
// integrations.view; pausing and resuming a connection integrations.manage, a fresh 2FA code and a reason. Until the phase 5
// adapters connect the first outside system every list is empty. Typed by hand; credentials never arrive (names, configured,
// last 4), bodies arrive redacted.

export type HealthVerdict = 'good' | 'attention' | 'disconnected' | 'unknown';
export type ConnectionStatus = 'configuring' | 'active' | 'paused' | 'disconnected';
export type EventStatus = 'pending' | 'sent' | 'acknowledged' | 'failed' | 'dead';

export interface Ref {
  id: number;
  name: string;
}

export interface ConnectionRow {
  uuid: string;
  store: Ref | null;
  branch: Ref | null;
  provider: string;
  provider_label: string;
  direction: string;
  direction_label: string;
  mode: string;
  mode_label: string;
  status: ConnectionStatus;
  status_label: string;
  health: {
    score: number | null;
    verdict: HealthVerdict;
    last_success_at: string | null;
    last_webhook_at: string | null;
    last_error_at: string | null;
    last_error: string | null;
    errors_today: number;
    checked_at: string | null;
  };
  paused: { at: string | null; by: Ref | null; reason: string | null } | null;
  created_at: string | null;
}

export interface EventRow {
  uuid: string;
  connection: { uuid: string; provider: string; provider_label: string; store: Ref | null } | null;
  direction: 'inbound' | 'outbound';
  direction_label: string;
  type: string;
  status: EventStatus;
  status_label: string;
  http_status: number | null;
  attempts: number;
  next_attempt_at: string | null;
  error: string | null;
  subject: { type: string; id: number | null } | null;
  duration_ms: number | null;
  created_at: string | null;
}

export interface EventDetail extends EventRow {
  idempotency_key: string | null;
  signature_valid: boolean | null;
  last_attempt_at: string | null;
  request_body: unknown;
  response_body: unknown;
}

export interface ConnectionCard extends ConnectionRow {
  credentials: Array<{ key: string; configured: boolean; last4: string | null }>;
  settings: Record<string, unknown>;
  recent_events: EventRow[];
  menu_map: { total: number; by_type: Record<string, number> };
  stuck_orders: number;
}

export interface ConnectionCounts {
  total: number;
  configuring: number;
  active: number;
  paused: number;
  disconnected: number;
  attention: number;
}

export interface ConnectionsPage {
  data: ConnectionRow[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  counts: ConnectionCounts;
  stuck_orders: number;
}

export interface ConnectionFilters {
  status: '' | ConnectionStatus;
  q: string;
  page: number;
}

export const DEFAULT_CONNECTION_FILTERS: ConnectionFilters = { status: '', q: '', page: 1 };

export interface EventFilters {
  status: '' | EventStatus;
  direction: '' | 'inbound' | 'outbound';
  type: string;
  connection: string;
}

export const DEFAULT_EVENT_FILTERS: EventFilters = { status: '', direction: '', type: '', connection: '' };

export interface EventsPage {
  data: EventRow[];
  next_cursor: string | null;
}

export interface StuckRow {
  event_uuid: string;
  connection: { uuid: string; provider: string; provider_label: string; store: Ref | null } | null;
  order: { uuid: string; code: string | null; status: string } | null;
  status: EventStatus;
  status_label: string;
  attempts: number;
  error: string | null;
  since: string | null;
  waiting_minutes: number;
}

export interface StuckAnswer {
  count: number;
  data: StuckRow[];
  stuck_after_minutes: number;
}

export const INTEGRATIONS_REFRESH_MS = 30_000;
const EMPTY_COUNTS: ConnectionCounts = { total: 0, configuring: 0, active: 0, paused: 0, disconnected: 0, attention: 0 };

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

// --- Calls -----------------------------------------------------------------------------------------------------------------

export async function listConnections(filters: ConnectionFilters, signal?: AbortSignal): Promise<ConnectionsPage> {
  const raw = (await call('GET', '/integrations/connections', { query: { status: filters.status, q: filters.q.trim().length >= 2 ? filters.q.trim() : undefined, page: filters.page }, signal })).data;
  const page = isRecord(raw) ? raw : {};
  return {
    data: Array.isArray(page.data) ? (page.data as ConnectionRow[]) : [],
    current_page: Number(page.current_page ?? 1),
    last_page: Number(page.last_page ?? 1),
    per_page: Number(page.per_page ?? 30),
    total: Number(page.total ?? 0),
    counts: isRecord(page.counts) ? { ...EMPTY_COUNTS, ...(page.counts as Partial<ConnectionCounts>) } : EMPTY_COUNTS,
    stuck_orders: Number(page.stuck_orders ?? 0),
  };
}

export const getConnection = async (uuid: string, signal?: AbortSignal): Promise<ConnectionCard> => (await call('GET', `/integrations/connections/${uuid}`, { signal })).data as ConnectionCard;

export const pauseConnection = async (uuid: string, reason: string): Promise<ConnectionCard> => (await call('POST', `/integrations/connections/${uuid}/pause`, { body: { reason } })).data as ConnectionCard;

export const resumeConnection = async (uuid: string, reason: string): Promise<ConnectionCard> => (await call('POST', `/integrations/connections/${uuid}/resume`, { body: { reason } })).data as ConnectionCard;

export async function listEvents(filters: EventFilters, cursor: string | null, signal?: AbortSignal): Promise<EventsPage> {
  const raw = (await call('GET', '/integrations/events', { query: { ...filters, cursor, per_page: 25 }, signal })).data;
  const page = isRecord(raw) ? raw : {};
  return { data: Array.isArray(page.data) ? (page.data as EventRow[]) : [], next_cursor: typeof page.next_cursor === 'string' && page.next_cursor !== '' ? page.next_cursor : null };
}

export const getEvent = async (uuid: string, signal?: AbortSignal): Promise<EventDetail> => (await call('GET', `/integrations/events/${uuid}`, { signal })).data as EventDetail;

export async function getStuck(signal?: AbortSignal): Promise<StuckAnswer> {
  const raw = (await call('GET', '/integrations/stuck-orders', { signal })).data;
  const answer = isRecord(raw) ? raw : {};
  return { count: Number(answer.count ?? 0), data: Array.isArray(answer.data) ? (answer.data as StuckRow[]) : [], stuck_after_minutes: Number(answer.stuck_after_minutes ?? 2) };
}

// --- Hooks -----------------------------------------------------------------------------------------------------------------

export const INTEGRATION_KEYS = {
  connections: (filters: ConnectionFilters) => ['admin-integrations-connections', filters] as const,
  allConnections: ['admin-integrations-connections'] as const,
  connection: (uuid: string) => ['admin-integrations-connection', uuid] as const,
  events: (filters: EventFilters) => ['admin-integrations-events', filters] as const,
  event: (uuid: string) => ['admin-integrations-event', uuid] as const,
  stuck: ['admin-integrations-stuck'] as const,
};

export const useConnections = (filters: ConnectionFilters, enabled = true) =>
  useQuery({ queryKey: INTEGRATION_KEYS.connections(filters), queryFn: ({ signal }) => listConnections(filters, signal), enabled, placeholderData: keepPreviousData, refetchInterval: INTEGRATIONS_REFRESH_MS });

export const useConnection = (uuid: string) => useQuery({ queryKey: INTEGRATION_KEYS.connection(uuid), queryFn: ({ signal }) => getConnection(uuid, signal), refetchInterval: INTEGRATIONS_REFRESH_MS });

export function useEvents(filters: EventFilters, enabled = true) {
  return useInfiniteQuery({
    queryKey: INTEGRATION_KEYS.events(filters),
    queryFn: ({ pageParam, signal }) => listEvents(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    refetchInterval: INTEGRATIONS_REFRESH_MS,
  });
}

export const useEvent = (uuid: string) => useQuery({ queryKey: INTEGRATION_KEYS.event(uuid), queryFn: ({ signal }) => getEvent(uuid, signal) });

export const useStuck = (enabled = true) => useQuery({ queryKey: INTEGRATION_KEYS.stuck, queryFn: ({ signal }) => getStuck(signal), enabled, refetchInterval: INTEGRATIONS_REFRESH_MS });

export function useConnectionAction(action: 'pause' | 'resume') {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, reason }: { uuid: string; reason: string }) => (action === 'pause' ? pauseConnection(uuid, reason) : resumeConnection(uuid, reason)),
    onSuccess: (card) => {
      client.setQueryData(INTEGRATION_KEYS.connection(card.uuid), card);
      void client.invalidateQueries({ queryKey: INTEGRATION_KEYS.allConnections });
    },
  });
}
