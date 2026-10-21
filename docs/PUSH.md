# Push xabarlar (Firebase Cloud Messaging)

Bu hujjat P1-22 ishini tushuntiradi: ilovalarga (mijoz, hamkor, kuryer) push xabar yuborish, xabarlarni Telegram, push va SMS
kanallari orasida taqsimlash va foydalanuvchi sozlamalari. Push **Telegram o'rniga emas, uning ustiga** qo'shiladi: foydalanuvchi
o'zi o'chirmagan bo'lsa, Telegram xabarlari avvalgidek ketadi.

> Holat: kod tayyor va Firebase javoblari soxtalashtirilgan testlar bilan tekshirilgan. Haqiqiy qurilmada sinov Firebase
> loyihasi yaratilgandan va ilova token yuborgandan keyingina mumkin (pastdagi 2 va 6-bo'limlar).

## 1. Qanday ishlaydi

```
buyurtma holati o'zgaradi / yangi buyurtma
        │
        ▼
NotificationRouter  ── foydalanuvchi sozlamasi (notification_preferences) ──┐
        │                                                                     │
        ├─ Telegram  → mavjud navbat (SendTelegramRequest), o'zgarmagan        │ kanal o'chirilgan
        ├─ Push      → har qurilma uchun SendPushNotification (navbatda)  ◄────┘ bo'lsa o'tkazib yuboriladi
        └─ SMS       → faqat "muhim" xabar va boshqa kanal yetkazmasa (hozir hech qaysi xabar ishlatmaydi)
```

- Push **yangi PHP paketsiz** yuboriladi: backend Firebase service account kaliti bilan JWT (RS256) imzolaydi, uni
  `https://oauth2.googleapis.com/token` da access tokenga almashtiradi (token keshda saqlanadi, muddati tugashiga yaqin
  yangilanadi) va `https://fcm.googleapis.com/v1/projects/{project}/messages:send` ga yuboradi.
- Push navbatda (`default` navbat, `queue` ishchisi) ketadi, shuning uchun buyurtma so'rovi Firebase'ni kutmaydi. Xato bo'lsa
  job 15, 60, 300 soniyadan keyin qayta uriniladi (jami 4 urinish), keyin log'ga `Push notification failed` yoziladi.
- Push yuborishdagi hech qanday xato buyurtma holatini o'zgartirishga, Telegram xabariga yoki API javobiga ta'sir qilmaydi.
- **Kalit yo'q bo'lsa push o'chiq:** hech narsa navbatga qo'yilmaydi, Firebase'ga so'rov ketmaydi, log'ga bir marta `info`
  yoziladi ("Push notifications are off…"). Xato yo'q.

## 2. Firebase loyihasi va service account

Loyihani Rizqim egasi yaratadi (bepul, Spark tarifi yetadi).

1. https://console.firebase.google.com ga Google hisobi bilan kiring, **Add project** → nom (masalan `rizqim`) → Google
   Analytics kerak emas.
2. **Project settings → Cloud Messaging** da **Firebase Cloud Messaging API (V1)** yoqilgan bo'lishi kerak (yangi
   loyihalarda yoqilgan; yoqilmagan bo'lsa "Manage API in Google Cloud Console" orqali *Enable*). Eski "Cloud Messaging API
   (Legacy)" kerak emas.
3. **Project settings → Service accounts → Generate new private key** → JSON fayl yuklab olinadi
   (`rizqim-firebase-adminsdk-….json`). Bu fayl parol bilan teng: gitga qo'shmang, chatga tashlamang. Service account
   rolini o'zgartirish shart emas (standart `Firebase Admin SDK` hisobi xabar yubora oladi).
4. Ilovalarni loyihaga qo'shing (Flutter ilovalar uchun, `flutterfire configure` buni o'zi qiladi):
   - Android: paket nomi (`applicationId`) bilan, `google-services.json` ilovaga qo'shiladi.
   - iOS: **Project settings → Cloud Messaging → Apple app configuration** ga Apple Developer'dan olingan **APNs
     Authentication Key** (.p8, Key ID, Team ID) yuklanadi. Busiz iOS'ga push bormaydi.
