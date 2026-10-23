'use client';

import { Plus } from 'lucide-react';
import { Card, EmptyState, cx, formatDate, formatMoney } from '@rizqim/ui';
import { CalendarDays } from 'lucide-react';
import type { Booking, BookingCalendar } from '@rizqim/staff';
import { addDays, inZone, isoWeekday } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { labelFor, localDate } from '../reports/format';
import { axisFor, byDay, clock, hourMarks, place, STATUS_TONE, type StatusTone } from './calendarModel';

const TONE: Record<StatusTone, string> = {
  warning: 'border-warning bg-warning-soft text-warning-soft-fg',
  info: 'border-info bg-info-soft text-info-soft-fg',
  success: 'border-success bg-success-soft text-success-soft-fg',
  neutral: 'border-line bg-surface-2 text-fg-muted',
  danger: 'border-danger bg-danger-soft text-danger-soft-fg',
};

const toneOf = (status: string) => TONE[STATUS_TONE[status] ?? 'neutral'];

// One booking as a chip: the time, the name, the guests, the status in words and the deposit when there is one
export function BookingChip({ booking, timeZone, onOpen, className, style }: { booking: Booking; timeZone: string; onOpen: (uuid: string) => void; className?: string; style?: React.CSSProperties }) {
  const { t, language } = useI18n();
  const starts = inZone(booking.starts_at, timeZone);
  const ends = inZone(booking.ends_at, timeZone);
  const range = `${starts?.time ?? '—'}–${ends?.time ?? '—'}`;
  const deposit = Number(booking.deposit_available) > 0 ? t('book_depositShort', { amount: formatMoney(booking.deposit_available, language) }) : null;
  const off = booking.status === 'canceled' || booking.status === 'no_show';
  return (
    <button
      type="button"
      onClick={() => onOpen(booking.uuid)}
      aria-label={t('book_openAria', { name: booking.contact_name, range, status: labelFor(t, 'book_st_', booking.status) })}
      className={cx('flex min-h-11 flex-col items-start justify-center overflow-hidden rounded-control border px-2 py-1 text-left text-caption focus-visible:outline-2 focus-visible:outline-brand', toneOf(booking.status), off && 'opacity-70', className)}
      style={style}
    >
      <span className={cx('max-w-full truncate font-bold', off && 'line-through')}>
        {range} · {booking.contact_name}
      </span>
      <span className="max-w-full truncate">
        {t('book_guestsShort', { n: booking.guests_planned })} · {labelFor(t, 'book_st_', booking.status)}
        {deposit ? ` · ${deposit}` : ''}
      </span>
    </button>
  );
}

