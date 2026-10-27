'use client';

import { useQuery } from '@tanstack/react-query';
import { ClipboardList } from 'lucide-react';
import { Card, CardSkeletons, EmptyState, formatDateTime, formatMoney, formatNumber, InlineError, Section, Skeleton, StatusBadge, PageHeader } from '@rizqim/ui';
import { useApi } from '@/lib/apiConfig';
import { hasAny } from '@/lib/permissions';
import { useAccess, useOptionalAuth } from './AuthProvider';
import { BoardSummary } from './orders/BoardSummary';
import { orderTypeLabel, paymentLabel, statusLabel } from '@/lib/labels';
import { useI18n } from './I18nProvider';

type Analytics = NonNullable<ReturnType<typeof useAnalyticsData>['data']>;

// GET /v1/owner/analytics?period=today: every figure is computed by the backend; this page only shows them. The
// backend caches the answer for 60 s, so asking more often is pointless.
function useAnalyticsData(enabled: boolean) {
  const api = useApi();
  return useQuery({
    enabled,
    queryKey: ['owner-analytics', 'today'],
    refetchInterval: 60_000,
    queryFn: async () => {
      const { data } = await api.GET('/v1/owner/analytics', { params: { query: { period: 'today' } } });
      if (!data) throw new Error('analytics');
      return data.data;
    },
  });
}

// The board summary for people who see orders (orders.view), the day's analytics for those who see reports
// (reports.view). Outside the shell (a page test) only the analytics.
export function Dashboard() {
  const { t, language } = useI18n();
  const auth = useOptionalAuth();
  const access = useAccess();
  const showBoard = access !== null && hasAny(access.permissions, ['orders.view']);
  const showAnalytics = auth === null || (access !== null && hasAny(access.permissions, ['reports.view']));
  const query = useAnalyticsData(showAnalytics);

  return (
    <>
      <PageHeader
        title={t('nav_dashboard')}
        subtitle={t('dashboardSubtitle')}
        action={showAnalytics && query.data && <p className="text-caption text-fg-muted">{t('updatedAt', { time: formatDateTime(query.data.generated_at, language) })}</p>}
      />
      {showBoard && (
        <div className="mb-8">
          <BoardSummary />
        </div>
      )}
      {!showAnalytics ? null : query.isPending ? (
        <DashboardSkeleton />
      ) : query.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      ) : (
        <DashboardBody data={query.data} />
      )}
    </>
  );
}

function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-6" role="status" aria-busy="true">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        {[0, 1, 2, 3].map((n) => (
          <Card key={n} className="flex flex-col gap-3">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-8 w-32" />
          </Card>
        ))}
      </div>
      <CardSkeletons count={2} lines={3} />
    </div>
  );
}

function Kpi({ label, value, note, tone }: { label: string; value: string; note?: string; tone?: 'brand' }) {
  return (
    <Card className="flex flex-col gap-1">
      <p className="text-footnote font-bold text-fg-muted">{label}</p>
      <p className={tone === 'brand' ? 'text-display font-extrabold text-brand' : 'text-display font-extrabold text-fg'}>{value}</p>
      {note && <p className="text-caption text-fg-muted">{note}</p>}
    </Card>
  );
}

function Row({ left, right }: { left: React.ReactNode; right: React.ReactNode }) {
  return (
    <li className="flex items-center justify-between gap-3 py-2.5">
      <span className="min-w-0 truncate text-body text-fg">{left}</span>
      <span className="shrink-0 text-body font-medium text-fg-muted">{right}</span>
    </li>
  );
}

function DashboardBody({ data }: { data: Analytics }) {
  const { t, language } = useI18n();
  const { summary } = data;

  if (summary.orders_count === 0 && summary.active_count === 0) {
    return <EmptyState icon={ClipboardList} title={t('noOrdersToday')} text={t('noOrdersTodayText')} />;
  }

  const minutes = (seconds: number | null) => (seconds === null ? '—' : t('minutes', { n: Math.max(1, Math.round(seconds / 60)) }));
  const statuses = Object.entries(data.by_status)
    .filter(([, count]) => count > 0)
    .sort((a, b) => b[1] - a[1]);

  return (
    <div className="flex flex-col gap-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <Kpi
          label={t('ordersCount')}
          value={formatNumber(summary.orders_count, language)}
          note={`${t('deliveredCount')}: ${formatNumber(summary.delivered_count, language)} · ${t('canceledCount')}: ${formatNumber(summary.canceled_count, language)}`}
        />
        <Kpi label={t('revenue')} value={formatMoney(summary.revenue, language)} note={t('revenueNote')} tone="brand" />
        <Kpi label={t('averageCheck')} value={formatMoney(summary.average_check, language)} />
        <Kpi label={t('activeOrders')} value={formatNumber(summary.active_count, language)} />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Section title={t('byStatus')}>
          <Card className="py-2">
            <ul className="divide-y divide-line">
              {statuses.map(([status, count]) => (
                <li key={status} className="flex items-center justify-between gap-3 py-2.5">
                  <StatusBadge status={status} label={statusLabel(language, status)} />
                  <span className="text-body font-bold text-fg">{formatNumber(count, language)}</span>
                </li>
              ))}
            </ul>
          </Card>
        </Section>

        <Section title={t('topProducts')}>
          <Card className="py-2">
            <ul className="divide-y divide-line">
              {data.top_products.slice(0, 8).map((product) => (
                <Row key={product.name} left={product.name} right={`${t('piecesShort', { n: formatNumber(product.quantity, language) })} · ${formatMoney(product.revenue, language)}`} />
              ))}
              {data.top_products.length === 0 && <Row left="—" right="" />}
            </ul>
          </Card>
        </Section>

        <Section title={t('byPayment')}>
          <Card className="py-2">
            <ul className="divide-y divide-line">
              {data.by_payment_method.map((row) => (
                <Row key={row.method} left={paymentLabel(language, row.method)} right={`${t('ordersShort', { n: formatNumber(row.orders, language) })} · ${formatMoney(row.revenue, language)}`} />
              ))}
            </ul>
          </Card>
        </Section>

        <div className="flex flex-col gap-6">
          <Section title={t('byType')}>
            <Card className="py-2">
              <ul className="divide-y divide-line">
                {data.by_order_type.map((row) => (
                  <Row key={row.type} left={orderTypeLabel(language, row.type)} right={`${t('ordersShort', { n: formatNumber(row.orders, language) })} · ${formatMoney(row.revenue, language)}`} />
                ))}
              </ul>
            </Card>
          </Section>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Kpi label={t('avgPrep')} value={minutes(data.times.average_prep_seconds)} />
            <Kpi label={t('avgDelivery')} value={minutes(data.times.average_delivery_seconds)} />
          </div>
        </div>
      </div>
    </div>
  );
}
