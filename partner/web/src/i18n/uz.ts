// O'zbekcha (lotin) matnlar: asosiy til. ru.ts va en.ts shu kalitlar bilan bir xil bo'lishi shart (Messages turi tekshiradi).
export const uz = {
  appName: 'Rizqim Hamkor',
  appTagline: 'Restoran boshqaruv paneli',

  // Navigatsiya
  nav_dashboard: 'Boshqaruv paneli',
  nav_orders: 'Buyurtmalar',
  nav_menu: 'Menyu',
  nav_stoplist: 'Stop-list',
  nav_branches: 'Filiallar',
  nav_staff: 'Xodimlar',
  nav_reports: 'Hisobotlar',
  nav_finance: 'Moliya',
  nav_settings: 'Sozlamalar',
  navAria: 'Asosiy menyu',
  openMenu: 'Menyuni ochish',
  closeMenu: 'Menyuni yopish',

  // Umumiy
  logout: 'Chiqish',
  retry: 'Qayta urinish',
  loading: 'Yuklanmoqda...',
  language: 'Til',
  loadErrorTitle: "Ma'lumot yuklanmadi",
  loadErrorText: "Internetni tekshirib, qayta urinib ko'ring.",
  unknownError: "Xatolik yuz berdi. Qayta urinib ko'ring.",
  networkError: "Serverga ulanib bo'lmadi. Internetni tekshiring.",
  signedInAs: 'Kirgan hisob',

  // Kirish
  loginTitle: 'Hamkor paneliga kirish',
  loginSubtitle: 'Telefon raqamingizni kiriting, SMS orqali kod yuboramiz.',
  phoneLabel: 'Telefon raqami',
  phoneHint: "O'zbekiston raqami: +998 dan keyin 9 ta raqam",
  invalidPhone: "Raqamni to'g'ri kiriting: +998 90 123 45 67",
  sendCode: 'Kod yuborish',
  codeLabel: 'SMS kod',
  codeSent: 'Kod {{phone}} raqamiga yuborildi.',
  invalidCode: "Kod 6 ta raqamdan iborat bo'lishi kerak.",
  verify: 'Kirish',
  resend: 'Kodni qayta yuborish',
  resendIn: 'Qayta yuborish mumkin: {{seconds}} s',
  changePhone: "Raqamni o'zgartirish",
  chooseAccount: 'Qaysi hisobga kirasiz?',
  rateLimited: "Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.",

  // Kirish huquqi yo'q
  noAccessTitle: "Bu hisob uchun panelga kirish yo'q",
  noAccessText: "Panel restoran egasi va menejerlari uchun. Boshqa hisob bilan kiring yoki ega bilan bog'laning.",

  // Hali tayyor emas
  placeholderTitle: "Bu bo'lim tez orada",
  placeholderText: "Bu sahifa keyingi bosqichlarda qo'shiladi.",

  // Boshqaruv paneli
  dashboardSubtitle: "Bugungi ko'rsatkichlar",
  ordersCount: 'Buyurtmalar',
  revenue: 'Tushum',
  revenueNote: 'Yetkazilgan buyurtmalar',
  averageCheck: "O'rtacha chek",
  activeOrders: 'Faol buyurtmalar',
  deliveredCount: 'Yetkazildi',
  canceledCount: 'Bekor qilindi',
  byStatus: "Holatlar bo'yicha",
  topProducts: "Eng ko'p sotilgan taomlar",
  byPayment: "To'lov usullari",
  byType: 'Buyurtma turlari',
  avgPrep: "O'rtacha tayyorlash vaqti",
  avgDelivery: "O'rtacha yetkazish vaqti",
  minutes: '{{n}} daq',
  piecesShort: '{{n}} ta',
  ordersShort: '{{n}} ta buyurtma',
  noOrdersToday: "Bugun hali buyurtma yo'q",
  noOrdersTodayText: "Birinchi buyurtma kelgach, ko'rsatkichlar shu yerda paydo bo'ladi.",
  updatedAt: 'Yangilandi: {{time}}',

  // Buyurtmalar
  ordersSubtitle: "Faol buyurtmalar (faqat ko'rish)",
  colCode: 'Raqam',
  colStatus: 'Holat',
  colCustomer: 'Mijoz',
  colType: 'Turi',
  colPayment: "To'lov",
  colTotal: 'Summa',
  colTime: 'Vaqt',
  noActiveOrders: "Faol buyurtma yo'q",
  noActiveOrdersText: "Yangi buyurtma kelganda shu yerda paydo bo'ladi. Ro'yxat o'zi yangilanadi.",
  ordersTruncated: "Ro'yxat eng so'nggi 200 ta buyurtma bilan cheklangan.",
  guest: 'Mijoz',

  // Buyurtma holatlari (xodim so'zlari)
  status_new: 'Yangi',
  status_accepted: 'Qabul qilindi',
  status_preparing: 'Tayyorlanmoqda',
  status_ready: 'Tayyor',
  status_courier_assigned: 'Kuryer kutilmoqda',
  status_on_the_way: "Yo'lda",
  status_delivery: "Yo'lda",
  status_delivered: 'Yetkazildi',
  status_canceled: 'Bekor qilindi',
  status_rejected: 'Rad etildi',
  status_refunded: 'Qaytarildi',

  // Buyurtma turlari va to'lov usullari
  type_delivery: 'Yetkazib berish',
  type_pickup: 'Olib ketish',
  type_dine_in: 'Joyida',
  payment_cash: 'Naqd pul',
  payment_click: 'Click',
  payment_payme: 'Payme',
  payment_uzum: 'Uzum',
  payment_paynet: 'Paynet',
  payment_keshbek: 'Keshbek',

  // Rollar
  role_owner: 'Ega',
  role_manager: 'Menejer',
  role_super_admin: 'Superadmin',
  role_cashier: 'Kassir',
  role_waiter: 'Ofitsiant',
  role_cook: 'Oshpaz',
  role_courier: 'Kuryer',
  role_customer: 'Mijoz',
} as const;

export type Messages = { [K in keyof typeof uz]: string };
