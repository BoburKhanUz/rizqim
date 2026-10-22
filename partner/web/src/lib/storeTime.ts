// Times in the store's own time zone. The API sends instants in UTC (ISO 8601) and reads a time without a zone as the store's
// local time; the booking calendar draws days of the store, whatever time zone the browser is in.

const formatters = new Map<string, Intl.DateTimeFormat>();

function formatterFor(timeZone: string): Intl.DateTimeFormat {
  let formatter = formatters.get(timeZone);
  if (!formatter) {
    formatter = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    formatters.set(timeZone, formatter);
  }
  return formatter;
}

export interface ZonedTime {
  // YYYY-MM-DD
  day: string;
  // HH:MM
  time: string;
  // Minutes since the store's midnight
  minutes: number;
}

// An instant as the date and the time on the wall of the store
export function inZone(iso: string | Date, timeZone: string): ZonedTime | null {
  const date = iso instanceof Date ? iso : new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  let parts: Record<string, string>;
  try {
    parts = Object.fromEntries(formatterFor(timeZone).formatToParts(date).map((part) => [part.type, part.value]));
  } catch {
    // An unknown time zone name: the browser's own
    parts = Object.fromEntries(formatterFor(Intl.DateTimeFormat().resolvedOptions().timeZone).formatToParts(date).map((part) => [part.type, part.value]));
  }
  const hour = Number(parts.hour) % 24;
  const minute = Number(parts.minute);
  return { day: `${parts.year}-${parts.month}-${parts.day}`, time: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`, minutes: hour * 60 + minute };
}

// "YYYY-MM-DD" and "HH:MM" as the zone-less local time the API reads in the store's time zone
export const localDateTime = (day: string, time: string): string => `${day}T${time}:00`;

// "2026-10-22" + 1 day -> "2026-10-23" (calendar arithmetic on the date itself, no time zones involved)
export function addDays(day: string, days: number): string {
  const [year = 1970, month = 1, date = 1] = day.split('-').map(Number);
  const moved = new Date(Date.UTC(year, month - 1, date + days));
  return moved.toISOString().slice(0, 10);
}

// ISO weekday of a day: 1 Monday ... 7 Sunday
export function isoWeekday(day: string): number {
  const [year = 1970, month = 1, date = 1] = day.split('-').map(Number);
  const weekday = new Date(Date.UTC(year, month - 1, date)).getUTCDay();
  return weekday === 0 ? 7 : weekday;
}

// The Monday of the week a day is in
export const mondayOf = (day: string): string => addDays(day, 1 - isoWeekday(day));

// Today in the store's time zone
export const todayIn = (timeZone: string, now: Date = new Date()): string => inZone(now, timeZone)?.day ?? now.toISOString().slice(0, 10);
