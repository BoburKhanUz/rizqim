'use client';

import { useState } from 'react';
import { ChevronRight, RefreshCcw } from 'lucide-react';
import { Button, Card, CardSkeletons, InlineError, Notice, Pill, Section } from '@rizqim/ui';
import { DEFAULT_SYNC_FILTERS, useResolveSync, useSyncCard, useSyncErrors, type SyncFilters, type SyncRow } from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { SYNC_TONE } from './posModel';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager, ReasonDialog } from './PosParts';

// Oflayn sinxron xatolari (P4-24): operations a till made offline that the server rejected, or applied with a difference.
// "Hal qilindi" (somebody did what was needed by hand) and "Yopish" (nothing to do) need stores.manage, a reason and the step-up;
// a resolved rejected operation no longer keeps its till's shift from closing.

export function SyncTab({ selected, onSelect }: { selected: string | null; onSelect: (opId: string | null) => void }) {
  if (selected) return <SyncCardView opId={selected} onBack={() => onSelect(null)} />;
  return <SyncList onSelect={onSelect} />;
}

function SyncList({ onSelect }: { onSelect: (opId: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<SyncFilters>(DEFAULT_SYNC_FILTERS);
  const errors = useSyncErrors(filters);
  const page = errors.data;
  const counts = page?.counts;
  const set = <K extends keyof SyncFilters>(name: K, value: SyncFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('pos_sync_filter')}>
        <Chip on={filters.state === 'open' && filters.status === ''} onClick={() => setFilters((previous) => ({ ...previous, state: 'open', status: '', page: 1 }))}>
          {counts ? t('pos_countLabel', { label: t('pos_sync_open'), count: counts.open }) : t('pos_sync_open')}
        </Chip>
        <Chip on={filters.state === 'open' && filters.status === 'rejected'} onClick={() => setFilters((previous) => ({ ...previous, state: 'open', status: 'rejected', page: 1 }))}>
          {counts ? t('pos_countLabel', { label: t('pos_sync_rejected'), count: counts.rejected }) : t('pos_sync_rejected')}
        </Chip>
        <Chip on={filters.state === 'open' && filters.status === 'conflict'} onClick={() => setFilters((previous) => ({ ...previous, state: 'open', status: 'conflict', page: 1 }))}>
          {counts ? t('pos_countLabel', { label: t('pos_sync_conflict'), count: counts.conflict }) : t('pos_sync_conflict')}
        </Chip>
        <Chip on={filters.state === 'resolved'} onClick={() => setFilters((previous) => ({ ...previous, state: 'resolved', status: '', page: 1 }))}>
          {t('pos_sync_resolved')}
        </Chip>
      </ChipRow>
      <ListState
        loading={!page}
        error={errors.isError ? errors.error : null}
        fetching={errors.isFetching}
        onRetry={() => void errors.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={t('pos_sync_empty')}
        emptyText={t('pos_sync_emptyText')}
        icon={RefreshCcw}
        errorTitle={t('pos_loadError')}
      >
        <p className="text-footnote text-fg-muted" aria-live="polite">
          {t('pos_total', { count: page?.total ?? 0 })}
        </p>
        <ul aria-label={t('pos_sync_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => <SyncListRow key={row.op_id} row={row} onSelect={() => onSelect(row.op_id)} />)}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => set('page', next)} />}
      </ListState>
    </div>
  );
}

function SyncListRow({ row, onSelect }: { row: SyncRow; onSelect: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onSelect}
          aria-label={t('pos_sync_open_row', { type: row.type_label, store: row.store?.name ?? '' })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">
                {row.type_label} · {row.store?.name ?? '—'}
              </span>
              <Pill tone={SYNC_TONE[row.status] ?? 'neutral'}>{t(row.status === 'rejected' ? 'pos_sync_rejectedOne' : 'pos_sync_conflictOne')}</Pill>
              {row.resolution && <Pill tone="success">{t(row.resolution === 'resolved' ? 'pos_sync_resolvedOne' : 'pos_sync_dismissedOne')}</Pill>}
            </span>
            {row.message && <span className="break-words text-footnote text-fg">{row.message}</span>}
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              {row.device && <span>{row.device.name}</span>}
              {row.staff && <span>{row.staff.name}</span>}
              {row.order?.short_code && <span>#{row.order.short_code}</span>}
              <span>{when(row.received_at)}</span>
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}

function SyncCardView({ opId, onBack }: { opId: string; onBack: () => void }) {
  const t = useT();
  const { can } = useCan();
  const card = useSyncCard(opId);
  const resolve = useResolveSync();
  const [resolution, setResolution] = useState<'resolved' | 'dismissed' | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (card.isError) return <InlineError title={t('pos_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const op = card.data;

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">
            {op.type_label} · {op.store?.name ?? '—'}
          </h2>
          <Pill tone={SYNC_TONE[op.status] ?? 'neutral'}>{t(op.status === 'rejected' ? 'pos_sync_rejectedOne' : 'pos_sync_conflictOne')}</Pill>
          {op.resolution && <Pill tone="success">{t(op.resolution === 'resolved' ? 'pos_sync_resolvedOne' : 'pos_sync_dismissedOne')}</Pill>}
        </div>
        {op.message && <p className="mb-3 break-words text-body text-fg">{op.message}</p>}
        <Facts
          rows={[
            [t('pos_sync_device'), op.device?.name ?? '—'],
            [t('pos_sync_staff'), op.staff?.name ?? '—'],
            [t('pos_sync_order'), op.order ? `${op.order.short_code ? `#${op.order.short_code} · ` : ''}${op.order.status}` : '—'],
            [t('pos_sync_reason'), op.reason ?? '—'],
            [t('pos_sync_occurredAt'), when(op.occurred_at)],
            [t('pos_sync_receivedAt'), when(op.received_at)],
            [t('pos_sync_skew'), op.clock_skew_seconds === null ? '—' : `${op.clock_skew_seconds} ${t('pos_seconds')}`],
            ...(op.resolved_at ? ([[t('pos_sync_resolvedBy'), `${op.resolved_by?.name ?? '—'} · ${when(op.resolved_at)}${op.resolution_note ? ` · ${op.resolution_note}` : ''}`]] as const) : []),
          ]}
        />
      </Card>
      <Section title={t('pos_sync_payload')}>
        <Card>
          <pre className="overflow-x-auto whitespace-pre-wrap break-words text-footnote text-fg">
            {JSON.stringify(op.payload, null, 2)}
          </pre>
          <p className="mt-2 text-caption text-fg-muted">{t('pos_sync_payloadNote')}</p>
        </Card>
      </Section>
      {can('stores.manage') && !op.resolved_at && (
        <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
          <Button onClick={() => setResolution('resolved')}>{t('pos_sync_resolve')}</Button>
          <Button variant="secondary" onClick={() => setResolution('dismissed')}>
            {t('pos_sync_dismiss')}
          </Button>
        </div>
      )}
      {resolution && (
        <ReasonDialog
          title={t(resolution === 'resolved' ? 'pos_sync_resolveTitle' : 'pos_sync_dismissTitle')}
          text={t(resolution === 'resolved' ? 'pos_sync_resolveText' : 'pos_sync_dismissText')}
          submitLabel={t(resolution === 'resolved' ? 'pos_sync_resolveConfirm' : 'pos_sync_dismissConfirm')}
          busy={resolve.isPending}
          error={resolve.error ? errorText(resolve.error, t) : null}
          reasonError={fieldError(resolve.error, 'reason')}
          onClose={() => setResolution(null)}
          onSubmit={(reason) =>
            resolve.mutate(
              { opId: op.op_id, resolution, reason },
              {
                onSuccess: () => {
                  setResolution(null);
                  setNotice(t(resolution === 'resolved' ? 'pos_sync_resolveDone' : 'pos_sync_dismissDone'));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
