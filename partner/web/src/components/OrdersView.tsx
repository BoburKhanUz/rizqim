'use client';

import { useQuery } from '@tanstack/react-query';
import { ClipboardCheck } from 'lucide-react';
import { Card, CardSkeletons, EmptyState, formatDateTime, formatMoney, formatOrderCode, InlineError, StatusBadge } from '@rizqim/ui';
import type { Schema } from '@rizqim/api-client';
import { useApi } from '@/lib/apiConfig';
import { orderTypeLabel, paymentLabel, statusLabel } from '@/lib/labels';
import { useI18n } from './I18nProvider';
import { PageHeader } from './PageHeader';

type Order = Schema<'Order'>;

// GET /v1/orders?scope=active: the orders still in progress (the store's for an owner, the branch's for a manager),
// newest first, at most 200. Read-only: changing an order's status comes with the live board (stage 2).
function useActiveOrders() {
  const api = useApi();
  return useQuery({
    queryKey: ['orders', 'active'],
    refetchInterval: 30_000,
    queryFn: async () => {
      const { data } = await api.GET('/v1/orders', { params: { query: { scope: 'active' } } });
      if (!data) throw new Error('orders');
      const meta = 'meta' in data ? (data.meta as { truncated?: boolean }) : undefined;
      return { orders: data.data, truncated: meta?.truncated === true };
    },
  });
}

export function OrdersView() {
  const { t } = useI18n();
  const query = useActiveOrders();

  return (
    <>
      <PageHeader title={t('nav_orders')} subtitle={t('ordersSubtitle')} />
      {query.isPending ? (
        <CardSkeletons count={4} lines={2} label={t('loading')} />
      ) : query.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      ) : query.data.orders.length === 0 ? (
        <EmptyState icon={ClipboardCheck} title={t('noActiveOrders')} text={t('noActiveOrdersText')} />
      ) : (
        <>
          <OrdersTable orders={query.data.orders} />
          {query.data.truncated && <p className="mt-3 text-footnote text-fg-muted">{t('ordersTruncated')}</p>}
        </>
      )}
    </>
  );
}

function OrdersTable({ orders }: { orders: Order[] }) {
  const { t, language } = useI18n();
  return (
    <>
      {/* Tablet and desktop: a table */}
      <Card padded={false} className="hidden overflow-x-auto md:block">
        <table className="w-full text-left text-body">
          <thead>
            <tr className="border-b border-line text-footnote text-fg-muted">
              <th scope="col" className="px-4 py-3 font-bold">{t('colCode')}</th>
              <th scope="col" className="px-4 py-3 font-bold">{t('colStatus')}</th>
              <th scope="col" className="px-4 py-3 font-bold">{t('colCustomer')}</th>
              <th scope="col" className="px-4 py-3 font-bold">{t('colType')}</th>
              <th scope="col" className="px-4 py-3 font-bold">{t('colPayment')}</th>
              <th scope="col" className="px-4 py-3 text-right font-bold">{t('colTotal')}</th>
              <th scope="col" className="px-4 py-3 text-right font-bold">{t('colTime')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {orders.map((order) => (
              <tr key={order.uuid}>
                <td className="whitespace-nowrap px-4 py-3 font-bold text-fg">{formatOrderCode(order)}</td>
                <td className="px-4 py-3">
                  <StatusBadge status={order.status} label={statusLabel(t, order.status)} />
                </td>
                <td className="px-4 py-3 text-fg">{order.user?.name || t('guest')}</td>
                <td className="px-4 py-3 text-fg">{orderTypeLabel(t, order.order_type ?? order.type)}</td>
                <td className="px-4 py-3 text-fg">{paymentLabel(t, order.payment_method)}</td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-bold text-fg">{formatMoney(order.total_price, language)}</td>
                <td className="whitespace-nowrap px-4 py-3 text-right text-fg-muted">{formatDateTime(order.created_at, language)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      {/* Phones: one card per order */}
      <ul className="flex flex-col gap-3 md:hidden">
        {orders.map((order) => (
          <li key={order.uuid}>
            <Card className="flex flex-col gap-2">
              <div className="flex items-center justify-between gap-3">
                <span className="font-bold text-fg">{formatOrderCode(order)}</span>
                <StatusBadge status={order.status} label={statusLabel(t, order.status)} />
              </div>
              <p className="text-footnote text-fg-muted">
                {order.user?.name || t('guest')} · {orderTypeLabel(t, order.order_type ?? order.type)} · {paymentLabel(t, order.payment_method)}
              </p>
              <div className="flex items-center justify-between gap-3">
                <span className="font-bold text-fg">{formatMoney(order.total_price, language)}</span>
                <span className="text-footnote text-fg-muted">{formatDateTime(order.created_at, language)}</span>
              </div>
            </Card>
          </li>
        ))}
      </ul>
    </>
  );
}
