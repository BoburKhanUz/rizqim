'use client';

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// Monitoring (P4-15, docs/API.md "Monitoring"): GET /admin/monitoring/health, /failed-jobs, /alerts, /rules and their actions.
// Typed by hand until the generated client is regenerated with these endpoints (the shapes follow the controllers' documented
// answers). Times are ISO 8601. Failed job payloads and exception texts arrive already redacted by the backend.

export type Severity = 'critical' | 'warning' | 'info';
// ok | degraded | fail for the overall verdict; a component adds not_used / not_configured / stale / missing / backlog ...
export type Verdict = string;

export interface QueueRow {
  name: string;
  size: number | null;
  oldest_age_seconds: number | null;
}

// M3: the server blocks. A value that could not be measured is null and its block says unknown / missing / stale, never ok.
export interface PostgresHealth {
  // ok | warning | unknown
  status: Verdict;
  connections: { used: number; max: number; percent: number } | null;
  longest_transaction_seconds: number | null;
  idle_transaction_seconds: number | null;
  lock_waits: number | null;
  archiver: {
    archive_mode: string;
    last_archived_wal: string | null;
    last_archived_at: string | null;
    last_failed_at: string | null;
    failing: boolean;
    ready_wal: number | null;
  } | null;
}

// on | off | no_cipher | partial | bad_endpoint
export type Offsite = 'on' | 'off' | 'no_cipher' | 'partial' | 'bad_endpoint';

export interface PitrHealth {
  // off | missing | stale | fail | warning | ok
  status: Verdict;
  enabled: boolean;
  marker_at: string | null;
  marker_age_seconds: number | null;
  message: string | null;
  last_backup_at: string | null;
  last_backup_type: string | null;
  last_backup_age_seconds: number | null;
  last_full_at: string | null;
  last_wal_archived_at: string | null;
  wal_archived_age_seconds: number | null;
  archive_failing: boolean | null;
  ready_wal: number | null;
  offsite: Offsite | string | null;
  offsite_note: string | null;
  max_backup_age_hours: number;
  max_marker_age_minutes: number;
}

export interface DrillHealth {
  // off | never | failed | stale | ok | unknown
  status: Verdict;
  last_at: string | null;
  last_status: 'ok' | 'failed' | string | null;
  restore_seconds: number | null;
  data_loss_seconds: number | null;
  reached_time: string | null;
  backup_label: string | null;
  error: string | null;
  last_ok_at: string | null;
  max_age_days: number;
}

export interface PartitionsHealth {
  // ok | warning | fail | unknown
  status: Verdict;
  months_ahead: number | null;
  min_months_ahead: number;
  default_rows: number;
  archive_failed: number;
  archive_stale: number;
  problems: Array<{ table: string | null; code: string; message: string }>;
  tables: Array<{ table: string; partitioned: boolean; future_months: number; default_rows: number; oldest_month: string | null; newest_month: string | null }>;
}

export interface DiskHealth {
  // ok | warning | fail | stale | missing
  status: Verdict;
  recorded_at: string | null;
  age_seconds: number | null;
  used_percent: number | null;
  avail_bytes: number | null;
  total_bytes: number | null;
  pg_wal_bytes: number | null;
  spool_bytes: number | null;
  db_bytes: number | null;
  repo_used_percent: number | null;
  repo_avail_bytes: number | null;
  warn_percent: number;
  critical_percent: number;
}

export interface MonitoringHealth {
  status: Verdict;
  checked_at: string;
  database: { status: Verdict; latency_ms?: number };
  // memory: null when Redis is not used or not readable; max_bytes null = no cap
  redis: { status: Verdict; latency_ms?: number; memory?: { used_bytes: number; max_bytes: number | null; percent: number | null; policy: string | null } | null };
  queue: {
    status: Verdict;
    queues: QueueRow[];
    worker?: { last_heartbeat_age_seconds: number | null; max_age_seconds: number };
    failed_jobs: { total: number; last_24h: number; latest_failed_at: string | null };
  };
  scheduler: { status: Verdict; last_heartbeat_age_seconds: number | null; max_age_seconds: number };
  reverb: { status: Verdict; latency_ms: number | null };
  backup: { status: Verdict; last_backup_at: string | null; age_hours: number | null; max_age_hours: number };
  app: { version: string | null; commit: string | null; environment: string };
  sentry: { configured: boolean; environment: string | null; project_url: string | null };
  alerts: { open: number; acknowledged: number; critical_unresolved: number };
  // M3; optional so an older backend still renders
  postgres?: PostgresHealth;
  pitr?: PitrHealth;
  drill?: DrillHealth;
  partitions?: PartitionsHealth;
  disk?: DiskHealth;
}

export type AlertState = 'open' | 'acknowledged' | 'resolved';
export type AlertStateFilter = 'active' | AlertState | 'all';

export interface AlertActor {
  id: number;
  name: string;
}

