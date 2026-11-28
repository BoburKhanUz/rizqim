# OFD ("Yangi texnologiyalar" markazi) hujjatlari

Egasi ofd.uz saytidan yuklab bergan rasmiy hujjatlar (2026-11-28). Asl fayllar o'zgartirilmagan, faqat nomi qisqartirilgan.

## Qaysi yo'l

| Yo'l | Nima uchun | Holat |
|---|---|---|
| **Marketpleys** (`marketplace/`) | Mini App orqali onlayn buyurtmalar cheki | **Birinchi navbatda** |
| **Virtual kassa / onlayn-NKM** (`virtual-kassa/`) | Restoran zalidagi RIZQIM POS cheki | Keyinroq |

Virtual kassa yo'li qo'shimcha ravishda talab qiladi: Kiberxavfsizlik markazi ekspertizasi, muvofiqlik sertifikati (UNICON.UZ / O'zstandart), ehtiyot qismlar kafolat xati, restoranlarning TXKM bilan shartnomasi, 24 soat oflayn ishlaganda avtomatik bloklash, har bir kassaga alohida modul.

## Fayllar

### `marketplace/`

| Fayl | Mazmuni |
|---|---|
| `marketplace-guide-uz.pdf` | Marketpleys uchun ulanish yo'riqnomasi |
| `technical-instruction-v6.5.docx` | Texnik yo'riqnoma v6.5 (9 ta integratsiya bosqichi, CSR, test cheklar) |
| `integration-template-ru.pdf` | Integratsiya xati namunasi (ruscha) |
| `sample-application.docx` | Ariza namunasi |
| `application-registry-ru.pdf` | Soliq qo'mitasi reyestriga kiritish arizasi (ruscha) |

### `virtual-kassa/`

| Fayl | Mazmuni |
|---|---|
| `online-kkm-virtual-guide-uz.pdf` | Onlayn-NKM va virtual kassa ulanish yo'riqnomasi |
| `online-kkm-virtual-requirements-uz.pdf` | Virtual kassaga texnik talablar |
| `integration-template-uz.pdf` | Integratsiya xati namunasi |
| `application-conclusion-uz.pdf` | OFD xulosasini olish arizasi |
| `application-registry-uz.pdf` | Reyestrga kiritish arizasi |

## Marketpleys yo'li bosqichlari (qisqa)

1. OFD'ga integratsiya xati (`integration-template-ru.pdf` asosida) → shartnoma.
2. API hujjatlari va test kirish ma'lumotlari olinadi.
3. CSR (RSA 2048, PKCS#10, CN = kompaniya, UID) yuboriladi.
4. Test cheklar: sotuv, qaytarish, avans, kredit.
5. Xulosa arizasi; har bir virtual fiskal modul uchun yiliga 1 BHM.
6. Soliq qo'mitasi reyestriga ariza va 6 hujjat: OFD xulosasi, chek namunalari, ilova namunasi (APK/URL), texnik pasport va kafolat, qo'llanmalar (foydalanuvchi, o'rnatish, soliq inspektori), mualliflik guvohnomasi.
7. Prod sertifikat.

Har bir restoran my.soliq.uz'da RIZQIM'ni komissioner sifatida ro'yxatdan o'tkazadi (komitent/komissioner shartnomasi).

Bog'liq: [`../letters/`](../letters/README.md), [`../../PAYMENTS_GO_LIVE.md`](../../PAYMENTS_GO_LIVE.md).
