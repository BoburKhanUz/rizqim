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
    delivery: 'Yetkazib berish',
    pickup: 'Olib ketish',
    dineIn: 'Joyida',
    deliveryAddress: 'Yetkazish manzili',
    paymentMethod: 'To\'lov usuli',
    notes: 'Qo\'shimcha eslatmalar',
    payButton: 'To\'lash: {{total}} so\'m',
    addToCart: 'Savatga qo\'shish',
    quantity: 'Soni',
    orderId: 'Buyurtma ID',
    status: 'Holat',
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
    // Shared UI (components/ui)
    retry: 'Qayta urinish',
    reload: 'Qayta yuklash',
    loading: 'Yuklanmoqda...',
    connecting: 'Ulanmoqda...',
    loadErrorTitle: "Ma'lumotlarni yuklab bo'lmadi",
    loadErrorText: "Internet aloqasini tekshiring va qayta urinib ko'ring.",
    authErrorTitle: 'Kirish amalga oshmadi',
    authErrorText: 'Ilovani Telegram ichida qaytadan oching.',
    ok: 'OK',
    dialogCancel: 'Bekor qilish',
    dialogConfirm: 'Tasdiqlash',
    close: 'Yopish',
    back: 'Orqaga',
    viewCart: "Savatni ko'rish",
    pageLoadErrorTitle: "Sahifani yuklab bo'lmadi",
    offlineTitle: 'Internet aloqasi mavjud emas',
    offlineText: "Iltimos, internet aloqasini tekshiring va qaytadan urining.",
    // Bottom navigation
    nav_home: 'Asosiy',
    nav_stores: 'Restoranlar',
    nav_add: "Qo'shish",
    nav_analytics: 'Analitika',
    nav_menu: 'Katalog',
    nav_orders: 'Buyurtmalar',
    nav_employees: 'Xodimlar',
    nav_deliveries: 'Yetkazmalar',
    nav_profile: 'Profil',
    // Payment methods (Order.payment_method)
    paymentMethod_cash: 'Naqd pul',
    paymentMethod_card: 'Plastik karta',
    paymentMethod_click: 'Click',
    paymentMethod_payme: 'Payme',
    paymentMethod_uzum: 'Uzum',
    paymentMethod_paynet: 'Paynet',
    paymentMethod_keshbek: 'Keshbek',
    // Order statuses as the staff sees them (StatusBadge audience="staff")
    staffStatus_new: 'Yangi',
    staffStatus_accepted: 'Qabul qilindi',
    staffStatus_preparing: 'Tayyorlanmoqda',
    staffStatus_ready: 'Tayyor',
    staffStatus_courier_assigned: 'Kuryer kutilmoqda',
    staffStatus_on_the_way: "Yo'lda",
    staffStatus_delivery: "Yo'lda",
    staffStatus_delivered: 'Yetkazildi',
    staffStatus_canceled: 'Bekor qilindi',
    staffStatus_rejected: 'Rad etildi',
    staffStatus_refunded: 'Qaytarildi',
  },
  ru: {
    catalog: 'Каталог',
    cart: 'Корзина',
    checkout: 'Оформление',
    myOrders: 'Мои заказы',
    total: 'Итого',
    subtotal: 'Подитог',
    deliveryFee: 'Доставка',
    delivery: 'Доставка',
    pickup: 'Самовывоз',
    dineIn: 'В зале',
    deliveryAddress: 'Адрес доставки',
    paymentMethod: 'Способ оплаты',
    notes: 'Дополнительные примечания',
    payButton: 'Оплатить: {{total}} сум',
    addToCart: 'В корзину',
    quantity: 'Количество',
    orderId: 'ID заказа',
    status: 'Статус',
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
    retry: 'Повторить',
    reload: 'Перезагрузить',
    loading: 'Загрузка...',
    connecting: 'Подключение...',
    loadErrorTitle: 'Не удалось загрузить данные',
    loadErrorText: 'Проверьте подключение к интернету и попробуйте снова.',
    authErrorTitle: 'Не удалось войти',
    authErrorText: 'Откройте приложение заново в Telegram.',
    ok: 'OK',
    dialogCancel: 'Отмена',
    dialogConfirm: 'Подтвердить',
    close: 'Закрыть',
    back: 'Назад',
    viewCart: 'Перейти в корзину',
    pageLoadErrorTitle: 'Не удалось загрузить страницу',
    offlineTitle: 'Нет подключения к интернету',
    offlineText: 'Проверьте подключение к интернету и попробуйте снова.',
    nav_home: 'Главная',
    nav_stores: 'Рестораны',
    nav_add: 'Добавить',
    nav_analytics: 'Аналитика',
    nav_menu: 'Меню',
    nav_orders: 'Заказы',
    nav_employees: 'Сотрудники',
    nav_deliveries: 'Доставки',
    nav_profile: 'Профиль',
    paymentMethod_cash: 'Наличные',
    paymentMethod_card: 'Карта',
    paymentMethod_click: 'Click',
    paymentMethod_payme: 'Payme',
    paymentMethod_uzum: 'Uzum',
    paymentMethod_paynet: 'Paynet',
    paymentMethod_keshbek: 'Кешбэк',
    staffStatus_new: 'Новый',
    staffStatus_accepted: 'Принят',
    staffStatus_preparing: 'Готовится',
    staffStatus_ready: 'Готов',
    staffStatus_courier_assigned: 'Ожидание курьера',
    staffStatus_on_the_way: 'В пути',
    staffStatus_delivery: 'В пути',
    staffStatus_delivered: 'Доставлен',
    staffStatus_canceled: 'Отменен',
    staffStatus_rejected: 'Отклонен',
    staffStatus_refunded: 'Возвращен',
  },
  en: {
    catalog: 'Catalog',
    cart: 'Cart',
    checkout: 'Checkout',
    myOrders: 'My Orders',
    total: 'Total',
    subtotal: 'Subtotal',
    deliveryFee: 'Delivery Fee',
    delivery: 'Delivery',
    pickup: 'Pickup',
    dineIn: 'Dine-in',
    deliveryAddress: 'Delivery Address',
    paymentMethod: 'Payment Method',
    notes: 'Additional Notes',
    payButton: 'Pay: {{total}} UZS',
    addToCart: 'Add to Cart',
    quantity: 'Quantity',
    orderId: 'Order ID',
    status: 'Status',
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
    retry: 'Try again',
    reload: 'Reload',
    loading: 'Loading...',
    connecting: 'Connecting...',
    loadErrorTitle: 'Could not load the data',
    loadErrorText: 'Check your internet connection and try again.',
    authErrorTitle: 'Could not sign in',
    authErrorText: 'Open the app again from Telegram.',
    ok: 'OK',
    dialogCancel: 'Cancel',
    dialogConfirm: 'Confirm',
    close: 'Close',
    back: 'Back',
    viewCart: 'View cart',
    pageLoadErrorTitle: 'Could not load the page',
    offlineTitle: 'No internet connection',
    offlineText: 'Check your internet connection and try again.',
    nav_home: 'Home',
    nav_stores: 'Restaurants',
    nav_add: 'Add',
    nav_analytics: 'Analytics',
    nav_menu: 'Menu',
    nav_orders: 'Orders',
    nav_employees: 'Staff',
    nav_deliveries: 'Deliveries',
    nav_profile: 'Profile',
    paymentMethod_cash: 'Cash',
    paymentMethod_card: 'Card',
    paymentMethod_click: 'Click',
    paymentMethod_payme: 'Payme',
    paymentMethod_uzum: 'Uzum',
    paymentMethod_paynet: 'Paynet',
    paymentMethod_keshbek: 'Cashback',
    staffStatus_new: 'New',
    staffStatus_accepted: 'Accepted',
    staffStatus_preparing: 'Preparing',
    staffStatus_ready: 'Ready',
    staffStatus_courier_assigned: 'Waiting for courier',
    staffStatus_on_the_way: 'On the way',
    staffStatus_delivery: 'On the way',
    staffStatus_delivered: 'Delivered',
    staffStatus_canceled: 'Cancelled',
    staffStatus_rejected: 'Rejected',
    staffStatus_refunded: 'Refunded',
  }
};

export type TranslationKey = keyof typeof translations['uz'];

interface LanguageState {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (key: TranslationKey, replacements?: Record<string, string | number>) => string;
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