export interface AlertRow {
  id: number;
  kind: string;
  kind_label: string;
  severity: Severity;
  state: AlertState;
  title: string;
  // Numbers and names only (counts, queue names, device and store names, ages)
  details: Record<string, unknown> | null;
  store: { id: number; name: string } | null;
  first_seen_at: string;
  last_seen_at: string;
  count: number;
  acknowledged: { by: AlertActor | null; at: string; note: string | null } | null;
  resolved: { by: AlertActor | null; at: string; note: string | null; resolution: 'manual' | 'auto' | 'rule_disabled' | null } | null;
}

export interface AlertsPage {
  data: AlertRow[];
  next_cursor: string | null;
  counts: { open: number; acknowledged: number; resolved_7d: number };
}

export interface AlertFilters {
  state: AlertStateFilter;
  kind: string;
}

export const EMPTY_ALERT_FILTERS: AlertFilters = { state: 'active', kind: '' };

export interface FailedJobRow {
  id: number;
  uuid: string;
  // The class's short name and its full name
  job: string;
  job_class: string;
  queue: string;
  connection: string;
  // The exception's first line, redacted
  exception_line: string;
  failed_at: string | null;
}

export interface FailedJobsPage {
  data: FailedJobRow[];
  next_cursor: string | null;
  total: number;
}

export interface FailedJobFilters {
  queue: string;
  q: string;
}

export const EMPTY_JOB_FILTERS: FailedJobFilters = { queue: '', q: '' };

export interface FailedJobDetail extends FailedJobRow {
  attempts: number | null;
  max_tries: number | null;
  // True for a member without monitoring.manage / pii.view: `exception` is then its first line and `payload` the job name only
  details_hidden?: boolean;
  // The whole exception with its stack trace, redacted
  exception: string;
  // The job as plain data, secrets shown as [redacted]
  payload: Record<string, unknown>;
}

export interface RuleField {
  label: string;
  unit: string;
  min: number;
  max: number;
}

export interface AlertRule {
  kind: string;
  label: string;
  description: string;
  // M3: what to do when it fires, one sentence
  action?: string;
  severity: Severity;
  enabled: boolean;
  telegram: boolean;
  threshold: number;
  threshold_secondary: number | null;
  window_minutes: number | null;
  fields: { threshold: RuleField; threshold_secondary: RuleField | null; window: RuleField | null };
  defaults: { threshold: number; threshold_secondary: number | null; window_minutes: number | null };
  unresolved_alerts: number;
  updated_at: string | null;
  updated_by: AlertActor | null;
}

export interface RulesAnswer {
  rules: AlertRule[];
  telegram_configured: boolean;
}

export interface RuleChange {
  enabled?: boolean;
  telegram?: boolean;
  threshold?: number;
  threshold_secondary?: number;
  window_minutes?: number;
}

// The tab asks again every 30 seconds (and when the browser tab comes back)
export const MONITORING_REFRESH_MS = 30_000;
export const JOBS_PAGE = 25;

// The live events of alerts on platform.ops (backend App\Events\Broadcast\PlatformAlertBroadcast): ids and statuses only, the page asks again
export const ALERT_EVENTS = ['ops.alert.opened', 'ops.alert.acknowledged', 'ops.alert.resolved'] as const;

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);
const cursorOf = (page: Record<string, unknown>) => (typeof page.next_cursor === 'string' && page.next_cursor !== '' ? page.next_cursor : null);

// --- Calls -----------------------------------------------------------------------------------------------------------------

export const getHealth = async (signal?: AbortSignal): Promise<MonitoringHealth> => (await call('GET', '/monitoring/health', { signal })).data as MonitoringHealth;

export async function listAlerts(filters: AlertFilters, cursor: string | null, signal?: AbortSignal): Promise<AlertsPage> {
  const raw = (await call('GET', '/monitoring/alerts', { query: { state: filters.state, kind: filters.kind, cursor, per_page: 25 }, signal })).data;
  const page = isRecord(raw) ? raw : {};
  const counts = isRecord(page.counts) ? page.counts : {};
  return {
    data: Array.isArray(page.data) ? (page.data as AlertRow[]) : [],
    next_cursor: cursorOf(page),
    counts: { open: Number(counts.open ?? 0), acknowledged: Number(counts.acknowledged ?? 0), resolved_7d: Number(counts.resolved_7d ?? 0) },
  };
}

export const acknowledgeAlert = async (id: number, note: string): Promise<void> => {
  await call('POST', `/monitoring/alerts/${id}/acknowledge`, { body: note ? { note } : {} });
};

export const resolveAlert = async (id: number, note: string): Promise<void> => {
  await call('POST', `/monitoring/alerts/${id}/resolve`, { body: note ? { note } : {} });
};

export async function listFailedJobs(filters: FailedJobFilters, cursor: string | null, signal?: AbortSignal): Promise<FailedJobsPage> {
  const raw = (await call('GET', '/monitoring/failed-jobs', { query: { ...filters, cursor, per_page: JOBS_PAGE }, signal })).data;
  const page = isRecord(raw) ? raw : {};
  return { data: Array.isArray(page.data) ? (page.data as FailedJobRow[]) : [], next_cursor: cursorOf(page), total: Number(page.total ?? 0) };
}