// The calendar of a branch: rooms as rows; a day is a time line, a week is seven columns
export function BookingCalendarView({
  calendar,
  mode,
  from,
  day,
  onOpen,
  onAdd,
  busy,
}: {
  calendar: BookingCalendar;
  mode: 'day' | 'week';
  from: string;
  day: string;
  onOpen: (uuid: string) => void;
  onAdd: (roomId: number | null, day: string) => void;
  busy: boolean;
}) {
  const { t, language } = useI18n();
  const zone = calendar.timezone;
  const all = [...calendar.rooms.flatMap((room) => room.bookings), ...calendar.unassigned];

  if (calendar.rooms.length === 0 && calendar.unassigned.length === 0) {
    return <EmptyState icon={CalendarDays} title={t('book_noRooms')} text={t('book_noRoomsText')} />;
  }

  return (
    <div aria-busy={busy || undefined} className={cx('flex flex-col gap-4', busy && 'opacity-70 transition-opacity')}>
      {mode === 'day' ? <DayLine calendar={calendar} day={day} onOpen={onOpen} onAdd={onAdd} all={all} /> : <WeekGrid calendar={calendar} from={from} onOpen={onOpen} onAdd={onAdd} language={language} />}

      {calendar.unassigned.length > 0 && (
        <Card className="flex flex-col gap-2" aria-label={t('book_unassigned')}>
          <h2 className="text-headline font-bold text-fg">{t('book_unassigned')}</h2>
          <p className="text-footnote text-fg-muted">{t('book_unassignedHint')}</p>
          <ul className="flex flex-wrap gap-2">
            {calendar.unassigned.map((booking) => (
              <li key={booking.uuid} className="min-w-56 max-w-sm flex-1">
                <BookingChip booking={booking} timeZone={zone} onOpen={onOpen} className="w-full" />
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  );
}

function DayLine({ calendar, day, onOpen, onAdd, all }: { calendar: BookingCalendar; day: string; onOpen: (uuid: string) => void; onAdd: (roomId: number | null, day: string) => void; all: Booking[] }) {
  const { t } = useI18n();
  const zone = calendar.timezone;
  const axis = axisFor(all, day, zone);
  const marks = hourMarks(axis);
  const length = axis.end - axis.start;
  return (
    <Card padded={false} className="overflow-x-auto">
      <div className="min-w-[56rem]">
        <div className="grid grid-cols-[10rem_1fr] border-b border-line">
          <div className="px-3 py-2 text-footnote font-bold text-fg-muted">{t('book_rooms')}</div>
          <div className="relative h-8">
            {marks.map((minute) => (
              <span key={minute} className="absolute top-2 -translate-x-1/2 text-caption text-fg-muted" style={{ left: `${((minute - axis.start) / length) * 100}%` }}>
                {clock(minute)}
              </span>
            ))}
          </div>
        </div>
        {calendar.rooms.map((room) => (
          <div key={room.id} role="group" aria-label={room.name} className="grid grid-cols-[10rem_1fr] border-b border-line last:border-b-0">
            <div className="flex min-w-0 flex-col justify-center gap-0.5 px-3 py-2">
              <span className="truncate text-body font-bold text-fg">{room.name}</span>
              <span className="truncate text-caption text-fg-muted">
                {room.hall_name ?? ''}
                {room.seats ? ` · ${t('book_seats', { n: room.seats })}` : ''}
              </span>
              <button type="button" onClick={() => onAdd(room.id, day)} aria-label={t('book_addRoomAria', { name: room.name })} className="inline-flex min-h-8 w-fit items-center gap-1 text-caption font-bold text-brand focus-visible:outline-2 focus-visible:outline-brand">
                <Plus aria-hidden="true" className="size-3.5" />
                {t('book_addShort')}
              </button>
            </div>
            <div className="relative min-h-16">
              {marks.map((minute) => (
                <span key={minute} aria-hidden="true" className="absolute inset-y-0 w-px bg-line" style={{ left: `${((minute - axis.start) / length) * 100}%` }} />
              ))}
              {room.bookings.map((booking) => {
                const placed = place(booking, day, zone, axis);
                if (!placed) return null;
                return (
                  <BookingChip
                    key={booking.uuid}
                    booking={booking}
                    timeZone={zone}
                    onOpen={onOpen}
                    className="absolute inset-y-1"
                    style={{ left: `${placed.left}%`, width: `${Math.min(placed.width, 100 - placed.left)}%` }}
                  />
                );
              })}
            </div>
          </div>
        ))}
      </div>
    </Card>
  );
}

function WeekGrid({ calendar, from, onOpen, onAdd, language }: { calendar: BookingCalendar; from: string; onOpen: (uuid: string) => void; onAdd: (roomId: number | null, day: string) => void; language: 'uz' | 'ru' | 'en' }) {
  const { t } = useI18n();
  const zone = calendar.timezone;
  const days = Array.from({ length: 7 }, (_, index) => addDays(from, index));
  return (
    <Card padded={false} className="overflow-x-auto">
      <table className="w-full min-w-[60rem] table-fixed border-collapse text-left">
        <caption className="sr-only">{t('book_mode_week')}</caption>
        <thead>
          <tr>
            <th scope="col" className="w-32 px-3 py-2 text-footnote font-bold text-fg-muted">
              {t('book_rooms')}
            </th>
            {days.map((item) => (
              <th key={item} scope="col" className="px-2 py-2 text-footnote font-bold text-fg-muted">
                {formatDate(localDate(item), language)}
                <span className="block text-caption font-medium">{t(`floor_cal_d${isoWeekday(item)}` as 'floor_cal_d1')}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {calendar.rooms.map((room) => {
            const grouped = byDay(room.bookings, zone);
            return (
              <tr key={room.id} className="border-t border-line align-top">
                <th scope="row" className="px-3 py-2 text-body font-bold text-fg">
                  {room.name}
                  <span className="block text-caption font-medium text-fg-muted">{room.hall_name ?? ''}</span>
                </th>
                {days.map((item) => (
                  <td key={item} className="px-1 py-1">
                    <div className="flex flex-col gap-1">
                      {(grouped.get(item) ?? []).map((booking) => (
                        <BookingChip key={booking.uuid} booking={booking} timeZone={zone} onOpen={onOpen} className="w-full" />
                      ))}
                      <button
                        type="button"
                        onClick={() => onAdd(room.id, item)}
                        aria-label={t('book_addCellAria', { room: room.name, date: item })}
                        className="inline-flex min-h-8 items-center justify-center rounded-control text-fg-muted hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-brand"
                      >
                        <Plus aria-hidden="true" className="size-4" />
                      </button>
                    </div>
                  </td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </Card>
  );
}
