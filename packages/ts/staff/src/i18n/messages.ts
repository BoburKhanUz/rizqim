import type { OrderStatus, OrderType, CancelReason } from '../types';
import { fillParams, localize, type Language, type Localized } from './localize';

// Words of the staff screens in uz and ru (en only where an app already shows English: statuses, payment methods and
// cancel reasons). Screens read them with staffText() / the label functions; an app with its own translation store
// (the Mini App) can build its entries from these tables, so a wording changes in one place.

// --- Order statuses -------------------------------------------------------------------------------------------------

// What the staff reads ("Yangi" rather than the customer's "Kutilmoqda"). on_the_way and delivery are two names of one stage.
export const STAFF_STATUS_LABELS: Record<OrderStatus, Required<Localized>> = {
  new: { uz: 'Yangi', ru: 'Новый', en: 'New' },
  accepted: { uz: 'Qabul qilindi', ru: 'Принят', en: 'Accepted' },
  preparing: { uz: 'Tayyorlanmoqda', ru: 'Готовится', en: 'Preparing' },
  ready: { uz: 'Tayyor', ru: 'Готов', en: 'Ready' },
  courier_assigned: { uz: 'Kuryer kutilmoqda', ru: 'Ожидание курьера', en: 'Waiting for courier' },
  on_the_way: { uz: "Yo'lda", ru: 'В пути', en: 'On the way' },
  delivery: { uz: "Yo'lda", ru: 'В пути', en: 'On the way' },
  delivered: { uz: 'Yetkazildi', ru: 'Доставлен', en: 'Delivered' },
  canceled: { uz: 'Bekor qilindi', ru: 'Отменен', en: 'Cancelled' },
  rejected: { uz: 'Rad etildi', ru: 'Отклонен', en: 'Rejected' },
  refunded: { uz: 'Qaytarildi', ru: 'Возвращен', en: 'Refunded' },
};

// The staff label of `status`; a status the package does not know is shown as the API sent it.
export function statusLabel(status: string, language: Language | string): string {
  return Object.hasOwn(STAFF_STATUS_LABELS, status) ? localize(STAFF_STATUS_LABELS[status as OrderStatus], language) : status;
}

// --- Order types and payment methods --------------------------------------------------------------------------------

export const ORDER_TYPE_LABELS: Record<OrderType, Required<Localized>> = {
  delivery: { uz: 'Yetkazish', ru: 'Доставка', en: 'Delivery' },
  pickup: { uz: 'Olib ketish', ru: 'Самовывоз', en: 'Pickup' },
  dine_in: { uz: 'Zalda', ru: 'В зале', en: 'Dine-in' },
};

export function orderTypeLabel(type: string | null | undefined, language: Language | string): string {
  if (!type) return '';
  return Object.hasOwn(ORDER_TYPE_LABELS, type) ? localize(ORDER_TYPE_LABELS[type as OrderType], language) : type;
}

// The methods the API sends in Order.payment_method ("cash", "CASH", "click" ...)
export const PAYMENT_METHODS = ['cash', 'card', 'click', 'payme', 'uzum', 'paynet', 'keshbek'] as const;
export type PaymentMethodId = (typeof PAYMENT_METHODS)[number];

export const PAYMENT_METHOD_LABELS: Record<PaymentMethodId, Required<Localized>> = {
  cash: { uz: 'Naqd pul', ru: 'Наличные', en: 'Cash' },
  card: { uz: 'Plastik karta', ru: 'Карта', en: 'Card' },
  click: { uz: 'Click', ru: 'Click', en: 'Click' },
  payme: { uz: 'Payme', ru: 'Payme', en: 'Payme' },
  uzum: { uz: 'Uzum', ru: 'Uzum', en: 'Uzum' },
  paynet: { uz: 'Paynet', ru: 'Paynet', en: 'Paynet' },
  keshbek: { uz: 'Keshbek', ru: 'Кешбэк', en: 'Cashback' },
};

const PAYMENT_ALIASES: Record<string, PaymentMethodId> = { naqd: 'cash', terminal: 'card' };

// The id of a payment method as the API may spell it, or null for one this package has no name for.
export function normalizePaymentMethod(method: string | null | undefined): PaymentMethodId | null {
  const value = method?.trim().toLowerCase();
  if (!value) return null;
  if ((PAYMENT_METHODS as readonly string[]).includes(value)) return value as PaymentMethodId;
  return PAYMENT_ALIASES[value] ?? null;
}

