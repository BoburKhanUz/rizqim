'use client';

import { useState } from 'react';
import Link from 'next/link';
import { CalendarHeart, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { Button, CardSkeletons, EmptyState, IconButton, InlineError, Notice, PageHeader, cx } from '@rizqim/ui';
import { todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { PlanButton, PlanNotice } from '../billing/PlanGate';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { Check } from '../management/fields';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { DateField } from '../pos/fields';
import { NAMED_SLOTS, type EventStatus, type EventSummary, type Slot } from './api';
import { BanquetEventDialog, type EventFormTarget } from './BanquetEventDialog';
import { daysOf, eventsOn, rangeOf, slotFree, stepFrom, type Mode } from './calendarModel';
import { useBanquetCalendar } from './hooks';

const DEFAULT_ZONE = 'Asia/Tashkent';

export const STATUS_CLASS: Record<EventStatus, string> = {
  hold: 'border-warning bg-warning-soft text-warning-soft-fg',
  confirmed: 'border-success bg-success-soft text-success-soft-fg',
  in_progress: 'border-info bg-info-soft text-info-soft-fg',
  settled: 'border-line bg-surface-2 text-fg-muted',
  cancelled: 'border-line bg-surface text-fg-muted line-through',
};

// /banquets: the events calendar (P6-51) — halls down, days across, each day's morning / day / evening (and custom
// times) per hall. A free slot's "+" holds the hall for a new event; an event opens its page. Reading with
// banquets.manage (the panel); the till's cashier sees it at the till.
export function BanquetsView() {
  const { t } = useI18n();
  const access = useCan();
  const choice = useBranchChoice();
  const [mode, setMode] = useState<Mode>('month');
  const [day, setDay] = useState<string | null>(null);
  const [withCancelled, setWithCancelled] = useState(false);
  const [form, setForm] = useState<EventFormTarget | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const today = todayIn(DEFAULT_ZONE);
  const chosen = day ?? today;
  const { from, to } = rangeOf(mode, chosen);
  const calendar = useBanquetCalendar(
    choice.branchId === null
      ? null
      : {
          branch_id: choice.branchId,
          from,
          to,
          include_cancelled: withCancelled,
        },
  );
  const data = calendar.data;
  const days = daysOf(from, to);
  const canManage = access.can('banquets.manage');

  if (access.ready && !canManage) {
    return (
      <>
        <PageHeader title={t('bq_title')} />
        <EmptyState icon={CalendarHeart} title={t('mgmt_forbidden')} text={t('bq_noAccess')} />
      </>
    );
  }

  const add = (forDay: string, hallId: number | null, slot: Slot) => setForm({ kind: 'create', day: forDay, hallId, slot });

  return (
    <>
      <PageHeader
        title={t('bq_title')}
        subtitle={t('bq_subtitle')}
        action={
          <div className="flex flex-wrap items-center gap-3">
            <Link href="/banquets/setup" className="font-bold text-brand underline-offset-2 hover:underline">
              {t('bq_setupNav')}
            </Link>
            {/* P6-53: the "Tadbirlar" report and the payment rules */}
            <Link href="/banquets/report" className="font-bold text-brand underline-offset-2 hover:underline">
              {t('bqp_reportNav')}
            </Link>
            {/* P6-54: the shopping list and (banquets.manage) the production settings */}
            <Link href="/banquets/shopping" className="font-bold text-brand underline-offset-2 hover:underline">
              {t('bqo_navShopping')}
            </Link>
            {canManage && (
              <Link href="/banquets/ops-settings" className="font-bold text-brand underline-offset-2 hover:underline">
                {t('bqo_navSettings')}
              </Link>
            )}
            {choice.branchId !== null && (
              <PlanButton feature="banquet" icon={Plus} onClick={() => add(chosen < today ? today : chosen, null, 'evening')}>
                {t('bq_add')}
              </PlanButton>
            )}
          </div>
        }
      />
      <Notice message={notice} />
      <PlanNotice feature="banquet" />

      {choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(null, t)} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.isPending || !access.ready ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : choice.branchId === null ? (
        <EmptyState icon={CalendarHeart} title={t('bq_noBranch')} text={t('bq_noBranchText')} />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-end gap-3">
            <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="sm:min-w-56" />
            <div role="group" aria-label={t('bq_mode')} className="flex gap-1 rounded-control border border-line bg-surface p-1">
              {(['month', 'week'] as const).map((id) => (
                <button
                  key={id}
                  type="button"
                  aria-pressed={mode === id}
                  onClick={() => setMode(id)}
                  className={cx(
                    'min-h-11 rounded-control px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-brand',
                    mode === id ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:bg-surface-2',
                  )}
                >
                  {t(`bq_mode_${id}`)}
                </button>
              ))}
            </div>
            <div className="flex items-end gap-1">
              <IconButton icon={ChevronLeft} label={t('bq_prev')} onClick={() => setDay(stepFrom(mode, chosen, -1))} />
              <DateField label={t('bq_date')} value={chosen} onChange={(value) => value && setDay(value)} />
              <IconButton icon={ChevronRight} label={t('bq_next')} onClick={() => setDay(stepFrom(mode, chosen, 1))} />
              <Button variant="secondary" onClick={() => setDay(today)}>
                {t('bq_today')}
              </Button>
            </div>
            <Check checked={withCancelled} onChange={setWithCancelled} label={t('bq_withCancelled')} />
          </div>

          {calendar.isError && !data ? (
            <InlineError title={t('loadErrorTitle')} message={errorText(calendar.error, t)} retryLabel={t('retry')} retrying={calendar.isFetching} onRetry={() => void calendar.refetch()} />
          ) : !data ? (
            <CardSkeletons count={2} lines={4} label={t('loading')} />
          ) : data.halls.length === 0 ? (
            <EmptyState icon={CalendarHeart} title={t('bq_noHalls')} text={t('bq_noHallsText')} />
          ) : (
            <div className="overflow-x-auto rounded-card border border-line bg-surface" aria-busy={calendar.isFetching}>
              <table className="min-w-full border-collapse text-footnote">
                <caption className="sr-only">{t('bq_calendarCaption', { from: data.from, to: data.to })}</caption>
                <thead>
                  <tr>
                    <th scope="col" className="sticky left-0 z-10 bg-surface p-2 text-left font-bold">
                      {t('bq_hall')}
                    </th>
                    {days.map((d) => (
                      <th key={d} scope="col" className={cx('min-w-28 border-l border-line p-2 text-left font-bold', d === today && 'bg-brand-soft text-brand-soft-fg')}>
                        {d.slice(8)}.{d.slice(5, 7)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.halls.map((hall) => (
                    <tr key={hall.id} className="border-t border-line align-top">
                      <th scope="row" className="sticky left-0 z-10 bg-surface p-2 text-left font-bold">
                        {hall.name}
                      </th>
                      {days.map((d) => (
                        <td key={d} className="border-l border-line p-1">
                          <div className="flex flex-col gap-1">
                            {eventsOn(hall, d).map((event) => (
                              <EventChip key={event.uuid} event={event} merged={event.hall_ids.length > 1} />
                            ))}
                            {canManage && d >= today && (
                              <div className="flex gap-1">
                                {NAMED_SLOTS.filter((slot) => slotFree(hall, d, slot, data.slots)).map((slot) => (
                                  <button
                                    key={slot}
                                    type="button"
                                    onClick={() => add(d, hall.id, slot)}
                                    aria-label={t('bq_holdSlot', {
                                      hall: hall.name,
                                      day: d,
                                      slot: t(`bq_slot_${slot}`),
                                    })}
                                    className="min-h-8 rounded-control border border-dashed border-line px-2 text-caption text-fg-muted hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-brand"
                                  >
                                    + {t(`bq_slotShort_${slot}`)}
                                  </button>
                                ))}
                              </div>
                            )}
                          </div>
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {form && choice.branchId !== null && (
        <BanquetEventDialog
          target={form}
          branchId={choice.branchId}
          onClose={() => setForm(null)}
          onSaved={(message) => {
            setNotice(message);
            setForm(null);
          }}
        />
      )}
    </>
  );
}

function EventChip({ event, merged }: { event: EventSummary; merged: boolean }) {
  const { t } = useI18n();
  return (
    <Link
      href={`/banquets/${event.uuid}`}
      className={cx('block rounded-control border px-2 py-1 font-bold focus-visible:outline-2 focus-visible:outline-brand', STATUS_CLASS[event.status])}
      aria-label={t('bq_openEvent', {
        name: event.customer_name,
        time: `${event.start_time}–${event.end_time}`,
        status: event.status_label,
      })}
    >
      <span className="block">
        {event.start_time}–{event.end_time}
      </span>
      <span className="block truncate font-normal">{event.customer_name}</span>
      <span className="block font-normal">
        {t('bq_guestsShort', { n: event.guests_billed })}
        {merged ? ` · ${t('bq_merged')}` : ''}
      </span>
      {event.overdue && <span className="mt-0.5 inline-block rounded-control bg-danger px-1 text-caption font-bold text-on-danger">{t('bqp_overdue')}</span>}
    </Link>
  );
}
