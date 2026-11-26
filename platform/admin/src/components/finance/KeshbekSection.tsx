'use client';

import { useState } from 'react';
import { FileSpreadsheet } from 'lucide-react';
import { Button, Card, CardSkeletons, FormError, InlineError } from '@rizqim/ui';
import { saveFile } from '@/lib/api/client';
import { downloadKeshbekReport, useKeshbekReport, type KeshbekMovement } from '@/lib/api/finance';
import { errorText } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { uzs } from './financeModel';
import { Row } from './ReasonDialog';

const isZero = (amount: string) => /^-?0+(?:\.0+)?$/.test(amount.trim());

// Keshbek in the monthly report: the month's movements (read back from their records), the balances customers hold now
// (the platform's liability) and the reconciliation of the movements with SUM(users.balance); a non-zero difference
// means a balance changed without a record and is shown in red, never hidden.
export function KeshbekSection({ period, enabled, canExport, onDone }: { period: string; enabled: boolean; canExport: boolean; onDone: (message: string) => void }) {
  const t = useT();
  const report = useKeshbekReport(period, enabled);
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);
  const data = report.data;
  const matched = data ? isZero(data.reconciliation.difference) : true;

  const exportFile = async () => {
    setExporting(true);
    setExportError(null);
    try {
      saveFile(await downloadKeshbekReport(period));
      onDone(t('finance_reportExported'));
    } catch (error) {
      setExportError(errorText(error, t));
    } finally {
      setExporting(false);
    }
  };

  const movement = (label: string, source: KeshbekMovement, sign: '+' | '−' | '') => (
    <Row
      label={
        <span className="flex flex-col">
          <span className="text-footnote text-fg">{label}</span>
          <span className="text-caption text-fg-muted">{t('finance_keshbekCount', { count: source.count })}</span>
        </span>
      }
      value={`${sign && !isZero(source.amount) ? `${sign} ` : ''}${uzs(source.amount)}`}
    />
  );

  return (
    <section aria-label={t('finance_keshbekTitle')} className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2 px-1">
        <h3 className="text-footnote font-bold text-fg-muted">{t('finance_keshbekTitle')}</h3>
        {canExport && (
          <Button variant="secondary" icon={FileSpreadsheet} loading={exporting} disabled={!data} onClick={() => void exportFile()}>
            {t('finance_keshbekExport')}
          </Button>
        )}
      </div>
      <FormError message={exportError} />
      {report.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(report.error, t)} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
      ) : !data ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Card className="flex flex-col gap-1">
              <span className="text-footnote font-bold text-fg-muted">{t('finance_keshbekLiability')}</span>
              <span className="text-title font-bold text-fg" data-testid="keshbek-liability">
                {uzs(data.outstanding.positive)}
              </span>
              <span className="text-caption text-fg-muted">{t('finance_keshbekHolders', { count: data.outstanding.holders })}</span>
              {data.outstanding.debtors > 0 && (
                <span className="text-caption text-fg-muted" data-testid="keshbek-debts">
                  {t('finance_keshbekDebts', { amount: uzs(data.outstanding.negative), count: data.outstanding.debtors })}
                </span>
              )}
              <span className="text-caption text-fg-muted">{t('finance_keshbekSnapshot')}</span>
            </Card>
            <Card className="flex flex-col gap-1" data-testid="keshbek-difference" data-mismatch={matched ? undefined : 'true'}>
              <span className="text-footnote font-bold text-fg-muted">{t('finance_keshbekDifference')}</span>
              {matched ? (
                <>
                  <span className="text-headline font-bold text-fg">{uzs(data.reconciliation.difference)}</span>
                  <span className="text-caption text-fg-muted">{t('finance_keshbekDifferenceZero')}</span>
                </>
              ) : (
                <div role="alert" className="flex flex-col gap-1 rounded-control bg-danger-soft px-3 py-2 text-danger-soft-fg">
                  <span className="text-headline font-bold">{uzs(data.reconciliation.difference)}</span>
                  <span className="text-caption">{t('finance_keshbekDifferenceNonZero')}</span>
                  {data.unrecorded.keshbek_orders_without_payment > 0 && (
                    <span className="text-caption">{t('finance_keshbekUnrecorded', { count: data.unrecorded.keshbek_orders_without_payment })}</span>
                  )}
                </div>
              )}
            </Card>
          </div>

          <Card>
            <dl className="divide-y divide-line" aria-label={t('finance_keshbekMovements')}>
              {movement(t('finance_keshbekIssued'), data.issued, '+')}
              {movement(t('finance_keshbekClawedBack'), data.clawed_back, '−')}
              {movement(t('finance_keshbekLegacy'), data.legacy_store_paid, '+')}
              {movement(t('finance_keshbekSpent'), data.spent, '−')}
              {movement(t('finance_keshbekReturned'), data.returned, '+')}
            </dl>
          </Card>

          <Card>
            <dl className="divide-y divide-line" aria-label={t('finance_keshbekReconciliation')}>
              <Row label={t('finance_keshbekOpening')} value={uzs(data.reconciliation.opening)} />
              <Row label={t('finance_keshbekMovement')} value={uzs(data.reconciliation.movement)} />
              <Row label={t('finance_keshbekClosing')} value={uzs(data.reconciliation.closing)} strong />
              <Row label={t('finance_keshbekAfter')} value={uzs(data.reconciliation.after_period)} />
              <Row label={t('finance_keshbekExpected')} value={uzs(data.reconciliation.expected_now)} />
              <Row label={t('finance_keshbekLive')} value={uzs(data.reconciliation.live)} strong />
            </dl>
          </Card>
          <p className="text-caption text-fg-muted">{t('finance_keshbekHint')}</p>
        </>
      )}
    </section>
  );
}
