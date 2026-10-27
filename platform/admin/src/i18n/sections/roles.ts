import { defineSection } from './define';

// Tizim → Rollar
export const roles = defineSection({
  uz: {
    roles_title: 'Rollar',
    roles_subtitle: "Har bir rol nimani qila oladi. Katakni bosing: o'zgarish darhol saqlanadi va jurnalga yoziladi.",
    roles_loadError: 'Rollar jadvali yuklanmadi',
    roles_matrixLabel: 'Rollar va ruxsatlar jadvali',
    roles_permission: 'Ruxsat',
    roles_locked: "Superadministratorning ruxsatlari o'zgarmaydi: u hamma narsani qila oladi.",
    roles_lockedBadge: 'Qulflangan',
    roles_switchLabel: '{{role}}: {{permission}}',
    roles_granted: '«{{role}}» roliga «{{permission}}» ruxsati berildi.',
    roles_revoked: '«{{role}}» rolidan «{{permission}}» ruxsati olindi.',
    roles_riskyTitle: '«{{permission}}» ruxsatini berasizmi?',
    roles_riskyText: "Bu pul yoki muhim ma'lumotlarga ta'sir qiladi. «{{role}}» rolidagi barcha xodimlar buni qila oladi.",
    roles_riskyConfirm: 'Ruxsat berish',
    roles_group_other: 'Boshqa',
    roles_changed: "O'zgartirilgan",
    roles_changedHint: "Rangli katak standart jadvaldan farq qiladi (\"O'zgartirilgan\"). Aylana strelka uni standartga qaytaradi.",
    roles_default: 'Standart: {{value}}',
    roles_defaultOn: 'ruxsat bor',
    roles_defaultOff: "ruxsat yo'q",
    roles_resetLabel: 'Standartga qaytarish: {{role}}, {{permission}} ({{value}})',
    roles_resetDone: '«{{role}}» roli uchun «{{permission}}» ruxsati standartga qaytarildi.',
  },
});
