'use client';

import { useEffect, useState } from 'react';
import { ChevronRight, Plug, Search } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Field, fieldClass, InlineError, Notice, Pill, type PillTone } from '@rizqim/ui';
import { DEFAULT_CONNECTION_FILTERS, useConnection, useConnectionAction, useConnections, type ConnectionFilters, type ConnectionRow, type ConnectionStatus, type HealthVerdict } from '@/lib/api/integrations';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager, ReasonDialog } from '../system/parts';
import { EventList } from './EventsTab';

const STATUSES: readonly ConnectionStatus[] = ['active', 'configuring', 'paused', 'disconnected'];

export const STATUS_TONE: Record<ConnectionStatus, PillTone> = { active: 'success', configuring: 'info', paused: 'warning', disconnected: 'danger' };
export const VERDICT_TONE: Record<HealthVerdict, PillTone> = { good: 'success', attention: 'warning', disconnected: 'danger', unknown: 'neutral' };

// Ulanishlar: every store's links to outside systems with their health (score 0-100, the last call and webhook, today's errors).
// The card shows the keys by name only (configured, last 4), the settings with secrets hidden, the last events and the menu map
// counts. Pausing and resuming need integrations.manage, a reason and a fresh 2FA code.
export function ConnectionsTab({ selected, onSelect, onOpenEvent }: { selected: string | null; onSelect: (uuid: string | null) => void; onOpenEvent: (uuid: string) => void }) {
  if (selected) return <ConnectionCardView uuid={selected} onBack={() => onSelect(null)} onOpenEvent={onOpenEvent} />;
  return <ConnectionList onSelect={onSelect} />;
}

function VerdictPill({ row }: { row: ConnectionRow }) {
  const t = useT();
  const { verdict, score } = row.health;
  return <Pill tone={VERDICT_TONE[verdict]}>{verdict === 'unknown' ? t('integrations_verdict_unknown') : t('integrations_verdict_score', { verdict: t(`integrations_verdict_${verdict}`), score: score ?? 0 })}</Pill>;
}

function ConnectionList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<ConnectionFilters>(DEFAULT_CONNECTION_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);
  const connections = useConnections(filters);
  const page = connections.data;
  const counts = page?.counts;
  const nothingYet = counts?.total === 0;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      {!nothingYet && (
        <>
          <ChipRow label={t('integrations_con_statusFilter')}>
            <Chip on={filters.status === ''} onClick={() => setFilters((previous) => ({ ...previous, status: '', page: 1 }))}>
              {t('integrations_countLabel', { label: t('integrations_all'), count: counts?.total ?? 0 })}
            </Chip>
            {STATUSES.map((status) => (
              <Chip key={status} on={filters.status === status} onClick={() => setFilters((previous) => ({ ...previous, status, page: 1 }))}>
                {t('integrations_countLabel', { label: t(`integrations_status_${status}`), count: counts?.[status] ?? 0 })}
              </Chip>
            ))}
          </ChipRow>
          <Field label={t('integrations_con_search')}>
            {(control) => (
              <div className="relative sm:max-w-sm">
                <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                <input {...control} type="search" value={search} placeholder={t('integrations_con_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
              </div>
            )}
          </Field>
        </>
      )}
      <ListState
        loading={!page}
        error={connections.isError ? connections.error : null}
        fetching={connections.isFetching}
        onRetry={() => void connections.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={nothingYet ? t('integrations_con_none') : t('integrations_con_empty')}
        emptyText={nothingYet ? t('integrations_con_noneText') : undefined}
        icon={Plug}
        errorTitle={t('integrations_loadError')}
      >
        <ul aria-label={t('integrations_con_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => (
            <li key={row.uuid}>
              <Card padded={false}>
                <button
                  type="button"
                  onClick={() => onSelect(row.uuid)}
                  aria-label={t('integrations_con_open', { provider: row.provider_label, store: row.store?.name ?? '—' })}
                  className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                  <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="break-words text-body font-bold text-fg">
                        {row.provider_label} · {row.store?.name ?? '—'}
                        {row.branch ? ` · ${row.branch.name}` : ''}
                      </span>
                      <Pill tone={STATUS_TONE[row.status]}>{row.status_label}</Pill>
                      <VerdictPill row={row} />
                    </span>
                    <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
                      <span>{row.direction_label}</span>
                      <span>{row.mode_label}</span>
                      <span>{t('integrations_con_lastSuccess', { time: when(row.health.last_success_at, t('integrations_never')) })}</span>
                      {row.health.errors_today > 0 && <span>{t('integrations_con_errorsToday', { count: row.health.errors_today })}</span>}
                    </span>
                  </span>
                  <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                </button>
              </Card>
            </li>
          ))}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => setFilters((previous) => ({ ...previous, page: next }))} />}
      </ListState>
    </div>
  );
}

