import type { PillTone } from '@rizqim/ui';
import type { MessageKey, Translate } from '@/i18n';
import { ApiError } from '@/lib/api/client';
import { errorText } from '@/lib/errors';
import type { Severity } from '@/lib/api/monitoring';

// "12 daqiqa", "3 soat", "2 kun": a length of time in the biggest whole unit, in plain words
export function span(seconds: number, t: Translate): string {
  const value = Math.max(0, Math.round(seconds));
  if (value < 60) return t('monitoring_sec', { n: value });
  if (value < 3600) return t('monitoring_min', { n: Math.floor(value / 60) });
  if (value < 86400) return t('monitoring_hour', { n: Math.floor(value / 3600) });
  return t('monitoring_day', { n: Math.floor(value / 86400) });
}

// How long ago a moment was, as a length of time ("5 daqiqa"); the caller adds "oldin"
export function elapsed(iso: string | null | undefined, t: Translate, now = Date.now()): string {
  if (!iso) return '—';
  const seconds = (now - new Date(iso).getTime()) / 1000;
  return Number.isFinite(seconds) ? span(seconds, t) : '—';
}

// "5 daqiqa oldin" for a moment in the past
export function ago(iso: string | null | undefined, t: Translate, now = Date.now()): string {
  if (!iso) return '—';
  const seconds = (now - new Date(iso).getTime()) / 1000;
  if (!Number.isFinite(seconds)) return '—';
  return seconds < 30 ? t('monitoring_justNow') : t('monitoring_ago', { span: span(seconds, t) });
}

const VERDICT_KEYS: Record<string, MessageKey> = {
  ok: 'monitoring_verdict_ok',
  degraded: 'monitoring_verdict_degraded',
  fail: 'monitoring_verdict_fail',
  not_used: 'monitoring_verdict_not_used',
  not_configured: 'monitoring_verdict_not_configured',
  stale: 'monitoring_verdict_stale',
  missing: 'monitoring_verdict_missing',
  backlog: 'monitoring_verdict_backlog',
  unknown: 'monitoring_verdict_unknown',
};

export const verdictText = (status: string, t: Translate): string => t(VERDICT_KEYS[status] ?? 'monitoring_verdict_unknown');

// The colour is never the only signal: the pill always carries the verdict in words (and an icon, in the tile)
export function verdictTone(status: string): PillTone {
  if (status === 'ok') return 'success';
  if (status === 'fail') return 'danger';
  if (status === 'not_used' || status === 'not_configured') return 'neutral';
  return 'warning';
}

export const severityText = (severity: Severity, t: Translate): string => t(severity === 'critical' ? 'monitoring_sev_critical' : severity === 'warning' ? 'monitoring_sev_warning' : 'monitoring_sev_info');
export const severityTone = (severity: Severity): PillTone => (severity === 'critical' ? 'danger' : severity === 'warning' ? 'warning' : 'info');

// The words for a refusal of the monitoring endpoints: its own codes first, then the shared ones
export function monitoringError(error: unknown, t: Translate, notFound: MessageKey = 'monitoring_alert_notFound'): string {
  if (error instanceof ApiError) {
    if (error.code === 'alert_not_open') return t('monitoring_alert_not_open');
    if (error.code === 'alert_resolved') return t('monitoring_alert_already_resolved');
    if (error.code === 'not_found') return t(notFound);
  }
  return errorText(error, t);
}
