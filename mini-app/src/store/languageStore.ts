import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { tg } from '../lib/telegram';

export type Language = 'uz' | 'ru' | 'en';

export const isLanguage = (value: string): value is Language => value === 'uz' || value === 'ru' || value === 'en';

const translations = {
  uz: {
    // Menu & general
    catalog: 'Katalog',
    cart: 'Savat',
    checkout: 'Rasmiylashtirish',
    myOrders: 'Buyurtmalarim',
    total: 'Jami',
    subtotal: 'Nimjami',
    deliveryFee: 'Yetkazish to\'lovi',
    orderType: 'Buyurtma turi',
    delivery: 'Yetkazib berish',
    pickup: 'O\'zi olib ketish',
    deliveryAddress: 'Yetkazish manzili',
    enterAddress: 'To\'liq manzilingizni kiriting...',
    paymentMethod: 'To\'lov usuli',
    cash: '💵 Kuryerga naqd pul',
    cashDesc: 'Yetkazib berilganda naqd pulda to\'lash',
    click: '🔵 Click orqali to\'lash',
    clickDesc: 'Click kartasi orqali lahzali to\'lov',
    payme: '🟢 Payme orqali to\'lash',
    paymeDesc: 'Payme ilovasi orqali to\'lov',
    notes: 'Qo\'shimcha eslatmalar',
    notesPlaceholder: 'Masalan: Eshik qo\'ng\'irog\'ini chalmang',
    payButton: 'To\'lash: {{total}} so\'m',
    processing: 'Jarayonda...',
    addToCart: 'Savatga qo\'shish',
    outOfStock: 'Tugagan',
    quantity: 'Soni',
    emptyCart: 'Savat bo\'sh 🍃',
    emptyCartDesc: 'Savatga tovar qo\'shish uchun katalogga qayting',
    goBack: 'Orqaga qaytish',
    successTitle: 'Muvaffaqiyatli qabul qilindi! 🎉',
    successDesc: 'Sizning buyurtmangiz tayyorlashga yuborildi. Status o\'zgarishi haqida xabar beramiz.',
    orderId: 'Buyurtma ID',
    status: 'Holat',
    activeBadge: 'Faol',
    deliveryFeePaidToCourier: 'Kuryerga to\'lanadi 🛵',
    // Statuses
    status_new: 'Kutilmoqda',
    status_accepted: 'Qabul qilindi',
    status_preparing: 'Tayyorlanmoqda',
    status_ready: 'Tayyor',
    status_courier_assigned: 'Kuryer biriktirildi',
    status_on_the_way: 'Yo\'lda',
    status_delivery: 'Yo\'lda', // the legacy name of on_the_way
    status_delivered: 'Yetkazildi',
    status_canceled: 'Bekor qilindi',
    status_rejected: 'Rad etildi',
    status_refunded: 'Qaytarildi',
    // Payment statuses
    paymentStatus_pending: 'Kutilmoqda',
    paymentStatus_paid: 'To\'langan',
    paymentStatus_failed: 'Muvaffaqiyatsiz',
    paymentStatus_cancelled: 'Bekor qilindi',
    paymentStatus_expired: 'Muddati tugagan',
    paymentStatus_refunded: 'Qaytarildi',
  },
  ru: {
    catalog: 'Каталог',
    cart: 'Корзина',
    checkout: 'Оформление',
    myOrders: 'Мои заказы',
    total: 'Итого',
    subtotal: 'Подитог',
    deliveryFee: 'Доставка',
    orderType: 'Тип заказа',
    delivery: 'Доставка',
    pickup: 'Самовывоз',
    deliveryAddress: 'Адрес доставки',
    enterAddress: 'Введите полный адрес доставки...',
    paymentMethod: 'Способ оплаты',
    cash: '💵 Наличными курьеру',
    cashDesc: 'Оплата наличными при получении',
    click: '🔵 Оплата через Click',
    clickDesc: 'Моментальная оплата картой Click',
    payme: '🟢 Оплата через Payme',
    paymeDesc: 'Оплата через приложение Payme',
    notes: 'Дополнительные примечания',
    notesPlaceholder: 'Например: Не звонить в звонок',
    payButton: 'Оплатить: {{total}} сум',
    processing: 'Обработка...',
    addToCart: 'В корзину',
    outOfStock: 'Нет в наличии',
    quantity: 'Количество',
    emptyCart: 'Корзина пуста 🍃',
    emptyCartDesc: 'Вернитесь в каталог, чтобы добавить товары',
    goBack: 'Вернуться назад',
    successTitle: 'Успешно принято! 🎉',
    successDesc: 'Ваш заказ отправлен на подготовку. Мы уведомим вас об изменении статуса.',
    orderId: 'ID заказа',
    status: 'Статус',
    activeBadge: 'Активно',
    deliveryFeePaidToCourier: 'Оплата курьеру 🛵',
    status_new: 'Ожидание',
    status_accepted: 'Принят',
    status_preparing: 'Готовится',
    status_ready: 'Готов',
    status_courier_assigned: 'Курьер назначен',
    status_on_the_way: 'В пути',
    status_delivery: 'В пути',
    status_delivered: 'Доставлен',
    status_canceled: 'Отменен',
    status_rejected: 'Отклонен',
    status_refunded: 'Возвращен',
    // Payment statuses
    paymentStatus_pending: 'Ожидает оплаты',
    paymentStatus_paid: 'Оплачено',
    paymentStatus_failed: 'Не удалась',
    paymentStatus_cancelled: 'Отменена',
    paymentStatus_expired: 'Истекла',
    paymentStatus_refunded: 'Возвращена',
  },
  en: {
    catalog: 'Catalog',
    cart: 'Cart',
    checkout: 'Checkout',
    myOrders: 'My Orders',
    total: 'Total',
    subtotal: 'Subtotal',
    deliveryFee: 'Delivery Fee',
    orderType: 'Order Type',
    delivery: 'Delivery',
    pickup: 'Pickup',
    deliveryAddress: 'Delivery Address',
    enterAddress: 'Enter your full delivery address...',
    paymentMethod: 'Payment Method',
    cash: '💵 Cash on Delivery',
    cashDesc: 'Pay with physical cash upon delivery',
    click: '🔵 Pay via Click',
    clickDesc: 'Instant card payment via Click',
    payme: '🟢 Pay via Payme',
    paymeDesc: 'Secure billing checkout via Payme',
    notes: 'Additional Notes',
    notesPlaceholder: 'e.g. Please don\'t ring the bell',
    payButton: 'Pay: {{total}} UZS',
    processing: 'Processing...',
    addToCart: 'Add to Cart',
    outOfStock: 'Out of Stock',
    quantity: 'Quantity',
    emptyCart: 'Cart is empty 🍃',
    emptyCartDesc: 'Go back to catalog to add items',
    goBack: 'Go back',
    successTitle: 'Order Received! 🎉',
    successDesc: 'Your order has been sent to the kitchen. We will notify you on status changes.',
    orderId: 'Order ID',
    status: 'Status',
    activeBadge: 'Active',
    deliveryFeePaidToCourier: 'Paid to courier 🛵',
    status_new: 'Pending',
    status_accepted: 'Accepted',
    status_preparing: 'Preparing',
    status_ready: 'Ready',
    status_courier_assigned: 'Courier assigned',
    status_on_the_way: 'On the way',
    status_delivery: 'On the way',
    status_delivered: 'Delivered',
    status_canceled: 'Cancelled',
    status_rejected: 'Rejected',
    status_refunded: 'Refunded',
    // Payment statuses
    paymentStatus_pending: 'Pending',
    paymentStatus_paid: 'Paid',
    paymentStatus_failed: 'Failed',
    paymentStatus_cancelled: 'Cancelled',
    paymentStatus_expired: 'Expired',
    paymentStatus_refunded: 'Refunded',
  }
};

interface LanguageState {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (key: keyof typeof translations['uz'], replacements?: Record<string, string | number>) => string;
}

// Helper to determine initial user language from Telegram WebApp config
const getInitialLanguage = (): Language => {
  const tgLang = tg.initDataUnsafe?.user?.language_code;
  if (tgLang && isLanguage(tgLang)) {
    return tgLang;
  }
  return 'uz'; // default fallback is Uzbek
};

export const useLanguageStore = create<LanguageState>()(
  persist(
    (set, get) => ({
      language: getInitialLanguage(),
      setLanguage: (lang) => set({ language: lang }),
      t: (key, replacements) => {
        const lang = get().language;
        const dict = translations[lang] || translations['uz'];
        let text = dict[key] || translations['uz'][key] || String(key);

        if (replacements) {
          Object.entries(replacements).forEach(([k, v]) => {
            text = text.replace(`{{${k}}}`, String(v));
          });
        }
        return text;
      }
    }),
    {
      name: 'restaurant-language-store',
    }
  )
);
