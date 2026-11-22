# Fiscal module 0400, FiscalDriveService v10 and the OFD protocol (evidence, 22.11.2026)

Sources read in full on 22.11.2026 by cloning the public repositories (git clone over HTTPS):

- `github.com/qo0p/fiscal-drive-service` — a single README (61 869 bytes), "РУКОВОДСТВО ПРОГРАММИСТА ВИРТУАЛЬНОЙ КАССЫ ДЛЯ ИНТЕГРАЦИИ С FiscalDriveService-v10". Commits: 10.4.0 (2026-08-06), 10.5.0 (2026-08-17), 10.6.1 (2026-09-23), 10.6.3 (2026-10-16). No releases, no tags, no binaries (GitHub releases page: "There aren't any releases here"). No licence file.
- `github.com/qo0p/acrsim-android` — README (137 771 bytes), "ИНСТРУКЦИЯ РАЗРАБОТЧИКА КОНТРОЛЬНО-КАССОВОЙ МАШИНЫ НА БАЗЕ ОС ANDROID ДЛЯ ИНТЕГРАЦИИ ФИСКАЛЬНОГО МОДУЛЯ ВЕРСИИ 0400" plus a Java Android project (112 .java files). Last commits v0.1.1 (2026-10-12, 2026-10-28). No licence file.

