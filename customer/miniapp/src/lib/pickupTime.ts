import { formatDate, formatTime } from './format';
import { translations, useLanguageStore, type Language } from '../store/languageStore';

// P6-34: a pickup time reads "Bugun, 18:30" / "Ertaga, 09:00" / "23-okt, 09:00": the day in words when it is close, so the
// customer sees at once that the order is for later today and not for another day.

const dayKey = (date: Date) => `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;

// The calendar day of an instant on the customer's phone, in words ("today", "tomorrow") or null for any other day
export function pickupDayWord(at: Date, language: Language, now: Date = new Date()): string | null {
  const words = translations[language];
  if (dayKey(at) === dayKey(now)) return words.pickupToday;
  const tomorrow = new Date(now);
  tomorrow.setDate(now.getDate() + 1);
  return dayKey(at) === dayKey(tomorrow) ? words.pickupTomorrow : null;
}

// The time of a slot: `time` is the store's clock (what the server offers), the day comes from the instant. Without `time`
// the phone's clock is used (an order card).
export function formatPickupTime(value: string | Date, language: Language = useLanguageStore.getState().language, time?: string, now: Date = new Date()): string {
  const at = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(at.getTime())) return '—';
  return `${pickupDayWord(at, language, now) ?? formatDate(at, language, now)}, ${time ?? formatTime(at)}`;
}
