// O'zbekcha (lotin) matnlar: asosiy til. Bo'limlarga xos matnlar sections/ papkasida.
export const uz = {
  appName: 'RIZQIM boshqaruv markazi',
  appShortName: 'RIZQIM',
  appTagline: 'Platforma jamoasi uchun',

  // Navigatsiya
  nav_today: 'Bugun',
  nav_orders: 'Buyurtmalar',
  nav_stores: 'Ovqatxonalar',
  nav_pos: 'POS va obunalar',
  nav_finance: 'Moliya',
  nav_customers: 'Mijozlar va yordam',
  nav_marketing: 'Marketing',
  nav_monitoring: 'Monitoring',
  nav_integrations: 'Integratsiyalar',
  nav_system: 'Tizim',
  nav_team: 'Jamoa',
  nav_roles: 'Rollar',
  nav_audit: 'Harakatlar jurnali',
  nav_sessions: 'Sessiyalarim',
  navAria: 'Asosiy menyu',
  openMenu: 'Menyuni ochish',
  closeMenu: 'Menyuni yopish',
  skipToContent: "Asosiy qismga o'tish",

  // Umumiy
  logout: 'Chiqish',
  retry: 'Qayta urinish',
  loading: 'Yuklanmoqda...',
  close: 'Yopish',
  cancel: 'Bekor qilish',
  save: 'Saqlash',
  continue: 'Davom etish',
  copy: 'Nusxa olish',
  copied: 'Nusxa olindi',
  signedInAs: 'Kirgan hisob',
  never: 'hali kirmagan',
  loadErrorTitle: "Ma'lumot yuklanmadi",
  unknownError: "Xatolik yuz berdi. Qayta urinib ko'ring.",
  networkError: "Serverga ulanib bo'lmadi. Internetni tekshiring.",
  rateLimited: "Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.",
  rateLimitedIn: "Juda ko'p urinish. {{minutes}} daqiqadan so'ng qayta urinib ko'ring.",
  forbidden: "Bu amal uchun ruxsatingiz yo'q.",
  noAccessTitle: "Sizda hali hech bir bo'limga ruxsat yo'q",
  noAccessText: "Ruxsatlar rolingizga bog'liq. Superadministratorga murojaat qiling.",

  // Hali qurilmagan bo'limlar
  placeholderTitle: "Bu bo'lim keyingi to'lqinda qo'shiladi",
  placeholderText: "Hozircha bu yerda hech narsa yo'q. Tayyor bo'lgach shu joyda paydo bo'ladi.",

  // Qidiruv
  search_button: 'Qidirish',
  search_buttonHint: 'Ctrl K',
  search_title: 'Qidirish',
  search_label: 'Ovqatxona, buyurtma, mijoz yoki xodim qidirish',
  search_placeholder: 'Nom, telefon, buyurtma kodi...',
  search_minChars: 'Kamida 2 ta belgi yozing',
  search_empty: 'Hech narsa topilmadi',
  search_error: "Qidirib bo'lmadi. Qayta urinib ko'ring.",
  search_results: 'Natijalar',
  search_group_store: 'Ovqatxonalar',
  search_group_order: 'Buyurtmalar',
  search_group_user: 'Foydalanuvchilar',
  search_group_member: 'Jamoa',
  search_group_other: 'Boshqa',
} as const;

export type Messages = { [K in keyof typeof uz]: string };
