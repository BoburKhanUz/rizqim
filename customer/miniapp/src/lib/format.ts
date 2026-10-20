import type { Amount } from '../types';
import { useLanguageStore, type Language } from '../store/languageStore';
import { toAmount } from './money';

// Every amount, number, date and time the app shows goes through here (docs/DESIGN.md, "Formatlash").
//
//   formatMoney(25000)          uz "25 000 so'm"   ru "25 000 сум"   en "25,000 UZS"
//   formatNumber(1234567)       uz/ru "1 234 567"  en "1,234,567"
//   formatDate(iso)             uz "19-okt"        ru "19 окт."      en "Oct 19"     (+ the year when not this year)
//   formatTime(iso)             "20:12" (24 h, every language)
//   formatDateTime(iso)         uz "19-okt, 20:12" ru "19 окт., 20:12" en "Oct 19, 20:12"
//
// Spaces inside a value are non-breaking, so "25 000 so'm" never wraps in the middle. Amounts are whole so'm: the
// tiyin are rounded away. The language defaults to the one the user picked (languageStore).
//
// Uzbek month names are written out here: Chromium's Intl has no Uzbek month abbreviations and prints "M10".

export const NBSP = ' ';

const currentLanguage = (): Language => useLanguageStore.getState().language;

const CURRENCY: Record<Language, string> = { uz: "so'm", ru: 'сум', en: 'UZS' };

function groupDigits(value: number, language: Language): string {
  const separator = language === 'en' ? ',' : NBSP;
  return Math.abs(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, separator);
}

// A whole number with thousands separators ("1 234 567"). A missing or non-numeric value is 0.
export function formatNumber(value: Amount | null | undefined, language: Language = currentLanguage()): string {
  const rounded = Math.round(toAmount(value));
  return `${rounded < 0 ? '-' : ''}${groupDigits(rounded, language)}`;
}

// An amount of so'm: "25 000 so'm" / "25 000 сум" / "25,000 UZS". A missing or non-numeric value is shown as 0.
export function formatMoney(value: Amount | null | undefined, language: Language = currentLanguage()): string {
  return `${formatNumber(value, language)}${NBSP}${CURRENCY[language]}`;
}

const UZ_MONTHS = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avg', 'sen', 'okt', 'noy', 'dek'];

const INTL_LOCALE: Record<Exclude<Language, 'uz'>, string> = { ru: 'ru-RU', en: 'en-US' };

type DateInput = string | number | Date | null | undefined;

// The Date of `value`, or null for nothing / an unparseable string.
function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === '') return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

const pad2 = (n: number) => String(n).padStart(2, '0');

const MISSING = '—';

// "20:12", 24-hour, the device's time zone.
export function formatTime(value: DateInput): string {
  const date = toDate(value);
  if (!date) return MISSING;
  return `${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

// "19-okt" / "19 окт." / "Oct 19"; a date of another year also shows the year ("19-okt 2025").
export function formatDate(value: DateInput, language: Language = currentLanguage(), now: Date = new Date()): string {
  const date = toDate(value);
  if (!date) return MISSING;
  const withYear = date.getFullYear() !== now.getFullYear();
  if (language === 'uz') {
    const day = `${date.getDate()}-${UZ_MONTHS[date.getMonth()]}`;
    return withYear ? `${day}${NBSP}${date.getFullYear()}` : day;
  }
  const text = new Intl.DateTimeFormat(INTL_LOCALE[language], {
    day: 'numeric',
    month: 'short',
    ...(withYear ? { year: 'numeric' } : {}),
  }).format(date);
  return text.replace(/ /g, NBSP);
}

// "19-okt, 20:12" / "19 окт., 20:12" / "Oct 19, 20:12".
export function formatDateTime(value: DateInput, language: Language = currentLanguage(), now: Date = new Date()): string {
  const date = toDate(value);
  if (!date) return MISSING;
  return `${formatDate(date, language, now)}, ${formatTime(date)}`;
}

// The number an order is known by: its short code ("#E101"), else the start of its uuid. Never both, never empty
// when either exists.
export function formatOrderCode(order: { short_code?: string | null; uuid?: string | null }): string {
  const code = order.short_code?.trim();
  if (code) return `#${code}`;
  return order.uuid ? `#${order.uuid.slice(0, 8).toUpperCase()}` : '';
}
