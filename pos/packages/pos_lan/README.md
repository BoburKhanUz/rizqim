# pos_lan

Lokal ko'prik (docs/POS_SPEC.md 5.5, P6 "Uskunalar va lokal tarmoq"): internet uzilsa, lekin Wi-Fi ishlayotgan bo'lsa,
filialning **asosiy kassasi (markaz, hub)** zaldagi qurilmalarni o'zi boshqaradi — ofitsiant buyurtma oladi, KDS taom
oladi, "tayyor" qaytadi, mijoz ekrani raqam ko'rsatadi. **To'lov, yopish, qaytarish faqat kassaning o'zida.** Internet
qaytgach markaz o'z navbatini (`pos_sync`) tartib bilan `POST /pos/sync` ga yuboradi; ofitsiant va KDS amallari ham shu
navbatda, kelib chiqqan qurilma va xodim bilan.

Flutter'siz (`dart test`), faqat `dart:io` va `package:crypto`. Bu fayl — **shartnoma**: ofitsiant, KDS va mijoz ekrani
(stage B) shunga qarab yoziladi. O'zgartirsangiz `v` ni oshiring.

## 1. Rollar va holatlar

| Holat (`LanMode`) | Qachon | Qurilma nima qiladi |
|---|---|---|
| `cloud` | server javob beradi | odatdagidek (HTTP + Reverb); LAN bo'sh turadi, markaz baribir ishlaydi (beacon, ulanishlar) |
| `lan` | server yo'q, markaz bor (WebSocket `welcome` olindi) | amallar markazga (`op`), ko'rinish markazdan (`state`, `order.changed`) |
| `alone` | ikkalasi ham yo'q | faqat ko'rish ("Aloqa yo'q"), bugungi 5.1 qoidalari |

- **`lan` dan `cloud` ga qaytish** faqat markaz `lan.state` da `cloud: true, pending: 0` deganda (markaz navbati serverga
  yetgan): aks holda server hali LAN amallarini bilmaydi va ekran eski holatni ko'rsatadi. Qoida: `LanModeRule.next`.
