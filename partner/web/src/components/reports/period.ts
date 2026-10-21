// The period logic of the reports screen, on calendar days ("YYYY-MM-DD") of the STORE's timezone. The server says what
// "today" is for the store (GET /owner/analytics?period=today -> period.from), so the presets below are plain date
// arithmetic from that day; the browser's own clock and timezone are never used once the store's day is known.

export type PeriodPreset = 'today' | 'yesterday' | 'last7' | 'last30' | 'thisMonth' | 'custom';

export const PERIOD_PRESETS: readonly PeriodPreset[] = ['today', 'yesterday', 'last7', 'last30', 'thisMonth', 'custom'];

export interface DayRange {
  from: string;
  to: string;
}

// The longest period GET /owner/reports/sales, /top-products and the sales/products CSV accept, both days counted
// (SalesReportService::MAX_RANGE_DAYS). The orders CSV is shorter; the server says so (422) when it is asked too much.
export const MAX_REPORT_DAYS = 366;

const DAY = /^(\d{4})-(\d{2})-(\d{2})$/;
const DAY_MS = 24 * 60 * 60 * 1000;

const utc = (day: string): number | null => {
  const match = DAY.exec(day);
  if (!match) return null;
  const [, year, month, date] = match.map(Number) as [number, number, number, number];
  const time = Date.UTC(year, month - 1, date);
  const check = new Date(time);
  // 2026-02-31 is not a day (Date would roll it into March)
  return check.getUTCFullYear() === year && check.getUTCMonth() === month - 1 && check.getUTCDate() === date ? time : null;
};

export const isDay = (value: string): boolean => utc(value) !== null;

const text = (time: number) => new Date(time).toISOString().slice(0, 10);

// The day `count` days after (before, when negative) `day`.
export function addDays(day: string, count: number): string {
  const time = utc(day);
  if (time === null) throw new RangeError(`Not a day: ${day}`);
  return text(time + count * DAY_MS);
}

// The days from `range.from` to `range.to`, both counted; NaN when either is not a day.
export function daysInRange(range: DayRange): number {
  const from = utc(range.from);
  const to = utc(range.to);
  return from === null || to === null ? Number.NaN : Math.round((to - from) / DAY_MS) + 1;
}

// The first day of the month `day` is in.
export const startOfMonth = (day: string): string => `${day.slice(0, 7)}-01`;

// The days of a preset, counted back from the store's today. "last7" is today and the 6 days before it. `custom` is what
// the person typed; null until both days are there.
export function resolvePeriod(preset: PeriodPreset, today: string, custom?: Partial<DayRange>): DayRange | null {
  switch (preset) {
    case 'today':
      return { from: today, to: today };
    case 'yesterday': {
      const day = addDays(today, -1);
      return { from: day, to: day };
    }
    case 'last7':
      return { from: addDays(today, -6), to: today };
    case 'last30':
      return { from: addDays(today, -29), to: today };
    case 'thisMonth':
      return { from: startOfMonth(today), to: today };
    case 'custom':
      return custom?.from && custom.to ? { from: custom.from, to: custom.to } : null;
  }
}

// Why a period cannot be asked of the server, or null when it can. The screen says so instead of sending it.
export type PeriodProblem = 'missing' | 'reversed' | 'too_long';

export function getPeriodProblem(range: DayRange | null): PeriodProblem | null {
  if (!range || !isDay(range.from) || !isDay(range.to)) return 'missing';
  const days = daysInRange(range);
  if (days < 1) return 'reversed';
  return days > MAX_REPORT_DAYS ? 'too_long' : null;
}

// The calendar day it is now in `timeZone` ("Asia/Tashkent"): the fallback while the server's own "today" is not known
// (the analytics request failed). An unknown zone falls back to the default zone of the backend.
export const DEFAULT_TIMEZONE = 'Asia/Tashkent';

export function todayIn(timeZone: string, now: Date = new Date()): string {
  const format = (zone: string) => new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  try {
    return format(timeZone);
  } catch {
    return format(DEFAULT_TIMEZONE);
  }
}
