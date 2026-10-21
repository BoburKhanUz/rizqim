import type { Localized } from '@rizqim/staff';

// The words the staff order screens add to @rizqim/staff's: the branch filter, the kitchen's progress, the waiting time and
// the order detail. Order statuses, types, payment methods and the buttons' names are the package's (staffText, statusLabel ...).
export const ORDER_TEXT = {
  branchFilter: { uz: "Filial bo'yicha", ru: 'По филиалу' },
  allBranches: { uz: 'Barcha filiallar', ru: 'Все филиалы' },
  kitchenProgress: { uz: 'Oshxona: {{ready}}/{{total}} tayyor', ru: 'Кухня: {{ready}}/{{total}} готово' },
  lineReady: { uz: 'Tayyor', ru: 'Готово' },
  waiting: { uz: 'Kutmoqda: {{time}}', ru: 'Ждёт: {{time}}' },
  details: { uz: 'Batafsil', ru: 'Подробнее' },
  detailsOf: { uz: '{{code}} buyurtmasi: batafsil', ru: 'Заказ {{code}}: подробнее' },
  note: { uz: 'Izoh', ru: 'Комментарий' },
  table: { uz: 'Stol {{table}}', ru: 'Стол {{table}}' },
  hall: { uz: 'Zal {{hall}}', ru: 'Зал {{hall}}' },
  guests: { uz: '{{n}} mehmon', ru: 'Гостей: {{n}}' },
  linesCount: { uz: '{{n}} ta taom', ru: 'Позиций: {{n}}' },

  // Detail screen
  orderTitle: { uz: 'Buyurtma {{code}}', ru: 'Заказ {{code}}' },
  notFound: { uz: 'Buyurtma topilmadi', ru: 'Заказ не найден' },
  notFoundText: { uz: "U o'chirilgan yoki boshqa filialniki bo'lishi mumkin.", ru: 'Он удалён или относится к другому филиалу.' },
  customer: { uz: 'Mijoz', ru: 'Клиент' },
  phone: { uz: 'Telefon', ru: 'Телефон' },
  call: { uz: "Qo'ng'iroq qilish", ru: 'Позвонить' },
  delivery: { uz: 'Yetkazish manzili', ru: 'Адрес доставки' },
  zone: { uz: 'Hudud: {{zone}}', ru: 'Зона: {{zone}}' },
  openMap: { uz: 'Xaritada ochish', ru: 'Открыть на карте' },
  courier: { uz: 'Kuryer', ru: 'Курьер' },
  noCourier: { uz: 'Kuryer biriktirilmagan', ru: 'Курьер не назначен' },
  items: { uz: 'Taomlar', ru: 'Блюда' },
  sums: { uz: 'Hisob', ru: 'Расчёт' },
  subtotal: { uz: 'Taomlar summasi', ru: 'Сумма блюд' },
  discount: { uz: 'Chegirma', ru: 'Скидка' },
  deliveryPrice: { uz: 'Yetkazish', ru: 'Доставка' },
  serviceCharge: { uz: 'Xizmat haqi', ru: 'Сервисный сбор' },
  placeFee: { uz: 'Joy narxi', ru: 'Плата за место' },
  total: { uz: 'Jami', ru: 'Итого' },
  payment: { uz: "To'lov", ru: 'Оплата' },
  channel: { uz: 'Kanal', ru: 'Канал' },
  branch: { uz: 'Filial', ru: 'Филиал' },
  history: { uz: 'Holatlar tarixi', ru: 'История статусов' },
  stages: { uz: 'Har bir bosqich vaqti', ru: 'Время по этапам' },
  createdAt: { uz: 'Kelgan vaqti', ru: 'Создан' },
  acceptedAt: { uz: 'Qabul qilingan', ru: 'Принят' },
  preparingAt: { uz: 'Tayyorlash boshlangan', ru: 'Готовка началась' },
  readyAt: { uz: "Tayyor bo'lgan", ru: 'Готов' },
  closedAt: { uz: 'Yopilgan', ru: 'Закрыт' },
  cancelReason: { uz: 'Sabab', ru: 'Причина' },
  hiddenForRole: { uz: "Mijoz ma'lumoti bu rol uchun yashirilgan.", ru: 'Данные клиента скрыты для этой роли.' },
} satisfies Record<string, Localized>;

export const CHANNEL_LABELS: Record<string, Localized> = {
  telegram: { uz: 'Telegram', ru: 'Telegram' },
  mobile: { uz: 'Mobil ilova', ru: 'Мобильное приложение' },
  pos_hall: { uz: 'Zal (kassa)', ru: 'Зал (касса)' },
  pos_takeaway: { uz: 'Olib ketish (kassa)', ru: 'С собой (касса)' },
  aggregator: { uz: 'Agregator', ru: 'Агрегатор' },
};
