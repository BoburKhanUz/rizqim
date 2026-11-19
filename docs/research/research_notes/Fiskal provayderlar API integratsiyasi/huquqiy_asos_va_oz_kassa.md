# Fiskal chek chiqarishning huquqiy va texnik asosi (O'zbekiston, 2026-noyabr holatiga): uchinchi tomon restoran POS mahsuloti uchun yo'llar

Yozilgan sana: 2026-11-19. Qoida: har bir fakt manbasi bilan; 2025 dan eski manba "ESKI" deb belgilangan. Matn ingliz/o'zbek aralash, qonun va atamalarning asl nomlari saqlangan. Avvalgi hisobot (`reports/POS bozori narxlar va fiskal terminallar.md`, 7-bo'lim) bilan mos kelmaydigan joylar "TUZATISH" deb belgilangan.

Metodika eslatmasi: lex.uz sahifalari juda uzun (VM 943 ~285 000 belgi, VM 393 ~324 000 belgi), men ularni faqat kesim-kesim o'qiy oldim. 4-ilovaning (reyestr tartibi) matni lex.uz da rasm ko'rinishida va men uni o'qiy olmadim.

## 1. Fiskal qurilma/dastur toifalari, OFD, chek soliq tizimiga qanday yetadi

### Takeaway
Asosiy hujjat hamon VM 943 (23.11.2019) va uning ilovalari; u 2026-yilda ketma-ket o'zgargan (23.01.2026, 21.07.2026, 16.09.2026, 22.10.2026). Toifalar: onlayn-NKM (jismoniy fiskal modul bilan), virtual kassa (fiskal modulga ulanadigan dastur) va 2026-yildan "virtual fiskal modul" (operator beradigan noyob raqam + ERI ochiq kalit sertifikati). Yagona fiskal ma'lumotlar operatori (OFD) — Soliq qo'mitasi huzuridagi "Yangi texnologiyalar" markazi.

