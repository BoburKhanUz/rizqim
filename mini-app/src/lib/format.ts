import type { Amount } from '../types';
import { toAmount } from './money';

// "12000.5" -> "12 001 so'm". A missing or non-numeric value is shown as zero.
export function formatUzbekSum(value: Amount | null | undefined): string {
  const numValue = Math.round(toAmount(value));
  return numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + " so'm";
}
