'use client';

import { useState } from 'react';
import { History, ShieldAlert } from 'lucide-react';
import { Card, EmptyState, InlineError, PageHeader, Skeleton, formatDateTime } from '@rizqim/ui';
import { useAuditLog, type AuditLogEntry } from '@rizqim/staff';
import { isMessageKey, type MessageKey, type Translate } from '@/i18n';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { HistoryFilters, SelectFilter, usableDates, type DateFilter } from '../reports/HistoryFilters';
import { DataTable, Pager, Pill, Td, Th, type Tone } from '../reports/ui';

// /audit (audit.view, owners by default): the store's harakatlar jurnali, what the old store panel's "Harakatlar jurnali"
// showed. Newest first, filtered by kind of action and dates; phones are masked and secrets never reach it (the server).
// P4-32.

// The kinds of action the filter offers: a prefix the server reads as `<prefix>.*`
export const AUDIT_GROUPS = ['order', 'staff', 'permissions', 'device', 'menu', 'product', 'branch', 'delivery_zone', 'settings', 'subscription'] as const;

const PER_PAGE = 25;

const DANGER = new Set(['order.cancelled', 'order.rejected', 'order.refunded', 'device.revoked', 'staff.pin_reset']);

function toneOf(action: string): Tone {
  if (DANGER.has(action)) return 'danger';
  if (action.startsWith('permissions.') || action.startsWith('staff.role')) return 'warning';
  return 'neutral';
}

// The words for an action: its own label when the panel knows it, else the action as the server wrote it
export function actionLabel(t: Translate, action: string): string {
  const key = `audit_a_${action.replace(/[^a-z_]/g, '_')}`;
  return isMessageKey(key) ? t(key as MessageKey) : action;
}

const scalar = (value: unknown, t: Translate): string => {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'boolean') return value ? t('audit_yes') : t('audit_no');
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
};

// "status: new → canceled; reason: out_of_stock" from the before / after and the context (as the old panel wrote it)
export function describeChange(entry: Pick<AuditLogEntry, 'before' | 'after' | 'meta'>, t: Translate): string {
  const parts: string[] = [];
  const before = (entry.before ?? {}) as Record<string, unknown>;
  for (const [key, value] of Object.entries((entry.after ?? {}) as Record<string, unknown>)) parts.push(`${key}: ${scalar(before[key], t)} → ${scalar(value, t)}`);
  for (const [key, value] of Object.entries((entry.meta ?? {}) as Record<string, unknown>)) parts.push(`${key}: ${scalar(value, t)}`);
  return parts.join('; ');
}

export function AuditView() {
  const { t, language } = useI18n();
  const access = useCan();
  const allowed = access.ready && access.can('audit.view');
  const [group, setGroup] = useState('');
  const [dates, setDates] = useState<DateFilter>({ from: '', to: '' });
  const [page, setPage] = useState(1);
  const usable = usableDates(dates);
  const query = { ...(group ? { action: `${group}.*` } : {}), ...(usable.from ? { from: usable.from } : {}), ...(usable.to ? { to: usable.to } : {}), page, per_page: PER_PAGE };
  const log = useAuditLog(query, { enabled: allowed && !usable.reversed });

  if (access.ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_audit')} />
        <EmptyState icon={ShieldAlert} title={t('mgmt_forbidden')} text={t('audit_forbidden')} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={t('nav_audit')} subtitle={t('audit_subtitle')} />
      <Card>
        <HistoryFilters
          value={dates}
          reversed={usable.reversed}
          onChange={(next) => {
            setDates(next);
            setPage(1);
          }}
        >
          <SelectFilter
            label={t('audit_group')}
            allLabel={t('audit_allGroups')}
            value={group}
            onChange={(value) => {
              setGroup(value);
              setPage(1);
            }}
            options={AUDIT_GROUPS.map((value) => ({ value, label: t(`audit_g_${value}` as MessageKey) }))}
          />
        </HistoryFilters>
        {log.isError && !log.data ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(log.error, t)} retryLabel={t('retry')} retrying={log.isFetching} onRetry={() => void log.refetch()} />
        ) : !log.data ? (
          <div role="status" aria-busy="true" className="flex flex-col gap-2">
            {[0, 1, 2, 3].map((n) => (
              <Skeleton key={n} className="h-10 w-full" />
            ))}
          </div>
        ) : log.data.data.length === 0 ? (
          <EmptyState icon={History} title={t('audit_empty')} text={t('audit_emptyText')} />
        ) : (
          <div aria-busy={log.isPlaceholderData || undefined} className={log.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
            <DataTable caption={t('nav_audit')}>
              <thead>
                <tr>
                  <Th>{t('audit_colTime')}</Th>
                  <Th>{t('audit_colAction')}</Th>
                  <Th>{t('audit_colWho')}</Th>
                  <Th>{t('audit_colSubject')}</Th>
                  <Th>{t('audit_colChange')}</Th>
                </tr>
              </thead>
              <tbody>
                {log.data.data.map((entry) => (
                  <tr key={entry.id}>
                    <Td muted>{formatDateTime(entry.created_at, language)}</Td>
                    <Td>
                      <Pill tone={toneOf(entry.action)}>{actionLabel(t, entry.action)}</Pill>
                    </Td>
                    <Td>
                      {entry.actor ? (
                        <>
                          <span className="font-bold text-fg">{entry.actor.name}</span>
                          <span className="block text-caption text-fg-muted">{roleLabel(t, entry.actor.role)}</span>
                        </>
                      ) : (
                        <span className="text-fg-muted">{t('audit_system')}</span>
                      )}
                      {entry.device && <span className="block text-caption text-fg-muted">{entry.device.name ?? entry.device.type}</span>}
                    </Td>
                    <Td muted>{entry.subject_type ? `${entry.subject_type} #${entry.subject_id ?? ''}` : '—'}</Td>
                    <Td className="max-w-md break-words">{describeChange(entry, t) || '—'}</Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
            <Pager page={log.data.current_page || page} lastPage={log.data.last_page} total={log.data.total} busy={log.isFetching} onPage={setPage} />
          </div>
        )}
      </Card>
    </>
  );
}
