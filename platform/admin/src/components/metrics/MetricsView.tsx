'use client';

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowDownRight, ArrowUpRight, Minus, RefreshCw, ShieldAlert, type LucideIcon } from 'lucide-react';
import { Card, CardSkeletons, cx, EmptyState, Field, fieldClass, formatMoney, formatNumber, formatTime, IconButton, InlineError, PageHeader } from '@rizqim/ui';
import { DEFAULT_MONTHS, getMetrics, METRICS_KEYS, METRICS_MONTHS, useMetrics, type MetricsOverview, type MrrChange } from '@/lib/api/metrics';
import { errorText } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { BarChart, type BarPoint } from './BarChart';
import { FunnelPanel } from './FunnelPanel';
import { decimal, monthLabel, monthShort, num, shortMoney } from './metricsModel';
import { MovementChart } from './MovementChart';

const money = (value: string | number) => formatMoney(value, 'uz');
const count = (value: number) => formatNumber(value, 'uz');

// Ko'rsatkichlar (P4-31, docs/PHASE4_PLAN.md 4.5, 9): the numbers of the B2B business on one page. Cards on top (MRR, ARR,
// paying stores, ARPA, active stores, churn, GMV and commission), then the months as charts that also read as tables: MRR,
// its movement, churn, active stores, GMV and commission; the application funnel last. Everything is the server's own
// arithmetic (docs/API.md "Ko'rsatkichlar"); the bars only scale it. Months are Tashkent months.
export function MetricsView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('finance.view', 'stores.view');
  const client = useQueryClient();
  const [months, setMonths] = useState<number>(DEFAULT_MONTHS);
  const [refreshing, setRefreshing] = useState(false);
  const metrics = useMetrics(months, allowed);
  const data = metrics.data;

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('metrics_title')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} text={t('metrics_forbidden')} />
      </>
    );
  }

  // "Yangilash" asks the backend to drop its few minutes of cache
  const refresh = async () => {
    setRefreshing(true);
    try {
      client.setQueryData(METRICS_KEYS.overview(months), await getMetrics(months, true));
      void client.invalidateQueries({ queryKey: [...METRICS_KEYS.all, 'funnel'] });
    } catch {
      void metrics.refetch();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <>
      <PageHeader
        title={t('metrics_title')}
        subtitle={t('metrics_subtitle')}
        action={
          <div className="flex items-end gap-2">
            <Field label={t('metrics_period')} className="w-32">
              {(control) => (
                <select {...control} className={fieldClass} value={months} onChange={(event) => setMonths(Number(event.target.value))}>
                  {METRICS_MONTHS.map((value) => (
                    <option key={value} value={value}>
                      {t('metrics_periodMonths', { count: value })}
                    </option>
                  ))}
                </select>
              )}
            </Field>
            <IconButton icon={RefreshCw} label={t('metrics_refresh')} onClick={() => void refresh()} className={cx((refreshing || metrics.isFetching) && 'animate-spin motion-reduce:animate-none')} />
          </div>
        }
      />

      <div className="flex max-w-6xl flex-col gap-4">
        {metrics.isError && !data ? (
          <InlineError title={t('metrics_loadError')} message={errorText(metrics.error, t)} retryLabel={t('retry')} retrying={metrics.isFetching} onRetry={() => void metrics.refetch()} />
        ) : !data ? (
          <CardSkeletons count={4} lines={2} label={t('loading')} />
        ) : (
          <Charts data={data} />
        )}
        <FunnelPanel months={months} enabled={allowed} />
      </div>
    </>
  );
}