### Cited Findings
- VM 943 ning 1-ilovasi ta'rifi: virtual kassa — "fiskal modulga bog'lanish imkoniyatiga ega bo'lgan, fiskal hujjatlarni hosil qiluvchi" dasturiy mahsulot, hujjatlarni operatorga real vaqtda yuboradi — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- Virtual fiskal modul: "operator tomonidan yaratiladigan unikal raqam va unga biriktirilgan elektron raqamli imzo ochiq kalit sertifikati" — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329) (uning kiritilishi VM 23 dan 23.01.2026, avvalgi hisobotga ko'ra; VM 943 sahifasida 23.01.2026 tahriri va "23.01.2026 dagi VM 23" havolasi bor)
- Fiskal ma'lumotlar operatori: "fiskal ma'lumotlarni mustaqil va doimiy ravishda qabul qilish, qayta ishlash va saqlash" bilan shug'ullanuvchi, "Yangi texnologiyalar" ilmiy-axborot markazi (Soliq qo'mitasi huzurida) — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329); [buxgalter.uz, FM operator haqi, 05.10.2026](https://buxgalter.uz/uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity)
- Fiskal modul (jismoniy) TXKM (texnik xizmat ko'rsatish markazi) orqali onlayn-NKM yoki virtual kassani ishga tushirishda beriladi — [buxgalter.uz FM savollari](https://buxgalter.uz/ru/publish/doc/text165671_ofd_otvetil_na_5_goryachih_voprosov_po_fiskalnym_modulyam) (ESKI/ikkilamchi)
- Chek soliq tizimiga yetishi: chek soliq to'lovchi kabinetida "ro'yxatdan o'tgan cheklar" ro'yxatida va QQS hisobotida ko'rinadi (my3.soliq.uz) — [buxgalter.uz, 2026](https://buxgalter.uz/ru/publish/doc/text165671_ofd_otvetil_na_5_goryachih_voprosov_po_fiskalnym_modulyam) (qidiruv xulosasi; ofd.soliq.uz alohida tekshirilmadi)
- Chekni tekshirish QR kodi kamida 30x30 mm; qog'oz kengligi kamida 55 mm; belgi balandligi kamida 2 mm — [lex.uz VM 943, 1-ilova, 7-band](https://lex.uz/uz/docs/-4603329)
- Reyestrlar manzili: onlayn-KKM https://ofd.uz/ru/pages/view/47, virtual kassalar https://ofd.uz/ru/pages/view/55, TXKM https://ofd.uz/ru/pages/view/48 (reyestrlar soliq.uz ham e'lon qilinadi) — [gov.uz/soliq, 28.04.2026](https://gov.uz/ru/soliq/news/view/159141)
- VM 943 tahrirlari (sahifadagi ro'yxat): 30.09.2025 (VM 610), 23.01.2026 (VM 23), 21.07.2026 (VM 393), 16.09.2026, 22.10.2026 — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- 16.09.2026 tahriri PF-191 (10.09.2026) asosida: retsept dorilar uchun elektron retsept raqami chekda — sahifa bayoni; loyiha haqida [anhor.uz, 28.05.2026](https://anhor.uz/uzl/iqtisodiyot/qqs-raqamli-markirovka-boyicha-yangi-qoidalar) (regulation.gov.uz muhokamasi 25.05–09.06.2026; restoranga taalluqli emas)

### Inferences
- Restoran POS uchun "virtual kassa" va "virtual fiskal modul" muhim; "onlayn-NKM + jismoniy FM" yo'li terminal sotuvchilariga qoladi.
- "2-in-1 terminal" alohida huquqiy toifa sifatida topilmadi; huquqiy jihatdan u TXKM xizmat ko'rsatadigan onlayn-NKM (yoki bank bilan bog'liq kassa) sifatida ko'rinadi.

### Gaps
- Aniq "ofd.soliq.uz" domenining roli tasdiqlanmadi: manbalarda ofd.uz va my3.soliq.uz uchraydi, ofd.soliq.uz uchramadi.
- 2-in-1 terminal toifasi bo'yicha rasmiy ta'rif topilmadi.
- Chekdagi QR/fiskal belgi tuzilmasi (maydonlar) o'qilmadi (2-ilova matni sahifada faqat sarlavhalar).

## 2. VM 393 (20.07.2026) va umumiy ovqatlanish integratsiyasi

### Takeaway
VM 393 — 20.07.2026 dagi "Hukumatning ayrim qarorlariga o'zgartirish va qo'shimchalar kiritish ..." qarori (21.07.2026 dan kuchda). U VM 943 ni o'zgartirgan; umumiy ovqatlanishga oid taqiq 22.10.2026 dan kuchga kirgan (avvalgi hisobotdagi hisob: e'lon + 3 oy). Shu bilan birga, mustaqil manbalardan tasdiq: lex.uz VM 943 sahifasida 3-ilovaning yangi 161-bandi "22 oktabr 2026" dan kuchda deb ko'rsatilgan.

