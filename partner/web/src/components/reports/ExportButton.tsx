'use client';

import { useState } from 'react';
import { Download } from 'lucide-react';
import { Button, cx, fieldClass } from '@rizqim/ui';
import { getApiErrorMessage, ReportDownloadError, useDownloadReport, type ReportDownloadQuery, type ReportGroupBy, type ReportKind, type ReportPeriodQuery, type TopProductsSort } from '@rizqim/staff';
import type { Translate } from '@/i18n';
import { useI18n } from '../I18nProvider';
import { saveBlob } from './download';

const KINDS: readonly ReportKind[] = ['orders', 'sales', 'products'];

// What to say about a refused download: the machine reasons first (the limits the server names), then its own words
export function exportErrorMessage(error: unknown, t: Translate): string {
  if (error instanceof ReportDownloadError) {
    if (error.tooManyRows) return t('report_exportTooMany', { limit: error.limit ?? 50000 });
    if (error.rateLimited) return error.retryAfter ? t('report_exportRate', { seconds: error.retryAfter }) : t('report_exportRateNoWait');
    if (error.status === 403) return t('report_exportForbidden');
    if (error.status === 422) return error.fieldError('to') ?? error.fieldError('from') ?? getApiErrorMessage(error) ?? t('report_exportFailed');
  }
  return t('report_exportFailed');
}

// "CSV yuklab olish": the kind of file and the button. The file is fetched with the session's token and saved by the browser.
export function ExportButton({ period }: { period: (ReportPeriodQuery & { group_by?: ReportGroupBy; sort?: TopProductsSort }) | null }) {
  const { t } = useI18n();
  const [kind, setKind] = useState<ReportKind>('sales');
  const [notice, setNotice] = useState<{ tone: 'ok' | 'error'; text: string } | null>(null);
  const download = useDownloadReport({
    onSuccess: ({ blob, filename }) => {
      saveBlob(blob, filename);
      setNotice({ tone: 'ok', text: t('report_exportDone', { name: filename }) });
    },
    onError: (error) => setNotice({ tone: 'error', text: exportErrorMessage(error, t) }),
  });

  return (
    <div className="flex flex-col items-end gap-2">
      <div className="flex flex-wrap items-center justify-end gap-2">
        <div className="w-40">
          <select
            aria-label={t('report_exportKind')}
            className={fieldClass}
            value={kind}
            onChange={(event) => {
              setKind(event.target.value as ReportKind);
              setNotice(null);
            }}
          >
            {KINDS.map((option) => (
              <option key={option} value={option}>
                {t(`report_kind_${option}` as const)}
              </option>
            ))}
          </select>
        </div>
        <Button
          variant="secondary"
          icon={Download}
          loading={download.isPending}
          disabled={period === null}
          onClick={() => {
            if (!period) return;
            setNotice(null);
            // The grouping belongs to the sales file, the sort to the items file; the orders file takes neither
            const { group_by: groupBy, sort, ...base } = period;
            const query: ReportDownloadQuery = { ...base, report: kind };
            if (kind === 'sales' && groupBy) query.group_by = groupBy;
            if (kind === 'products' && sort) query.sort = sort;
            download.mutate(query);
          }}
        >
          {download.isPending ? t('report_exporting') : t('report_export')}
        </Button>
      </div>
      {notice && (
        <p role={notice.tone === 'error' ? 'alert' : 'status'} className={cx('max-w-md text-right text-footnote font-bold', notice.tone === 'error' ? 'text-danger-soft-fg' : 'text-success-soft-fg')}>
          {notice.text}
        </p>
      )}
    </div>
  );
}
