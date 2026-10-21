# 2-bosqich: Hamkor web va oshxona ekrani — ish tartibi

Holati: **tasdiqlangan (21.10.2026).** Ishlar shu hujjat bo'yicha boradi; o'zgarish bo'lsa, avval shu yerda yangilanadi.

Har bir ishning kodi bor (masalan, `P2-21`). Hisobotlar, commit xabarlari va PR'larda shu kod ishlatiladi.

## Maqsad

1. **Hamkor web panel** (`partner/web`, Next.js) — ega va menejer uchun to'liq ish joyi: jonli buyurtmalar doskasi, menyu, stop-list, qozon, xodimlar, filiallar, zonalar, hisobotlar, moliya.
2. **Mini App'dagi xodim ekranlari qoladi va yangilanadi.** Ular ko'chirilmaydi: ega, menejer va kuryer telefonda Telegram orqali ishlashda davom etadi. Web panel ularning nusxasi va kengaytmasi bo'ladi.
3. **Oshxona ekrani** (`pos/apps/kds`, Flutter) — planshet yoki televizorda buyurtmalar, oshpaz "tayyor" deb belgilaydi.

## Asosiy tamoyil: bitta API, ikki xil ekran, umumiy kod

Bir xil ish ikki joyda (Mini App va web) qilinadi, shuning uchun:

- **Biznes qoidalari faqat backend'da.** Ekranlar faqat API'ni chaqiradi; narx, status o'tishlari, ruxsatlar hech qachon frontend'da qayta yozilmaydi.
- **Umumiy TypeScript kodi `packages/ts/` da:** API klienti (`@rizqim/api-client`, allaqachon bor), UI komponentlar (`@rizqim/ui`, allaqachon bor) va yangi `@rizqim/staff` paketi — buyurtma statuslari va ularning nomlari, pul va vazn formati, uz/ru tarjimalar, buyurtmalar uchun React Query hook'lari. Mini App va web panel shu paketdan foydalanadi, shuning uchun bitta tuzatish ikkala joyga tushadi.
- **Ekranlar farqi faqat ko'rinishda:** Mini App telefon uchun (bitta ustun, MainButton), web katta ekran uchun (doska ustunlari, jadvallar, xarita).

Qaysi ish qayerda bo'lishi (taklif, siz tasdiqlaysiz):

