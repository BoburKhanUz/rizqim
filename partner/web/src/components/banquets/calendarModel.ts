import { addDays } from '@/lib/storeTime';
import type { CalendarHall, EventSummary, Slot, SlotTimes } from './api';

// The banquet calendar's arithmetic, kept pure for the tests: the month and the week around a day, the days of a range,
// each hall's events of a day, and whether a named slot of a hall is still free (the server decides for real: 409
// slot_taken; this only greys out the "+" of a taken slot). Times are the store's "HH:MM"; an end at or before the
// start is the next day.

export type Mode = 'month' | 'week';

export function monthOf(day: string): { from: string; to: string } {
  const [year = 1970, month = 1] = day.split('-').map(Number);
  const first = `${year}-${String(month).padStart(2, '0')}-01`;
  const nextFirst = month === 12 ? `${year + 1}-01-01` : `${year}-${String(month + 1).padStart(2, '0')}-01`;
  return { from: first, to: addDays(nextFirst, -1) };
}

export function weekOf(day: string): { from: string; to: string } {
  const date = new Date(`${day}T00:00:00Z`);
  const iso = date.getUTCDay() === 0 ? 7 : date.getUTCDay();
  const from = addDays(day, 1 - iso);
  return { from, to: addDays(from, 6) };
}

export const rangeOf = (mode: Mode, day: string) => (mode === 'month' ? monthOf(day) : weekOf(day));

// The first day of the previous or next month / week
export function stepFrom(mode: Mode, day: string, direction: 1 | -1): string {
  if (mode === 'week') return addDays(weekOf(day).from, 7 * direction);
  const { from, to } = monthOf(day);
  return direction === 1 ? addDays(to, 1) : monthOf(addDays(from, -1)).from;
}

export function daysOf(from: string, to: string): string[] {
  const days: string[] = [];
  for (let day = from; day <= to && days.length < 62; day = addDays(day, 1)) days.push(day);
  return days;
}

export const eventsOn = (hall: CalendarHall, day: string): EventSummary[] => hall.events.filter((event) => event.event_date === day).sort((a, b) => a.starts_at.localeCompare(b.starts_at));

const minutes = (time: string): number => {
  const [h = 0, m = 0] = time.split(':').map(Number);
  return h * 60 + m;
};

// [start, end) in minutes from the day's midnight
export function span(start: string, end: string): [number, number] {
  const from = minutes(start);
  let to = minutes(end);
  if (to <= from) to += 24 * 60;
  return [from, to];
}

export const overlaps = (a: [number, number], b: [number, number]): boolean => a[0] < b[1] && b[0] < a[1];

// A named slot of the hall on the day is free when no event that holds its time overlaps it
export function slotFree(hall: CalendarHall, day: string, slot: Exclude<Slot, 'custom'>, slots: SlotTimes): boolean {
  const wanted = span(slots[slot].start, slots[slot].end);
  return !eventsOn(hall, day).some((event) => event.status !== 'cancelled' && overlaps(wanted, span(event.start_time, event.end_time)));
}
