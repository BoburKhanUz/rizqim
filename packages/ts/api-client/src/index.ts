/**
 * Rizqim backend API uchun TypeScript klient.
 *
 * Turlar (`paths`, `components`, `operations`) packages/api-spec/openapi.json dan `npm run generate` bilan
 * yaratiladi (src/schema.d.ts, qo'lda tahrirlanmaydi). So'rovlar openapi-fetch orqali: yo'l, parametrlar, body va
 * javob turlari spetsifikatsiyadan tekshiriladi.
 *
 * @example
 * const api = createApiClient({ baseUrl: '/api', getToken: () => localStorage.getItem('token') });
 * const { data, error } = await api.GET('/v1/orders', { params: { query: { scope: 'active' } } });
 */
import createClient, { createQuerySerializer, type Client, type ClientOptions } from 'openapi-fetch';
import type { components, operations, paths } from './schema.js';

export type { components, operations, paths };

/** Spetsifikatsiyadagi nomlangan sxemalar: `Schema<'Order'>`, `Schema<'ErrorResponse'>`. */
export type Schema<Name extends keyof components['schemas']> = components['schemas'][Name];

/** Xato javobi (`success: false`, `message`, ba'zan `errors`). */
export type ApiError = Schema<'ErrorResponse'>;

export type ApiClient = Client<paths>;

export interface ApiClientOptions extends Omit<ClientOptions, 'baseUrl'> {
  /** API serverining ildizi (spetsifikatsiyadagi `servers`): standart `/api`. Yo'llar `/v1/...` bilan boshlanadi. */
  baseUrl?: string;
  /** Sanctum tokeni: bor bo'lsa har so'rovga `Authorization: Bearer <token>` qo'shiladi. */
  getToken?: () => string | null | undefined | Promise<string | null | undefined>;
}

const serializeQuery = createQuerySerializer();

/**
 * Laravel's `boolean` rule accepts 1, 0, "1" and "0" in a query string, not "true"/"false" (which is what openapi-fetch
 * writes for a boolean), so a request with `include_revoked=false` is answered with 422. Booleans go out as 1/0.
 */
export function serializeBooleansAsNumbers(query: unknown): string {
  const numeric = (value: unknown) => (typeof value === 'boolean' ? (value ? 1 : 0) : value);
  if (!query || typeof query !== 'object') return serializeQuery(query);
  return serializeQuery(Object.fromEntries(Object.entries(query).map(([name, value]) => [name, Array.isArray(value) ? value.map(numeric) : numeric(value)])));
}

export function createApiClient({ baseUrl = '/api', getToken, ...options }: ApiClientOptions = {}): ApiClient {
  const client = createClient<paths>({ baseUrl, querySerializer: serializeBooleansAsNumbers, ...options });

  if (getToken) {
    client.use({
      async onRequest({ request }) {
        const token = await getToken();
        if (token) {
          request.headers.set('Authorization', `Bearer ${token}`);
        }
        return request;
      },
    });
  }

  return client;
}
