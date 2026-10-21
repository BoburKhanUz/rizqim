import { cancelReasonLabel, CANCEL_NOTE_MAX_LENGTH, getStaffCancelReasons, STAFF_CANCEL_REASONS } from '@rizqim/staff';
import { getApiErrorMessage, getApiErrorStatus, getApiFieldError } from './apiError';
import type { CancelReason, Order } from '../types';

type Lang = 'uz' | 'ru' | 'en';

const isLang = (language: string): language is Lang => language === 'uz' || language === 'ru' || language === 'en';

// The staff's side (the reasons they may give, their words, which ones fit an order) is @rizqim/staff's, shared with the
// partner web; the customer's words stay here.
export { CANCEL_NOTE_MAX_LENGTH, getStaffCancelReasons, STAFF_CANCEL_REASONS };

// The reason as the customer reads it (App\Enums\CancelReason::customerLabel)
const CUSTOMER_LABELS: Record<CancelReason, Record<Lang, string>> = {
  customer_request: { uz: "Sizning so'rovingizga ko'ra", ru: 'По вашей просьбе', en: 'At your request' },
  no_show: { uz: 'Buyurtma olib ketilmadi', ru: 'Заказ не был забран', en: 'The order was not picked up' },
  out_of_stock: { uz: 'Mahsulot qolmagan', ru: 'Нет в наличии', en: 'Out of stock' },
  store_closed: { uz: "Do'kon hozir ishlamayapti", ru: 'Магазин сейчас не работает', en: 'The store is closed right now' },
  courier_unavailable: { uz: "Bo'sh kuryer topilmadi", ru: 'Свободный курьер не найден', en: 'No free courier was found' },
  payment_timeout: {
    uz: "To'lov belgilangan vaqtda amalga oshirilmadi",
    ru: 'Оплата не поступила в установленное время',
    en: 'Payment was not made in time',
  },
  other: { uz: 'Boshqa sabab', ru: 'Другая причина', en: 'Other reason' },
};

const lookup = (labels: Record<CancelReason, Record<Lang, string>>, reason: string, language: string): string => {
  if (!Object.hasOwn(labels, reason)) return reason; // a reason this app does not know: shown as the API sent it
  return labels[reason as CancelReason][isLang(language) ? language : 'uz'];
};

export const getStaffCancelReasonLabel = (reason: string, language: string): string => cancelReasonLabel(reason, language);

export const getCustomerCancelReasonLabel = (reason: string, language: string): string => lookup(CUSTOMER_LABELS, reason, language);

// Whether the customer may cancel the order themselves (Order::isCancellableByCustomer): while it is new and nothing
// was paid for it.
export function isCancellableByCustomer(order: Pick<Order, 'status' | 'payment_status'>): boolean {
  return order.status === 'new' && order.payment_status !== 'paid' && order.payment_status !== 'refunded';
}

const TOO_MANY_REQUESTS: Record<Lang, string> = {
  uz: "Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.",
  ru: 'Слишком много попыток. Повторите чуть позже.',
  en: 'Too many attempts. Please try again in a moment.',
};

const GENERIC_FAILURE: Record<Lang, string> = {
  uz: "Buyurtmani bekor qilib bo'lmadi. Qayta urinib ko'ring.",
  ru: 'Не удалось отменить заказ. Попробуйте ещё раз.',
  en: 'Could not cancel the order. Please try again.',
};

// What to tell the person who tried to cancel an order and failed: the server's own (Uzbek) message for a 422 (the
// order is no longer cancellable, a field is invalid), a plain "slow down" for the 5/min throttle.
export function getCancelErrorMessage(err: unknown, language: string): string {
  const lang = isLang(language) ? language : 'uz';
  if (getApiErrorStatus(err) === 429) return TOO_MANY_REQUESTS[lang];
  return getApiFieldError(err, 'reason') ?? getApiFieldError(err, 'note') ?? getApiErrorMessage(err) ?? GENERIC_FAILURE[lang];
}
