import { useLanguageStore, type Language } from '../store/languageStore';
import type { Amount } from '../types';
import {
  formatDate as formatDateIn,
  formatDateTime as formatDateTimeIn,
  formatMoney as formatMoneyIn,
  formatNumber as formatNumberIn,
} from '@rizqim/ui';

// Every amount, number, date and time the app shows goes through here (docs/DESIGN.md, "Formatlash").
//
//   formatMoney(25000)          uz "25 000 so'm"   ru "25 000 сум"   en "25,000 UZS"
//   formatNumber(1234567)       uz/ru "1 234 567"  en "1,234,567"
//   formatDate(iso)             uz "19-okt"        ru "19 окт."      en "Oct 19"     (+ the year when not this year)
//   formatTime(iso)             "20:12" (24 h, every language)
//   formatDateTime(iso)         uz "19-okt, 20:12" ru "19 окт., 20:12" en "Oct 19, 20:12"
//
// The implementation is @rizqim/ui's (shared with the partner web); this file only defaults the language to the one the
// user picked (languageStore). Spaces inside a value are non-breaking, so "25 000 so'm" never wraps in the middle.
// Amounts are whole so'm: the tiyin are rounded away.

export { formatOrderCode, formatTime, NBSP } from '@rizqim/ui';

const currentLanguage = (): Language => useLanguageStore.getState().language;

type DateInput = string | number | Date | null | undefined;

// A whole number with thousands separators ("1 234 567"). A missing or non-numeric value is 0.
export const formatNumber = (value: Amount | null | undefined, language: Language = currentLanguage()): string => formatNumberIn(value, language);

// An amount of so'm: "25 000 so'm" / "25 000 сум" / "25,000 UZS". A missing or non-numeric value is shown as 0.
export const formatMoney = (value: Amount | null | undefined, language: Language = currentLanguage()): string => formatMoneyIn(value, language);

// "19-okt" / "19 окт." / "Oct 19"; a date of another year also shows the year ("19-okt 2025").
export const formatDate = (value: DateInput, language: Language = currentLanguage(), now: Date = new Date()): string => formatDateIn(value, language, now);

// "19-okt, 20:12" / "19 окт., 20:12" / "Oct 19, 20:12".
export const formatDateTime = (value: DateInput, language: Language = currentLanguage(), now: Date = new Date()): string => formatDateTimeIn(value, language, now);
