import { formatDate, type Language } from '@rizqim/ui';
import { isMessageKey, type Translate } from '@/i18n';
import type { ReportGroupBy } from '@rizqim/staff';

// Display helpers of the reports and finance screens. Nothing here changes a number: the server's figures go through
// the shared formatters (@rizqim/ui, via @rizqim/staff) exactly as they arrived.

// A "YYYY-MM-DD" day as a Date at local midnight. new Date("2026-10-21") would be UTC midnight, which is the previous
// evening in a browser west of Greenwich and would print the wrong day.
export function localDate(day: string): Date {
  const [year = 1970, month = 1, date = 1] = day.split('-').map(Number);
  return new Date(year, month - 1, date);
}

// "21-okt" (the year too when it is not this year), from a day of the store's calendar
export const formatDay = (day: string, language: Language): string => formatDate(localDate(day), language);

const UZ_MONTHS = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avg', 'sen', 'okt', 'noy', 'dek'];

// "okt 2026" for a month bucket
export function formatMonth(day: string, language: Language): string {
  const date = localDate(day);
  if (language === 'uz') return `${UZ_MONTHS[date.getMonth()]} ${date.getFullYear()}`;
  return new Intl.DateTimeFormat(language === 'ru' ? 'ru-RU' : 'en-US', { month: 'short', year: 'numeric' }).format(date);
}

// The label of a bucket of the sales series: a day, the Monday of a week, a month
export function formatBucket(day: string, groupBy: ReportGroupBy, language: Language, t: Translate): string {
  if (groupBy === 'month') return formatMonth(day, language);
  if (groupBy === 'week') return t('report_weekOf', { date: formatDay(day, language) });
  return formatDay(day, language);
}

// t(`${prefix}${value}`) for a value the server names ("fin_t_" + "commission"); a value without a text is shown as sent
export function labelFor(t: Translate, prefix: string, value: string | null | undefined): string {
  if (!value) return '—';
  const key = `${prefix}${value}`;
  return isMessageKey(key) ? t(key) : value;
}
