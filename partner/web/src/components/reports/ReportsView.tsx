'use client';

import { useMemo, useState } from 'react';
import { ChartColumn } from 'lucide-react';
import { Card, CardSkeletons, cx, EmptyState, InlineError, Skeleton, formatMoney, formatNumber, PageHeader } from '@rizqim/ui';
import { getApiErrorMessage, getApiErrorStatus, useOwnerAnalytics, useSalesReport, useTopProducts, type TopProductsSort } from '@rizqim/staff';
import { orderTypeLabel, paymentLabel } from '@/lib/labels';
import { useAccess } from '../AuthProvider';
import { useI18n } from '../I18nProvider';
import { useBranchScope } from '../shell/BranchScope';
import { Breakdown } from './Breakdown';
import { ExportButton } from './ExportButton';
import { formatDay } from './format';
import { PeriodPicker, type PeriodState } from './PeriodPicker';
import { DEFAULT_TIMEZONE, getPeriodProblem, resolvePeriod, todayIn } from './period';
import { RevenueChart } from './RevenueChart';
import { TopProducts } from './TopProducts';

const INITIAL: PeriodState = { preset: 'last7', custom: {}, groupBy: 'day' };

// Hisobotlar: the sales of a period, as the server computed them (GET /owner/reports/sales and /top-products). This
// screen chooses the period (from the store's own "today"), asks, and shows; it counts nothing itself.
export function ReportsView() {
  const { t, language } = useI18n();
  const [state, setState] = useState<PeriodState>(INITIAL);
  const [sort, setSort] = useState<TopProductsSort>('quantity');

  // The store's calendar day and timezone come from the server (a store need not be in Tashkent's zone)
  const today = useOwnerAnalytics('today');
  const storeZone = useAccess()?.timezone ?? null;
  const storeToday = today.data?.period.from ?? (today.isError ? todayIn(storeZone ?? DEFAULT_TIMEZONE) : null);
  const timezone = today.data?.period.timezone ?? storeZone ?? DEFAULT_TIMEZONE;

  const range = useMemo(() => (storeToday ? resolvePeriod(state.preset, storeToday, state.custom) : null), [state.preset, state.custom, storeToday]);
  const problem = storeToday && (state.preset === 'custom' ? getPeriodProblem(range) : null);
  const valid = range !== null && !problem;

  // The branch is the one chosen in the top bar of the panel (all branches when none is)
  const { branchId, branchName } = useBranchScope();
  const base = valid ? { from: range.from, to: range.to, ...(branchId ? { branch_id: branchId } : {}) } : null;
  const sales = useSalesReport(base && { ...base, group_by: state.groupBy });
  const top = useTopProducts(base && { ...base, limit: 10, sort });

  const change = (next: Partial<PeriodState>) =>
    setState((current) => {
      const merged = { ...current, ...next };
      // Choosing "custom" starts from the period that was on screen
      if (next.preset === 'custom' && current.preset !== 'custom' && range && !current.custom.from) merged.custom = { from: range.from, to: range.to };
      return merged;
    });

  const branchNote = branchId ? ` · ${t('report_branch')}: ${branchName(branchId) ?? `#${branchId}`}` : '';
  const subtitle =
    range && valid
      ? `${t('report_range', { from: formatDay(range.from, language), to: formatDay(range.to, language) })} · ${t('report_timezone', { zone: sales.data?.period.timezone ?? timezone })}${branchNote}`
      : t('report_subtitle');

  return (
    <>
      <PageHeader
        title={t('report_title')}
        subtitle={subtitle}
        action={<ExportButton period={base && { ...base, group_by: state.groupBy, sort }} />}
      />
      <div className="flex flex-col gap-6">
        <PeriodPicker state={state} onChange={change} problem={problem || null} />
        {(valid || storeToday === null) && <ReportBody sales={sales} top={top} groupBy={state.groupBy} sort={sort} onSort={setSort} waiting={storeToday === null} />}
      </div>
    </>
  );
}

type SalesQuery = ReturnType<typeof useSalesReport>;
type TopQuery = ReturnType<typeof useTopProducts>;

function ReportBody({ sales, top, groupBy, sort, onSort, waiting }: { sales: SalesQuery; top: TopQuery; groupBy: PeriodState['groupBy']; sort: TopProductsSort; onSort: (sort: TopProductsSort) => void; waiting: boolean }) {
  const { t, language } = useI18n();

  if (sales.isError && !sales.data) {
    const status = getApiErrorStatus(sales.error);
    const serverText = status === 403 || status === 422 ? getApiErrorMessage(sales.error) : undefined;
    return <InlineError title={t('loadErrorTitle')} message={serverText ?? t('loadErrorText')} retryLabel={t('retry')} retrying={sales.isFetching} onRetry={() => void sales.refetch()} />;
  }
  if (!sales.data) return waiting || sales.isLoading ? <ReportSkeleton /> : null;

  const report = sales.data;
  const { summary } = report;

  if (summary.orders_count === 0) return <EmptyState icon={ChartColumn} title={t('report_emptyTitle')} text={t('report_emptyText')} />;

  return (
    // Another period of the same branch loading: the figures on screen are the previous period's, dimmed (another
    // branch's figures are never kept: useSalesReport)
    <div className={cx('flex flex-col gap-6', sales.isPlaceholderData && 'opacity-60 transition-opacity')} aria-busy={sales.isPlaceholderData || undefined}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <Kpi label={t('report_kpi_revenue')} value={formatMoney(summary.revenue, language)} note={t('report_kpi_revenueNote')} tone="brand" />
        <Kpi label={t('report_kpi_orders')} value={formatNumber(summary.orders_count, language)} note={t('report_kpi_ordersNote', { n: formatNumber(summary.delivered_count, language) })} />
        <Kpi label={t('report_kpi_average')} value={formatMoney(summary.average_check, language)} note={t('report_kpi_averageNote')} />
        <Kpi label={t('report_kpi_canceled')} value={formatNumber(summary.canceled_count, language)} note={t('report_kpi_canceledNote', { n: formatNumber(summary.refunded_count, language) })} />
      </div>

      <RevenueChart series={report.series} groupBy={groupBy} />

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Breakdown title={t('report_byChannel')} rows={report.by_channel.map((row) => ({ key: row.channel, label: row.label, orders: row.orders, revenue: row.revenue }))} />
        <Breakdown title={t('report_byBranch')} rows={report.by_branch.map((row) => ({ key: String(row.branch_id ?? 'none'), label: row.name ?? t('report_noBranch'), orders: row.orders, revenue: row.revenue }))} />
        <Breakdown title={t('report_byPayment')} rows={report.by_payment_method.map((row) => ({ key: row.method, label: paymentLabel(language, row.method), orders: row.orders, revenue: row.revenue }))} />
        <Breakdown title={t('report_byType')} rows={report.by_order_type.map((row) => ({ key: row.type, label: orderTypeLabel(language, row.type), orders: row.orders, revenue: row.revenue }))} />
      </div>

      <TopProducts report={top.data} sort={sort} onSort={onSort} />
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

function ReportSkeleton() {
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
      <Skeleton className="h-64 w-full" />
      <CardSkeletons count={2} lines={3} />
    </div>
  );
}