### Cited Findings
- VM 393 nomi: "On amendments and additions to certain decisions of the Government ... as well as deeming some of them to have lost force", Bosh vazir A. Aripov, kuchga kirishi 21.07.2026 — [lex.uz/docs/8343199](https://lex.uz/docs/8343199) (sahifada faqat sarlavha; operativ matn yo'q). To'liq matn: [lex.uz/ru/docs/8343102](https://lex.uz/ru/docs/8343102) (sarlavha "Ўзбекистон Республикаси Ҳукуматининг айрим қарорларига ўзгартириш ва қўшимchalar киритиш"). Ikki lex.uz hujjat identifikatori turlicha (8343199 vs 8343102); ikkalasi bir qarorga tegishli deb olindi, lekin buni lex.uz da bevosita tekshirmadim.
- VM 393 bo'yicha VM 943 ga o'zgartirishlar (14-band): 1-ilova 2-band "QR-kodli to'lov" ta'rifi; 1-ilova 7-band 21-xatboshi: "to'lov shakli: naqd, bank kartalari, QR-kodli to'lov yoki aralash"; 2-ilova 3-band: "banklarda generatsiya qilingan yagona QR-kodni shakllantirish axborot tizimi hamda elektron to'lov tizimlariga majburiy ulanishi" va "barcha bank kartalari va QR-kodlar orqali to'lovlarni to'g'ridan-to'g'ri shu qurilma orqali qabul qilish imkoniyati" (asl matn kirill) — [lex.uz/ru/docs/8343102](https://lex.uz/ru/docs/8343102)
- 3-ilova yangi 161-band (22.10.2026 dan): TXKM yoki tadbirkorlik sub'yektlari "soliq organlarining axborot tizimlari bilan integratsiya qilinmagan" hisob/buyurtma yuritish dasturlaridan foydalanishga imkon bermaydi — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329) (sahifa bayoni: katering so'zi shu bandning ko'rilgan qismida yo'q); [lex.uz/ru/docs/8343102](https://lex.uz/ru/docs/8343102) (161-band raqami kirill)
- 1-ilova 4-band yangi tahriri: reyestrdagi onlayn-NKM yoki virtual kassa bilan bir vaqtda boshqa (ikkilamchi) hisob tizimini ishlatish taqiqlanadi; avvalgi hisobot so'zma-so'z keltiradi: "umumiy ovqatlanish korxonalari tomonidan soliq organlarining axborot tizimlari bilan integratsiya qilinmagan ... buyurtmalar hisobini yuritish dasturiy mahsulotlaridan foydalanish taqiqlanadi" — `reports/POS bozori narxlar va fiskal terminallar.md`, 5-bo'lim (men bu iborani lex.uz da mustaqil ko'ra olmadim; lex.uz kesimida 4-band birinchi xatboshisi PF-5813 ga havola bilan boshlanadi) — [lex.uz/ru/docs/8343102](https://lex.uz/ru/docs/8343102)
- "Integratsiya" ta'rifi: VM 393 matnida alohida ta'rif topilmadi. Amaliy mazmun — hisob dasturi OFD bilan integratsiya shartnomasi + OFD xulosasi + Kiberxavfsizlik markazi xulosasi + Soliq qo'mitasi reyestriga kiritilish (2023 tavsifi) — [buxgalter.uz, 26.12.2023, ESKI](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini_soliq_organlarining_ahborot_tizimlari_bilan_integraciya_qilish_talablari)
- Jarima: Soliq kodeksi 227-1 modda (chek fiskal belgilari, tegishli axborot tizimlari bilan integratsiya, majburiy raqamli markirovka buzilishi), jarima oxirgi hisobot choragida sotuv bo'lgan sof tushumga nisbatan — [buxgalter.uz, 2023, ESKI](https://buxgalter.uz/uz/publish/doc/text188587_mojet_li_obshchepit_rabotat_bez_skanera_kodov_cifrovoy_markirovki). Pog'onalar (0,2% / 0,4% / 1% / 2%, takroriy ogohlantirishdan keyin; asos PP-190 23.05.2025, 2-ilova) — [buxgalter.uz, "Чеки под контролем"](https://buxgalter.uz/publish/doc/text211946_cheki_pod_kontrolem_chto_delat_esli_kassa_ne_sootvetstvuet_trebovaniyam); bu pog'onalar markirovka/chek mosligiga oid maqola bayoni, aynan 393 taqiqiga qo'llanishi tasdiqlanmagan.
- Texnik talablarga mos kelmaydigan KKT ishlatish: 20 mln so'm (SK 221-modda 4-qism), almashtirish muddati 15 kun (VM 943 4-ilova 22-band) — [buxgalter.uz, 2026-11](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra)
- Loyiha bosqichi (ESKI emas, 2026-02-02): Soliq qo'mitasi loyihasi — 01.06.2026 gacha umumiy ovqatlanish korxonalari buyurtma hisobi axborot tizimlari bilan to'liq jihozlanib, kassa uskunasiga integratsiya qilinishi; 227-1 modda javobgarligi — [kun.uz, 02.02.2026](https://kun.uz/en/news/2026/02/02/uzbekistan-plans-expanded-digital-oversight-to-reduce-informal-economic-activity); loyiha raqami berilmagan.
- Boshqa shartlar: 01.11.2026 sanasi VM 943 sahifasida VM 393 bilan bog'liq havola sifatida uchraydi, ma'nosi aniq emas — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)

### Inferences
- 22.10.2026 dan "kassa + alohida integratsiyalanmagan buyurtma dasturi" sxemasi taqiqlangan (matn bo'yicha). Rizqim bu taqiqning ikki tomonida: u o'zi integratsiyalangan bo'lishi kerak (provayder orqali yoki o'z virtual kassasi sifatida).
- TXKM ga ham javobgarlik (161-band) qo'yilgani sababli TXKM lar integratsiyalanmagan POS ga "bu kassa bilan ishlaydi" demaydi; bu Rizqim uchun bozor bosimi.

### Gaps
- VM 393 ning oxirgi (kuchga kirish) bandi va 4-band umumiy ovqatlanish xatboshisini lex.uz dan to'g'ridan-to'g'ri o'qiy olmadim (sahifa uzunligi); so'zma-so'z iqtibos faqat avvalgi hisobotga tayanadi.
- "Integratsiya" huquqiy ta'rifi, nazorat mexanizmi va umumiy ovqatlanishga alohida jarima summasi topilmadi.
- buxgalter.uz "Правовые основы..." maqolasi pullik (Buxgalter Pro); VM 393 ga havola borligi ko'rindi xolos — [buxgalter.uz](https://buxgalter.uz/oz/publish/doc/text207135_pravovye_osnovy_deyatelnosti_predpriyatiy_obshchestvennogo_pitaniya1)
- Rasmiy Soliq qo'mitasi tushuntirishi (22.10.2026 dan keyin) topilmadi.

## 3. O'z virtual kassasini ro'yxatdan o'tkazish; narx; muddat; O'zbek vendorlari

### Takeaway
Yo'l: OFD bilan integratsiya (shartnoma + CSR/sertifikat + OFD xulosasi) -> Kiberxavfsizlik markazi xulosasi -> Soliq qo'mitasiga ariza (10 hujjat) -> reyestrga kiritish -> TXKM hamkor bilan FM shartnomasi. Narxi: ro'yxatdan o'tkazishga soliq to'lovi yo'q; FM operator haqi yiliga 1 BHM. Aniq vaqt va sertifikatlash tashkiloti narxi topilmadi.

### Cited Findings
- Tartib (2023 tavsifi, ESKI): (1) FMO "Yangi texnologiyalar" ga ariza va integratsiya shartnomasi; (2) FMO xulosasi va "Kiberxavfsizlik markazi" DUK xulosasi; (3) Soliq qo'mitasiga ariza; ilovalar: dastur shabloni, muvofiqlik sertifikati, reyestrga kiritish arizasi, hosil qilingan chek namunasi, dasturning texnik pasporti, foydalanuvchi hujjatlari (qo'llanma + soliq inspektori yo'riqnomasi), ehtiyot qismlar kafolat xati, Kiberxavfsizlik markazi xulosasi, FMO xulosasi, mualliflik huquqi (patent). Rad etilsa kamchilik tuzatib qayta murojaat. Muqobil: ro'yxatdan o'tgan TXKM dasturni qo'llab-quvvatlaydi, TXKM–FMO FM shartnomasi — [buxgalter.uz, 26.12.2023](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini_soliq_organlarining_ahborot_tizimlari_bilan_integraciya_qilish_talablari)
- Marketpleys uchun o'xshash tartib (2022, ESKI): OFD ga yozma ariza, integratsiya shartnomasi, CSR fayl, OFD dan virtual FM sertifikati; reyestr uchun hujjatlar (chek namunalari, APK/URL, hujjatlar, mualliflik hujjati); qaror 15 ish kuni — [buxgalter.uz, 02.08.2022](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov). Marketpleys reyestri restoran kassasidan alohida; restoran uchun foydalanish-mumkinligi aniqlanmagan.
- Avvalgi hisobot: OFD xulosasi 10 ish kuni, Kiberxavfsizlik markazi 15 ish kuni — VM 943 4-ilovasiga havola. TUZATISH: men VM 943 sahifasida bu raqamlarni tasdiqlay olmadim (4-ilova rasm; 15 ish kuni marketpleys qoidasi 14-bandida uchraydi) — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- Texnik talablar (2-ilova, 6-band, virtual kassa): versiya raqami va seriya/litsenziya raqami; fiskal modulsiz foydalanish cheklangan; 48 soatlik oflaynda avtomatik blokirovka; reyestrda bo'lmagan versiya ishlatilmaydi — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- Yagona FM operator haqi: yiliga 1 BHM (1-ilova, 55-band); 05.10.2026 dagi sahifada 440 000 so'm (BHM qiymati sahifada aytilmagan, 10% = 44 000 deb chiqarilgan); ERI: yuridik shaxs 10% BHM, jismoniy 7% BHM, 2 yil — [buxgalter.uz, 05.10.2026](https://buxgalter.uz/uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity); [lex.uz VM 943](https://lex.uz/uz/docs/-4603329). TUZATISH: avvalgi hisobotda 412 000 so'm yoki 440 000 (1.09.2026) noaniq edi; 05.10.2026 manba 440 000 beradi, 1.09.2026 dan 440 000 degan versiyani ma'qullaydi (BHM qiymatini rasmiy manbadan tasdiqlamadim).
- O'zi ro'yxatdan o'tgan virtual kassa uchun vakolatli organdagi depozit-zaxira talabi qo'llanmaydi (2023 maqola, ESKI) — [buxgalter.uz, 2023](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini_soliq_organlarining_ahborot_tizimlari_bilan_integraciya_qilish_talablari); TXKM zaxirasi 100 dan 1000 BHM gacha (3-ilova 7-band) — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- TXKM: ariza qabul qilishdan 4 ish soati, soliq xodimi 8 ish soati; ro'yxatdan o'tkazish bepul (1-ilova 16-25 bandlar; bu onlayn-NKM/virtual kassa foydalanuvchisini soliq organida ro'yxatga olish, dasturni reyestrga kiritish emas) — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- Reyestrdan chiqarilgan vendorlar: "Uzkass-185" (onlayn-KKM) va "Bestpos" MChJ (TXKM) — 28.04.2026 — [gov.uz/soliq](https://gov.uz/ru/soliq/news/view/159141); virtual kassa "E-POS" (E-POS SYSTEMS MChJ) 30.10.2026 reyestrdan chiqarilgan, E-POS TXKM xabari 07.11.2026, foydalanuvchilar muddati 14.11.2026 gacha — [buxgalter.uz, 11.2026](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra). MUHIM: avvalgi hisobot E-POS ni zaxira provayder sifatida tavsiya qilgan; bu xavfli.
- Reyestrdagi vendorlar: Multikassa — "Virtual kassalar davlat reyestri"da deb ko'rsatilgan — [Poster katalogi](https://joinposter.com/en/applications/multikassa) (vendor sahifasi, reyestr yozuvi raqamsiz). Rahmat: virtual kassa qo'llanmasi VM 943 reglamentiga asoslangan (qo'llanmada 23.11.2020 deb yozilgan, ehtimol 2019 ning xatosi) — [Rahmat qo'llanmasi (PDF)](https://rhmt.uz/upload/Руководство_пользователя_Rahmat_Виртуальная_касса.pdf) (qidiruv bayoni; faylni o'zim ocha olmadim, keyingi urinishda 404). REGOS, BILLZ, Poster, iiko, Jowi, Alipos reyestr holati: tasdiqlanmadi.
- Mavjud eski reyestr tavsifi (2023): R-Keeper, JOWi, iiko "integratsiya qilishi" kerak degan dasturlar sifatida tilga olingan, reyestr yozuvi sifatida emas — [buxgalter.uz, 2023](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini_soliq_organlarining_ahborot_tizimlari_bilan_integraciya_qilish_talablari)

### Inferences
- Eng uzun/noaniq qadam OFD va Kiberxavfsizlik markazi xulosalari va "muvofiqlik sertifikati" (kim beradi — topilmadi). Avvalgi hisobotdagi 3–6 oy taxmini mustaqil tasdiqlanmadi.
- E-POS ning chiqarilishi tasdiqlaydi: reyestr shartlarini ushlab turish (ogohlantirishlardan keyin) doimiy xarajat; bitta provayderga bog'liqlik xavfi real.

### Gaps
- Reyestrning joriy to'liq ro'yxati (ofd.uz/ru/pages/view/55) 503 xato bilan ochilmadi; shuning uchun vendorlar ro'yxati yo'q.
- Sertifikatlash organi, haq va aniq muddat topilmadi; virtual kassa narxi (Rahmat/Multikassa) topilmadi.
- Marketpleys reyestriga o'xshash "virtual FM" yo'li restoran POS uchun ochiqligi rasman tasdiqlanmadi.

## 4. Chek talablari: MXIK, QQS, bo'nak, nasiya, qaytarish, aralash to'lov, markirovka, xizmat haqi

### Takeaway
VM 943 1-ilova 7-band chek turlarini belgilaydi: sotuv, qaytarish, bo'nak, bo'lib to'lash, kredit; to'lov shakli naqd/karta/QR/aralash. Mahsulot kodi — yagona elektron milliy katalog (MXIK) kodi. Markirovka va xizmat haqi bo'yicha aniq talablar kam topildi.

### Cited Findings
- Chek maydoni "to'lov turi (sotuv, qaytarish, bo'nak, bo'lib to'lash, kredit)"; bo'nak, bo'lib to'lash va kredit cheklarida QR kod va fiskal belgi bo'lmaydi — [lex.uz VM 943, 7-band](https://lex.uz/uz/docs/-4603329)
- To'lov shakli: "naqd, bank kartalari, QR-kodli to'lov yoki aralash" (21.07.2026 tahriri) — [lex.uz/ru/docs/8343102](https://lex.uz/ru/docs/8343102)
- Mahsulot identifikatsiya kodlari — yagona elektron milliy katalog; GS1 va boshqa global kodlar bor joyda mos kelishi kerak — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329); katalogsiz kod chiqarmaydigan KKM/virtual kassa ishlatish mumkin emas — [gov.uz/soliq](https://gov.uz/ru/soliq/news/view/19560) (2024, ESKI)
- Qaytarish: foydalanuvchi alohida qaytarish cheki shakllantiradi (38-band) — [lex.uz VM 943](https://lex.uz/uz/docs/-4603329)
- Markirovka: raqamli markirovkali tovar chekida kod kiritilgandan keyingina chek shakllanadi, takroriy foydalanish mumkin emas, 01.09.2025 dan (PP-190, 23.05.2025, 7-band "v"); ishlab chiqaruvchilar 28.11.2025 gacha dasturni tuzatishi kerak; 01.03.2026 dan muddati o'tgan markirovkali tovarga chek chiqmasligi (PP-296 06.10.2025, 1-band) — [buxgalter.uz](https://buxgalter.uz/publish/doc/text211946_cheki_pod_kontrolem_chto_delat_esli_kassa_ne_sootvetstvuet_trebovaniyam)
- Umumiy ovqatlanish: tayyor taomlarda markirovka yo'q; alkogol majburiy markirovkada; skaner (2D) talabi 01.01.2022 dan (PP-5252, 04.10.2021) — [buxgalter.uz, 03.2023, ESKI](https://buxgalter.uz/uz/publish/doc/text188587_mojet_li_obshchepit_rabotat_bez_skanera_kodov_cifrovoy_markirovki). Anhor.uz loyihasida VM 478 (03.08.2024) 3-bandi, ya'ni markirovkali tovarni chakana sotish tartibi, olib tashlanishi kutilgani yozilgan (loyiha, tasdiqlanmagan) — [anhor.uz, 28.05.2026](https://anhor.uz/uzl/iqtisodiyot/qqs-raqamli-markirovka-boyicha-yangi-qoidalar)
- Chek maydonlari: QQS maydonlari, nasiya (kredit) chek ketma-ketligi, xizmat haqi (xona puli, 10–15% xizmat) bo'yicha rasmiy talab topilmadi.

### Gaps
- QQS stavkasi/ajratilishi va xizmat haqi uchun aniq chek maydoni; bo'nak va keyingi yakuniy chek bog'lanishi (avans zachyoti) rasman o'qilmadi (2-ilova matni sahifada yo'q).
- Chek JSON strukturasi (`Type`, `Operation`, `PaymentType`) faqat avvalgi hisobotdagi GitHub (qo'p/fiscal-drive-service) ga tayanadi; men uni tekshirmadim.

## 5. Rasmiy API / protokol spetsifikatsiyasi

### Takeaway
Ommaviy rasmiy API/protokol hujjati topilmadi. Integratsiya OFD bilan shartnoma va sertifikat/CSR orqali yopiq tarzda.

### Cited Findings
- OFD operatori: NITs "Yangi texnologiyalar", e-pochta info@yt.uz, tel. 71 202-32-32 (buxgalter.uz bo'yicha) — [buxgalter.uz FM savollari](https://buxgalter.uz/ru/publish/doc/text165671_ofd_otvetil_na_5_goryachih_voprosov_po_fiskalnym_modulyam)
- Rasmiy portallar: https://ofd.uz (reyestrlar, marketpleys tartibi sahifalari /pages/view/71, /72) — [buxgalter.uz, 2022](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov); tasnif.soliq.uz (MXIK katalogi) — [Poster Multikassa sahifasi](https://joinposter.com/en/applications/multikassa)
- Marketpleys tartibida protokol nomlanmaydi; faqat CSR, virtual FM sertifikati va OFD ga elektron chek yuborish — [buxgalter.uz, 2022](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov)
- Provayder API'lari: REGOS API hujjati (kassa uchun umumiy savdo API, reyestr holati yo'q) — [docs.regos.uz](https://docs.regos.uz/uz/api/intro/quick-start); E-POS hujjati docs.epos.uz (avvalgi hisobotda tilga olingan, kirmadim); mahalliy FiscalDriveService (GitHub) — `reports/POS bozori narxlar va fiskal terminallar.md` 6-bo'lim.

### Gaps
- Soliq qo'mitasi/OFD ning ommaviy API spetsifikatsiyasi (URL) topilmadi: qidiruv natijalari faqat Rossiya (Saby, Kontur) hujjatlarini berdi, ular O'zbekistonga tegishli emas.

## Qisqa o'zgarishlar jadvali (2025–2026)

| Sana | O'zgarish | Manba |
|---|---|---|
| 23.05.2025 / 01.09.2025 | PP-190: markirovkali tovarda kodsiz chek yo'q; jarima pog'onalari | [buxgalter.uz](https://buxgalter.uz/publish/doc/text211946_cheki_pod_kontrolem_chto_delat_esli_kassa_ne_sootvetstvuet_trebovaniyam) |
| 23.01.2026 | VM 23: virtual FM, 3-ilova 18-band bekor | [lex.uz VM 943](https://lex.uz/uz/docs/-4603329) |
| 02.02.2026 | Loyiha: umumiy ovqatlanishda buyurtma hisobi + kassa integratsiyasi 01.06.2026 gacha | [kun.uz](https://kun.uz/en/news/2026/02/02/uzbekistan-plans-expanded-digital-oversight-to-reduce-informal-economic-activity) |
| 28.04.2026 | Uzkass-185 va Bestpos reyestrdan chiqdi | [gov.uz](https://gov.uz/ru/soliq/news/view/159141) |
| 20.07.2026 (21.07 dan) | VM 393: QR/karta to'g'ridan-to'g'ri qurilmada; ikkilamchi tizim taqiqi; TXKM qoidalari | [lex.uz](https://lex.uz/ru/docs/8343102) |
| 16.09.2026 | e-retsept raqami chekda (dorixona) | [lex.uz VM 943](https://lex.uz/uz/docs/-4603329) |
| 22.10.2026 | VM 393 ning umumiy ovqatlanish va TXKM bandlari kuchga kirdi (161-band) | [lex.uz VM 943](https://lex.uz/uz/docs/-4603329) |
| 30.10.2026 / 14.11.2026 | E-POS virtual kassasi reyestrdan chiqarildi; almashtirish muddati | [buxgalter.uz](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra) |