// The name of `method`; one this package does not know is shown as sent.
export function paymentMethodLabel(method: string | null | undefined, language: Language | string): string {
  const id = normalizePaymentMethod(method);
  return id ? localize(PAYMENT_METHOD_LABELS[id], language) : (method ?? '');
}

// --- Cancel reasons -------------------------------------------------------------------------------------------------

// A reason as the staff reads it (App\Enums\CancelReason)
export const CANCEL_REASON_LABELS: Record<CancelReason, Required<Localized>> = {
  customer_request: { uz: "Mijoz so'rovi", ru: 'По просьбе клиента', en: 'Customer request' },
  no_show: { uz: 'Mijoz kelmadi', ru: 'Клиент не пришёл', en: 'Customer did not show up' },
  out_of_stock: { uz: 'Mahsulot qolmagan', ru: 'Нет в наличии', en: 'Out of stock' },
  store_closed: { uz: "Do'kon yopiq", ru: 'Магазин закрыт', en: 'Store closed' },
  courier_unavailable: { uz: "Kuryer yo'q", ru: 'Нет курьера', en: 'No courier available' },
  payment_timeout: { uz: "To'lov muddati o'tdi", ru: 'Время оплаты истекло', en: 'Payment timed out' },
  other: { uz: 'Boshqa sabab', ru: 'Другая причина', en: 'Other' },
};

// A reason this package does not know is shown as the API sent it.
export function cancelReasonLabel(reason: string, language: Language | string): string {
  return Object.hasOwn(CANCEL_REASON_LABELS, reason) ? localize(CANCEL_REASON_LABELS[reason as CancelReason], language) : reason;
}

// --- Screen texts ---------------------------------------------------------------------------------------------------

// Texts of the order screens (manager, courier). {{name}} placeholders are filled by staffText's params.
export const STAFF_MESSAGES = {
  // Shared
  loading: { uz: 'Yuklanmoqda', ru: 'Загрузка' },
  orderWord: { uz: 'Buyurtma', ru: 'Заказ' },
  ordersSection: { uz: "Buyurtmalar bo'limi", ru: 'Раздел заказов' },
  tabActive: { uz: 'Faol ({{n}})', ru: 'Активные ({{n}})' },
  paidSuffix: { uz: " · To'langan", ru: ' · Оплачено' },
  paid: { uz: "To'langan", ru: 'Оплачено' },
  gpsLocation: { uz: 'GPS lokatsiya', ru: 'GPS локация' },
  truncatedOrders: {
    uz: "Faol buyurtmalar juda ko'p: faqat eng yangi 200 tasi ko'rsatilmoqda.",
    ru: 'Активных заказов очень много: показаны только 200 самых новых.',
  },
  statusUpdateFailed: { uz: 'Holatni yangilashda xatolik yuz berdi!', ru: 'Ошибка при обновлении статуса!' },

  // Manager
  managerSubtitle: { uz: 'Menejer boshqaruvi', ru: 'Панель менеджера' },
  filterAria: { uz: "Holat bo'yicha saralash", ru: 'Фильтр по статусу' },
  filterNew: { uz: 'Yangi', ru: 'Новые' },
  filterPreparing: { uz: 'Tayyorlash', ru: 'Готовка' },
  filterReady: { uz: 'Tayyor', ru: 'Готовы' },
  filterCourier: { uz: 'Kuryer', ru: 'Курьер' },
  // All finished orders (the partner web's board column is today's only: "Bugun yakunlangan")
  tabCompleted: { uz: 'Yakunlangan', ru: 'Завершённые' },
  noActiveOrdersTitle: { uz: "Hozircha faol buyurtmalar yo'q", ru: 'Активных заказов пока нет' },
  noActiveOrdersText: { uz: "Yangi buyurtma kelganda shu yerda paydo bo'ladi.", ru: 'Новые заказы появятся здесь.' },
  noOrdersInStatus: { uz: "Bu holatda buyurtma yo'q", ru: 'В этом статусе заказов нет' },
  showAll: { uz: "Hammasini ko'rsatish", ru: 'Показать все' },
  noCompletedOrders: { uz: "Hozircha yakunlangan buyurtmalar yo'q", ru: 'Завершенных заказов пока нет' },
  courierFallback: { uz: 'Kuryer', ru: 'Курьер' },
  itemsLabel: { uz: 'Tarkibi', ru: 'Состав' },
  assignCourierLabel: { uz: 'Kuryerni biriktirish', ru: 'Назначить курьера' },
  chooseCourier: { uz: 'Kuryerni tanlang', ru: 'Выбрать курьера' },
  assignedCourier: { uz: 'Biriktirilgan kuryer', ru: 'Назначенный курьер' },
  waitingForPickup: { uz: "Kuryer buyurtmani olib yo'lga chiqishini kutmoqda.", ru: 'Ожидание, пока курьер заберёт заказ.' },
  responsibleCourier: { uz: "Mas'ul kuryer", ru: 'Курьер' },
  cancelFailed: { uz: 'Bekor qilishda xatolik yuz berdi!', ru: 'Ошибка при отмене заказа!' },

  // Courier
  courierSubtitle: { uz: 'Kuryer kabineti', ru: 'Кабинет курьера' },
  currentMark: { uz: 'Hozirgi', ru: 'Текущий' },
  noPhone: { uz: 'Telefon kiritilmagan', ru: 'Телефон не указан' },
  call: { uz: "Qo'ng'iroq qilish", ru: 'Позвонить' },
  openOnMap: { uz: 'Xaritada ochish', ru: 'Открыть на карте' },
  itemsList: { uz: 'Mahsulotlar', ru: 'Содержимое' },
  cashDue: { uz: 'Naqd olinadi', ru: 'Оплата наличными' },
  todayEarnings: { uz: 'Bugungi daromad', ru: 'Сегодня заработано' },
  deliveredCount: { uz: 'Yetkazilganlar', ru: 'Доставлено' },
  piecesShort: { uz: 'ta', ru: 'шт' },
  tabHandedOver: { uz: 'Topshirilganlar ({{n}})', ru: 'Завершенные ({{n}})' },
  noAssignedTitle: { uz: 'Sizga hali buyurtma biriktirilmagan', ru: 'У вас пока нет активных заказов' },
  noAssignedText: { uz: "Menejer buyurtmani biriktirsa, shu yerda paydo bo'ladi.", ru: 'Когда менеджер назначит заказ, он появится здесь.' },
  noDeliveredToday: { uz: "Bugun hali yetkazib berilgan buyurtmalar yo'q", ru: 'Сегодня еще нет доставленных заказов' },
  completeFailed: { uz: 'Buyurtmani topshirishda xatolik yuz berdi!', ru: 'Ошибка при завершении заказа!' },
  genericFailed: { uz: 'Holatni yangilashda xatolik yuz berdi!', ru: 'Ошибка!' },

  // Stages of an order's life (time each one took)
  stageAccept: { uz: 'Qabul qilish', ru: 'Принятие' },
  stagePrepare: { uz: 'Tayyorlash', ru: 'Готовка' },
  stageReady: { uz: "Tayyor bo'lishi", ru: 'Готовность' },
  stageHandToCourier: { uz: 'Kuryerga topshirish', ru: 'Передача курьеру' },
  stageDelivery: { uz: 'Yetkazish', ru: 'Доставка' },
} as const satisfies Record<string, Localized>;