function Charts({ data }: { data: MetricsOverview }) {
  const t = useT();
  const { cards } = data;
  const incomplete = data.revenue.filter((row) => !row.complete);

  const seriesPoints: BarPoint[] = data.series.map((row, index) => ({
    key: row.month,
    short: monthShort(row.month, index),
    value: num(row.mrr),
    partial: row.partial,
    read: t('metrics_mrrSeriesRead', { month: monthLabel(row.month), value: money(row.mrr), paying: row.paying_stores }),
  }));
  const churnPoints: BarPoint[] = data.churn.map((row, index) => ({
    key: row.month,
    short: monthShort(row.month, index),
    value: num(row.rate),
    partial: row.partial,
    read: row.rate !== null ? t('metrics_churnRead', { month: monthLabel(row.month), rate: decimal(row.rate), lost: row.churned, start: row.active_at_start }) : t('metrics_churnNoBase', { month: monthLabel(row.month) }),
  }));
  const activePoints: BarPoint[] = data.series.map((row, index) => ({
    key: row.month,
    short: monthShort(row.month, index),
    value: row.active_stores,
    partial: row.partial,
    read: t('metrics_activeRead', { month: monthLabel(row.month), count: row.active_stores }),
  }));
  const gmvPoints: BarPoint[] = data.revenue.map((row, index) => ({
    key: row.month,
    short: monthShort(row.month, index),
    value: num(row.gmv),
    partial: row.partial || !row.complete,
    read: t('metrics_gmvRead', { month: monthLabel(row.month), value: money(row.gmv), orders: count(row.orders) }),
  }));
  const commissionPoints: BarPoint[] = data.revenue.map((row, index) => ({
    key: row.month,
    short: monthShort(row.month, index),
    value: num(row.commission),
    partial: row.partial || !row.complete,
    read: t('metrics_gmvRead', { month: monthLabel(row.month), value: money(row.commission), orders: count(row.orders) }),
  }));
  const label = (row: { month: string; partial: boolean }) => (row.partial ? `${monthLabel(row.month)} · ${t('metrics_partial')}` : monthLabel(row.month));

  return (
    <>
      <ul aria-label={t('metrics_cardsLabel')} className="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t('metrics_mrr')} value={money(cards.mrr)} brand note={<MrrChangeLine change={cards.mrr_change} />} hint={t('metrics_mrrHint')} />
        <StatCard label={t('metrics_arr')} value={money(cards.arr)} note={t('metrics_arrHint')} />
        <StatCard label={t('metrics_paying')} value={count(cards.paying_stores)} note={t('metrics_payingHint', { count: cards.trialing_stores })} />
        <StatCard label={t('metrics_arpa')} value={cards.arpa !== null ? money(cards.arpa) : '—'} note={cards.arpa === null ? t('metrics_arpaNone') : undefined} />
        <StatCard label={t('metrics_active')} value={count(cards.active_stores)} note={t('metrics_activeHint')} />
        <StatCard
          label={t('metrics_churn')}
          value={cards.churn_rate !== null ? `${decimal(cards.churn_rate)}%` : '—'}
          note={cards.churn_month ? t('metrics_churnMonth', { month: monthLabel(cards.churn_month) }) : t('metrics_churnNone')}
        />
        <StatCard label={t('metrics_gmv')} value={money(cards.gmv_month)} note={t('metrics_revenueHint')} />
        <StatCard label={t('metrics_commission')} value={money(cards.commission_month)} note={cards.revenue_complete ? undefined : t('metrics_revenueIncomplete', { count: data.revenue[data.revenue.length - 1]?.missing_days ?? 0 })} />
      </ul>
      <p className="px-1 text-caption text-fg-muted">{t('metrics_updated', { time: formatTime(new Date(data.generated_at)) })}</p>

      <BarChart
        title={t('metrics_mrrSeries')}
        points={seriesPoints}
        top={money(Math.max(0, ...seriesPoints.map((point) => point.value)))}
        tableHeaders={[t('metrics_colMonth'), t('metrics_mrrSeriesColumn'), t('metrics_paying'), t('metrics_active')]}
        tableRows={data.series.map((row) => [label(row), money(row.mrr), count(row.paying_stores), count(row.active_stores)])}
      />

      <MovementChart months={data.movement} />

      <BarChart
        title={t('metrics_churnTitle')}
        text={t('metrics_churnText')}
        points={churnPoints}
        top={`${decimal(Math.max(0, ...churnPoints.map((point) => point.value)).toFixed(1).replace(/\.0$/, ''))}%`}
        tableHeaders={[t('metrics_colMonth'), t('metrics_churnStart'), t('metrics_churnInactive'), t('metrics_churnEnded'), t('metrics_churnLost'), t('metrics_churnRate'), t('metrics_churnRevenueRate')]}
        tableRows={data.churn.map((row) => [label(row), count(row.active_at_start), count(row.went_inactive), count(row.subscription_ended), count(row.churned), row.rate !== null ? decimal(row.rate) : '—', row.revenue_rate !== null ? decimal(row.revenue_rate) : '—'])}
      />

      <BarChart
        title={t('metrics_activeTitle')}
        points={activePoints}
        top={count(Math.max(0, ...activePoints.map((point) => point.value)))}
        tableHeaders={[t('metrics_colMonth'), t('metrics_activeColumn')]}
        tableRows={data.series.map((row) => [label(row), count(row.active_stores)])}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <BarChart
          title={t('metrics_gmvTitle')}
          points={gmvPoints}
          top={shortMoney(Math.max(0, ...gmvPoints.map((point) => point.value)))}
          tableHeaders={[t('metrics_colMonth'), t('metrics_gmvColumn'), t('metrics_ordersColumn'), t('metrics_subscriptionColumn')]}
          tableRows={data.revenue.map((row) => [label(row), money(row.gmv), count(row.orders), money(row.subscription_revenue)])}
        />
        <BarChart
          title={t('metrics_commissionTitle')}
          points={commissionPoints}
          top={shortMoney(Math.max(0, ...commissionPoints.map((point) => point.value)))}
          tableHeaders={[t('metrics_colMonth'), t('metrics_commissionColumn'), t('metrics_ordersColumn')]}
          tableRows={data.revenue.map((row) => [label(row), money(row.commission), count(row.orders)])}
        />
      </div>
      {incomplete.length > 0 && <p className="px-1 text-caption text-fg-muted">{t('metrics_revenueNote', { months: incomplete.map((row) => monthLabel(row.month)).join(', ') })}</p>}
    </>
  );
}