function ConnectionCardView({ uuid, onBack, onOpenEvent }: { uuid: string; onBack: () => void; onOpenEvent: (uuid: string) => void }) {
  const t = useT();
  const { can } = useCan();
  const card = useConnection(uuid);
  const [dialog, setDialog] = useState<'pause' | 'resume' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const pause = useConnectionAction('pause');
  const resume = useConnectionAction('resume');
  const mutation = dialog === 'resume' ? resume : pause;

  if (card.isError) return <InlineError title={t('integrations_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const connection = card.data;
  const settings = Object.entries(connection.settings ?? {});
  const types = Object.entries(connection.menu_map.by_type);

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="w-full min-w-0 break-words text-headline font-bold text-fg sm:w-auto sm:flex-1">
            {connection.provider_label} · {connection.store?.name ?? '—'}
          </h2>
          <Pill tone={STATUS_TONE[connection.status]}>{connection.status_label}</Pill>
          <VerdictPill row={connection} />
        </div>
        <Facts
          rows={[
            [t('integrations_store'), connection.store?.name ?? '—'],
            [t('integrations_branch'), connection.branch?.name ?? t('integrations_wholeStore')],
            [t('integrations_direction'), connection.direction_label],
            [t('integrations_mode'), connection.mode_label],
            [t('integrations_con_created'), when(connection.created_at)],
            [t('integrations_con_stuck'), String(connection.stuck_orders)],
          ]}
        />
        {connection.paused && (
          <p role="note" className="mt-3 rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">
            {t('integrations_con_pausedNote', { by: connection.paused.by?.name ?? '—', time: when(connection.paused.at), reason: connection.paused.reason ?? '—' })}
          </p>
        )}
      </Card>

      <Card>
        <h3 className="mb-2 text-body font-bold text-fg">{t('integrations_con_health')}</h3>
        <Facts
          rows={[
            [t('integrations_con_score'), connection.health.score === null ? '—' : `${connection.health.score} / 100`],
            [t('integrations_con_lastSuccessAt'), when(connection.health.last_success_at, t('integrations_never'))],
            [t('integrations_con_lastWebhook'), when(connection.health.last_webhook_at, t('integrations_never'))],
            [t('integrations_con_errors'), String(connection.health.errors_today)],
            [t('integrations_con_lastError'), connection.health.last_error ? `${connection.health.last_error} (${when(connection.health.last_error_at)})` : '—'],
            [t('integrations_con_checked'), when(connection.health.checked_at)],
          ]}
        />
      </Card>

      <Card>
        <h3 className="mb-2 text-body font-bold text-fg">{t('integrations_con_keys')}</h3>
        {connection.credentials.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('integrations_con_noKeys')}</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {connection.credentials.map((key) => (
              <li key={key.key} className="flex flex-wrap items-center gap-2 text-footnote">
                <span className="font-mono text-fg">{key.key}</span>
                <span className="text-fg-muted">{key.configured ? (key.last4 ? t('integrations_key_last4', { last4: key.last4 }) : t('integrations_key_set')) : t('integrations_key_unset')}</span>
              </li>
            ))}
          </ul>
        )}
        <p className="mt-2 text-caption text-fg-muted">{t('integrations_con_keysHint')}</p>
        {settings.length > 0 && (
          <>
            <h3 className="mb-2 mt-4 text-body font-bold text-fg">{t('integrations_con_settings')}</h3>
            <Facts rows={settings.map(([key, value]) => [key, typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : JSON.stringify(value)] as const)} />
          </>
        )}
      </Card>

      <Card>
        <h3 className="mb-2 text-body font-bold text-fg">{t('integrations_con_menuMap')}</h3>
        <p className="text-footnote text-fg-muted">
          {connection.menu_map.total === 0 ? t('integrations_con_menuEmpty') : t('integrations_con_menuCounts', { total: connection.menu_map.total, types: types.map(([type, count]) => `${type}: ${count}`).join(', ') })}
        </p>
      </Card>

      <Card>
        <h3 className="mb-2 text-body font-bold text-fg">{t('integrations_con_recent')}</h3>
        {connection.recent_events.length === 0 ? <p className="text-footnote text-fg-muted">{t('integrations_ev_none')}</p> : <EventList rows={connection.recent_events} onSelect={onOpenEvent} />}
      </Card>

      {can('integrations.manage') && (
        <div role="group" aria-label={t('integrations_actions')} className="flex flex-wrap gap-2">
          {connection.status === 'paused' ? (
            <Button onClick={() => setDialog('resume')}>{t('integrations_con_resume')}</Button>
          ) : (
            <Button variant="danger" onClick={() => setDialog('pause')}>
              {t('integrations_con_pause')}
            </Button>
          )}
        </div>
      )}

      {dialog && (
        <ReasonDialog
          title={dialog === 'pause' ? t('integrations_con_pauseTitle', { provider: connection.provider_label, store: connection.store?.name ?? '—' }) : t('integrations_con_resumeTitle', { provider: connection.provider_label, store: connection.store?.name ?? '—' })}
          text={dialog === 'pause' ? t('integrations_con_pauseText') : t('integrations_con_resumeText')}
          submitLabel={dialog === 'pause' ? t('integrations_con_pause') : t('integrations_con_resume')}
          tone={dialog === 'pause' ? 'danger' : 'primary'}
          busy={mutation.isPending}
          error={mutation.error ? errorText(mutation.error, t) : null}
          reasonError={fieldError(mutation.error, 'reason')}
          onClose={() => {
            setDialog(null);
            pause.reset();
            resume.reset();
          }}
          onSubmit={(reason) =>
            mutation.mutate(
              { uuid: connection.uuid, reason },
              {
                onSuccess: () => {
                  setNotice(dialog === 'pause' ? t('integrations_con_paused') : t('integrations_con_resumed'));
                  setDialog(null);
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
