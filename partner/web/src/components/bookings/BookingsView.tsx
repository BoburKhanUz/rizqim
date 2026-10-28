'use client';

import { useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { Button, CardSkeletons, EmptyState, IconButton, InlineError, cx, PageHeader, Notice } from '@rizqim/ui';
import { useBookingCalendar, useFeature, type BookingCalendar } from '@rizqim/staff';
import { addDays, mondayOf, todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { PlanButton, PlanNotice } from '../billing/PlanGate';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { Check } from '../management/fields';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { DateField } from '../pos/fields';
import { BookingCalendarView } from './BookingCalendarView';
import { BookingDetailDialog } from './BookingDetailDialog';
import { BookingFormDialog, type BookingFormTarget } from './BookingFormDialog';

type Mode = 'day' | 'week';
const DEFAULT_ZONE = 'Asia/Tashkent';

// /bookings: the booking calendar (rooms by time, one day or a week). A booking is read, made, edited, confirmed or canceled
// here with the owner's or manager's own sign-in (bookings.manage). Deposits are shown; taking or returning one is done at the till.
export function BookingsView() {
  const { t } = useI18n();
  const access = useCan();
  const choice = useBranchChoice();
  // P4-21: a new booking is the plan's `bookings`; the ones that exist stay open (confirm, arrive, cancel)
  const canAdd = useFeature('bookings').allowed;
  const [mode, setMode] = useState<Mode>('day');
  const [day, setDay] = useState<string | null>(null);
  const [withCanceled, setWithCanceled] = useState(false);
  const [open, setOpen] = useState<string | null>(null);
  const [form, setForm] = useState<BookingFormTarget | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // The store's today until a day is chosen (the calendar answers with the store's time zone; its own is the default)
  const [zoneGuess] = useState(DEFAULT_ZONE);
  const today = todayIn(zoneGuess);
  const chosenDay = day ?? today;
  const from = mode === 'week' ? mondayOf(chosenDay) : chosenDay;
  const to = mode === 'week' ? addDays(from, 6) : chosenDay;
  const query = choice.branchId === null ? null : { branch_id: choice.branchId, from, to, include_canceled: withCanceled };
  const calendar = useBookingCalendar(query);
  const step = mode === 'week' ? 7 : 1;

  const forbidden = access.ready && !access.can('bookings.manage');
  const data: BookingCalendar | undefined = calendar.data;
  const rooms = useMemo(() => data?.rooms ?? [], [data]);

  if (forbidden) {
    return (
      <>
        <PageHeader title={t('nav_bookings')} />
        <EmptyState icon={CalendarDays} title={t('mgmt_forbidden')} text={t('book_noAccess')} />
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t('nav_bookings')}
        subtitle={t('book_subtitle')}
        action={
          choice.branchId !== null && (
            <PlanButton feature="bookings" icon={Plus} onClick={() => setForm({ kind: 'create', day: chosenDay, roomId: null })}>
              {t('book_add')}
            </PlanButton>
          )
        }
      />
      <Notice message={notice} />
      <PlanNotice feature="bookings" />

      {choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(null, t)} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.isPending || !access.ready ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : choice.branchId === null ? (
        <EmptyState icon={CalendarDays} title={t('book_noBranch')} text={t('book_noBranchText')} />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-end gap-3">
            <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="sm:min-w-56" />
            <div role="group" aria-label={t('book_mode')} className="flex gap-1 rounded-control border border-line bg-surface p-1">
              {(['day', 'week'] as const).map((id) => (
                <button
                  key={id}
                  type="button"
                  aria-pressed={mode === id}
                  onClick={() => setMode(id)}
                  className={cx('min-h-11 rounded-control px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-brand', mode === id ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg-muted hover:bg-surface-2')}
                >
                  {t(`book_mode_${id}`)}
                </button>
              ))}
            </div>
            <div className="flex items-end gap-1">
              <IconButton icon={ChevronLeft} label={t('book_prev')} onClick={() => setDay(addDays(chosenDay, -step))} />
              <DateField label={t('book_date')} value={chosenDay} onChange={(value) => value && setDay(value)} />
              <IconButton icon={ChevronRight} label={t('book_next')} onClick={() => setDay(addDays(chosenDay, step))} />
              <Button variant="secondary" onClick={() => setDay(today)} disabled={chosenDay === today && mode === 'day'}>
                {t('book_today')}
              </Button>
            </div>
            <Check checked={withCanceled} onChange={setWithCanceled} label={t('book_withCanceled')} />
          </div>

          {calendar.isError && !data ? (
            <InlineError title={t('loadErrorTitle')} message={errorText(calendar.error, t)} retryLabel={t('retry')} retrying={calendar.isFetching} onRetry={() => void calendar.refetch()} />
          ) : !data ? (
            <CardSkeletons count={2} lines={4} label={t('loading')} />
          ) : (
            <BookingCalendarView
              calendar={data}
              mode={mode}
              from={from}
              day={chosenDay}
              onOpen={setOpen}
              onAdd={(roomId, forDay) => {
                if (canAdd) setForm({ kind: 'create', day: forDay, roomId });
              }}
              busy={calendar.isFetching}
            />
          )}
        </div>
      )}

      {open && (
        <BookingDetailDialog
          uuid={open}
          rooms={rooms}
          timeZone={data?.timezone ?? zoneGuess}
          onClose={() => setOpen(null)}
          onEdit={(booking) => {
            setOpen(null);
            setForm({ kind: 'edit', booking });
          }}
          onDone={(message) => {
            setNotice(message);
            setOpen(null);
          }}
        />
      )}
      {form && data && (
        <BookingFormDialog
          target={form}
          branchId={choice.branchId as number}
          rooms={rooms}
          timeZone={data.timezone}
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