| Ish | Mini App (telefon) | Web panel |
|---|---|---|
| Buyurtmalarni qabul qilish, status, kuryer tayinlash | ✅ | ✅ (doska ko'rinishida) |
| Stop-list, "tugadi" belgisi, qozonni ochish/yopish | ✅ | ✅ |
| Kuryer ekrani | ✅ | — (kuryer ilovasi 4-bosqichda) |
| Taom qo'shish va tahrirlash (narx, rasm, variant) | ✅ oddiy | ✅ to'liq: modifikatorlar, vazn, kanal narxlari |
| Xodimlar, PIN, login telefonlari | ✅ | ✅ |
| Filiallar, ish vaqti, xizmat haqi | ✅ | ✅ |
| Yetkazish zonalarini xaritada chizish | — | ✅ |
| Hisobotlar va analitika | ✅ qisqa | ✅ to'liq, filtrlar, eksport |
| To'lovlar, payout, tarif va hisob-fakturalar | ✅ ko'rish | ✅ |

## Umumiy ko'rinish

```
To'lqin 1    P2-11 Xodimlar uchun buyurtma API va real-time ┐
(API va      P2-12 Boshqaruv API'sidagi bo'shliqlar          ├── parallel ─┐
 poydevor)   P2-13 @rizqim/staff paketi                      │             │
             P2-14 Hujjatlar va ROADMAP yangilash            ┘             ▼
To'lqin 2    P2-21 Web: jonli buyurtmalar doskasi ┐
(ilovalar)   P2-22 Web: menyu, stop-list, qozon   │
             P2-23 Web: xodimlar, filiallar, zonalar├── parallel ─┐
             P2-24 Web: hisobotlar va moliya      │             │
             P2-25 Mini App xodim ekranlari       │             │
             P2-26 Oshxona ekrani (KDS)           ┘             ▼
To'lqin 3    P2-30 Backend kamchiliklari             ┐
             P2-31 E2E testlar (web, Mini App, KDS) │
             P2-32 Foydalanuvchi qo'llanmalari      ├ parallel ─┐
             P2-33 Mustaqil review va tuzatishlar ──────────────┤
                                                                ▼
             P2-34 Staging'da tekshiruv (KDS haqiqiy planshetda)  (bosqich tugadi)
```

Ilovalar o'z PR'iga ega (ulkan PR yo'q): to'lqin 1 — **PR #6**; to'lqin 2 — **PR #7** (web panel), **PR #8** (Mini App), **PR #9** (KDS); to'lqin 3 — **PR #10**. Siz merge qilganingizdan keyin keyingi to'lqin boshlanadi.

## To'lqin 1 — API va poydevor (4 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P2-11 | **Xodimlar uchun buyurtma API va real-time.** Oshpaz, kassir, ofitsiant va menejer o'z filiali buyurtmalarini ko'radi (hozir faqat ega, menejer va kuryer ko'radi). Rolga qarab status o'tishlari: oshpaz — "tayyorlanmoqda", "tayyor"; har bir taom qatorini alohida "tayyor" deb belgilash. Oshxona chiptasi uchun ma'lumot: taomlar, modifikatorlar, vazn, izoh, kutish vaqti. Filial uchun yopiq real-time kanal (xodim va ulangan terminal ulana oladi; hozir terminallar hech qaysi kanalga ulana olmaydi). Yangi buyurtma, bekor qilish va o'zgarish hodisalari | Opus | `Ordering`, `routes/channels.php`, ruxsatlar, migratsiyalar | Rol va filial bo'yicha izolyatsiya testlari, terminal tokeni bilan kanalga ulanish testi, eski testlar o'tadi |
| P2-12 | **Boshqaruv API'sidagi bo'shliqlar.** Web panel uchun hozir faqat Filament'da bor ishlar API'ga chiqadi: yetkazish zonalarini yaratish va tahrirlash, filiallar va ish vaqti, hisobotlar (filtr, davr, eksport CSV), to'lovlar va payout tarixi. Hammasi mavjud ruxsatlar tizimi orqali | Opus | `Delivery`, `Finance`, hisobot controllerlari | Har endpoint uchun ruxsat va izolyatsiya testlari |
| P2-13 | **`@rizqim/staff` paketi.** Buyurtma statuslari va nomlari, pul va kg formati, uz/ru tarjimalar, buyurtmalar uchun hook'lar (`@rizqim/api-client` ustida). Mini App xodim ekranlari o'z axios turlaridan shu paketga o'tkaziladi (ko'rinish o'zgarmaydi) | Sonnet | `packages/ts/staff`, `customer/miniapp` xodim sahifalari | Mini App testlari va e2e o'tadi, ikkala ilova build bo'ladi |
| P2-14 | **Hujjatlar.** ROADMAP'dagi eskirgan joylar (PostGIS o'rniga GeoJSON, POS birinchi versiyasi fiskalsiz, xodim ekranlari Mini App'da qoladi); ushbu reja bo'yicha `docs/ARCHITECTURE.md` yangilanadi | Men | `docs/` | — |

**To'qnashuv xavfi va yechim:**
- P2-11 va P2-12 backend'da, lekin turli sohalarda (buyurtmalar va boshqaruv). Umumiy fayllar (`routes/api.php`, ruxsatlar ro'yxati) — har biri o'z bloki bilan.
- P2-13 Mini App'ga tegadigan yagona ish. U P2-11 ning yangi endpoint'larini ishlatmaydi, faqat mavjudlarini ko'chiradi; yangilarini to'lqin 2 da P2-25 ulaydi.

**PR #6:** "P2 to'lqin 1: xodimlar uchun buyurtma API, boshqaruv API, umumiy paket".

## To'lqin 2 — ilovalar (6 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P2-21 | **Web: jonli buyurtmalar doskasi.** Ustunlar: yangi, tayyorlanmoqda, tayyor, yo'lda, yakunlangan. Real-time, yangi buyurtmada ovoz, qabul qilish yoki rad etish, kuryer tayinlash, filial tanlash, qidiruv va filtrlar, buyurtma tafsiloti. Web panelning umumiy layout'i va navigatsiyasi shu ishda | Opus | `partner/web` (layout, `orders`, `dashboard`) | Komponent testlari, real-time testi |
| P2-22 | **Web: menyu, stop-list, qozon.** Taomlar, variantlar, rasm, modifikator guruhlari, vazn (kg narxi, qadam, minimal), kanal narxlari, stop-list (filial bo'yicha), qozonni ochish, to'ldirish va yopish | Sonnet | `partner/web` (`menu`, `stoplist`) | Komponent testlari, xato xabarlari o'zbekcha |
| P2-23 | **Web: xodimlar, filiallar, zonalar, sozlamalar.** Xodim qo'shish, rol, PIN, login telefoni; filiallar va ish vaqti; zonalarni xaritada chizish (narx, minimal buyurtma, bepul yetkazish chegarasi); xizmat haqi va do'kon sozlamalari | Sonnet | `partner/web` (`staff`, `branches`, `settings`) | Komponent testlari, xaritada chizish testi |
| P2-24 | **Web: hisobotlar va moliya.** Savdo, eng ko'p sotilgan taomlar, kanal va filial bo'yicha; davr tanlash, CSV eksport; to'lovlar, payout so'rovlari, tarif va hisob-fakturalar | Sonnet | `partner/web` (`reports`, `finance`) | Komponent testlari, raqamlar backend bilan bir xil |
| P2-25 | **Mini App xodim ekranlari.** 1-bosqichdagi yangiliklarni ko'rsatish: buyurtmada modifikator va vazn, stop-list va qozon boshqaruvi, P2-11 ning real-time hodisalari, filial bo'yicha ish | Sonnet | `customer/miniapp` (xodim sahifalari) | Unit va e2e, yorug' va qorong'i mavzuda suratlar |
| P2-26 | **Oshxona ekrani (KDS).** Ulash bor; qo'shiladi: oshpaz PIN bilan kiradi, chiptalar doskasi (kelgan vaqti va kutish vaqti rangi bilan), taom yoki butun buyurtmani "tayyor" deb belgilash, ovoz, real-time va uzilishdan keyin o'zi qayta ulanish, katta shrift va qorong'i mavzu. Android planshet va Windows | Opus | `pos/apps/kds`, `packages/dart` | Flutter testlari, Linux'da ishga tushirib suratlar |

**To'qnashuv xavfi va yechim:**
- P2-21…P2-24 bitta ilovada. Umumiy layout, navigatsiya va tarjima fayllari P2-21 ga tegishli; qolganlari faqat o'z sahifa papkalarida ishlaydi va tarjimalarni o'z bo'lim fayllariga yozadi. Navigatsiya bandlari P2-21 da oldindan tayyorlanadi.
- P2-25 yagona Mini App ishi. P2-26 yagona Flutter ishi.

**PR #7:** "P2 web panel", **PR #8:** "P2 Mini App xodim ekranlari", **PR #9:** "P2 oshxona ekrani".

## To'lqin 3 — sinov, qo'llanma, review

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P2-30 | **2-to'lqinda topilgan backend kamchiliklari:** menejer menyuni ko'ra olmasligi, kuryer endpoint'lari ruxsat bo'yicha emas rol bo'yicha, xodim ro'yxatida telefon qanday tasdiqlangani, buyurtma ro'yxatida kutish vaqti, do'kon vaqt mintaqasi, payout javobida karta ma'lumoti, server xabarlari o'zbekcha, spetsifikatsiyadagi xatolar, oshxonada tayyor taomni qaytarish, terminal xodimlari ro'yxati; so'ng frontend'dagi aylanma yechimlar olib tashlanadi | Opus | Har tuzatish uchun test, CI yashil |
| P2-31 | **E2E testlar:** web panelda buyurtmani qabul qilishdan yakunlashgacha; Mini App va web bir vaqtda ochiq bo'lganda ikkalasi ham yangilanishi; KDS'da "tayyor" bosilganda doskada ko'rinishi | Sonnet | CI'da o'tadi, 3 marta ketma-ket barqaror |
| P2-32 | **Qo'llanmalar** (o'zbekcha, oddiy tilda): ega va menejer uchun web panel, oshpaz uchun oshxona ekrani | Sonnet | Siz o'qib tasdiqlaysiz |
| P2-33 | **Mustaqil review** (backend, web va Mini App, KDS) va topilmalarni tuzatish | 3 × Opus + tuzatuvchilar | Topilmalar yopilgan, CI yashil |
| P2-34 | **Staging'da tekshiruv:** web panel `panel.<domen>` da, KDS haqiqiy planshetda, real-time va ovoz | Men + siz (server va planshet kerak) | Tekshiruv ro'yxati to'liq |

**PR #10:** "P2 to'lqin 3: e2e, qo'llanmalar, review".

## Har bir ishning bajarilish tartibi

1-bosqichdagidek: agentga aniq topshiriq → agent ishlaydi → men o'zim tekshiraman (testlar, PHPStan, lint, build, e2e, suratlar) → commit (ish kodi bilan) → to'lqin oxirida PR, yashil CI, sizga qisqa hisobot → siz merge qilasiz.

## Sizdan kerak bo'ladiganlar

| Nima | Qachongacha | Kerak bo'lmasa nima bo'ladi |
|---|---|---|
| Ushbu rejani tasdiqlash yoki o'zgartirish | To'lqin 1 dan oldin | Boshlanmaydi |
| Yuqoridagi "Mini App yoki web" jadvalini tasdiqlash | To'lqin 2 | Jadvaldagi taklif bo'yicha qilinadi |
| Filament do'kon paneli taqdiri: web panel to'liq bo'lgach do'konlar uchun yopiladimi (superadmin paneli Filament'da qoladi) | To'lqin 3 | Ikkalasi ham ishlayveradi |
| Oshxona ekrani: bitta oshxona bormi yoki bo'limlar (issiq, sovuq, bar) alohida ekranmi | To'lqin 2 | Birinchi versiyada filialga bitta ekran |
| Sinov uchun Android planshet (yoki televizorga ulanadigan qurilma) | To'lqin 3 | KDS faqat emulator va Linux'da sinaladi |
| Staging server va domen (1-bosqichning P1-35 bilan birga) | To'lqin 3 | P1-35 va P2-34 keyinga qoladi |
| PR'larni ko'rib chiqish va merge | Har to'lqin oxirida | Keyingi to'lqin boshlanmaydi |

## Bosqich tugaganini qanday bilamiz

- Ega va menejer web panelda butun ishni qila oladi: buyurtmalar, menyu, stop-list, qozon, xodimlar, filiallar, zonalar, hisobotlar, moliya.
- Mini App'dagi xodim ekranlari ishlaydi va 1-bosqich yangiliklarini ko'rsatadi; bir buyurtma ikkala joyda bir vaqtda yangilanadi.
- Oshpaz planshetda buyurtmani ko'radi va "tayyor" deydi, bu menejerga darhol ko'rinadi.
- Barcha testlar va e2e o'tadi, CI yashil, PR'lar merge qilingan.
- Staging'da ishlaydi.
