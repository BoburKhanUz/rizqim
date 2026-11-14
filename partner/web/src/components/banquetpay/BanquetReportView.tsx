'use client';

import { useState } from 'react';
import Link from 'next/link';
import { CalendarHeart, Download } from 'lucide-react';
import { Button, Card, EmptyState, InlineError, Notice, PageHeader, Skeleton, formatDate, formatMoney } from '@rizqim/ui';
import { todayIn } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DateField } from '../pos/fields';
import { saveBlob } from '../reports/download';
import { DataTable, Pill, Td, Th, type Tone } from '../reports/ui';
import type { EventStatus, ReportRange } from './api';
import { useBanquetReport, useDownloadBanquetReport } from './hooks';
import { PaymentRulesCard } from './PaymentRulesCard';

const TONES: Record<EventStatus, Tone> = { hold: 'warning', confirmed: 'info', in_progress: 'info', settled: 'success', cancelled: 'neutral' };

function monthOf(today: string): { from: string; to: string } {
  const [year = 2026, month = 1] = today.split('-').map(Number);
  const last = new Date(Date.UTC(year, month, 0)).getUTCDate();
  const mm = String(month).padStart(2, '0');
  return { from: `${year}-${mm}-01`, to: `${year}-${mm}-${String(last).padStart(2, '0')}` };
}

// /banquets/report "Tadbirlar hisoboti" (P6-53): the events of a period — guests, total, paid, outstanding, overdue, the
// cancelled ones with their retained and refunded parts — and the Excel file (banquets.manage or reports.view; phones
// masked for a report-only reader); the payment and cancellation rules below for banquets.manage.
export function BanquetReportView() {
  const { t, language } = useI18n();
  const access = useCan();
  const canRead = access.can('banquets.manage') || access.can('reports.view');
  const canManage = access.can('banquets.manage');
  const month = monthOf(todayIn('Asia/Tashkent'));
  const [from, setFrom] = useState(month.from);
  const [to, setTo] = useState(month.to);
  const [notice, setNotice] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const reversed = from !== '' && to !== '' && from > to;
  const range: ReportRange = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const report = useBanquetReport(range, canRead && !reversed);
  const download = useDownloadBanquetReport({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice(t('bqp_exportDone', { name: filename }));
    },
    onError: () => setExportError(t('bqp_exportFailed')),
  });
  const money = (value: string) => formatMoney(value, language);

  if (access.ready && !canRead) {
    return (
      <>
        <PageHeader title={t('bqp_reportTitle')} />
        <EmptyState icon={CalendarHeart} title={t('mgmt_forbidden')} text={t('bqp_noAccess')} />
      </>
    );
  }

  const data = report.data;

  return (
    <>
      <PageHeader
        title={t('bqp_reportTitle')}
        subtitle={t('bqp_reportSubtitle')}
        action={
          <Button
            variant="secondary"
            icon={Download}
            loading={download.isPending}
            disabled={reversed}
            onClick={() => {
              setExportError(null);
              setNotice(null);
              download.mutate(range);
            }}
          >
            {download.isPending ? t('bqp_exporting') : t('bqp_export')}
          </Button>
        }
      />
      <Notice message={notice} />
      {exportError && (
        <p role="alert" className="mb-4 text-footnote font-bold text-danger-soft-fg">
          {exportError}
        </p>
      )}

      <Card className="mb-4 flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('report_from')} value={from} onChange={setFrom} />
          <DateField label={t('report_to')} value={to} onChange={setTo} />
        </div>
        {reversed && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {t('report_problem_reversed')}
          </p>
        )}
        {report.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(report.error, t)} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {data
              ? [
                  { label: t('bqp_events'), amount: data.total, hint: t('bqp_eventsN', { n: data.events, guests: data.guests }) },
                  { label: t('bqp_paid'), amount: data.paid },
                  { label: t('bqp_outstanding'), amount: data.outstanding, hint: t('bqp_overdueN', { n: data.overdue.count, amount: money(data.overdue.amount) }) },
                  { label: t('bqp_retainedRefunded'), amount: data.retained, hint: `${t('bqp_cancelledN', { n: data.cancelled })} · ${t('bqp_refund')}: ${money(data.refunded)}` },
                ].map((card) => (
                  <div key={card.label} className="flex flex-col gap-1 rounded-control border border-line p-3">
                    <span className="text-footnote font-bold text-fg-muted">{card.label}</span>
                    <span className="text-headline font-extrabold text-fg">{money(card.amount)}</span>
                    {card.hint && <span className="text-footnote text-fg-muted">{card.hint}</span>}
                  </div>
                ))
              : [0, 1, 2, 3].map((n) => <Skeleton key={n} className="h-24 w-full" />)}
          </div>
        )}
      </Card>

      <Card className="mb-4 flex flex-col gap-3">
        {!data ? (
          <Skeleton className="h-32 w-full" />
        ) : data.rows.length === 0 ? (
          <EmptyState icon={CalendarHeart} title={t('bqp_empty')} text={t('bqp_emptyText')} />
        ) : (
          <DataTable caption={t('bqp_events')}>
            <thead>
              <tr>
                <Th>{t('bqp_colDate')}</Th>
                <Th>{t('bqp_colEvent')}</Th>
                <Th>{t('bqp_colCustomer')}</Th>
                <Th numeric>{t('bqp_colGuests')}</Th>
                <Th numeric>{t('bqp_total')}</Th>
                <Th numeric>{t('bqp_paid')}</Th>
                <Th numeric>{t('bqp_outstanding')}</Th>
                <Th numeric>{t('bqp_overdue')}</Th>
                <Th numeric>{t('bqp_retained')}</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {data.rows.map((row) => (
                <tr key={row.uuid}>
                  <Td muted className="whitespace-nowrap">
                    {formatDate(row.event_date, language)}
                  </Td>
                  <Td>
                    <span className="flex flex-col gap-1">
                      <span className="font-bold">{row.title ?? row.event_type}</span>
                      <Pill tone={TONES[row.status]}>{row.status_label}</Pill>
                    </span>
                  </Td>
                  <Td muted>{[row.customer_name, row.customer_phone].filter(Boolean).join(', ')}</Td>
                  <Td numeric>{row.guests}</Td>
                  <Td numeric className="whitespace-nowrap">
                    {money(row.total)}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {money(row.paid)}
                  </Td>
                  <Td numeric className="whitespace-nowrap font-bold">
                    {money(row.outstanding)}
                  </Td>
                  <Td numeric className={Number(row.overdue) > 0 ? 'whitespace-nowrap font-bold text-danger-soft-fg' : 'whitespace-nowrap'}>
                    {money(row.overdue)}
                  </Td>
                  <Td numeric className="whitespace-nowrap">
                    {money(row.retained)}
                  </Td>
                  <Td>
                    <Link href={`/banquets/schedule?event=${row.uuid}`} className="font-bold text-brand underline-offset-2 hover:underline">
                      {t('bqp_openSchedule')}
                    </Link>
                  </Td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        )}
      </Card>

      {canManage && <PaymentRulesCard />}
    </>
  );
}
