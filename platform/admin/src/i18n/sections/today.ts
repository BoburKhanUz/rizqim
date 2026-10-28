import { defineSection } from './define';

// Bugun: the control center's home page
export const today = defineSection({
  uz: {
    today_title: 'Bugun',
    today_subtitle: '{{date}}, Toshkent vaqti',
    today_updated: 'Yangilandi: {{time}}',
    today_refresh: 'Yangilash',
    today_loadError: "Bugungi raqamlar yuklanmadi",
    today_statsLate: "Yig'ma jadvallar {{time}} da yangilangan. Raqamlar baribir to'g'ri, faqat sahifa sekinroq ochiladi.",

    today_kpis: "Bugungi ko'rsatkichlar",
    today_gmv: 'GMV (marketpleys)',
    today_orders: 'Buyurtmalar',
    today_commission: 'Komissiya',
    today_subscription: 'Obuna daromadi',
    today_canceled: 'Bekor qilingan: {{n}}',
    today_wholeYesterday: 'Kecha jami: {{value}}',
    today_vsYesterday: 'Kechagi shu vaqtga',
    today_vsLastWeek: "O'tgan haftaning shu kuniga",
    today_more: '{{percent}}% ko\'p ({{before}})',
    today_less: '{{percent}}% kam ({{before}})',
    today_moreFromZero: "ko'p (u paytda {{before}} edi)",
    today_same: "o'zgarmadi ({{before}})",

    today_attention: 'Diqqat talab qiladi',
    today_attentionLoadError: "Diqqat talab qiladigan ro'yxat yuklanmadi",
    today_allGood: 'Hammasi joyida',
    today_allGoodText: "Hozircha aralashish kerak bo'lgan narsa yo'q. Sahifa har 30 soniyada o'zi yangilanadi.",
    today_severity_critical: 'Shoshilinch',
    today_severity_warning: 'Diqqat',
    today_severity_info: "Ma'lumot",
    today_count: '{{n}} ta',
    today_andMore: "Yana {{n}} ta bor",
    today_since: '{{time}} dan beri',

    today_channels: "Kanallar bo'yicha",
    today_notInGmv: 'GMV ga kirmaydi',
    today_ordersN: '{{n}} buyurtma',
    today_regions: "Viloyatlar bo'yicha",
    today_activeStores: '{{n}} faol ovqatxona',
    today_noRegions: "Hali viloyatlar bo'yicha ma'lumot yo'q",
  },
});
