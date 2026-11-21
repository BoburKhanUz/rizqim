# Fiskal xatlar: kimga, qanday va qaysi tartibda yuboriladi

Bu papkada 5 ta tayyor xat bor. Har biri bitta faylda, o'zbekcha va ruscha versiyada (qabul qiluvchining tili noma'lum bo'lgani uchun hammasida o'zbekcha birinchi). Xatlarni egasi jo'natadi; biz faqat yozdik. Asos: [LAUNCH_PLAN.md](../../LAUNCH_PLAN.md) "P rejasi" 2-yo'l, hisobotlar: `docs/research/reports/Fiskal provayder*`.

## Jo'natishdan oldin (har bir xat uchun)

1. Qavsdagi joylarni to'ldiring: [Kompaniya rekvizitlari], [Direktor F.I.Sh.], [Telefon], [Sana] (Soliq qo'mitasi xatida [Raqam] ham).
2. Xatni kompaniya blankasida chop eting, direktor imzolasin va muhr qo'ysin; elektron pochta uchun imzolangan PDF skanerini ilova qiling.
3. 1-xatdagi ikki iqtibos (25-band 9-xatboshi va 161-band) tadqiqotdagi matndan so'zma-so'z olingan, lekin tadqiqotda "yurist qayta tekshirsin" deb belgilangan (lex.uz matni qisqartiruvchi vosita orqali o'qilgan). Jo'natishdan oldin lex.uz VM 943 (3-ilova) dagi matn bilan solishtiring.
4. Yuboriladigan nusxani va jo'natish sanasini saqlang (3-bo'limdagi jadvalga yozing).

## 1. Kimga, qaysi kanal orqali

| Xat | Kimga | Kanal | Eslatma |
|---|---|---|---|
| [01_soliq_qomitasi.md](01_soliq_qomitasi.md) | Davlat soliq qo'mitasi | Asosiy: my3.soliq.uz orqali elektron murojaat (kabinetdan). Nusxa: rasmiy xat (kirish bo'limiga topshirish yoki pochta) | Javob rasmiy bo'lishi uchun murojaat raqamini saqlang. Javob muddatini murojaat qabul qilinganda so'rang |
| [02_ofd_yangi_texnologiyalar.md](02_ofd_yangi_texnologiyalar.md) | "Yangi texnologiyalar" markazi (OFD) | Elektron pochta: info@yt.uz; tel. 71 202-32-32 orqali xat kelganini tasdiqlang | Aloqa ma'lumoti tadqiqotda tekshirilmagan (MEDIA manba), qo'ng'iroq qilib aniqlang |
| [03_bct_fiscalbox.md](03_bct_fiscalbox.md) | BCT (FiscalBox) | Elektron pochta: info@bct.uz; qo'ng'iroq: +998 71 205-80-89, sotuv +998 91 162-88-90 | Faqat vaqtinchalik ko'prik uchun |
| [04_payme.md](04_payme.md) | Payme | Payme biznes (merchant) kabineti yoki texnik qo'llab-quvvatlash; pochta manzilini egasi tekshirib qo'yadi | Manzil tadqiqotda yo'q, taxmin qilib yozilmadi |
| [05_click.md](05_click.md) | Click | Click merchant kabineti yoki texnik qo'llab-quvvatlash; pochta manzilini egasi tekshirib qo'yadi | Manzil tadqiqotda yo'q, taxmin qilib yozilmadi |

## 2. Tartib

