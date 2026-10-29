'use client';

import { useState } from 'react';
import { Scale } from 'lucide-react';
import { Card, CardSkeletons, EmptyState, formatDateTime, InlineError, PageHeader, Tabs } from '@rizqim/ui';
import { formatQuantity, type MenuUnit } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';
import type { PeriodQuery, YieldReport } from './api';
import { useWriteOffs, useYieldReport } from './hooks';

const asUnit = (unit: string | null): MenuUnit | null => (unit === 'kg' || unit === 'l' || unit === 'piece' ? unit : null);

type TabId = 'yield' | 'writeoffs';

// The calendar day on this device (the report asks the server for business days; today is the default)
function today(): string {
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

// Hosil hisoboti: what each kazan or tandir was cooked, sold and written off, per batch and per day; and the list of
// write-offs with their reasons. The server works out every figure.
export function YieldView() {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const [tab, setTab] = useState<TabId>('yield');
  const [from, setFrom] = useState(today);
  const [to, setTo] = useState(today);

  const query: PeriodQuery | null = choice.branchId !== null && from !== '' && to !== '' ? { branchId: choice.branchId, from, to } : null;

  return (
    <>
      <PageHeader title={t('dm_yieldTitle')} subtitle={t('dm_yieldSubtitle')} />
      {choice.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={Scale} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-end gap-4">
            <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="min-w-52 max-w-xs" />
            <DateField label={t('dm_from')} value={from} max={to || undefined} onChange={setFrom} />
            <DateField label={t('dm_to')} value={to} min={from || undefined} onChange={setTo} />
          </div>
          <Tabs
            idPrefix="dm-yield"
            label={t('dm_yieldTabs')}
            value={tab}
            onChange={setTab}
            tabs={[
              { id: 'yield', label: t('dm_tabYield') },
              { id: 'writeoffs', label: t('dm_tabWriteoffs') },
            ]}
          />
          <div role="tabpanel" id={`dm-yield-panel-${tab}`} aria-labelledby={`dm-yield-tab-${tab}`}>
            {tab === 'yield' ? <YieldPanel query={query} /> : <WriteOffsPanel query={query} />}
          </div>
        </div>
      )}
    </>
  );
}