export const getFailedJob = async (uuid: string, signal?: AbortSignal): Promise<FailedJobDetail> => (await call('GET', `/monitoring/failed-jobs/${uuid}`, { signal })).data as FailedJobDetail;

export const retryFailedJob = async (uuid: string): Promise<void> => {
  await call('POST', `/monitoring/failed-jobs/${uuid}/retry`, { body: {} });
};

// Needs a step-up confirmation (the shell asks for the code) and a reason
export const deleteFailedJob = async (uuid: string, reason: string): Promise<void> => {
  await call('DELETE', `/monitoring/failed-jobs/${uuid}`, { body: { reason } });
};

export async function getRules(signal?: AbortSignal): Promise<RulesAnswer> {
  const raw = (await call('GET', '/monitoring/rules', { signal })).data;
  const answer = isRecord(raw) ? raw : {};
  return { rules: Array.isArray(answer.rules) ? (answer.rules as AlertRule[]) : [], telegram_configured: answer.telegram_configured === true };
}

// Needs a step-up confirmation and a reason; the answer is all the rules
export async function updateRule(kind: string, change: RuleChange, reason: string): Promise<RulesAnswer> {
  const raw = (await call('PUT', `/monitoring/rules/${kind}`, { body: { ...change, reason } })).data;
  const answer = isRecord(raw) ? raw : {};
  return { rules: Array.isArray(answer.rules) ? (answer.rules as AlertRule[]) : [], telegram_configured: answer.telegram_configured === true };
}

// --- Hooks -----------------------------------------------------------------------------------------------------------------

export const MONITORING_KEYS = {
  health: ['admin-monitoring-health'] as const,
  alerts: (filters: AlertFilters) => ['admin-monitoring-alerts', filters] as const,
  allAlerts: ['admin-monitoring-alerts'] as const,
  jobs: (filters: FailedJobFilters) => ['admin-monitoring-jobs', filters] as const,
  allJobs: ['admin-monitoring-jobs'] as const,
  job: (uuid: string) => ['admin-monitoring-job', uuid] as const,
  rules: ['admin-monitoring-rules'] as const,
};

export const useHealth = (enabled = true) => useQuery({ queryKey: MONITORING_KEYS.health, queryFn: ({ signal }) => getHealth(signal), enabled, refetchInterval: MONITORING_REFRESH_MS });

// Open and acknowledged alerts come whole; the history pages by cursor
export function useAlerts(filters: AlertFilters, enabled = true) {
  return useInfiniteQuery({
    queryKey: MONITORING_KEYS.alerts(filters),
    queryFn: ({ pageParam, signal }) => listAlerts(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    refetchInterval: MONITORING_REFRESH_MS,
  });
}

export function useFailedJobs(filters: FailedJobFilters, enabled = true) {
  return useInfiniteQuery({
    queryKey: MONITORING_KEYS.jobs(filters),
    queryFn: ({ pageParam, signal }) => listFailedJobs(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
    refetchInterval: MONITORING_REFRESH_MS,
  });
}

export const useFailedJob = (uuid: string | null) => useQuery({ queryKey: MONITORING_KEYS.job(uuid ?? ''), queryFn: ({ signal }) => getFailedJob(uuid ?? '', signal), enabled: uuid !== null });

export const useRules = (enabled = true) => useQuery({ queryKey: MONITORING_KEYS.rules, queryFn: ({ signal }) => getRules(signal), enabled });

export function useAlertAction(action: 'acknowledge' | 'resolve') {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ id, note }: { id: number; note: string }) => (action === 'acknowledge' ? acknowledgeAlert(id, note) : resolveAlert(id, note)),
    onSuccess: () => Promise.all([client.invalidateQueries({ queryKey: MONITORING_KEYS.allAlerts }), client.invalidateQueries({ queryKey: MONITORING_KEYS.health })]),
  });
}

export function useRetryJob() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: retryFailedJob,
    onSuccess: () => Promise.all([client.invalidateQueries({ queryKey: MONITORING_KEYS.allJobs }), client.invalidateQueries({ queryKey: MONITORING_KEYS.health })]),
  });
}

export function useDeleteJob() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, reason }: { uuid: string; reason: string }) => deleteFailedJob(uuid, reason),
    onSuccess: () => Promise.all([client.invalidateQueries({ queryKey: MONITORING_KEYS.allJobs }), client.invalidateQueries({ queryKey: MONITORING_KEYS.health })]),
  });
}

export function useUpdateRule() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ kind, change, reason }: { kind: string; change: RuleChange; reason: string }) => updateRule(kind, change, reason),
    // The answer is all the rules; alerts of a rule that was switched off are resolved
    onSuccess: (answer) => {
      client.setQueryData(MONITORING_KEYS.rules, answer);
      void client.invalidateQueries({ queryKey: MONITORING_KEYS.allAlerts });
      void client.invalidateQueries({ queryKey: MONITORING_KEYS.health });
    },
  });
}