Tag: [PRIMARY as developer documentation; the publisher is not officially confirmed]. Evidence that it comes from the operator: the Java package is `uz.yt.ofd.*` ("yt" = Yangi Texnologiyalar, "ofd"); the same account publishes `e-imzo-doc` (E-IMZO integration guide); the predecessor FiscalDriveAPI is listed by a software catalogue under publisher "НИЦ НТ ГНК РУз" (the Tax Committee's research centre "Yangi texnologiyalar") ([fiscaldriveapi.software.informer.com](https://fiscaldriveapi.software.informer.com/), search snippet, page itself returned 403) [MEDIA]. The account profile shows no organisation ([github.com/qo0p](https://github.com/qo0p)).

No licence: the specification can be followed, but the Java code (crypto, codecs) should not be copied into RIZQIM without the operator's permission [inference].

## 1. What the fiscal module is

- "ФМ - фискальный модуль выданный ОФД в виде USB-ключа или Смарт-карты" (fds README, Определения).
- "смарт-карта или USB-токен с ОС JavaCard и установленным апплетов выполняющего функцию фискального модуля" (acrsim README, Термины).
- Applet AID selected with APDU `00 A4 04 00 0E 66697363616C6472697665533031` (ASCII "fiscaldriveS01").
- Info fields include `TAG_MODE` "тест - 0x01 или продакшн - 0x02", `TAG_LOCKED`, `TAG_POS_LOCKED`.
- Serial number (TerminalID): "две лат. загл. буквы и 12 цифр", e.g. `UZ724549167320`; test/emulator `ZZ000000000000`. Factory id (`FactoryID`) is 36 characters.
- Feitian is the main supplier of the tokens/cards to the operator ([ftsafe.com reference](https://www.ftsafe.com/Reference/State_Tax_Committee_of_Uzbekistan/), read in the previous round) [PRIMARY].
- So: a hardware JavaCard applet, not a cloud HSM and not a pure software module. The "virtual fiscal module" (unique number + e-signature certificate issued by the operator) exists in VM 943 but in the rules it is tied to marketplaces (previous round, `Fiskal provayder raqib bo'lmaganlar/huquqiy_yangi_topilmalar.md`).
- Placement: "ПО FiscalDriveService и ФМ устанавливаются непосредственно на компьютер где установлено ПО Кассы. Требуется интернет соединение для этого компьютера." (fds README, Установка). VM 943 2-ilova 6-band: one FM used by only one virtual kassa at a time; fiscal data go to the operator only, directly through the FM (see legal notes).

## 2. FM 0400 changes (acrsim README "Отличия ФМ версии 0400")

- Rejects a date more than 2 days ahead of its last operation; asks for server sync.
- No limit on number of Z reports or receipts (only physical memory).
- Up to 29 999 sale+refund operations per Z report (was 9 999).
- New receipt types Advance and Credit: "ФМ также выдает порядковый номер чека, но не выдает ФП".
- Authentication by FM in IT systems (signed challenge).
- Optional binding of FM to one POS (secret key).
- Idempotent operations: the same register command repeated gives one operation and the same answer.
- Emulator of FM 0400 is part of FiscalDriveService v10.
- "Нет обратной совместимости" with older FM versions; FiscalDriveAPI does not support 0400.

## 3. FiscalDriveService v10 (local service)

- Windows 11 x64 service or Linux x64/arm binary; REST on `http://127.0.0.1:3449/`, Swagger at `/swagger/index.html`. Needs pcscd/libccid on Linux, smart-card service on Windows. RAM ≥ 2 GB, disk ≥ 512 MB, UPS recommended.
- Receipts are "шифруются и подписываются ЭЦП ФМ и временно сохраняются в файле БД FiscalDriveService"; on the till's command the service connects to OFD servers (addresses in `config.ini`, random fallback) and passes the server's confirmation files back to the FM, which then erases the receipt.
- Clock: if the computer clock runs more than 2 days ahead, the FM refuses the operation.
- Optional TLS between till software and the service (own root CA, server cert, client PFX).
- Before upgrading the service: send all unsent receipts first.

### REST methods (verbatim names)

`/FiscalDrive/List`, `/FiscalDrive/Info/{FactoryID}`, `/FiscalDrive/State/Sync/{FactoryID}`, `/FiscalDrive/FiscalMemory/Info/{FactoryID}`, `/FiscalDrive/ZReport/Open/{FactoryID}`, `/FiscalDrive/ZReport/Close/{FactoryID}`, `/FiscalDrive/ZReport/Info/{FactoryID}`, `/FiscalDrive/ZReport/UnackowledgedIndexes/{FactoryID}` (sic), `/FiscalDrive/Receipt/GetTXID/{FactoryID}`, `/FiscalDrive/Receipt/RegisterTXID/{FactoryID}`, `/FiscalDrive/Receipt/Info/{FactoryID}`, `/DataBase/Files/Count`, `/DataBase/Files/Status/Reset`, `/DataBase/Files/List/{FactoryID}/{Limit}/{Offset}`, `/DataBase/Files/Sync/FullReceipts/{FactoryID}`, `/DataBase/Files/Resend/FullReceipts/{FactoryID}` (≤ 32), `/DataBase/Files/Sync/ZReports/{FactoryID}`, `/DataBase/Files/Sync/Receipts/{FactoryID}` (only after DB loss), `/Auth/SignChallenge/{FactoryID}`, `/POS/Lock/{FactoryID}`, `/POS/Challenge/{FactoryID}`, `/POS/Auth/{FactoryID}`.

HTTP verbs and exact request parameter shapes are only in the Swagger of the binary, which is not public [NOT FOUND]. The README names fields `FactoryID`, `JsonReceipt`, `TXID`, `Index`, `ItemsCount` (≤ 32), `SecretKey`, header `X-POS-Auth`, `Status` (2 = sent and acknowledged).

### Registration flow

1. `GetTXID` with the JSON receipt → `TXID` (the same JSON returns the same TXID).
2. `RegisterTXID` with TXID → `{"TerminalID","ReceiptSeq","DateTime","FiscalSign","QRCodeURL"}`. Example: `{"TerminalID":"ZZ000000000000","ReceiptSeq":1,"DateTime":"2024-09-04 09:45:38","FiscalSign":"320262508539","QRCodeURL":"https://ofd.soliq.uz/check?t=ZZ000000000000&r=1&c=20240904094538&s=320262508539"}`. "Данный JSON-ответ должен быть сохранен в БД ПО Кассы и будет нужен в случае выполнения операции возврат по чеку."
3. On a connection failure with the FM, repeat `RegisterTXID` with the same TXID: "не будет создан дубликат или пустой чек". Do not call `GetTXID` again for the same receipt.
4. `DataBase/Files/Sync/FullReceipts` (≤ 32 per call) → `{"Items":[{"TerminalID","ReceiptSeq","StatusText":"Acknowledged"}],"SuccessfulsCount":1}`.
5. If `RegisterTXID` returns `DATETIME_IS_IN_THE_PAST` on retry, the receipt may already be erased from FM memory (see "Решение проблем").

### JSON receipt (FiscalDriveService), exact field names

Top level: `Time` (string "ГГГГ-ММ-ДД ЧС-МН-СК", example "2024-09-04 09:45:38"), `ReceivedCash` uint64 tiyin, `ReceivedCard` uint64 tiyin, `Type` uint8 (0 Обычный, 1 Аванс, 2 Кредит), `Operation` uint8 (0 Продажа, 1 Возврат), `Location` {`Latitude`,`Longitude`} float64, `Items` [], `ExtraInfo`, `RefundInfo`, `FlightPassengerInfo`, `PaymentType` uint8 (1 cash, 2 card, 3 QR, 4 mixed).

Item: `Name`, `Barcode`, `Label` (deprecated), `Labels` [] (≤ 300 marking codes), `SPIC` (MXIK/IKPU), `Units` uint64 ("Единица измерения", a code), `PackageCode`, `OwnerType` uint8 ("см. справочник" — reference not published), `RecipeID` (≤ 32, optional), `Amount` uint64, `Price` uint64 ("Общая сумма позиции без учета скидок"), `Discount` uint64, `Other` uint64 ("Прочие (Оплата по страховки и др.)"), `VATPercent` uint8, `VAT` uint64, `CommissionInfo` {`TIN`,`PINFL`}.

ExtraInfo: `TIN`, `PINFL`, `PhoneNumber` (998XXYYYAABB), `CarNumber`, `QRPaymentID`, `QRPaymentProvider` uint16 (code list not published), `CashedOutFromCard`, `CardType` (1 corporate, 2 personal, 3 social), `CardNumber` (social card masked), `PPTID` (processing centre transaction id).

RefundInfo: `TerminalID`, `ReceiptSeq`, `DateTime` ("ГГГГММДДЧСМНСК"), `FiscalSign`. Wrong values → registration refused.

Constraints (both READMEs):
- `ReceivedCard + ReceivedCash − Σ(Price_i − Discount_i − Other_i) ≤ 10000` (100 so'm tolerance). Only an upper bound is stated.
- `Price_i − Discount_i − Other_i ≥ 0`; `Price_i > 0`; all amounts ≥ 0.
- From the FullReceipt TLV (acrsim): `Amount` is multiplied by 1000 ("1 шт = 1000, 0.5 кг = 500, 125 г = 125"); `Price` is "в тийинах ... с учетом кол-ва"; `NAME` 1–63 bytes in a custom one-byte charset (`TextCodecV2`), `SPIC` 1–18 ASCII digits, `PackageCode` 1–20 digits, `VAT_PERCENT` BCD 1 byte (whole number).
- NAME charset: Cyrillic incl. Ў Қ Ғ Ҳ, Latin A–Z/a–z, Ö Ç Ğ Ş Ü Ŋ, digits, `' " ! № @ ; # , $ : % / ^ & \` { } ( ) [ ] < > _ ~ + - = ?` and some symbols. The Uzbek letter signs ʻ (U+02BB) and ʼ (U+02BC) are not in the table; the ASCII apostrophe is.

### Z report

- `ZReport/Open` before any receipt; `ZReport/Close`; `ZReport/Info` → `TerminalID, OpenTime, CloseTime, TotalSaleCount, TotalRefundCount, TotalCash{Sale,Refund}, TotalCard{Sale,Refund}, TotalVAT{Sale,Refund}, FirstReceiptSeq, LastReceiptSeq` (+ `AcknowledgedTime` once sent).
- SW `0x9032 CANNOT_CLOSE_EMPTY_ZREPORT`: a Z cannot be closed without at least one sale or refund.
- `0x9040` at 29 999 operations: close and open a new Z.
- `Sync/ZReports` sends closed Z files to the OFD.
- FM keeps only cash and card accumulators (sale/refund) plus VAT; there is no separate QR accumulator [inference: QR money is reported in `ReceivedCard`; not stated].

### Offline limit and numbering

- `FiscalMemory/Info` → `ReceiptSeq` (last number), `LastOperationTime`, `FirstUnacknowledgedReceiptTime`, `ZReportsCount`, `ReceiptsCount` (unsent), accumulators. "если эта дата-время отстает на 2 или более дней от LastOperationTime то ФМ не даст выполнить операцию открыть/закрыть ZReport и зарегистрировать чек" (SW `0x9031 SEND_ALL_RECEIPTS_FIRST`).
- Numbering: the FM gives `ReceiptSeq`, one sequence per FM, advance and credit included. `0x9033 RECIPT_SEQ_MAX_VALUE_REACHED` → replace FM.
- Time must be strictly increasing: `0x9030 DATETIME_IS_IN_THE_PAST` "Передаваемое дата-время должно быть как минимум на 1 сек. позьже чем дата-время последней операции в ФМ"; `0x9091` if more than 2 days ahead.
- `0x9035/0x9037` refund larger than accumulated sales/VAT is refused.
- `0x90F1 RECEIPTS_MEMORY_FULL` → sync with server.
- Artix sets the till to block after 24 h offline ([docs.artix.su](https://docs.artix.su/pages/viewpage.action?pageId=93946891)) [PRIMARY].

### Signing, hashing, encryption (acrsim)

- `TotalBlock` sent to FM: `ITEMS_HASH` (32 bytes) of FullReceipt, `RECEIVED_CASH`, `RECEIVED_CARD`, `TOTAL_VAT`, `TIME`, `TYPE`, `OPERATION`, `ITEMS_COUNT`, `EXTRA`.
- Hash: "алг. 2 стандарт УзДСТ 1106:2009 sbox Gost-R-34-11-94-CryptoProParamSet".
- FM returns `FiscalSignInfo` (TerminalID, ReceiptSeq, Time, FiscalSign, CipherKey). The till encrypts FullReceipt with "ГОСТ 28147 sbox Gost-R-34-11-94-CryptoProParamSet".
- Receipt and Z files are "Зашифрованный и подписанны ЭЦП" by the FM.
- With FiscalDriveService all of this is done inside the service; the till only sends JSON.

### Fiscal sign and QR

- FiscalSign: 12 digits (BCD). QR: `https://ofd.soliq.uz/check?t=<TerminalID>&r=<ReceiptSeq>&c=<YYYYMMDDHHMISS>&s=<FiscalSign>`. Same format is used by Payme receipts (previous round, Payme docs) [PRIMARY].
- VM 943 1-ilova 7-band: QR at least 30×30 mm (previous round) [PRIMARY].

### POS lock (binding FM to the kassa software)

- "каждое ЦТО генерирует свой секретный ключ 32-байт (SecretKey) и хранит этот ключ в защищенном от постороннего доступа месте (например в коде программы кассы ...)". `/POS/Lock` with `SecretKey + SHA-256(SecretKey)` (hex). Then before every Z open/close and RegisterTXID: `/POS/Challenge` → `X-POS-Auth = SHA-256(SecretKey + Challenge)`. "Разблокировка возможно только по команде сервера ОФД."
- Purpose (acrsim): stop an FM taken out of one TXKM's kassa from being used in another TXKM's kassa.

### OFD transport (MessageV6, only needed without FiscalDriveService, e.g. Android SAM slot)

- Binary TLV message: `VERSION`=0x06, TLV (`TAG_REQUEST` 0x8a / `TAG_RESPONSE` 0x8b), CRC32 (Castagnoli). Sent over raw TCP (`TCPSender`, `java.net.Socket`), random server choice, "live server address" kept.
- File types: 0x01 PurchaseReceipt (FullReceiptFile), 0x02 ZReport, 0x03 ShortReceipt, 0x04 AdvanceReceipt, 0x05 CreditReceipt, 0xc8 VerifyFiscalSignQuery, 0xc9 SyncStateQuery. File versions: 0x02 (Z, short), 0x14 (full receipts).
- Status codes: OK, OKNotice, RetrySend, NotActive (FM inactive: keep files, send later), NotFound (TerminalID unknown), BadMessageSyntax/Struct, TooBigMessage, BadMessageCRC32, TooManyFiles. Per-file: Acknowledge, Reject, Error, UnrecognizedType.
- Refund flow step 3.1: the till first asks the OFD to verify the original receipt's fiscal sign (VerifyFiscalSignQuery).
- Production OFD server addresses are not published; the sample config uses `192.168.0.1:13447` (test server) and `192.168.0.1:4387` (emulator) [NOT FOUND for production].

## 4. Test environment

- `fiscal-drive-service devtool fiscal-drive-emulator` — FM 0400 emulator on TCP 4387, memory in RAM, new factory number every start, `--fail-apdu 001700 --fail-chance 25` to simulate FM connection failures at receipt registration.
- `fiscal-drive-service devtool test-server` — test OFD server on TCP 13447, state API on `http://127.0.0.1:13448/state` (lock/unlock, unbind POS: `ChangeLockState`, `LockState`, `POS Unlock`).
- `fiscal-drive-service api config.ini -o - -e 7 --server-address 127.0.0.1:13447 --use-fiscal-drive-emulator-address 127.0.0.1:4387`.
- `fiscal-drive-service devtool receipt generate` — sample JSON receipt.
- Test receipts: "Сервера ОФД не принимают тестовые чеки"; test server does not accept receipts from a registered FM.
- Emulator starts Locked: unlock through the test server state API, then `State/Sync`.
- **Blocker:** the binary (`Setup-FiscalDriveService-v10.XX.exe`, `fiscal-drive-service-v10.XX.tar.gz`) is not in the repository and not found anywhere public (search for both file names: no results) [NOT FOUND]. So the emulator and test server exist but must be obtained from the operator.
- Other open material: `@exode-team/ofd-uz` npm (base URL `https://test.ofd.uz`, `sendReceipt`, `sendQRPayment`, PEM key + certificate, "PKCS#7 signing"; fields `ReceiptSeq, IsRefund, Items, ReceivedCash, ReceivedCard, TotalVAT, Time, ReceiptType`; item `Name, SPIC, PackageCode, OwnerType, GoodPrice, Price, VAT, VATPercent, Amount`; publisher unnamed, MIT) ([unpkg README](https://unpkg.com/@exode-team/ofd-uz@1.0.4/README.md)) [MEDIA]. This looks like the certificate-based (virtual FM / marketplace) channel, not FM 0400 [inference].
- iiko plugin for Uzbek restaurants (March 2026) talks JSON-RPC to `http://127.0.0.1:3448/rpc/api` — the older FiscalDriveAPI ([davr.dev](https://davr.dev/projects/ofd)) [MEDIA]. Shows third-party restaurant software does use the local fiscal service.

## 5. MXIK catalogue (needed for "real-time loading of codes")

- Unofficial client `mxik-js` (last commit 2026-10-07) documents the public, no-auth API `https://tasnif.soliq.uz/api/cls-api`: `/elasticsearch/search`, `/mxik/search/by-params` (`text`, `brandName`, `mxikCode`, `gtin`), `/mxik/get/by-mxik`, `/integration-mxik/get/history/{code}`, `/integration-mxik/references/get/units/all` (unit codes), `/integration-mxik/references/lgota` (VAT benefits). Errors come as HTTP 200 with `success:false`. ([github.com/azabroflovski/mxik-js](https://github.com/azabroflovski/mxik-js), CLAUDE.md "tasnif.soliq.uz API quirks") [MEDIA: unofficial, undocumented by the Tax Committee].
