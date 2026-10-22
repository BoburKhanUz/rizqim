# pos/apps/cashier

Kassa ilovasi (Android planshet, Sunmi, Windows; sinov uchun Linux). Hozir — qobiq: ulash, PIN, ekran qulfi, menejer
tasdig'i, yuqori panel va bo'limlar. Bo'limlar (zal, navbat, bronlar, nasiya, smena, sozlamalar) P3-21 va P3-22 da.

## Ishga tushirish

```bash
cd pos/packages/pos_data && dart run tool/fake_pos_server.dart --port 8788        # yoki haqiqiy backend
cd pos/apps/cashier && flutter run -d linux \
  --dart-define=RIZQIM_API_URL=http://127.0.0.1:8788/api \
  --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8788
```

Soxta backend'da ulash kodi — istalgan 8 belgi, PIN: kassir 2468, menejer 5926, ofitsiant 1357.

## Qanday ishlaydi

1. **Ulash** — panelda Sozlamalar → Qurilmalar → «Ulash kodini yaratish» (turi: kassa), 8 belgili kod. Token qurilmaning
   maxfiy omborida.
2. **PIN** — xodim ismini tanlab PIN teradi (kassirlar birinchi). Ko'p xato — kutish vaqti ko'rsatiladi.
3. **Ish ekrani** — bootstrap yuklanadi (filial, printerlar, zallar, smena; menyu fonda keshga olinadi). Yuqorida:
   do'kon va filial, smena belgisi (bosilsa — Smena), aloqa ("Jonli", "Har 15 soniyada", "Aloqa yo'q"), sinxronlash
   nishoni, xodim menyusi (qulflash, mavzu, menejer va egaga — «Terminalni uzish») va qulf tugmasi.
4. **Ekran qulfi** — filialning `screen_lock_minutes` (standart 5 daqiqa, 0 — qulflanmaydi) davomida tegilmasa PIN
   ekraniga qaytadi, ochiq oyna va sahifalar yopiladi.
5. **Menejer tasdig'i** — `withApproval(context, (token) => ...)`: server tasdiq so'rasa menejer o'z PIN'ini teradi.

## Bo'limlar kelishuvi (P3-21, P3-22)

| Bo'lim | Fayl (vidjet) | Egasi |
|---|---|---|
| Zal | `lib/src/features/floor/floor_page.dart` (`FloorPage()`) | P3-21 |
| Buyurtma (ustidan ochiladi) | `lib/src/features/order/order_page.dart` (`OrderPage({orderUuid, tableId})`) | P3-21 |
| Navbat | `lib/src/features/queue/queue_page.dart` (`QueuePage()`) | P3-22 |
| Bronlar | `lib/src/features/bookings/bookings_page.dart` (`BookingsPage()`) | P3-22 |
| Nasiya | `lib/src/features/accounts/accounts_page.dart` (`AccountsPage()`) | P3-22 |
| Smena | `lib/src/features/shift/shift_page.dart` (`ShiftPage()`) | P3-22 |
| Sozlamalar | `lib/src/features/settings/settings_page.dart` (`SettingsPage()`) | P3-22 |
| Schyot (ustidan ochiladi) | `lib/src/features/billing/billing_page.dart` (`BillingPage({required orderUuid})`) | P3-22 |

Feature faqat o'z papkasini o'zgartiradi; marshrutlar (`shell/destinations.dart`, `shell/routes.dart`) va qobiq —
P3-21 da. Bog'liqliklar `PosScope.of(context)` dan: `data` (`PosData`), `session`, `realtime`, `printing`
(`receiptPrinter`, `kitchenPrinterFor(stationId)`), `go(CashierDestination...)`. Ustidan sahifa —
`CashierRoutes.openOrder / openBilling / push`. Testlar: `pos_data/testing.dart` va `test/support/harness.dart`.
