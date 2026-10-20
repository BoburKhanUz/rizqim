# 1-bosqich: Poydevor — ish tartibi

Holati: **tasdiqlangan (20.10.2026).** Ishlar shu hujjat bo'yicha boradi; o'zgarish bo'lsa, avval shu yerda yangilanadi.

Har bir ishning kodi bor (masalan, `P1-12`). Hisobotlar, commit xabarlari va PR'larda shu kod ishlatiladi, shuning uchun qaysi ish qayerda ekani doim ko'rinib turadi.

## Umumiy ko'rinish

```
Qadam 0      P1-01 Papkalarni ko'chirish ───────────────┐   (yolg'iz)
                                                        ▼
To'lqin 1    P1-11 OpenAPI      ┐
             P1-12 Kirish/rollar├── parallel ────────────┐
             P1-13 Infratuzilma │                        │
             P1-14 Texnik qarzlar┘                       ▼
To'lqin 2    P1-21 Menyu + buyurtma modeli ┐
             P1-22 Push                    │
             P1-23 Tariflar                ├── parallel ─┐
             P1-24 Yetkazish zonalari      │             │
             P1-25 Hamkor web skeleti      │             │
             P1-26 Flutter skeleti         ┘             ▼
To'lqin 3    P1-31 Mini-app yangi menyuga  ┐
             P1-32 Zonalarni buyurtmaga ulash├ parallel ─┐
             P1-33 Hujjatlar va POS_SPEC   ┘             ▼
             P1-34 Mustaqil review va tuzatishlar ───────┐
                                                         ▼
             P1-35 Staging'da tekshiruv            (bosqich tugadi)
```

Har bir qadam yoki to'lqin **alohida PR** bo'ladi (jami 4 ta PR). Siz merge qilganingizdan keyin keyingisi boshlanadi.

## Qadam 0 — yolg'iz

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P1-01 | **Papkalarni ko'chirish.** `mini-app/` → `customer/miniapp/`. docker-compose, Dockerfile, nginx, CI, e2e va hujjatlardagi barcha yo'llar yangilanadi. Bo'sh papkalar va har biriga qisqa README: `partner/web`, `partner/mobile`, `courier/`, `pos/apps/{cashier,waiter,kds,customer_display}`, `pos/packages/`, `packages/dart/`, `packages/ts/`, `services/`, `infra/`. CI'da har papka uchun yo'l filtri | Sonnet | Barcha testlar, e2e, build, `docker compose config` |

**Nega yolg'iz:** mini-app'ning barcha fayl yo'llarini o'zgartiradi. Shu payt boshqa ish qilinsa, o'zgarishlar to'qnashadi.
**PR #2:** "P1-01 Papkalar tuzilmasi".

## To'lqin 1 — 4 ta parallel ish

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P1-11 | **OpenAPI.** Scramble o'rnatiladi, `/docs/api` sahifasi, spetsifikatsiya `packages/api-spec/openapi.json` ga eksport qilinadi, CI'da farq tekshiruvi. TypeScript klient `packages/ts/api-client`. Kirish (auth) controllerlariga izohlar P1-12 tugagach qo'shiladi | Opus | `backend/config`, controller izohlari (auth'dan tashqari), `packages/` | Spetsifikatsiya to'liq, klient kompilyatsiya bo'ladi, CI farqni ushlaydi |
| P1-12 | **Kirish, rollar, qurilmalar.** Refresh token; `devices` jadvali; POS'ni 6 xonali kod bilan ulash; PIN bilan kirish; ruxsatlar tizimi (ega, menejer, kassir, ofitsiant, oshpaz, kuryer, superadmin) filial bo'yicha; harakatlar jurnali; hisoblarni telefon bo'yicha birlashtirish; Filament'da rollar ekrani | Opus | `backend/app/…/Auth`, migratsiyalar, middleware, policy'lar, Filament | Yangi testlar: ulash, PIN, ruxsatlar, jurnal; eski testlar o'tadi |
| P1-13 | **Infratuzilma.** Staging uchun compose va `.env` namunasi; feature flag (Laravel Pennant); Reverb uchun `ext-uv`; Postgres versiyasini CI va Docker'da bir xil qilish; zaxira nusxani S3'ga yuborish | Sonnet | `docker-compose*.yml`, `backend/docker/`, `.github/`, `scripts/`, `docs/DEPLOY.md` | `docker compose config`, image yig'iladi, CI yashil |
| P1-14 | **Texnik qarzlar.** Ega analitikasi uchun yig'ma endpoint va mini-app'ni unga ulash; ega yoki menejer kuryer bo'lganda real-time; arxivdagi buyurtma o'zgarishi darhol ko'rinishi; `X-Request-Id`ni mijoz bera olmasligi; `/health` ichki raqamlarini yashirish; mini-app'dagi ikki tarjima jadvalini birlashtirish; xarita va manzil qidirish uchun e2e test | Sonnet | analitika controlleri, `routes/channels.php`, `customer/miniapp/` | Testlar va e2e o'tadi |

