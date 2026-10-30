'use client';

import { useState } from 'react';
import { Hourglass } from 'lucide-react';
import { Card, CardSkeletons, EmptyState, InlineError, PageHeader } from '@rizqim/ui';
import { useKitchenStations } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { describeError } from '../menu/serverError';
import { DateField, SelectField } from '../pos/fields';
import { labelFor } from '../reports/format';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import type { PrepQuery, PrepStats, PrepTimeReport } from './api';
import { formatDuration } from './format';
import { usePrepTimes } from './hooks';

const ALL = '';

// The calendar day on this device (the report asks the server for business days; today is the default)
function today(): string {
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

// Tayyorlash vaqti (P6-33): how long the kitchen takes per station, dish and hour, from the KDS bumps. The server works out
// every figure (median, p90, over-target counts); this page only lays them out.
export function KitchenTimesView() {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const [from, setFrom] = useState(today);
  const [to, setTo] = useState(today);
  const [station, setStation] = useState<string>(ALL);
  // The station list is the owner's setup (settings.manage); a manager who only reads reports sees every station's rows
  const access = useCan();
  const canStations = access.can('settings.manage');
  const stations = useKitchenStations(choice.branchId ?? undefined, { enabled: choice.branchId !== null && canStations, retry: false });

  const query: PrepQuery | null =
    choice.branchId !== null && from !== '' && to !== '' ? { branchId: choice.branchId, from, to, stationId: station === ALL ? null : Number(station) } : null;

  return (
    <>
      <PageHeader title={t('kds_report_title')} subtitle={t('kds_report_subtitle')} />
      {choice.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={Hourglass} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-end gap-4">
            <BranchSelect
              branches={choice.branches}
              value={choice.branchId}
              onChange={(id) => {
                setStation(ALL);
                choice.choose(id);
              }}
              className="min-w-52 max-w-xs"
            />
            <DateField label={t('kds_report_from')} value={from} max={to || undefined} onChange={setFrom} />
            <DateField label={t('kds_report_to')} value={to} min={from || undefined} onChange={setTo} />
            {canStations && (
              <SelectField
                label={t('kds_report_station')}
                value={station}
                onChange={setStation}
                className="min-w-48"
                options={[{ value: ALL, label: t('kds_report_allStations') }, ...(stations.data?.stations ?? []).map((row) => ({ value: String(row.id), label: row.name }))]}
              />
            )}
          </div>
          <ReportPanel query={query} />
        </div>
      )}
    </>
  );
}

