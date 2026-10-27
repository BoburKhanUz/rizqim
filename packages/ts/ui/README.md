# @rizqim/ui

Rizqim dizayn tizimi: tokenlar (CSS o'zgaruvchilar + Tailwind 4 `@theme`, light/dark) va uchta veb-ilova (`customer/miniapp`,
`partner/web`, `platform/admin`) ishlatadigan React komponentlari. Qoidalar va palitra: [docs/DESIGN.md](../../../docs/DESIGN.md).

Paket **manba kod** sifatida ulanadi (build qadami yo'q): `package.json` `exports` to'g'ridan-to'g'ri `src/` ga ishora qiladi,
TypeScript va JSX'ni ilovaning o'z bundler'i kompilyatsiya qiladi. Telegram, router va til ombori haqida hech narsa bilmaydi:
matnlar va til prop sifatida beriladi (`closeLabel`, `retryLabel`, `label`, `formatMoney(value, language)`).

## Nima bor

| | |
|---|---|
| `src/styles.css` | Tokenlar (`--rz-*`), `@theme` (rang, radius, shrift o'lchamlari, animatsiyalar), `html`/`body` asosi. Mavzu `<html data-theme>` yoki tizim sozlamasi bo'yicha. Host ilova `--rz-host-page/surface/fg/fg-subtle` ilgaklari bilan to'rtta rangni almashtira oladi (Mini App Telegram ranglarini shunday beradi) |
| `src/components/` | `Button`, `buttonClasses`, `IconButton`, `StatusBadge`, `Card`, `Section`, `EmptyState`, `InlineError`, `Skeleton`, `CardSkeletons`, `Field`, `fieldClass`, `Dialog`, `ConfirmDialog`, `PageHeader`, `Notice`, `FormError`, `Pill`, `Switch`, `Tabs`, `Spinner`, `cx` |
| `src/lib/` | `formatMoney`, `formatNumber`, `formatDate`, `formatTime`, `formatDateTime`, `formatOrderCode`, `toAmount`/`parseAmount`/`sumAmounts`, buyurtma holati jadvali (`getOrderStatusLook`), turlar (`Language`, `Amount`, `OrderStatus`) |

`PageHeader`, `Notice`, `Pill`, `Switch`, `Tabs` va `ConfirmDialog` hamkor panelidan ko'chirilgan (P4-12): ikkala veb-panel bir xil kodni ishlatadi, matnlar (`cancelLabel`, `closeLabel`) ilovadan beriladi.

Telegramga xos qismlar (`twa.ts`, MainButton, BackButton, BottomDock, Telegram popup'lari) Mini App'da qoladi.

## Ilovaga ulash

1. `package.json`: `"@rizqim/ui": "file:../../packages/ts/ui"`, so'ng `npm install` (`node_modules/@rizqim/ui` — symlink; o'zgartirish
   darhol ko'rinadi, qayta o'rnatish shart emas). Peer bog'liqliklar ilovada bo'lishi kerak: `react`, `react-dom`, `lucide-react`.
2. CSS: Tailwind'dan keyin `@import "@rizqim/ui/styles.css";`. Fayl ichidagi `@source "./"` Tailwind'ga paket klasslarini
   skanerlashni aytadi (symlink orqali `node_modules` ichida turgani uchun avtomatik aniqlash uni o'tkazib yuboradi).
3. **Bitta React.** Paket fayllari symlink orqali haqiqiy joyida (`packages/ts/ui/src`) turadi va u yerda `node_modules` yo'q, shuning
   uchun `react`, `react-dom`, `lucide-react` importlari ilovaning nusxalariga yo'naltirilishi kerak:
   - Vite (Mini App): `resolve.dedupe: ['react', 'react-dom', 'lucide-react']`, dev serverda `server.fs.allow: ['../..']`;
   - TypeScript: `tsconfig` `paths` — `react` va `react-dom` ni `node_modules/@types/...` ga, `lucide-react` ni `node_modules/lucide-react` ga;
   - Next.js (partner/web, platform/admin): `transpilePackages: ['@rizqim/ui']`, `turbopack.root` repozitoriy ildizi va `turbopack.resolveAlias` (react, react-dom, lucide-react).
4. Docker: `file:` bog'liqlik paket papkasi build kontekstida bo'lishini talab qiladi, shuning uchun ikkala Dockerfile ham kontekst
   sifatida repozitoriy ildizini ishlatadi (`docker-compose.yml`: `context: .`).

Nega npm workspaces emas: ildizdagi yagona `package-lock.json` Mini App'ning `npm ci` ini, uning Dockerfile'ini va CI job'larini
o'zgartirishni talab qilardi va kutubxona versiyalarini ko'tarib qo'yishi mumkin edi (Mini App "avvalgidek ishlashi" shart). `file:` +
alohida lock fayllar mavjud tuzilmaga (api-client ham shunday) mos keladi.

## Tekshiruv

Paketning o'z `npm` buyruqlari yo'q: uni ishlatgan ilovalar tekshiradi (`tsc`, eslint, testlar, build).
`customer/miniapp/src/test/designSystem.test.ts` paketdagi `.tsx` fayllarni ham dizayn qoidalari bo'yicha tekshiradi (xom ranglar,
< 12px matn va h.k.). Strict TypeScript (`noUncheckedIndexedAccess`) ostida ham kompilyatsiya bo'lishi kerak: `partner/web` shunday sozlangan.
Dialog kabi hook ishlatadigan komponentlarda faylning boshida `'use client'` bor (Next.js App Router uchun).
