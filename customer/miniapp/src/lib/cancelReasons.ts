import { isAxiosError } from 'axios';
import { getApiErrorMessage, getApiFieldError } from './apiError';
import type { CancelReason, Order, OrderStatus } from '../types';

type Lang = 'uz' | 'ru' | 'en';

const isLang = (language: string): language is Lang => language === 'uz' || language === 'ru' || language === 'en';

// Reasons staff can give, in the order they are offered (App\Enums\CancelReason::staffCases). payment_timeout is the
// system's own and is never offered.
export const STAFF_CANCEL_REASONS: readonly CancelReason[] = [
  'customer_request',
  'no_show',
  'out_of_stock',
  'store_closed',
  'courier_unavailable',
  'other',
];

const STAFF_LABELS: Record<CancelReason, Record<Lang, string>> = {
  customer_request: { uz: "Mijoz so'rovi", ru: 'По просьбе клиента', en: 'Customer request' },
  no_show: { uz: 'Mijoz kelmadi', ru: 'Клиент не пришёл', en: 'Customer did not show up' },
  out_of_stock: { uz: 'Mahsulot qolmagan', ru: 'Нет в наличии', en: 'Out of stock' },
  store_closed: { uz: "Do'kon yopiq", ru: 'Магазин закрыт', en: 'Store closed' },
  courier_unavailable: { uz: "Kuryer yo'q", ru: 'Нет курьера', en: 'No courier available' },
  payment_timeout: { uz: "To'lov muddati o'tdi", ru: 'Время оплаты истекло', en: 'Payment timed out' },
  other: { uz: 'Boshqa sabab', ru: 'Другая причина', en: 'Other' },
};

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

export const getStaffCancelReasonLabel = (reason: string, language: string): string => lookup(STAFF_LABELS, reason, language);

export const getCustomerCancelReasonLabel = (reason: string, language: string): string => lookup(CUSTOMER_LABELS, reason, language);

const NO_SHOW_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready'];

// The reasons a member of staff may give for cancelling `order`, as the bot offers them (and the API accepts them):
// no_show only for a pickup order the store accepted, is preparing or has ready that the customer never came for, and
// courier_unavailable only for a delivery order.
export function getStaffCancelReasons(order: Pick<Order, 'status' | 'order_type'>): CancelReason[] {
  const canBeNoShow = order.order_type === 'pickup' && NO_SHOW_STATUSES.includes(order.status);
  const needsCourier = order.order_type === 'delivery';
  return STAFF_CANCEL_REASONS.filter(
    (reason) => (reason !== 'no_show' || canBeNoShow) && (reason !== 'courier_unavailable' || needsCourier),
  );
}

// Whether the customer may cancel the order themselves (Order::isCancellableByCustomer): while it is new and nothing
// was paid for it.
export function isCancellableByCustomer(order: Pick<Order, 'status' | 'payment_status'>): boolean {
  return order.status === 'new' && order.payment_status !== 'paid' && order.payment_status !== 'refunded';
}

export const CANCEL_NOTE_MAX_LENGTH = 500;

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
  if (isAxiosError(err) && err.response?.status === 429) return TOO_MANY_REQUESTS[lang];
  return getApiFieldError(err, 'reason') ?? getApiFieldError(err, 'note') ?? getApiErrorMessage(err) ?? GENERIC_FAILURE[lang];
}