function ReportPanel({ query }: { query: PrepQuery | null }) {
  const { t } = useI18n();
  const report = usePrepTimes(query);

  if (query === null) return null;
  if (report.isPending) return <CardSkeletons count={3} lines={3} label={t('loading')} />;
  if (report.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(report.error, t).message} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />;

  const data: PrepTimeReport = report.data;
  if (data.summary.count === 0 && data.orders.count === 0 && (data.expo?.count ?? 0) === 0) {
    return <EmptyState icon={Hourglass} title={t('kds_report_empty')} text={t('kds_report_emptyText')} />;
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">
        {t('kds_report_legend')} {t('kds_report_period', { zone: data.timezone, start: data.day_starts_at })}
      </p>

      <div className="grid gap-4 md:grid-cols-3">
        <StatsCard title={t('kds_report_lines')} stats={data.summary} />
        <StatsCard title={t('kds_report_orders')} hint={t('kds_report_ordersHint')} stats={data.orders} />
        {data.expo && <StatsCard title={`${t('kds_report_expo')} · ${data.expo.name}`} hint={t('kds_report_expoHint')} stats={data.expo} target={data.expo.target_seconds} />}
      </div>

      {data.stations.length > 0 && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('kds_report_byStation')}</h2>
          <DataTable caption={t('kds_report_byStation')}>
            <thead>
              <tr>
                <Th>{t('kds_col_station')}</Th>
                <Th numeric>{t('kds_col_target')}</Th>
                <StatsHeads />
              </tr>
            </thead>
            <tbody>
              {data.stations.map((row) => (
                <tr key={row.station_id ?? 'none'}>
                  <Td className="font-bold">
                    {row.station_id === null ? t('kds_noStation') : row.name}
                    {row.kind ? <span className="block text-caption font-normal text-fg-muted">{labelFor(t, 'pos_kind_', row.kind)}</span> : null}
                  </Td>
                  <Td numeric>{formatDuration(row.target_seconds, t)}</Td>
                  <StatsCells stats={row} />
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}

      {data.products.length > 0 && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('kds_report_byProduct')}</h2>
          <DataTable caption={t('kds_report_byProduct')}>
            <thead>
              <tr>
                <Th>{t('kds_col_dish')}</Th>
                <StatsHeads />
              </tr>
            </thead>
            <tbody>
              {data.products.map((row) => (
                <tr key={row.product_id ?? `none-${row.name}`}>
                  <Td>{row.name}</Td>
                  <StatsCells stats={row} />
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}

      {data.hours.some((row) => row.count > 0) && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('kds_report_byHour')}</h2>
          <DataTable caption={t('kds_report_byHour')}>
            <thead>
              <tr>
                <Th>{t('kds_col_hour')}</Th>
                <StatsHeads />
              </tr>
            </thead>
            <tbody>
              {data.hours
                .filter((row) => row.count > 0)
                .map((row) => (
                  <tr key={row.hour}>
                    <Td className="whitespace-nowrap">{t('kds_hour', { from: String(row.hour).padStart(2, '0'), to: String((row.hour + 1) % 24).padStart(2, '0') })}</Td>
                    <StatsCells stats={row} />
                  </tr>
                ))}
            </tbody>
          </DataTable>
        </Card>
      )}
    </div>
  );
}

function StatsCard({ title, hint, stats, target }: { title: string; hint?: string; stats: PrepStats; target?: number | null }) {
  const { t } = useI18n();
  return (
    <Card className="flex flex-col gap-2">
      <h2 className="text-headline font-bold text-fg">{title}</h2>
      {hint ? <p className="text-footnote text-fg-muted">{hint}</p> : null}
      <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-body">
        <dt className="text-fg-muted">{t('kds_col_count')}</dt>
        <dd className="text-right font-bold text-fg">{stats.count}</dd>
        <dt className="text-fg-muted">{t('kds_report_median')}</dt>
        <dd className="text-right font-bold text-fg">{formatDuration(stats.median_seconds, t)}</dd>
        <dt className="text-fg-muted">{t('kds_report_p90')}</dt>
        <dd className="text-right font-bold text-fg">{formatDuration(stats.p90_seconds, t)}</dd>
        {target !== undefined && (
          <>
            <dt className="text-fg-muted">{t('kds_col_target')}</dt>
            <dd className="text-right text-fg">{formatDuration(target, t)}</dd>
          </>
        )}
        {stats.with_target > 0 && (
          <>
            <dt className="text-fg-muted">{t('kds_report_over')}</dt>
            <dd className="text-right">
              <OverPill stats={stats} />
            </dd>
          </>
        )}
      </dl>
    </Card>
  );
}

function StatsHeads() {
  const { t } = useI18n();
  return (
    <>
      <Th numeric>{t('kds_col_count')}</Th>
      <Th numeric>{t('kds_report_median')}</Th>
      <Th numeric>{t('kds_report_p90')}</Th>
      <Th numeric>{t('kds_report_over')}</Th>
    </>
  );
}

function StatsCells({ stats }: { stats: PrepStats }) {
  const { t } = useI18n();
  return (
    <>
      <Td numeric>{stats.count}</Td>
      <Td numeric className="font-bold">{formatDuration(stats.median_seconds, t)}</Td>
      <Td numeric>{formatDuration(stats.p90_seconds, t)}</Td>
      <Td numeric>{stats.with_target > 0 ? <OverPill stats={stats} /> : '—'}</Td>
    </>
  );
}

// "2 / 6 (33,3%)": the colour only backs the numbers up
function OverPill({ stats }: { stats: PrepStats }) {
  const { t } = useI18n();
  const share = Number(stats.over_target_share ?? '0');
  const tone = stats.over_target === 0 ? 'success' : share > 10 ? 'danger' : 'warning';
  return <Pill tone={tone}>{t('kds_report_overValue', { n: stats.over_target, of: stats.with_target, share: `${(stats.over_target_share ?? '0').replace('.', ',')}%` })}</Pill>;
}
