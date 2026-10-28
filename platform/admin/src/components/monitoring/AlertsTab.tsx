'use client';

import { useEffect, useRef, useState } from 'react';
import { BellRing, CheckCircle2, ChevronDown, ChevronUp, CircleAlert, Info, TriangleAlert, type LucideIcon } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, InlineError, Notice, Pill, TAB_CLASS, tabTone } from '@rizqim/ui';
import { EMPTY_ALERT_FILTERS, useAlertAction, useAlerts, type AlertFilters, type AlertRow, type AlertStateFilter, type Severity } from '@/lib/api/monitoring';
import { isMessageKey, type Translate } from '@/i18n';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { NoteDialog } from './NoteDialog';
import { elapsed, monitoringError, severityText, severityTone, span } from './words';

const SEVERITY_ICON: Record<Severity, LucideIcon> = { critical: CircleAlert, warning: TriangleAlert, info: Info };
const KINDS = ['failed_jobs_spike', 'queue_backlog', 'pos_device_offline', 'fiscal_failures', 'settlement_failures', 'backup_stale', 'scheduler_stale'] as const;

// Ogohlantirishlar: open ones first (critical before the rest), each with who took it; "Faol" is open + acknowledged together,
// the other filters read the history. A member with monitoring.manage acknowledges ("men shug'ullanyapman") and resolves, each
// with an optional note.
export function AlertsTab({ focusAlert }: { focusAlert: number | null }) {
  const t = useT();
  const { can } = useCan();
  const manage = can('monitoring.manage');
  const [filters, setFilters] = useState<AlertFilters>(EMPTY_ALERT_FILTERS);
  const alerts = useAlerts(filters);
  const [action, setAction] = useState<{ type: 'acknowledge' | 'resolve'; alert: AlertRow } | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const rows = alerts.data?.pages.flatMap((page) => page.data) ?? [];
  const counts = alerts.data?.pages[0]?.counts;
  const states: Array<{ id: AlertStateFilter; label: string }> = [
    { id: 'active', label: t('monitoring_alerts_f_active', { n: counts ? counts.open + counts.acknowledged : 0 }) },
    { id: 'open', label: t('monitoring_alerts_f_open', { n: counts?.open ?? 0 }) },
    { id: 'acknowledged', label: t('monitoring_alerts_f_acknowledged', { n: counts?.acknowledged ?? 0 }) },
    { id: 'resolved', label: t('monitoring_alerts_f_resolved', { n: counts?.resolved_7d ?? 0 }) },
    { id: 'all', label: t('monitoring_alerts_f_all') },
  ];

  return (
    <div className="flex flex-col gap-4">
      <Notice message={notice} />
      <div className="flex flex-wrap items-end gap-3">
        <div role="group" aria-label={t('monitoring_alerts_filtersLabel')} className="mb-4 flex min-w-0 flex-wrap gap-2">
          {states.map((state) => (
            <button
              key={state.id}
              type="button"
              aria-pressed={filters.state === state.id}
              onClick={() => setFilters((previous) => ({ ...previous, state: state.id }))}
              className={cx(TAB_CLASS, tabTone(filters.state === state.id))}
            >
              {state.label}
            </button>
          ))}
        </div>
        <Field label={t('monitoring_alerts_kind')} className="mb-4 w-full sm:w-64">
          {(control) => (
            <select {...control} value={filters.kind} onChange={(event) => setFilters((previous) => ({ ...previous, kind: event.target.value }))} className={fieldClass}>
              <option value="">{t('monitoring_alerts_kindAll')}</option>
              {KINDS.map((kind) => (
                <option key={kind} value={kind}>
                  {t(`monitoring_kind_${kind}`)}
                </option>
              ))}
            </select>
          )}
        </Field>
      </div>

      {alerts.isError ? (
        <InlineError title={t('monitoring_alerts_loadError')} message={errorText(alerts.error, t)} retryLabel={t('retry')} retrying={alerts.isFetching} onRetry={() => void alerts.refetch()} />
      ) : !alerts.isSuccess ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        filters.state === 'active' ? (
          <EmptyState icon={CheckCircle2} title={t('monitoring_alerts_emptyActive')} text={t('monitoring_alerts_emptyActiveText')} />
        ) : (
          <EmptyState icon={BellRing} title={t('monitoring_alerts_emptyOther')} text={t('monitoring_alerts_emptyOtherText')} />
        )
      ) : (
        <div className="flex max-w-4xl flex-col gap-3">
          <ul aria-label={t('monitoring_alerts_listLabel')} className="flex flex-col gap-3">
            {rows.map((alert) => (
              <AlertCard key={alert.id} alert={alert} manage={manage} focused={alert.id === focusAlert} onAction={(type) => setAction({ type, alert })} />
            ))}
          </ul>
          {alerts.hasNextPage && (
            <Button variant="secondary" loading={alerts.isFetchingNextPage} onClick={() => void alerts.fetchNextPage()}>
              {t('monitoring_loadMore')}
            </Button>
          )}
        </div>
      )}
      {!manage && rows.length > 0 && <p className="text-footnote text-fg-muted">{t('monitoring_viewOnly')}</p>}

      {action && (
        <AlertActionDialog
          type={action.type}
          alert={action.alert}
          onClose={() => setAction(null)}
          onDone={(message) => {
            setAction(null);
            setNotice(message);
          }}
        />
      )}
    </div>
  );
}

