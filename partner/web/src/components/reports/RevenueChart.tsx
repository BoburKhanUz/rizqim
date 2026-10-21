'use client';

import { useId, useState } from 'react';
import { Card, cx, formatMoney, formatNumber, toAmount } from '@rizqim/ui';
import type { ReportGroupBy, SalesReport } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { formatBucket } from './format';
import { DataTable, Td, Th } from './ui';

type Point = SalesReport['series'][number];

// Revenue per bucket of the period, as bars. Plain HTML/CSS bars rather than a charting library: one series, one axis,
// so a library would add weight and a second colour system for nothing. The bar colour is the brand token (light and dark
// come with it), a bar's height is its revenue over the largest revenue shown (a display scale only: every figure
// printed is the server's own), and the same numbers are one click away as a table.
export function RevenueChart({ series, groupBy }: { series: readonly Point[]; groupBy: ReportGroupBy }) {
  const { t, language } = useI18n();
  const [view, setView] = useState<'chart' | 'table'>('chart');
  const [active, setActive] = useState<number | null>(null);
  const titleId = useId();

  const values = series.map((point) => toAmount(point.revenue));
  const max = Math.max(0, ...values);
  const label = (point: Point) => formatBucket(point.date, groupBy, language, t);
  const selected = active !== null ? series[active] : undefined;
  // About seven labels under the bars, whatever the number of bars
  const step = Math.max(1, Math.ceil(series.length / 7));

  return (
    <Card className="flex flex-col gap-3" aria-labelledby={titleId}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id={titleId} className="text-headline font-bold text-fg">
          {t('report_chartTitle')}
        </h2>
        <div role="group" aria-label={t('report_chartTitle')} className="flex gap-1 rounded-control bg-surface-2 p-1">
          {(['chart', 'table'] as const).map((mode) => (
            <button
              key={mode}
              type="button"
              aria-pressed={view === mode}
              onClick={() => setView(mode)}
              className={cx(
                'min-h-9 min-w-20 cursor-pointer rounded-control px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-brand',
                view === mode ? 'bg-surface text-fg shadow-sm' : 'text-fg-muted',
              )}
            >
              {mode === 'chart' ? t('report_viewChart') : t('report_viewTable')}
            </button>
          ))}
        </div>
      </div>

      {view === 'table' ? (
        <DataTable caption={t('report_chartTitle')}>
          <thead>
            <tr>
              <Th>{t('report_colDate')}</Th>
              <Th numeric>{t('report_colOrders')}</Th>
              <Th numeric>{t('report_colDelivered')}</Th>
              <Th numeric>{t('report_colRevenue')}</Th>
            </tr>
          </thead>
          <tbody>
            {series.map((point) => (
              <tr key={point.date}>
                <Td>{label(point)}</Td>
                <Td numeric>{formatNumber(point.orders, language)}</Td>
                <Td numeric>{formatNumber(point.delivered, language)}</Td>
                <Td numeric>{formatMoney(point.revenue, language)}</Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      ) : (
        <>
          <p className="min-h-5 text-footnote text-fg-muted" aria-live="polite">
            {selected ? t('report_chartReadout', { date: label(selected), revenue: formatMoney(selected.revenue, language), orders: formatNumber(selected.orders, language) }) : t('report_chartHint')}
          </p>
          <div className="flex flex-col gap-1" role="group" aria-label={t('report_chartAria', { count: series.length })}>
            <p className="text-caption text-fg-muted">{formatMoney(max, language)}</p>
            <div className="relative h-48 border-b border-line" onMouseLeave={() => setActive(null)}>
              <div aria-hidden="true" className="absolute inset-x-0 top-0 border-t border-dashed border-line" />
              <div aria-hidden="true" className="absolute inset-x-0 top-1/2 border-t border-dashed border-line" />
              <div className={cx('relative flex h-full items-stretch', series.length > 45 ? 'gap-px' : 'gap-1')}>
                {series.map((point, index) => {
                  const value = values[index] ?? 0;
                  const height = max > 0 && value > 0 ? Math.max((value / max) * 100, 1.5) : 0;
                  return (
                    <button
                      key={point.date}
                      type="button"
                      aria-label={`${label(point)}: ${formatMoney(point.revenue, language)}, ${t('report_colOrders')}: ${formatNumber(point.orders, language)}`}
                      onMouseEnter={() => setActive(index)}
                      onFocus={() => setActive(index)}
                      onBlur={() => setActive(null)}
                      className="group flex min-w-0 flex-1 cursor-pointer items-end focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand"
                    >
                      <span
                        style={{ height: `${height}%` }}
                        className={cx('w-full rounded-t-[4px] bg-brand transition-opacity', active !== null && active !== index && 'opacity-40')}
                      />
                    </button>
                  );
                })}
              </div>
            </div>
            <div aria-hidden="true" className="relative h-5">
              {series.map((point, index) =>
                index % step === 0 ? (
                  <span
                    key={point.date}
                    style={{ left: `${((index + 0.5) / series.length) * 100}%` }}
                    className={cx('absolute top-1 -translate-x-1/2 text-caption whitespace-nowrap text-fg-muted', index === 0 && 'translate-x-0 [left:0!important]', (index / step) % 2 === 1 && 'max-sm:hidden')}
                  >
                    {label(point)}
                  </span>
                ) : null,
              )}
            </div>
          </div>
        </>
      )}
    </Card>
  );
}