- **Markaz** — filialda bitta: bootstrap'dagi `lan.role == "hub"` bo'lgan kassa. Ega uni tanlaydi (`PUT /owner/pos/lan`),
  tanlanmagan bo'lsa — filialning "chop etuvchi" kassasi (`pos_settings.printing_device_id`), u ham bo'lmasa filialning
  birinchi ulangan faol POS terminali. Markaz ilovasi ochiq turguncha (xodim ekranni qulflagan bo'lsa ham) ishlaydi.
- Markaz **o'zi onlayn bo'lsa ham** amallarni qabul qiladi (ular uning navbatiga tushadi va darhol serverga ketadi):
  masalan, ofitsiant telefonining Wi-Fi'sida internet yo'q, kassada LTE bor.

## 2. Kalitlar (server, `GET /pos/bootstrap` → `lan`)

Server har filial uchun 32 baytli tasodifiy **filial siri** (`S`, versiya `key_id`) saqlaydi (shifrlangan). Ega uni
yangilaydi (`POST /owner/pos/lan/rotate`, audit `pos.lan_key_rotated`). Undan HMAC-SHA256 bilan:

| Kalit | Formula | Kimda |
|---|---|---|
| qurilma kaliti `K_D` | `HMAC(S, "rizqim-lan/v1/device/" + D_uuid)` | faqat D da va markazda (markaz `S` dan hisoblaydi) |
| beacon kaliti `K_B` | `HMAC(S, "rizqim-lan/v1/beacon")` | filialning har bir terminalida |
| grant kaliti `K_G` | `HMAC(S, "rizqim-lan/v1/grant")` | faqat server va markaz |
| kadr shifri / imzosi | `E_D = HMAC(K_D, "enc")`, `M_D = HMAC(K_D, "mac")` | D va markaz |

`lan` bo'limi **faqat filialning ulangan terminalidagi PIN sessiyasiga** beriladi (`GET /pos/bootstrap`) yoki xodimsiz
terminal tokeniga (`GET /pos/lan`, mijoz ekrani uchun). Oddiy xodim yoki ega web sessiyasida — `null`. `S` faqat
markazning javobida (`hub_secret`). Shu sababli oddiy qurilma boshqa qurilma nomidan kadr yasay olmaydi va grant
soxtalashtira olmaydi; o'g'irlangan telefon serverda uziladi (markazning `devices` ro'yxatidan chiqadi) va kalit
yangilanadi.

```json
"lan": {
  "v": 1, "branch_id": 12, "key_id": 3,
  "role": "hub",                       // yoki "client"
  "device": "<shu terminal uuid>",
  "device_key": "<base64 32 bayt>", "beacon_key": "<base64>",
  "hub": {"device": "<uuid>|null", "name": "Kassa 1", "port": 18480},
  "hub_secret": "<base64 S>",          // faqat role=hub
  "devices": [{"uuid": "...", "type": "pos|kds|display", "name": "..."}],   // faqat role=hub: kimni qabul qiladi
  "grant": "<base64url(json)>.<base64url(hmac)>",   // PIN sessiyasining oflayn granti, xodimsiz — null
  "clock_window_seconds": 120
}
```

Kalit yangilangach har qurilma yangisini keyingi bootstrap'da (kirish, ilovani qayta ochish) oladi; ungacha u LAN'ga
ulana olmaydi (markaz `key_id` farqini `4401 key_mismatch` bilan aytadi).

## 3. Oflayn grant (xodim kimligi)

Internetsiz PIN tekshirilmaydi (POS_SPEC 5.4), shuning uchun qurilmadagi **hozirgi PIN sessiyasi** server bergan imzoli
grant bilan markazga aytiladi. Payload (JSON, base64url):

```json
{"v": 1, "kid": 3, "branch_id": 12, "device": "<uuid>", "staff_id": 7, "name": "Aziz", "role": "waiter",
 "permissions": ["orders.view", "orders.manage"], "session": 9812, "iat": 1793700000, "exp": 1793829600}
```

Imzo: `base64url(HMAC(K_G, base64url_payload))`. Markaz tekshiradi: imzo, `kid`, `branch_id`, `device == kadr.device`,
`exp` (berilgandan 36 soat — serverning `SESSION_WINDOW_HOURS` bilan bir xil). Grantsiz (mijoz ekrani) ulanish faqat
hodisalarni oladi, amal yubora olmaydi (`grant_required`).

## 4. Topish: beacon va IP/QR

- Markaz har **2 soniyada** UDP broadcast (`255.255.255.255`, port = `hub.port`, standart **18480**) yuboradi:
  `{"v":1,"type":"lan.beacon","branch_id":12,"key_id":3,"hub_device":"<uuid>","host":"192.168.1.20","port":18480,
  "ts":1793700000123,"sig":"<base64 HMAC(K_B, kanonik JSON sig'siz)>"}`.
- Qurilma tekshiradi: imzo, `branch_id`, `hub_device` == o'z bootstrap'idagi markaz, `ts` ±10 daqiqa (qurilma soati).
- Zaxira: sozlamalarda IP yoki QR (`rizqim-lan://192.168.1.20:18480?b=12&h=<uuid>`): qurilma
  `GET http://host:port/lan/v1/beacon` ni o'qiydi va xuddi shunday tekshiradi. Beacon faqat manzil aytadi — haqiqiy
  tekshiruv WebSocket qo'l berishida (soxta markaz `K_D` ni bilmaydi).

## 5. Kadr (har bir xabar)

WebSocket: `ws://host:port/lan/v1/ws`, har xabar — bitta matn kadri:

```json
{"v":1,"kid":3,"type":"op","device":"<yuboruvchi uuid>","seq":1793700000123456,"ts":1793700000123,
 "n":"<base64 12 bayt>","payload":"<base64 shifrlangan JSON>","sig":"<base64 32 bayt>"}
```

- **Kalit:** markaz va D orasidagi har ikki yo'nalishdagi kadr — `K_D` dan (`E_D`, `M_D`). `device` — yuboruvchi:
  D ning kadri `device = D`, markazniki `device = markaz uuid` (shuning uchun kadrni qaytarib yuborish ishlamaydi).
- **Shifr (encrypt-then-MAC):** `payload = base64(JSON ⊕ keystream)`, keystream bloklari
  `HMAC(E_D, n ‖ uint32be(i))`, `i = 0,1,…`; `n` — har kadrga yangi tasodifiy 12 bayt.
- **Imzo:** `sig = HMAC(M_D, kanonik)`, kanonik = `sig` siz kadr, kalitlari alifbo tartibida, bo'shliqsiz JSON
  (`{"device":…,"kid":…,"n":…,"payload":…,"seq":…,"ts":…,"type":…,"v":1}`). Imzo to'g'ri bo'lmasa shifr ochilmaydi.
  `kid` — kalit versiyasi (`key_id`): boshqasi bo'lsa kadr ochilmay `4401 key_mismatch`.
- **Qayta yuborishdan himoya:** `ts` (markaz vaqti, ms) qabul qiluvchining hisobidagi markaz vaqtidan
  ±`clock_window_seconds` (120) dan oshmasin; `(device, seq)` shu oyna ichida ikkinchi marta kelmasin (qabul qiluvchi
  ko'rganlarini 2 × oyna saqlaydi). `seq` — yuboruvchi ishga tushgan paytdagi mikrosoniyadan boshlab har kadrga +1
  (ilova qayta ochilganda ham takrorlanmaydi); himoyaning o'zi `ts` oynasi va ko'rilgan `(device, seq)` ro'yxatida.
- **Qo'l berish:** mijoz `hello` `{nonce, app: "waiter|kds|display|cashier", version, grant?}` (mijoz soati bilan,
  oyna 10 daqiqa) → markaz `welcome` `{nonce (qaytarilgan), hub_time, hub: {device, name}, staff, grant_error,
  state}`. Mijoz `nonce` ni tekshiradi va markaz soatiga farqni (`hub_time − hozir`) oladi; keyingi kadrlarida `ts` —
  markaz vaqti.
- **Xatolar:** tanilmagan qurilma, imzo yoki kalit versiyasi — WebSocket `4401` bilan yopiladi (`unknown_device`,
  `bad_sig`, `key_mismatch`, bir ulanishda boshqa qurilma nomidan — `device_changed`), qo'l berishsiz kadr — `4400
  hello_required`, 10 soniyada `hello` kelmasa — `4400 hello_timeout`. Boshqa filialning qurilmasi markaz ro'yxatida
  yo'q (`unknown_device`), uning beacon'i va granti qabul qilinmaydi (`wrong_branch`). Ulanishdan
  keyingi qayta yuborilgan yoki eskirgan kadr tashlab yuboriladi (`error` `{reason: replay|stale}`).

## 6. Xabarlar

### Mijoz → markaz

| `type` | `payload` | Javob |
|---|---|---|
| `hello` | `{nonce, app, version, grant?}` | `welcome` |
| `grant` | `{grant, nonce}` — qurilmada xodim almashdi (onlayn) | `welcome` (yangi `staff`) |
| `op` | `pos_sync` amali konverti: `{op_id, type, occurred_at, staff_id, payload}` (pastda) | `op.result` |
| `state.get` | `{}` | `state` |
| `order.get` | `{order}` | `order.changed` (yo'q bo'lsa `op.result`siz `error` `not_found`) |
| `ping` | `{}` | `pong` `{hub_time}` |

`op` — `op_id` (UUID, mijoz beradi; markaz uni o'z navbatida **shu id bilan** saqlaydi, qayta yuborilsa bir marta
qo'llanadi), `occurred_at` (markaz vaqti, ISO 8601), `staff_id` (grantdagi bilan bir xil bo'lishi shart). Amallar va
`payload` (onlayn API dagidek: `items[]` — `POST /pos/orders` qatori, `line` — markaz ko'rsatgan qator `id`si):

| `op.type` | `payload` | Huquq | Modul |
|---|---|---|---|
| `order.open` | `order` (mijoz uuid'si), `channel` (`pos_hall`/`pos_takeaway`), `table_id?`, `guests_count?`, `note?`, `table_label?`, `items[]`, `send?` | `orders.manage` | — |
| `order.round` | `order`, `items[]`, `fire_mode?`, `send?` | `orders.manage` | — |
| `order.send` | `order`, `lines?` | `orders.manage` | — |
| `order.course_send` | `order`, `course_no` | `orders.manage` | `courses` |
| `order.weight` | `order`, `line`, `quantity` | `orders.manage` | `weight_later` |
| `order.items.void` | `order`, `line`, `reason?` — faqat oshxonaga yuborilmagan qator | `orders.manage` | — |
| `order.served` | `order`, `line` | `orders.manage` | — |
| `kitchen.bump` / `kitchen.recall` | `order`, `line` | `kitchen.manage` | — |
| `kitchen.assembled` | `order` | `kitchen.manage` | `kds_expo` |
| `bill.request` | `order` (markaz relay'ida `printed_on_lan?` — pastda, 9) | `orders.manage` | — |

Grant xatosi (`welcome.grant_error`: `grant_invalid`, `grant_device`, `wrong_branch`, `key_mismatch`,
`grant_expired`) bilan ulanish qoladi, lekin amal `grant_required` bilan rad etiladi; `staff_id` grantdagidan boshqa —
`staff_mismatch`. Markaz har amalni o'zining `PosOffline` qoidalari bilan qo'llaydi (narx — markazdagi menyu nusxasidan, 500 buyurtma
chegarasi, 24 soatlik menyu va aloqa sharti, xona bandligi, yuborilgan qatorni o'chirish yo'q) va qo'shimcha:
grantdagi huquq, filial modullari (markaz bootstrap'idan) va **ofitsiant qoidasi** — `waiter` roli faqat o'z yoki
ofitsiantsiz buyurtmasini o'zgartiradi (internetsiz menejer tasdig'i yo'q: `approval_required`). `bill.pay`,
`bill.create`, `order.close`, qaytarish, chegirma va boshqalar — `not_allowed_on_lan` ("To'lov faqat kassada").

`op.result`: `{op_id, status: "accepted"|"refused", duplicate, reason?, message?, order?}` — `accepted` = markaz
navbatiga diskka yozildi (serverga keyin boradi); `order` — buyurtmaning markazdagi ko'rinishi (server JSON shakli).

### Markaz → mijozlar

| `type` | `payload` |
|---|---|
| `welcome` | yuqorida |
| `state` | `{lan, floor, open_orders}` — markazning `OfflineOrderBook` ko'rinishi (`GET /pos/floor`, `GET /pos/orders?scope=open` shakli) |
| `order.changed` | `{order, ref}` — har o'zgargan buyurtma (markazning o'z amallari ham; `ref` — markaz bilgan id) |
| `kitchen.ticket` | `{order, short_code, table_ref, hall_ref, lines: [{id, client_line_id, name, variant_name, quantity, unit_label, station_id, course_no, note, modifiers}], sent_at}` — oshxonaga ketgan qatorlar |
| `kitchen.item_status` | `{order, line, status: ready\|sent\|served\|void}` |
| `queue.ready` / `queue.called` / `queue.handed` | `{order, number, short_code}` |
| `cart.display` | `POST /pos/display/cart` tanasi bilan bir xil |
| `lan.state` | `{cloud: bool, pending: int, devices: [{uuid, type, name, app, staff}], hub_time}` |
| `pong`, `error` | `{hub_time}`, `{reason, message}` |

Hodisalar "yetkazib bo'lsa" yuboriladi: qayta ulangan mijoz `state.get` bilan to'liq holatni oladi.

## 7. Server bilan sinxronlash (relay)

Markaz navbatidagi LAN amali `SyncOperation.origin` (kelib chiqqan qurilma uuid'si) bilan saqlanadi va `POST /pos/sync`
da `origin_device` bo'lib boradi (`op_id` — mijozniki, `seq` — markaz navbatiniki, `staff_id` — grantdagi). Server:
yuboruvchi shu filialning markazi bo'lishi (yoki markaz almashgan bo'lsa, almashishdan oldingi amal uchun eski markaz),
`origin_device` shu filialning faol terminali bo'lishi, amal turi LAN'ga ruxsat etilgan bo'lishi kerak; xodim **kelib
chiqqan qurilmada** 36 soat ichida PIN bilan kirgan va huquqi bor. Aks holda amal `rejected` (`relay_not_hub`,
`origin_unknown`, `origin_wrong_branch`, `origin_revoked`, `relay_not_allowed`) — "Sinxronlash xatolari"da. Buyurtma
`device_id` si — kelib chiqqan qurilma.

## 8. Dart API

```dart
final LanCredentials creds = LanCredentials.fromBootstrap(bootstrap.raw)!;   // null: LAN yo'q
// Markaz (kassa)
final LanHub hub = LanHub(credentials: creds, applier: PosOfflineLanApplier(offline: pos.offline!));
await hub.start();                      // HTTP + WebSocket (port 18480), beacon har 2 s
hub.devices;                            // ulangan qurilmalar ("Lokal tarmoq: 3 qurilma")
hub.publish(LanMsg.cartDisplay, cart);
// Mijoz (ofitsiant, KDS, mijoz ekrani — stage B)
final LanClient client = LanClient(credentials: creds, app: LanApp.waiter);
await client.connect(LanEndpoint('192.168.1.20', 18480));  // yoki LanDiscovery bilan
final LanOpResult r = await client.send(LanOpType.orderRound, {'order': uuid, 'items': [...]});
client.events.listen(...);              // order.changed, kitchen.ticket, lan.state ...
```

## 9. Ilovalarda: ofitsiant, KDS, mijoz ekrani (stage B)

**`LanLink`** — uchala ilovaning umumiy mijoz boshqaruvchisi (Flutter'siz):

- **Kalitlar:** ofitsiant va KDS — PIN bilan kirilgandagi `GET /pos/bootstrap` `lan` qismi (grant bilan; faqat
  xotirada, xodim chiqsa ko'prik to'xtaydi); mijoz ekrani — terminal tokeni bilan `GET /pos/lan` (grantsiz: faqat
  hodisalar), qurilmaning xavfsiz xotirasida saqlanadi (ekran internetsiz qayta ochilsa ham ulanadi); `key_mismatch`
  yoki `unknown_device` dan keyin internet qaytgach yangidan olinadi.
- **Rejim:** ilova o'z so'rovi serverga yetdi/yetmadi deb aytadi (`reportCloud`); server yo'q paytda `LanLink` o'zi
  har 15 soniyada `GET /health` (tokensiz) bilan tekshiradi. `cloud` da LAN bo'sh (ulanish ham, beacon tinglash ham
  yo'q); server javob bermasa — ulanadi (`lan`, topilmasa `alone` va 2/5/10/30 soniyada qayta urinadi); `cloud` ga
  faqat `LanModeRule` bo'yicha (`cloud: true, pending: 0`) qaytadi va ulanishni yopadi.
- **Topish tartibi:** qo'lda kiritilgan manzil (IP yoki `rizqim-lan://` havola; qurilmada saqlanadi) → oxirgi
  ishlagan manzil → UDP beacon (3 soniya; Android'da shu vaqt `WifiManager.MulticastLock` ushlanadi —
  `pos_hardware` `WifiMulticastLock`, ruxsat `CHANGE_WIFI_MULTICAST_STATE`) → `127.0.0.1` (Windows'da kassa bilan bir
  kompyuterdagi mijoz ekrani).
- **Belgi:** ofitsiant — yuqori paneldagi aloqa belgisi "Lokal tarmoq" / "Aloqa yo'q" (bosilsa: markaz, xato, qo'lda
  manzil) va lenta; KDS — "Lokal tarmoq" / "Internet yo'q" (bosilsa — xuddi shunday); mijoz ekrani — burchakda kichik
  "Lokal tarmoq" yoki "Aloqa yo'q", manzil — ekran sozlamalarida (pastki chap burchakni 2 soniya bosib turish).

**Ofitsiant** (`LanPosRoute` → `pos_data` `PosLanRoute`): `lan` rejimida `FloorRepository`, `PosOrderRepository`
zal xaritasini, ochiq buyurtmalarni va buyurtmani markazdan (`state.get`, `order.get`) oladi; `open`, `addRound`,
`send`, `sendCourse`, `setWeight`, `voidItem`, `markServed`, `requestBill` — markazga `op` (`Idempotency-Key` →
`op_id`, javob kelmasa xuddi shu kalit bilan qayta yuborilsa bir marta qo'llanadi); qolgan amallar (ko'chirish,
mehmonlar soni, chegirma ...) — "Internet yo'q: ... faqat internet tiklangach". Markaz rad etsa — uning sababi va
xabari (`approval_required`, `staff_mismatch` ...). Ekranlar markaz hodisalarini (`order.changed`, `kitchen.*`)
server hodisalari kabi oladi (`PosData.lanEvents`). Schyot, to'lov, yopish, qaytarish — `BillingRepository` da
`not_allowed_on_lan`.

**Schyotni qayta chiqarish LAN orqali yo'q** (B1 qarori): nusxa uchun `pos.reprint_bill` yoki menejer PIN'i kerak,
menejer PIN'ini internetsiz tekshirib bo'lmaydi (bootstrap'da PIN xeshlari yo'q, 5.4) va nusxaning jurnali
(`pos.bill_reprinted`) serverda — internetsiz chiqarilgan nusxa izsiz qolar edi (pulni ikki marta yig'ish xavfi).
Ofitsiant ilovasi `lan` da "Internet yo'q: schyotni qayta chiqarish faqat internet bilan (yoki kassaning o'zida)"
deydi; birinchi schyot (`bill.request`, avtomatik chop etish) ishlaydi.

**KDS** (`LanKitchenService`): onlayn paytdagi oxirgi ro'yxat saqlanadi; `lan` da doska shu ro'yxat + markazning
`order.changed` (chipta buyurtmaning markazdagi ko'rinishidan qayta yasaladi), `kitchen.item_status`, `kitchen.ticket`
(noma'lum buyurtma `order.get` bilan so'raladi). "Tayyor", "Qaytarish", "Yig'ildi" — `kitchen.bump` / `kitchen.recall`
/ `kitchen.assembled`. "Buyurtma tayyor" (butun buyurtma) va raqamni tayyor qilish — LAN amali yo'q, oddiy xabar
(taomlar birma-bir "Tayyor"; hammasi tayyor bo'lsa kassa `queue.ready` ni o'zi e'lon qiladi). Kutish vaqti markaz
soati bo'yicha (`hubNow`): planshet soati farq qilsa ham to'g'ri.

**Mijoz ekrani:** `cart.display` (faqat ekran kuzatadigan kassa markazning o'zi bo'lsa; bir ishga tushishda `seq`
kichigi tashlanadi), `queue.ready` / `queue.called` (ovoz va yirik raqam, bir raqam bir marta), `queue.handed`,
`order.changed` dagi `queue` (yangi raqam "Tayyorlanmoqda"ga). `lan` paytida server so'ralmaydi; `cloud` ga qaytgach
darhol serverdan yangilanadi.

**Markaz kassa** (`pos/apps/cashier` `lib/src/lan/`): `CashierLanHub` markazni bootstrap kalitlari bilan ishga
tushiradi; `LanPublisher` kassaning o'z hodisalarini tarqatadi — `cart.display` (`DisplayFeed` dagi joriy chek, serverni
kutmasdan; yangi ulangan mijoz ekraniga oxirgi chek darhol), `queue.ready` / `queue.called` / `queue.handed` (kassadagi
"Tayyor", "Qayta chaqirish", "Berildi"), va internetsiz paytda navbat raqami bor buyurtmaning hamma taomi tayyor
bo'lsa `queue.ready` (onlayn buni server e'lon qiladi); kassaning internetsiz amallari (`kitchen.*`, `order.changed`)
navbat orqali `PosOfflineLanApplier` dan o'tadi. `LanBillPrints`: kassa internetsiz, filialda "schyotni avtomatik
chop etish" yoqilgan va kassada printer bor bo'lsa, LAN orqali kelgan `bill.request` navbatga `printed_on_lan: true`
bilan yoziladi va pre-check kassaning o'z printerida bir marta chiqadi (so'rov id'si — `op_id`; navbatga schyot
yozilmaydi); server navbat yetganda uni qayta chiqarmaydi. Internet bor paytda markaz chiqarmaydi — server
odatdagidek `bill.print_requested` yuboradi (bir so'rov — bitta qog'oz). Sozlamalardagi "Lokal tarmoq" bo'limi:
holat, IP, port, ulangan qurilmalar, qo'lda ulanish uchun QR (`rizqim-lan://...`), hech kim ulanmagan bo'lsa —
Wi-Fi va Windows ruxsat qoidasi bo'yicha maslahat. Markazni ega tanlaydi (boshqaruv paneli: Kassa sozlamalari →
Lokal tarmoq); tanlanmagan bo'lsa server kassa ilovasini (`devices.app = cashier`) oladi, ofitsiant telefonini hech
qachon.

## Sinov

```bash
cd pos/packages/pos_lan && dart test
```

`crypto_test.dart` — kalitlar backend bilan bayt-bayt bir xil (vektor), kadr, qayta yuborish, grant, beacon, holat
qoidasi; `hub_test.dart` — haqiqiy soketlar (localhost): tanilmagan qurilma, noto'g'ri kalit, boshqa versiya,
`hello`siz kadr, qayta yuborilgan va eskirgan kadr, buzilgan imzo, soxta/eskirgan grant, kassaga tegishli amallar,
hodisalar tarqalishi, beacon (HTTP va UDP); `bridge_test.dart` — soxta backend ustidagi haqiqiy `PosOffline`: markaz +
2 ofitsiant + KDS + mijoz ekrani, ofitsiant qoidasi, huquq va modul, `op_id` bir marta, internet qaytgach navbat
tartib bilan va `origin_device` bilan serverga; `link_test.dart` — `LanLink` va `LanPosRoute` haqiqiy markaz bilan
(ikki tomonga rejim almashishi, "navbat yetdi" qoidasi, markaz yo'qolishi, noto'g'ri kalit, beacon va multicast qulfi,
ofitsiant repozitoriylari markaz orqali); `platform_bind_test.dart` — barcha interfeyslar, `127.0.0.1`, beacon porti.

Kassada: `pos/apps/cashier/lib/src/lan/` (`CashierLanHub` — bootstrap'dan ishga tushadi, kalitlar xavfsiz xotirada,
markaz emas bo'lsa to'xtaydi; yuqori paneldagi "Lokal tarmoq: N qurilma" belgisi va IP/QR manzili).
