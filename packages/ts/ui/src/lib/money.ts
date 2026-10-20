import type { Amount } from './types';

// Money comes from the API as decimal strings ("15000.00") and from the app's own arithmetic as numbers. `+` on
// two strings concatenates, and a stray NaN ends up on screen as "NaN so'm": every amount goes through here first.

// The finite number in `value`, or null. Accepts a number or a numeric string; a blank string, NaN, Infinity and
// anything else that is not a number is null.
export function parseAmount(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

// `value` as a number for arithmetic and display. A missing or non-numeric value counts as 0, so one bad field
// does not turn a whole sum or screen into NaN.
export function toAmount(value: Amount | null | undefined): number {
  return parseAmount(value) ?? 0;
}

// The sum of amounts, exact to the tiyin (two decimals): 0.1 + 0.2 is 0.3, not 0.30000000000000004.
export function sumAmounts(values: Iterable<Amount | null | undefined>): number {
  let sum = 0;
  for (const value of values) sum += toAmount(value);
  return Math.round(sum * 100) / 100;
}
