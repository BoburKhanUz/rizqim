# Rizqim

O'zbekiston uchun ko'p do'konli (multi-store) ovqat buyurtma va yetkazib berish platformasi. Mijozlar buyurtmani Telegram Mini App orqali beradi; do'kon egalari, menejerlar va kuryerlar o'sha ilova, Telegram bot hamda Filament panellari orqali ishlaydi. Komissiya, hisob-kitob, yechib olish va qarz hisobi platformaning o'zida yuritiladi.

| Qism | Texnologiya | Hujjat |
|---|---|---|
| `backend/` | Laravel 12, PHP 8.3, PostgreSQL, Redis, Sanctum, Filament (`/admin`, `/superadmin`) | [backend/README.md](backend/README.md) |
| `mini-app/` | React 19, TypeScript, Vite 8, Tailwind 4, TanStack Query, zustand | [mini-app/README.md](mini-app/README.md) |
| Deploy | Docker Compose | [docs/DEPLOY.md](docs/DEPLOY.md) |

## Arxitektura

```
Telegram (mijoz, xodim)
   |  Mini App
   v
[frontend: nginx + React] --/api/*, /storage/*--> [backend: nginx + php-fpm, Laravel]
                                        /api/v1   /admin   /superadmin
                                           |          |
                                           v          v
                                    [PostgreSQL]  [Redis: kesh, navbat, sessiya]

Telegram bot --- webhook (secret_token) ---> backend: /api/v1/telegram/webhook
Click, Payme --- webhook (imzo / Basic) ---> backend: /api/v1/payments/{click,payme}/webhook
queue worker --- Bot API ------------------> Telegram    (barcha xabarlar navbat orqali)
scheduler    --- har daqiqa ---------------> to'lanmagan buyurtmalarni bekor qilish, kuryer kechikishi
backend      --- geokodlash proksisi ------> Nominatim   (Mini App unga o'zi murojaat qilmaydi)
```

`queue worker` va `scheduler` backend bilan bir kod bazasidan ishlaydi. Navbat ikki ishchiga bo'lingan: `default` (Telegram xabarlari, listenerlar) va `reports` (og'ir CSV hisobotlar, `SendOrderReport`), shunda hisobot xabarlar ortidan navbat kutib qolmaydi.