**To'qnashuv xavfi va yechim:**
- P1-11 va P1-12 ikkalasi controllerlarga tegadi. Shuning uchun P1-11 auth controllerlariga tegmaydi, ularni P1-12 tugagach izohlaydi.
- P1-14 mini-app'da ishlaydigan yagona ish, boshqa hech kim mini-app'ga tegmaydi.

**PR #3:** "P1 to'lqin 1: OpenAPI, kirish va rollar, infratuzilma, texnik qarzlar".

## To'lqin 2 — 6 ta parallel ish

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P1-21 | **Menyu va buyurtma modeli** (bitta agent, chunki ikkalasi bir jadvalga tegadi). Modifikator guruhlari (majburiy/ixtiyoriy, min/max, narx qo'shimchasi); dona yoki kg, kg narxi va qadami, kasr miqdor; qozon qoldig'i (avtomatik "tugadi"); filial bo'yicha stop-list va uning real-time tarqalishi; kanal bo'yicha narx va ko'rinish; buyurtmada `channel`, tashqi raqam, idempotency kaliti; buyurtma qatorida modifikatorlar va vazn; xizmat haqi va joy narxi qatorlari; stol, zal va mehmonlar soni maydonlari (POS uchun zaxira); rasmlar uchun S3 va kichraytirish | Opus | `Catalog`, `Ordering`, migratsiyalar, Filament menyu ekranlari | Pul hisobi testlari (kasr miqdor, modifikator narxi), qozon qoldig'i parallel so'rovlarda to'g'ri kamayadi |
| P1-22 | **Push.** FCM (HTTP v1, yangi PHP paketsiz: service account bilan to'g'ridan-to'g'ri); xabar kanallarini birlashtirish (Telegram, push, SMS); foydalanuvchi sozlamalari | Sonnet | `Notifications`, P1-12'dagi `devices` | Sinov qurilmasiga push boradi (Firebase loyihasi kerak) |
| P1-23 | **Tariflar.** Tarif rejalari (obuna, komissiya yoki ikkalasi); har oy avtomatik hisob-faktura va ledger'ga yozish; superadmin ekrani; Rizqim daromadi hisoboti | Opus | `Finance`, Filament superadmin | Pul testlari, oy oxiri jobi |
| P1-24 | **Yetkazish zonalari.** Zonalar ko'pburchak sifatida (GeoJSON, `jsonb`) saqlanadi, nuqta zonadami — PHP'da hisoblanadi, tez filtr uchun chegara to'rtburchagi indekslanadi. PostGIS hozircha kerak emas (har do'konda bir nechta zona); kerak bo'lsa keyin qo'shiladi. Zonalar (narx va minimal buyurtma); eng yaqin ochiq filial; kuryer turi (o'z yoki Rizqim). Zonani buyurtma yaratishga ulash bu yerda **emas**, P1-32 da | Sonnet | `Delivery`, migratsiyalar, Filament zona chizish | Geo testlar |
| P1-25 | **Hamkor web skeleti.** `partner/web` da Next.js: login, layout, menyu; dizayn tokenlari va komponentlar `packages/ts/ui` ga ajratiladi va mini-app ham shuni ishlatadi | Sonnet | `partner/web`, `packages/ts/ui`, mini-app importlari | Ikkala ilova build bo'ladi, mini-app testlari o'tadi |
| P1-26 | **Flutter skeleti.** Dart monorepo (melos yoki pub workspace); `rizqim_core`, `rizqim_ui`, `rizqim_api` (OpenAPI'dan Dart klient); namunaviy ilova; CI job | Sonnet | `packages/dart/`, `pos/`, `.github/` | `flutter analyze` va `flutter test` (Flutter SDK va pub.dev ruxsati kerak) |

**To'qnashuv xavfi va yechim:**
- P1-21 va P1-24 ikkalasi buyurtma yaratishga tegishi mumkin. Shuning uchun P1-24 faqat alohida servis yozadi, buyurtmaga ulash P1-32 ga qoladi.
- P1-25 mini-app importlarini o'zgartiradi, bu to'lqinda mini-app'da boshqa ish yo'q.
- P1-26 ishlash muhitida Flutter bo'lmasa, P1-26 to'lqin 3 ga suriladi, qolganlari kutmaydi.

**PR #4:** "P1 to'lqin 2: menyu va buyurtma modeli, push, tariflar, zonalar, skeletlar".

## To'lqin 3 — integratsiya, hujjatlar, review

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P1-31 | **Mini-app'ni yangi menyuga ulash:** modifikator tanlash, kilogrammli taom, "tugadi" belgisi, stop-list real-time | Sonnet | Unit va e2e testlar, yorug' va qorong'i mavzuda suratlar |
| P1-32 | **Zonalarni buyurtmaga ulash:** yetkazish narxi va minimal buyurtma zonadan olinadi, filial avtomatik tanlanadi | Opus | Pul testlari, mavjud buyurtma testlari |
| P1-33 | **Hujjatlar:** `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/POS_SPEC.md` (tanlangan format uchun ekranlar, ma'lumotlar modeli, internetsiz ishlash va sinxronlash qoidalari) | Opus | Siz POS_SPEC'ni tasdiqlaysiz |
| P1-34 | **Ikki mustaqil review** (backend va frontend/infra) va topilmalarni tuzatish | 2 × Opus + tuzatuvchi | Topilmalar yopilgan, CI yashil |
| P1-35 | **Staging'da tekshiruv:** image yig'ish, migratsiyalar, Reverb, push, Click/Payme sinov to'lovi | Men + siz (server kerak) | Tekshiruv ro'yxati to'liq |

**PR #5:** "P1 to'lqin 3: integratsiya, hujjatlar, review".

## Har bir ishning bajarilish tartibi

1. Men ish uchun agentga aniq topshiriq beraman: nima qilish kerak, qaysi fayllarga tegish mumkin, qaysilariga mumkin emas, qanday tekshiriladi.
2. Agent ishlaydi va hisobot beradi.
3. Men o'zim tekshiraman: backend testlari, PHPStan, frontend lint, testlar, build, e2e, kerak bo'lsa suratlar.
4. Commit xabarida ish kodi bo'ladi (masalan, `P1-12: …`). Push qilinadi.
5. To'lqin tugagach: PR, CI yashil, sizga qisqa hisobot.
6. Siz merge qilasiz, keyingi to'lqin boshlanadi.

Bajaruvchilar: **Opus** murakkab va pulga oid ishlarga (auth, menyu va buyurtma modeli, tariflar, review), **Sonnet** aniq va mexanik ishlarga (papkalar, infratuzilma, push, skeletlar, UI ulash).

## Sizdan kerak bo'ladiganlar

| Nima | Qachongacha | Kerak bo'lmasa nima bo'ladi |
|---|---|---|
| Ushbu rejani tasdiqlash yoki o'zgartirish | Qadam 0 dan oldin | Boshlanmaydi |
| Ishlash muhitida Flutter SDK va pub.dev'ga tarmoq ruxsati | To'lqin 2 | P1-26 keyinga suriladi |
| Firebase loyihasi (bepul) | To'lqin 2 | P1-22 kod tayyor bo'ladi, lekin push sinovdan o'tmaydi |
| Taxminiy tarif narxlari (obuna summasi, komissiya %) | To'lqin 2 | Standart qiymatlar qo'yiladi, keyin admin paneldan o'zgartiriladi |
| POS qaysi formatdan boshlansin (osh markazi yoki fast food) | To'lqin 3 | POS_SPEC yozilmaydi |
| Staging server va domen | To'lqin 3 | P1-35 keyinga qoladi |
| PR'larni ko'rib chiqish va merge | Har to'lqin oxirida | Keyingi to'lqin boshlanmaydi |

## Bosqich tugaganini qanday bilamiz

- Barcha testlar o'tadi, CI yashil, 4 ta PR merge qilingan.
- OpenAPI spetsifikatsiyasi va undan yaratilgan TypeScript va Dart klientlar bor.
- POS qurilmasini kod bilan ulash va PIN bilan kirish API orqali ishlaydi.
- Modifikator, kilogramm va qozon qoldig'i backend'da ishlaydi va mini-app'da ko'rinadi.
- Push xabar sinov qurilmasiga yetib boradi.
- Staging serverda ishlaydi.
- `docs/POS_SPEC.md` tayyor va tasdiqlangan.
