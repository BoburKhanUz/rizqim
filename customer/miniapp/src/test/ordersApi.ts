import type { Order } from '../types';

const FINISHED = new Set(['delivered', 'canceled', 'rejected', 'refunded']);

// The part of axios' request config the fake reads
interface GetConfig {
  params?: unknown;
}

// What GET /orders answers for `config.params` over `orders` (newest first): the open ones for scope=active, a page of the
// finished ones for scope=history (the cursor is the offset of the page, as a string), everything without a scope.
export function ordersResponse(orders: Order[], config?: GetConfig) {
  const params = (config?.params ?? {}) as Record<string, unknown>;
  const scope = params.scope;
  if (scope === 'active') {
    return { data: { success: true, message: '', data: orders.filter((o) => !FINISHED.has(o.status)), meta: { truncated: false } } };
  }
  if (scope === 'history') {
    const finished = orders.filter((o) => FINISHED.has(o.status));
    const perPage = Number(params.per_page ?? 20);
    const offset = params.cursor ? Number(params.cursor) : 0;
    const end = offset + perPage;
    return {
      data: {
        success: true,
        message: '',
        data: finished.slice(offset, end),
        meta: { next_cursor: end < finished.length ? String(end) : null, per_page: perPage },
      },
    };
  }
  return { data: { success: true, data: orders } };
}

// An `api.get` mock implementation serving `orders` on /orders and `otherwise` (default: an empty list) elsewhere.
export function serveOrdersApi(orders: Order[], otherwise: unknown = []) {
  return (url: string, config?: GetConfig): Promise<unknown> =>
    Promise.resolve(url === '/orders' ? ordersResponse(orders, config) : { data: { success: true, data: otherwise } });
}
