'use client';

import { useId, useState } from 'react';
import { FileSpreadsheet } from 'lucide-react';
import { Button, cx, formatNumber } from '@rizqim/ui';
import { ApiError, saveFile } from '@/lib/api/client';
import { downloadExport, type ExportList, type ExportQuery } from '@/lib/api/metrics';
import { errorText } from '@/lib/errors';
import { useCan } from './AuthProvider';
import { useT } from './I18nProvider';

// "Excel'ga yuklab olish" (P4-31): the one export button of every list that exports. It asks for the list's file with the
// list's own filters (`query`), hands the .xlsx to the browser and says how many rows it holds, or that the list was cut at
// the cap. Shown only to members with the export permission; the backend checks it, and the list's own view permission,
// again. Phones in the file are masked as in the list unless the member may see them; each export is in the audit log.
export function ExportButton({ list, query, className }: { list: ExportList; query?: ExportQuery; className?: string }) {
  const t = useT();
  const { can } = useCan();
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<{ tone: 'ok' | 'warning' | 'error'; text: string } | null>(null);
  const statusId = useId();

  if (!can('export')) return null;

  const run = async () => {
    setBusy(true);
    setStatus(null);
    try {
      const file = await downloadExport(list, query);
      saveFile(file);
      const rows = file.rows !== undefined ? formatNumber(file.rows, 'uz') : null;
      setStatus(
        file.truncated && rows ? { tone: 'warning', text: t('metrics_exportTruncated', { rows }) } : { tone: 'ok', text: rows ? t('metrics_exportDone', { rows }) : t('metrics_exportDoneNoCount') },
      );
    } catch (error) {
      setStatus({ tone: 'error', text: error instanceof ApiError && error.status === 429 ? t('metrics_exportRate') : errorText(error, t) });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className={cx('flex flex-col items-start gap-1.5', className)}>
      <Button variant="secondary" icon={FileSpreadsheet} loading={busy} onClick={() => void run()} aria-describedby={status ? statusId : undefined} title={t('metrics_exportHint')}>
        {busy ? t('metrics_exporting') : t('metrics_export')}
      </Button>
      {status && (
        <p id={statusId} role={status.tone === 'error' ? 'alert' : 'status'} className={cx('max-w-sm text-footnote font-bold', status.tone === 'error' ? 'text-danger-soft-fg' : status.tone === 'warning' ? 'text-warning-soft-fg' : 'text-success-soft-fg')}>
          {status.text}
        </p>
      )}
    </div>
  );
}
