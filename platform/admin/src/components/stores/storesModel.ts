import type { PillTone } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import type { HealthLevel, TaskState } from '@/lib/api/stores';

// Words and tones of the Ovqatxonalar screens: health and states are always said in words, the colour only repeats them.

export const HEALTH_TONE: Record<HealthLevel, PillTone> = { good: 'success', attention: 'warning', churn_risk: 'danger' };
export const HEALTH_LABEL: Record<HealthLevel | 'none', MessageKey> = {
  good: 'stores_health_good',
  attention: 'stores_health_attention',
  churn_risk: 'stores_health_churn_risk',
  none: 'stores_health_none',
};

export const TASK_TONE: Record<TaskState, PillTone> = { overdue: 'danger', today: 'warning', upcoming: 'neutral', done: 'success' };
export const TASK_LABEL: Record<TaskState, MessageKey> = {
  overdue: 'stores_task_overdue',
  today: 'stores_task_today',
  upcoming: 'stores_task_upcoming',
  done: 'stores_task_done',
};

export function statusTone(status: string): PillTone {
  if (status === 'active') return 'success';
  if (status === 'pending' || status === 'under_review') return 'info';
  if (status === 'payment_required') return 'warning';
  return 'danger';
}

export const SOURCE_LABEL: Record<string, MessageKey> = { website: 'stores_source_website', telegram: 'stores_source_telegram', admin: 'stores_source_admin' };

// "2026-11-10" → "10.11.2026"
export function day(value: string | null | undefined): string {
  if (!value) return '—';
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  return match ? `${match[3]}.${match[2]}.${match[1]}` : value;
}

// Today in Asia/Tashkent as Y-m-d (the backend's dates are Tashkent dates)
export function todayInTashkent(now = new Date()): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tashkent', year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
}

export const percent = (value: number | null | undefined) => (value === null || value === undefined ? '—' : `${Math.round(value * 100)}%`);
