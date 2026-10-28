'use client';

import { useState } from 'react';
import { ChevronRight, Wallet } from 'lucide-react';
import { Card, CardSkeletons, Field, fieldClass, InlineError, Pill, Section } from '@rizqim/ui';
import { DEFAULT_SHIFT_FILTERS, SHIFT_KINDS, useShift, useShifts, type ShiftFilters, type ShiftRow } from '@/lib/api/pos';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { day, money } from './posModel';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager } from './PosParts';

// Smenalar (P4-24): the till shifts of every store, read-only. A cash difference is counted minus expected (negative: cash is
// missing); a forced close is a manager closing the shift without the cashier.

function Difference({ row }: { row: Pick<ShiftRow, 'difference' | 'has_difference' | 'status'> }) {
  const t = useT();
  if (row.status === 'open' || row.difference === null) return <>{t('pos_shift_noDifference')}</>;
  if (!row.has_difference) return <>{t('pos_shift_exact')}</>;
  const value = Number(row.difference);
  return <span className="font-bold text-danger-soft-fg">{t(value < 0 ? 'pos_shift_missing' : 'pos_shift_surplus', { amount: money(Math.abs(value)) })}</span>;
}

export function ShiftsTab({ selected, onSelect }: { selected: string | null; onSelect: (uuid: string | null) => void }) {
  if (selected) return <ShiftCardView uuid={selected} onBack={() => onSelect(null)} />;
  return <ShiftList onSelect={onSelect} />;
}

function ShiftList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<ShiftFilters>(DEFAULT_SHIFT_FILTERS);
  const shifts = useShifts(filters);
  const page = shifts.data;
  const counts = page?.counts;
  const set = <K extends keyof ShiftFilters>(name: K, value: ShiftFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));
  const KIND_LABEL = { difference: 'pos_shift_kind_difference', force_closed: 'pos_shift_kind_force_closed', open: 'pos_shift_kind_open' } as const;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('pos_shift_kindFilter')}>
        <Chip on={filters.kind === ''} onClick={() => set('kind', '')}>
          {counts ? t('pos_countLabel', { label: t('pos_all'), count: counts.all }) : t('pos_all')}
        </Chip>
        {SHIFT_KINDS.map((kind) => (
          <Chip key={kind} on={filters.kind === kind} onClick={() => set('kind', kind)}>
            {counts ? t('pos_countLabel', { label: t(KIND_LABEL[kind]), count: counts[kind] }) : t(KIND_LABEL[kind])}
          </Chip>
        ))}
      </ChipRow>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={t('pos_from')}>{(control) => <input {...control} type="date" className={fieldClass} value={filters.from} onChange={(event) => set('from', event.target.value)} />}</Field>
        <Field label={t('pos_to')}>{(control) => <input {...control} type="date" className={fieldClass} value={filters.to} onChange={(event) => set('to', event.target.value)} />}</Field>
      </div>

      <ListState
        loading={!page}
        error={shifts.isError ? shifts.error : null}
        fetching={shifts.isFetching}
        onRetry={() => void shifts.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={t('pos_shift_empty')}
        emptyText={t('pos_shift_emptyText')}
        icon={Wallet}
        errorTitle={t('pos_loadError')}
      >
        <p className="text-footnote text-fg-muted" aria-live="polite">
          {t('pos_total', { count: page?.total ?? 0 })}
        </p>
        <ul aria-label={t('pos_shift_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => (
            <li key={row.uuid}>
              <Card padded={false}>
                <button
                  type="button"
                  onClick={() => onSelect(row.uuid)}
                  aria-label={t('pos_shift_open', { store: row.store?.name ?? '', date: day(row.business_date) })}
                  className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                  <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="break-words text-body font-bold text-fg">{row.store?.name ?? '—'}</span>
                      {row.status === 'open' && <Pill tone="info">{t('pos_shift_openNow')}</Pill>}
                      {row.force_closed && <Pill tone="warning">{t('pos_shift_forced')}</Pill>}
                      {row.has_difference && <Pill tone="danger">{t('pos_shift_hasDifference')}</Pill>}
                    </span>
                    <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
                      <span>{day(row.business_date)}</span>
                      {row.branch && <span>{row.branch.name}</span>}
                      {row.device && <span>{row.device.name}</span>}
                      {row.z_number !== null && <span>Z-{row.z_number}</span>}
                      <span>
                        <Difference row={row} />
                      </span>
                    </span>
                  </span>
                  <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                </button>
              </Card>
            </li>
          ))}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => set('page', next)} />}
      </ListState>
    </div>
  );
}

