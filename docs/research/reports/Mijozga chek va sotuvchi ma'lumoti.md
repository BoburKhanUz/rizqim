# Mijozga chek va sotuvchi ma'lumoti

**Xulosa.** Yandex Eats (Yandex Go, O'zbekiston), Express24 va Rossiyadagi Yandex Eda bir xil modelda ishlaydi. Sotuvchi restoran ("Hamkor"), platforma esa shartnoma tarafi emas. Chek PDF qilib yuborilmaydi. O'zbekistonda chek ilovadagi "Buyurtmalar tarixi"da beriladi, Rossiyada esa e-pochtaga yuboriladi. Restoranning yuridik ma'lumoti restoran sahifasida ko'rsatiladi. Platformaning o'z servis yig'imi uchun ham chek beriladi. Qonun ham shuni talab qiladi. ZRU-792 23-modda 3-qism: elektron to'lovda elektron chek xaridorning telefoniga, e-pochtasiga yoki **shaxsiy kabinetiga** yuboriladi. VM 943 1a-ilova 8-band: chekning **havolasi** yuboriladi. Iste'molchilar qonuni 28-1-modda: sotuvchi nomi, manzili va shikoyat tartibi **shartnomadan oldin** ko'rsatiladi. RIZQIM'da cheklar allaqachon bor: Payme, Click va Paylov restoran STIRi komitent bo'lgan chekni ro'yxatdan o'tkazadi, kassa (POS) o'z chekini chiqaradi, hammasi `fiscal_receipts.qr_url` da saqlanadi. Lekin mijoz ularni hech qayerda ko'rmaydi. **Tavsiya:**

1. Mini App'dagi buyurtma sahifasi va tarixida "Fiskal chek" tugmasi bo'lsin. U ofd.soliq.uz havolasini ochadi, sotuv va qaytarish cheklarini ko'rsatadi. Bu "shaxsiy kabinet" talabini bajaradi.
2. Chek ro'yxatga olinganda Telegram bot havolani tugma bilan yuborsin.
3. Restoran sahifasida va to'lovdan oldin "Sotuvchi" bloki chiqsin: yuridik nomi, STIR, yuridik manzil, telefon, shikoyat tartibi.
4. PDF birinchi bosqichda kerak emas. Keyin qilinsa, "fiskal chek emas" deb belgilangan buyurtma hisobi bo'lsin, ichida OFD QR-kodi tursin.
5. E-pochta ikkinchi bosqichda.

## Belgilar va cheklovlar

Belgilar oldingi hisobotlardagidek. **[BIRLAMCHI]** degani manbaning o'zini o'qiganman: lex.uz, platformaning o'z shartnomasi yoki yordam sahifasi, to'lov tizimining hujjati yoki RIZQIM kodi. Platforma sahifasi uning o'zi nima deyishini ko'rsatadi, amalda shundayligini kafolatlamaydi. **[MEDIA]** degani faqat gazeta, buxgalteriya sayti yoki qidiruv xulosasi aytgan. **[TOPILMADI]** degani men ko'ra olmadim, "yo'q" degani emas. Hammasi 23.11.2026 da tekshirilgan.

Cheklov: ilovalarning o'zini ochib ko'ra olmadim (haqiqiy buyurtma va chek yo'q). Shuning uchun "ekranda nima ko'rinadi" degan savollarga shartnoma va yordam sahifalari orqali javob berilgan. Haqiqiy Yandex yoki Uzum chekini ko'rish uchun bitta test buyurtma kerak (pastda, savollar ro'yxatida).

## 1. Qonun nima talab qiladi

| Talab | Manba | Belgi |
|---|---|---|
| Elektron to'lov vositasi bilan to'lansa, virtual kassaning elektron cheki xaridorga yuboriladi: telefon raqamiga, e-pochtaga yoki shaxsiy kabinetga. 4-qism: e-tijoratda yetkazib berish xizmati uchinchi shaxslar (sotuvchilar) uchun pul qabul qilishi mumkin | ZRU-792 "Elektron tijorat to'g'risida", 23-modda 3, 4-qismlar ([lex.uz](https://lex.uz/ru/docs/6213428)) | [BIRLAMCHI] |
| Xo'jalik yurituvchi subyektlarga (biznesga) sotuvda elektron hisob-faktura rasmiylashtiriladi | ZRU-792, 23-modda 1, 2-qismlar (o'sha) | [BIRLAMCHI] |
| 23-moddaga **12.12.2026 dan kuchga kiradigan** o'zgartishlar bor (4-qismdan keyin). Ularning matnini ochib o'qiy olmadim | lex.uz dagi "Смотрите на изменения" belgisi | [BIRLAMCHI, matn TOPILMADI] |
| Marketpleys elektron fiskal chekning **havolasini** xaridor ko'rsatgan abonent raqamiga, e-pochtasiga yoki shaxsiy kabinetiga yuborilishini ta'minlaydi. Chek ma'lumotlari kamida 5 yil saqlanadi | VM 943, 1a-ilova, 8 va 12-bandlar ([lex.uz](https://lex.uz/ru/docs/-4603329)) | [BIRLAMCHI] |
| Chekda "Komitent (vositachi) STIRi/(JShShIRi)" bo'ladi. Yetkazib berishda manzil sifatida **yetkazib beruvchi subyektning yuridik manzili** yoziladi | VM 943, 1-ilova, 7-band (o'sha) | [BIRLAMCHI] |
| Chekni to'liq shakllantirib xaridorga berish uchun javobgarlik tadbirkorda (restoranda) | VM 943, 1-ilova, 4-band (repo eslatmasi `research_notes/POS va agregator integratsiyalari/fiscal_legal.md`, 25.10.2026) | [BIRLAMCHI, bugun qayta o'qilmadi] |
| Axborot tizimi orqali savdoda shartnomadan oldin quyidagilar ko'rsatiladi: sotuvchining nomi, manzili, shikoyat berish tartibi, narx, yetkazish narxi, to'lov shartlari, litsenziya QR-kodi (bo'lsa) | Iste'molchilar huquqlarini himoya qilish to'g'risidagi qonun, 28-1-modda (ZRU-746, 19.04.2022 dan) ([lex.uz](https://lex.uz/ru/docs/14643)) | [BIRLAMCHI] |
| Sotuvchi firma nomi, joylashgan joyi (yuridik manzili) va ish vaqtini ma'lum qiladi. Har bir sotuvda kassa yoki tovar cheki beriladi, cheksiz sotish taqiqlangan | O'sha qonun, 5 va 10-moddalar | [BIRLAMCHI] |
| Taklif (oferta) yuborgan shaxsning nomi, pochta va elektron manzili, telefoni ko'rsatiladi | ZRU-792, 16-modda | [BIRLAMCHI] |
| Soliq qo'mitasi 11.08.2026 dan elektron chekni "soddalashtirilgan" ko'rinishda ham ko'rsatadi (nomi, summasi, jami). Chekni saqlash va boshqalarga ulashish mumkin. PDF tilga olinmagan | [spot.uz, 11.08.2026](https://www.spot.uz/oz/2026/08/11/fiscal-receipt/) | [MEDIA] |

**Oddiy tilda:** qonun PDF talab qilmaydi. U ofd.soliq.uz dagi chekka **havola** yuborishni talab qiladi, va Mini App'dagi buyurtma tarixi "shaxsiy kabinet" bo'la oladi. Telegram bot qonunda alohida yozilmagan, lekin bot mijozning telefon raqamiga bog'langan kanal. Uni havolani yetkazishning qo'shimcha yo'li deb hisoblash mumkin, lekin yagona yo'l qilmaslik kerak (bu mening o'qishim) [YURIST].

## 2. Agregatorlar qanday qiladi

| Savol | Yandex Eats / Yandex Go (O'zbekiston) | Express24 (endi Yandex) | Uzum Tezkor | Wolt (O'zbekiston, yopilgan) | Yandex Eda (Rossiya) |
|---|---|---|---|---|---|
| Kim sotuvchi | Hamkor (restoran). Yandex shartnoma tarafi emas (2.9, 3.2, 3.5) [BIRLAMCHI] | Hamkor. Express24 tarafi emas (2.9, 3.5) [BIRLAMCHI] | [TOPILMADI]. Uzum Market sotuvchilar bilan "vositachilik" ofertasida ishlaydi, Tezkorga tegishliligi tasdiqlanmagan (repo eslatmasi) | Hamkor. Yetkazib berish shartnomasi Wolt bilan bo'lishi mumkin (3.3) [BIRLAMCHI] | Restoran. Yandex to'lovni "restoran agenti sifatida" oladi (4.1.3) [BIRLAMCHI] |
| Chek qayerda | "Чек/чеки ... при оплате Товара, Доставки и сервисного сбора", qonun talab qilgan hollarda. Buyurtmalar "Мои Заказы" bo'limida (3.7, 5.3) [BIRLAMCHI] | Chek profildagi **"Buyurtmalar tarixi"** bo'limida (5.3) [BIRLAMCHI] | [TOPILMADI] | Wolt chekni **Hamkor nomidan** beradi, qonun Hamkorning o'zini talab qilsa Hamkor beradi (3.3) [BIRLAMCHI] | Kassa cheki **ro'yxatdan o'tishda ko'rsatilgan e-pochtaga** yuboriladi. E-pochta yoki telefonni ilovada o'zgartirish mumkin (4.15) [BIRLAMCHI] |
| Kim chekni urib chiqaradi | Shartnomada aytilmagan. Yandex Go 2023-yil dekabrdan karta to'lovlarida chekni ilova orqali bera boshlagan (taksi) [MEDIA, kun.uz 02.12.2023]. Eats uchun [TOPILMADI] | Aytilmagan | [TOPILMADI] | Wolt (Hamkor nomidan) | Onlayn to'lovda "фискальный чек пробивает Яндекс Еда", restoran faqat pre-chek qiladi [BIRLAMCHI, vendor yordam sahifasi] |
| PDF | Yo'q, ilova ichida [BIRLAMCHI, shartnomada PDF tilga olinmagan] | Yo'q | [TOPILMADI] | [TOPILMADI] | Yo'q, e-pochta |
| Servis yig'imi | 5%, kamida 4 990, ko'pi bilan 7 990 so'm (2.6). Chek "сервисного сбора" uchun ham beriladi (5.3) [BIRLAMCHI] | Algoritm bilan belgilanadi, buyurtmada ko'rsatiladi (2.6) [BIRLAMCHI] | [TOPILMADI] | Yetkazish va servis yig'imi bor (3.4), chekdagi bo'linishi aytilmagan | Shartnomada alohida chek tilga olinmagan [TOPILMADI] |
| Sotuvchi ma'lumoti qayerda | "На странице соответствующего Партнера" (6.8). Yandex'ning o'z rekvizitlari: ИП ООО «Y EATS», STIR 310150022, Toshkent (6.10) [BIRLAMCHI] | Hamkor sahifasida (6.7). Operator: Y EATS MChJ XK, STIR 310150022 [BIRLAMCHI] | [TOPILMADI] | Hamkor ma'lumoti va aloqa servisda (2, 4.7) | Restoran sahifasida (8.4) [BIRLAMCHI] |
| Qaytarish | Pul kartaga qaytadi, 14 kun ichida da'vo (5.8). Yandex buyurtmani bekor qilishi mumkin (3.21). Qaytarish cheki tilga olinmagan [TOPILMADI] | Vakolatli to'lov operatori orqali (5.8) [BIRLAMCHI]. Qaytarish cheki [TOPILMADI] | [TOPILMADI] | 14 kun ichida, o'sha to'lov usuliga (4.5, 4.6) | Kartaga yoki promokod (4.16). Qaytarish cheki tilga olinmagan |
| Biznes uchun hujjat | "Yandex Go biznes uchun" ofertasi: oylik akt va elektron hisob-faktura, 10 kun ichida (6.1). Lekin u taksi va kuryer uchun, ovqat yetkazishni qamramaydi [BIRLAMCHI] | [TOPILMADI] | Hamkorlar hujjatni Didox'da oladi (repo eslatmasi) [BIRLAMCHI] | Wolt for Work hisobi tilga olingan | Restoranga Yandex xizmatlari uchun oylik hujjat e-pochtaga keladi [BIRLAMCHI] |

Manbalar:
- [yandex.uz/legal/termsofuse_eda](https://yandex.uz/legal/termsofuse_eda/ru/), 18.11.2026 tahriri
- [yandex.uz/legal/express24_tos](https://yandex.uz/legal/express24_tos/), 07.08.2025
- [explore.wolt.com/en/uzb/terms](https://explore.wolt.com/en/uzb/terms), 30.06.2025. Wolt 05.03.2026 dan O'zbekistonda buyurtma qabul qilmaydi ([e'lon](https://explore.wolt.com/en/uzb/announcement)) [BIRLAMCHI]
- [yandex.ru/legal/termsofuse_eda](https://yandex.ru/legal/termsofuse_eda/), 12.11.2026
- [Yandex Eda vendor yordami, buxgalteriya](https://yandex.com/support/eda-vendor-ru/about-finance.html)
- [yandex.com/legal/clients_b2b_uz](https://yandex.com/legal/clients_b2b_uz), 03.07.2026
- Delivery Club 2023-yilda "Маркет Деливери" bo'ldi va Yandex platformasiga o'tdi, alohida chek modeli yo'q [MEDIA]

**Rossiya qoidasi qiyoslash uchun.** Agent chekida har bir pozitsiyada "данные поставщика" (teg 1224) va "ИНН поставщика" (teg 1226) bo'ladi. Ular bo'lmasa, tushum agentniki hisoblanadi ([ФНС, 11.07.2025, АБ-4-20/6609@](https://www.consultant.ru/document/cons_doc_LAW_511626/)) [BIRLAMCHI, Konsultant matni]. O'zbekistondagi "Komitent STIRi" maydoni xuddi shu vazifani bajaradi. RIZQIM uni allaqachon to'ldiradi (3-bo'lim).

**Umumiy naqsh:**
1. Restoran sotuvchi bo'lib qoladi.
2. Chek ilovaning buyurtma tarixida (O'zbekiston) yoki e-pochtada (Rossiya) beriladi.
3. Restoran rekvizitlari restoran sahifasida turadi.
4. Platformaning o'z yig'imi alohida chek bilan beriladi.
5. PDF hech kimda topilmadi.
6. Qaytarish chekini mijozga qanday ko'rsatishni hech kim ochiq yozmagan.

## 3. RIZQIM'da nima bor (kod, 23.11.2026, `p-receipts-research` shoxi)

| Narsa | Qayerda | Holat |
|---|---|---|
| Barcha cheklar bitta jadvalda: tur (sale/refund/advance/credit), status, `qr_url`, `fiscal_sign`, `receipt_number`, `terminal_id`, `provider`, `subject_type`/`subject_id`, `original_receipt_id` | `backend/app/Models/FiscalReceipt.php`, `2026_10_25_100200_create_fiscal_receipts_table.php` | Bor [BIRLAMCHI] |
| Payme: `SetFiscalData` PERFORM (sotuv) va CANCEL (qaytarish) `payload.fiscal.perform_data/cancel_data` ga va `fiscal_receipts` ga yoziladi. `qr_code_url` saqlanadi | `PaymeWebhookController::setFiscalData` | Sotuv va qaytarish bor [BIRLAMCHI] |
| Click: `ofd_data/submit_items`, keyin `ofd_data/{service}/{payment}` dan `qrCodeURL` o'qiladi | `Jobs/SubmitClickFiscalData.php`, `ClickMerchantApi.php` | Sotuv bor. **Qaytarish cheki yo'q**: kodda Click qaytarish chekini so'raydigan joy topilmadi [BIRLAMCHI, kod] |
| Paylov: `fiscalization/register`, qaytarishda `fiscalization/status?refunded=true` yoki `fiscalization/refund`. `receiptUrl` saqlanadi | `RegisterPaylovFiscalReceipt.php`, `RefundPaylovFiscalReceipt.php` | Sotuv va to'liq qaytarish bor [BIRLAMCHI] |
| Onlayn chek restoran STIRi bilan (komitent): avval `stores.tin`, bo'lmasa `stores.tax_number`. Yetkazish Click'da alohida qator, Payme'da `shipping` | `OnlineFiscalReceiptBuilder` | Bor. STIR yo'q bo'lsa chek `blocked` (`store_tin_missing`) [BIRLAMCHI] |
| Kassa (POS) cheki buyurtma hisobidan (`bill.order_id`) | `Domain/Pos/Billing/BillFiscalizer.php` | Bor, buyurtmaga hisob orqali bog'lanadi |
| Chekni kim chiqargani: `orders.fiscalized_by` (venue_pos, payment_provider, aggregator, rizqim_marketplace) | `Domain/Integrations/FiscalizedBy.php` | Ustun bor. Izohda "Nothing sets or reads it before phase 5" deyilgan |
| Chek havolasini tashqariga berishda xost tekshiruvi (faqat `https://ofd.soliq.uz`) | `config/qr_payments.php` (`UZQR_RECEIPT_HOSTS`), `SendUzQrReceiptLink` | Bor. Mini App uchun ham shu ro'yxatni ishlatish mumkin |
| Chek o'zgarsa real vaqt hodisasi | `Events/Broadcast/FiscalReceiptUpdatedBroadcast.php` | Bor (xodimlar kanali) |
| Restoran rekvizitlari: `legal_name`, `tax_number`, `bank_account` (2026-06), `tin`, `tax_regime` (P3-11), `address` (brend manzili), `phone` | `stores` jadvali | `legal_name` va `tax_number` `INTERNAL_ATTRIBUTES` da, ya'ni ommaviy API'da ko'rinmaydi. `legal_name` faqat admin va ariza orqali kiritiladi. Egasi `tin` va `address` ni o'zgartira oladi (`OwnerSettingsController`). **Yuridik manzil** uchun alohida ustun yo'q |
| Mini App: buyurtmada chek yoki sotuvchi ma'lumoti | `customer/miniapp/src` | **Yo'q**. Mijozning buyurtmalar ro'yxati faqat `items` ni yuklaydi (`OrderQueryService`), do'kon rekvizitlari ham, cheklar ham kelmaydi |
| Telegram bot mijozga | `TelegramNotificationService::sendOrderStatusChanged` | Faqat holat xabarlari. Chek havolasi yo'q |
| Mijoz e-pochtasi | `users.email` | Faqat adminlar uchun ishlatiladi. Mijozdan e-pochta so'ralmaydi |
| Shikoyat | `Order/ComplaintController` (mijoz shikoyat yuboradi, egasi javob beradi) | Bor. "Shikoyat tartibi" uchun shunga havola berish mumkin |
| Mijozdan platforma yig'imi | `service_charge_amount`, `place_fee_amount`, `packaging_amount` restoranniki va restoran chekiga kiradi. RIZQIM'ning mijozdan oladigan yig'imi yo'q | RIZQIM uchun alohida mijoz cheki hozir kerak emas |
| Huquqiy eslatma | `docs/legal/README.md` (`p-legal` shoxi, PR #48): "Mini App hozir chek havolasini ko'rsatmaydi... qo'shish tavsiya etiladi"; "yuridik nomi va shikoyat tartibi ko'rsatilmaydi" | Bu ish shu ikki bandni yopadi |

## 4. RIZQIM uchun tavsiya etilgan dizayn

### 4.1 Ma'lumot (backend)

1. **Sotuvchi rekvizitlari.**
   - `stores` ga `legal_address` qo'shiladi. VM 943 7-band yetkazishda chekka yuridik manzilni talab qiladi, iste'molchilar qonuni 5-modda esa joylashgan joyni.
   - `legal_name` va STIR (`tin`, bo'lmasa `tax_number`) bor.
   - Ixtiyoriy `complaint_contact` (telefon yoki Telegram) qo'shiladi.
   - Egasi bularni hamkor panelida to'ldiradi. O'zgarish audit jurnaliga yoziladi.
   - `legal_name` ariza bosqichida tekshiriladi (`StoreApplicationStage` da "Yuridik nom tasdiqlandi" bandi bor), shuning uchun keyingi o'zgarishni admin tasdiqlashi mumkin.
2. **Buyurtmadagi nusxa.** Buyurtma yaratilganda `orders.seller_snapshot` (jsonb) ga yoziladi: `legal_name`, `tin`, `legal_address`, `phone`. Tarixda o'sha paytdagi sotuvchi ko'rinadi, restoran keyin nomini o'zgartirsa ham.
3. **Mijoz API.** Buyurtma tafsilotida ikki maydon qaytariladi:
   - `seller {legal_name, tin, legal_address, phone, complaint}`;
   - `receipts [{type: sale|refund, status, provider, qr_url, issued_at}]`. Ular `fiscal_receipts` dan olinadi: `subject_type=order` yoki buyurtmaning hisoblari (`bill.order_id`). Faqat `non_fiscal=false`.
   - `qr_url` faqat ruxsat etilgan xostdan (ofd.soliq.uz, `https`) bo'lsa qaytariladi. `provider_payload` hech qachon chiqmaydi.
   - `pending` chek "tayyorlanmoqda" bo'lib ko'rinadi. `blocked` yoki `failed` mijozga "chek restorandan so'raladi" bo'lib ko'rinadi, ichki sabab ko'rsatilmaydi.

### 4.2 Mini App

1. **Restoran sahifasi va to'lovdan oldin** (28-1-modda "shartnomadan oldin" deydi) qisqa "Sotuvchi" bloki chiqadi: yuridik nomi, STIR, manzil, telefon, "Shikoyat: ilovadagi Shikoyat bo'limi yoki telefon". RIZQIM esa "savdo maydonchasi operatori" deb ko'rsatiladi, rekvizitlari landing sahifadan olinadi.
2. **Buyurtma sahifasi va tarixi**:
   - "Fiskal chek" tugmasi `Telegram.WebApp.openLink` bilan ofd.soliq.uz ni ochadi.
   - Qaytarish bo'lsa, "Qaytarish cheki" tugmasi ham chiqadi.
   - Naqd yoki kassada to'langan buyurtmada chek hali yo'q bo'lsa, "Chekni restoran kassasi beradi" yozuvi chiqadi.
   - Xuddi shu blokda sotuvchi nusxasi (`seller_snapshot`) ko'rsatiladi.
3. Shu bilan "shaxsiy kabinet" talabi bajariladi: Yandex va Express24 ham chekni "Buyurtmalar tarixi"da beradi.

### 4.3 Telegram bot

1. Chek ro'yxatga olinganda (`FiscalIssuer` yozuvi `issued` bo'lganda, sotuv yoki qaytarish) mijozga bitta xabar boradi: "Buyurtma #... fiskal cheki tayyor", "Chekni ochish" URL tugmasi va "Buyurtmani ochish" (Mini App) tugmasi bilan.
2. Bitta chek uchun faqat bitta xabar boradi. Navbatda ishlaydi, mavjud `notification_logs` orqali yoziladi.
3. Mijozning "buyurtma xabarlari" sozlamasi hurmat qilinadi. Qonuniy yetkazish baribir Mini App tarixi orqali ta'minlanadi.

### 4.4 PDF va e-pochta

- **PDF birinchi bosqichda yo'q.** Qonuniy chek OFD dagi hujjat. RIZQIM yasagan PDF fiskal chek emas, uni chek deb ko'rsatish mijozni chalg'itadi. Agregatorlarning birortasi PDF bermaydi.
- Egasi "PDF" deganini bajarish uchun ikkinchi bosqichda **"Buyurtma hisobi" PDF** qilish mumkin. U botga `sendDocument` bilan yuboriladi va quyidagilardan iborat:
  - sotuvchi rekvizitlari, qatorlar, yetkazish, jami;
  - ofd.soliq.uz havolasining QR-kodi;
  - "Bu hujjat fiskal chek emas. Fiskal chek QR-kod orqali" degan yozuv.
- **E-pochta ikkinchi bosqichda.** Uning uchun kerak bo'ladi: mijozdan ixtiyoriy e-pochta maydoni, ma'lumot olishga rozilik (O'RQ-547, maxfiylik siyosatiga qo'shiladi) va pochta yuborish infratuzilmasi. Rossiyadagi Yandex modeli shunday.

### 4.5 Qaytarish, kassa va boshqa holatlar

- **Click qaytarish cheki** hozir yo'q. Click qaytarishida qaytarish cheki qanday olinishini Click'dan so'rash va qo'shish kerak. Hozircha Payme va Paylov qaytarish cheklari ko'rsatiladi.
- **Naqd yoki kassa:** chekni restoran POS'i chiqaradi (`BillFiscalizer`). U hisob orqali buyurtmaga bog'langan bo'lsa, Mini App'da ko'rinadi. Restoran boshqa kassada chek chiqarsa, RIZQIM'da u bo'lmaydi va mijozga "chek restoranda" deb ko'rsatiladi.
- **`orders.fiscalized_by`** shu ish bilan to'ldirila boshlansin: payment_provider yoki venue_pos. Shunda Mini App qaysi xabarni ko'rsatishni biladi, ikki marta chek chiqishiga ham yo'l qo'yilmaydi.
- **Platforma yig'imi.** RIZQIM mijozdan yig'im olmaydi, demak mijozga RIZQIM nomidan chek kerak emas. Kelajakda servis yig'imi qo'shilsa, u **RIZQIM STIRi bilan alohida chek** bo'ladi (Yandex Eats 5.3 kabi). Restoranning komitent chekiga kirmaydi.
- **RIZQIM kuryeri** (`delivery_courier = fleet`): hozir yetkazish puli restoran chekiga (komitent restoran) kiradi. Yetkazishni RIZQIM bajarsa, yetkazish xizmatining sotuvchisi kim bo'lishi va chekdagi manzil qaysi bo'lishi yurist savoli.
- **Biznes mijozlar:** ZRU-792 23-moddasi biznesga sotuvda elektron hisob-fakturani talab qiladi. Bu birinchi bosqichga kirmaydi. Keyinroq "Yuridik shaxs sifatida buyurtma" (STIR kiritish) qilinadi va restoran hisob-fakturani Didox orqali chiqaradi.

## 5. Ochiq savollar

1. 12.12.2026 dan ZRU-792 23-moddasiga kiradigan o'zgartishlarning matni (lex.uz da havola bor, matn ochilmadi). Ishni qurishdan oldin o'qish kerak.
2. Telegram bot xabari "abonent raqamiga yuborish" hisoblanadimi, yoki faqat SMS shunday hisoblanadimi [YURIST]. Mini App tarixi "shaxsiy kabinet" bo'lgani uchun xavf past.
3. Payme, Click va Paylov o'z ilovasida yoki SMS orqali to'lovchiga chek havolasini yuboradimi [TOPILMADI]. Agar yuborsa ham, RIZQIM'ning o'zida ko'rsatish yaxshi.
4. Click qaytarish chekini qanday so'rash kerak (Click hujjat sahifasi bugun ochilmadi) [TOPILMADI].
5. Yandex Eats O'zbekiston chekida qaysi STIR turadi va servis yig'imi alohida chekmi. Bitta test buyurtma bilan tekshirish kerak [TOPILMADI].
6. Uzum Tezkor mijoz shartnomasi va cheklari [TOPILMADI].
7. RIZQIM kuryeri yetkazganda yetkazish xizmatining sotuvchisi kim [YURIST].

## 6. Ikkinchi bosqich uchun qisqa reja (nazoratchi hal qiladi)

1. Backend:
   - `stores.legal_address` va `complaint_contact`;
   - `orders.seller_snapshot`;
   - mijoz buyurtmasida `seller` va `receipts`;
   - `fiscalized_by` ni to'ldirish;
   - testlar.
2. Hamkor panelida "Yuridik ma'lumotlar" bo'limi: yuridik nomi, STIR, yuridik manzil, shikoyat aloqasi.
3. Mini App:
   - restoran sahifasi va to'lovdan oldin "Sotuvchi" bloki;
   - buyurtma va tarixda "Fiskal chek" va "Qaytarish cheki" tugmalari, uz/ru.
4. Telegram bot: chek tayyor bo'lganda havola tugmasi bilan xabar.
5. Keyinroq: Click qaytarish cheki, "Buyurtma hisobi" PDF (fiskal emas), ixtiyoriy e-pochta.
