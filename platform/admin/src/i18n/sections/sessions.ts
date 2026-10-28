import { defineSection } from './define';

// Sessiyalarim va xavfsizlik
export const sessions = defineSection({
  uz: {
    sessions_title: 'Sessiyalarim',
    sessions_subtitle: "Hisobingizga kirilgan qurilmalar. Tanimagan bo'lsangiz, sessiyani tugating.",
    sessions_loadError: "Sessiyalar ro'yxati yuklanmadi",
    sessions_current: 'Shu qurilma',
    sessions_started: 'Kirgan vaqt',
    sessions_lastUsed: 'Oxirgi faollik',
    sessions_expires: 'Muddati',
    sessions_unknownDevice: "Noma'lum qurilma",
    sessions_revoke: 'Sessiyani tugatish',
    sessions_revokeTitle: 'Sessiyani tugatasizmi?',
    sessions_revokeText: 'Shu qurilmadagi kirish darhol tugaydi.',
    sessions_revokeConfirm: 'Ha, tugatish',
    sessions_revoked: 'Sessiya tugatildi.',
    sessions_empty: "Boshqa faol sessiya yo'q",

    sessions_security: 'Xavfsizlik',
    sessions_twoFactor: '2 bosqichli tasdiqlash',
    sessions_twoFactorOn: 'Yoqilgan',
    sessions_twoFactorOff: 'Yoqilmagan',
    sessions_codesLeft: 'Tiklash kodlari qoldi',
    sessions_lastLogin: 'Oxirgi kirish',
    sessions_newCodes: 'Yangi tiklash kodlarini yaratish',
    sessions_newCodesTitle: 'Yangi tiklash kodlari',
    sessions_newCodesText: "Eski kodlar ishlamaydi. Yangilarini saqlab qo'ying: ular faqat hozir ko'rsatiladi.",
  },
});
