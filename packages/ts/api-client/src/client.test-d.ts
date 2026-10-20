// Compile-time checks (npm run typecheck): the generated types describe the endpoints the apps rely on. Not built.
import { createApiClient, type ApiError, type Schema, type paths } from './index.js';

type Equal<A, B> = (<T>() => T extends A ? 1 : 2) extends <T>() => T extends B ? 1 : 2 ? true : false;
type Expect<T extends true> = T;

type OrdersQuery = NonNullable<paths['/v1/orders']['get']['parameters']['query']>;
type CancelPath = paths['/v1/orders/{uuid}/cancel']['post']['parameters']['path'];

export type Checks = [
  Expect<Equal<OrdersQuery['scope'], 'active' | 'history' | undefined>>,
  Expect<Equal<OrdersQuery['cursor'], string | undefined>>,
  Expect<Equal<CancelPath, { uuid: string }>>,
  Expect<Equal<ApiError['success'], false>>,
  Expect<Equal<Schema<'Order'>['uuid'], string>>,
];

const api = createApiClient({ getToken: () => 'token' });

export async function usage(): Promise<void> {
  const history = await api.GET('/v1/orders', { params: { query: { scope: 'history', per_page: 20 } } });
  const orders: Schema<'Order'>[] | undefined = history.data?.data;
  void orders;

  const cancelled = await api.POST('/v1/orders/{uuid}/cancel', {
    params: { path: { uuid: '00000000-0000-0000-0000-000000000000' } },
    body: { reason: 'customer_request', note: null },
  });
  const status: Schema<'OrderStatus'> | undefined = cancelled.data?.data.status;
  void status;
}