function AlertCard({ alert, manage, focused, onAction }: { alert: AlertRow; manage: boolean; focused: boolean; onAction: (type: 'acknowledge' | 'resolve') => void }) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLLIElement>(null);
  const Icon = SEVERITY_ICON[alert.severity];

  // The row a "Bugun" link pointed at is brought into view
  useEffect(() => {
    if (focused) ref.current?.scrollIntoView?.({ block: 'center' });
  }, [focused]);

  const details = detailRows(alert.details, t);
  return (
    <li ref={ref} aria-current={focused ? 'true' : undefined}>
      <Card className={cx('flex flex-col gap-2', focused && 'outline-2 outline-offset-2 outline-brand')} padded>
        <div className="flex flex-wrap items-center gap-2">
          <Pill tone={severityTone(alert.severity)} icon={Icon}>
            {severityText(alert.severity, t)}
          </Pill>
          <Pill tone={alert.state === 'open' ? 'warning' : alert.state === 'acknowledged' ? 'info' : 'success'}>{t(`monitoring_state_${alert.state}`)}</Pill>
          <span className="text-footnote text-fg-muted">{alert.kind_label}</span>
        </div>
        <h3 className="break-words text-body font-bold text-fg">{alert.title}</h3>
        <ul className="flex flex-col gap-0.5 text-footnote text-fg-muted">
          <li>{t('monitoring_alert_since', { time: when(alert.first_seen_at), age: elapsed(alert.first_seen_at, t) })}</li>
          {alert.state !== 'resolved' && <li>{t('monitoring_alert_seen', { count: alert.count, age: elapsed(alert.last_seen_at, t) })}</li>}
          {alert.store && <li>{t('monitoring_alert_store', { name: alert.store.name })}</li>}
          {alert.acknowledged && (
            <li>
              {alert.acknowledged.by ? t('monitoring_alert_acknowledged', { name: alert.acknowledged.by.name, time: when(alert.acknowledged.at) }) : t('monitoring_alert_acknowledgedUnknown', { time: when(alert.acknowledged.at) })}
              {alert.acknowledged.note ? ` · ${t('monitoring_alert_note', { note: alert.acknowledged.note })}` : ''}
            </li>
          )}
          {alert.resolved && (
            <li>
              {alert.resolved.resolution === 'auto'
                ? t('monitoring_alert_resolvedAuto', { time: when(alert.resolved.at) })
                : alert.resolved.resolution === 'rule_disabled'
                  ? t('monitoring_alert_resolvedRule', { time: when(alert.resolved.at) })
                  : t('monitoring_alert_resolvedManual', { name: alert.resolved.by?.name ?? '—', time: when(alert.resolved.at) })}
              {alert.resolved.note ? ` · ${t('monitoring_alert_note', { note: alert.resolved.note })}` : ''}
            </li>
          )}
        </ul>
        {details.length > 0 && (
          <>
            <Button variant="ghost" icon={open ? ChevronUp : ChevronDown} aria-expanded={open} onClick={() => setOpen((value) => !value)} className="self-start">
              {open ? t('monitoring_alert_hideDetails') : t('monitoring_alert_details')}
            </Button>
            {open && (
              <dl className="grid grid-cols-1 gap-x-4 gap-y-1 rounded-control bg-surface-2 p-3 text-footnote sm:grid-cols-[auto_1fr]">
                {details.map(([name, value]) => (
                  <div key={name} className="contents">
                    <dt className="font-bold text-fg-muted">{name}</dt>
                    <dd className="break-words text-fg">{value}</dd>
                  </div>
                ))}
              </dl>
            )}
          </>
        )}
        {manage && alert.state !== 'resolved' && (
          <div className="flex flex-wrap gap-2 pt-1">
            {alert.state === 'open' && (
              <Button variant="secondary" aria-label={t('monitoring_alert_acknowledgeLabel', { title: alert.title })} onClick={() => onAction('acknowledge')}>
                {t('monitoring_alert_acknowledge')}
              </Button>
            )}
            <Button variant="primary" aria-label={t('monitoring_alert_resolveLabel', { title: alert.title })} onClick={() => onAction('resolve')}>
              {t('monitoring_alert_resolve')}
            </Button>
          </div>
        )}
      </Card>
    </li>
  );
}