`docker-compose.yml` yettita xizmat ishga tushiradi: `postgres` (17), `redis` (7, AOF), `backend` (migratsiyalarni ham shu bajaradi), `queue` (`queue:work --queue=default,broadcasts`), `queue-reports` (`queue:work --queue=reports`), `scheduler` (`schedule:work`), `frontend` (Mini App statikasi hamda `/api/` va `/storage/` proksisi; `backend` manzilini Docker DNS orqali har so'rovda aniqlaydi). Admin panellar (`/admin`, `/superadmin`) backend portida (standart 8000), Mini App frontend portida (standart 8080) ochiladi.

## Rollar

| Rol | Doirasi | Nima qila oladi |
|---|---|---|
| `super_admin` | butun platforma | Do'kon yaratadi va moliyaviy sozlaydi (komissiya, kredit limiti, to'lov hisobi turi); to'lov tizimlari, promo-kodlar, SMS, hududlar, kategoriyalarni boshqaradi; yechib olish so'rovlarini hal qiladi; qo'lda balans to'ldiradi; shikoyatlarni ko'radi. Barcha do'konlar buyurtmalarida to'liq huquqli, yetkazilgan buyurtmani qaytara oladigan (`refunded`) yagona rol |
| `owner` | o'z do'koni | Katalog va zaxira, filiallar, xodimlar (taklif havolasi, filialga ko'chirish, o'chirish), barcha buyurtmalar, moliya (balans, yechib olish, top-up), sozlamalar (to'lov usullari, ish vaqti, yetkazish narxi, o'z merchant kalitlari), shikoyatlarga javob, hisobot (CSV Telegram'ga) |
| `manager` | o'z filiali (filial berilmagan bo'lsa butun do'kon) | Filial buyurtmalarini yuritadi, kuryer biriktiradi, filialda mahsulot mavjudligini yoqadi/o'chiradi, shikoyatlarga javob beradi, hisobot oladi |
| `courier` | o'ziga biriktirilgan buyurtmalar | Faqat o'z buyurtmasini ko'radi; yo'lga chiqadi va "yetkazildi" deb belgilaydi |
| `customer` | o'z hisobi | Do'kon va katalog, savat, buyurtma va to'lov, buyurtma kuzatuvi, manzillar (xaritada), shikoyat, do'konga baho, keshbek va referral havolasi |

- Kirish: Mini App Telegram `initData` orqali (HMAC tekshiriladi, `auth_date` 10 daqiqadan eski bo'lmasligi kerak) va Sanctum tokeni oladi; `/admin` (owner, manager, super_admin) hamda `/superadmin` (faqat super_admin) paneli email yoki telefon + parol bilan.
- Rol berish: mijoz Mini App'ga birinchi kirishda `customer` bo'ladi; xodimni egasi taklif havolasi (`invite_...`) bilan qo'shadi; istalgan rolni `php artisan user:assign-role` beradi (audit logga yoziladi). Oddiy kirish saqlangan rolni hech qachon o'zgartirmaydi.
- Do'koni yo'q `owner`/`manager`/`courier` hisoblari API'da 403 oladi (`staff.store` middleware) va panelga kira olmaydi.

## Buyurtma berish

`POST /api/v1/orders` (daqiqasiga 10 ta) `CreateOrderAction` ichida bitta tranzaksiyada bajariladi:

1. **Do'kon:** `payment_required` yoki `finance_hold` holatida, `debt_balance > |credit_limit|` bo'lganda yoki do'kon yopiq bo'lganda (`is_open` va ish vaqti) buyurtma rad etiladi.
2. **Usullar:** to'lov usuli, yetkazish (`delivery`) va olib ketish (`pickup`) do'kon sozlamalarida yoqilgan bo'lishi shart; `uzum` va `paynet` har doim rad etiladi; naqd uchun "naqd + olib ketish taqiqi" tekshiriladi.
3. **Filial:** `pickup` — tanlangan faol filial; `delivery` — manzil nuqtasini `radius_km` ichiga oladigan eng yaqin faol filial (Haversine), hech biriga tushmasa rad etiladi (koordinata yo'q bo'lsa birinchi faol filial).
4. **Narx va zaxira faqat serverda:** mijoz faqat `variant_id` (uuid) va `quantity` yuboradi. Variantlar `FOR UPDATE` bilan id tartibida qulflanadi va zaxira buyurtma yaratilishi bilan darhol kamaytiriladi (`stock >= quantity` sharti va bazadagi `CHECK (stock >= 0)`). Filialda o'chirilgan mahsulot, minimal buyurtma summasi tekshiriladi; yetkazish narxi `payment_settings.delivery_fee` dan olinadi (faqat `delivery`).
5. **Promo-kod (serverda):** mijoz faqat `promo_code` yuboradi; kod qulflanib tekshiriladi: faol va muddati o'tmagan, do'konga mosligi, `min_order_amount`, umumiy `usage_limit` va har bir mijoz uchun `per_user_limit`. Chegirma `percentage` (`max_discount_amount` bilan cheklanadi) yoki qat'iy summa bo'lib, mahsulotlar summasidan oshmaydi; yetkazish narxiga chegirma berilmaydi. `canceled`, `rejected`, `refunded` buyurtmalar limitga sanalmaydi. `POST /promo/validate` faqat oldindan ko'rish.
6. **Komissiya snapshot'i:** `commission_amount = total_price x commission_rate / 100` buyurtma yaratilganda saqlanadi va yetkazilganda aynan shu summa yechiladi.
7. **Keshbek:** `payment_method=keshbek` bo'lsa mijoz `balance` i qulflanib, `total_price` atomik yechiladi va to'lov darhol `paid` bo'ladi. Keshbek hozircha faqat referral mukofotidan to'planadi (pastga qarang).
8. Javob: `order_uuid`, `order_number` (`ORD-XXXXXX`), `status`, `payment_url` (Click/Payme havolasi; boshqa usullarda `null`). Commit'dan keyin do'kon admin chatiga va menejerlarga (menejer bo'lmasa egalarga) tugmali Telegram kartochkasi yuboriladi.

## To'lovlar

| Usul | Pul kimga tushadi | Tasdiqlanishi |
|---|---|---|
| `cash` | do'konga (`payment_owner=STORE`) | Panelda "Mark Cash Collected" bosilganda `paid`; olib ketish bo'yicha taqiq qoidasi pastda |
| `click`, `payme` | do'konga (`STORE`) yoki platformaga (`PLATFORM`), pastga qarang | Webhook; to'lov tushganda `new` buyurtma o'zi `accepted` bo'ladi |
| `keshbek` | `PLATFORM` | Buyurtma yaratilganda `paid`; mijozda `balance > 0` bo'lsagina ko'rsatiladi |
| `uzum`, `paynet` | — | O'chirilgan: `config/orders.php` dagi `unavailable_payment_methods`; panelda "Tez orada", API `{enabled: false, available: false}` qaytaradi |

**PLATFORM yoki STORE.** Click/Payme buyurtmasi `STORE` bo'ladi faqat `Store::collectsOwnPayments($provider)` rost bo'lsa: do'konning `payment_account_type = own_account` va shu provayder bo'limida barcha merchant ma'lumotlari to'ldirilgan (Click: `service_id`, `merchant_id`, `secret_key`; Payme: `merchant_id`, `secret_key`). Unda to'lov havolasi do'kon merchantiga quriladi va webhook do'konning o'z kaliti bilan tekshiriladi. Aks holda buyurtma `PLATFORM`: havola platforma merchantiga (Superadmin > To'lov Tizimlari, bo'lmasa `CLICK_*`/`PAYME_*` env) quriladi, yetkazilganda to'liq `total_price` do'kon hamyoniga o'tkaziladi. Do'kon kaliti platforma buyurtmasini, platforma kaliti do'kon buyurtmasini tasdiqlay olmaydi. Kalit sozlanmagan bo'lsa webhooklar rad etadi (fail closed). To'lov yetkazishdan keyin kelsa ham do'konga bir marta (idempotent) o'tkaziladi.

## Buyurtma holatlari

`app/Enums/OrderStatus.php` — yagona o'tish xaritasi. O'zgarish faqat `OrderStatusService::transition` orqali (qator qulfi ostida; xuddi shu holatni so'rash hech narsa qilmaydi).

| Joriy holat | `delivery` buyurtmada mumkin | `pickup` / `dine_in` da mumkin |
|---|---|---|
| `new` | `accepted`, `rejected`, `canceled` | xuddi shu |
| `accepted` | `preparing`, `canceled` | xuddi shu |
| `preparing` | `ready`, `courier_assigned`, `on_the_way`, `delivery`, `canceled` | `ready`, `delivered`, `canceled` |
| `ready` | `courier_assigned`, `on_the_way`, `delivery`, `canceled` | `delivered`, `canceled` |
| `courier_assigned` | `on_the_way`, `delivery`, `canceled` | — |
| `on_the_way`, `delivery` | `delivered`, `canceled` | — |
| `delivered` | `refunded` | `refunded` |
| `canceled`, `rejected`, `refunded` | — (yakuniy) | — |

`pickup` / `dine_in` buyurtmalar `courier_assigned`, `on_the_way`, `delivery` holatlariga o'tmaydi. `on_the_way` va `delivery` — bitta bosqichning ikki nomi. Kuryerga o'tishda kuryer ko'rsatilmasa eng kam yuklangan kuryer (avval shu filial, keyin umumiy, keyin boshqa filiallar) biriktiriladi; kuryer bo'lmasa egasi o'rnini bosadi.

**Kim nima qila oladi** (`OrderPolicy::changeStatus`):
- `super_admin` — jadval doirasidagi har qanday o'tish, jumladan `refunded` (faqat u).
- `owner` — o'z do'koni buyurtmalarida `refunded` dan boshqa hamma o'tish; `manager` — xuddi shu, lekin faqat o'z filiali (filial berilmagan bo'lsa butun do'kon).
- `courier` — faqat o'ziga biriktirilgan va `courier_assigned`/`on_the_way`/`delivery` dagi buyurtmani `on_the_way`, `delivery` yoki `delivered` ga o'tkazadi.
- `customer` holat o'zgartira olmaydi. Tizim (`null` aktor) o'zi bajaradi: to'lanmagan buyurtmani bekor qilish, Click/Payme to'lovi tushganda `accepted`, Payme to'lovni qaytarganda `refunded`.

## Bekor qilish, qaytarish, cheklovlar

- **Bekor qilish (`canceled`, `rejected`):** zaxira qaytariladi (`stock` oshadi), promo-kod ishlatilishi bo'shaydi, keshbek `balance` ga qaytadi (to'lov `refunded`), mijozga Telegram xabari ketadi. Click/Payme (yoki yig'ib olingan naqd) bilan to'langan pulni tizim qaytara olmaydi: to'lov `paid` bo'lib qoladi, `manual_refund_required` belgilanadi, tarix qaydi va ogohlantirish logi yoziladi, panelda "To'lov qaytarilmagan" ko'rsatiladi — mijozga qo'lda qaytariladi.
- **To'lanmagan onlayn buyurtma:** scheduler har daqiqada `new` holatda turgan, to'lanmagan onlayn buyurtmalarni yaratilganidan `ORDERS_UNPAID_ONLINE_TTL_MINUTES` (standart 30; `0` — o'chirilgan) daqiqa o'tgach avtomatik bekor qiladi (zaxira va promo-kod ishlatilishi qaytadi). Bekor qilingan buyurtmaga keyin kelgan to'lov webhookda rad etiladi.
- **Naqd + olib ketish taqiqi:** oxirgi `ORDERS_CASH_PICKUP_BAN_WINDOW_DAYS` (standart 30; `0` — muddatsiz) kun ichida kamida `ORDERS_CASH_PICKUP_BAN_THRESHOLD` (standart 1; `0` — o'chirilgan) ta naqd `pickup` buyurtma `canceled` bo'lgan bo'lsa (bekor qilishni buyurtma egasi qilgan yoki buyurtma `new` dan keyingi holatga o'tgan bo'lsa) mijoz uchun naqd to'lov yopiladi: `POST /orders` rad etadi, `GET /stores` naqdni o'chirilgan ko'rsatadi. `rejected` va `new` holatda do'kon bekor qilgan buyurtmalar sanalmaydi.
- **Yetkazilgan buyurtmani qaytarish:** faqat `super_admin`, do'kon panelidagi (`/admin/<do'kon>`; Superadmin > Restoranlar > "Login as Owner") buyurtmalar ro'yxatida "Qaytarish" tugmasi, sabab majburiy. Komissiya do'konga qaytariladi (qaysi hisobdan olingan bo'lsa shunga); platforma yig'ib o'tkazgan pul hamyondan qaytarib olinadi (hamyonda yetmagan qismi qarzga yoziladi); keshbek mijozga qaytadi; zaxira qaytmaydi; referral mukofoti qoladi. Click/Payme/naqd pulni mijozga qo'lda qaytarish kerak. Payme yetkazilgandan keyin to'lovni o'zi qaytarsa (`CancelTransaction`) buyurtma avtomatik `refunded` bo'ladi.

## Moliya

- **Komissiya** har bir `delivered` buyurtma uchun (naqd ham) yechiladi: avval do'kon hamyonidan (`wallet_balance`), yetmagan qismi `debt_balance` (do'kon platformadan qarzi) ga qo'shiladi. Standartlar: `commission_rate` 10%, `credit_limit` -100000, `min_payout_reserve` 100000.
- **Hisob-kitob (settlement):** `PLATFORM` buyurtma yetkazilganda to'langan summa hamyonga yoziladi. Keshbek buyurtmasida avval do'kon qarzi yopiladi, qoldig'i hamyonga o'tadi.
- **Bloklash:** komissiyadan keyin `debt_balance > |credit_limit|` bo'lsa `active` do'kon `payment_required` ga o'tadi, yangi buyurtma olmaydi va `/stores` ro'yxatida chiqmaydi, do'kon admin chatiga Telegram ogohlantirishi ketadi. `credit_limit = 0` — qarzga ruxsat yo'q: qarzi yo'q do'kon sotadi, har qanday qarz paydo bo'lsa bloklanadi (qoida `Store::exceedsCreditLimit()`). `finance_hold` ham buyurtmani to'xtatadi, lekin avtomatik o'zgarmaydi.
- **Top-up:** egasi Click/Payme orqali platformaga to'laydi (`POST /owner/finance/top-up`) yoki super admin qo'lda "To'lov Qabul Qilish" bajaradi; `debt_balance` kamayadi; do'kon `payment_required` holatida bo'lib, qarz limitdan pastga tushsa, yana `active` bo'ladi.
- **Yechib olish:** egasi `POST /owner/payouts` (16 xonali karta, kamida 1000) yuboradi; summa `wallet_balance` - kutilayotgan so'rovlar - `min_payout_reserve` dan oshmasligi kerak. Super admin (Superadmin > Moliya > Yechib Olish So'rovlari) so'rovni `completed` (hamyondan yechiladi) yoki `rejected` qiladi.
- **Referral:** taklif qilingan mijozning birinchi yetkazilgan, kamida 50 000 UZS li buyurtmasidan keyin taklif qilganga 5 000 keshbek beriladi; bu summa do'kon qarziga yoziladi.
- Barcha pul hisoblari `Money` (aniq o'nli arifmetika, 2 xona) va `LedgerService` orqali; har bir o'zgarish `store_transactions` (qarz) yoki `store_wallet_transactions` (hamyon) jurnaliga yoziladi.

## Telegram bot

- Webhook `POST /api/v1/telegram/webhook` faqat `X-Telegram-Bot-Api-Secret-Token` sarlavhasi `TELEGRAM_WEBHOOK_SECRET` ga teng bo'lsa ishlaydi; secret bo'sh bo'lsa hamma so'rovga 403. `php artisan telegram:set-webhook` webhookni shu secret bilan ro'yxatdan o'tkazadi (deploy buni o'zi bajaradi).
- `/start` deep-linklar (`https://t.me/<bot>?start=...`): `store_<do'kon uuid>` — chatni do'konga bog'laydi (faqat shu do'kon egasi yoki filialsiz menejeri); `invite_<token>` — xodim taklifi (bir martalik token); `rizqim_<user id>` (shuningdek `ref_`, `referrer_`) — referral: ro'yxatdan o'tmagan odam uchun 30 kun saqlanadi, `referred_by` faqat ro'yxatdan o'tishda bir marta o'rnatiladi, o'zini taklif qilib bo'lmaydi. Payloadsiz `/start` telefonsiz mijozdan o'z kontaktini so'raydi, so'ng rolga mos Mini App tugmasini yuboradi.
- Buyurtma kartochkalaridagi tugmalar bir martalik (24 soat): bosgan odam o'sha do'konning xodimi (yoki super admin) bo'lishi va `OrderPolicy` ruxsat berishi shart; "Rad etish" faqat `new` holatda.
- Mijozga har holat o'zgarishi haqida xabar boradi; kuryer 15 daqiqada yo'lga chiqmasa filial menejeriga ogohlantirish yuboriladi.
- Xabarlar `SendTelegramRequest` joblari bilan (qayta urinish, backoff) navbatdan ketadi: `queue` ishlamasa xabar yetmaydi; `TELEGRAM_BOT_TOKEN` yo'q bo'lsa hech narsa navbatga qo'yilmaydi.
- Lokal ishlab chiqishda webhook o'rniga `php artisan telegram:poll` (u botning webhookini o'chiradi — productionda ishlatmang).

## Geokodlash va xarita

- Mijoz manzilni xaritada belgilaydi (Leaflet, pin — haqiqat manbai), GPS bilan aniqlaydi yoki manzil qidiradi. Nuqta bo'yicha manzil va manzil bo'yicha nuqtani backend beradi: `GET /api/v1/geocode/reverse?lat=&lng=` va `GET /api/v1/geocode/search?q=` (kamida 3 belgi, 5 natija; kirish shart; daqiqasiga 30 ta).
- Provayder — Nominatim (`GEOCODING_PROVIDER`). Server uning siyosatiga amal qiladi: aniqlovchi `User-Agent`, butun platforma bo'yicha 1 so'rov/s (`NOMINATIM_MIN_INTERVAL`), natijalar keshlanadi (`GEOCODING_CACHE_TTL`, kamida 1 kun). Provayder javob bermasa 503 qaytadi va Mini App manzilni qo'lda kiritishni taklif qiladi. Productionda o'z (yoki shartnomali) Nominatim manzilini (`NOMINATIM_BASE_URL`) va tile serverini (`VITE_MAP_TILE_URL`) bering: ochiq OSM serverlari faqat ishlab chiqish va kam trafik uchun.

## Tezkor boshlash

- Production-ga o'xshash ishga tushirish: `cp .env.example .env`, `cp backend/.env.example backend/.env`, ikkalasini to'ldiring (`APP_KEY` va `https://` li `TELEGRAM_WEBAPP_URL` ni oldindan yozing), `docker compose up -d --build` — batafsil [docs/DEPLOY.md](docs/DEPLOY.md). Konteynerda artisan buyruqlari: `docker compose exec -u www-data backend php artisan ...`.
- Lokal ishlab chiqish (Docker'siz): [backend/README.md](backend/README.md) va [mini-app/README.md](mini-app/README.md). Docker konteynerlari `APP_ENV=production` bo'lmasa ishga tushmaydi.
- Sifat nazorati (`.github/workflows/ci.yml`): backend testlari (PostgreSQL, `php artisan test`), PHPStan/Larastan (level 5; hozircha ogohlantiruvchi job), Mini App (`eslint`, `vitest`, `build`).
