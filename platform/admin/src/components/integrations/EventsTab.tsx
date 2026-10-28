'use client';

import { useState } from 'react';
import { ChevronRight, ScrollText } from 'lucide-react';
import { Button, Card, CardSkeletons, Field, fieldClass, InlineError, Pill, type PillTone } from '@rizqim/ui';
import { DEFAULT_EVENT_FILTERS, useEvent, useEvents, type EventFilters, type EventRow, type EventStatus } from '@/lib/api/integrations';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { BackButton, Chip, ChipRow, Facts, ListState } from '../system/parts';

const STATUSES: readonly EventStatus[] = ['pending', 'sent', 'acknowledged', 'failed', 'dead'];

export const EVENT_TONE: Record<EventStatus, PillTone> = { pending: 'info', sent: 'info', acknowledged: 'success', failed: 'warning', dead: 'danger' };

// Hodisalar jurnali: every call to or from an outside system, newest first, by cursor ("Ko'proq"). A row opens the event with its
// request and response bodies (secrets hidden, phone numbers masked by the backend). "Qayta yuborish" comes with phase 5.
export function EventsTab({ selected, onSelect }: { selected: string | null; onSelect: (uuid: string | null) => void }) {
  if (selected) return <EventDetailView uuid={selected} onBack={() => onSelect(null)} />;
  return <EventsList onSelect={onSelect} />;
}

export function EventList({ rows, onSelect }: { rows: EventRow[]; onSelect: (uuid: string) => void }) {
  const t = useT();
  return (
    <ul aria-label={t('integrations_ev_listLabel')} className="flex flex-col gap-2">
      {rows.map((row) => (
        <li key={row.uuid}>
          <Card padded={false}>
            <button
              type="button"
              onClick={() => onSelect(row.uuid)}
              aria-label={t('integrations_ev_open', { type: row.type, time: when(row.created_at) })}
              className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            >
              <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="break-all font-mono text-footnote font-bold text-fg">{row.type}</span>
                  <Pill tone={EVENT_TONE[row.status]}>{row.status_label}</Pill>
                  {row.http_status !== null && <Pill tone={row.http_status >= 400 ? 'danger' : 'neutral'}>HTTP {row.http_status}</Pill>}
                </span>
                <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
                  <span>{row.direction_label}</span>
                  <span>{row.connection ? `${row.connection.provider_label} · ${row.connection.store?.name ?? '—'}` : t('integrations_ev_noConnection')}</span>
                  <span>{when(row.created_at)}</span>
                  {row.attempts > 1 && <span>{t('integrations_ev_attempts', { count: row.attempts })}</span>}
                </span>
                {row.error && <span className="break-words text-footnote text-danger">{row.error}</span>}
              </span>
              <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
            </button>
          </Card>
        </li>
      ))}
    </ul>
  );
}