function YieldPanel({ query }: { query: PeriodQuery | null }) {
  const { t, language } = useI18n();
  const report = useYieldReport(query);

  if (query === null) return null;
  if (report.isPending) return <CardSkeletons count={3} lines={3} label={t('loading')} />;
  if (report.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(report.error, t).message} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />;

  const data: YieldReport = report.data;
  const qty = (value: number, unit: string) => formatQuantity(value, asUnit(unit), language);
  const percent = (value: string | null) => (value === null ? '—' : `${value.replace('.', ',')}%`);
  const variance = (value: number, unit: string) => (value === 0 ? '—' : `${value > 0 ? '+' : '−'}${qty(Math.abs(value), unit)}`);

  if (data.batches.length === 0 && data.in_sets.length === 0) return <EmptyState icon={Scale} title={t('dm_yieldEmptyTitle')} text={t('dm_yieldEmptyText')} />;

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('dm_yieldLegend')}</p>

      <Card className="flex flex-col gap-2">
        <h2 className="text-headline font-bold text-fg">{t('dm_yieldDays')}</h2>
        <DataTable caption={t('dm_yieldDays')}>
          <thead>
            <tr>
              <Th>{t('dm_colDate')}</Th>
              <Th>{t('dm_colDish')}</Th>
              <Th numeric>{t('dm_colProduced')}</Th>
              <Th numeric>{t('dm_colSold')}</Th>
              <Th numeric>{t('dm_colWrittenOff')}</Th>
              <Th numeric>{t('dm_colLeft')}</Th>
              <Th numeric>{t('dm_colVariance')}</Th>
              <Th numeric>{t('dm_colSoldPercent')}</Th>
            </tr>
          </thead>
          <tbody>
            {data.days.map((day) => (
              <tr key={`${day.business_date}-${day.product_id}-${day.unit}`}>
                <Td className="whitespace-nowrap">{formatDay(day.business_date, language)}</Td>
                <Td>{day.product_name}</Td>
                <Td numeric>{qty(day.produced, day.unit)}</Td>
                <Td numeric className="font-bold">{qty(day.sold, day.unit)}</Td>
                <Td numeric>{qty(day.written_off, day.unit)}</Td>
                <Td numeric>{qty(day.left, day.unit)}</Td>
                <Td numeric>{variance(day.variance, day.unit)}</Td>
                <Td numeric>{percent(day.sold_percent)}</Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      <Card className="flex flex-col gap-2">
        <h2 className="text-headline font-bold text-fg">{t('dm_yieldBatches')}</h2>
        <DataTable caption={t('dm_yieldBatches')}>
          <thead>
            <tr>
              <Th>{t('dm_colStarted')}</Th>
              <Th>{t('dm_colDish')}</Th>
              <Th numeric>{t('dm_colProduced')}</Th>
              <Th numeric>{t('dm_colSold')}</Th>
              <Th numeric>{t('dm_colWrittenOff')}</Th>
              <Th numeric>{t('dm_colLeft')}</Th>
              <Th numeric>{t('dm_colVariance')}</Th>
              <Th>{t('dm_colState')}</Th>
            </tr>
          </thead>
          <tbody>
            {data.batches.map((batch) => (
              <tr key={batch.id}>
                <Td muted className="whitespace-nowrap">{formatDateTime(batch.started_at, language)}</Td>
                <Td>
                  {batch.product_name}
                  {batch.variant_name ? ` · ${batch.variant_name}` : ''}
                  {batch.label ? <span className="block text-caption text-fg-muted">{batch.label}</span> : null}
                </Td>
                <Td numeric>{qty(batch.produced, batch.unit)}</Td>
                <Td numeric className="font-bold">{qty(batch.sold, batch.unit)}</Td>
                <Td numeric>{qty(batch.written_off, batch.unit)}</Td>
                <Td numeric>{qty(batch.left, batch.unit)}</Td>
                <Td numeric>{variance(batch.variance, batch.unit)}</Td>
                <Td>{batch.is_open ? t('menu_batch_status_open') : t('menu_batch_status_closed')}</Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {data.write_offs.length > 0 && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('dm_yieldByReason')}</h2>
          <ul>
            {data.write_offs.map((row) => (
              <li key={row.reason} className="flex items-baseline justify-between gap-4 border-b border-line py-1.5 last:border-b-0">
                <span className="text-body text-fg-muted">{row.label}</span>
                <span className="text-body font-bold text-fg">{t('dm_reasonRow', { n: row.count, qty: String(row.quantity).replace('.', ',') })}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {data.in_sets.length > 0 && (
        <Card className="flex flex-col gap-2">
          <h2 className="text-headline font-bold text-fg">{t('dm_yieldInSets')}</h2>
          <p className="text-footnote text-fg-muted">{t('dm_yieldInSetsText')}</p>
          <ul>
            {data.in_sets.map((row) => (
              <li key={`${row.business_date}-${row.product_id}-${row.unit}`} className="flex items-baseline justify-between gap-4 border-b border-line py-1.5 last:border-b-0">
                <span className="text-body text-fg-muted">
                  {formatDay(row.business_date, language)} · {row.name}
                </span>
                <span className="text-body font-bold text-fg">{qty(row.quantity, row.unit)}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  );
}

function WriteOffsPanel({ query }: { query: PeriodQuery | null }) {
  const { t, language } = useI18n();
  const list = useWriteOffs(query);

  if (query === null) return null;
  if (list.isPending) return <CardSkeletons count={3} lines={2} label={t('loading')} />;
  if (list.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(list.error, t).message} retryLabel={t('retry')} retrying={list.isFetching} onRetry={() => void list.refetch()} />;
  if (list.data.write_offs.length === 0) return <EmptyState icon={Scale} title={t('dm_writeOffsEmptyTitle')} text={t('dm_writeOffsEmptyText')} />;

  return (
    <Card className="flex flex-col gap-2">
      <DataTable caption={t('dm_tabWriteoffs')}>
        <thead>
          <tr>
            <Th>{t('dm_colTime')}</Th>
            <Th>{t('dm_colDish')}</Th>
            <Th numeric>{t('dm_colQuantity')}</Th>
            <Th>{t('dm_colReason')}</Th>
            <Th>{t('dm_colWho')}</Th>
          </tr>
        </thead>
        <tbody>
          {list.data.write_offs.map((row) => (
            <tr key={row.uuid}>
              <Td muted className="whitespace-nowrap">{row.created_at ? formatDateTime(row.created_at, language) : '—'}</Td>
              <Td>
                {row.product_name}
                {row.variant_name ? ` · ${row.variant_name}` : ''}
              </Td>
              <Td numeric className="whitespace-nowrap font-bold">{formatQuantity(row.quantity, asUnit(row.unit), language)}</Td>
              <Td>
                {row.reason_label}
                {row.note ? <span className="block text-caption text-fg-muted">{row.note}</span> : null}
              </Td>
              <Td>{row.written_off_by?.name ?? '—'}</Td>
            </tr>
          ))}
        </tbody>
      </DataTable>
    </Card>
  );
}
