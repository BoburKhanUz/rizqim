# Dinamik UzQR va fiskal chek

Tekshirilgan sana: 21.11.2026.

- Umumiy ovqatlanishda har qanday to'lov (naqd va naqdsiz) uchun onlayn-NKM yoki virtual kassa cheki beriladi (VM 75; avvalgi hisobot "Choyxona ishlashi va POS talablari") [PRIMARY, avvalgi tadqiqot].
- 3817-son qoidalarda fiskal chek haqida gap yo'q [PRIMARY].
- Statik UzQR chekni fiskallashtirmaydi; dinamik QR'da chek kassa orqali chiqadi [MEDIA buxgalter.uz 30.06.2026].
- Kassa sotuvchilari (SetRetail10, R-Keeper, REGOS) chekni **o'zlari** chiqaradi, UzQR to'lovi karta to'loviga tenglashtiriladi; keyin chek havolasi UzQR'ga yuboriladi [PRIMARY vendor hujjatlari].
- Operatorning 0,20% ulushiga "onlayn-NKM bilan integratsiya" kiradi [PRIMARY cbu.uz 23.06.2026] — ya'ni UzQR o'zi chek chiqarmaydi, kassalar bilan bog'lanadi.
- Ipak Yo'li sahifasi "fiskal cheklar sotuvchi nomidan avtomatik yaratiladi va Telegram bot orqali yuboriladi" deydi [PRIMARY bank sahifasi]. Bu dinamik QR'ga ham tegishlimi, noaniq. Agar bank ham chek chiqarsa — **ikki marta chek** xavfi. Bankdan yozma so'rash kerak.
- Ekvayer (bank) fiskal ma'lumot (IKPU, pozitsiyalar) qabul qilishi yoki yuborishi topilmadi [NOT FOUND]. UzQR API faqat chek **havolasini** qabul qiladi (CSI).
- Xulosa: sotuv bizniki, chekni Rizqim (P3-32 fiskal drayveri) chiqaradi; to'lov turi — naqdsiz (karta), RRN va UzQR operatsiya id chekka va hisobotga yoziladi; chek havolasi UzQR'ga yuboriladi.
