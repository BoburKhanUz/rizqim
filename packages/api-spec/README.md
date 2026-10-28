# packages/api-spec

Backend API'ning OpenAPI 3.1 spetsifikatsiyasi: `openapi.json`. Qo'lda tahrirlanmaydi — koddan
([Scramble](https://scramble.dedoc.co)) yaratiladi: marshrutlar, validatsiya qoidalari, modellar va controllerlardagi
`#[...]` izohlar.

## Yangilash

API o'zgarganda (yangi endpoint, maydon, parametr):

```bash
cd backend
php artisan migrate          # model sxemalari jadval ustunlaridan olinadi
php artisan api-spec:export  # packages/api-spec/openapi.json ni qayta yozadi
cd ../packages/ts/api-client && npm run generate   # TypeScript turlari
```

So'ng ikkala faylni ham commit qiling. CI `php artisan api-spec:export --check` va `npm run check:generated` bilan
farqni tekshiradi: fayl koddan orqada qolsa, job yiqiladi.

## Ko'rish

- Brauzerda: backend'da `/docs/api` (JSON: `/docs/api.json`). `local` va `testing` muhitida ochiq; boshqa muhitda yopiq,
  `API_DOCS_PUBLIC=true` hammaga ochadi (P4-33 dan beri sessiya bilan ochilmaydi; hujjatning o'zi shu papkada).
- Server: `/api`, yo'llar `/v1/...` (masalan `GET /api/v1/orders`) va `/broadcasting/auth`.
- Kirish: `Authorization: Bearer <token>` (Sanctum). Webhooklar va health ochiq (`security: []`).
- Xatolar: `ErrorResponse` (`success: false`, `message`, ba'zan `errors`); Laravel validatsiyasi 422 `{message, errors}`.

Foydalanuvchilar: `packages/ts/api-client` (TypeScript), keyinchalik `packages/dart` (Flutter).
