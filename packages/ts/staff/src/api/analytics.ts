import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// The timeframes of the analytics and reports screens, and how they are asked of GET /owner/analytics. The figures
// themselves are computed by the server; nothing here counts orders.
export type Timeframe = 'today' | 'weekly' | 'monthly' | 'yearly' | 'custom';

export type AnalyticsPeriod = 'today' | 'week' | 'month' | 'year' | 'custom';

// Inclusive calendar days ("YYYY-MM-DD", as <input type="date"> gives them) of the 'custom' timeframe.
export interface DateRange {
  start: string;
  end: string;
}

// The longest custom period the server serves (config orders.reports.max_custom_days), both ends included.
export const MAX_CUSTOM_DAYS = 93;

const PERIOD_OF: Record<Timeframe, AnalyticsPeriod> = {
  today: 'today',
  weekly: 'week',
  monthly: 'month',
  yearly: 'year',
  custom: 'custom',
};

export const toAnalyticsPeriod = (timeframe: Timeframe): AnalyticsPeriod => PERIOD_OF[timeframe];

const DAY_MS = 24 * 60 * 60 * 1000;
const DATE_FORMAT = /^\d{4}-\d{2}-\d{2}$/;

// Days from the first to the last date, both counted; NaN for something that is not a date.
function inclusiveDays(range: DateRange): number {
  if (!DATE_FORMAT.test(range.start) || !DATE_FORMAT.test(range.end)) return Number.NaN;
  return (Date.parse(`${range.end}T00:00:00Z`) - Date.parse(`${range.start}T00:00:00Z`)) / DAY_MS + 1;
}

// Why a custom range cannot be asked of the server, or null when it can. The screens say so instead of sending it.
export type CustomRangeProblem = 'missing' | 'reversed' | 'too_long';

export function getCustomRangeProblem(range: DateRange | undefined): CustomRangeProblem | null {
  if (!range) return 'missing';
  const days = inclusiveDays(range);
  if (Number.isNaN(days)) return 'missing';
  if (days < 1) return 'reversed';
  return days > MAX_CUSTOM_DAYS ? 'too_long' : null;
}

// The date an <input type="date"> takes for a moment: the local calendar day (toISOString() would give the UTC one,
// the day before for the first hours of a day east of Greenwich).
export function toDateInputValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

// GET /owner/analytics: server-side aggregates of the store's orders (a branch manager's branch) created in the period
// (Asia/Tashkent calendar days). "revenue" is the total of the delivered orders.
export type OwnerAnalytics = OkData<'ownerAnalytics.index'>;

export async function fetchOwnerAnalytics(api: StaffApi, timeframe: Timeframe, range?: DateRange): Promise<OwnerAnalytics> {
  const query = {
    period: toAnalyticsPeriod(timeframe),
    ...(timeframe === 'custom' && range ? { from: range.start, to: range.end } : {}),
  };
  const body = unwrap(await api.client.GET('/v1/owner/analytics', { params: { query } }));
  return body.data;
}

export type ReportExportInput = JsonRequest<'order.exportReport'>;

// POST /reports/export: the server sends the report to the person's Telegram. Answers the server's own confirmation text.
export async function exportReport(api: StaffApi, input: ReportExportInput): Promise<string | undefined> {
  const body = unwrap(await api.client.POST('/v1/reports/export', { body: input }));
  return body.message || undefined;
}