5. Yuqoridagi JSON faylni serverga bering (pastdagi 3-bo'lim).

## 3. Server sozlamalari

`backend/.env` (namuna `backend/.env.example` da):

| O'zgaruvchi | Ma'nosi |
|---|---|
| `FIREBASE_CREDENTIALS_JSON` | **Tavsiya etiladi.** Service account JSON faylning base64 ko'rinishi, bir qatorda: `base64 -w0 rizqim-firebase-adminsdk.json`. Docker uchun eng qulayi |
| `FIREBASE_CREDENTIALS` | Yoki JSON fayl yo'li. Faylni `backend/` papkasidan **tashqarida** saqlang (masalan `/etc/rizqim/firebase.json`, konteynerga volume bilan ulanadi): `backend/` ichidagi fayl Docker obraziga (`COPY . .`) tushishi mumkin. Nisbiy yo'l `backend/` dan boshlanadi. Ikkalasi berilsa `FIREBASE_CREDENTIALS_JSON` ustun |

Kalit fayli hech qachon gitga yoki Docker obraziga tushmasligi kerak. Ehtiyot chorasi sifatida `*firebase-adminsdk*.json`,
`*service-account*.json` va `backend/storage/firebase*.json` nomli fayllar `.gitignore` (ildiz va `backend/`) hamda
`backend/.dockerignore` da e'tiborsiz qoldiriladi, lekin boshqa nom bilan saqlangan fayl himoyalanmaydi.
| `FIREBASE_PROJECT_ID` | Odatda kerak emas: JSON ichidagi `project_id` ishlatiladi |
| `PUSH_ANDROID_CHANNEL_ID` | Android bildirishnoma kanali, standart `orders`. Ilova shu nomli kanal yaratishi shart |

O'zgartirgandan keyin konteynerlarni qayta yarating (`docker compose up -d --force-recreate backend queue queue-reports
scheduler`) va `queue` ishchisi ishlayotganini tekshiring. Production'da `config:cache` bo'lsa, `.env` o'zgarishi uchun
konteynerni qayta yaratish yetarli (entrypoint keshni qayta quradi).

## 4. Ilova tokenni qanday ro'yxatdan o'tkazadi (Flutter)

Token `devices.push_token` da saqlanadi. API token qiymatini **hech qachon qaytarmaydi**, qurilma haqida faqat
`has_push_token: true/false` ko'rinadi. Bitta token faqat bitta qurilmada turadi: boshqa odam shu telefonda kirsa, token
oldingi odamning qurilmasidan olinadi.

**Oqim**

1. Ilova ruxsat so'raydi (`FirebaseMessaging.instance.requestPermission()`; iOS va Android 13+ uchun majburiy) va tokenni oladi
   (`FirebaseMessaging.instance.getToken()`).
2. Kirishda tokenni qurilma bilan birga yuboradi. Ikki yo'l, ikkalasi ham bir xil natija beradi:
   - login so'rovida `device` obyekti: `{"type": "customer_mobile", "uuid": "<saqlangan uuid>", "name": "...", "platform": "android", "app_version": "1.0.0", "push_token": "<token>"}`
     (`POST /auth/telegram`, `POST /auth/phone/verify`, tafsilot `docs/AUTH.md`, 1-bo'lim);
   - yoki kirgandan keyin `POST /devices` shu maydonlar bilan.
   `type`: `customer_mobile` (mijoz), `partner_mobile` (hamkor: ega, menejer), `courier` (kuryer), `web`. Javobdagi
   `device.uuid` ni ilova saqlaydi va keyingi safar yuboradi.
3. Firebase tokenni yangilasa (`FirebaseMessaging.instance.onTokenRefresh`), ilova yangisini yuboradi:
   `PUT /devices/{uuid}/push-token` `{"push_token": "<yangi token>"}`.
4. Foydalanuvchi ilovada bildirishnomani o'chirsa yoki ruxsatni qaytarib olsa: `DELETE /devices/{uuid}/push-token`
   (yoki `PUT` ga `{"push_token": null}`).
5. Chiqishda (`POST /auth/logout`, `POST /auth/logout-all`, qurilmani o'chirish) server tokenni o'zi tozalaydi, ilovadan
   qo'shimcha so'rov shart emas.

Endpointlar faqat o'z qurilmasi uchun ishlaydi (boshqa odamning uuid'i uchun 404) va POS/KDS terminal tokeni bilan
chaqirilmaydi.

**Push tarkibi.** `notification` (`title`, `body`) tizim tomonidan ko'rsatiladi. `data` (barcha qiymatlar satr):

| Kalit | Qiymat |
|---|---|
| `type` | `order_updates`, `staff_new_order`, `courier_tasks` |
| `order_uuid` | Buyurtma uuid'i: bosilganda ilova shu buyurtma ekranini ochadi |
| `status` | Buyurtmaning joriy holati (`accepted`, `preparing`, `courier_assigned`, `on_the_way`, `delivered`, `canceled`…) |

Bitta buyurtma haqidagi yangi push eskisini almashtiradi (Android `collapse_key`/`tag`, iOS `apns-collapse-id` =
`order-<uuid>`). Ovoz: `default`. Android'da `orders` kanalini ilova o'zi yaratadi (yuqori muhimlik).

## 5. Qaysi xabar qachon ketadi va sozlamalar

| Tur (`type`) | Kimga | Qachon | Push qaysi qurilmaga |
|---|---|---|---|
| `order_updates` | Buyurtma egasi (mijoz) | Buyurtma holati har o'zgarganda (Telegram xabari ketadigan paytlarda), bekor qilish va qaytarish ham | barcha shaxsiy qurilmalar |
| `staff_new_order` | Buyurtmaga tegishli filial menejerlari va do'kon bo'yicha menejerlar; menejer bo'lmasa ega | Yangi buyurtma yaratilganda | `partner_mobile`, `courier`, `web` |
| `courier_tasks` | Kuryer (kuryer yo'q bo'lsa filial kuryerlari, ular ham yo'q bo'lsa menejer/ega) | Buyurtma kuryerga biriktirilganda va buyurtma tayyor bo'lganda | `partner_mobile`, `courier`, `web` |
| `promotions` | Mijoz | Hozircha hech narsa yubormaydi; kelajakdagi aksiyalar uchun tayyor | barcha shaxsiy qurilmalar |

**Standart qiymatlar.** Buyurtma va ish xabarlari Telegram va push uchun yoqilgan (Telegram avvalgidek). `promotions`
foydalanuvchi o'zi yoqmaguncha o'chiq.

**API** (kirgan foydalanuvchi, faqat o'zining sozlamalari):

- `GET /api/v1/me/notification-preferences` → roliga mos turlar, har birida kanallar:
  `{"type": "order_updates", "label": "...", "channels": [{"channel": "telegram", "enabled": true, "available": true}, {"channel": "push", "enabled": true, "available": false}]}`.
  `available` kanal foydalanuvchiga umuman yeta olishini bildiradi (Telegram ulangan, push token yuborilgan qurilma bor).
- `PUT /api/v1/me/notification-preferences` `{"preferences": {"order_updates": {"push": false}, "promotions": {"push": true}}}`.
  Faqat yuborilgani o'zgaradi. Mijozga `staff_new_order` kabi turlar ko'rinmaydi va qabul qilinmaydi (422).
  Kanallar: `telegram`, `push`.

**Telegram o'chirilsa.** Foydalanuvchi biror tur uchun Telegram'ni o'chirsa, o'sha tur Telegram orqali kelmaydi
(yangi buyurtma kartasi ham, mijoz holat xabari ham). Telegram'dagi tugmalar bilan boshqarish o'sha xabarlar bilan birga
ketadi, shuning uchun egalar va menejerlar uchun Telegram'ni o'chirish ixtiyoriy tanlov. Ilova ichidagi bildirishnomalar
ro'yxati (Mini App) baribir to'ladi: Telegram o'chirilgan bo'lsa ham xabar yozuvi ro'yxatga tushadi.

## 6. Sinash

**Yuborishdan oldin (Firebase'siz).** `php artisan push:test <foydalanuvchi_id> --dry-run` FCM xabarining qanday ko'rinishini
chiqaradi, hech narsa yubormaydi.

**Real qurilma bilan** (Firebase loyihasi va 3-bo'lim tayyor bo'lgach):

1. Ilovani ochib kiring, bildirishnomaga ruxsat bering. `GET /api/v1/devices` da qurilma `has_push_token: true` bo'lishi kerak.
2. Serverda: `docker compose exec -u www-data backend php artisan push:test <foydalanuvchi_id | telefon | qurilma_uuid>`
   (`--title=`, `--body=` ixtiyoriy). Buyruq foydalanuvchining barcha qurilmalariga (yoki bitta qurilmaga) sinov pushini
   to'g'ridan-to'g'ri yuboradi, navbat va foydalanuvchi sozlamalariga qaramaydi, natijani har qurilma uchun chiqaradi va tokenni
   to'liq ko'rsatmaydi.
3. Haqiqiy oqim: mijoz ilovasi bilan buyurtma bering, hamkor ilovasida menejer sifatida holatni o'zgartiring. Mijoz telefoniga
   push kelishi kerak. `queue` ishchisi ishlayotganini tekshiring (`docker compose logs -f queue`).

**Natijalar va xatolar.** `push:test` quyidagilarni chiqaradi: `yuborildi`, `disabled` (kalit sozlanmagan), `invalid_token`
(FCM tokenni o'lik deb topdi, qurilmadan avtomatik tozalandi: `UNREGISTERED`, `SENDER_ID_MISMATCH` yoki tokenga oid
`INVALID_ARGUMENT`), `retry` (FCM yoki tarmoq muammosi), `failed` (masalan `PERMISSION_DENIED`: service account loyiha bilan
mos emas yoki FCM API yoqilmagan).

**Avtomatik testlar.** `tests/Feature/Push*Test.php`, `NotificationRouterTest`, `NotificationPreferencesApiTest`,
`OrderPushFlowTest`: JWT imzosi (haqiqiy RSA kalit bilan tekshiriladi), token almashtirish va keshlash, xabar tuzilishi,
o'lik token tozalanishi, 5xx/429 qayta urinish, o'chiq rejim, sozlamalar, kanallar bo'yicha taqsimlash, do'konlar orasidagi
izolyatsiya va Firebase ishlamaganda buyurtma oqimi buzilmasligi. Hammasi `Http::fake` bilan; Google yoki Firebase'ga haqiqiy
so'rov yuborilmaydi.

## 7. Bilish kerak bo'lgan cheklovlar

- Buyurtmani kuryerga biriktirish Telegramga bog'liq emas (P1-36, `OrderStatusService::joined()`): Telegram taklifini qabul
  qilgan yoki ilovaga kirgan (o'chirilmagan shaxsiy qurilmasi bor) kuryer qo'lda ham, avtomatik ham biriktiriladi. Telegrami
  bor kuryer xabarni Telegramda, faqat ilovasi bor (o'z yoki Rizqim kuryeri) — push orqali oladi; push tokeni yo'q bo'lsa
  ham buyurtma biriktiriladi (ilova ochilganda ko'rinadi). Taklifning `temp_…` belgisiga Telegram xabari yuborilmaydi.
- Push matnlari qisqa va o'zbekcha (`App\Services\Push\OrderPushMessages`); boshqa tillar kerak bo'lsa shu klass kengaytiriladi.
- Foydalanuvchi uchun tilga qarab matn tanlash va rasmli push hozircha yo'q.
- Web push (brauzer) uchun `web` turidagi qurilma tokeni qabul qilinadi, lekin service worker va VAPID sozlamalari bu vazifada
  qilinmagan.
