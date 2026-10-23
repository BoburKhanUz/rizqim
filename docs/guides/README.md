# Foydalanuvchi qo'llanmalari

Restoran xodimlari uchun oddiy tilda yozilgan qo'llanmalar (o'zbekcha, lotin). Texnik bilim kerak emas.

| Qo'llanma | Kim uchun | Nima haqida |
|---|---|---|
| [hamkor-panel.md](hamkor-panel.md) | Ega va menejer | Veb-panelga kirish, buyurtmalar doskasi, menyu, stop-list va qozon, xodimlar va PIN, filiallar, yetkazish zonalari, sozlamalar va planshet ulash, hisobotlar, moliya |
| [oshxona-ekrani.md](oshxona-ekrani.md) | Oshpaz, mangalchi va planshetni sozlovchi | Planshetni kod bilan ulash, PIN bilan kirish, chiptani o'qish, taomni "tayyor" deb belgilash va qaytarish, ranglar, "Aloqa yo'q"; sexlar, mangal ko'rinishi (six sanog'i), oldindan buyurtmalar, "O'CHIRILDI", qozon paneli, muammolar |
| [kassa.md](kassa.md) | Kassir (va menejer) | Kassani ulash, PIN va menejer tasdig'i, smena, zal xaritasi, buyurtma olish, oshxonaga yuborish, o'chirish, ko'chirish va birlashtirish, schyot va bo'lish, to'lov turlari, fiskal chek, qaytarish, bron va avans, nasiya, onlayn buyurtmalar, kirim-chiqim, X va Z, printer, "Internet uzilsa" (internetsiz rejim), muammolar |
| [ofitsiant.md](ofitsiant.md) | Ofitsiant | Telefon ilovasi: ulash va PIN, "Mening xonalarim", xonani ochish, taom qo'shish va yuborish, "Berildi", schyot so'rash, boshqa ofitsiantning buyurtmasi, aloqa yo'qolganda |
| [ega-pos-sozlash.md](ega-pos-sozlash.md) | Ega (va buxgalter) | Kassa tizimini birinchi sozlash: zal xaritasi, xona tariflari va bayramlar, choy-non, xizmat haqi (oldindan e'lon qilish), fiskal sozlamalar (STIR, soliq rejimi, MXIK), sexlar va printerlar, terminallar, xodimlar va PIN, smenalar va Z, bron, nasiya; **"Ikki marta hisoblamaslik"** |
| [mini-app-xodim.md](mini-app-xodim.md) | Ega, menejer, kuryer | Telegram Mini App: buyurtmalar, stop-list va qozon, xodimlar va PIN, filial ish vaqti, kuryer qadamlari |

## Qaysi qo'llanmadan boshlash kerak

- **Restoran egasi**: [hamkor-panel.md](hamkor-panel.md), so'ng kerak bo'lsa [mini-app-xodim.md](mini-app-xodim.md).
  Kassa tizimini (zal, xona narxi, fiskal chek, printer) sozlash uchun: [ega-pos-sozlash.md](ega-pos-sozlash.md). Avval
  uning boshidagi "Ikki marta hisoblamaslik" qutisini o'qing.
- **Menejer**: [hamkor-panel.md](hamkor-panel.md) (4–6, 9-bo'limlar) yoki telefonda [mini-app-xodim.md](mini-app-xodim.md) (4–5, 8-bo'limlar).
  Kassada menejer tasdig'ini berish: [kassa.md](kassa.md#menejer-tasdigi). Smena va Z hisobot: [ega-pos-sozlash.md](ega-pos-sozlash.md#11-smenalar-va-z-hisobotlarni-korish).
- **Oshpaz**: [oshxona-ekrani.md](oshxona-ekrani.md) (2–4-bo'limlar). Choyxonada qo'shimcha: sexlar, mangal, oldindan buyurtmalar, qozon (8–12-bo'limlar).
- **Kassir**: [kassa.md](kassa.md) (butun qo'llanma; avval 1–4-bo'limlar).
- **Ofitsiant**: [ofitsiant.md](ofitsiant.md).
- **Oshxona planshetini birinchi marta ulaydigan odam**: [oshxona-ekrani.md](oshxona-ekrani.md) (1-bo'lim) va [hamkor-panel.md](hamkor-panel.md) (8-bo'lim).
- **Kuryer**: [mini-app-xodim.md](mini-app-xodim.md) (10-bo'lim).

## Qisqa lug'at

- **Hamkor paneli** — kompyuter yoki planshetdagi veb-sahifa (Rizqim Hamkor).
- **Mini App** — Telegram ichida ochiladigan ilova.
- **Oshxona ekrani (KDS)** — oshxonadagi planshet, unda buyurtmalar chiptalar bo'lib turadi.
- **Chipta** — oshxona ekranidagi bitta buyurtma.
- **Stop-list** — hozir sotilmaydigan taomlar ro'yxati.
- **Qozon** — bir marta pishirilib, porsiyalab sotiladigan taom (osh) va uning qoldig'i.
- **Kirish raqami** — xodim SMS kod bilan kiradigan telefon raqami.
- **PIN** — planshetga kirish uchun 4–6 xonali kod.
- **Ulash kodi** — planshetni do'konga ulaydigan 8 belgili bir martalik kod (`ABCD-EFGH`).
- **Kassa** — kassir ishlaydigan ilova (planshet, Sunmi yoki Windows monoblok).
- **Ofitsiant ilovasi** — ofitsiantning telefonidagi ilova.
- **Sex** — taom tayyorlanadigan joy: osh, mangal, salat va bar, choy. Har sexning o'z printeri yoki ekrani bor.
- **Bosqich** — xona hisobiga bir vaqtda qo'shilgan taomlar (choy → salat → osh → yana choy).
- **Schyot** — mehmonga beriladigan hisob. Bu fiskal chek emas. To'langandan keyin **fiskal chek** chiqadi.
- **Fiskal kutmoqda** — pul olingan, lekin sotuv cheki hali chiqmagan schyot. Chek chiqquncha smena yopilmaydi.
- **Menejer tasdig'i** — kassirda huquq bo'lmagan amalni menejer o'z PIN'i bilan tasdiqlashi.
- **Smena** — bitta kassaning ish davri. **X** — smenani yopmasdan oraliq hisobot, **Z** — smenani yopish hisoboti.
- **Bron, avans, "Keldi"** — oldindan band qilingan xona; bron uchun oldindan olingan pul; mehmonlar kelganda bronni buyurtmaga aylantirish.
- **Nasiya** — pulni keyinroq to'lash sharti bilan hisobga yozish. Qarz to'langanda kredit cheki chiqadi.
- **MXIK** — taom yoki xizmatning 17 xonali soliq tasnifi kodi. Fiskal chekda majburiy.
- **STIR** — korxonaning 9 xonali soliq to'lovchi raqami.

Qo'llanmalar hozirgi ekran matnlariga qarab yozilgan. Ekranda boshqacha yozuv ko'rsangiz, ilova yangilangan bo'lishi mumkin.
