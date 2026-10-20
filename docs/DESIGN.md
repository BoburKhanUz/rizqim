# Rizqim dizayn tizimi (Mini App)

Bitta manba: `mini-app/src/index.css` (`--rz-*` o'zgaruvchilar va Tailwind `@theme`). Komponentlar:
`mini-app/src/components/ui/`. Formatlash: `mini-app/src/lib/format.ts`. Telegram: `mini-app/src/lib/twa.ts`,
`lib/dialogs.ts`, `hooks/useMainButton.ts`.

Mavzu (light/dark) Telegram'dan olinadi: `lib/twa.ts` `<html data-theme>` ni `WebApp.colorScheme` bo'yicha qo'yadi va
`themeChanged` da yangilaydi. Telegram tashqarisida tizim sozlamasi (`prefers-color-scheme`) ishlaydi. Telegram
ranglari faqat `page`, `surface`, `fg`, `fg-subtle` uchun ishlatiladi; `fg-muted`, brend va holat ranglari — Rizqim'niki.

## Palitra

| Token (Tailwind nomi) | Light | Dark | Qayerda |
|---|---|---|---|
| `page` | TG secondary_bg, `#F4F5F7` | TG secondary_bg, `#0E1621` | Sahifa foni (body) |
| `surface` | TG section_bg/bg, `#FFFFFF` | TG section_bg/bg, `#17212B` | Kartochka, nav, sheet |
| `surface-2` | `#F1F3F5` | `rgba(255,255,255,.06)` | Input, chip, ikkinchi tugma |
| `line` | `rgba(15,23,42,.08)` | `rgba(255,255,255,.08)` | Chegara, ajratgich |
| `fg` | TG text, `#0F172A` | TG text, `#F1F5F9` | Asosiy matn |
| `fg-muted` | `#5F6B7A` | `#9AA8B6` | Ikkinchi darajali matn (Telegram hint EMAS) |
| `fg-subtle` | TG hint, `#94A3B8` | TG hint, `#7D8B99` | Faqat placeholder / disabled |
| `brand` / `on-brand` | `#C2410C` / `#FFFFFF` | `#FB923C` / `#1C1917` | Asosiy tugma, faol tab, narx |
| `brand-soft` / `brand-soft-fg` | `#FFF1E6` / `#9A3412` | `rgba(251,146,60,.16)` / `#FDBA74` | Yumshoq brend fon |
| `accent` | `#F59E0B` | `#FBBF24` | Faqat bezak (yulduzcha, nuqta) — ostida matn yo'q |
| `success` / `on-success` / `success-soft` / `success-soft-fg` | `#15803D` / `#FFF` / `#E8F6EE` / `#166534` | `#4ADE80` / `#052E16` / `rgba(34,197,94,.16)` / `#86EFAC` | Yetkazildi, to'landi |
| `warning` / `on-warning` / `-soft` / `-soft-fg` | `#B45309` / `#FFF` / `#FEF3C7` / `#92400E` | `#FBBF24` / `#1C1917` / `rgba(251,191,36,.16)` / `#FCD34D` | Kutilmoqda, ogohlantirish |
| `danger` / `on-danger` / `-soft` / `-soft-fg` | `#DC2626` / `#FFF` / `#FDECEC` / `#991B1B` | `#F87171` / `#1C1917` / `rgba(239,68,68,.16)` / `#FCA5A5` | Bekor, xato, o'chirish |
| `info` / `on-info` / `-soft` / `-soft-fg` | `#2563EB` / `#FFF` / `#EAF1FE` / `#1E40AF` | `#60A5FA` / `#0B1220` / `rgba(96,165,250,.16)` / `#93C5FD` | Jarayonda |
| `scrim` | `rgba(15,23,42,.5)` | `rgba(0,0,0,.6)` | Modal orqa foni |

Dark rejimda to'q rangli (solid) fon ustidagi matn **qorong'i** (`on-*`), oq emas.

### Kontrast (WCAG, ≥ 4.5:1)

Matn: surface / page / eng yomon holat (Telegram Android/iOS palitralari va `surface-2` ham hisobga olingan).

| Matn tokeni | Light | Dark |
|---|---|---|
| `fg` | 17.85 / 16.37 / 15.58 | 14.87 / 16.59 / 10.58 |
| `fg-muted` | 5.43 / 4.97 / 4.73 | 6.71 / 7.49 / 4.77 |
| `brand` (matn sifatida) | 5.18 / 4.75 / 4.52 | 7.20 / 8.03 / 5.12 |
| `brand-soft-fg` | 7.31 / 6.70 / 6.38 | 9.66 / 10.78 / 6.87 |
| `success-soft-fg` | 7.13 / 6.54 / 6.22 | 11.60 / 12.94 / 8.25 |
| `warning-soft-fg` | 7.09 / 6.50 / 6.19 | 11.30 / 12.61 / 8.04 |
| `danger-soft-fg` | 8.31 / 7.62 / 7.25 | 8.58 / 9.58 / 6.10 |
| `info-soft-fg` | 8.72 / 8.00 / 7.61 | 9.04 / 10.08 / 6.43 |
| `fg-subtle` | 2.56 (faqat placeholder/disabled — matn uchun emas) | 4.67 |

Juftliklar (matn / fon):

| Juftlik | Light | Dark |
|---|---|---|
| `on-brand` / `brand` | 5.18 | 7.73 |
| `brand-soft-fg` / `brand-soft` | 6.60 | 7.33 |
| `on-success` / `success` | 5.02 | 8.55 |
| `success-soft-fg` / `success-soft` | 6.40 | 8.72 |
| `on-warning` / `warning` | 5.02 | 10.48 |
| `warning-soft-fg` / `warning-soft` | 6.37 | 7.98 |
| `on-danger` / `danger` | 4.83 | 6.32 |
| `danger-soft-fg` / `danger-soft` | 7.28 | 7.32 |
| `on-info` / `info` | 5.17 | 7.36 |
| `info-soft-fg` / `info-soft` | 7.69 | 6.84 |
| `fg-muted` / `surface-2` (neytral badge) | 4.88 | 5.67 |

Dark soft fonlar Telegram'ning `#212D3B`, `#000`, `#1C1C1D` foni ustida ham ≥ 5.87.

**Qoida:** rangli matn uchun `text-*-soft-fg` ishlating. `success`/`warning`/`danger`/`info` solid ranglari matn
sifatida kulrang sahifada 4.21–4.6 ga tushadi — ular faqat fon (`on-*` bilan), ikonka va chegara uchun.

## Tipografika

| Klass | O'lcham / qator | Qachon |
|---|---|---|
| `text-caption` | 12 / 16 | Badge, nav yorlig'i, izoh. **Eng kichik o'lcham** |
| `text-footnote` | 13 / 18 | Ikkinchi darajali matn, bo'lim sarlavhasi |
| `text-body` | 15 / 20 | Asosiy matn, tugmalar (xodim ekranlarida tugma ≥ 14px) |
| `text-headline` | 17 / 22 | Kichik sarlavha, katta tugma |
| `text-title` | 20 / 26 | Ekran sarlavhasi |
| `text-display` | 28 / 34 | Katta raqamlar (jami summa) |

Og'irlik: `font-medium` (500) va `font-bold` (700). `font-extrabold` (800) faqat jami summalar uchun. `font-black`
ishlatilmaydi. KATTA HARFLI + keng harf oralig'i sarlavhalar yo'q.

## Radius

`rounded-control` 10px (input, chip) · `rounded-card` 16px (kartochka, tugma) · `rounded-sheet` 24px (sheet, dialog).

## Komponentlar (`components/ui`)

| Komponent | Qachon |
|---|---|
| `Button` | Har qanday tugma. `variant`: `primary` (ekran/kartochkaning bitta asosiy amali), `secondary` (boshqa amallar), `ghost` (past urg'u), `danger` (o'chirish, rad etish), `success` (ijobiy holat o'zgarishi). `size`: `md` 44px, `lg` 52px. `loading`, `icon`, `fullWidth`. Link uchun: `buttonClasses()` |
| `IconButton` | Faqat ikonka. `label` majburiy (aria-label). Hit area 44×44 |
| `StatusBadge` | Buyurtma holati. `audience="staff"` — xodim so'zlari ("Yangi") |
| `InlineError` | Ma'lumot yuklanmadi: sahifa ichida, nav ishlab turadi, "Qayta urinish" (`onRetry={refetch}`) |
| `EmptyState` | Ro'yxat bo'sh: ikonka, sarlavha, matn, bitta keyingi amal |
| `Card`, `Section` | Kontent bloki (surface, 16px, `line` chegara); sarlavhali guruh |
| `PageHeader` | Ekran sarlavhasi; `back` — Telegram ichida chizilmaydi (Telegram BackButton ishlaydi) |
| `Dialog` | Yagona modal: `variant="sheet"` (forma, tanlov) yoki `"center"` (savol). Escape, fokus, scroll lock |
| `Spinner` | Tugma/hudud ichidagi yuklanish |
| `Skeleton`, `CardSkeletons` | Ro'yxat/kartochka yuklanayotganda o'rnidagi kulrang bloklar |
| `Field`, `fieldClass` | Yorliqli forma maydoni; har bir input/select/textarea ko'rinishi (17px, 44px) |
| `ProductImage` | Taom rasmi yoki neytral o'rinbosar (rasm yo'q, generatsiya qilingan avatar, yuklanmagan rasm) |
| `useMainButton` | Ekranning bitta asosiy amali ("Buyurtma berish", "Saqlash"): Telegram MainButton yoki dock'dagi o'rinbosar. Stek: oxirgi ochilgan egasi (masalan, sahifa ustidagi sheet) ko'rsatiladi, u yopilsa yoki `visible: false` bo'lsa oldingisi qaytadi. `onClick` Promise qaytarsa, tugaguncha qayta bosish e'tiborsiz |
| `showAlert` / `showConfirm` / `showPopup` | `lib/dialogs.ts`: Telegram popup yoki ilova ichidagi dialog. Promise qaytaradi |
| `selectionChanged()`, `notificationOccurred()` | `lib/twa.ts`: tab/nav/stepper uchun; amal natijasi uchun |

`ErrorFallback` — faqat kirish (auth) xatosi uchun. Sahifa ichida `FullScreenLoader` emas, skeleton yoki
`RouteFallback`.

Pastki dock (`components/ui/BottomDock.tsx`): MainButton o'rinbosari → savat paneli → nav. Balandligi
`--rz-dock-height` ga yoziladi; sahifa `pb-dock` bilan, pastki suzuvchi elementlar `bottom-dock` bilan joylanadi.
Safe area (`env(safe-area-inset-bottom)`, `--tg-safe-area-inset-bottom`, `--tg-content-safe-area-inset-bottom`)
hisobga olinadi. Sahifalarda `fixed bottom-[NNpx]` yozmang.

## Holat ranglari (`lib/orderStatus.ts`)

| Guruh | Holatlar | Badge |
|---|---|---|
| warning | `new` | `bg-warning-soft text-warning-soft-fg`, soat |
| info | `accepted` (✓), `preparing` (oshpaz), `ready` (quti), `courier_assigned` (odam), `on_the_way`/`delivery` (velosiped) | `bg-info-soft text-info-soft-fg` |
| success | `delivered` | `bg-success-soft text-success-soft-fg` |
| danger | `canceled` (×), `rejected` (taqiq) | `bg-danger-soft text-danger-soft-fg` |
| neutral | `refunded`, noma'lum holat | `bg-surface-2 text-fg-muted` |

Bir guruhdagi holatlar ikonka va yorliq bilan farqlanadi, rang bilan emas. Badge pulslamaydi.

## Formatlash (`lib/format.ts`)

- Pul: `formatMoney(v)` → uz `25 000 so'm`, ru `25 000 сум`, en `25,000 UZS`. Butun so'm, bo'shliqlar ajralmas (NBSP).
- Son: `formatNumber(v)` (ball, dona).
- Sana/vaqt: `formatDate` → `19-okt` (boshqa yil: `19-okt 2025`), `formatTime` → `20:12` (24 soat),
  `formatDateTime` → `19-okt, 20:12`. O'zbek oy nomlari kodda (Chromium `Intl` "M10" chiqaradi); ru/en — `Intl`.
- Buyurtma raqami: `formatOrderCode(order)` → `#E101` (short_code; yo'q bo'lsa uuid boshi).
- To'lov usuli: `getPaymentMethodLabel(method, t)` (`lib/paymentMethod.ts`) — "CASH" emas, "Naqd pul".

## Qilmang

- Xom ranglar: `slate-*`, `amber-*`, `gray-*`, `bg-white`, `text-white`, `text-black`, `bg-[#...]`. Faqat tokenlar.
- Eski aliaslar: `bg-bg`, `bg-secondary`, `text-hint`, `text-text`, `*-primary` (vaqtincha ishlaydi, yangi kodda yo'q).
- Telegram `hint` rangini matn uchun ishlatmang (kontrasti 2.1–2.9) — `text-fg-muted`.
- 12px dan kichik matn (`text-[10px]`, `text-[9px]` ...).
- `alert()` / `confirm()` — `showAlert` / `showConfirm`.
- Ma'lumot xatosida butun ekranli xato — `InlineError`.
- `toLocaleString()`, `Intl.NumberFormat`, `' UZS'` qo'lda — `formatMoney` / `formatDateTime`.
- Rangni yagona belgi qilish (holat faqat rang bilan) va `animate-pulse` badge'larda.
- 44px dan kichik bosiladigan element.

## Nazorat

`src/test/designSystem.test.ts` har bir `.tsx` faylni tekshiradi (xom ranglar, eski aliaslar, < 12px matn,
`alert/confirm`). Hali o'tkazilmagan fayllar `src/test/designSystemAllowlist.ts` da. Faylni o'tkazgach uni ro'yxatdan
o'chiring; ro'yxatdagi toza fayl testni yiqitadi (ro'yxat faqat qisqaradi). Asosli istisno: qator oxirida
`design-ok: <sabab>` izohi.
