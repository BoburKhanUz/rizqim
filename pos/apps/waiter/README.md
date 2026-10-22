# pos/apps/waiter

Ofitsiant ilovasi (telefon). Hozir — qobiq: ulash, PIN (avval xodimlar ro'yxati, keyin klaviatura), ekran qulfi,
menejer tasdig'i oynasi va "Mening xonalarim" sahifasi o'rni. Buyurtma olish, bosqich, yuborish, schyot so'rash — P3-25.

## Ishga tushirish

```bash
cd pos/packages/pos_data && dart run tool/fake_pos_server.dart --port 8788
cd pos/apps/waiter && flutter run -d linux \
  --dart-define=RIZQIM_API_URL=http://127.0.0.1:8788/api \
  --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8788
```

Telefon panelda kassa turidagi kod bilan ulanadi. Soxta backend'da ofitsiant Botir — PIN 1357, menejer — 5926.

## Kelishuv (P3-25)

- Ish ekrani `lib/src/features/rooms/rooms_page.dart` dagi `RoomsPage()` ni ochadi; P3-25 shu papkada quradi.
- Bog'liqliklar `WaiterScope.of(context)`: `data` (`PosData`: buyurtmalar, zal, menyu), `session`, `realtime`. Ustidan
  sahifa — `WaiterScope.push(context, page)`. Menejer tasdig'i — `withApproval(context, (token) => ...)` (ofitsiant
  qoidasi: boshqa ofitsiantning buyurtmasi).
- Ekran qulfi, ulash va PIN — `lib/src/shell`, `lib/src/app.dart`.