export type StaffMessageKey = keyof typeof STAFF_MESSAGES;

export function staffText(key: StaffMessageKey, language: Language | string, params?: Record<string, string | number>): string {
  return fillParams(localize(STAFF_MESSAGES[key], language), params);
}

// The words of a button (docs: the staff screens offer these, orders/status.ts decides which).
export const ACTION_LABELS: Record<string, Localized> = {
  accept: { uz: 'Qabul qilish', ru: 'Принять' },
  reject: { uz: 'Rad etish', ru: 'Отклонить' },
  start_preparing: { uz: 'Tayyorlashni boshlash', ru: 'Начать готовку' },
  mark_ready: { uz: "Tayyor bo'ldi", ru: 'Готово' },
  assign_courier: { uz: 'Kuryerni biriktirish', ru: 'Назначить курьера' },
  hand_over: { uz: 'Mijozga topshirish', ru: 'Выдать заказ' },
  pick_up: { uz: "Yo'lga chiqdi", ru: 'В пути' },
  confirm_delivery: { uz: 'Yetkazib berildi', ru: 'Доставлен' },
  cancel: { uz: 'Bekor qilish', ru: 'Отменить заказ' },
};

// What the courier's own screen calls the same steps: the same words, said by the one who does them (P2-33: one wording
// for the courier steps: biriktirish, yo'lga chiqish, yetkazib berish)
export const COURIER_ACTION_LABELS: Record<string, Localized> = {
  pick_up: { uz: "Yo'lga chiqdim", ru: 'В пути' },
  confirm_delivery: { uz: 'Yetkazib berdim', ru: 'Доставил' },
};

export function actionLabel(id: string, language: Language | string, role?: string): string {
  const text = (role === 'courier' ? COURIER_ACTION_LABELS[id] : undefined) ?? ACTION_LABELS[id];
  return text ? localize(text, language) : id;
}
