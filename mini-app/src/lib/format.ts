import type { Decimal } from '../types';

// "12000.5" -> "12 001 so'm". A missing value is shown as zero.
export function formatUzbekSum(value: Decimal | null | undefined): string {
  if (value === undefined || value === null) return "0 so'm";
  const numValue = Math.round(Number(value));
  return numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + " so'm";
}