1. **1-kun: 1-xat (Soliq qo'mitasi) va 2-xat (OFD) birga.** Ikkalasi kutish vaqti eng uzun, shuning uchun birinchi yuboriladi. Ular bir-birini to'ldiradi: Soliq qo'mitasi qonuniy savolga, OFD texnik yo'lga javob beradi.
2. **Xuddi shu kuni yoki 1-2 kun ichida: 4- va 5-xat (Payme, Click).** Marketpleys pilotida bular tez kerak bo'ladi, javobni kutish shart emas, kalitlar olish bilan parallel ketadi.
3. **3-xat (BCT) hozir ham yuborilishi mumkin**, lekin u faqat zaxira: Soliq qo'mitasi 1-xatdagi (a) sxemani ruxsat etsa yoki BCT 25-band bo'yicha yozma pozitsiya bersa, ko'prikdan foydalaniladi. Javobsiz yoki salbiy bo'lsa ham o'z kassamiz yo'li to'xtamaydi.
4. Reyestrni qo'lda tekshirish (soliq.uz virtual kassa va TXKM reyestrlari) xatlarga bog'liq emas, parallel qilinadi. BCT ning hozirgi reyestr holati va raqib bo'lmagan TXKM ro'yxati shundan chiqadi.
5. Javob kelmasa: 7 ish kunidan keyin qo'ng'iroq yoki eslatma xati, 15 kundan keyin ikkinchi eslatma. Soliq qo'mitasi uchun murojaat kabinetida holatni kuzating.

## 3. Javoblar bilan nima qilinadi

Har bir javobni `docs/fiscal/` ichida olingan sanasi bilan saqlang (PDF/skaner), va quyidagi jadvalga yozing (keyinroq alohida fayl qilinadi). Kelgan javob asl matnda, o'zgartirilmasdan saqlanadi; xulosa alohida yoziladi.

| Javob | Nima qilamiz |
|---|---|
| **Soliq qo'mitasi: (a) sxema ruxsat etiladi** | BCT/boshqa provayder orqali ko'prik bilan POS pilotni ertaroq boshlash mumkin. Javob matnini provayder shartnomasiga ilova qiling. LAUNCH_PLAN "P rejasi" 2-yo'lini yangilang |
| **Soliq qo'mitasi: (a) ruxsat etilmaydi yoki javob noaniq** | Ko'prik yo'q. POS faqat o'z virtual kassamiz (b) bilan; tasdiqgacha POS real tushum bilan ishlatilmaydi. 3-xatni to'xtating |
| **Soliq qo'mitasi: (b) va (c) tasdiqlanadi** | Reja o'zgarmaydi: marketpleys pilot (c) va o'z kassamiz (b). Matnni ariza hujjatlariga qo'shing |
| **OFD javobi** | Shartnoma namunasi, spetsifikatsiya, test serverni bizning texnik ish ro'yxatiga o'tkazing (emulyator bilan integratsiya). Narx va muddatlarni 4-6 oylik rejaga kiriting. Bitta yoki ikkita ariza (kassa va marketpleys) savoliga javobga qarab hujjatlarni yig'ing |
| **BCT javobi** | Reyestr raqami, chek turlari, qurilma narxi va 9-savoldagi pozitsiyani solishtirish jadvaliga kiriting. Agar yozma pozitsiya bo'lmasa, uni faqat "reyestrdan chiqarilsa" bandi bilan ko'rib chiqing |
| **Payme / Click** | Kim sotuvchi bo'lishi, MXIK maydonlari, qaytarish va qaytariladigan ma'lumot javoblari marketpleys to'lov kodiga talab sifatida o'tkaziladi (Payme: SetFiscalData; Click: ofd_data). Zal kassasi bilan ikki marta fiskallanish savoliga javob POS dizaynini belgilaydi |
| **Javob ziddiyatli yoki norasmiy (telefon, chat)** | Yozma tasdiq so'rang. Og'zaki javobga tayanib qaror qilmang |

## 4. Nazorat jadvali (to'ldiriladi)

| Xat | Yuborilgan sana | Kanal / murojaat raqami | Javob sanasi | Natija |
|---|---|---|---|---|
| 1 Soliq qo'mitasi | | | | |
| 2 OFD | | | | |
| 3 BCT | | | | |
| 4 Payme | | | | |
| 5 Click | | | | |
