// Every amount, number, date and time the staff screens show (docs/DESIGN.md, "Formatlash"). Money and dates are
// @rizqim/ui's, the very functions the Mini App prints with today:
//   formatMoney(25000, 'uz')   "25 000 so'm"      formatDateTime(iso, 'uz')   "19-okt, 20:12"
//   formatQuantity(1.5, 'kg', 'uz')  "1,5 kg"
// The language is always passed in: this package has no language store (each app binds its own).
export {
  formatDate,
  formatDateTime,
  formatMoney,
  formatNumber,
  formatOrderCode,
  formatTime,
  NBSP,
  parseAmount,
  sumAmounts,
  toAmount,
} from '@rizqim/ui';
export type { Amount, Language } from '@rizqim/ui';
export { formatLineQuantity, formatQuantity, formatQuantityNumber, isMeasuredUnit, roundQuantity, unitLabel } from './quantity';