function ShiftCardView({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const t = useT();
  const card = useShift(uuid);
  if (card.isError) return <InlineError title={t('pos_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const shift = card.data;
  const notes = Object.entries(shift.counted_breakdown ?? {}).sort((a, b) => Number(b[0]) - Number(a[0]));

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">
            {shift.store?.name ?? '—'} · {day(shift.business_date)}
          </h2>
          {shift.status === 'open' && <Pill tone="info">{t('pos_shift_openNow')}</Pill>}
          {shift.force_closed && <Pill tone="warning">{t('pos_shift_forced')}</Pill>}
        </div>
        <Facts
          rows={[
            [t('pos_branch'), shift.branch?.name ?? '—'],
            [t('pos_shift_device'), shift.device?.name ?? '—'],
            [t('pos_shift_openedBy'), `${shift.opened_by?.name ?? '—'} · ${when(shift.opened_at)}`],
            [t('pos_shift_closedBy'), shift.closed_at ? `${shift.closed_by?.name ?? '—'} · ${when(shift.closed_at)}` : '—'],
            [t('pos_shift_opening'), money(shift.opening_cash)],
            [t('pos_shift_expected'), shift.expected_cash === null ? '—' : money(shift.expected_cash)],
            [t('pos_shift_counted'), shift.counted_cash === null ? '—' : money(shift.counted_cash)],
            [t('pos_shift_difference'), <Difference key="d" row={shift} />],
            ...(shift.z_number !== null ? ([[t('pos_shift_z'), `Z-${shift.z_number}`]] as const) : []),
          ]}
        />
      </Card>

      {shift.force_closed && (
        <Card>
          <h3 className="mb-2 text-body font-bold text-fg">{t('pos_shift_forcedTitle')}</h3>
          <Facts
            rows={[
              [t('pos_shift_forcedBy'), shift.forced_by?.name ?? '—'],
              [t('pos_shift_note'), shift.close_note ?? '—'],
            ]}
          />
        </Card>
      )}

      {notes.length > 0 && (
        <Section title={t('pos_shift_banknotes')}>
          <Card padded={false}>
            <ul aria-label={t('pos_shift_banknotes')} className="divide-y divide-line">
              {notes.map(([note, count]) => (
                <li key={note} className="flex items-center justify-between p-3 text-body">
                  <span>{money(note)}</span>
                  <span className="font-bold">× {count}</span>
                </li>
              ))}
            </ul>
          </Card>
        </Section>
      )}

      <Section title={t('pos_shift_movements')}>
        {shift.cash_movements.length === 0 ? (
          <p className="px-1 text-footnote text-fg-muted">{t('pos_shift_noMovements')}</p>
        ) : (
          <Card padded={false}>
            <ul aria-label={t('pos_shift_movements')} className="divide-y divide-line">
              {shift.cash_movements.map((movement) => (
                <li key={movement.uuid} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                  <span className="min-w-0 break-words">
                    {movement.type}
                    {movement.category ? ` · ${movement.category}` : ''}
                    {movement.note ? ` · ${movement.note}` : ''}
                    <span className="text-footnote text-fg-muted">{movement.user ? ` · ${movement.user.name}` : ''}</span>
                  </span>
                  <span className="font-bold">
                    {movement.direction > 0 ? '+' : '−'}
                    {money(movement.amount)}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        )}
      </Section>
    </div>
  );
}