function EventsList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<EventFilters>(DEFAULT_EVENT_FILTERS);
  const [type, setType] = useState('');
  const events = useEvents(filters);
  const rows = events.data?.pages.flatMap((page) => page.data) ?? [];
  const filtered = filters.status !== '' || filters.direction !== '' || filters.type !== '';

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('integrations_ev_statusFilter')}>
        <Chip on={filters.status === ''} onClick={() => setFilters((previous) => ({ ...previous, status: '' }))}>
          {t('integrations_all')}
        </Chip>
        {STATUSES.map((status) => (
          <Chip key={status} on={filters.status === status} onClick={() => setFilters((previous) => ({ ...previous, status }))}>
            {t(`integrations_ev_status_${status}`)}
          </Chip>
        ))}
      </ChipRow>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('integrations_direction')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.direction} onChange={(event) => setFilters((previous) => ({ ...previous, direction: event.target.value as EventFilters['direction'] }))}>
              <option value="">{t('integrations_all')}</option>
              <option value="outbound">{t('integrations_ev_outbound')}</option>
              <option value="inbound">{t('integrations_ev_inbound')}</option>
            </select>
          )}
        </Field>
        <Field label={t('integrations_ev_type')} hint={t('integrations_ev_typeHint')}>
          {(control) => (
            <input
              {...control}
              value={type}
              placeholder="order.push"
              onChange={(event) => setType(event.target.value)}
              onBlur={() => setFilters((previous) => ({ ...previous, type: /^[a-z0-9_.]*$/.test(type.trim()) ? type.trim() : previous.type }))}
              onKeyDown={(event) => {
                if (event.key === 'Enter') setFilters((previous) => ({ ...previous, type: /^[a-z0-9_.]*$/.test(type.trim()) ? type.trim() : previous.type }));
              }}
              className={fieldClass}
            />
          )}
        </Field>
      </div>
      <ListState
        loading={!events.data}
        error={events.isError ? events.error : null}
        fetching={events.isFetching}
        onRetry={() => void events.refetch()}
        empty={rows.length === 0}
        emptyTitle={filtered ? t('integrations_ev_empty') : t('integrations_ev_none')}
        emptyText={filtered ? undefined : t('integrations_ev_noneText')}
        icon={ScrollText}
        errorTitle={t('integrations_loadError')}
      >
        <EventList rows={rows} onSelect={onSelect} />
        {events.hasNextPage && (
          <Button variant="secondary" loading={events.isFetchingNextPage} onClick={() => void events.fetchNextPage()}>
            {t('integrations_more')}
          </Button>
        )}
      </ListState>
    </div>
  );
}

const pretty = (body: unknown) => (body === null || body === undefined ? null : JSON.stringify(body, null, 2));

function EventDetailView({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const t = useT();
  const event = useEvent(uuid);
  if (event.isError) return <InlineError title={t('integrations_loadError')} message={errorText(event.error, t)} retryLabel={t('retry')} retrying={event.isFetching} onRetry={() => void event.refetch()} />;
  if (!event.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const row = event.data;
  const signature = row.signature_valid === null ? '—' : row.signature_valid ? t('integrations_ev_signatureOk') : t('integrations_ev_signatureBad');

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="min-w-0 break-all font-mono text-headline font-bold text-fg">{row.type}</h2>
          <Pill tone={EVENT_TONE[row.status]}>{row.status_label}</Pill>
        </div>
        <Facts
          rows={[
            [t('integrations_direction'), row.direction_label],
            [t('integrations_ev_connection'), row.connection ? `${row.connection.provider_label} · ${row.connection.store?.name ?? '—'}` : t('integrations_ev_noConnection')],
            [t('integrations_ev_http'), row.http_status === null ? '—' : String(row.http_status)],
            [t('integrations_ev_attemptsLabel'), String(row.attempts)],
            [t('integrations_ev_nextAttempt'), when(row.next_attempt_at)],
            [t('integrations_ev_lastAttempt'), when(row.last_attempt_at)],
            [t('integrations_ev_signature'), signature],
            [t('integrations_ev_key'), row.idempotency_key ?? '—'],
            [t('integrations_ev_subject'), row.subject ? `${row.subject.type} #${row.subject.id ?? '—'}` : '—'],
            [t('integrations_ev_duration'), row.duration_ms === null ? '—' : t('integrations_ev_ms', { ms: row.duration_ms })],
            [t('integrations_ev_created'), when(row.created_at)],
          ]}
        />
        {row.error && <p className="mt-3 break-words rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">{row.error}</p>}
      </Card>
      {([['integrations_ev_request', row.request_body], ['integrations_ev_response', row.response_body]] as const).map(([label, body]) => (
        <Card key={label}>
          <h3 className="mb-2 text-body font-bold text-fg">{t(label)}</h3>
          {pretty(body) === null ? (
            <p className="text-footnote text-fg-muted">{t('integrations_ev_noBody')}</p>
          ) : (
            <pre className="max-h-96 overflow-auto whitespace-pre-wrap break-all rounded-control bg-surface-2 p-3 font-mono text-caption text-fg">{pretty(body)}</pre>
          )}
        </Card>
      ))}
      <p className="text-caption text-fg-muted">{t('integrations_ev_redactedHint')}</p>
    </div>
  );
}
