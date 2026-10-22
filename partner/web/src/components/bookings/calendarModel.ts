import type { Booking } from '@rizqim/staff';
import { inZone } from '@/lib/storeTime';

// The arithmetic of the booking calendar: where a booking sits on a day's time line and which day of a week it belongs to.
// Everything is read in the store's time zone (the API sends UTC instants).

export const DAY_MINUTES = 24 * 60;
// The line shows the evening hours a choyxona lives in; it grows to hold a booking outside them
export const DEFAULT_AXIS = { start: 8 * 60, end: 24 * 60 };

export interface Axis {
  start: number;
  end: number;
}

export interface Placed {
  // 0..100, percent of the line
  left: number;
  width: number;
  // The booking runs into the next day (or began the day before): the chip says so
  continuesAfter: boolean;
  continuesBefore: boolean;
}

// The booking's start and end as minutes of the store's `day` (clipped to the day: a booking over midnight ends at 24:00)
export function minutesOnDay(booking: Pick<Booking, 'starts_at' | 'ends_at'>, day: string, timeZone: string): { start: number; end: number; continuesAfter: boolean; continuesBefore: boolean } | null {
  const starts = inZone(booking.starts_at, timeZone);
  const ends = inZone(booking.ends_at, timeZone);
  if (!starts || !ends) return null;
  const startsBefore = starts.day < day;
  const endsAfter = ends.day > day;
  if (starts.day > day || ends.day < day) return null;
  return { start: startsBefore ? 0 : starts.minutes, end: endsAfter ? DAY_MINUTES : ends.minutes, continuesAfter: endsAfter, continuesBefore: startsBefore };
}

// The hours the line shows: the default evening, stretched to whole hours around any booking outside it
export function axisFor(bookings: ReadonlyArray<Pick<Booking, 'starts_at' | 'ends_at'>>, day: string, timeZone: string): Axis {
  let start = DEFAULT_AXIS.start;
  let end = DEFAULT_AXIS.end;
  for (const booking of bookings) {
    const span = minutesOnDay(booking, day, timeZone);
    if (!span) continue;
    start = Math.min(start, Math.floor(span.start / 60) * 60);
    end = Math.max(end, Math.ceil(span.end / 60) * 60);
  }
  return { start, end };
}

export function place(booking: Pick<Booking, 'starts_at' | 'ends_at'>, day: string, timeZone: string, axis: Axis): Placed | null {
  const span = minutesOnDay(booking, day, timeZone);
  if (!span) return null;
  const length = axis.end - axis.start;
  const left = Math.max(0, ((span.start - axis.start) / length) * 100);
  const right = Math.min(100, ((span.end - axis.start) / length) * 100);
  // A booking never shrinks to nothing: a sliver is still a click target
  return { left, width: Math.max(right - left, 2), continuesAfter: span.continuesAfter, continuesBefore: span.continuesBefore };
}

// The hour marks of a line, every hour (every second hour on a long line)
export function hourMarks(axis: Axis): number[] {
  const hours = (axis.end - axis.start) / 60;
  const step = hours > 14 ? 2 : 1;
  const marks: number[] = [];
  for (let minute = axis.start; minute <= axis.end; minute += step * 60) marks.push(minute);
  return marks;
}

export const clock = (minutes: number): string => `${String(Math.floor(minutes / 60) % 24).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

// The bookings of a room by the store's day they start on
export function byDay<T extends Pick<Booking, 'starts_at'>>(bookings: readonly T[], timeZone: string): Map<string, T[]> {
  const days = new Map<string, T[]>();
  for (const booking of bookings) {
    const day = inZone(booking.starts_at, timeZone)?.day;
    if (!day) continue;
    days.set(day, [...(days.get(day) ?? []), booking]);
  }
  for (const list of days.values()) list.sort((a, b) => a.starts_at.localeCompare(b.starts_at));
  return days;
}

// Status -> colour pair of the design system. The word is always written beside it.
export type StatusTone = 'warning' | 'info' | 'success' | 'neutral' | 'danger';
export const STATUS_TONE: Record<string, StatusTone> = {
  requested: 'warning',
  confirmed: 'info',
  arrived: 'success',
  completed: 'neutral',
  canceled: 'danger',
  no_show: 'danger',
};

// A booking that still holds its room: those can be edited, confirmed or canceled
export const isOpenBooking = (status: string): boolean => status === 'requested' || status === 'confirmed';