const DIRECTION: Record<MrrChange['direction'], { icon: LucideIcon; tone: string }> = {
  up: { icon: ArrowUpRight, tone: 'text-success' },
  down: { icon: ArrowDownRight, tone: 'text-danger' },
  same: { icon: Minus, tone: 'text-fg-muted' },
};

function MrrChangeLine({ change }: { change: MrrChange }) {
  const t = useT();
  const { icon: Icon, tone } = DIRECTION[change.direction];
  const amount = money(change.amount.replace('-', ''));
  const text =
    change.direction === 'same'
      ? t('metrics_mrrSame')
      : change.percent === null
        ? t('metrics_mrrUpNoBase', { amount })
        : t(change.direction === 'up' ? 'metrics_mrrUp' : 'metrics_mrrDown', { amount, percent: decimal(change.percent) });
  return (
    <span className={cx('inline-flex items-start gap-1 font-bold', tone)}>
      <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      <span>{text}</span>
    </span>
  );
}

function StatCard({ label, value, note, hint, brand = false }: { label: string; value: string; note?: React.ReactNode; hint?: string; brand?: boolean }) {
  return (
    <li>
      <Card className="flex h-full flex-col gap-1">
        <h3 className="text-footnote font-bold text-fg-muted" title={hint}>
          {label}
        </h3>
        <p className={cx('text-title font-extrabold break-words', brand ? 'text-brand' : 'text-fg')}>{value}</p>
        {note && <p className="text-caption text-fg-muted">{note}</p>}
      </Card>
    </li>
  );
}
