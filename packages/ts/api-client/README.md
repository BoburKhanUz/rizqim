# @rizqim/api-client

Rizqim backend API uchun TypeScript klient. Turlar `packages/api-spec/openapi.json` dan
[openapi-typescript](https://openapi-ts.dev) bilan yaratiladi (`src/schema.d.ts`, qo'lda tahrirlanmaydi), so'rovlar
[openapi-fetch](https://openapi-ts.dev/openapi-fetch/) orqali.

```ts
import { createApiClient, type Schema } from '@rizqim/api-client';

const api = createApiClient({ baseUrl: '/api', getToken: () => token });
const { data, error } = await api.GET('/v1/orders', { params: { query: { scope: 'history', per_page: 20 } } });
const orders: Schema<'Order'>[] = data?.data ?? [];
```

Buyruqlar:

- `npm run generate` — spetsifikatsiya o'zgarganda turlarni qayta yaratish (natijani commit qiling);
- `npm run check:generated` — turlar spetsifikatsiyaga mosligini tekshirish (CI);
- `npm run typecheck`, `npm run build` — tekshiruv va `dist/` ga yig'ish.
