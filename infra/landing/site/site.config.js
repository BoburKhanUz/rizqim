/*
 * rizqim.uz: bitta sozlama fayli. Har bir qiymat EGASI BERADI (placeholder) va shu yerda almashtiriladi,
 * HTML va matn fayllariga tegish shart emas. Fayl deploy bilan o'zgaradi (nginx uni no-cache beradi).
 *
 * Konfiguratsiya kalitlari:
 *   siteUrl, botUrl, businessUrl, phone, phoneHref, telegramHandle, telegramUrl, address (uz/ru),
 *   social.telegram, social.instagram (bo'sh = ko'rinmaydi), faq.<id> (javob; null = "Tez orada"),
 *   tariffs.* (null = "Tariflar" bloki umuman ko'rinmaydi), photos.<nom> (true = img/photos/<nom>.webp va <nom>-1x.webp ishlatiladi).
 * Hech qachon o'ylab topilgan narx yozmang: faqat egasi bergan haqiqiy qiymat.
 */
window.RZ_SITE = {
  // EGASI BERADI: asosiy sayt manzili (sitemap.xml, canonical, og:url shu bilan mos bo'lsin)
  siteUrl: 'https://rizqim.uz',

  // EGASI BERADI: Telegram bot havolasi (asosiy tugma "Telegram'da ochish"). Hozir .env.example dagi VITE_BOT_USERNAME
  botUrl: 'https://t.me/rizqimbot',

  // Hamkor paneli (tayyor: business.rizqim.uz)
  businessUrl: 'https://business.rizqim.uz',

  // EGASI BERADI: aloqa. Bo'sh ('') = joy ko'rinmaydi (soxta raqam yoki havola hech qachon chiqmaydi)
  phone: '',
  phoneHref: '',
  telegramHandle: '',
  telegramUrl: '',
  address: { uz: '', ru: '' },

  // EGASI BERADI: faqat haqiqatan bor ijtimoiy tarmoqlar. Bo'sh qator ('') = tugma ko'rinmaydi
  social: {
    telegram: '',
    instagram: '',
  },

  // EGASI BERADI: savollarga javoblar. null = sahifada "Tez orada" ko'rsatiladi. Narx va muddat o'ylab topilmaydi.
  // Kalitlar: price (ulanish narxi), commission, free (bepulmi), time (ishga tushirish muddati), devices, payments
  faq: {
    price:      { uz: null, ru: null },
    commission: { uz: null, ru: null },
    free:       { uz: null, ru: null },
    time:       { uz: null, ru: null },
    devices:    { uz: null, ru: null },
    payments:   { uz: null, ru: null },
  },

  // EGASI BERADI: tariflar (hamkor sahifasidagi ixtiyoriy blok). Hammasi null bo'lsa blok yashirin.
  // Misol: marketplaceCommission: { uz: "Har buyurtmadan X%", ru: "X% с каждого заказа" }
  tariffs: {
    marketplaceCommission: null,
    posMonthly: null,
  },

  // EGASI BERADI: rasmlar. site/img/photos/<nom>.webp (2x) va <nom>-1x.webp fayllari (o'lchamlari: img/photos/README.md).
  // true = rasm ishlatiladi, false = hozirgi SVG chizma. Fayl topilmasa ham chizma qaytadi. Hozir egasi bergan 6 ta rasm.
  photos: {
    'hero-phone': true,
    'hero-food': true,
    'storefront': true,
    'pos-devices': true,
    'partner-panel': true,
    'scooter': true,
  },

  // Forma uchun API (bir xil origin; nginx faqat POST /api/v1/store-applications va GET /api/v1/locations ni o'tkazadi)
  apiBase: '/api/v1',
};
