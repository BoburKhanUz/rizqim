'use client';

import { useState } from 'react';
import { FileSpreadsheet, LineChart } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, Field, fieldClass, FormError, InlineError } from '@rizqim/ui';
import { saveFile } from '@/lib/api/client';
import { downloadMonthlyReport, useMonthlyReport } from '@/lib/api/finance';
import { errorText } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { uzs } from './financeModel';
import { Row } from './ReasonDialog';

// "YYYY-MM" of the month a moment falls in, in Tashkent (the report's months are Tashkent months)
export function tashkentMonth(at = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tashkent', year: 'numeric', month: '2-digit' }).formatToParts(at);
  const part = (type: string) => parts.find((item) => item.type === type)?.value ?? '';
  return `${part('year')}-${part('month')}`;
}

// Oylik hisobot: RIZQIM's revenue for a month — commission by channel and subscriptions — and per store, with the Excel
// export for members with admin.export.
export function ReportPanel({ enabled, canExport, onDone }: { enabled: boolean; canExport: boolean; onDone: (message: string) => void }) {
  const t = useT();
  const [period, setPeriod] = useState(() => tashkentMonth());
  const report = useMonthlyReport(period, enabled);
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);
  const data = report.data;

  const exportFile = async () => {
    setExporting(true);
    setExportError(null);
    try {
      saveFile(await downloadMonthlyReport(period));
      onDone(t('finance_reportExported'));
    } catch (error) {
      setExportError(errorText(error, t));
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div className="flex flex-wrap items-end gap-3">
        <Field label={t('finance_reportMonth')} className="w-full sm:w-56">
          {(control) => <input {...control} type="month" className={fieldClass} value={period} max={tashkentMonth()} onChange={(event) => setPeriod(event.target.value)} />}
        </Field>
        {canExport && (
          <Button variant="secondary" icon={FileSpreadsheet} loading={exporting} disabled={!data} onClick={() => void exportFile()}>
            {t('finance_reportExport')}
          </Button>
        )}
      </div>
      <FormError message={exportError} />
      <p className="text-caption text-fg-muted">{t('finance_reportHint')}</p>
      {report.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(report.error, t)} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
      ) : !data ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Card className="flex flex-col gap-1">
              <span className="text-footnote font-bold text-fg-muted">{t('finance_reportTotal')}</span>
              <span className="text-title font-bold text-fg" data-testid="report-total">
                {uzs(data.totals.total)}
              </span>
            </Card>
            <Card className="flex flex-col gap-1">
              <span className="text-footnote font-bold text-fg-muted">{t('finance_reportCommission')}</span>
              <span className="text-headline font-bold text-fg">{uzs(data.totals.commission)}</span>
              <span className="text-caption text-fg-muted">{t('finance_reportOrders', { count: data.totals.orders, gmv: uzs(data.totals.gmv) })}</span>
              {data.totals.commission_refunded !== '0.00' && <span className="text-caption text-fg-muted">{t('finance_reportRefunded', { amount: uzs(data.totals.commission_refunded) })}</span>}
            </Card>
            <Card className="flex flex-col gap-1">
              <span className="text-footnote font-bold text-fg-muted">{t('finance_reportSubscription')}</span>
              <span className="text-headline font-bold text-fg">{uzs(data.totals.subscription)}</span>
            </Card>
          </div>

          <section aria-label={t('finance_reportChannels')}>
            <h3 className="mb-2 px-1 text-footnote font-bold text-fg-muted">{t('finance_reportChannels')}</h3>
            <Card>
              <dl className="divide-y divide-line">
                {data.channels.map((channel) => (
                  <Row
                    key={channel.channel}
                    label={
                      <span className="flex flex-col">
                        <span className="text-footnote font-bold text-fg">{channel.label}</span>
                        <span className="text-caption text-fg-muted">{t('finance_reportChannelRow', { orders: channel.orders, gmv: uzs(channel.gmv) })}</span>
                      </span>
                    }
                    value={uzs(channel.commission)}
                  />
                ))}
              </dl>
            </Card>
          </section>

          <section aria-label={t('finance_reportInvoices')}>
            <h3 className="mb-2 px-1 text-footnote font-bold text-fg-muted">{t('finance_reportInvoices')}</h3>
            <Card>
              <dl className="divide-y divide-line">
                <Row label={t('finance_reportInvoiced')} value={uzs(data.subscriptions.invoiced)} strong />
                <Row label={t('finance_reportPaid')} value={uzs(data.subscriptions.paid)} />
                <Row label={t('finance_reportOutstanding')} value={uzs(data.subscriptions.outstanding)} />
              </dl>
            </Card>
          </section>

          <section aria-label={t('finance_reportStores')}>
            <h3 className="mb-2 px-1 text-footnote font-bold text-fg-muted">{t('finance_reportStores')}</h3>
            {data.stores.length === 0 ? (
              <EmptyState icon={LineChart} title={t('finance_reportStoresEmpty')} />
            ) : (
              <Card padded={false}>
                <ul className="divide-y divide-line">
                  {data.stores.map((row) => (
                    <li key={row.store.id} className="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                      <span className="flex min-w-0 flex-col">
                        <span className="break-words text-footnote font-bold text-fg">{row.store.name}</span>
                        <span className="text-caption text-fg-muted">{t('finance_reportStoreRow', { commission: uzs(row.commission), subscription: uzs(row.subscription) })}</span>
                      </span>
                      <span className="text-body font-bold text-fg">{uzs(row.total)}</span>
                    </li>
                  ))}
                </ul>
              </Card>
            )}
          </section>
        </>
      )}
    </div>
  );
}