function AlertActionDialog({ type, alert, onClose, onDone }: { type: 'acknowledge' | 'resolve'; alert: AlertRow; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const mutation = useAlertAction(type);
  const [error, setError] = useState<string | null>(null);
  const ack = type === 'acknowledge';

  return (
    <NoteDialog
      title={ack ? t('monitoring_alert_ackTitle') : t('monitoring_alert_resolveTitle')}
      text={`${alert.title}. ${ack ? t('monitoring_alert_ackText') : t('monitoring_alert_resolveText')}`}
      label={t('monitoring_alert_noteLabel')}
      placeholder={t('monitoring_alert_notePlaceholder')}
      confirmLabel={ack ? t('monitoring_alert_acknowledge') : t('monitoring_alert_resolve')}
      required={false}
      minLength={2}
      busy={mutation.isPending}
      error={error}
      onClose={onClose}
      onSubmit={(note) => {
        setError(null);
        mutation.mutate(
          { id: alert.id, note },
          {
            onSuccess: () => onDone(ack ? t('monitoring_alert_acknowledged_done') : t('monitoring_alert_resolved_done')),
            onError: (failure) => setError(monitoringError(failure, t)),
          },
        );
      }}
    />
  );
}

// The numbers and names the check found, as label / value pairs in words
const TIME_KEYS = new Set(['last_seen_at', 'last_backup_at', 'oldest_failed_at']);
const SECONDS_KEYS = new Set(['oldest_age_seconds', 'last_heartbeat_age_seconds']);

export function detailRows(details: Record<string, unknown> | null, t: Translate): Array<[string, string]> {
  if (!details) return [];
  const rows: Array<[string, string]> = [];
  for (const [key, value] of Object.entries(details)) {
    if (value === null || value === undefined || value === '') continue;
    // A key only gets a translated label when the dictionary has one
    const label = `monitoring_d_${key}`;
    rows.push([isMessageKey(label) ? t(label) : key, formatDetail(key, value, t)]);
  }
  return rows;
}

function formatDetail(key: string, value: unknown, t: Translate): string {
  if (TIME_KEYS.has(key) && typeof value === 'string') return when(value);
  if (SECONDS_KEYS.has(key) && typeof value === 'number') return span(value, t);
  if (Array.isArray(value)) {
    return value
      .map((item) => {
        if (item !== null && typeof item === 'object') {
          const entry = item as Record<string, unknown>;
          const label = entry.job ?? entry.name ?? entry.store_id;
          const number = entry.count ?? entry.failures;
          return number !== undefined ? `${String(label)}: ${String(number)}` : JSON.stringify(item);
        }
        return String(item);
      })
      .join(', ');
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}
